<?php

namespace App\Filament\Pages;

use App\Models\CrmVisita;
use App\Models\User;
use App\Support\CrmRota;
use App\Support\Tenancy;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Roteiro do dia: visitas do vendedor numa data, ordem sugerida por vizinho mais próximo (sem API paga),
 * km estimado e link da rota no Google Maps. A ordem escolhida fica salva em `ordem_roteiro`.
 */
class CrmRoteiro extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-map';

    protected static ?string $navigationLabel = 'Roteiro do Dia';

    protected static ?string $title = 'Roteiro do Dia';

    protected static ?string $navigationGroup = 'Comercial';

    protected static ?string $navigationParentItem = 'Gestão CRM';

    protected static ?int $navigationSort = 11;

    protected static string $view = 'filament.pages.crm-roteiro';

    public string $dia = '';

    public ?string $vendedorId = null;

    /** Posição do aparelho (opcional): ponto de partida da otimização. */
    public ?float $origemLat = null;

    public ?float $origemLng = null;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('viewAny', CrmVisita::class);
    }

    public function mount(): void
    {
        $this->dia = now()->toDateString();
        $this->vendedorId = auth()->id();
    }

    public function podeEscolherVendedor(): bool
    {
        return (bool) auth()->user()?->canSeeAllCrmLeads();
    }

    public function getVendedoresProperty()
    {
        return User::where('tenant_id', Tenancy::current()?->id)->orderBy('name')->pluck('name', 'id');
    }

    private function vendedorEfetivo(): string
    {
        return $this->podeEscolherVendedor() && $this->vendedorId ? $this->vendedorId : (string) auth()->id();
    }

    /** Visitas do dia (abertas) na ordem salva; as sem ordem entram depois por horário. */
    public function getVisitasProperty()
    {
        return CrmVisita::query()
            ->where('user_id', $this->vendedorEfetivo())
            ->doDia($this->dia)
            ->whereIn('status', [CrmVisita::STATUS_AGENDADA, CrmVisita::STATUS_EM_ANDAMENTO])
            ->orderByRaw('ordem_roteiro asc nulls last')->orderBy('agendada_para')
            ->with(['conta', 'lead'])
            ->get();
    }

    /** @return list<array{lat: float, lng: float}> pontos com coordenadas, na ordem atual */
    public function getSequenciaProperty(): array
    {
        return $this->visitas->filter->temCoordenadas()
            ->map(fn (CrmVisita $v) => ['lat' => (float) $v->latitude, 'lng' => (float) $v->longitude])->values()->all();
    }

    public function getKmEstimadosProperty(): float
    {
        return CrmRota::kmEstimados($this->sequencia);
    }

    public function getLinkRotaProperty(): ?string
    {
        return CrmRota::linkGoogleMaps($this->sequencia);
    }

    public function definirOrigem(?float $lat, ?float $lng): void
    {
        $this->origemLat = $lat;
        $this->origemLng = $lng;
        $this->otimizar();
    }

    /** Ordena por vizinho mais próximo a partir da posição do aparelho (ou da 1ª visita) e salva. */
    public function otimizar(): void
    {
        $visitas = $this->visitas;
        $pontos = $visitas->mapWithKeys(fn (CrmVisita $v) => [$v->id => [
            'lat' => $v->temCoordenadas() ? (float) $v->latitude : null,
            'lng' => $v->temCoordenadas() ? (float) $v->longitude : null,
        ]])->all();

        $origem = $this->origemLat !== null && $this->origemLng !== null ? ['lat' => $this->origemLat, 'lng' => $this->origemLng] : null;

        $this->salvarOrdem(CrmRota::ordenar($pontos, $origem));

        Notification::make()->title('Roteiro otimizado.')->success()->send();
    }

    public function mover(string $id, string $direcao): void
    {
        $ids = $this->visitas->pluck('id')->all();
        $i = array_search($id, $ids, true);
        $j = $direcao === 'subir' ? $i - 1 : $i + 1;

        if ($i === false || ! isset($ids[$j])) {
            return;
        }

        [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
        $this->salvarOrdem($ids);
    }

    /** @param  list<string>  $ids */
    private function salvarOrdem(array $ids): void
    {
        foreach (array_values($ids) as $posicao => $id) {
            CrmVisita::query()->where('user_id', $this->vendedorEfetivo())->whereKey($id)->update(['ordem_roteiro' => $posicao + 1]);
        }

        unset($this->visitas); // limpa o cache da propriedade calculada

        $this->dispatch('roteiro-atualizado', pontos: $this->pontosDoMapa());
    }

    /** Dados do mapa (Leaflet): marcadores numerados. */
    public function pontosDoMapa(): array
    {
        return $this->visitas->values()->map(fn (CrmVisita $v, int $i) => [
            'n' => $i + 1, 'nome' => $v->alvoNome(), 'lat' => $v->temCoordenadas() ? (float) $v->latitude : null, 'lng' => $v->temCoordenadas() ? (float) $v->longitude : null,
        ])->filter(fn ($p) => $p['lat'] !== null)->values()->all();
    }
}
