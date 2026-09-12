<?php

namespace App\Filament\Central\Resources;

use App\Filament\Central\Resources\LandingPageLeadResource\Pages;
use App\Filament\Central\Resources\LandingPageLeadResource\RelationManagers;
use App\Models\LandingPageLead;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class LandingPageLeadResource extends Resource
{
    protected static ?string $model = LandingPageLead::class;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';
    protected static ?string $navigationLabel = 'Leads das Landing Pages';
    protected static ?string $navigationGroup = 'Comercial';
    protected static ?int $navigationSort = 50;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(191),
                Forms\Components\TextInput::make('email')
                    ->email()
                    ->required()
                    ->maxLength(191),
                Forms\Components\TextInput::make('phone')
                    ->tel()
                    ->required()
                    ->maxLength(191),
                Forms\Components\TextInput::make('company')
                    ->required()
                    ->maxLength(191),
                Forms\Components\TextInput::make('segment')
                    ->required()
                    ->maxLength(191),
                Forms\Components\TextInput::make('product')
                    ->required()
                    ->maxLength(191)
                    ->default('wms'),
                Forms\Components\TextInput::make('status')
                    ->required()
                    ->maxLength(191)
                    ->default('novo'),
                Forms\Components\Textarea::make('notes')
                    ->columnSpanFull(),
                Forms\Components\DateTimePicker::make('contacted_at'),
                Forms\Components\DateTimePicker::make('converted_at'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->copyable()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Telefone')
                    ->copyable(),
                Tables\Columns\TextColumn::make('company')
                    ->label('Empresa')
                    ->searchable(),
                Tables\Columns\BadgeColumn::make('product')
                    ->label('Produto')
                    ->color(fn (string $state): string => match ($state) {
                        'crm' => 'blue',
                        'wms' => 'purple',
                        'frota' => 'orange',
                        'os' => 'green',
                        default => 'gray',
                    }),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'primary' => 'novo',
                        'warning' => 'contatado',
                        'success' => 'convertido',
                        'danger' => 'rejeitado',
                    ]),
                Tables\Columns\TextColumn::make('contacted_at')
                    ->label('Contatado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Recebido em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('converted_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLandingPageLeads::route('/'),
            'create' => Pages\CreateLandingPageLead::route('/create'),
            'edit' => Pages\EditLandingPageLead::route('/{record}/edit'),
        ];
    }
}
