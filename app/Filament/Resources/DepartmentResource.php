<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DepartmentResource\Pages;
use App\Models\Department;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Filament\Facades\Filament;

class DepartmentResource extends Resource
{ 
    protected static ?string $model = Department::class;
    
    // CORREÇÃO 1: Ativa o menu na barra lateral para renderizar o submenu
    protected static bool $shouldRegisterNavigation = true;
    
    // CORREÇÃO 2: Escopa o recurso ao Tenant logado automaticamente no Filament v3
    protected static bool $isScopedToTenant = true;

    protected static ?string $navigationIcon = 'heroicon-o-building-office';
    protected static ?string $navigationGroup = 'GESTÃO DE PESSOAS';
    protected static ?string $navigationLabel = 'Departamentos';
    protected static ?string $pluralModelLabel = 'Departamentos';
    protected static ?string $modelLabel = 'Departamento';
    
    // Define a ordenação para aparecer em primeiro no grupo de Gestão de Pessoas
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Configuração de Departamento')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nome do Departamento')
                            ->placeholder('Ex: Manutenção, Operações, Comercial')
                            ->required()
                            ->maxLength(255)
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set) {
                                if (empty($state)) return;
                                
                                $prefix = substr(strtoupper(preg_replace('/[^A-Za-z]/', '', $state)), 0, 5);
                                
                                // Resgata o ID do tenant ativo de forma segura
                                $tenantId = Filament::getTenant()?->id ?? Auth::user()->tenant_id;
                                
                                // Conta os registros existentes do tenant para o sequencial
                                $count = Department::where('tenant_id', $tenantId)->count() + 1;
                                $code = $prefix . str_pad($count, 3, '0', STR_PAD_LEFT);
                                $set('code', $code);
                            }),
                        
                        Forms\Components\TextInput::make('code')
                            ->label('Código Sequencial')
                            ->required()
                            ->disabled()
                            ->dehydrated() 
                            ->maxLength(8), // Prefixo(5) + Número(3)
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Código')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('name')
                    ->label('Departamento')
                    ->searchable()
                    ->sortable(),

                // EXIBE AS FUNÇÕES: Puxa o nome de todas as roles vinculadas a este departamento e monta como Badges amarelados
                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Funções Cadastradas')
                    ->badge()
                    ->color('warning')
                    ->searchable(),

                // CONTA OS FUNCIONÁRIOS: Faz o cálculo em tempo real de usuários linkados ao ID deste departamento
                Tables\Columns\TextColumn::make('users_count')
                    ->label('Total de Funcionários')
                    ->counts('users')
                    ->badge()
                    ->color('success')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    /**
     * Injeta e calcula o Tenant e o Código Dinâmico antes de persistir no banco de dados
     */
    public static function mutateFormDataBeforeCreate(array $data): array
    {
        $tenantId = Filament::getTenant()?->id ?? Auth::user()->tenant_id;
        $data['tenant_id'] = $tenantId;
        
        // Se o código não estiver no $data (por estar disabled), calculamos aqui novamente por segurança
        if (empty($data['code'])) {
            $prefix = substr(strtoupper(preg_replace('/[^A-Za-z]/', '', $data['name'])), 0, 5);
            $count = Department::where('tenant_id', $tenantId)->count() + 1;
            $data['code'] = $prefix . str_pad($count, 3, '0', STR_PAD_LEFT);
        }
        
        return $data;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDepartments::route('/'),
            'create' => Pages\CreateDepartment::route('/create'),
            'edit' => Pages\EditDepartment::route('/{record}/edit'),
        ];
    }
}