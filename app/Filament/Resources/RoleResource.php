<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoleResource\Pages;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;
    protected static ?string $navigationIcon = 'heroicon-o-shield-check';
    protected static ?string $navigationGroup = 'GESTÃO DE PESSOAS';
    protected static ?string $navigationLabel = 'Perfis de Acesso';
    protected static ?int $navigationSort = 3;
    protected static bool $isScopedToTenant = false;

    public static function form(Form $form): Form
    {
        $actions = ['ler', 'criar', 'editar', 'excluir'];
        $departments = [
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

        $tabs = [];
        foreach ($departments as $label => $slug) {
            $components = [];
            foreach ($actions as $action) {
                $pName = "{$action}_{$slug}";
                Permission::firstOrCreate(['name' => $pName, 'guard_name' => 'web']);

                $components[] = Forms\Components\Toggle::make("perm_{$pName}")
                    ->label(ucfirst($action))
                    ->onColor('success')
                    ->offColor('danger')
                    ->inline(true)
                    ->dehydrated(false); 
            }
            $tabs[] = Forms\Components\Tabs\Tab::make($label)->schema($components)->columns(4);
        }

        return $form->schema([
            Forms\Components\Section::make('Configuração Geral')->schema([
                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\TextInput::make('name')->required(),
                    Forms\Components\Select::make('department_id')
                        ->relationship('department', 'name', fn ($query) => $query->where('tenant_id', Filament::getTenant()?->id))
                        ->required(),
                ]),
                Forms\Components\Tabs::make('Permissões')->tabs($tabs)->columnSpanFull()
            ])
        ]);
    }

    public static function mutateFormDataBeforeFill(array $data): array
    {
        $role = Role::find($data['id']);
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
            ->modifyQueryUsing(fn ($query) => Filament::getTenant() ? $query->where('tenant_id', Filament::getTenant()->id) : $query)
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Função')->searchable(),
                Tables\Columns\TextColumn::make('created_at')->label('Criado em')->dateTime('d/m/Y'),
            ])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()]);
    }

    public static function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = Filament::getTenant()?->id;
        return $data;
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