<?php

namespace App\Filament\Pages;

use App\Support\NotificationCategories;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\HtmlString;

/**
 * Cada pessoa escolhe quais tipos de aviso do sino quer receber (pedido do dono, 12/10/2026).
 * Marcado = recebe. Desmarcado = o aviso nem é gravado (App\Listeners\SkipMutedNotification).
 * Avarias e contrato assinado são obrigatórios e só aparecem como informação.
 */
class MinhasNotificacoes extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-bell-slash';

    protected static ?string $title = 'Minhas notificações';

    protected static ?string $slug = 'minhas-notificacoes';

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.pages.minhas-notificacoes';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        $muted = auth()->user()->muted_notification_categories ?? [];
        $this->form->fill([
            'receber' => array_values(array_diff(array_keys(NotificationCategories::optional()), $muted)),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                CheckboxList::make('receber')
                    ->label('Receber avisos de')
                    ->options(NotificationCategories::optional())
                    ->columns(2)
                    ->bulkToggleable(),
                Placeholder::make('obrigatorios')
                    ->label('Sempre ativos')
                    ->content(new HtmlString(e(implode(' · ', NotificationCategories::mandatory())).'<br><small>Fazem parte do fluxo jurídico e de segurança e não podem ser desligados.</small>')),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $receber = $this->form->getState()['receber'] ?? [];
        $muted = array_values(array_diff(array_keys(NotificationCategories::optional()), $receber));

        auth()->user()->forceFill(['muted_notification_categories' => $muted])->save();

        Notification::make()->title('Preferências salvas')->success()->send();
    }
}
