<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssetLogResource extends JsonResource
{
    /**
     * Transforma o recurso em um array para o Front-end.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'asset_id'   => $this->asset_id,
            
            // Tradução da ação para o usuário
            'acao'       => match($this->action) {
                'checklist_failure_detected' => 'Falha detectada no Checklist',
                'status_change'              => 'Alteração de Status',
                default                      => $this->action,
            },

            // Detalhes técnicos (itens que falharam, usuário que alterou, etc)
            'detalhes'   => $this->details,

            // Formatação de datas
            'data_registro' => $this->created_at->format('d/m/Y H:i:s'),
            'tempo_atras'   => $this->created_at->diffForHumans(),
        ];
    }
}
