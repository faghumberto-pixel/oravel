<?php

namespace App\Relacionamento\Filament\RelationManagers;

use App\Relacionamento\Models\RelInteracao;
use App\Relacionamento\Models\RelInteresse;
use App\Relacionamento\Services\CalculaScore;
use App\Relacionamento\Services\ProximaAcao;
use App\Relacionamento\Services\SugereInteresses;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Aba "Relacionamento" da ficha da empresa (Prospect e SalesLead): score, proxima acao, interesses e a
 * linha do tempo cronologica de tudo que a empresa fez no ecossistema Oravel. A timeline e' somente
 * leitura: o registro nasce do endpoint/backfill, nunca digitado aqui.
 */
class RelacionamentoRelationManager extends RelationManager
{
    protected static string $relationship = 'relInteracoes';

    protected static ?string $title = 'Relacionamento';

    protected static ?string $icon = 'heroicon-o-clock';

    public function isReadOnly(): bool
    {
        return true;
    }

    /** Interacoes da pessoa/empresa dona da ficha. Quem herda (ficha do Contato) troca a relacao. */
    protected function interacoes(): HasMany
    {
        return $this->getOwnerRecord()->relInteracoes();
    }

    /** Interesses sao marcados por empresa (Prospect/SalesLead); a ficha do Contato nao os tem. */
    protected function mostraInteresses(): bool
    {
        return true;
    }

    /** Resumo em texto puro (sem HTML proprio): score, faixa, proxima acao, interesses e ultimo acesso. */
    public function resumo(): string
    {
        $dono = $this->getOwnerRecord();
        $q = $this->interacoes();

        $score = app(CalculaScore::class)->calcular($q);
        $acao = app(ProximaAcao::class)->para($score['score']);
        $interesses = $this->mostraInteresses() ? RelInteresse::marcados($dono) : [];
        $ultima = (clone $q)->max('ocorrido_em');

        return implode(' · ', array_filter([
            "Relacionamento: {$score['score']}/100 ({$score['faixa']})",
            'Próxima ação: '.($acao?->titulo ?? '—'),
            $this->mostraInteresses() ? 'Interesses: '.($interesses ? implode(', ', $interesses) : '—') : null,
            $ultima ? 'Último acesso: '.Carbon::parse($ultima)->format('d/m/Y H:i') : 'Sem interações ainda',
        ]));
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('titulo')
            ->heading('Linha do tempo')
            ->description(fn (): string => $this->resumo())
            ->headerActions(! $this->mostraInteresses() ? [] : [
                Tables\Actions\Action::make('interesses')->label('Interesses')->icon('heroicon-o-tag')
                    ->modalHeading('Interesses da empresa')
                    ->form(fn (): array => [
                        Forms\Components\CheckboxList::make('interesses')->label('Temas de interesse')
                            ->options(array_combine(RelInteresse::TEMAS, RelInteresse::TEMAS))->columns(3)
                            ->helperText($this->textoSugestao()),
                    ])
                    ->fillForm(fn (): array => ['interesses' => RelInteresse::marcados($this->getOwnerRecord())])
                    ->action(function (array $data): void {
                        RelInteresse::definir($this->getOwnerRecord(), (array) ($data['interesses'] ?? []));
                        Notification::make()->title('Interesses atualizados')->success()->send();
                    }),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('ocorrido_em')->label('Quando')->dateTime('d/m/Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('tipo')->label('O que fez')->badge()
                    ->formatStateUsing(fn (string $state): string => RelInteracao::rotulo($state))
                    ->color(fn (string $state): string => RelInteracao::cor($state))
                    ->icon(fn (string $state): string => RelInteracao::icone($state)),
                Tables\Columns\TextColumn::make('titulo')->label('Conteúdo')->weight('bold')->wrap()->placeholder('—'),
                Tables\Columns\TextColumn::make('resultado')->label('Resultado')->wrap()->limit(120)->placeholder('—'),
                Tables\Columns\TextColumn::make('duracao_segundos')->label('Tempo')
                    ->formatStateUsing(fn ($state): string => $state ? gmdate($state >= 3600 ? 'H:i:s' : 'i:s', $state) : '—'),
                Tables\Columns\TextColumn::make('origem')->label('Origem')->badge()->color('gray')->placeholder('—'),
            ])
            ->defaultSort('ocorrido_em', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('tipo')->label('Tipo')
                    ->options(collect(RelInteracao::TIPOS)->map(fn ($t) => $t[0])->all()),
            ])
            ->emptyStateHeading('Nenhuma interação registrada')
            ->emptyStateDescription('As ações da empresa no oravel.org e os contatos comerciais aparecem aqui em ordem cronológica.');
    }

    private function textoSugestao(): string
    {
        $sugeridos = app(SugereInteresses::class)->para($this->interacoes());

        return $sugeridos === []
            ? 'Sem sugestões: nenhum conteúdo com tema foi consumido ainda.'
            : 'Sugestão pelos conteúdos acessados: '.collect($sugeridos)->map(fn ($n, $t) => "{$t} ({$n})")->implode(', ').'.';
    }
}
