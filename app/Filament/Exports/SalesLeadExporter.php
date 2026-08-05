<?php

namespace App\Filament\Exports;

use App\Models\SalesLead;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class SalesLeadExporter extends Exporter
{
    protected static ?string $model = SalesLead::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('company_name')->label('Empresa'),
            ExportColumn::make('pipeline_stage')
                ->label('Estágio')
                ->formatStateUsing(fn (?string $state) => $state ? (SalesLead::stageLabels()[$state] ?? $state) : null),
            ExportColumn::make('phone')->label('Telefone'),
            ExportColumn::make('email')->label('E-mail'),
            ExportColumn::make('website')->label('Site'),
            ExportColumn::make('city')->label('Cidade'),
            ExportColumn::make('uf')->label('UF'),
            ExportColumn::make('segment')->label('Segmento'),
            ExportColumn::make('source')
                ->label('Origem')
                ->formatStateUsing(fn (?string $state) => $state ? (SalesLead::sourceLabels()[$state] ?? $state) : null),
            ExportColumn::make('estimated_contract_value')->label('Valor Estimado'),
            ExportColumn::make('assignedUser.name')->label('Responsável'),
            ExportColumn::make('critical_pain')->label('Dor Crítica Mapeada'),
            ExportColumn::make('oravel_solution')->label('Solução Oravel'),
            ExportColumn::make('outreach_email_draft')->label('Rascunho E-mail de Prospecção'),
            ExportColumn::make('last_interaction_at')->label('Última Interação'),
            ExportColumn::make('created_at')->label('Criado em'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Sua exportação de leads comerciais foi concluída, '.number_format($export->successful_rows).' '.str('registro')->plural($export->successful_rows).' exportados.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('linha')->plural($failedRowsCount).' falharam ao exportar.';
        }

        return $body;
    }
}
