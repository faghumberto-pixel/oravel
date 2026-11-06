<?php

namespace App\Services;

use App\Filament\Resources\AssetResource;
use App\Models\Asset;
use App\Models\Contract;
use App\Models\FrotaVinculoGps;
use App\Services\Demo\RastreadorSimulado;
use App\Support\Tenancy;
use App\Support\TraccarService;
use Illuminate\Support\Carbon;

/**
 * Onde esta cada equipamento (Inteligencia de Ativos). A posicao vem, nesta ordem, de: rastreador (Traccar; na
 * empresa de demonstracao e' simulado) -> endereco da obra do contrato ativo -> localizacao do cadastro do
 * equipamento -> unidade interna -> endereco do cliente. Sem hardware novo, a obra do contrato ja resolve a maioria.
 */
class LocalizacaoEquipamentosService
{
    /** Chave de contrato que libera a tela (config/menu_modules.php). */
    public const FEATURE = 'menu_localizacao_equipamentos';

    private const FONTES = [
        'rastreador' => 'Rastreador (GPS)',
        'contrato' => 'Endereço da obra do contrato ativo',
        'cadastro' => 'Localização do cadastro do equipamento',
        'unidade' => 'Unidade interna da empresa',
        'cliente' => 'Endereço do cliente',
    ];

    private const STATUS = [
        'disponivel' => 'Disponível', 'locado' => 'Locado', 'manutencao' => 'Em manutenção', 'operando' => 'Em operação',
        'aguardando_triagem' => 'Aguardando triagem', 'reservado' => 'Reservado', 'quarentena' => 'Quarentena',
    ];

    public static function habilitado(): bool
    {
        $tenant = Tenancy::current();

        return $tenant !== null && $tenant->hasFeature(self::FEATURE);
    }

    /**
     * @return array{pontos: array<int, array<string, mixed>>, sem_localizacao: int, simulado: bool, atualizado_em: string}
     */
    public function pontos(): array
    {
        $tenant = Tenancy::current();
        $simulado = (bool) $tenant?->is_demo;

        $ativos = Asset::query()
            ->with(['client:id,name,latitude,longitude,address', 'internalUnit:id,name,latitude,longitude,city'])
            ->get(['id', 'name', 'patrimonio', 'asset_category', 'status', 'latitude', 'longitude', 'client_id', 'internal_unit_id']);

        $contratos = Contract::query()
            ->where('status', 'Ativo')
            ->with(['client:id,name,latitude,longitude,address', 'internalUnit:id,name,latitude,longitude'])
            ->orderBy('start_date')
            ->get()
            ->keyBy('asset_id');

        $custos = app(CustoManutencaoService::class)->ranking(12)->keyBy('id');
        $reais = $simulado ? [] : $this->posicoesReais();

        $sem = 0;
        $pontos = [];
        foreach ($ativos as $a) {
            $contrato = $contratos->get($a->id);
            $base = $this->posicaoCadastral($a, $contrato);
            $rastreio = null;

            if ($simulado && RastreadorSimulado::tem($a->patrimonio) && $base) {
                $rastreio = app(RastreadorSimulado::class)->posicao($a->patrimonio, $base['lat'], $base['lng']);
            } elseif (isset($reais[$a->id])) {
                $rastreio = $reais[$a->id];
            }

            $posicao = $rastreio
                ? ['lat' => $rastreio['lat'], 'lng' => $rastreio['lng'], 'fonte' => 'rastreador', 'local' => $base['local'] ?? 'Posição do rastreador']
                : $base;

            if (! $posicao) {
                $sem++;

                continue;
            }

            $custo = $custos->get((string) $a->id);
            $pontos[] = [
                'id' => (string) $a->id,
                'nome' => $a->name,
                'patrimonio' => $a->patrimonio,
                'categoria' => $a->asset_category,
                'status' => $a->status,
                'status_rotulo' => self::STATUS[$a->status] ?? $a->status,
                'lat' => (float) $posicao['lat'],
                'lng' => (float) $posicao['lng'],
                'fonte' => $posicao['fonte'],
                'fonte_rotulo' => $posicao['fonte'] === 'rastreador' && $simulado ? 'Rastreador (simulado — demonstração)' : self::FONTES[$posicao['fonte']],
                'local' => $posicao['local'],
                'cliente' => $contrato?->client?->name ?? $a->client?->name,
                'contrato' => $contrato ? ['numero' => $contrato->contract_number, 'ate' => $contrato->end_date ? Carbon::parse($contrato->end_date)->format('d/m/Y') : null] : null,
                'custo_total' => $custo['custo_total'] ?? 0.0,
                'razao' => isset($custo['razao']) && $custo['razao'] !== null ? (int) round($custo['razao'] * 100) : null,
                'situacao' => $custo['situacao'] ?? null,
                'rastreador' => $rastreio !== null,
                'velocidade' => $rastreio['velocidade'] ?? null,
                'atualizado_em' => $rastreio['atualizado_em'] ?? null,
                'trilha' => $rastreio['trilha'] ?? [],
                'url' => AssetResource::getUrl('edit', ['record' => $a->id]),
            ];
        }

        return ['pontos' => $pontos, 'sem_localizacao' => $sem, 'simulado' => $simulado, 'atualizado_em' => Carbon::now()->format('H:i:s')];
    }

