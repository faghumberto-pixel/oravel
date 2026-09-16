<?php

namespace App\Filament\Central\Resources;

use App\Filament\Central\Resources\TenantComplianceResource\Pages;
use App\Models\Tenant;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

class TenantComplianceResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'Conformidade & Compliance';

    protected static ?string $navigationLabel = 'Status de Conformidade dos Clientes';

    protected static ?string $pluralModelLabel = 'Tenants';

    protected static ?string $modelLabel = 'Tenant';

    protected static bool $isScopedToTenant = false;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Informações do Tenant')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Empresa')
                        ->disabled(),

                    Forms\Components\TextInput::make('slug')
                        ->label('Slug')
                        ->disabled(),

                    Forms\Components\Select::make('plan_id')
                        ->label('Plano')
                        ->relationship('plan', 'name')
                        ->disabled(),
                ]),

            Forms\Components\Section::make('Status de Conformidade')
                ->schema([
                    Forms\Components\Textarea::make('compliance_notes')
                        ->label('Notas Internas')
                        ->rows(3),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Empresa')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                BadgeColumn::make('signature_status')
                    ->label('Contrato SLA+LGPD')
                    ->getStateUsing(function (Tenant $record) {
                        if ($record->signature) {
                            return '✅ Assinado';
                        }
                        if ($record->signature_required_by && now()->isAfter($record->signature_required_by)) {
                            return '⚠️ Vencido';
                        }
                        if ($record->signature_required_by) {
                            $days = now()->diffInDays($record->signature_required_by, false);
                            return "⏳ {$days}d";
                        }
                        return '❓ Sem prazo';
                    })
                    ->color(function (Tenant $record) {
                        if ($record->signature) return 'success';
                        if ($record->signature_required_by && now()->isAfter($record->signature_required_by)) return 'danger';
                        return 'warning';
                    }),

                BadgeColumn::make('payment_status')
                    ->label('Pagamento')
                    ->getStateUsing(fn (Tenant $record) => match ($record->asaas_payment_status) {
                        'em_dia' => '✅ Em Dia',
                        'atrasado' => '⚠️ Atrasado',
                        default => '❓ Pendente',
                    })
                    ->color(function (Tenant $record) {
                        return match ($record->asaas_payment_status) {
                            'em_dia' => 'success',
                            'atrasado' => 'danger',
                            default => 'gray',
                        };
                    }),

                BadgeColumn::make('documentation_status')
                    ->label('Documentação')
                    ->getStateUsing(fn (Tenant $record) => $record->cpf_cnpj ? '✅ Completo' : '⏳ Pendente')
                    ->color(fn (Tenant $record) => $record->cpf_cnpj ? 'success' : 'warning'),

                TextColumn::make('signature.signed_at')
                    ->label('Contrato Assinado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('signature_required_by')
                    ->label('Prazo de Assinatura')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('plan.name')
                    ->label('Plano')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('status')
                    ->label('Status Tenant')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('signed')
                    ->query(fn (Builder $query) => $query->whereNotNull('signature_id'))
                    ->label('Apenas contrato assinado'),

                Filter::make('pending_signature')
                    ->query(fn (Builder $query) => $query->whereNull('signature_id'))
                    ->label('Apenas contrato pendente'),

                Filter::make('overdue')
                    ->query(function (Builder $query) {
                        return $query
                            ->whereNull('signature_id')
                            ->whereNotNull('signature_required_by')
                            ->where('signature_required_by', '<', now());
                    })
                    ->label('Apenas prazo vencido'),

                Filter::make('payment_overdue')
                    ->query(fn (Builder $query) => $query->where('asaas_payment_status', 'atrasado'))
                    ->label('Apenas pagamento atrasado'),

                Filter::make('documentation_pending')
                    ->query(fn (Builder $query) => $query->whereNull('cpf_cnpj'))
                    ->label('Apenas documentação pendente'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('send_reminder')
                    ->label('Enviar Lembrete')
                    ->icon('heroicon-o-envelope')
                    ->action(function (Tenant $record) {
                        if (!$record->signature && $record->signature_required_by) {
                            $adminUser = $record->users()
                                ->whereHas('roles', fn($q) => $q->where('name', 'admin'))
                                ->first();

                            if ($adminUser) {
                                \Mail::to($adminUser->email)->send(
                                    new \App\Mail\SignatureReminderMail($record)
                                );

                                \Filament\Notifications\Notification::make()
                                    ->title('Lembrete Enviado')
                                    ->body("Email enviado para {$adminUser->email}")
                                    ->success()
                                    ->send();
                            }
                        }
                    })
                    ->visible(fn (Tenant $record) => !$record->signature && $record->signature_required_by),
            ])
            ->defaultSort('signature_required_by', 'asc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTenantCompliance::route('/'),
            'view' => Pages\ViewTenantCompliance::route('/{record}'),
        ];
    }
}
