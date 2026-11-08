<?php

namespace App\Services\Catalogo;

use App\Jobs\Aptidao\RecalcularAptidaoGrupoJob;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\CatalogoItem;
use App\Models\CatalogoModel;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Catalogo\Aptidao\AssetFitnessService;
use App\Services\Catalogo\Aptidao\PerfilDoGrupo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * G1 fase 6 -- atualizacao de template por tenant. O template NUNCA sobrescreve o tenant sozinho:
 *  - diff item a item ("atualizacao disponivel"): o tenant aceita, ignora ou restaura cada um;
 *  - item PERSONALIZADO nunca e alterado por aceite (aparece so como informacao: "ha versao nova do padrao");
 *  - o que o tenant arquivou/removeu nao volta sozinho (so por "restaurar padrao");
 *  - o que saiu do template nao e apagado do tenant (so informado);
 *  - edicao sensivel (IMP->ALR, ampliar intervalo, remover/desativar obrigatorio) exige justificativa e vai para o log;
 *  - "restaurar padrao" (item ou grupo) sempre exige justificativa e e logado;
 *  - previa de impacto na aptidao simulando dentro de uma transacao desfeita.
 */
class CatalogoTemplateService
{
    public const ACOES_ACEITAVEIS = ['grupo_novo', 'novo', 'atualizar'];

    public const ACOES_RESTAURAVEIS = ['personalizado', 'atualizar', 'arquivado', 'novo'];

    public function __construct(private CatalogoInstalador $instalador, private AssetFitnessService $motor) {}

    // ================================================================== diff

    /** @return array<string, array<string, mixed>> entradas por chave ("{tipo}:{grupo}:{slug}") */
    public function diferencas(Tenant $tenant, ?string $dir = null): array
    {
        $tpl = $this->instalador->carregarTemplate($dir);
        $tid = (string) $tenant->id;
        $versaoBib = (int) ($tpl['biblioteca']['versao'] ?? 1);
        $nomesItens = collect($tpl['biblioteca']['itens'] ?? [])->pluck('nome', 'slug')->all();
        $out = [];

        // ---- biblioteca
        $slugsItens = [];
        foreach ($tpl['biblioteca']['itens'] ?? [] as $item) {
            $slugsItens[] = $item['slug'];
            $row = CatalogoItem::withoutGlobalScopes()->withTrashed()->where('tenant_id', $tid)->where('slug', $item['slug'])->first();
            $this->classificar($out, 'item', null, $item['slug'], $item['nome'], 'Item da biblioteca', $row, CatalogoTemplate::item($item), 'item', $versaoBib);
        }
        $this->removidosDoPadrao($out, 'item', null, CatalogoItem::withoutGlobalScopes()->withTrashed()->where('tenant_id', $tid)->whereNotNull('slug_template'), $slugsItens, fn ($r) => $r->nome, 'Item da biblioteca');

        // ---- grupos
        foreach ($tpl['grupos'] as $slug => $arq) {
            $versao = (int) ($arq['versao'] ?? 1);
            $g = AssetCategory::withoutGlobalScopes()->withTrashed()->where('tenant_id', $tid)->where('slug_template', $slug)->first();

            if (! $g) {
                $conflito = AssetCategory::withoutGlobalScopes()->withTrashed()->where('tenant_id', $tid)->where('slug', $slug)->exists();
                $out["grupo_novo::{$slug}"] = ['chave' => "grupo_novo::{$slug}", 'tipo' => 'grupo_novo', 'grupo' => $slug, 'slug' => $slug, 'rotulo' => $arq['grupo']['nome'],
                    'tipo_rotulo' => 'Grupo', 'acao' => $conflito ? 'conflito' : 'grupo_novo', 'campos' => [], 'id' => null];

                continue;
            }

            $this->classificar($out, 'grupo', $slug, $slug, $arq['grupo']['nome'], 'Grupo', $g, CatalogoTemplate::grupo($arq), 'grupo', $versao);

            if ($g->trashed()) {
                continue; // grupo arquivado: filhos so voltam por "restaurar padrao" do grupo
            }

            foreach (CatalogoTemplate::SECOES as $secao => [$classe, $rotulo]) {
                $slugsSecao = [];
                foreach ($arq[$secao] ?? [] as $linha) {
                    $slugsSecao[] = $linha['slug'];
                    $row = $classe::withoutGlobalScopes()->withTrashed()->where('grupo_id', $g->id)->where('slug_template', $linha['slug'])->first();
                    $this->classificar($out, $secao, $slug, $linha['slug'], $this->rotuloLinha($secao, $linha, $nomesItens), $rotulo, $row, CatalogoTemplate::campos($secao, $linha), $secao, $versao);
                }
                $this->removidosDoPadrao($out, $secao, $slug, $classe::withoutGlobalScopes()->withTrashed()->where('grupo_id', $g->id)->whereNotNull('slug_template'), $slugsSecao, fn ($r) => $this->rotuloRow($secao, $r), $rotulo);
            }
        }

        return $out;
    }

