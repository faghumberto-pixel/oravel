<?php

namespace App\Services;

use App\Models\TaxaConfiguracao;
use App\Models\Tenant;
use App\Models\TenantEvent;
use App\Models\TenantTaxa;
use App\Models\User;
use App\Support\Precos;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Taxas de configuração opcionais (pagamento único). O valor vem SEMPRE do catálogo no servidor e fica congelado na aprovação;
 * todas as taxas aprovadas de um assinante saem numa única cobrança do Asaas da Oravel, que não mexe na mensalidade nem no bloqueio.
 */
class TaxasService
{
    public function __construct(private ?AsaasService $asaas = null) {}

    private function asaas(): AsaasService
    {
        return $this->asaas ??= app(AsaasService::class);
    }

    /** @return Collection<int, TaxaConfiguracao> */
    public function disponiveis(): Collection
    {
        return TaxaConfiguracao::disponiveis()->get();
    }

    /**
     * Aprova taxas para o assinante (checkout ou Central). Ignora código inexistente/indisponível e taxa já vigente.
     * `$valorPersonalizado` só para a Central (com observação): demais casos usam o catálogo.
     *
     * @param  list<string>  $codigos
     * @return Collection<int, TenantTaxa>
     */
    public function aprovar(Tenant $tenant, array $codigos, string $origem = 'checkout', ?string $ip = null, ?User $por = null, ?float $valorPersonalizado = null, ?string $observacao = null): Collection
    {
        $catalogo = TaxaConfiguracao::disponiveis()->whereIn('codigo', array_values(array_unique($codigos)))->get();

        if ($valorPersonalizado !== null && ($catalogo->count() !== 1 || $valorPersonalizado <= 0 || blank($observacao))) {
            throw new ComplementoException('Valor personalizado exige uma única taxa, valor maior que zero e o motivo.');
        }

        $criadas = collect();

        foreach ($catalogo as $taxa) {
            $jaTem = TenantTaxa::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('taxa_codigo', $taxa->codigo)->where('status', '!=', TenantTaxa::CANCELADA)->exists();

            if ($jaTem) {
                continue;
            }

            $valor = $valorPersonalizado ?? (float) $taxa->valor;

            $linha = TenantTaxa::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id, 'taxa_codigo' => $taxa->codigo, 'nome' => $taxa->nome, 'valor' => $valor, 'origem' => $origem,
                'aprovada_em' => now(), 'aprovada_ip' => $ip, 'status' => TenantTaxa::APROVADA, 'observacao' => $observacao,
            ]);

            TenantTimeline::record($tenant, TenantEvent::TAXA_APROVADA, 'Taxa aprovada: '.$taxa->nome, Precos::brl($valor).' (pagamento único)'.($observacao ? ' — '.$observacao : '').'.',
                ['taxa_id' => $linha->id, 'codigo' => $taxa->codigo, 'origem' => $origem], dedupeKey: 'taxa-aprovada:'.$linha->id);

