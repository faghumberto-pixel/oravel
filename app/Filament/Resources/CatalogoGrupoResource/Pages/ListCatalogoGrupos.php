<?php

namespace App\Filament\Resources\CatalogoGrupoResource\Pages;

use App\Filament\Pages\AtualizacoesCatalogo;
use App\Filament\Resources\CatalogoGrupoResource;
use App\Services\Catalogo\CatalogoInstalador;
use App\Services\Catalogo\CatalogoTemplateService;
use App\Support\CatalogoAcesso;
use App\Support\Tenancy;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListCatalogoGrupos extends ListRecords
{
    protected static string $resource = CatalogoGrupoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('instalar')->label('Instalar catálogo padrão')->icon('heroicon-o-arrow-down-tray')->color('gray')
                ->visible(fn () => CatalogoAcesso::podeGerenciar())
                ->requiresConfirmation()
                ->modalDescription('Copia os grupos padrão para a sua empresa. Nunca sobrescreve o que você já ajustou e não recria o que você arquivou. Os requisitos que dependem de norma devem ser validados com o responsável técnico.')
                ->action(function () {
                    $tenant = Tenancy::current();

                    if (! $tenant) {
                        Notification::make()->warning()->title('Escolha a empresa atuante antes de instalar.')->send();

                        return;
                    }

                    $r = app(CatalogoInstalador::class)->instalar($tenant);
                    $novos = $r['criados']['grupos'] ?? 0;
                    $body = $novos > 0 ? "{$novos} grupo(s) instalado(s)." : 'Nada novo para instalar.';

                    if ($r['conflitos']) {
                        $body .= ' Já existe categoria com o mesmo identificador (não adotada): '.implode(', ', $r['conflitos']).'.';
                    }

                    Notification::make()->success()->title('Catálogo padrão')->body($body)->send();
                }),
            Actions\Action::make('atualizacoes')->label(function () {
                $n = ($t = Tenancy::current()) ? app(CatalogoTemplateService::class)->pendentes($t) : 0;

                return $n > 0 ? "Atualizações do catálogo ({$n})" : 'Atualizações do catálogo';
            })->icon('heroicon-o-arrow-path')->color('gray')->url(fn () => AtualizacoesCatalogo::getUrl()),
            Actions\CreateAction::make()->label('Novo grupo'),
        ];
    }
}