    /** Quantas entradas o tenant pode aceitar (para o aviso "atualizacao disponivel"). */
    public function pendentes(Tenant $tenant, ?string $dir = null): int
    {
        return collect($this->diferencas($tenant, $dir))->whereIn('acao', self::ACOES_ACEITAVEIS)->count();
    }

    private function classificar(array &$out, string $tipo, ?string $grupo, string $slug, string $rotulo, string $tipoRotulo, ?Model $row, array $padrao, string $secao, int $versao): void
    {
        $chave = $tipo.':'.($grupo ?? '').':'.$slug;
        $base = ['chave' => $chave, 'tipo' => $tipo, 'grupo' => $grupo, 'slug' => $slug, 'rotulo' => $rotulo, 'tipo_rotulo' => $tipoRotulo, 'versao' => $versao, 'id' => $row?->getKey()];

        if (! $row) {
            if ($tipo !== 'grupo') { // grupo ausente e tratado por grupo_novo
                $out[$chave] = $base + ['acao' => 'novo', 'campos' => []];
            }

            return;
        }

        if (method_exists($row, 'trashed') && $row->trashed()) {
            $out[$chave] = $base + ['acao' => 'arquivado', 'campos' => $this->campos($row, $secao, $padrao, true)];

            return;
        }

        $campos = $this->campos($row, $secao, $padrao, false);

        if ($campos === []) {
            return;
        }

        $out[$chave] = $base + ['acao' => $row->personalizado ? 'personalizado' : 'atualizar', 'campos' => $campos];
    }

    private function removidosDoPadrao(array &$out, string $tipo, ?string $grupo, $consulta, array $slugsDoTemplate, \Closure $rotulo, string $tipoRotulo): void
    {
        foreach ($consulta->get() as $row) {
            if (! in_array($row->slug_template, $slugsDoTemplate, true) && ! ($row->trashed() ?? false)) {
                $chave = $tipo.':'.($grupo ?? '').':'.$row->slug_template;
                $out[$chave] = ['chave' => $chave, 'tipo' => $tipo, 'grupo' => $grupo, 'slug' => $row->slug_template, 'rotulo' => $rotulo($row), 'tipo_rotulo' => $tipoRotulo,
                    'acao' => 'removido_do_padrao', 'campos' => [], 'id' => $row->getKey()];
            }
        }
    }

    /** @return array<string, array{atual: mixed, padrao: mixed}> so os campos que diferem */
    private function campos(Model $row, string $secao, array $padrao, bool $arquivado): array
    {
        $atual = $this->valoresDaLinha($row, $secao);
        $dif = [];

        foreach ($padrao as $campo => $valorPadrao) {
            if ($campo === 'ordem') {
                continue; // ordem e cosmetica (o tenant reordena pela tabela): nao e diferenca de regra
            }

            $a = $atual[$campo] ?? null;

            if ($this->normalizar($a) !== $this->normalizar($valorPadrao)) {
                $dif[$campo] = ['atual' => $a, 'padrao' => $valorPadrao];
            }
        }

        if ($arquivado) {
            $dif['arquivado'] = ['atual' => true, 'padrao' => false];
        }

        return $dif;
    }

    /** @return array<string, mixed> */
    private function valoresDaLinha(Model $row, string $secao): array
    {
        $v = $row->only(array_keys($this->colunas($secao)));

        if ($secao === 'requisitos') {
            $v['item_slug'] = CatalogoItem::withoutGlobalScopes()->withTrashed()->find($row->item_id)?->slug;
        }

        if ($secao === 'grupo') {
            $v['is_active'] = (bool) $row->is_active;
        }

        return $v;
    }

