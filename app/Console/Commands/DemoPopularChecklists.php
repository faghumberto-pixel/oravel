<?php

namespace App\Console\Commands;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\CkAnswer;
use App\Models\CkExecution;
use App\Models\CkItem;
use App\Models\CkSection;
use App\Models\CkTemplate;
use App\Models\MaintenanceOrder;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Exemplos para a "Visão geral de checklists": ativos com e sem checklist, em dia/atrasados/nunca conferidos, serviços com checklist feito
 * ou pendente e uma corretiva para cada diagnóstico possível. Tudo marcado (tag CKD-, prefixo na descrição da OS e nas observações da execução),
 * então --remover apaga só o que este comando criou. Sem Faker: roda em PROD. Não dispara observers (sem e-mail, notificação ou parada automática).
 */
class DemoPopularChecklists extends Command
{
    private const TAG = 'CKD-';

    private const MARCA = '[Exemplo checklist] ';

    protected $signature = 'demo:popular-checklists {--tenant=Locadora Demo : nome da empresa} {--remover : Apaga o que este comando criou}';

    /** @var array{ativos: int, os: int, execucoes: int} */
    private array $n = ['ativos' => 0, 'os' => 0, 'execucoes' => 0];

    protected $description = 'Popula a Visão geral de checklists com exemplos (ativos, serviços e corretivas), reversível com --remover';

    public function handle(): int
    {
        $tenant = Tenant::withoutGlobalScopes()->where('name', $this->option('tenant'))->first();

        if (! $tenant) {
            $this->error('Empresa "'.$this->option('tenant').'" não encontrada.');

            return self::FAILURE;
        }

        if ($this->option('remover')) {
            return $this->remover($tenant);
        }

        if (Asset::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('tag', 'like', self::TAG.'%')->exists()) {
            $this->warn('Os exemplos já existem. Use --remover antes para recriar.');

            return self::FAILURE;
        }

        $executor = User::withoutGlobalScopes()->where('tenant_id', $tenant->id)->orderBy('created_at')->first();
        $modelos = CkTemplate::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('ativo', true)->whereNotNull('slug_template')->get()->keyBy('slug_template');

        if (! $executor || $modelos->isEmpty()) {
            $this->error('A empresa precisa de um usuário e dos checklists padrão instalados (php artisan checklists:seed --tenant=ID).');

            return self::FAILURE;
        }

        $this->n = ['ativos' => 0, 'os' => 0, 'execucoes' => 0];

        DB::transaction(function () use ($tenant, $executor, $modelos) {
            Asset::withoutEvents(fn () => MaintenanceOrder::withoutEvents(fn () => CkExecution::withoutEvents(fn () => $this->criar($tenant, $executor, $modelos))));
        });

        $this->info("Criados: {$this->n['ativos']} ativos, {$this->n['os']} ordens de serviço, {$this->n['execucoes']} checklists preenchidos.");

        return self::SUCCESS;
    }

