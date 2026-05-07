<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClientResource\Pages;
use App\Models\Client;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ClientResource extends Resource
{
    protected static ?string $model = Client::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = 'Clientes';
    protected static ?string $navigationGroup = 'GESTAO DE MANUTENCAO';

    /**
     * Garante que cada empresa veja apenas os seus próprios clientes
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('tenant_id', Auth::user()->tenant_id);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Identificação')
                    ->description('Dados principais do cliente ou empresa')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nome / Razão Social')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(2),

                        // --- INCLUSÃO MÍNIMA: Campo Tipo de Atividade ---
                        Forms\Components\Select::make('activity_type')
                            ->label('Tipo de Atividade')
                            ->options([
                                'Industria' => 'Indústria',
                                'Construcao Civil' => 'Construção Civil',
                                'Logistica' => 'Logística',
                                'Agronegocio' => 'Agronegócio',
                                'Outros' => 'Outros',
                            ])
                            ->searchable()
                            ->preload(),
                        
                        Forms\Components\TextInput::make('cpf_cnpj')
                            ->label('CPF / CNPJ')
                            ->maxLength(20),
                            
                        Forms\Components\TextInput::make('contact_name')
                            ->label('Pessoa de Contato / Representante')
                            ->maxLength(255),
                    ])->columns(2),

                Forms\Components\Section::make('Localização')
                    ->description('Endereço completo para atendimentos externos')
                    ->schema([
                        Forms\Components\Grid::make(3)->schema([
                            Forms\Components\TextInput::make('cep')
                                ->label('CEP')
                                ->maxLength(9),
                            
                            Forms\Components\TextInput::make('city')
                                ->label('Cidade')
                                ->maxLength(255),

                            Forms\Components\Select::make('uf')
                                ->label('Estado (UF)')
                                ->options([
                                    'AC'=>'Acre','AL'=>'Alagoas','AP'=>'Amapá','AM'=>'Amazonas','BA'=>'Bahia','CE'=>'Ceará','DF'=>'Distrito Federal','ES'=>'Espírito Santo','GO'=>'Goiás','MA'=>'Maranhão','MT'=>'Mato Grosso','MS'=>'Mato Grosso do Sul','MG'=>'Minas Gerais','PA'=>'Pará','PB'=>'Paraíba','PR'=>'Paraná','PE'=>'Pernambuco','PI'=>'Piauí','RJ'=>'Rio de Janeiro','RN'=>'Rio Grande do Norte','RS'=>'Rio Grande do Sul','RO'=>'Rondônia','RR'=>'Roraima','SC'=>'Santa Catarina','SP'=>'São Paulo','SE'=>'Sergipe','TO'=>'Tocantins',
                                ])->searchable(),
                        ]),

                        Forms\Components\TextInput::make('address')
                            ->label('Endereço (Rua, Número, Bairro)')
                            ->maxLength(500)
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Contato')
                    ->description('Canais de comunicação direta')
                    ->schema([
                        Forms\Components\TextInput::make('phone')
                            ->label('Telefone Fixo')
                            ->tel(),
                            
                        Forms\Components\TextInput::make('whatsapp')
                            ->label('WhatsApp / Celular')
                            ->tel(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome / Cliente')
                    ->searchable()
                    ->sortable(),

                // --- INCLUSÃO MÍNIMA: Coluna Tipo de Atividade no Grid ---
                Tables\Columns\TextColumn::make('activity_type')
                    ->label('Atividade')
                    ->badge()
                    ->color('gray')
                    ->searchable(),

                Tables\Columns\TextColumn::make('cpf_cnpj')
                    ->label('Documento')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('city')
                    ->label('Cidade/UF')
                    ->formatStateUsing(fn ($record) => "{$record->city} - {$record->uf}")
                    ->searchable(),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Contato')
                    ->searchable(),

                Tables\Columns\TextColumn::make('whatsapp')
                    ->label('WhatsApp')
                    ->icon('heroicon-m-chat-bubble-left-right')
                    ->color('success')
                    ->searchable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Cadastro')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // --- SUBSTITUIÇÃO: TrashedFilter removido e Status incluído ---
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'ativo' => 'Ativo',
                        'inativo' => 'Inativo',
                    ]),

                Tables\Filters\SelectFilter::make('uf')
                    ->label('Filtrar por Estado')
                    ->options([
                        'SP' => 'São Paulo',
                        'RJ' => 'Rio de Janeiro',
                        'MG' => 'Minas Gerais',
                        // Adicionar outros conforme necessário
                    ]),
                // --- INCLUSÃO MÍNIMA: Filtro por Tipo de Atividade ---
                Tables\Filters\SelectFilter::make('activity_type')
                    ->label('Tipo de Atividade')
                    ->options([
                        'Industria' => 'Indústria',
                        'Construcao Civil' => 'Construção Civil',
                        'Logistica' => 'Logística',
                        'Agronegocio' => 'Agronegócio',
                        'Outros' => 'Outros',
                    ]),
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

    public static function getRelations(): array
    {
        return [
            // No futuro, podemos adicionar aqui um RelationManager para listar as OS deste cliente
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClients::route('/'),
            'create' => Pages\CreateClient::route('/create'),
            'edit' => Pages\EditClient::route('/{record}/edit'),
        ];
    }
}