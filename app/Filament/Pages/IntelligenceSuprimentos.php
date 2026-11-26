<?php

namespace App\Filament\Pages;

use App\Domain\Intelligence\Models\SuprimentosCarga;
use App\Domain\Intelligence\Services\DiagnosticoCalculator;
use App\Domain\Intelligence\Suprimentos\Catalogo;
use App\Domain\Intelligence\Suprimentos\ExemploDeSuprimentos;
use App\Domain\Intelligence\Suprimentos\ImportadorSuprimentos;
use App\Domain\Intelligence\Suprimentos\IndicadoresDeSuprimentos;
use App\Domain\Intelligence\Suprimentos\ModeloSuprimentos;
use App\Domain\Intelligence\Trial\Services\TrialCiclo;
use App\Domain\Intelligence\Trial\Support\Planos;
use App\Support\Tenancy;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Url;

/**
 * Intelligence › Suprimentos: estoque, compras e movimentações lidos de uma planilha PRÓPRIA (modelo diferente do modelo geral).
 * Nota da área, indicadores com variação, quebra por categoria e listas de atenção. Módulo do degrau Standard: o item de menu aparece
 * sempre, desabilitado para quem ainda não o tem (ItemDeMenuDoModulo). Chaves de contrato: menu_intelligence_suprimentos e
 * tabela_intelligence_suprimentos.
 */
