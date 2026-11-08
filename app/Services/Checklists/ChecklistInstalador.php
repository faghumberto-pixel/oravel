<?php

namespace App\Services\Checklists;

use App\Models\AssetCategory;
use App\Models\CatalogoRequisito;
use App\Models\CkItem;
use App\Models\CkSection;
use App\Models\CkTemplate;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Symfony\Component\Yaml\Yaml;

/**
 * Copia os checklists padrao (YAML em database/seeders/checklists) para o tenant. O YAML e so o ponto de partida: a fonte da
 * verdade e o banco.
 *
 * Idempotente e conservador: se o tenant ja tem o template (inclusive arquivado ou editado), nada e recriado nem sobrescrito;
 * restaurar o padrao e uma acao explicita a parte (C2). Itens citando norma entram com validar_norma = true.
 *
 * Grupo: o campo `grupo` do YAML e o slug do grupo no catalogo (G1, asset_categories.slug_template). Sem o grupo instalado o
 * template entra sem grupo (grupo_id nulo) e o relatorio avisa. `requisito` liga o item ao requisito equivalente do catalogo
 * (a aptidao do G1 le esse vinculo na C3); sem equivalente fica nulo.
 */
class ChecklistInstalador
{
    public const TIPOS = ['pre_uso', 'diario', 'periodico', 'saida', 'entrada'];

    public const PERIODICIDADES = ['por_uso', 'diaria', 'semanal', 'mensal', 'por_medidor'];

    public const TIPOS_RESPOSTA = ['conforme_nao_conforme_na', 'numerico', 'texto', 'foto', 'selecao'];

    public static function diretorio(): string
    {
        return database_path('seeders/checklists');
    }

    /**
     * @return array<string, array> slug do template => definicao
     */
    public function carregar(?string $dir = null): array
    {
        $dir ??= self::diretorio();
        $arquivos = glob($dir.'/*.yaml') ?: [];
        sort($arquivos);
        $out = [];

        foreach ($arquivos as $arquivo) {
            $d = Yaml::parseFile($arquivo);

            if (! is_array($d) || empty($d['template']['slug']) || empty($d['secoes'])) {
                throw new InvalidArgumentException('Checklist padrao invalido: '.basename($arquivo));
            }

            $out[$d['template']['slug']] = $d + ['_arquivo' => basename($arquivo)];
        }

        return $out;
    }

    /**
     * @param  ?string  $grupo  instala so o(s) template(s) deste grupo do catalogo (ex.: gerador)
     * @return array{criados: array<string,int>, ignorados: array<string,int>, avisos: array<int,string>}
     */
    public function instalar(Tenant $tenant, ?string $grupo = null, ?string $dir = null): array
    {
        $stats = ['criados' => [], 'ignorados' => [], 'avisos' => []];
        $tid = (string) $tenant->id;

        DB::transaction(function () use ($tid, $grupo, $dir, &$stats) {
            foreach ($this->carregar($dir) as $def) {
                if ($grupo !== null && ($def['template']['grupo'] ?? null) !== $grupo) {
                    continue;
                }

                $this->instalarTemplate($tid, $def, $stats);
            }
        });

        return $stats;
    }

    private function instalarTemplate(string $tid, array $def, array &$stats): void
    {
        $t = $def['template'];
        $existente = CkTemplate::withoutGlobalScopes()->withTrashed()->where('tenant_id', $tid)->where('slug_template', $t['slug'])->first();

        if ($existente) {
            $this->contar($stats, 'ignorados', 'templates');

            return;
        }

        $grupoId = null;

        if (! empty($t['grupo'])) {
            $grupoId = AssetCategory::withoutGlobalScopes()->where('tenant_id', $tid)->where('slug_template', $t['grupo'])->value('id');

            if (! $grupoId) {
                $stats['avisos'][] = "Grupo '{$t['grupo']}' do catálogo não está instalado: o checklist '{$t['nome']}' entrou sem grupo.";
            }
        }

        $versao = (int) ($def['versao'] ?? 1);
        $template = CkTemplate::withoutGlobalScopes()->create([
            'tenant_id' => $tid, 'grupo_id' => $grupoId, 'nome' => $t['nome'], 'tipo' => $t['tipo'], 'periodicidade' => $t['periodicidade'] ?? 'por_uso',
            'intervalo_horimetro_km' => $t['intervalo_horimetro_km'] ?? null, 'versao' => 1, 'ativo' => true, 'origem' => CkTemplate::ORIGEM_PADRAO,
            'referencia_manual' => $t['referencia_manual'] ?? null, 'observacoes' => $t['observacoes'] ?? null,
            'slug_template' => $t['slug'], 'versao_template' => $versao, 'personalizado' => false,
        ]);
        $this->contar($stats, 'criados', 'templates');

        foreach ($def['secoes'] as $i => $s) {
            $secao = CkSection::withoutGlobalScopes()->create([
                'tenant_id' => $tid, 'template_id' => $template->id, 'titulo' => $s['titulo'], 'ordem' => $i, 'slug_template' => $s['slug'],
            ]);
            $this->contar($stats, 'criados', 'secoes');

            foreach ($s['itens'] as $j => $item) {
                $this->criarItem($tid, $secao, $grupoId, $item, $j);
                $this->contar($stats, 'criados', 'itens');
            }
        }
    }

    private function criarItem(string $tid, CkSection $secao, ?string $grupoId, array $item, int $ordem): void
    {
        $critico = (bool) ($item['critico'] ?? false);
        $ref = $item['referencia'] ?? null;
        $requisitoId = null;

        if ($grupoId && ! empty($item['requisito'])) {
            $requisitoId = CatalogoRequisito::withoutGlobalScopes()->where('tenant_id', $tid)->where('grupo_id', $grupoId)->where('slug', $item['requisito'])->value('id');
        }

        CkItem::withoutGlobalScopes()->create([
            'tenant_id' => $tid, 'section_id' => $secao->id, 'descricao' => $item['descricao'],
            'tipo_resposta' => $item['tipo_resposta'] ?? 'conforme_nao_conforme_na', 'unidade' => $item['unidade'] ?? null, 'opcoes' => $item['opcoes'] ?? null,
            'obrigatorio' => (bool) ($item['obrigatorio'] ?? true), 'critico' => $critico,
            'exige_foto_se_nao_conforme' => (bool) ($item['foto'] ?? $critico), 'referencia_manual' => $ref,
            'validar_norma' => (bool) ($item['validar_norma'] ?? ($ref !== null && $ref !== '')),
            'catalogo_requisito_id' => $requisitoId, 'ordem' => $ordem, 'ativo' => true, 'slug_template' => $item['slug'], 'personalizado' => false,
        ]);
    }

    private function contar(array &$stats, string $tipo, string $rotulo): void
    {
        $stats[$tipo][$rotulo] = ($stats[$tipo][$rotulo] ?? 0) + 1;
    }
}
