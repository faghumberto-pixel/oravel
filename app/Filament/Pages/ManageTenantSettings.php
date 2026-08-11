<?php

namespace App\Filament\Pages;

use App\Models\Client;
use App\Models\Tenant;
use App\Support\Tenancy;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Tela de auto-atendimento do admin da locadora pra ligar/desligar os
 * modulos de nicho (Prazo Fatal, Quarentena, SLA/Emergencia, Kanban de
 * oficina extra, Mau Uso, Horimetro rigido, Banco de Carga, automacao de
 * multa em Contas a Receber) e algumas preferencias de tela -- sem tocar em
 * terminologia (fixa pra todo mundo) nem no que o plano libera
 * (enabled_modules e' SUBTRATIVO, nunca liga o que o plano nega, ver
 * Tenant::hasModuleEnabled()).
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

    private const MODULE_KEYS = [
        'prazo_fatal',
        'banco_de_carga',
        'quarentena',
        'sla_emergencia',
        'horimetro_rigido',
        'kanban_oficina_extra',
        'mau_uso',
        'contas_a_receber',
    ];

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

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    public function mount(): void
    {
        $tenant = Tenancy::current();

        $enabledModules = is_array($tenant?->enabled_modules) ? $tenant->enabled_modules : [];
        $uiCustomizations = is_array($tenant?->ui_customizations) ? $tenant->ui_customizations : [];
        $targets = is_array($tenant?->targets) ? $tenant->targets : [];

        $this->form->fill([
            'segment' => $tenant?->segment,
            'targets' => array_merge(self::TARGET_DEFAULTS, array_intersect_key($targets, self::TARGET_DEFAULTS)),
            'enabled_modules' => array_keys(array_filter(array_merge(
                array_fill_keys(self::MODULE_KEYS, true),
                array_intersect_key($enabledModules, array_flip(self::MODULE_KEYS))
            ))),
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
                ->description('Predefinição usada só para pré-marcar os módulos abaixo. Trocar o segmento não altera nada sozinho — clique em "Aplicar Predefinição" quando quiser recarregar as sugestões, e depois Salvar para confirmar.')
                ->schema([
                    Select::make('segment')
                        ->label('Segmento da Locadora')
                        ->options(Client::nicheLabels())
                        ->native(false)
                        ->live(),

                    Actions::make([
                        Action::make('aplicar_preset')
                            ->label('Aplicar Predefinição do Segmento')
                            ->icon('heroicon-m-sparkles')
                            ->color('warning')
                            ->requiresConfirmation()
                            ->modalDescription('Isso substitui as marcações de módulos abaixo pela predefinição do segmento escolhido — só depois de clicar em Salvar. Módulos já configurados manualmente serão sobrescritos.')
                            ->action(function (Get $get, Set $set) {
                                $preset = Tenant::applySegmentPreset((string) $get('segment'));
                                $set('enabled_modules', array_keys(array_filter($preset)));
                            }),
                    ]),
                ]),

            Section::make('Módulos por Nicho')
                ->description('Desmarque para esconder um módulo que seu plano libera, mas que sua operação não usa.')
                ->schema([
                    CheckboxList::make('enabled_modules')
                        ->hiddenLabel()
                        ->options([
                            'prazo_fatal' => 'Prazo Fatal (Eventos)',
                            'banco_de_carga' => 'Banco de Carga (Eventos)',
                            'quarentena' => 'Quarentena pós-chegada (Eventos / Industrial / Construção)',
                            'sla_emergencia' => 'SLA / Chamado de Emergência (Industrial / Hospitalar)',
                            'horimetro_rigido' => 'Horímetro rígido (Industrial / Construção)',
                            'kanban_oficina_extra' => 'Colunas extras no Kanban de Oficina (Construção Civil)',
                            'mau_uso' => 'Evidência de Mau Uso (Construção Civil)',
                            'contas_a_receber' => 'Automação de multa em Contas a Receber',
                        ])
                        ->columns(2),
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

        $enabledModules = array_fill_keys(self::MODULE_KEYS, false);
        foreach ((array) ($state['enabled_modules'] ?? []) as $key) {
            $enabledModules[$key] = true;
        }

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
            'enabled_modules' => $enabledModules,
            'ui_customizations' => $uiCustomizations,
            'targets' => $targets,
        ]);

        Notification::make()
            ->title('Configurações salvas')
            ->success()
            ->send();
    }
}
