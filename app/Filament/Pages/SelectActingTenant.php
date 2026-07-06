<?php

namespace App\Filament\Pages;

use App\Models\Tenant;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Super admin nao tem tenant proprio, entao nao consegue criar nenhum
 * registro por tenant (Ativo, Cliente, Grupo de Checklist, etc.) sem
 * escolher "em nome de qual tenant" ele esta cadastrando agora. Essa
 * escolha fica na sessao (acting_tenant_id) e e lida por
 * App\Support\Tenancy::current() -- nao afeta a leitura (super admin
 * sempre ve todos os tenants, com ou sem essa escolha).
 */
class SelectActingTenant extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationGroup = 'Configurações';

    protected static ?string $navigationLabel = 'Atuar como Tenant';

    protected static ?string $title = 'Atuar como Tenant';

    protected static string $view = 'filament.pages.select-acting-tenant';

    public ?array $data = [];

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) auth()->user()?->isSuperAdmin();
    }

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isSuperAdmin();
    }

    public function mount(): void
    {
        $this->form->fill([
            'acting_tenant_id' => session('acting_tenant_id'),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Select::make('acting_tenant_id')
                ->label('Tenant em que vou cadastrar registros')
                ->helperText('Enquanto nenhum tenant for escolhido, você não consegue criar Ativos, Clientes, Grupos de Checklist etc. — só visualizar. A visualização de todos os tenants nunca é afetada por essa escolha.')
                ->options(Tenant::orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->native(false),
        ])->statePath('data');
    }

    public function save(): void
    {
        $tenantId = $this->form->getState()['acting_tenant_id'] ?? null;

        if ($tenantId) {
            session(['acting_tenant_id' => $tenantId]);
            Notification::make()
                ->title('Agora você está atuando como '.Tenant::find($tenantId)?->name)
                ->success()
                ->send();
        } else {
            session()->forget('acting_tenant_id');
            Notification::make()
                ->title('Seleção de tenant removida')
                ->body('Você não conseguirá criar novos registros por tenant até escolher um.')
                ->warning()
                ->send();
        }
    }
}
