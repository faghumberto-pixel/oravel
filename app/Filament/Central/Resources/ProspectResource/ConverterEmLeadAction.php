<?php

namespace App\Filament\Central\Resources\ProspectResource;

use App\Filament\Central\Resources\SalesLeadResource;
use App\Models\Prospect;
use App\Services\ProspectToLead;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;

/** Botao "Converter em lead": cria o SalesLead (CRM Comercial/Funil/Kanban) e mostra o atalho para ele. */
class ConverterEmLeadAction
{
    public static function make(?callable $recordResolver = null): Action
    {
        return Action::make('converterEmLead')->label('Converter em lead')->icon('heroicon-o-arrow-right-circle')->color('success')
            ->visible(fn (?Prospect $record = null): bool => ($record ?? ($recordResolver ? $recordResolver() : null))?->sales_lead_id === null)
            ->requiresConfirmation()
            ->modalHeading('Converter em lead do CRM Comercial?')
            ->modalDescription('Cria o lead em Prospecção (aparece no CRM Comercial, Funil e Kanban), copia os contatos já feitos e marca este prospect como "Virou lead".')
            ->action(function (?Prospect $record = null) use ($recordResolver): void {
                $p = $record ?? $recordResolver();
                $lead = ProspectToLead::converter($p, auth()->user());
                Notification::make()->success()->title('Lead criado')
                    ->actions([\Filament\Notifications\Actions\Action::make('abrir')->label('Abrir lead')->url(SalesLeadResource::getUrl('edit', ['record' => $lead]))])
                    ->send();
            });
    }
}
