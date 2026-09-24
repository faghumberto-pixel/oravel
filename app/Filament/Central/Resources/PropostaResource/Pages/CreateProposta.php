<?php

namespace App\Filament\Central\Resources\PropostaResource\Pages;

use App\Filament\Central\Resources\PropostaResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateProposta extends CreateRecord
{
    protected static string $resource = PropostaResource::class;

    /**
     * 'price' não aparece no formulário (a tela foi enxugada pra só o
     * essencial), mas a coluna é usada em alguns lugares legados do
     * sistema como fallback de valor -- mantida igual a 'base_price' pra
     * nunca ficar em 0 por engano.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['price'] = $data['base_price'] ?? 0;

        return $data;
    }

    /**
     * Mostra o link assim que a proposta é criada -- é o ponto inteiro
     * dessa tela existir (pedido do usuário 2026-09-23: "quero uma tela
     * nova, só pra gerar o link"). Redireciona pra lista (comportamento
     * padrão do Filament sem página de 'view' configurada), que funciona
     * como o histórico das propostas já geradas.
     */
    protected function afterCreate(): void
    {
        $link = route('checkout.create', ['plano' => $this->record->id]);

        Notification::make()
            ->title('Link de assinatura gerado')
            ->body("Copie e envie pro cliente:\n\n{$link}")
            ->success()
            ->persistent()
            ->send();
    }
}
