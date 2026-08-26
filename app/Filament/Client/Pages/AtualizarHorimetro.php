<?php

namespace App\Filament\Client\Pages;

use App\Models\Asset;
use App\Models\Client;
use App\Models\Contract;
use App\Models\HorimeterReading;
use App\Support\HourMeterReadingWriter;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Cliente registra horímetro do próprio ativo locado. Reaproveita
 * HourMeterReadingWriter (mesmo serviço usado por HourMeterSyncController
 * e HourMeterPublicController) -- sem duplicar lógica de gravação/foto.
 * source = SOURCE_CLIENT_PORTAL (distinto de SOURCE_PUBLIC_CLIENT, que é
 * o link sem login -- aqui o Client está autenticado).
 */
class AtualizarHorimetro extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationLabel = 'Atualizar Horímetro';

    protected static string $view = 'filament.client.pages.atualizar-horimetro';

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
                Forms\Components\TextInput::make('reading')
                    ->label('Leitura do Horímetro')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(9999999.99)
                    ->required(),
                Forms\Components\DateTimePicker::make('recorded_at')
                    ->label('Data/Hora da Leitura')
                    ->default(now())
                    ->required(),
                Forms\Components\Textarea::make('notes')
                    ->label('Observações')
                    ->rows(3),
                Forms\Components\Checkbox::make('reset_confirmed')
                    ->label('Confirmo que o horímetro foi zerado/substituído (leitura menor que a anterior é intencional)'),
                Forms\Components\FileUpload::make('photo')
                    ->label('Foto do Horímetro (opcional)')
                    ->image()
                    ->directory('horimeter-readings-portal-cliente'),
            ])
            ->statePath('data');
    }

    public function create(): void
    {
        /** @var Client $client */
        $client = $this->guard()->user();

        $state = $this->form->getState();

        // Revalida no servidor -- não confia apenas nas opções do Select,
        // o payload Livewire pode ser manipulado client-side.
        $belongsToClient = Contract::withoutGlobalScope('tenant')
            ->where('tenant_id', $client->tenant_id)
            ->where('client_id', $client->id)
            ->where('asset_id', $state['asset_id'])
            ->exists();

        if (! $belongsToClient) {
            Notification::make()
                ->title('Equipamento inválido')
                ->danger()
                ->send();

            return;
        }

        $asset = Asset::find($state['asset_id']);

        if (! $asset || $asset->status !== Asset::STATUS_LOCADO) {
            Notification::make()
                ->title('Este equipamento não está disponível para apontamento de horímetro no momento')
                ->danger()
                ->send();

            return;
        }

        // HourMeterReadingWriter::create() espera um UploadedFile real (faz
        // ->store() internamente) -- o FileUpload do Filament já entrega um
        // path já salvo em disco, não um UploadedFile. A foto fica com o
        // registro via photo_path apontando pro mesmo disco público
        // (mesmo diretório usado pelo campo), sem reconstruir UploadedFile
        // a partir de um path (frágil/inseguro).
        $reading = DB::transaction(function () use ($asset, $state, $client) {
            return app(HourMeterReadingWriter::class)->create($asset, [
                'reading' => $state['reading'],
                'recorded_at' => $state['recorded_at'],
                'recorded_by_name' => $client->name,
                'source' => HorimeterReading::SOURCE_CLIENT_PORTAL,
                'reset_confirmed' => $state['reset_confirmed'] ?? false,
                'notes' => $state['notes'] ?? null,
            ], null);
        });

        if (! empty($state['photo'])) {
            $reading->update(['photo_path' => $state['photo']]);
        }

        $this->form->fill();

        Notification::make()
            ->title('Leitura registrada')
            ->success()
            ->send();
    }

    private function guard(): Guard
    {
        return Auth::guard('client');
    }
}
