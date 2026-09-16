<?php

namespace App\Filament\Central\Resources\TenantComplianceResource\Pages;

use App\Filament\Central\Resources\TenantComplianceResource;
use App\Models\Tenant;
use Filament\Actions;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\BadgeEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewTenantCompliance extends ViewRecord
{
    protected static string $resource = TenantComplianceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()->hidden(),
            Actions\DeleteAction::make()->hidden(),
        ];
    }

    public function getContentTabLabel(): ?string
    {
        return 'Visão Geral';
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Informações do Tenant')
                    ->schema([
                        TextEntry::make('name')
                            ->label('Empresa'),
                        TextEntry::make('slug')
                            ->label('Slug'),
                        TextEntry::make('plan.name')
                            ->label('Plano'),
                    ])
                    ->columns(3),

                Section::make('Status de Conformidade')
                    ->schema([
                        BadgeEntry::make('signature_status')
                            ->label('Contrato SLA+LGPD')
                            ->getStateUsing(function (Tenant $record) {
                                if ($record->signature) {
                                    return '✅ Assinado em ' . $record->signature->signed_at->format('d/m/Y');
                                }
                                if ($record->signature_required_by && now()->isAfter($record->signature_required_by)) {
                                    return '⚠️ VENCIDO desde ' . $record->signature_required_by->format('d/m/Y');
                                }
                                if ($record->signature_required_by) {
                                    $days = now()->diffInDays($record->signature_required_by, false);
                                    return "⏳ Pendente ({$days} dias)";
                                }
                                return '❓ Sem prazo definido';
                            })
                            ->color(function (Tenant $record) {
                                if ($record->signature) return 'success';
                                if ($record->signature_required_by && now()->isAfter($record->signature_required_by)) return 'danger';
                                return 'warning';
                            }),

                        BadgeEntry::make('payment_status')
                            ->label('Pagamento')
                            ->getStateUsing(fn (Tenant $record) => match ($record->asaas_payment_status) {
                                'em_dia' => '✅ Em Dia',
                                'atrasado' => '⚠️ Atrasado',
                                default => '❓ Desconhecido',
                            })
                            ->color(function (Tenant $record) {
                                return match ($record->asaas_payment_status) {
                                    'em_dia' => 'success',
                                    'atrasado' => 'danger',
                                    default => 'gray',
                                };
                            }),

                        BadgeEntry::make('documentation_status')
                            ->label('Documentação')
                            ->getStateUsing(fn (Tenant $record) => $record->cpf_cnpj ? '✅ Completa' : '⏳ Incompleta')
                            ->color(fn (Tenant $record) => $record->cpf_cnpj ? 'success' : 'warning'),

                        BadgeEntry::make('access_status')
                            ->label('Acesso')
                            ->getStateUsing(function (Tenant $record) {
                                if (!$record->signature_required_by || !now()->isAfter($record->signature_required_by)) {
                                    return '✅ Ativo';
                                }
                                return '⚠️ Suspenso (prazo vencido)';
                            })
                            ->color(function (Tenant $record) {
                                if (!$record->signature_required_by || !now()->isAfter($record->signature_required_by)) {
                                    return 'success';
                                }
                                return 'danger';
                            }),
                    ])
                    ->columns(2),

                Section::make('Notas Internas')
                    ->schema([
                        TextEntry::make('compliance_notes')
                            ->label('')
                            ->default('Nenhuma nota registrada'),
                    ]),
            ]);
    }
}
