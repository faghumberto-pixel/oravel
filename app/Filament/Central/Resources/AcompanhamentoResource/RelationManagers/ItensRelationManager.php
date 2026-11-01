<?php

namespace App\Filament\Central\Resources\AcompanhamentoResource\RelationManagers;

use App\Models\AcompanhamentoItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/** Lista do acompanhamento: paginas do site, fontes de uma pesquisa, concorrentes... */
class ItensRelationManager extends RelationManager
{
    protected static string $relationship = 'itens';

    protected static ?string $title = 'Lista (páginas, fontes, itens da pesquisa)';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('nome')->label('Nome')->required()->maxLength(190),
            Forms\Components\TextInput::make('referencia')->label('Endereço ou referência')->maxLength(300),
            Forms\Components\TextInput::make('grupo')->label('Grupo')->maxLength(80),
            Forms\Components\Select::make('status')->label('Status')->options(AcompanhamentoItem::STATUS)->default('a_fazer')->required()->native(false),
            Forms\Components\Textarea::make('observacao')->label('Observação')->rows(3)->columnSpanFull(),
        ])->columns(2);
    }

    /**
     * Cola uma lista, uma linha por item: "Nome", "Nome | referência" ou "Grupo | Nome | referência"
     * (separador | ; ou tab). Linhas repetidas (mesmo grupo e nome) sao ignoradas.
     *
     * @return array{criados:int, ignorados:int}
     */
    protected function importarLista(string $texto, ?string $grupoPadrao, string $status): array
    {
        $criados = $ignorados = 0;
        foreach (preg_split('/\R/u', $texto) as $linha) {
            $linha = trim($linha);
            if ($linha === '') {
                continue;
            }
            $p = array_map('trim', preg_split('/\s*[|;\t]\s*/u', $linha));
            [$grupo, $nome, $ref] = match (count($p)) {
                1 => [$grupoPadrao, $p[0], null],
                2 => [$grupoPadrao, $p[0], $p[1]],
                default => [$p[0] ?: $grupoPadrao, $p[1], $p[2] ?? null],
            };
            if ($nome === '') {
                continue;
            }
            $existe = $this->getOwnerRecord()->itens()->where('nome', $nome)->where(fn ($q) => $grupo ? $q->where('grupo', $grupo) : $q->whereNull('grupo'))->exists();
            if ($existe) {
                $ignorados++;

                continue;
            }
            $this->getOwnerRecord()->itens()->create(['nome' => $nome, 'referencia' => $ref ?: null, 'grupo' => $grupo ?: null, 'status' => $status]);
            $criados++;
        }

        return ['criados' => $criados, 'ignorados' => $ignorados];
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nome')
            ->columns([
                Tables\Columns\TextColumn::make('grupo')->label('Grupo')->badge()->color('gray')->sortable()->placeholder('—'),
                Tables\Columns\TextColumn::make('nome')->label('Item')->weight('bold')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('referencia')->label('Endereço')->placeholder('—')->searchable()
                    ->url(fn (AcompanhamentoItem $r): ?string => $r->link(), true)->color('primary'),
                Tables\Columns\SelectColumn::make('status')->label('Status')->options(AcompanhamentoItem::STATUS)->selectablePlaceholder(false),
                Tables\Columns\TextInputColumn::make('observacao')->label('Observação'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(AcompanhamentoItem::STATUS),
                Tables\Filters\SelectFilter::make('grupo')->options(fn () => $this->getOwnerRecord()->itens()->whereNotNull('grupo')->distinct()->orderBy('grupo')->pluck('grupo', 'grupo')->all()),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('Novo item')->icon('heroicon-o-plus'),
                Tables\Actions\Action::make('colar')->label('Colar lista')->icon('heroicon-o-clipboard-document-list')->color('gray')->modalHeading('Colar uma lista de itens')
                    ->modalDescription('Uma linha por item: "Nome", "Nome | referência" ou "Grupo | Nome | referência".')->modalSubmitActionLabel('Adicionar à lista')
                    ->form([
                        Forms\Components\Textarea::make('texto')->label('Lista')->required()->rows(10)->placeholder("Guindastes | Página de guindastes | /software-manutencao-guindastes/\nMunks | Página de munks | /software-manutencao-munks/"),
                        Forms\Components\TextInput::make('grupo')->label('Grupo padrão (para linhas sem grupo)')->maxLength(80),
                        Forms\Components\Select::make('status')->label('Status inicial')->options(AcompanhamentoItem::STATUS)->default('a_fazer')->required()->native(false),
                    ])
                    ->action(function (array $data): void {
                        $r = $this->importarLista($data['texto'], $data['grupo'] ?? null, $data['status']);
                        Notification::make()->title("{$r['criados']} itens adicionados".($r['ignorados'] ? ", {$r['ignorados']} já existiam" : ''))->success()->send();
                    }),
            ])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
            ->defaultSort('grupo')
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(10);
    }
}