    /** colunas comparaveis de cada tipo (as mesmas chaves que o CatalogoTemplate produz, menos item_slug). */
    private function colunas(string $secao): array
    {
        $exemplo = match ($secao) {
            'grupo' => CatalogoTemplate::grupo(['grupo' => ['nome' => 'x', 'medidor' => 'x', 'criticidade_padrao' => 'x']]),
            'item' => CatalogoTemplate::item(['tipo' => 'x', 'nome' => 'x']),
            'atributos' => CatalogoTemplate::campos('atributos', ['slug' => 'x', 'rotulo' => 'x', 'tipo' => 'x']),
            'requisitos' => CatalogoTemplate::campos('requisitos', ['item' => 'x', 'efeito' => 'x']),
            'preventivas' => CatalogoTemplate::campos('preventivas', ['nome' => 'x', 'medidor' => 'x', 'intervalo' => 1]),
            'operadores' => CatalogoTemplate::campos('operadores', ['habilitacao' => 'x']),
            'pecas_criticas' => CatalogoTemplate::campos('pecas_criticas', ['descricao' => 'x']),
            'sla' => CatalogoTemplate::campos('sla', ['criticidade' => 'x']),
            'regras' => CatalogoTemplate::campos('regras', ['nome' => 'x', 'efeito' => 'x']),
        };
        unset($exemplo['item_slug']);

        return $exemplo;
    }

    private function normalizar(mixed $v): mixed
    {
        if (is_array($v)) {
            $v = array_map(fn ($x) => $this->normalizar($x), $v);

            return $v === [] ? null : $v;
        }

        if ($v === '' || $v === null) {
            return null;
        }

        return is_bool($v) ? $v : (is_numeric($v) ? $v + 0 : $v);
    }

    private function rotuloLinha(string $secao, array $l, array $nomesItens = []): string
    {
        if ($secao === 'requisitos') {
            return (string) ($nomesItens[$l['item']] ?? $l['item']);
        }

        return (string) ($l['rotulo'] ?? $l['nome'] ?? $l['habilitacao'] ?? $l['descricao'] ?? $l['item'] ?? $l['criticidade'] ?? $l['slug']);
    }

    private function rotuloRow(string $secao, Model $r): string
    {
        if ($secao === 'requisitos') {
            return (string) (CatalogoItem::withoutGlobalScopes()->withTrashed()->find($r->item_id)?->nome ?? $r->slug);
        }

        return (string) ($r->rotulo ?? $r->nome ?? $r->habilitacao ?? $r->descricao ?? $r->criticidade ?? $r->slug);
    }

    // ================================================================== acoes

    /** Aceita atualizacoes (novo / atualizar / grupo_novo). Personalizados nunca entram aqui. */
    public function aceitar(Tenant $tenant, array $chaves, ?string $justificativa = null, ?User $usuario = null, ?string $dir = null): array
    {
        return $this->executar($tenant, $chaves, 'aceitar', $justificativa, $usuario, $dir);
    }

    /** Restaura o padrao (sobrescreve a personalizacao do tenant): sempre com justificativa. */
    public function restaurar(Tenant $tenant, array $chaves, string $justificativa, ?User $usuario = null, ?string $dir = null): array
    {
        $this->exigirJustificativa($justificativa);

        return $this->executar($tenant, $chaves, 'restaurar', $justificativa, $usuario, $dir);
    }

    /** Restaura o grupo inteiro: perfil do grupo + todo item de sistema (personalizado, arquivado, alterado ou ausente). Itens criados pelo tenant ficam intactos. */
    public function restaurarGrupo(Tenant $tenant, AssetCategory $grupo, string $justificativa, ?User $usuario = null, ?string $dir = null): array
    {
        $this->exigirJustificativa($justificativa);

        if (! $grupo->slug_template) {
            throw ValidationException::withMessages(['grupo' => 'Este grupo foi criado por você, não vem do padrão: não há o que restaurar.']);
        }

        $chaves = collect($this->diferencas($tenant, $dir))->filter(fn ($e) => $e['grupo'] === $grupo->slug_template && in_array($e['acao'], self::ACOES_RESTAURAVEIS, true))->keys()->all();

        return $this->executar($tenant, $chaves, 'restaurar', $justificativa, $usuario, $dir);
    }