            $criadas->push($linha);
        }

        return $criadas;
    }

    /**
     * Gera UMA cobrança com todas as taxas aprovadas ainda sem cobrança. Devolve o link ou null (sem taxas, sem conta no Asaas ou falha).
     * Idempotente: linhas já cobradas não entram de novo.
     */
    public function cobrar(Tenant $tenant): ?string
    {
        $linhas = TenantTaxa::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('status', TenantTaxa::APROVADA)->whereNull('asaas_payment_id')->get();

        if ($linhas->isEmpty() || blank($tenant->asaas_customer_id)) {
            return null;
        }

        $total = round((float) $linhas->sum('valor'), 2);

        try {
            $pagamento = $this->asaas()->createPayment([
                'customer' => $tenant->asaas_customer_id,
                'billingType' => 'UNDEFINED',
                'value' => $total,
                'dueDate' => now()->toDateString(),
                'description' => 'Taxas de configuração Oravel ('.$linhas->pluck('nome')->implode(', ').') -- '.$tenant->name,
                'externalReference' => 'taxas:'.$tenant->id.':'.now()->timestamp,
            ]);
        } catch (\Throwable $e) {
            Log::warning('TaxasService: falha ao criar a cobrança das taxas.', ['tenant_id' => $tenant->id, 'error' => $e->getMessage()]);

            return null;
        }

        if (blank($pagamento['id'] ?? null)) {
            return null;
        }

        TenantTaxa::withoutGlobalScopes()->whereIn('id', $linhas->pluck('id'))->update([
            'status' => TenantTaxa::COBRADA, 'asaas_payment_id' => $pagamento['id'], 'invoice_url' => $pagamento['invoiceUrl'] ?? null,
        ]);

        TenantTimeline::record($tenant, TenantEvent::TAXA_COBRADA, 'Taxas de configuração cobradas', Precos::brl($total).' em pagamento único: '.$linhas->pluck('nome')->implode(', ').'.',
            ['payment_id' => $pagamento['id'], 'invoice_url' => $pagamento['invoiceUrl'] ?? null], dedupeKey: 'taxa-cobrada:'.$pagamento['id']);

        return $pagamento['invoiceUrl'] ?? null;
    }

    /** Webhook: as taxas dessa cobrança foram pagas. Idempotente. */
    public function marcarPagas(string $paymentId, array $payment = []): int
    {
        $linhas = TenantTaxa::withoutGlobalScopes()->where('asaas_payment_id', $paymentId)->where('status', '!=', TenantTaxa::PAGA)->where('status', '!=', TenantTaxa::CANCELADA)->get();

        foreach ($linhas as $linha) {
            $linha->update(['status' => TenantTaxa::PAGA, 'paid_at' => now()]);
        }

        if ($linhas->isNotEmpty()) {
            TenantTimeline::record($linhas->first()->tenant_id, TenantEvent::TAXA_PAGA, 'Taxas de configuração pagas', Precos::brl((float) $linhas->sum('valor')).' recebidos.',
                ['payment_id' => $paymentId], dedupeKey: 'taxa-paga:'.$paymentId, notify: true, level: 'success');
        }

        return $linhas->count();
    }

    /** Cobrança apagada/estornada no Asaas: as taxas voltam a "aprovada" para poderem ser cobradas de novo. */
    public function reabrir(string $paymentId): int
    {
        return TenantTaxa::withoutGlobalScopes()->where('asaas_payment_id', $paymentId)->where('status', TenantTaxa::COBRADA)
            ->update(['status' => TenantTaxa::APROVADA, 'asaas_payment_id' => null, 'invoice_url' => null]);
    }

    /**
     * Cancela uma taxa ainda não paga. Se já estava cobrada, a cobrança inteira é cancelada no Asaas e as OUTRAS taxas dela voltam
     * a "aprovada" (para sair em uma nova cobrança).
     */
    public function cancelar(TenantTaxa $taxa): TenantTaxa
    {
        if (! in_array($taxa->status, [TenantTaxa::APROVADA, TenantTaxa::COBRADA], true)) {
            throw new ComplementoException('Só é possível cancelar uma taxa que ainda não foi paga.');
        }

        DB::transaction(function () use ($taxa) {
            if ($taxa->status === TenantTaxa::COBRADA && filled($taxa->asaas_payment_id)) {
                $pagamento = $taxa->asaas_payment_id;

                if (! $this->asaas()->cancelPayment($pagamento)) {
                    throw new ComplementoException('Não foi possível cancelar a cobrança no Asaas.');
                }

                TenantTaxa::withoutGlobalScopes()->where('asaas_payment_id', $pagamento)->where('id', '!=', $taxa->id)->where('status', TenantTaxa::COBRADA)
                    ->update(['status' => TenantTaxa::APROVADA, 'asaas_payment_id' => null, 'invoice_url' => null]);
            }

            $taxa->update(['status' => TenantTaxa::CANCELADA, 'asaas_payment_id' => null, 'invoice_url' => null]);
        });

        TenantTimeline::record($taxa->tenant_id, TenantEvent::TAXA_CANCELADA, 'Taxa cancelada: '.$taxa->nome, Precos::brl((float) $taxa->valor).'.', ['taxa_id' => $taxa->id]);

        return $taxa;
    }
}
