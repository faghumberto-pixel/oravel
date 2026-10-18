<?php

namespace App\Filament\Pages;

use App\Models\Course;
use App\Models\Tenant;
use App\Services\ClientExcelImporter;
use App\Support\AppProfile;
use App\Support\ClientImport\ClientImportColumns;
use App\Support\Onboarding\OnboardingService;
use App\Support\Tenancy;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;

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

    /** O item do menu fica enquanto houver passo por fazer; some quando tudo foi concluído. */
    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess() && app(OnboardingService::class)->incompleto(Tenancy::current());
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
                fn () => ClientImportColumns::writeTemplate('php://output'),
                'modelo-importacao-clientes.xlsx',
                ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            ));
    }

    public function importarAction(): Action
    {
        return Action::make('importar')
            ->label('Enviar planilha preenchida')
            ->modalHeading('Enviar planilha de clientes')
            ->modalDescription('Use a planilha modelo. As linhas com erro são puladas e listadas no final; as demais são gravadas.')
            ->modalSubmitActionLabel('Importar')
            ->form([
                FileUpload::make('arquivo')->label('Planilha (.xlsx)')->disk('local')->directory('client-imports')
                    ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->maxSize(10240)->required(),
            ])
            ->action(function (array $data) {
                $caminho = Storage::disk('local')->path($data['arquivo']);

                try {
                    $r = app(ClientExcelImporter::class)->import($caminho, $this->tenant());
                } finally {
                    Storage::disk('local')->delete($data['arquivo']);
                }

                $erros = count($r['errors']);
                Notification::make()->title("{$r['created']} importados".($erros ? ", {$erros} com erro" : ''))
                    ->color($erros ? 'warning' : 'success')->persistent()->send();
            });
    }

    public function dispensar(): void
    {
        app(OnboardingService::class)->dispensar($this->tenant());

        $this->redirect(AppProfile::homeUrl(auth()->user(), AppProfile::ADMIN));
    }
}