    /** Posicao "de cadastro": obra do contrato ativo, depois o proprio equipamento, a unidade e o cliente. @return array<string, mixed>|null */
    private function posicaoCadastral(Asset $a, ?Contract $contrato): ?array
    {
        if ($contrato) {
            $loc = match ($contrato->local_tipo) {
                Contract::LOCAL_TIPO_SEDE_EMPRESA => $contrato->internalUnit ? ['lat' => $contrato->internalUnit->latitude, 'lng' => $contrato->internalUnit->longitude, 'local' => 'Sede: '.$contrato->internalUnit->name] : null,
                Contract::LOCAL_TIPO_ENDERECO_CLIENTE => $contrato->client ? ['lat' => $contrato->client->latitude, 'lng' => $contrato->client->longitude, 'local' => 'Endereço do cliente: '.($contrato->client->address ?: $contrato->client->name)] : null,
                default => ['lat' => $contrato->latitude_obra, 'lng' => $contrato->longitude_obra, 'local' => trim(($contrato->endereco_obra ?: 'Obra').($contrato->cidade_obra ? ' — '.$contrato->cidade_obra.'/'.$contrato->uf_obra : ''))],
            };
            if ($loc && $loc['lat'] && $loc['lng']) {
                return $loc + ['fonte' => 'contrato'];
            }
        }

        if ($a->latitude && $a->longitude) {
            return ['lat' => $a->latitude, 'lng' => $a->longitude, 'fonte' => 'cadastro', 'local' => $a->endereco ?? 'Local do cadastro'];
        }
        if ($a->internalUnit?->latitude && $a->internalUnit?->longitude) {
            return ['lat' => $a->internalUnit->latitude, 'lng' => $a->internalUnit->longitude, 'fonte' => 'unidade', 'local' => $a->internalUnit->name];
        }
        if ($a->client?->latitude && $a->client?->longitude) {
            return ['lat' => $a->client->latitude, 'lng' => $a->client->longitude, 'fonte' => 'cliente', 'local' => $a->client->address ?: $a->client->name];
        }

        return null;
    }

    /** Posicoes ao vivo do Traccar para os equipamentos com rastreador vinculado. Falha de rede = sem rastreio (cai no contrato). @return array<string, array<string, mixed>> */
    private function posicoesReais(): array
    {
        try {
            $vinculos = FrotaVinculoGps::query()->where('ativo', true)->get(['ativo_id', 'traccar_device_id']);
            if ($vinculos->isEmpty()) {
                return [];
            }

            $porDispositivo = collect(app(TraccarService::class)->getPositions($vinculos->pluck('traccar_device_id')->all()))->keyBy('deviceId');
            $resultado = [];
            foreach ($vinculos as $v) {
                $p = $porDispositivo->get($v->traccar_device_id);
                if (! $p || blank($p['latitude'] ?? null)) {
                    continue;
                }
                $resultado[(string) $v->ativo_id] = [
                    'lat' => (float) $p['latitude'], 'lng' => (float) $p['longitude'],
                    'velocidade' => round(($p['speed'] ?? 0) * 1.852, 1), // Traccar manda nos
                    'atualizado_em' => isset($p['fixTime']) ? Carbon::parse($p['fixTime'])->format('d/m/Y H:i') : null,
                    'trilha' => [],
                ];
            }

            return $resultado;
        } catch (\Throwable) {
            return [];
        }
    }
}
