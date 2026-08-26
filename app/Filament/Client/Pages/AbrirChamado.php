<?php

namespace App\Filament\Client\Pages;

use App\Models\Client;
use App\Models\Contract;
use App\Models\MaintenanceOrder;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Support\Facades\Auth;

/**
 * Abertura de chamado pelo Client -- não existia nenhum caminho externo de
 * criação de MaintenanceOrder antes deste portal (confirmado em
 * investigação: só Filament Pages internas e um Console Command criavam
 * OS). Cria com status 'Aberto' (mesmo default do form interno em
 * MaintenanceOrderResource) e maintenance_type Corretiva -- é sempre uma
 * ocorrência relatada pelo cliente, nunca preventiva/troca/avaria (esses
 * ficam a critério do operador).
 */
class AbrirChamado extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?string $navigationLabel = 'Abrir Chamado';

    protected static string $view = 'filament.client.pages.abrir-chamado';

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
                Forms\Components\Textarea::make('description')
                    ->label('O que está acontecendo?')
                    ->rows(4)
                    ->required(),
                Forms\Components\FileUpload::make('photos')
                    ->label('Fotos (opcional)')
                    ->image()
                    ->multiple()
                    ->directory('chamados-portal-cliente'),
            ])
            ->statePath('data');
    }

    public function create(): void
    {
        /** @var Client $client */
        $client = $this->guard()->user();

        $state = $this->form->getState();

        $order = MaintenanceOrder::create([
            'tenant_id' => $client->tenant_id,
            'client_id' => $client->id,
            'asset_id' => $state['asset_id'],
            'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE,
            'status' => 'Aberto',
            'description' => $state['description'],
        ]);

        foreach ($state['photos'] ?? [] as $photo) {
            $order->addMediaFromDisk($photo, 'public')->toMediaCollection();
        }

        $this->form->fill();

        Notification::make()
            ->title('Chamado aberto: '.$order->os_number)
            ->success()
            ->send();
    }

    private function guard(): Guard
    {
        return Auth::guard('client');
    }
}
