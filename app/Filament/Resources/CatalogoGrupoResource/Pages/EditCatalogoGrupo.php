<?php

namespace App\Filament\Resources\CatalogoGrupoResource\Pages;

use App\Filament\Resources\CatalogoGrupoResource;
use App\Jobs\Aptidao\RecalcularAptidaoGrupoJob;
use App\Services\Catalogo\Aptidao\AptidaoImpacto;
use App\Services\Catalogo\CatalogoGestao;
use Filament\Actions;
use Filament\Notifications\Notification;
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

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('previa_impacto')->label('Prévia de impacto')->icon('heroicon-o-eye')->color('gray')
                ->tooltip('Mostra quantos ativos deste grupo mudariam de status de aptidão com o que está no formulário (nada é salvo).')
                ->action(function () {
                    $p = app(AptidaoImpacto::class)->previa($this->record, $this->data);

                    if ($p['total'] === 0) {
                        Notification::make()->info()->title('Nenhum ativo neste grupo.')->send();

                        return;
                    }

                    $linhas = collect($p['transicoes'])->map(function ($n, $chave) {
                        [$de, $para] = explode('>', $chave);

                        return "{$n} ativo(s): ".AptidaoImpacto::rotulo($de).' → '.AptidaoImpacto::rotulo($para);
                    })->implode("\n");

                    Notification::make()->title("{$p['mudaram']} de {$p['total']} ativo(s) mudariam de status")
                        ->body($p['mudaram'] ? $linhas : 'Nenhum ativo muda de status com estas alterações.')
                        ->color($p['mudaram'] ? 'warning' : 'success')->persistent()->send();
                }),
        ];
    }

    protected function afterSave(): void
    {
        // A regra do grupo mudou: recalcula a aptidao dos ativos dele em fila (sem efeito se a chave do contrato estiver desligada).
        RecalcularAptidaoGrupoJob::dispatch($this->record->getKey());

        if ($this->mudancasSensiveis !== []) {
            app(CatalogoGestao::class)->registrarEdicaoSensivel($this->record, $this->mudancasSensiveis, (string) $this->data['justificativa']);
            $this->mudancasSensiveis = [];
            $this->data['justificativa'] = null;
        }
    }
}