    /** "Ignorar": mantem a versao do tenant (vira personalizado) e para de oferecer esta atualizacao. */
    public function ignorar(Tenant $tenant, array $chaves, ?User $usuario = null, ?string $dir = null): int
    {
        $diff = $this->diferencas($tenant, $dir);
        $n = 0;

        foreach ($chaves as $chave) {
            $e = $diff[$chave] ?? null;

            if (! $e || $e['acao'] !== 'atualizar') {
                throw ValidationException::withMessages(['chaves' => 'Só uma atualização disponível pode ser ignorada.']);
            }

            $row = $this->linhaDe($tenant, $e);
            $row->forceFill(['personalizado' => true, 'versao_template' => $e['versao']])->saveQuietly();
            $this->log($this->grupoDe($tenant, $e) ?? $row, 'Catalogo: atualizacao de template ignorada', $usuario, ['item' => $e['chave'], 'campos' => $e['campos']]);
            $n++;
        }

        return $n;
    }

    /**
     * Previa de impacto na aptidao: simula aceitar/restaurar dentro de uma transacao que e DESFEITA.
     *
     * @return array{total: int, mudaram: int, transicoes: array<string, int>}
     */
    public function previa(Tenant $tenant, array $chaves, string $modo = 'aceitar', ?string $dir = null): array
    {
        return $this->executar($tenant, $chaves, $modo, null, null, $dir, simular: true)['impacto'];
    }

    // ================================================================== nucleo

    private function executar(Tenant $tenant, array $chaves, string $modo, ?string $justificativa, ?User $usuario, ?string $dir, bool $simular = false): array
    {
        $tpl = $this->instalador->carregarTemplate($dir);
        $diff = $this->diferencas($tenant, $dir);
        $permitidas = $modo === 'aceitar' ? self::ACOES_ACEITAVEIS : self::ACOES_RESTAURAVEIS;
        $sel = [];

        foreach (array_unique($chaves) as $chave) {
            $e = $diff[$chave] ?? null;

            if (! $e || ! in_array($e['acao'], $permitidas, true)) {
                throw ValidationException::withMessages(['chaves' => "Item inválido para esta ação: {$chave}."]);
            }

            $sel[$chave] = $e;
        }

        $sensiveis = [];
        foreach ($sel as $e) {
            array_push($sensiveis, ...$this->sensiveis($e));
        }

        if ($sensiveis !== [] && ! $simular && mb_strlen(trim((string) $justificativa)) < 5) {
            throw ValidationException::withMessages(['justificativa' => CatalogoGestao::AVISO_RESPONSAVEL_TECNICO.' Informe a justificativa.']);
        }

        $grupoIds = collect($sel)->map(fn ($e) => $this->grupoDe($tenant, $e)?->id)->filter()->unique()->values()->all();
        $impacto = ['total' => 0, 'mudaram' => 0, 'transicoes' => []];
        $aplicadas = 0;

        DB::beginTransaction();

        try {
            $antes = $simular ? $this->statusDosAtivos($grupoIds) : [];

            foreach ($sel as $e) {
                $this->aplicarEntrada($tenant, $e, $tpl, $dir);
                $aplicadas++;
            }

            if ($simular) {
                $depois = $this->statusDosAtivos($grupoIds);
                foreach ($antes as $id => $st) {
                    $impacto['total']++;

                    if (($depois[$id] ?? $st) !== $st) {
                        $impacto['mudaram']++;
                        $k = $st.'>'.$depois[$id];
                        $impacto['transicoes'][$k] = ($impacto['transicoes'][$k] ?? 0) + 1;
                    }
                }
            }

            $simular ? DB::rollBack() : DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            throw $e;
        }

        if (! $simular) {
            $this->registrar($tenant, $sel, $modo, $justificativa, $sensiveis, $usuario);

            foreach ($grupoIds as $gid) {
                RecalcularAptidaoGrupoJob::dispatch($gid);
            }
        }

        return ['aplicadas' => $aplicadas, 'grupos' => $grupoIds, 'sensiveis' => $sensiveis, 'impacto' => $impacto];
    }

