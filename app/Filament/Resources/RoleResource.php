<?php

namespace App\Filament\Resources;

use App\Filament\Attributes\BelongsToFeature;
use App\Filament\Resources\RoleResource\Pages;
use App\Support\SaaSRegistry;
use App\Support\Tenancy;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

#[BelongsToFeature('roles')]
class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'Equipe';

    protected static ?string $navigationLabel = 'Perfis de Acesso';

    protected static ?int $navigationSort = 3;

    protected static bool $isScopedToTenant = false;

    /**
     * Antes: super admin via os perfis de TODOS os tenants misturados (sem
     * coluna de tenant na tabela pra diferenciar) -- como a maioria dos
     * tenants usa os mesmos nomes de papel (Comercial, tecnico, admin...),
     * a lista mostrava o mesmo nome repetido ate 8x. Agora usa
     * Tenancy::current() (mesma fonte que o resto do painel), respeitando
     * o "tenant atuante" do super admin: sem tenant atuante selecionado,
     * nao mostra nada em vez de misturar tudo.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->withoutGlobalScopes();

        $tenantId = Tenancy::current()?->id;

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        } else {
            $query->whereRaw('1 = 0');
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        $actions = [
            'ler' => 'Ler',
            'criar' => 'Criar',
            'editar' => 'Editar',
            'excluir' => 'Excluir',
        ];

        $sections = [];

        // Interseccao com o plano do tenant: o admin de um tenant so pode
        // configurar por-role o que o Plano (Central) ja libera pra ele --
        // nunca a lista inteira do SaaSRegistry. Sem contexto de tenant
        // (super admin/console), nao filtra.
        $tenant = Tenancy::current();

        // Fonte unica de verdade: cada Model com a trait HasSaaSMetadata vira
        // uma secao. Layout em coluna (empilhado), nao em abas horizontais --
        // com ~30 modulos, a barra de abas ficava grande demais e obrigava a
        // clicar em cada uma pra editar; em coluna da pra rolar e ver/editar
        // varias de uma vez.
        foreach (SaaSRegistry::modules() as $module) {
            if ($tenant && $module['feature'] && ! $tenant->hasFeature($module['feature'])) {
                continue;
            }

            $slug = $module['slug'];
            $label = $module['label'] ?? $slug;

            $components = [];
            $toggleNames = [];

            foreach ($actions as $action => $actionLabel) {
                $pName = "{$action}_{$slug}";

                // Garante que a permissao exista no banco.
                Permission::firstOrCreate(['name' => $pName, 'guard_name' => 'web']);

                $field = "perm_{$pName}";
                $toggleNames[] = $field;

                $components[] = Forms\Components\Toggle::make($field)
                    ->label($actionLabel)
                    ->onColor('success')
                    ->offColor('danger')
                    ->inline(true)
                    ->dehydrated(false);
            }

            $sections[] = Forms\Components\Section::make($label)
                ->collapsible()
                ->collapsed()
                ->schema([
                    Forms\Components\Actions::make([
                        Forms\Components\Actions\Action::make("marcar_todos_{$slug}")
                            ->label('Marcar todos')
                            ->icon('heroicon-m-check-circle')
                            ->color('success')
                            ->link()
                            ->action(function (Forms\Set $set) use ($toggleNames) {
                                foreach ($toggleNames as $name) {
                                    $set($name, true);
                                }
                            }),

                        Forms\Components\Actions\Action::make("desmarcar_todos_{$slug}")
                            ->label('Desmarcar todos')
                            ->icon('heroicon-m-x-circle')
                            ->color('danger')
                            ->link()
                            ->action(function (Forms\Set $set) use ($toggleNames) {
                                foreach ($toggleNames as $name) {
                                    $set($name, false);
                                }
                            }),
                    ]),

                    Forms\Components\Grid::make(4)->schema($components),
                ]);
        }

        return $form->schema([
            Forms\Components\Section::make('Configuração Geral')->schema([
                Forms\Components\Grid::make(1)->schema([
                    Forms\Components\TextInput::make('name')->label('Nome do Perfil')->required(),
                ]),
            ]),
            Forms\Components\Group::make($sections)->columnSpanFull(),
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
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Função')->searchable(),
                Tables\Columns\TextColumn::make('created_at')->label('Criado em')->dateTime('d/m/Y'),
            ])
            ->actions([
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