    private function criar(Tenant $tenant, User $executor, $modelos): void
    {
        $ativo = function (string $nome, ?string $grupo, int $seq, float $horas = 0) use ($tenant): Asset {
            $cat = $grupo ? AssetCategory::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('slug_template', $grupo)->first() : null;
            $this->n['ativos']++;

            return Asset::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id, 'name' => $nome, 'tag' => self::TAG.str_pad((string) $seq, 3, '0', STR_PAD_LEFT), 'status' => Asset::STATUS_DISPONIVEL,
                'asset_category_id' => $cat?->id, 'horimetro_atual' => $horas, 'criticality' => 'medium',
            ]);
        };

        $os = function (Asset $a, string $tipo, int $diasAtras, string $descricao, string $status = 'Concluída') use ($tenant): MaintenanceOrder {
            $this->n['os']++;
            $o = MaintenanceOrder::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id, 'asset_id' => $a->id, 'maintenance_type' => $tipo, 'status' => $status, 'priority' => $tipo === 'Corretiva' ? 'alta' : 'media',
                'description' => self::MARCA.$descricao,
            ]);
            $o->forceFill(['created_at' => now()->subDays($diasAtras), 'updated_at' => now()->subDays($diasAtras)])->save();

            return $o;
        };

        // Preenche TODOS os itens do modelo como conforme, exceto os que casam com $nc (trecho da descricao => observacao).
        $exec = function (Asset $a, string $slug, int $diasAtras, array $nc = [], ?MaintenanceOrder $ordem = null) use ($tenant, $executor, $modelos): ?CkExecution {
            $t = $modelos[$slug] ?? null;

            if (! $t) {
                return null;
            }

            $quando = now()->subDays($diasAtras);
            $e = CkExecution::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id, 'asset_id' => $a->id, 'template_id' => $t->id, 'template_nome' => $t->nome, 'versao_template' => $t->versao, 'executor_id' => $executor->id,
                'status' => $nc ? 'reprovado' : 'aprovado', 'iniciado_em' => $quando, 'finalizado_em' => $quando, 'os_id' => $ordem?->id, 'observacoes' => self::MARCA.'exemplo',
            ]);
            $this->n['execucoes']++;

            foreach (CkSection::withoutGlobalScopes()->where('template_id', $t->id)->orderBy('ordem')->get() as $s) {
                foreach (CkItem::withoutGlobalScopes()->where('section_id', $s->id)->where('ativo', true)->orderBy('ordem')->get() as $i) {
                    if ($i->tipo_resposta !== 'conforme_nao_conforme_na') {
                        continue;
                    }

                    $obs = null;
                    $resp = 'conforme';

                    foreach ($nc as $trecho => $nota) {
                        if (mb_stripos($i->descricao, $trecho) !== false) {
                            [$resp, $obs] = ['nao_conforme', $nota];
                        }
                    }

                    CkAnswer::withoutGlobalScopes()->create([
                        'tenant_id' => $tenant->id, 'execution_id' => $e->id, 'item_id' => $i->id, 'secao_titulo' => $s->titulo, 'item_descricao' => $i->descricao,
                        'tipo_resposta' => $i->tipo_resposta, 'critico' => $i->critico, 'resposta' => $resp, 'observacao' => $obs,
                    ]);
                }
            }

            return $e;
        };

        // ---- Cobertura: em dia, atrasados, nunca conferidos e sem checklist
        $seq = 1;
        foreach ([['Gerador 100 kVA', 'gerador', 'gerador-pre-uso', 3], ['Gerador 180 kVA', 'gerador', 'gerador-pre-uso', 6], ['Empilhadeira 2,5 t', 'empilhadeira', 'empilhadeira-pre-uso', 2], ['Compressor 185 pcm', 'compressor', 'compressor-diario', 1]] as [$nome, $g, $slug, $dias]) {
            $exec($ativo($nome.' (em dia)', $g, $seq++), $slug, $dias);
        }
        foreach ([['Gerador 60 kVA', 'gerador', 'gerador-pre-uso', 45], ['Empilhadeira 3,5 t', 'empilhadeira', 'empilhadeira-pre-uso', 70], ['Plataforma 12 m', 'plataforma-elevatoria', 'plataforma-pre-uso', 38]] as [$nome, $g, $slug, $dias]) {
            $exec($ativo($nome.' (atrasado)', $g, $seq++), $slug, $dias);
        }
        foreach ([['Gerador 250 kVA', 'gerador'], ['Munk 8 t', 'munk'], ['Compressor 375 pcm', 'compressor'], ['Guindaste 30 t', 'guindaste']] as [$nome, $g]) {
            $ativo($nome.' (nunca conferido)', $g, $seq++);
        }
        foreach (['Andaime fachadeiro', 'Furadeira de impacto', 'Betoneira 400 L'] as $nome) {
            $ativo($nome.' (sem checklist)', null, $seq++);
        }

        // ---- Serviços: preventivas com checklist feito e pendente
        $g1 = $ativo('Gerador 150 kVA (serviços)', 'gerador', $seq++);
        $os1 = $os($g1, 'Preventiva', 25, 'Preventiva de 250 h');
        $exec($g1, 'gerador-pre-uso', 25, [], $os1);
        $os($g1, 'Preventiva', 8, 'Troca de filtros e revisão geral');
        $e2 = $ativo('Empilhadeira 5 t (serviços)', 'empilhadeira', $seq++);
        $exec($e2, 'empilhadeira-pre-uso', 12, [], $os($e2, 'Preventiva', 12, 'Revisão de freios e hidráulica'));
        $os($ativo('Betoneira 150 L (serviços)', null, $seq++), 'Preventiva', 5, 'Lubrificação geral');

        // ---- Corretivas: uma para cada diagnóstico
        // conferido: o item do óleo estava conforme dias antes e mesmo assim o motor fundiu
        $a = $ativo('Gerador 500 kVA (corretiva: conferido)', 'gerador', $seq++);
        $exec($a, 'gerador-pre-uso', 11);
        $os($a, 'Corretiva', 1, 'Motor fundiu por falta de óleo', 'Em andamento');

        // ja_registrado: o vazamento já tinha sido apontado como não conforme
        $a = $ativo('Gerador 30 kVA (corretiva: já registrado)', 'gerador', $seq++);
        $o = $os($a, 'Preventiva', 18, 'Preventiva de 500 h');
        $exec($a, 'gerador-pre-uso', 18, ['vazamento' => 'mancha de óleo sob o motor, acompanhar'], $o);
        $os($a, 'Corretiva', 2, 'Vazando óleo pela junta do cárter', 'Em andamento');

        // sem_item: falha em algo que o checklist nem pergunta
        $a = $ativo('Empilhadeira 1,5 t (corretiva: sem item)', 'empilhadeira', $seq++);
        $exec($a, 'empilhadeira-pre-uso', 4);
        $os($a, 'Corretiva', 1, 'Rolamento da roda traseira travou', 'Aberta');

        // nunca_conferido: tem checklist aplicável, nunca foi feito
        $os($ativo('Compressor 100 pcm (corretiva: nunca conferido)', 'compressor', $seq++), 'Corretiva', 3, 'Compressor não pressuriza', 'Em andamento');

        // sem_checklist: equipamento sem grupo
        $os($ativo('Torre de iluminação (corretiva: sem checklist)', null, $seq++), 'Emergência', 2, 'Parou de funcionar durante a noite', 'Aberta');
    }

    private function remover(Tenant $tenant): int
    {
        DB::transaction(function () use ($tenant) {
            $ativos = Asset::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('tag', 'like', self::TAG.'%')->pluck('id');
            $execs = CkExecution::withoutGlobalScopes()->where('tenant_id', $tenant->id)->whereIn('asset_id', $ativos)->pluck('id');
            CkAnswer::withoutGlobalScopes()->whereIn('execution_id', $execs)->delete();
            CkExecution::withoutGlobalScopes()->whereIn('id', $execs)->delete();
            MaintenanceOrder::withoutGlobalScopes()->where('tenant_id', $tenant->id)->whereIn('asset_id', $ativos)->where('description', 'like', self::MARCA.'%')->forceDelete();
            Asset::withoutGlobalScopes()->whereIn('id', $ativos)->forceDelete();
        });

        $this->info('Exemplos removidos.');

        return self::SUCCESS;
    }
}
