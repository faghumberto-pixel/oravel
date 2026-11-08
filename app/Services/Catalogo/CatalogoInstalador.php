<?php

namespace App\Services\Catalogo;

use App\Models\AssetCategory;
use App\Models\CatalogoInstalacao;
use App\Models\CatalogoItem;
use App\Models\CatalogoModel;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\Yaml\Yaml;

/**
 * G1 -- copia o TEMPLATE INICIAL (database/seeders/catalog/*.yaml) para as tabelas do tenant.
 *
 * Garantias (testadas):
 *  - Idempotente: cada item e identificado por slug estavel; rodar de novo nao cria nem altera nada.
 *  - Nunca sobrescreve o tenant: item existente (inclusive arquivado/removido pelo tenant) e pulado.
 *  - Grupo ja instalado e pulado INTEIRO (filhos inclusos): novidades de versao do template entram so pela
 *    atualizacao com diff, item a item (fase 6), nunca aqui.
 *  - Categoria do tenant com o mesmo slug mas sem origem no template NAO e adotada nem duplicada: vira
 *    "conflito" no relatorio, para vinculacao manual (fase 2).
 *  - O template e validado por inteiro ANTES de gravar qualquer linha.
 */
class CatalogoInstalador
{
    public static function diretorio(): string
    {
        return database_path('seeders/catalog');
    }

    /** @return array{vocabulario: array, biblioteca: array, grupos: array<string, array>, versao: int} */
    public function carregarTemplate(?string $dir = null): array
    {
        $dir ??= self::diretorio();

        $vocab = $this->yaml($dir.'/_vocabulario.yaml');
        $biblioteca = $this->yaml($dir.'/_biblioteca.yaml');
        $grupos = [];

        $arquivos = glob($dir.'/*.yaml') ?: [];
        sort($arquivos);

        foreach ($arquivos as $arquivo) {
            if (str_starts_with(basename($arquivo), '_')) {
                continue;
            }
            $dados = $this->yaml($arquivo);
            $slug = $dados['grupo']['slug'] ?? null;
            $grupos[$slug ?? basename($arquivo)] = $dados;
        }

        $this->validar($vocab, $biblioteca, $grupos);

        $versao = max([(int) ($biblioteca['versao'] ?? 1), ...array_map(fn ($g) => (int) ($g['versao'] ?? 1), $grupos)]);

        return ['vocabulario' => $vocab, 'biblioteca' => $biblioteca, 'grupos' => $grupos, 'versao' => $versao];
    }

    /**
     * @return array{criados: array<string,int>, ignorados: array<string,int>, conflitos: array<int,string>}
     */
    public function instalar(Tenant $tenant, ?string $dir = null, ?array $apenasGrupos = null): array
    {
        $template = $this->carregarTemplate($dir);

        if ($apenasGrupos !== null) {
            $template['grupos'] = array_intersect_key($template['grupos'], array_flip($apenasGrupos));
        }
        $tenantId = (string) $tenant->id;
        $stats = ['criados' => [], 'ignorados' => [], 'conflitos' => []];

        DB::transaction(function () use ($template, $tenantId, &$stats) {
            $itens = [];
            foreach ($template['biblioteca']['itens'] ?? [] as $i => $item) {
                $itens[$item['slug']] = $this->criar(CatalogoItem::class, ['tenant_id' => $tenantId, 'slug' => $item['slug']],
                    ['ordem' => $i, ...$this->meta($item['slug'], $template['biblioteca'])] + CatalogoTemplate::item($item), $stats)->id;
            }

            foreach ($template['grupos'] as $arquivo) {
                $this->instalarGrupo($tenantId, $arquivo, $itens, $stats);
            }

            if (array_sum($stats['criados']) > 0 || ! CatalogoInstalacao::withoutGlobalScopes()->where('tenant_id', $tenantId)->exists()) {
                CatalogoInstalacao::withoutGlobalScopes()->updateOrCreate(
                    ['tenant_id' => $tenantId],
                    ['versao_instalada' => $template['versao'], 'instalado_em' => now()],
                );
            }
        });

        return $stats;
    }

    private function instalarGrupo(string $tenantId, array $arquivo, array $itens, array &$stats): void
    {
        $g = $arquivo['grupo'];
        $slug = $g['slug'];

        $existente = AssetCategory::withoutGlobalScopes()->withTrashed()
            ->where('tenant_id', $tenantId)->where('slug_template', $slug)->first();

        if ($existente) {
            $this->contar($stats, 'ignorados', 'grupos');

            return; // grupo ja instalado: filhos so mudam por atualizacao com diff (fase 6)
        }

        $conflito = AssetCategory::withoutGlobalScopes()->withTrashed()
            ->where('tenant_id', $tenantId)->where('slug', $slug)->first();

        if ($conflito) {
            $stats['conflitos'][] = $slug;

            return;
        }

        $grupo = AssetCategory::withoutGlobalScopes()->create([
            'tenant_id' => $tenantId, 'slug' => $slug,
            'origem' => CatalogoModel::ORIGEM_SISTEMA, 'slug_template' => $slug,
            'versao_template' => (int) ($arquivo['versao'] ?? 1), 'personalizado' => false,
        ] + CatalogoTemplate::grupo($arquivo));
        $this->contar($stats, 'criados', 'grupos');

        $base = fn (string $filhoSlug, int $ordem) => [
            'tenant_id' => $tenantId, 'grupo_id' => $grupo->id, 'slug' => $filhoSlug, 'ordem' => $ordem,
            'origem' => CatalogoModel::ORIGEM_SISTEMA, 'slug_template' => $filhoSlug,
            'versao_template' => (int) ($arquivo['versao'] ?? 1), 'personalizado' => false, 'ativo' => true,
        ];

        foreach (CatalogoTemplate::SECOES as $secao => [$classe]) {
            foreach ($arquivo[$secao] ?? [] as $i => $linha) {
                $this->gravar($classe, $base($linha['slug'], $i) + CatalogoTemplate::camposDb($secao, $linha, $itens), $stats);
            }
        }
    }

