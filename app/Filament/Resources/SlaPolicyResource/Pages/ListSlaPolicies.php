<?php

namespace App\Filament\Resources\SlaPolicyResource\Pages;

use App\Filament\Resources\SlaPolicyResource;
use App\Services\Sla\SlaService;
use App\Support\Tenancy;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListSlaPolicies extends ListRecords
{
    protected static string $resource = SlaPolicyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('configurar')->label('Pausas e alerta')->icon('heroicon-o-cog-6-tooth')->color('gray')
                ->modalHeading('Quando o relógio pausa e quando fica em risco')
                ->fillForm(fn () => ['pause_statuses' => SlaService::settings((string) Tenancy::current()?->id)->pause_statuses, 'warn_pct' => SlaService::settings((string) Tenancy::current()?->id)->warn_pct, 'escalate_pct' => SlaService::settings((string) Tenancy::current()?->id)->escalate_pct])
                ->form([
                    Forms\Components\CheckboxList::make('pause_statuses')->label('Status que pausam o relógio')
                        ->helperText('Enquanto a OS estiver em um destes status do Kanban, o prazo não corre (ex.: aguardando peça).')
                        ->options(SlaService::STATUS_PAUSA_OPCOES),
                    Forms\Components\TextInput::make('warn_pct')->label('Fica "em risco" a partir de')->numeric()->minValue(1)->maxValue(99)->suffix('% do prazo')->required(),
                    Forms\Components\TextInput::make('escalate_pct')->label('Escalona (avisa a gerência) a partir de')->numeric()->minValue(100)->maxValue(1000)->suffix('% do prazo')->required()
                        ->helperText('Passou do prazo e continua sem solução até este percentual: aviso de escalonamento.'),
                ])
                ->action(function (array $data): void {
                    SlaService::salvarSettings((string) Tenancy::current()?->id, $data['pause_statuses'] ?? [], (int) $data['warn_pct'], (int) $data['escalate_pct']);
                    Notification::make()->title('Configuração de SLA salva')->success()->send();
                }),
            Actions\CreateAction::make(),
        ];
    }
}