    private function aplicarEntrada(Tenant $tenant, array $e, array $tpl, ?string $dir): void
    {
        $tid = (string) $tenant->id;

        if ($e['acao'] === 'grupo_novo') {
            $this->instalador->instalar($tenant, $dir, [$e['slug']]);

            return;
        }

        if ($e['tipo'] === 'item') {
            $item = collect($tpl['biblioteca']['itens'])->firstWhere('slug', $e['slug']);
            $versao = (int) ($tpl['biblioteca']['versao'] ?? 1);
            $this->gravar(CatalogoItem::class, $tid, ['slug' => $e['slug']], CatalogoTemplate::item($item), $e, $versao, ['ordem' => 0]);

            return;
        }

        $arq = $tpl['grupos'][$e['grupo']];
        $versao = (int) ($arq['versao'] ?? 1);

        if ($e['tipo'] === 'grupo') {
            $g = $this->linhaDe($tenant, $e);
            $campos = CatalogoTemplate::grupo($arq);
            unset($campos['ordem']); // preserva a ordem escolhida pelo tenant
            $g->forceFill($campos + ['versao_template' => $versao, 'personalizado' => false, 'deleted_at' => null])->saveQuietly();

            return;
        }

        $grupo = AssetCategory::withoutGlobalScopes()->withTrashed()->where('tenant_id', $tid)->where('slug_template', $e['grupo'])->firstOrFail();
        [$classe] = CatalogoTemplate::SECOES[$e['tipo']];
        $linha = collect($arq[$e['tipo']])->firstWhere('slug', $e['slug']);
        $itens = $this->garantirItens($tid, $tpl, $linha['item'] ?? null);

        $this->gravar($classe, $tid, ['grupo_id' => $grupo->id, 'slug' => $e['slug']], CatalogoTemplate::camposDb($e['tipo'], $linha, $itens), $e, $versao, ['ordem' => (int) array_search($e['slug'], array_column($arq[$e['tipo']], 'slug'), true)]);
    }

    /** Cria (novo) ou repoe os valores do padrao (atualizar/personalizado/arquivado): sempre "de sistema", nao personalizado, ativo, nao arquivado. */
    private function gravar(string $classe, string $tid, array $chave, array $campos, array $e, int $versao, array $extra): void
    {
        $row = $classe::withoutGlobalScopes()->withTrashed()->where('tenant_id', $tid)->where($chave)->first();
        $meta = ['origem' => CatalogoModel::ORIGEM_SISTEMA, 'slug_template' => $e['slug'], 'versao_template' => $versao, 'personalizado' => false, 'deleted_at' => null];

        if ($row) {
            $row->forceFill($campos + $meta)->saveQuietly();

            return;
        }

        $classe::withoutGlobalScopes()->create(['tenant_id' => $tid] + $chave + $campos + $meta + $extra);
    }

    /** @return array<string, string> slug => id dos itens da biblioteca do tenant (cria o que faltar, a partir do template) */
    private function garantirItens(string $tid, array $tpl, ?string $slugNecessario): array
    {
        $mapa = CatalogoItem::withoutGlobalScopes()->withTrashed()->where('tenant_id', $tid)->pluck('id', 'slug')->all();

        if ($slugNecessario && ! isset($mapa[$slugNecessario])) {
            $item = collect($tpl['biblioteca']['itens'])->firstWhere('slug', $slugNecessario);
            $mapa[$slugNecessario] = CatalogoItem::withoutGlobalScopes()->create(['tenant_id' => $tid, 'slug' => $slugNecessario, 'origem' => CatalogoModel::ORIGEM_SISTEMA,
                'slug_template' => $slugNecessario, 'versao_template' => (int) ($tpl['biblioteca']['versao'] ?? 1), 'personalizado' => false] + CatalogoTemplate::item($item))->id;
        }

        return $mapa;
    }

    // ================================================================== sensibilidade, impacto, log