class IntelligenceSuprimentos extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static ?string $navigationGroup = 'Intelligence';

    protected static ?string $navigationLabel = 'Suprimentos';

    protected static ?int $navigationSort = 4;

    protected static ?string $title = 'Suprimentos';

    protected static ?string $slug = 'intelligence-suprimentos';

    protected static string $view = 'filament.pages.intelligence-suprimentos';

    // O item de menu é registrado à mão (ItemDeMenuDoModulo) para poder aparecer desabilitado.
    protected static bool $shouldRegisterNavigation = false;

    #[Url]
    public string $categoria = '';

    private ?SuprimentosCarga $cargaMemo = null;

    private bool $cargaCarregada = false;

    public static function canAccess(): bool
    {
        $u = auth()->user();
        if (! $u || $u->isSuperAdmin() || blank($u->tenant_id)) {
            return false;
        }

        return (bool) $u->can('viewAny', SuprimentosCarga::class)
            && $u->tenant?->hasFeature('menu_intelligence_suprimentos')
            && Planos::moduloLiberado((string) $u->tenant_id, 'suprimentos');
    }

    public function mount(): void
    {
        $trial = Tenancy::current() ? TrialCiclo::doTenant(Tenancy::current()->id) : null;
        if ($trial) {
            app(ExemploDeSuprimentos::class)->aoAbrir($trial); // 1ª abertura: mostra o exemplo; passado o prazo, apaga o que sobrou
        }
    }

    public function carga(): ?SuprimentosCarga
    {
        if (! $this->cargaCarregada) {
            $this->cargaCarregada = true;
            $this->cargaMemo = SuprimentosCarga::query()->orderByDesc('data_referencia')->orderByDesc('created_at')->first();
        }

        return $this->cargaMemo;
    }

    public function anterior(): ?SuprimentosCarga
    {
        $c = $this->carga();

        return $c ? SuprimentosCarga::query()->where('id', '!=', $c->id)->where('data_referencia', '<=', $c->data_referencia)->orderByDesc('data_referencia')->orderByDesc('created_at')->first() : null;
    }

    /** @return list<string> */
    public function categoriasDaCarga(): array
    {
        return array_column($this->carga()?->listas['por_categoria'] ?? [], 'categoria');
    }

    /**
     * Listas de atenção. Sem filtro, as guardadas na importação; com categoria, recalculadas sobre as linhas dessa carga.
     *
     * @return array<string, mixed>
     */
    public function listas(): array
    {
        $carga = $this->carga();
        if (! $carga) {
            return [];
        }
        $listas = $carga->listas ?? [];
        if ($this->categoria === '' || ! in_array($this->categoria, $this->categoriasDaCarga(), true)) {
            return $listas;
        }

        $itens = $carga->itens()->get()->map(fn ($i) => ['codigo' => $i->codigo, 'descricao' => $i->descricao, 'categoria' => $i->categoria, 'quantidade' => (float) $i->quantidade,
            'estoque_minimo' => $i->estoque_minimo !== null ? (float) $i->estoque_minimo : null, 'custo_unitario' => (float) $i->custo_unitario, 'ultima_saida' => $i->ultima_saida?->toDateString()])->all();
        $movs = $carga->movimentos()->where('tipo', 'saida')->get()->map(fn ($m) => ['tipo' => 'saida', 'data' => $m->data->toDateString(), 'codigo_item' => $m->codigo_item, 'quantidade' => (float) $m->quantidade])->all();
        $ref = Carbon::parse($carga->data_referencia);
        $inicio = $ref->copy()->subDays((int) $carga->periodo_dias)->toDateString();
        $janela = array_values(array_filter($movs, fn ($m) => $m['data'] >= $inicio && $m['data'] <= $ref->toDateString()));
        $calc = app(IndicadoresDeSuprimentos::class);

        return array_merge($listas, $calc->listasDeItens($itens, $calc->ultimaSaidaPorItem($itens, $movs, $ref), $ref, (int) config('intelligence.suprimentos.dias_parado'), $this->categoria, $janela, (int) $carga->periodo_dias));
    }

    public function faixaDaNota(float $nota): string
    {
        return app(DiagnosticoCalculator::class)->faixa($nota);
    }

    public function corDaFaixa(string $faixa): string
    {
        return ['Excelente' => '#16a34a', 'Bom' => '#65a30d', 'Atenção' => '#d97706', 'Crítico' => '#dc2626'][$faixa] ?? '#64748b';
    }

    public function formatar(float $valor, string $unidade): string
    {
        return match ($unidade) {
            'R$' => 'R$ '.number_format($valor, 2, ',', '.'),
            '%' => rtrim(rtrim(number_format($valor, 1, ',', '.'), '0'), ',').'%',
            default => rtrim(rtrim(number_format($valor, 1, ',', '.'), '0'), ',').' '.$unidade,
        };
    }

    /** Variação contra a carga anterior: [texto, bom?] ou null quando não há como comparar. */
    public function variacao(string $chave, array $ind): ?array
    {
        $ant = $this->anterior()?->indicadores[$chave]['valor'] ?? null;
        if ($ant === null || $ind['ruim'] === null) {
            return null;
        }
        $delta = round($ind['valor'] - $ant, 2);
        if ($delta == 0.0) {
            return ['sem mudança', null];
        }
        $melhorou = ($ind['bom'] > $ind['ruim']) ? $delta > 0 : $delta < 0;

        return [($delta > 0 ? '+' : '').rtrim(rtrim(number_format($delta, 1, ',', '.'), '0'), ',').' '.($ind['unidade'] === 'R$' ? '' : $ind['unidade']), $melhorou];
    }

    // ---- exemplo ----

    public function temExemplo(): bool
    {
        return ($t = Tenancy::current()) && ExemploDeSuprimentos::existe((string) $t->id);
    }

    public function podeVerExemplo(): bool
    {
        $t = Tenancy::current();
        $trial = $t ? TrialCiclo::doTenant($t->id) : null;

        return $trial && ExemploDeSuprimentos::podeGerar($trial);
    }

    public function diasDoExemplo(): ?int
    {
        $t = Tenancy::current();
        $trial = $t ? TrialCiclo::doTenant($t->id) : null;

        return $trial ? ExemploDeSuprimentos::diasRestantes($trial) : null;
    }

    public function limparExemplo(): void
    {
        abort_unless(static::canAccess(), 403);
        $n = app(ExemploDeSuprimentos::class)->limpar(Tenancy::current());
        $this->cargaCarregada = false;
        Notification::make()->success()->title($n > 0 ? 'Dados de exemplo apagados' : 'Não havia dados de exemplo')
            ->body($n > 0 ? 'Agora é só enviar a sua planilha de Suprimentos.' : null)->send();
    }

    public function verExemplo(): void
    {
        abort_unless(static::canAccess(), 403);
        $t = Tenancy::current();
        $trial = TrialCiclo::doTenant($t->id);
        if (! $trial || ! ExemploDeSuprimentos::podeGerar($trial)) {
            Notification::make()->warning()->title('Não foi possível mostrar o exemplo')->body('Você já tem dados próprios ou o prazo do exemplo terminou.')->send();

            return;
        }
        try {
            app(ExemploDeSuprimentos::class)->gerar($t);
            $this->cargaCarregada = false;
            Notification::make()->success()->title('Dados de exemplo carregados')->body('São fictícios. Limpe-os quando for começar com os seus.')->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->danger()->title('Não consegui carregar o exemplo agora. Tente de novo em instantes.')->send();
        }
    }

    // ---- ações do topo ----

    protected function getHeaderActions(): array
    {
        return [
            Action::make('modelo')->label('Modelo de Suprimentos (Excel)')->icon('heroicon-o-arrow-down-tray')->color('gray')
                ->action(fn () => response()->streamDownload(fn () => ModeloSuprimentos::escrever('php://output'), ModeloSuprimentos::ARQUIVO,
                    ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])),
            Action::make('importar')->label('Importar planilha de Suprimentos')->icon('heroicon-o-arrow-up-tray')
                ->modalHeading('Importar a planilha de Suprimentos')
                ->modalDescription('Use o modelo de Suprimentos (é diferente do modelo geral). Cada importação gera uma fotografia nova e nada é sobrescrito.')
                ->modalSubmitActionLabel('Importar')
                ->form([
                    Forms\Components\FileUpload::make('arquivo')->label('Planilha (.xlsx)')->disk('local')->directory('intelligence-imports')
                        ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->maxSize(10240)->required(),
                    Forms\Components\Toggle::make('simular')->label('Apenas validar (não grava nada)')
                        ->helperText('Mostra se a planilha está correta e quantos indicadores seriam calculados.')->default(true)->live(),
                    Forms\Components\Toggle::make('limpar_exemplo')->label('Apagar os dados de exemplo antes de importar')
                        ->helperText('Você tem dados de demonstração de Suprimentos. Se mantiver, eles se misturam com os seus na comparação entre fotografias.')->default(true)
                        ->visible(fn (Get $get) => ! $get('simular') && ($t = Tenancy::current()) && ExemploDeSuprimentos::existe((string) $t->id)),
                ])
                ->action(function (array $data) {
                    $tenant = Tenancy::current();
                    $path = Storage::disk('local')->path($data['arquivo']);
                    try {
                        if (! $tenant) {
                            Notification::make()->title('Selecione a empresa (tenant) antes de importar.')->danger()->send();

                            return;
                        }
                        $apagou = ! $data['simular'] && ($data['limpar_exemplo'] ?? false) && ExemploDeSuprimentos::existe((string) $tenant->id);
                        if ($apagou) {
                            app(ExemploDeSuprimentos::class)->limpar($tenant);
                        }
                        $r = app(ImportadorSuprimentos::class)->importar($path, $tenant, auth()->user(), (bool) $data['simular'], basename($data['arquivo']));
                        if ($apagou && ! $r['ok']) {
                            try {
                                app(ExemploDeSuprimentos::class)->gerar($tenant); // a planilha falhou: devolve o exemplo para a tela não ficar vazia
                            } catch (\Throwable) {
                            }
                        }
                    } finally {
                        Storage::disk('local')->delete($data['arquivo']);
                    }
                    $this->cargaCarregada = false;

                    if (! $r['ok']) {
                        Notification::make()->title('Importação não realizada')->body(implode('<br>', array_map('e', array_slice($r['erros'], 0, 15))))->danger()->persistent()->send();

                        return;
                    }
                    $linhas = ['Itens: '.$r['totais']['itens'].' · Compras: '.$r['totais']['compras'].' · Movimentações: '.$r['totais']['movimentos']];
                    if ($r['diagnostico']) {
                        $linhas[] = 'Nota de Suprimentos: '.number_format($r['diagnostico']['pontuacao'], 0).' ('.$r['diagnostico']['faixa'].')';
                    }
                    foreach ($r['avisos'] as $aviso) {
                        $linhas[] = 'Aviso: '.e($aviso);
                    }
                    Notification::make()->title(($r['simulacao'] ? 'SIMULAÇÃO (nada foi gravado): ' : 'Importação concluída: ').count($r['indicadores']).' indicadores calculados')
                        ->body(implode('<br>', $linhas))->success()->persistent()->send();
                }),
        ];
    }

    /** Rótulos das abas, para a ajuda da tela. */
    public function abas(): array
    {
        return [Catalogo::ABA_INVENTARIO, Catalogo::ABA_COMPRAS, Catalogo::ABA_MOVIMENTOS];
    }
}
