<?php

namespace App\Filament\Central\Resources\ContratoResource\Pages;

use App\Filament\Central\Resources\ContratoResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateContrato extends CreateRecord
{
    protected static string $resource = ContratoResource::class;

    /**
     * 'price' não aparece no formulário (a tela foi enxugada pra só o
     * essencial), mas a coluna é usada em alguns lugares legados do
     * sistema como fallback de valor -- mantida igual a 'base_price' pra
     * nunca ficar em 0 por engano.
     *
     * O checklist de módulos vem dividido em vários campos
     * 'features_group_{grupo}' (um CheckboxList por grupo de menu, 2026-09-23
     * -- ver ContratoResource::groupedFeatureOptions()) -- mesclados aqui
     * num único array 'features' antes de salvar, que é a coluna real do
     * Plan. Os campos temporários são descartados (Plan não tem essas
     * colunas; deixá-los não quebraria nada por causa da proteção de mass
     * assignment do Eloquent, mas é mais limpo remover).
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['price'] = $data['base_price'] ?? 0;
        $data['features'] = static::mergeGroupedFeatures($data);

        return array_filter($data, fn ($key) => ! str_starts_with($key, 'features_group_'), ARRAY_FILTER_USE_KEY);
    }

    /**
     * Bug real achado 2026-09-24 simulando a primeira contratação: cada
     * CheckboxList devolve uma lista SIMPLES das chaves marcadas
     * (`['tabela_assets', ...]`), mas `Plan::hasFeature()` (e todo o resto
     * do sistema) espera um MAPA (`['tabela_assets' => true, ...]`) --
     * "funcionava" só pelo fallback `in_array()` de `Plan::hasFeature()`,
     * até o fix do `saas:sync-modules` (mesmo dia) começar a adicionar
     * `false` pra toda chave que "não existia" no array (usa
     * `array_key_exists`, que não via as chaves numéricas da lista) --
     * essas chaves falsas passaram a vencer a checagem antes do fallback,
     * derrubando TODOS os módulos de um Contrato novo mesmo depois de
     * pago. Convertido pra mapa aqui na origem, formato único e correto
     * em todo o sistema.
     */
    public static function mergeGroupedFeatures(array $data): array
    {
        $selectedKeys = [];
        foreach ($data as $key => $value) {
            if (str_starts_with($key, 'features_group_') && is_array($value)) {
                $selectedKeys = array_merge($selectedKeys, $value);
            }
        }

        $features = [];
        foreach (array_unique($selectedKeys) as $featureKey) {
            $features[$featureKey] = true;
        }

        return $features;
    }

    /**
     * Mostra o link assim que o contrato é criado -- é o ponto inteiro
     * dessa tela existir (pedido do usuário 2026-09-23: "quero uma tela
     * nova, só pra gerar o link"). Redireciona pra lista (comportamento
     * padrão do Filament sem página de 'view' configurada), que funciona
     * como o histórico dos contratos já gerados.
     */
    protected function afterCreate(): void
    {
        $link = route('checkout.create', ['plano' => $this->record->id]);

        Notification::make()
            ->title('Link do contrato gerado')
            ->body("Copie e envie pro cliente:\n\n{$link}")
            ->success()
            ->persistent()
            ->send();
    }
}
