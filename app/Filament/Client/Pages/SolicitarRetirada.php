<?php

namespace App\Filament\Client\Pages;

use App\Models\Client;
use App\Models\Contract;
use App\Models\EquipmentPickupRequest;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Support\Facades\Auth;

/**
 * Cliente pede retirada de equipamento que já terminou de usar -- sem
 * automação de despacho, o operador vê e aciona manualmente em
 * EquipmentPickupRequestResource (admin, grupo Logística).
 */
class SolicitarRetirada extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-uturn-left';

    protected static ?string $navigationLabel = 'Solicitar Retirada';

    protected static string $view = 'filament.client.pages.solicitar-retirada';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        /** @var Client $client */
        $client = $this->guard()->user();

        $assetOptions = Contract::withoutGlobalScope('tenant')
            ->where('tenant_id', $client->tenant_id)
            ->where('client_id', $client->id)
            ->whereNotNull('asset_id')
            ->with('asset')
            ->get()
            ->pluck('asset.name', 'asset_id')
            ->filter();

        return $form
            ->schema([
                Forms\Components\Select::make('asset_id')
                    ->label('Equipamento')
                    ->options($assetOptions)
                    ->required(),
                Forms\Components\Textarea::make('notes')
                    ->label('Observações')
                    ->rows(3),
            ])
            ->statePath('data');
    }

    public function create(): void
    {
        /** @var Client $client */
        $client = $this->guard()->user();

        $state = $this->form->getState();

        $contract = Contract::withoutGlobalScope('tenant')
            ->where('tenant_id', $client->tenant_id)
            ->where('client_id', $client->id)
            ->where('asset_id', $state['asset_id'])
            ->latest('start_date')
            ->first();

        EquipmentPickupRequest::create([
            'tenant_id' => $client->tenant_id,
            'client_id' => $client->id,
            'asset_id' => $state['asset_id'],
            'contract_id' => $contract?->id,
            'notes' => $state['notes'] ?? null,
        ]);

        $this->form->fill();

        Notification::make()
            ->title('Solicitação de retirada enviada')
            ->success()
            ->send();
    }

    private function guard(): Guard
    {
        return Auth::guard('client');
    }
}
