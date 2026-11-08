<?php

namespace App\Filament\Resources\CkTemplateResource\Pages;

use App\Filament\Resources\CkTemplateResource;
use App\Services\Checklists\ChecklistInstalador;
use App\Support\Tenancy;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListCkTemplates extends ListRecords
{
    protected static string $resource = CkTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('instalar')->label('Instalar checklists padrão')->icon('heroicon-o-arrow-down-tray')->color('gray')
                ->visible(fn () => CkTemplateResource::canCreate())
                ->requiresConfirmation()
                ->modalDescription('Copia os checklists padrão por grupo de equipamento para a sua empresa. Nunca sobrescreve o que você já editou nem recria o que você arquivou. Os itens que citam norma devem ser validados com o responsável técnico.')
                ->action(function () {
                    $tenant = Tenancy::current();

                    if (! $tenant) {
                        Notification::make()->warning()->title('Escolha a empresa atuante antes de instalar.')->send();

                        return;
                    }

                    $r = app(ChecklistInstalador::class)->instalar($tenant);
                    $novos = $r['criados']['templates'] ?? 0;
                    $body = $novos > 0 ? "{$novos} checklist(s) instalado(s)." : 'Nada novo para instalar.';

                    if ($r['avisos']) {
                        $body .= ' '.implode(' ', $r['avisos']);
                    }

                    Notification::make()->success()->title('Checklists padrão')->body($body)->send();
                }),
            Actions\CreateAction::make()->label('Novo checklist'),
        ];
    }
}