    /** Cria so se nao existir (inclusive arquivado). Retorna o registro existente ou o novo. */
    private function criar(string $classe, array $chave, array $dados, array &$stats): CatalogoModel
    {
        $existente = $classe::withoutGlobalScopes()->withTrashed()->where($chave)->first();

        if ($existente) {
            $this->contar($stats, 'ignorados', $this->rotulo($classe));

            return $existente;
        }

        $this->contar($stats, 'criados', $this->rotulo($classe));

        return $classe::withoutGlobalScopes()->create($chave + $dados);
    }

    private function gravar(string $classe, array $dados, array &$stats): void
    {
        $this->criar($classe, ['tenant_id' => $dados['tenant_id'], 'grupo_id' => $dados['grupo_id'], 'slug' => $dados['slug']], $dados, $stats);
    }

    private function meta(string $slug, array $arquivo): array
    {
        return [
            'origem' => CatalogoModel::ORIGEM_SISTEMA, 'slug_template' => $slug, 'versao_template' => (int) ($arquivo['versao'] ?? 1),
            'personalizado' => false, 'ativo' => true,
        ];
    }

    private function contar(array &$stats, string $tipo, string $rotulo): void
    {
        $stats[$tipo][$rotulo] = ($stats[$tipo][$rotulo] ?? 0) + 1;
    }

    private function rotulo(string $classe): string
    {
        return Str::of(class_basename($classe))->after('Catalogo')->snake()->plural()->toString();
    }

    private function yaml(string $arquivo): array
    {
        if (! is_file($arquivo)) {
            throw new InvalidArgumentException("Template do catalogo nao encontrado: {$arquivo}");
        }

        $dados = Yaml::parseFile($arquivo);

        return is_array($dados) ? $dados : throw new InvalidArgumentException("YAML invalido: {$arquivo}");
    }

    private function validar(array $vocab, array $biblioteca, array $grupos): void
    {
        $efeitos = array_keys($vocab['efeitos_requisito'] ?? []);
        $slugsItens = [];

        foreach ($biblioteca['itens'] ?? [] as $item) {
            $this->exigir(! isset($slugsItens[$item['slug']]), "Item de biblioteca duplicado: {$item['slug']}");
            $this->exigir(in_array($item['tipo'] ?? null, $vocab['tipos_item'], true), "Tipo de item invalido: {$item['slug']}");
            $slugsItens[$item['slug']] = true;
        }

        foreach ($grupos as $chave => $arq) {
            $g = $arq['grupo'] ?? [];
            $this->exigir(! empty($g['slug']) && ! empty($g['nome']), "Grupo sem slug/nome: {$chave}");
            $this->exigir(in_array($g['medidor'] ?? null, $vocab['medidores'], true), "Medidor invalido no grupo {$chave}");
            $this->exigir(in_array($g['criticidade_padrao'] ?? null, $vocab['criticidades'], true), "Criticidade invalida no grupo {$chave}");

            foreach (['atributos', 'requisitos', 'preventivas', 'operadores', 'pecas_criticas', 'sla', 'regras'] as $secao) {
                $vistos = [];
                foreach ($arq[$secao] ?? [] as $linha) {
                    $this->exigir(! empty($linha['slug']) && ! isset($vistos[$linha['slug']]), "Slug ausente ou duplicado em {$chave}/{$secao}");
                    $vistos[$linha['slug']] = true;
                }
            }

            foreach ($arq['atributos'] ?? [] as $a) {
                $this->exigir(in_array($a['tipo'] ?? null, $vocab['tipos_atributo'], true), "Tipo de atributo invalido em {$chave}: {$a['slug']}");
            }
            foreach ($arq['requisitos'] ?? [] as $r) {
                $this->exigir(in_array($r['efeito'] ?? null, $efeitos, true), "Efeito invalido em {$chave}: {$r['slug']}");
                $this->exigir(isset($slugsItens[$r['item'] ?? '']), "Requisito {$chave}/{$r['slug']} referencia item inexistente na biblioteca");
            }
            foreach ($arq['preventivas'] ?? [] as $p) {
                $this->exigir(in_array($p['medidor'] ?? null, ['horimetro', 'km', 'dias'], true), "Medidor invalido na preventiva {$chave}/{$p['slug']}");
            }
            foreach ($arq['sla'] ?? [] as $s) {
                $this->exigir(in_array($s['criticidade'] ?? null, $vocab['criticidades'], true), "Criticidade invalida no SLA {$chave}/{$s['slug']}");
                $this->exigir(in_array($s['calendario'] ?? 'comercial', $vocab['calendarios'], true), "Calendario invalido no SLA {$chave}/{$s['slug']}");
            }
            foreach ($arq['regras'] ?? [] as $r) {
                $this->exigir(in_array($r['efeito'] ?? null, $vocab['efeitos_regra'], true), "Efeito de regra invalido em {$chave}/{$r['slug']}");
            }
        }
    }

    private function exigir(bool $condicao, string $mensagem): void
    {
        if (! $condicao) {
            throw new InvalidArgumentException('Template do catalogo: '.$mensagem);
        }
    }
}
