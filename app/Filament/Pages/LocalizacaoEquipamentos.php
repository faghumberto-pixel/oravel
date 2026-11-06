<?php

namespace App\Filament\Pages;

use App\Filament\Resources\AssetResource;
use App\Services\LocalizacaoEquipamentosService;
use Filament\Pages\Page;

/**
 * Mapa de localizacao dos equipamentos (Inteligencia de Ativos): obra do contrato ativo e rastreador (Traccar; na
 * demo, simulado). Opcional por contrato (chave menu_localizacao_equipamentos). Diferente do "Mapa de Equipamentos"
 * antigo (so' marcadores do cadastro).
 */
class LocalizacaoEquipamentos extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $navigationGroup = 'Inteligência de Ativos';

    protected static ?string $navigationLabel = 'Localização dos Equipamentos';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Localização dos equipamentos';

    protected static ?string $slug = 'localizacao-equipamentos';

    protected static string $view = 'filament.pages.localizacao-equipamentos';

    /** @var array<string, mixed> */
    public array $dados = [];

    public static function canAccess(): bool
    {
        return AssetResource::canViewAny() && LocalizacaoEquipamentosService::habilitado();
    }

    public function mount(): void
    {
        $this->dados = app(LocalizacaoEquipamentosService::class)->pontos();
    }

    /** Chamado a cada 30 s: recalcula as posicoes e avisa o mapa (sem recarregar a tela). */
    public function atualizar(): void
    {
        $this->dados = app(LocalizacaoEquipamentosService::class)->pontos();
        $this->dispatch('pontos-atualizados', dados: $this->dados);
    }
}
