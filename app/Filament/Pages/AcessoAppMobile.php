<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Support\Colaborador\Modulos;
use App\Support\Tenancy;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

/**
 * O administrador da empresa escolhe, usuário a usuário, quais módulos (e funções dentro deles) do App do Colaborador
 * cada um enxerga. Só aparece o que o Contrato da empresa libera. Quem nunca foi configurado segue o padrão de sempre
 * (perfil + permissões): users.app_access NULL. Ver App\Support\Colaborador\Modulos::acesso().
 */
class AcessoAppMobile extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-device-phone-mobile';

    protected static ?string $navigationGroup = 'Equipe';

    protected static ?string $navigationLabel = 'Acesso ao App Mobile';

    protected static ?int $navigationSort = 5;

    protected static ?string $title = 'Acesso ao App Mobile';

    protected static string $view = 'filament.pages.acesso-app-mobile';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) $user && ($user->isSuperAdmin() || $user->isAdmin()) && (bool) Tenancy::current()?->hasFeature(Modulos::FEATURE);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(User::query()->where('tenant_id', Tenancy::current()?->id)->orderBy('name'))
            ->columns([
                TextColumn::make('name')->label('Usuário')->searchable()->sortable(),
                TextColumn::make('email')->label('E-mail')->searchable()->toggleable(),
                TextColumn::make('app_access')
                    ->label('Acesso ao app')
                    ->badge()
                    ->state(fn (User $u) => $this->situacao($u))
                    ->color(fn (User $u) => match (true) {
                        $u->app_access === null => 'gray',
                        $u->app_access === [] => 'danger',
                        default => 'success',
                    }),
            ])
            ->actions([
                Action::make('configurar')
                    ->label('Configurar')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->modalHeading(fn (User $record) => 'App Mobile: '.$record->name)
                    ->modalSubmitActionLabel('Salvar')
                    ->fillForm(fn (User $record) => $this->preencher($record))
                    ->form($this->campos())
                    ->action(function (User $record, array $data) {
                        $record->forceFill(['app_access' => $this->montar($data)])->save();

                        Notification::make()->title('Acesso ao app salvo')->success()->send();
                    }),
            ])
            ->bulkActions([
                BulkAction::make('padrao')
                    ->label('Voltar ao padrão do perfil')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->requiresConfirmation()
                    ->action(function (Collection $records) {
                        $records->each(fn (User $u) => $u->forceFill(['app_access' => null])->save());

                        Notification::make()->title('Usuários voltaram ao padrão')->success()->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ]);
    }

    private function situacao(User $u): string
    {
        if ($u->app_access === null) {
            return 'Padrão do perfil';
        }

        $modulos = collect($u->app_access)->reject(fn ($k) => str_contains($k, '.'))->count();

        return $modulos === 0 ? 'Sem acesso' : $modulos.($modulos === 1 ? ' módulo liberado' : ' módulos liberados');
    }

    /** @return array<int, mixed> */
    private function campos(): array
    {
        $campos = [
            Toggle::make('personalizar')
                ->label('Escolher módulo a módulo')
                ->helperText('Desligado: o usuário vê tudo que o perfil e as permissões dele já permitem. Ligado: só o que você marcar abaixo.')
                ->live(),
        ];

        foreach (Modulos::catalogo(Tenancy::current()) as $area => $modulos) {
            $blocos = [];

            foreach ($modulos as $m) {
                $blocos[] = Toggle::make('mod_'.$m['chave'])->label($m['titulo'])->helperText($m['descricao'])->live();

                if ($m['recursos']) {
                    $blocos[] = CheckboxList::make('rec_'.$m['chave'])
                        ->label('Funções de '.$m['titulo'])
                        ->options($m['recursos'])
                        ->columns(2)
                        ->bulkToggleable()
                        ->required()
                        ->visible(fn (Get $get) => (bool) $get('mod_'.$m['chave']));
                }
            }

            $campos[] = Section::make($area)->schema($blocos)->visible(fn (Get $get) => (bool) $get('personalizar'))->compact();
        }

        if (count($campos) === 1) {
            $campos[] = Placeholder::make('vazio')->label('')->content('O Contrato da empresa não libera nenhum módulo do app.');
        }

        return $campos;
    }

    /** @return array<string, mixed> */
    private function preencher(User $u): array
    {
        $lista = $u->app_access;
        $dados = ['personalizar' => is_array($lista)];

        foreach (Modulos::catalogo(Tenancy::current()) as $modulos) {
            foreach ($modulos as $m) {
                $ligado = is_array($lista) ? in_array($m['chave'], $lista, true) : true;
                $dados['mod_'.$m['chave']] = $ligado;
                $dados['rec_'.$m['chave']] = collect(array_keys($m['recursos']))
                    ->filter(fn ($r) => ! is_array($lista) || in_array($m['chave'].'.'.$r, $lista, true))->values()->all();
            }
        }

        return $dados;
    }

    /** @return array<int, string>|null */
    private function montar(array $dados): ?array
    {
        if (! ($dados['personalizar'] ?? false)) {
            return null;
        }

        $lista = [];

        foreach (Modulos::catalogo(Tenancy::current()) as $modulos) {
            foreach ($modulos as $m) {
                if (! ($dados['mod_'.$m['chave']] ?? false)) {
                    continue;
                }

                $lista[] = $m['chave'];

                foreach ((array) ($dados['rec_'.$m['chave']] ?? []) as $r) {
                    isset($m['recursos'][$r]) && $lista[] = $m['chave'].'.'.$r;
                }
            }
        }

        return $lista;
    }
}
