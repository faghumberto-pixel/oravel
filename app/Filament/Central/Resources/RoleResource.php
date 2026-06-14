<?php

namespace App\Filament\Central\Resources;

use App\Filament\Central\Resources\RoleResource\Pages;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';
    protected static ?string $navigationLabel = 'Perfis de Acesso';
    protected static ?string $modelLabel = 'Perfil';
    protected static ?string $pluralModelLabel = 'Perfis de Acesso';

    protected static ?string $navigationGroup = 'Gestão SaaS';
    protected static ?int $navigationSort = 3;

    protected static bool $isScopedToTenant = false;

    public static array $modules = [
        'Ordens de Serviço' => 'ordem_servico',
        'Checklists'        => 'checklist',
        'Funcionários'      => 'funcionario',
        'Departamentos'     => 'departamento',
        'Clientes'          => 'cliente',
        'Materiais'         => 'material',
        'Categorias'        => 'categoria_material',
        'Fila de Logística' => 'fila_logistica',
        'Canais de Chat'    => 'chat',
        'Suprimentos'       => 'suprimentos',
        'Ativos'            => 'ativo',
    ];

    public static array $permActions = [
        'ler'     => 'Ler',
        'criar'   => 'Criar',
        'editar'  => 'Editar',
        'excluir' => 'Excluir',
    ];

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes();
    }

    public static function existingPermissionNames(): array
    {
        return Permission::query()
            ->where('guard_name', 'web')
            ->pluck('name')
            ->all();
    }

    public static function form(Form $form): Form
    {
        $existing = static::existingPermissionNames();

        $tabs = [];

        foreach (static::$modules as $label => $slug) {
            $components = [];
            foreach (static::$permActions as $action => $actionLabel) {
                $permName = "{$action}_{$slug}";

                if (! in_array($permName, $existing, true)) {
                    continue;
                }

                $components[] = Forms\Components\Toggle::make("perm_{$permName}")
                    ->label($actionLabel)
                    ->onColor('success')
                    ->offColor('danger')
                    ->inline(true)
                    ->dehydrated(false);
            }

            if (empty($components)) {
                continue;
            }

            $tabs[] = Forms\Components\Tabs\Tab::make($label)
                ->schema($components)
                ->columns(4);
        }

        return $form->schema([
            Forms\Components\Section::make('Dados do Perfil')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nome do Perfil')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\Tabs::make('Permissões')
                        ->tabs($tabs)
                        ->columnSpanFull(),
                ])->columns(1),
        ]);
    }

    public static function mutateFormDataBeforeFill(array $data): array
    {
        $role = Role::find($data['id'] ?? null);
        if ($role) {
            foreach ($role->permissions as $permission) {
                $data["perm_{$permission->name}"] = true;
            }
        }
        return $data;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nome')->searchable(),
                Tables\Columns\TextColumn::make('permissions_count')
                    ->label('Permissões')
                    ->counts('permissions')
                    ->badge(),
                Tables\Columns\TextColumn::make('created_at')->label('Criado em')->dateTime('d/m/Y'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }
}
