<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PmocResource\Pages;
use App\Filament\Resources\PmocResource\RelationManagers;
use App\Models\Pmoc;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PmocResource extends Resource
{
    protected static ?string $model = Pmoc::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'PMOC';
    protected static ?string $modelLabel = 'PMOC';
    protected static ?string $pluralModelLabel = 'PMOCs';
    protected static ?string $navigationGroup = 'FSM — Serviços Técnicos';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Identificação')
                    ->schema([
                        Forms\Components\TextInput::make('titulo')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Textarea::make('descricao')
                            ->maxLength(500),
                        Forms\Components\TextInput::make('local_ambiente')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('tipo_sistema')
                            ->options([
                                'split' => 'Split',
                                'central' => 'Central',
                                'janela' => 'Janela',
                                'piso-teto' => 'Piso-Teto',
                                'cassete' => 'Cassete',
                                'outro' => 'Outro',
                            ])
                            ->required(),
                        Forms\Components\Select::make('asset_id')
                            ->relationship('asset', 'name')
                            ->searchable(),
                    ])->columns(2),

                Forms\Components\Section::make('Responsável')
                    ->schema([
                        Forms\Components\TextInput::make('responsavel_nome')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('responsavel_telefone')
                            ->tel()
                            ->maxLength(20),
                        Forms\Components\TextInput::make('responsavel_email')
                            ->email()
                            ->maxLength(255),
                    ])->columns(2),

                Forms\Components\Section::make('Vigência')
                    ->schema([
                        Forms\Components\DatePicker::make('data_inicio_vigencia')
                            ->required(),
                        Forms\Components\DatePicker::make('data_fim_vigencia'),
                        Forms\Components\TextInput::make('frequencia_dias')
                            ->numeric()
                            ->required()
                            ->helperText('Frequência de manutenção em dias'),
                    ])->columns(3),

                Forms\Components\Section::make('Procedimentos')
                    ->schema([
                        Forms\Components\Textarea::make('procedimentos_limpeza')
                            ->required()
                            ->maxLength(1000)
                            ->rows(3),
                        Forms\Components\Textarea::make('procedimentos_filtros')
                            ->required()
                            ->maxLength(1000)
                            ->rows(3),
                        Forms\Components\Textarea::make('procedimentos_inspecao')
                            ->required()
                            ->maxLength(1000)
                            ->rows(3),
                        Forms\Components\Textarea::make('procedimentos_medicao')
                            ->required()
                            ->maxLength(1000)
                            ->rows(3),
                    ]),

                Forms\Components\Section::make('Parâmetros de Qualidade do Ar')
                    ->schema([
                        Forms\Components\TextInput::make('temperatura_ideal_min')
                            ->numeric()
                            ->step(0.01)
                            ->suffix('°C'),
                        Forms\Components\TextInput::make('temperatura_ideal_max')
                            ->numeric()
                            ->step(0.01)
                            ->suffix('°C'),
                        Forms\Components\TextInput::make('umidade_ideal_min')
                            ->numeric()
                            ->step(0.01)
                            ->suffix('%'),
                        Forms\Components\TextInput::make('umidade_ideal_max')
                            ->numeric()
                            ->step(0.01)
                            ->suffix('%'),
                        Forms\Components\Textarea::make('parametros_qualidade_ar')
                            ->maxLength(1000)
                            ->rows(3),
                    ])->columns(2),

                Forms\Components\Section::make('Observações')
                    ->schema([
                        Forms\Components\Textarea::make('observacoes')
                            ->maxLength(1000)
                            ->rows(4),
                        Forms\Components\Select::make('status')
                            ->options([
                                'ativo' => 'Ativo',
                                'inativo' => 'Inativo',
                                'em_revisao' => 'Em Revisão',
                            ])
                            ->default('ativo')
                            ->required(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('titulo')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('local_ambiente')
                    ->searchable(),
                Tables\Columns\TextColumn::make('tipo_sistema')
                    ->searchable(),
                Tables\Columns\TextColumn::make('responsavel_nome')
                    ->searchable(),
                Tables\Columns\TextColumn::make('data_inicio_vigencia')
                    ->date()
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'ativo',
                        'danger' => 'inativo',
                        'warning' => 'em_revisao',
                    ]),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'ativo' => 'Ativo',
                        'inativo' => 'Inativo',
                        'em_revisao' => 'Em Revisão',
                    ]),
                Tables\Filters\SelectFilter::make('tipo_sistema'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\DeleteAction::make(),
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
            'index' => Pages\ListPmocs::route('/'),
            'create' => Pages\CreatePmoc::route('/create'),
            'edit' => Pages\EditPmoc::route('/{record}/edit'),
        ];
    }
}