    /** @return array<int, string> */
    private function sensiveis(array $e): array
    {
        if (in_array($e['acao'], ['novo', 'grupo_novo'], true)) {
            return [];
        }

        $c = $e['campos'];
        $rotulo = $e['rotulo'];
        $r = [];

        if ($e['tipo'] === 'requisitos') {
            $antes = $c['efeito']['atual'] ?? null;
            $depois = $c['efeito']['padrao'] ?? null;

            if (isset($c['efeito']) && in_array($antes, ['IMP', 'DOC', 'INSP'], true) && $depois !== $antes) {
                $r[] = "{$rotulo}: {$antes} -> {$depois}";
            }
        }

        if ($e['tipo'] === 'preventivas' && isset($c['intervalo']) && (int) $c['intervalo']['padrao'] > (int) $c['intervalo']['atual']) {
            $r[] = "{$rotulo}: intervalo {$c['intervalo']['atual']} -> {$c['intervalo']['padrao']}";
        }

        if (in_array($e['tipo'], ['requisitos', 'preventivas'], true) && isset($c['ativo']) && $c['ativo']['atual'] && ! $c['ativo']['padrao']) {
            $r[] = "{$rotulo}: desativado";
        }

        return $r;
    }

    /** @return array<string, string> asset_id => status */
    private function statusDosAtivos(array $grupoIds): array
    {
        $out = [];

        foreach ($grupoIds as $gid) {
            $g = AssetCategory::withoutGlobalScopes()->withTrashed()->find($gid);

            if (! $g) {
                continue;
            }

            $perfil = PerfilDoGrupo::doGrupo($g);

            Asset::withoutGlobalScopes()->where('tenant_id', $g->tenant_id)->where('asset_category_id', $g->id)->whereNull('deleted_at')
                ->chunkById(100, function ($lote) use ($perfil, &$out) {
                    foreach ($lote as $a) {
                        $out[$a->id] = $this->motor->avaliar($a, $perfil)->status;
                    }
                });
        }

        return $out;
    }

    private function registrar(Tenant $tenant, array $sel, string $modo, ?string $justificativa, array $sensiveis, ?User $usuario): void
    {
        $porGrupo = collect($sel)->groupBy(fn ($e) => $e['grupo'] ?? '_biblioteca');

        foreach ($porGrupo as $slug => $entradas) {
            $alvo = $slug === '_biblioteca' ? null : AssetCategory::withoutGlobalScopes()->withTrashed()->where('tenant_id', $tenant->id)->where('slug_template', $slug)->first();
            $alvo ??= $tenant; // itens da biblioteca nao tem grupo: o sujeito do log e o proprio tenant

            $this->log($alvo, $modo === 'aceitar' ? 'Catalogo: atualizacao de template aceita' : 'Catalogo: restaurado ao padrao', $usuario, [
                'justificativa' => $justificativa,
                'itens' => $entradas->map(fn ($e) => ['chave' => $e['chave'], 'acao' => $e['acao'], 'campos' => $e['campos']])->values()->all(),
                'mudancas_sensiveis' => $sensiveis,
                'aviso' => $sensiveis ? CatalogoGestao::AVISO_RESPONSAVEL_TECNICO : null,
            ]);
        }
    }

    private function log(Model $alvo, string $descricao, ?User $usuario, array $props): void
    {
        $usuario ??= auth()->user();
        $l = activity()->performedOn($alvo)->withProperties($props);

        if ($usuario) {
            $l->causedBy($usuario);
        }

        $l->log($descricao);
    }

    private function linhaDe(Tenant $tenant, array $e): Model
    {
        $tid = (string) $tenant->id;

        return match ($e['tipo']) {
            'grupo' => AssetCategory::withoutGlobalScopes()->withTrashed()->where('tenant_id', $tid)->where('slug_template', $e['slug'])->firstOrFail(),
            'item' => CatalogoItem::withoutGlobalScopes()->withTrashed()->where('tenant_id', $tid)->where('slug', $e['slug'])->firstOrFail(),
            default => CatalogoTemplate::SECOES[$e['tipo']][0]::withoutGlobalScopes()->withTrashed()->where('tenant_id', $tid)->where('slug_template', $e['slug'])
                ->where('grupo_id', $this->grupoDe($tenant, $e)->id)->firstOrFail(),
        };
    }

    private function grupoDe(Tenant $tenant, array $e): ?AssetCategory
    {
        return $e['grupo'] ? AssetCategory::withoutGlobalScopes()->withTrashed()->where('tenant_id', $tenant->id)->where('slug_template', $e['grupo'])->first() : null;
    }

    private function exigirJustificativa(string $justificativa): void
    {
        if (mb_strlen(trim($justificativa)) < 5) {
            throw ValidationException::withMessages(['justificativa' => 'Informe a justificativa.']);
        }
    }
}
