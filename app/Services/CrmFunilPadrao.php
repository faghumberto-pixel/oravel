<?php

namespace App\Services;

use App\Models\CrmLead;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Cria (uma vez por tenant) o funil padrão, os motivos de perda e as origens
 * a partir das constantes históricas do CRM, e liga os leads existentes à
 * etapa certa. Usa só DB (sem models/eventos) para poder rodar dentro de
 * migration e ser idempotente.
 */
class CrmFunilPadrao
{
    /** slug => [nome, probabilidade, tipo, cor] — mesmas 5 etapas fixas de antes. */
    public const ETAPAS = [
        'novo' => ['Novo', 10, 'aberta', 'blue'],
        'contato_iniciado' => ['Contato Iniciado', 25, 'aberta', 'indigo'],
        'qualificado' => ['Qualificado', 50, 'aberta', 'orange'],
        'convertido' => ['Convertido', 100, 'ganho', 'emerald'],
        'perdido' => ['Perdido', 0, 'perdido', 'red'],
    ];

    /** Retorna o id do funil padrão do tenant (criando tudo que faltar). */
    public static function garantir(string $tenantId): string
    {
        return DB::transaction(function () use ($tenantId) {
            $funilId = DB::table('crm_funis')->where('tenant_id', $tenantId)->where('padrao', true)->value('id');

            if (! $funilId) {
                $funilId = (string) Str::uuid();
                DB::table('crm_funis')->insert([
                    'id' => $funilId, 'tenant_id' => $tenantId, 'nome' => 'Funil de Vendas',
                    'padrao' => true, 'ativo' => true, 'created_at' => now(), 'updated_at' => now(),
                ]);

                $ordem = 0;
                foreach (self::ETAPAS as $slug => [$nome, $prob, $tipo, $cor]) {
                    DB::table('crm_etapas')->insert([
                        'id' => (string) Str::uuid(), 'tenant_id' => $tenantId, 'crm_funil_id' => $funilId,
                        'nome' => $nome, 'slug' => $slug, 'ordem' => $ordem++, 'probabilidade' => $prob,
                        'tipo' => $tipo, 'cor' => $cor, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }

            self::garantirListas($tenantId);
            self::ligarLeadsSemEtapa($tenantId, $funilId);

            return $funilId;
        });
    }

    private static function garantirListas(string $tenantId): void
    {
        $ordem = 0;
        foreach (CrmLead::lostReasonCategoryLabels() as $slug => $nome) {
            if (DB::table('crm_motivos_perda')->where('tenant_id', $tenantId)->where('slug', $slug)->exists()) {
                $ordem++;

                continue;
            }
            DB::table('crm_motivos_perda')->insert([
                'id' => (string) Str::uuid(), 'tenant_id' => $tenantId, 'nome' => $nome, 'slug' => $slug,
                'exige_detalhe' => in_array($slug, CrmLead::lostReasonCategoriesRequiringDetail(), true),
                'ordem' => $ordem++, 'ativo' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $ordem = 0;
        foreach (CrmLead::sourceLabels() as $slug => $nome) {
            if (DB::table('crm_origens')->where('tenant_id', $tenantId)->where('slug', $slug)->exists()) {
                $ordem++;

                continue;
            }
            DB::table('crm_origens')->insert([
                'id' => (string) Str::uuid(), 'tenant_id' => $tenantId, 'nome' => $nome, 'slug' => $slug,
                'ordem' => $ordem++, 'ativo' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    /** Backfill: etapa_id pelo slug de stage; stage desconhecido (ex.: 'prospecção') cai na 1ª etapa aberta. */
    private static function ligarLeadsSemEtapa(string $tenantId, string $funilId): void
    {
        $etapas = DB::table('crm_etapas')->where('crm_funil_id', $funilId)->orderBy('ordem')->get();
        $primeiraAberta = $etapas->firstWhere('tipo', 'aberta');

        foreach (DB::table('crm_leads')->where('tenant_id', $tenantId)->whereNull('etapa_id')->distinct()->pluck('stage') as $stage) {
            $etapa = $etapas->firstWhere('slug', $stage) ?? $primeiraAberta;

            DB::table('crm_leads')->where('tenant_id', $tenantId)->whereNull('etapa_id')->where('stage', $stage)
                ->update(['etapa_id' => $etapa->id]);
        }
    }
}
