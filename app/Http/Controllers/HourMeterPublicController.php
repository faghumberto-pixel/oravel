<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePublicHourMeterRequest;
use App\Models\Asset;
use App\Models\HorimeterReading;
use App\Support\HourMeterReadingWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Link público (sem login) de registro de horímetro, pra funcionário do
 * cliente que alugou o equipamento -- não é usuário do ERP, não tem
 * permissão nem tenant. Acesso só pelo token dedicado do ativo
 * (Asset::hourMeterPublicToken(), distinto do QR de pátio/portaria já
 * existente), e só enquanto o ativo estiver com status "locado": fora
 * disso não faz sentido um terceiro apontar o horímetro.
 */
class HourMeterPublicController extends Controller
{
    public function __construct(private HourMeterReadingWriter $writer) {}

    public function show(string $token): View|Response
    {
        $asset = $this->findLocadoAssetByToken($token);

        $lastReading = HorimeterReading::query()->latestForAsset($asset->id)->first();

        return view('hour-meter-public', [
            'asset' => $asset,
            'lastReading' => $lastReading?->reading,
        ]);
    }

    public function store(StorePublicHourMeterRequest $request, string $token): JsonResponse
    {
        $asset = $this->findLocadoAssetByToken($token);

        $reading = DB::transaction(fn () => $this->writer->create(
            $asset,
            [...$request->validated(), 'source' => HorimeterReading::SOURCE_PUBLIC_CLIENT],
            $request->file('photo'),
        ));

        return response()->json(['id' => $reading->id, 'status' => 'synced']);
    }

    /**
     * Str::isUuid() antes da query -- um token mal formado (link copiado
     * errado, bot escaneando URLs) direto no WHERE de uma coluna uuid
     * derruba o Postgres com "invalid input syntax for type uuid" (erro
     * SQL, 500) em vez de um 404 limpo, já que não há cast implícito
     * varchar->uuid nessa comparação.
     */
    private function findLocadoAssetByToken(string $token): Asset
    {
        if (! Str::isUuid($token)) {
            abort(404);
        }

        $asset = Asset::query()->where('hour_meter_public_token', $token)->first();

        if (! $asset || $asset->status !== Asset::STATUS_LOCADO) {
            abort(404);
        }

        return $asset;
    }
}
