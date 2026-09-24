<?php

namespace App\Filament\Pages;

use App\Models\Client;
use App\Support\Tenancy;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Tela de auto-atendimento do admin da locadora: segmento da operação e
 * algumas preferencias de tela/metas -- terminologia fixa pra todo mundo.
 *
 * REMOVIDO 2026-09-24 (decisão do usuário): o toggle "Módulos por Nicho"
 * (enabled_modules/Tenant::hasModuleEnabled(), Prazo Fatal, Quarentena,
 * SLA/Emergência etc) era um segundo sistema de gate paralelo ao Contrato,
 * subtrativo e nunca de fato configurado por ninguém (enabled_modules
 * sempre null na prática) -- inconsistente com "uma única fonte de
 * verdade pro que o cliente vê" que o Contrato já resolve. Removido daqui
 * e de todo lugar que checava Tenant::hasModuleEnabled() (AccountReceivableResource,
 * MaintenanceOrderResource, AssetResource, PainelSlaEmergencia,
 * MaintenanceKanban, EquipmentPatioArrivalMobile, EquipmentMovementMobile,
 * AsaasWebhookController) -- cada checagem sempre resolvia pra "true" por
 * padrão mesmo, então remover é comportamento idêntico ao anterior pra
 * quem nunca configurou (ou seja, todo mundo).
 */
class ManageTenantSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationGroup = 'Configurações';

    protected static ?string $navigationLabel = 'Configurações do Tenant';

    protected static ?string $title = 'Configurações do Tenant';

    protected static string $view = 'filament.pages.manage-tenant-settings';

    public ?array $data = [];

    private const FIELD_VISIBILITY_KEYS = [
        'billing_plan_id',
    ];

    // Mesmas chaves de Tenant::DEFAULT_TARGETS -- centralizado la' pro
    // getTarget() usado no dashboard Gestao a Vista, repetido aqui so'
    // pros defaults do form (nao da pra acessar a const privada do Model).
    private const TARGET_DEFAULTS = [
        'manutencao_realizada' => 90.0,
        'disponibilidade' => 90.0,
        'efetividade' => 85.0,
    ];

    // Decidido 2026-09-24: "Configurações" agora é um módulo como qualquer
    // outro, selecionável por Contrato (feature key 'modulo_configuracoes',
    // ver Plan::getAvailableFeaturesOptions() e ContratoResource::
    // groupedFeatureOptions()) -- antes era sempre visível pra todo admin,
    // sem gate nenhum.
    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isAdmin()
            && (bool) Tenancy::current()?->hasFeature('modulo_configuracoes');
    }

    public function mount(): void
    {
        $tenant = Tenancy::current();

        $uiCustomizations = is_array($tenant?->ui_customizations) ? $tenant->ui_customizations : [];
        $targets = is_array($tenant?->targets) ? $tenant->targets : [];

        $this->form->fill([
            'segment' => $tenant?->segment,
            'targets' => array_merge(self::TARGET_DEFAULTS, array_intersect_key($targets, self::TARGET_DEFAULTS)),
            'ui_customizations' => array_keys(array_filter(array_merge(
                array_fill_keys(self::FIELD_VISIBILITY_KEYS, true),
                array_intersect_key($uiCustomizations, array_flip(self::FIELD_VISIBILITY_KEYS))
            ))),
            'kanban_default_view' => $uiCustomizations['kanban_default_view'] ?? 'oficina',
        ]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Segmento')
                ->schema([
                    Select::make('segment')
                        ->label('Segmento da Locadora')
                        ->options(Client::nicheLabels())
                        ->native(false),
                ]),

            Section::make('Preferências de Tela')
                ->schema([
                    CheckboxList::make('ui_customizations')
                        ->label('Campos visíveis')
                        ->options([
                            'billing_plan_id' => 'Mostrar campo "Plano de Cobrança (Dinâmico)" em Contas a Receber',
                        ]),

                    Select::make('kanban_default_view')
                        ->label('View padrão do Kanban de Manutenção')
                        ->options([
                            'oficina' => 'Oficina (Pátio)',
                            'comercial' => 'Comercial (Giro)',
                        ])
                        ->native(false),
                ]),

            Section::make('Metas de Manutenção')
                ->description('Usadas como referência visual (meta ≥) nos indicadores do painel "Gestão à Vista".')
                ->schema([
                    TextInput::make('targets.manutencao_realizada')
                        ->label('Meta de Manutenção Realizada')
                        ->numeric()->minValue(0)->maxValue(100)->suffix('%'),
                    TextInput::make('targets.disponibilidade')
                        ->label('Meta de Disponibilidade')
                        ->numeric()->minValue(0)->maxValue(100)->suffix('%'),
                    TextInput::make('targets.efetividade')
                        ->label('Meta de Efetividade')
                        ->numeric()->minValue(0)->maxValue(100)->suffix('%'),
                ])
                ->columns(3),
        ])->statePath('data');
    }

    public function save(): void
    {
        $tenant = Tenancy::current();

        if (! $tenant) {
            return;
        }

        $state = $this->form->getState();

        $uiCustomizations = array_fill_keys(self::FIELD_VISIBILITY_KEYS, false);
        foreach ((array) ($state['ui_customizations'] ?? []) as $key) {
            $uiCustomizations[$key] = true;
        }
        $uiCustomizations['kanban_default_view'] = $state['kanban_default_view'] ?? 'oficina';

        $targets = self::TARGET_DEFAULTS;
        foreach (self::TARGET_DEFAULTS as $key => $default) {
            if (isset($state['targets'][$key]) && is_numeric($state['targets'][$key])) {
                $targets[$key] = (float) $state['targets'][$key];
            }
        }

        $tenant->update([
            'segment' => $state['segment'] ?? null,
            'ui_customizations' => $uiCustomizations,
            'targets' => $targets,
        ]);

        Notification::make()
            ->title('Configurações salvas')
            ->success()
            ->send();
    }
}
