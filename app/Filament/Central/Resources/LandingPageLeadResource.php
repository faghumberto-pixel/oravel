<?php

namespace App\Filament\Central\Resources;

use App\Filament\Central\Resources\LandingPageLeadResource\Pages;
use App\Filament\Central\Resources\LandingPageLeadResource\RelationManagers;
use App\Models\LandingPageLead;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class LandingPageLeadResource extends Resource
{
    protected static ?string $model = LandingPageLead::class;

    protected static ?string $navigationIcon = 'heroicon-o-phone';
    protected static ?string $navigationLabel = 'Leads das Landing Pages';
    protected static ?string $modelLabel = 'Lead';
    protected static ?string $pluralModelLabel = 'Leads';

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
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('phone')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('company')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('segment')
                    ->label('Landing Page')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('product')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color(fn (string $state): string => match($state) {
                        'CRM' => 'blue',
                        'WMS' => 'purple',
                        'FROTA' => 'orange',
                        'OS' => 'green',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('status')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color(fn (string $state): string => match($state) {
                        'novo' => 'primary',
                        'contatado' => 'warning',
                        'convertido' => 'success',
                        'rejeitado' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Data de Cadastro')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('contacted_at')
                    ->label('Data de Contato')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('converted_at')
                    ->label('Data de Conversão')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
            ])
            ->filters([
                SelectFilter::make('product')
                    ->options([
                        'CRM' => 'CRM',
                        'WMS' => 'WMS',
                        'FROTA' => 'Frota',
                        'OS' => 'Ordem de Serviço',
                    ]),
                SelectFilter::make('status')
                    ->options([
                        'novo' => 'Novo',
                        'contatado' => 'Contatado',
                        'convertido' => 'Convertido',
                        'rejeitado' => 'Rejeitado',
                    ]),
                SelectFilter::make('segment')
                    ->label('Landing Page')
                    ->options([
                        'CRM' => 'CRM',
                        'WMS' => 'WMS',
                        'FROTA' => 'Frota',
                        'OS' => 'Ordem de Serviço',
                    ]),
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
