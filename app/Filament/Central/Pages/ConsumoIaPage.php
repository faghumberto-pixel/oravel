<?php

namespace App\Filament\Central\Pages;

use App\Services\IaConsumoPainel;
use App\Support\Ia\ConsumoIa;
use Filament\Pages\Page;

/**
 * Painel de consumo de tokens da IA, em tempo real (atualiza sozinho), por aplicativo, cliente e usuario.
 * So super admin (Central). Dados: ia_consumos, gravados pelo AnthropicApiClient a cada chamada.
 */
class ConsumoIaPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cpu-chip';

    protected static ?string $navigationLabel = 'Consumo de IA';

    protected static ?string $navigationGroup = 'Acessos e Uso';

    protected static ?string $title = 'Consumo de IA';

    protected static ?string $slug = 'consumo-ia';

    protected static ?int $navigationSort = 5;

    protected static string $view = 'filament.central.pages.consumo-ia';

    public string $periodo = 'hoje';

    public string $area = '';

    public string $cliente = '';

    public string $usuario = '';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isSuperAdmin();
    }

    private function uuidOuNulo(string $v): ?string
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $v) ? $v : null;
    }

    protected function getViewData(): array
    {
        // Os filtros vem do navegador: so valores validos entram na consulta.
        $periodo = array_key_exists($this->periodo, IaConsumoPainel::PERIODOS) ? $this->periodo : 'hoje';
        $area = array_key_exists($this->area, ConsumoIa::AREAS) ? $this->area : null;

        $painel = new IaConsumoPainel($periodo, $area, $this->uuidOuNulo($this->cliente), $this->uuidOuNulo($this->usuario));

        return [
            'periodoAtual' => $periodo,
            'resumo' => $painel->resumo(),
            'hoje' => (new IaConsumoPainel('hoje', $area, $this->uuidOuNulo($this->cliente), $this->uuidOuNulo($this->usuario)))->resumo(),
            'serie' => $painel->serie(),
            'porArea' => $painel->porArea(),
            'porCliente' => $painel->porCliente(),
            'porUsuario' => $painel->porUsuario(),
            'ultimas' => $painel->ultimas(),
            'clientes' => IaConsumoPainel::clientesComConsumo(),
            'usuarios' => IaConsumoPainel::usuariosComConsumo(),
            'desde' => IaConsumoPainel::primeiroRegistro(),
            'dolar' => IaConsumoPainel::dolar(),
        ];
    }
}
