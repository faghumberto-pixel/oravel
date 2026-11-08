<?php

namespace App\Filament\Resources\CatalogoItemResource\Pages;

use App\Filament\Resources\CatalogoItemResource;
use App\Services\Catalogo\CatalogoGestao;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditCatalogoItem extends EditRecord
{
    protected static string $resource = CatalogoItemResource::class;

    protected ?array $mudanca = null;

    protected function beforeSave(): void
    {
        $gestao = app(CatalogoGestao::class);
        $desativando = $this->record->ativo && ! filter_var($this->data['ativo'] ?? true, FILTER_VALIDATE_BOOLEAN);

        if ($desativando && $gestao->itemUsadoEmImpeditivo($this->record)) {
            if (mb_strlen(trim((string) ($this->data['justificativa'] ?? ''))) < 5) {
                throw ValidationException::withMessages(['data.justificativa' => CatalogoGestao::AVISO_RESPONSAVEL_TECNICO.' Informe a justificativa para desativar um item impeditivo em uso.']);
            }

            $this->mudanca = [['tipo' => 'item_impeditivo_desativado', 'descricao' => "Item desativado: {$this->record->nome}", 'antes' => true, 'depois' => false]];
        }
    }

    protected function afterSave(): void
    {
        if ($this->mudanca) {
            app(CatalogoGestao::class)->registrarEdicaoSensivel($this->record, $this->mudanca, (string) $this->data['justificativa']);
            $this->mudanca = null;
        }
    }
}
