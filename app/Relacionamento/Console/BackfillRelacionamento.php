<?php

namespace App\Relacionamento\Console;

use App\Models\Prospect;
use App\Models\ProspectContato;
use App\Models\SalesLeadInteraction;
use App\Relacionamento\Models\RelInteracao;
use App\Relacionamento\Services\RegistraInteracao;
use Illuminate\Console\Command;

/**
 * Popula rel_interacoes com o historico que a Central ja tem (contatos de Prospect e interacoes de
 * SalesLead), para a timeline nascer com dado real. Idempotente (dedupe_key): pode rodar de novo.
 * Nao inclui web_events: o acesso ao site e' anonimo e so' entra apos a identificacao (Fase 3).
 */
class BackfillRelacionamento extends Command
{
    protected $signature = 'relacionamento:backfill {--dry-run : so conta, nao grava}';

    protected $description = 'Importa contatos de Prospect e interações de SalesLead para a linha do tempo do Relacionamento';

    public function handle(RegistraInteracao $registra): int
    {
        $dry = (bool) $this->option('dry-run');
        $novos = 0;

        ProspectContato::query()->orderBy('created_at')->chunk(500, function ($lote) use ($registra, $dry, &$novos) {
            foreach ($lote as $c) {
                $key = "prospect_contato:{$c->id}";
                if (RelInteracao::where('dedupe_key', $key)->exists()) {
                    continue;
                }
                $novos++;
                if ($dry) {
                    continue;
                }
                $registra->registrar([
                    'tipo' => RelInteracao::TIPO_CONTATO_COMERCIAL,
                    'prospect_id' => $c->prospect_id,
                    'titulo' => $c->titulo ?: 'Contato por '.($c->canal ?? 'outro'),
                    'resultado' => $c->texto,
                    'origem' => 'central',
                    'dados' => ['canal' => $c->canal, 'autor' => $c->autor],
                    'ocorrido_em' => $c->data?->copy()->setTime(12, 0) ?? $c->created_at,
                    'dedupe_key' => $key,
                ]);
            }
        });

        SalesLeadInteraction::query()->orderBy('created_at')->chunk(500, function ($lote) use ($registra, $dry, &$novos) {
            foreach ($lote as $i) {
                $key = "sales_lead_interaction:{$i->id}";
                if (RelInteracao::where('dedupe_key', $key)->exists()) {
                    continue;
                }
                $novos++;
                if ($dry) {
                    continue;
                }
                $registra->registrar([
                    'tipo' => RelInteracao::TIPO_CONTATO_COMERCIAL,
                    'sales_lead_id' => $i->sales_lead_id,
                    'prospect_id' => Prospect::where('sales_lead_id', $i->sales_lead_id)->value('id'),
                    'titulo' => SalesLeadInteraction::channelLabels()[$i->channel] ?? $i->channel,
                    'resultado' => $i->summary,
                    'origem' => 'central',
                    'dados' => ['canal' => $i->channel, 'etapa' => $i->stage_at_time],
                    'ocorrido_em' => $i->contact_date,
                    'dedupe_key' => $key,
                ]);
            }
        });

        $this->info(($dry ? '[dry-run] ' : '')."{$novos} interações ".($dry ? 'seriam importadas.' : 'importadas.'));

        return self::SUCCESS;
    }
}
