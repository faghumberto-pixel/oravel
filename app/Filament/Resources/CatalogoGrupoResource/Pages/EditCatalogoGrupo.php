<?php

namespace App\Filament\Resources\CatalogoGrupoResource\Pages;

use App\Filament\Resources\CatalogoGrupoResource;
use App\Services\Catalogo\CatalogoGestao;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditCatalogoGrupo extends EditRecord
{
    protected static string $resource = CatalogoGrupoResource::class;

    /** @var array<int, array> mudancas sensiveis detectadas neste salvamento */
    protected array $mudancasSensiveis = [];

    protected function beforeSave(): void
    {
        try {
            // $this->data ainda tem as linhas dos repeaters como "record-{id}" e o banco ainda esta como antes.
            $this->mudancasSensiveis = app(CatalogoGestao::class)
                ->exigirJustificativaSeSensivel($this->record, $this->data, $this->data['justificativa'] ?? null);
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(['data.justificativa' => collect($e->errors())->flatten()->first()]);
        }
    }

    protected function afterSave(): void
    {
        if ($this->mudancasSensiveis !== []) {
            app(CatalogoGestao::class)->registrarEdicaoSensivel($this->record, $this->mudancasSensiveis, (string) $this->data['justificativa']);
            $this->mudancasSensiveis = [];
            $this->data['justificativa'] = null;
        }
    }
}
