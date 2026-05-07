<?php

namespace App\Filament\Resources\MaintenanceOrderResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class CommunicationsRelationManager extends RelationManager
{
    protected static string $relationship = 'internalCommunications';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Textarea::make('message')
                    ->label('Mensagem')
                    ->required()
                    ->columnSpanFull(),
                
                // Captura automática do Usuário
                Forms\Components\Hidden::make('user_id')
                    ->default(Auth::id()),

                // Captura automática do Tenant
                Forms\Components\Hidden::make('tenant_id')
                    ->default(fn () => Auth::user()->tenant_id),
            ]);
    }

    // Este método garante que, mesmo que o campo esteja escondido, 
    // o tenant_id seja injetado no momento da criação.
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = Auth::user()->tenant_id;
        $data['user_id'] = Auth::id();

        return $data;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('message')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Data / Hora')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Emissor')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('maintenanceOrder.os_number')
                    ->label('OS')
                    ->color('primary')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('maintenanceOrder.asset.patrimonio')
                    ->label('Patrimônio'),

                Tables\Columns\TextColumn::make('message')
                    ->label('Mensagem')
                    ->wrap()
                    ->limit(100),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('Nova Mensagem'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}