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
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable()->weight('bold'),
                Tables\Columns\TextColumn::make('email')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('phone')->searchable(),
                Tables\Columns\TextColumn::make('segment')->label('Landing Page')->badge()->color('info'),
                Tables\Columns\TextColumn::make('product')->badge()->color(fn(string $state) => match($state) {
                    'CRM' => 'blue', 'WMS' => 'purple', 'FROTA' => 'orange', 'OS' => 'green', default => 'gray'
                }),
                Tables\Columns\TextColumn::make('status')->badge()->color(fn(string $state) => match($state) {
                    'novo' => 'primary', 'contatado' => 'warning', 'convertido' => 'success', 'rejeitado' => 'danger', default => 'gray'
                }),
                Tables\Columns\TextColumn::make('created_at')->label('Data')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('product')->options(['CRM' => 'CRM', 'WMS' => 'WMS', 'FROTA' => 'Frota', 'OS' => 'Ordem de Serviço']),
                SelectFilter::make('status')->options(['novo' => 'Novo', 'contatado' => 'Contatado', 'convertido' => 'Convertido', 'rejeitado' => 'Rejeitado']),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageLandingPageLeads::route('/'),
        ];
    }
}
