<?php

namespace App\Filament\Central\Resources\DashboardTemplateResource\RelationManagers;

use App\Dashboards\Personalizacao\DashboardTemplateService;
use App\Models\Tenant;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use InvalidArgumentException;

/** Empresas que veem este dashboard. */
class AssignmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'assignments';

    protected static ?string $title = 'Empresas atribuídas';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('tenant'))
            ->columns([
                Tables\Columns\TextColumn::make('tenant.name')->label('Empresa')->searchable(),
                Tables\Columns\TextColumn::make('created_at')->label('Atribuído em')->dateTime('d/m/Y H:i'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('atribuir')
                    ->label('Atribuir a empresas')->icon('heroicon-o-plus')
                    ->form([
                        Forms\Components\Select::make('tenant_ids')->label('Empresas')->multiple()->searchable()->required()
                            ->options(fn () => Tenant::query()->when($this->getOwnerRecord()->tenant_id, fn ($q, $id) => $q->whereKey($id))->orderBy('name')->pluck('name', 'id')),
                    ])
                    ->action(function (array $data) {
                        try {
                            $novas = DashboardTemplateService::atribuir($this->getOwnerRecord(), $data['tenant_ids']);
                        } catch (InvalidArgumentException $e) {
                            Notification::make()->title('Não foi possível atribuir')->body($e->getMessage())->danger()->send();

                            return;
                        }
                        Notification::make()->title($novas.' atribuição(ões) nova(s)')->success()->send();
                    }),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make()->label('Remover')->modalHeading('Remover atribuição')
                    ->modalDescription('A empresa deixa de ver este dashboard e volta aos dashboards padrão dos módulos.'),
            ]);
    }
}
