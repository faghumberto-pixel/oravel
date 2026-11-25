<?php

namespace App\Domain\Intelligence\Trial\Services;

use App\Domain\Intelligence\Trial\Mail\ContratoAceitoMail;
use App\Domain\Intelligence\Trial\Models\IntelligenceContrato;
use App\Domain\Intelligence\Trial\Models\IntelligenceTrial;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Throwable;

/** Contrato do plano básico: texto versionado (resources/contratos/intelligence/{versao}.txt), hash do texto aceito e PDF. */
class ContratoPlano
{
    /** Primeira cobrança: fim do teste, se ainda estiver nele; senão, hoje. NADA é cobrado antes disso. */
    public static function inicioDaCobranca(IntelligenceTrial $trial): Carbon
    {
        $fim = $trial->trial_ends_at;

        return $fim && $fim->isFuture() ? $fim->copy()->startOfDay() : today();
    }

    /** @return array{texto: string, hash: string, versao: string} */
    public function renderizar(array $d, Carbon $inicioCobranca): array
    {
        $versao = (string) config('intelligence_trial.contrato_versao');
        $arquivo = resource_path("contratos/intelligence/{$versao}.txt");
        if (! is_file($arquivo)) {
            throw new \RuntimeException("Template de contrato não encontrado: {$versao}");
        }
        $empresa = config('oravel.company');
        $troca = [
            '{versao}' => $versao,
            '{razao_social}' => $d['razao_social'],
            '{cnpj}' => $d['cnpj'],
            '{email}' => $d['email'],
            '{responsavel}' => $d['responsavel'],
            '{contratada_nome}' => $empresa['legal_name'],
            '{contratada_cnpj}' => $empresa['cnpj'],
            '{plano_nome}' => config('intelligence_trial.plano.nome'),
            '{preco}' => number_format((float) config('intelligence_trial.plano.preco'), 2, ',', '.'),
            '{dias_teste}' => (string) config('intelligence_trial.dias'),
            '{inicio_cobranca}' => $inicioCobranca->format('d/m/Y'),
            '{carencia_dias}' => (string) config('intelligence_trial.carencia_dias'),
            '{foro}' => $empresa['forum'],
        ];
        $texto = strtr(trim(file_get_contents($arquivo)), $troca);

        return ['texto' => $texto, 'hash' => hash('sha256', $texto), 'versao' => $versao];
    }

    public function registrarAceite(IntelligenceTrial $trial, array $d, array $contrato, Carbon $inicio, ?string $ip, ?string $ua): IntelligenceContrato
    {
        $registro = IntelligenceContrato::withoutGlobalScopes()->create([
            'trial_id' => $trial->id,
            'tenant_id' => $trial->tenant_id,
            'versao' => $contrato['versao'],
            'hash' => $contrato['hash'],
            'texto' => $contrato['texto'],
            'razao_social' => $d['razao_social'],
            'cnpj' => $d['cnpj'],
            'email' => $d['email'],
            'responsavel' => $d['responsavel'],
            'forma_pagamento' => $d['forma_pagamento'],
            'preco' => config('intelligence_trial.plano.preco'),
            'billing_starts_on' => $inicio->toDateString(),
            'aceite_em' => now(),
            'ip' => $ip,
            'user_agent' => $ua ? mb_substr($ua, 0, 500) : null,
        ]);

        try {
            $registro->update(['pdf_path' => $this->gerarPdf($registro)]);
            Mail::to($registro->email)->send(new ContratoAceitoMail($registro, Storage::disk('local')->path($registro->pdf_path)));
        } catch (Throwable $e) {
            // O aceite já vale (texto + hash gravados); PDF/e-mail podem ser refeitos.
            Log::warning('Intelligence trial: PDF/e-mail do contrato falhou.', ['contrato_id' => $registro->id, 'erro' => $e->getMessage()]);
        }
        Log::info('Intelligence trial: contrato aceito.', ['trial_id' => $trial->id, 'versao' => $registro->versao, 'hash' => $registro->hash]);

        return $registro;
    }

    public function gerarPdf(IntelligenceContrato $c): string
    {
        $caminho = "intelligence-contratos/{$c->id}.pdf";
        $pdf = Pdf::loadView('intelligence-trial.contrato-pdf', ['c' => $c])->setPaper('a4');
        Storage::disk('local')->put($caminho, $pdf->output());

        return $caminho;
    }
}
