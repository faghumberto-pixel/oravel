<?php

namespace App\Filament\Pages;

use App\Models\Course;
use App\Models\Tenant;
use App\Services\ClientExcelImporter;
use App\Support\AppProfile;
use App\Support\ClientImport\ClientImportColumns;
use App\Support\ClientImport\ClientImportRelatorio;
use App\Support\Onboarding\OnboardingService;
use App\Support\Pacotes;
use App\Support\Tenancy;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * "Primeiros passos": checklist guiado da solução contratada (config/onboarding.php). Aparece como página inicial do administrador
 * enquanto houver passo pendente e a empresa não tiver dispensado. Os passos se completam sozinhos, pelos dados reais.
 */
class PrimeirosPassos extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-rocket-launch';

    protected static ?string $navigationLabel = 'Primeiros passos';

    protected static ?int $navigationSort = -20;

    protected static ?string $title = 'Primeiros passos';

    protected static ?string $slug = 'primeiros-passos';

    protected static string $view = 'filament.pages.primeiros-passos';

    public static function canAccess(): bool
    {
        $tenant = Tenancy::current();

        return (bool) (auth()->user()?->isAdmin() && $tenant && app(OnboardingService::class)->solucaoDe($tenant));
    }

    /** O item do menu fica sempre: com passos por fazer é "Primeiros passos"; concluídos, vira o "Guia dos módulos". */
    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    private static function concluido(): bool
    {
        return ! app(OnboardingService::class)->incompleto(Tenancy::current());
    }

    public static function getNavigationLabel(): string
    {
        return static::concluido() ? 'Guia dos módulos' : 'Primeiros passos';
    }

    public static function getNavigationIcon(): string|Htmlable|null
    {
        return static::concluido() ? 'heroicon-o-book-open' : 'heroicon-o-rocket-launch';
    }

    public function getTitle(): string|Htmlable
    {
        return static::concluido() ? 'Guia dos módulos' : 'Primeiros passos';
    }

    /**
     * Telas importantes de cada produto contratado (config/guia_modulos.php), só as que o usuário pode acessar.
     *
     * @return list<array{nome: string, cor: string, telas: list<array{titulo: string, descricao: string, icone: string, url: string}>}>
     */
    public function guiaDosModulos(): array
    {
        $tenant = $this->tenant();
        $guia = [];

        foreach (Pacotes::produtosDoTenant($tenant) as $codigo) {
            $telas = [];

            foreach ((array) config("guia_modulos.{$codigo}", []) as $tela) {
                $url = $this->urlDaAcao(isset($tela['recurso']) ? ['recurso' => $tela['recurso']] : ['pagina_filament' => $tela['pagina']]);

                if ($url) {
                    $telas[] = ['titulo' => $tela['titulo'], 'descricao' => $tela['descricao'], 'icone' => $tela['icone'], 'url' => $url];
                }
            }

            if ($telas) {
                $guia[] = ['nome' => Pacotes::nome($codigo), 'cor' => Pacotes::todos()[$codigo]['app']['cor'] ?? '#334155', 'telas' => $telas];
            }
        }

        return $guia;
    }

    public function mount(): void
    {
        app(OnboardingService::class)->registrarPrimeiroAcesso($this->tenant());
    }

    private function tenant(): Tenant
    {
        return Tenancy::current() ?? abort(403);
    }

    public function getHeading(): string
    {
        return '';
    }

    /** @return array<string, mixed> */
    public function getEstado(): array
    {
        return app(OnboardingService::class)->estado($this->tenant()) ?? abort(404);
    }

    public function nomeDoUsuario(): string
    {
        return explode(' ', trim((string) auth()->user()?->name))[0] ?: 'por aqui';
    }

    /** Endereço da ação de um passo (null = ação por botão da própria tela, ou o usuário não tem acesso). */
    public function urlDaAcao(array $acao): ?string
    {
        if (isset($acao['recurso'])) {
            $recurso = $acao['recurso'];

            return $recurso::canViewAny() ? $recurso::getUrl($acao['pagina'] ?? 'index') : null;
        }

        if (isset($acao['pagina_filament'])) {
            $pagina = $acao['pagina_filament'];

            return $pagina::canAccess() ? $pagina::getUrl() : null;
        }

        if (isset($acao['aula'])) {
            return Course::visibleToUser()->where('slug', $acao['aula'])->exists() ? route('academy.course', $acao['aula']) : null;
        }

        return null;
    }

    public function linkDeAjuda(array $estado): string
    {
        $passo = $estado['atual'] !== null ? $estado['passos'][$estado['atual']] : null;
        $texto = 'Olá! Sou '.auth()->user()?->name.', da empresa '.$this->tenant()->name.'.'
            .($passo ? " Travei no passo {$passo['numero']} ({$passo['titulo']}) dos primeiros passos no Oravel." : ' Concluí os primeiros passos no Oravel e gostaria de conversar.');

        return 'https://wa.me/'.config('onboarding.whatsapp').'?text='.rawurlencode($texto);
    }

    public function baixarModeloAction(): Action
    {
        return Action::make('baixarModelo')
            ->label('Baixar planilha modelo')
            ->action(fn () => response()->streamDownload(
                fn () => ClientImportColumns::writeTemplate('php://output', linhaDeExemplo: true),
                'modelo-importacao-clientes.xlsx',
                ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            ));
    }

    public function importarAction(): Action
    {
        return Action::make('importar')
            ->label('Enviar planilha preenchida')
            ->modalHeading('Enviar planilha de clientes')
            ->modalDescription('Use a planilha modelo, com até '.config('onboarding.importacao_max_linhas').' clientes por envio. As linhas com erro são puladas e você baixa a lista com o motivo de cada uma; as demais são gravadas.')
            ->modalSubmitActionLabel('Importar')
            ->form([
                FileUpload::make('arquivo')->label('Planilha (.xlsx)')->disk('local')->directory('client-imports')
                    ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->maxSize(10240)->required(),
            ])
            ->action(function (array $data) {
                $caminho = Storage::disk('local')->path($data['arquivo']);
                $tenant = $this->tenant();

                try {
                    $r = app(ClientExcelImporter::class)->import($caminho, $tenant, maxRows: (int) config('onboarding.importacao_max_linhas'));
                } finally {
                    Storage::disk('local')->delete($data['arquivo']);
                }

                $notificacao = Notification::make()->title(ClientImportRelatorio::resumo($r))->color($r['errors'] ? 'warning' : 'success')->persistent();

                if ($r['errors'] && $r['rejected'] !== []) {
                    $arquivo = $tenant->id.'-'.Str::uuid().'.csv';
                    Storage::disk('local')->put("onboarding-rejeitadas/{$arquivo}", ClientImportRelatorio::csvDasRejeitadas($r['rejected']));
                    $notificacao->actions([
                        NotificationAction::make('baixar')->label('Baixar linhas rejeitadas')
                            ->url(URL::temporarySignedRoute('onboarding.rejeitadas', now()->addMinutes(30), ['arquivo' => $arquivo])),
                    ]);
                } elseif ($r['errors']) {
                    $notificacao->body($r['errors'][0]['message']);
                }

                $notificacao->send();
            });
    }

    public function dispensar(): void
    {
        app(OnboardingService::class)->dispensar($this->tenant());

        $this->redirect(AppProfile::homeUrl(auth()->user(), AppProfile::ADMIN));
    }
}
