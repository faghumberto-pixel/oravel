<?php

namespace App\Filament\Pages;

use App\Services\Catalogo\Aptidao\AptidaoImpacto;
use App\Services\Catalogo\CatalogoTemplateService;
use App\Support\CatalogoAcesso;
use App\Support\Tenancy;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Validation\ValidationException;

/**
 * G1 fase 6 -- atualizacoes do template do catalogo, item a item. Nada e aplicado sozinho: o tenant aceita, ignora ou restaura.
 * Itens personalizados nunca sao alterados por aceite (so aparecem como "ha versao nova do padrao").
 */
class AtualizacoesCatalogo extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';

    protected static ?string $navigationGroup = 'Manutenção';

    protected static ?string $navigationParentItem = 'Cadastros';

    protected static ?string $navigationLabel = 'Atualizações do catálogo';

    protected static ?string $title = 'Atualizações do catálogo';

    protected static ?string $slug = 'catalogo-atualizacoes';

    protected static ?int $navigationSort = 7;

    protected static string $view = 'filament.pages.atualizacoes-catalogo';

    /** @var array<int, string> chaves marcadas para aceitar */
    public array $selecionadas = [];

    public static function canAccess(): bool
    {
        return CatalogoAcesso::podeLer();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return CatalogoAcesso::podeLer();
    }

    public function getDiferencasProperty(): array
    {
        $tenant = Tenancy::current();

        return $tenant ? array_values(app(CatalogoTemplateService::class)->diferencas($tenant)) : [];
    }

    public function podeGerenciar(): bool
    {
        return CatalogoAcesso::podeGerenciar();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('previa')->label('Prévia de impacto')->icon('heroicon-o-eye')->color('gray')
                ->visible(fn () => $this->podeGerenciar())
                ->tooltip('Mostra quantos ativos mudariam de status de aptidão se você aceitar as marcadas (nada é salvo).')
                ->action(function () {
                    if ($this->selecionadas === []) {
                        Notification::make()->warning()->title('Marque ao menos uma atualização.')->send();

                        return;
                    }

                    try {
                        $p = app(CatalogoTemplateService::class)->previa(Tenancy::current(), $this->selecionadas, 'aceitar');
                    } catch (ValidationException $e) {
                        Notification::make()->danger()->title('Seleção inválida')->body(collect($e->errors())->flatten()->first())->send();

                        return;
                    }

                    $linhas = collect($p['transicoes'])->map(function ($n, $k) {
                        [$de, $para] = explode('>', $k);

                        return "{$n} ativo(s): ".AptidaoImpacto::rotulo($de).' → '.AptidaoImpacto::rotulo($para);
                    })->implode("\n");

                    Notification::make()->title("{$p['mudaram']} de {$p['total']} ativo(s) mudariam de status")
                        ->body($p['mudaram'] ? $linhas : 'Nenhum ativo muda de status.')->color($p['mudaram'] ? 'warning' : 'success')->persistent()->send();
                }),
            Action::make('aceitar')->label('Aceitar selecionadas')->icon('heroicon-o-check')
                ->visible(fn () => $this->podeGerenciar())
                ->form([Forms\Components\Textarea::make('justificativa')->label('Justificativa')->rows(2)
                    ->helperText('Obrigatória se alguma atualização for sensível (IMP→ALR, ampliar intervalo, remover/desativar obrigatório). Valide com o responsável técnico.')])
                ->action(function (array $data) {
                    if ($this->selecionadas === []) {
                        Notification::make()->warning()->title('Marque ao menos uma atualização.')->send();

                        return;
                    }

                    try {
                        $r = app(CatalogoTemplateService::class)->aceitar(Tenancy::current(), $this->selecionadas, $data['justificativa'] ?? null, auth()->user());
                    } catch (ValidationException $e) {
                        Notification::make()->danger()->title('Não foi possível aceitar')->body(collect($e->errors())->flatten()->first())->persistent()->send();

                        return;
                    }

                    $this->selecionadas = [];
                    Notification::make()->success()->title("{$r['aplicadas']} atualização(ões) aplicada(s).")->send();
                }),
        ];
    }

    public function restaurarAction(): Action
    {
        return Action::make('restaurar')->label('Restaurar ao padrão')->color('warning')->requiresConfirmation()
            ->modalDescription('Substitui a sua versão deste item pela do padrão. Fica registrado em log com a justificativa.')
            ->visible(fn () => $this->podeGerenciar())
            ->form([Forms\Components\Textarea::make('justificativa')->label('Justificativa')->required()->minLength(5)->rows(2)])
            ->action(function (array $data, array $arguments) {
                try {
                    app(CatalogoTemplateService::class)->restaurar(Tenancy::current(), [$arguments['chave']], $data['justificativa'], auth()->user());
                } catch (ValidationException $e) {
                    Notification::make()->danger()->title('Não foi possível restaurar')->body(collect($e->errors())->flatten()->first())->persistent()->send();

                    return;
                }

                Notification::make()->success()->title('Item restaurado ao padrão.')->send();
            });
    }

    public function ignorarAction(): Action
    {
        return Action::make('ignorar')->label('Ignorar')->color('gray')->requiresConfirmation()
            ->modalDescription('Mantém a sua versão atual. O item passa a contar como personalizado e esta atualização deixa de ser oferecida.')
            ->visible(fn () => $this->podeGerenciar())
            ->action(function (array $arguments) {
                try {
                    app(CatalogoTemplateService::class)->ignorar(Tenancy::current(), [$arguments['chave']], auth()->user());
                } catch (ValidationException $e) {
                    Notification::make()->danger()->title('Não foi possível ignorar')->body(collect($e->errors())->flatten()->first())->send();

                    return;
                }

                $this->selecionadas = array_values(array_diff($this->selecionadas, [$arguments['chave']]));
                Notification::make()->success()->title('Atualização ignorada.')->send();
            });
    }
}
