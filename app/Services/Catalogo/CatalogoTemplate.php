<?php

namespace App\Services\Catalogo;

use App\Models\CatalogoAtributo;
use App\Models\CatalogoOperador;
use App\Models\CatalogoPecaCritica;
use App\Models\CatalogoPreventiva;
use App\Models\CatalogoRegra;
use App\Models\CatalogoRequisito;
use App\Models\CatalogoSla;

/**
 * Mapeamento UNICO YAML -> campos do banco. O instalador (cria) e o servico de atualizacao de template (compara/aplica) usam
 * esta classe, para que "o que o template manda" nunca divirja entre quem instala e quem calcula o diff.
 */
class CatalogoTemplate
{
    /** secao do YAML => [model, rotulo humano] (a ordem aqui e a ordem de instalacao). */
    public const SECOES = [
        'atributos' => [CatalogoAtributo::class, 'Campo técnico'],
        'requisitos' => [CatalogoRequisito::class, 'Requisito'],
        'preventivas' => [CatalogoPreventiva::class, 'Preventiva'],
        'operadores' => [CatalogoOperador::class, 'Operador'],
        'pecas_criticas' => [CatalogoPecaCritica::class, 'Peça crítica'],
        'sla' => [CatalogoSla::class, 'SLA'],
        'regras' => [CatalogoRegra::class, 'Regra'],
    ];

    /** @return array<string, mixed> campos do GRUPO (AssetCategory) */
    public static function grupo(array $arquivo): array
    {
        $g = $arquivo['grupo'];

        return [
            'name' => $g['nome'], 'description' => $g['descricao'] ?? null, 'medidor' => $g['medidor'],
            'criticidade_padrao' => $g['criticidade_padrao'], 'exige_operador' => (bool) ($g['exige_operador'] ?? false),
            'ordem' => (int) ($g['ordem'] ?? 0), 'is_active' => true,
        ];
    }

    /** @return array<string, mixed> campos de um item da BIBLIOTECA */
    public static function item(array $i): array
    {
        return ['tipo' => $i['tipo'], 'nome' => $i['nome'], 'descricao' => $i['descricao'] ?? null, 'validade_dias' => $i['validade_dias'] ?? null, 'parametros' => $i['parametros'] ?? null, 'ativo' => true];
    }

    /**
     * Campos de uma linha de secao. Em `requisitos`, o item vem como `item_slug` (o chamador troca por item_id).
     *
     * @return array<string, mixed>
     */
    public static function campos(string $secao, array $l): array
    {
        $campos = match ($secao) {
            'atributos' => ['chave' => $l['slug'], 'rotulo' => $l['rotulo'], 'tipo' => $l['tipo'], 'unidade' => $l['unidade'] ?? null,
                'obrigatorio' => (bool) ($l['obrigatorio'] ?? false), 'opcoes' => $l['opcoes'] ?? null],
            'requisitos' => ['item_slug' => $l['item'], 'efeito' => $l['efeito'], 'validade_dias' => $l['validade_dias'] ?? null,
                'tolerancia_pct' => $l['tolerancia_pct'] ?? null, 'gera_os' => (bool) ($l['gera_os'] ?? false),
                'validar_norma' => (bool) ($l['validar_norma'] ?? false), 'norma' => $l['norma'] ?? null, 'parametros' => $l['parametros'] ?? null],
            'preventivas' => ['nome' => $l['nome'], 'medidor' => $l['medidor'], 'intervalo' => $l['intervalo'], 'tolerancia_pct' => $l['tolerancia_pct'] ?? 10,
                'ou_dias' => $l['ou_dias'] ?? null, 'regra_ou' => $l['regra_ou'] ?? 'primeiro', 'parametros' => $l['parametros'] ?? null],
            'operadores' => ['habilitacao' => $l['habilitacao'], 'norma' => $l['norma'] ?? null, 'validade_dias' => $l['validade_dias'] ?? null,
                'obrigatorio' => (bool) ($l['obrigatorio'] ?? true), 'validar_norma' => (bool) ($l['validar_norma'] ?? false)],
            'pecas_criticas' => ['descricao' => $l['descricao'], 'compatibilidade' => $l['compatibilidade'] ?? null],
            'sla' => ['criticidade' => $l['criticidade'], 'resposta_min' => $l['resposta_min'] ?? null, 'resolucao_min' => $l['resolucao_min'] ?? null,
                'calendario' => $l['calendario'] ?? 'comercial', 'pausa_por' => $l['pausa_por'] ?? null],
            'regras' => ['nome' => $l['nome'], 'condicao' => $l['condicao'] ?? null, 'efeito' => $l['efeito'], 'strategy' => $l['strategy'] ?? null,
                'validar_norma' => (bool) ($l['validar_norma'] ?? false)],
        };

        return $campos + ['ativo' => true];
    }

    /** Campos prontos para gravar: troca item_slug por item_id (requisitos). */
    public static function camposDb(string $secao, array $linha, array $itensPorSlug): array
    {
        $c = self::campos($secao, $linha);

        if (isset($c['item_slug'])) {
            $c['item_id'] = $itensPorSlug[$c['item_slug']];
            unset($c['item_slug']);
        }

        return $c;
    }
}
