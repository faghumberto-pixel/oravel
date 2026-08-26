<?php

namespace App\Filament\Pages;

use App\Models\Client;
use App\Models\ClientMessage;
use App\Models\EquipmentPickupRequest;
use App\Models\MaintenanceOrder;
use App\Models\SolicitacaoLocacao;
use App\Notifications\ClientCommunicationNotification;
use App\Support\Tenancy;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Espelho, no lado do Tenant, do que o Client faz no Portal (chat,
 * solicitações). Agrega 3 fontes diferentes de pendência
 * (SolicitacaoLocacao/MaintenanceOrder/EquipmentPickupRequest) por
 * Client -- mesmo padrão de MaintenanceKanban (Page customizada, não
 * Resource, pra visão que não é 1 tabela de 1 model).
 */
class GestaoClientes extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Comercial';

    protected static ?string $navigationLabel = 'Gestão de Clientes';

    protected static string $view = 'filament.pages.gestao-clientes';

    public ?string $selectedClientId = null;

    public ?string $search = null;

    public ?array $replyData = [];

    public ?array $communicationData = [];

    public function mount(): void
    {
        $this->replyForm->fill();
        $this->communicationForm->fill();
    }

    /**
     * Duas forms nomeadas na mesma Page -- InteractsWithForms resolve
     * métodos chamados 'form' por convenção, então com múltiplas forms é
     * preciso declarar getForms() explicitamente (mesmo padrão que
     * Filament\Pages\Auth\Login usa internamente).
     *
     * @return array<string, Form>
     */
    protected function getForms(): array
    {
        return [
            'replyForm' => $this->replyFormSchema($this->makeForm()->statePath('replyData')),
            'communicationForm' => $this->communicationFormSchema($this->makeForm()->statePath('communicationData')),
        ];
    }

    private function replyFormSchema(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Textarea::make('body')
                    ->label('Responder')
                    ->rows(3),
            ]);
    }

    private function communicationFormSchema(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('client_ids')
                    ->label('Destinatários')
                    ->multiple()
                    ->options(fn () => Client::where('tenant_id', Tenancy::current()?->id)
                        ->whereNotNull('portal_access_enabled_at')
                        ->pluck('name', 'id'))
                    ->placeholder('Selecione (vazio = todos com acesso ao portal)'),
                Forms\Components\TextInput::make('subject')
                    ->label('Assunto')
                    ->required(),
                Forms\Components\Textarea::make('body')
                    ->label('Mensagem')
                    ->rows(5)
                    ->required(),
            ]);
    }

    public function getClientsProperty()
    {
        $tenantId = Tenancy::current()?->id;
        $areas = $this->visibleAreas();

        return Client::where('tenant_id', $tenantId)
            ->when(filled($this->search), fn ($query) => $query->where('name', 'like', "%{$this->search}%"))
            ->orderBy('name')
            ->get()
            ->map(function (Client $client) use ($tenantId, $areas) {
                $client->pending_count = $this->pendingCountFor($client->id, $tenantId);
                $client->unread_count = ClientMessage::withoutGlobalScope('tenant')
                    ->where('tenant_id', $tenantId)
                    ->where('client_id', $client->id)
                    ->where('sender_type', ClientMessage::SENDER_CLIENT)
                    ->whereNull('read_at')
                    ->where(fn ($q) => $q->whereIn('area', $areas)->orWhereNull('area'))
                    ->count();

                return $client;
            })
            ->sortByDesc(fn (Client $client) => ($client->unread_count * 1000) + $client->pending_count)
            ->values();
    }

    /**
     * Áreas de ClientMessage que o usuário logado pode ver -- mensagens
     * antigas sem área (area = null) ficam visíveis a todos, fallback
     * seguro pra não esconder histórico pré-existente à feature.
     *
     * @return array<int, string>
     */
    private function visibleAreas(): array
    {
        return Auth::user()?->visibleClientMessageAreas() ?? [];
    }

    public function getSelectedClientProperty(): ?Client
    {
        if (! $this->selectedClientId) {
            return null;
        }

        return Client::where('tenant_id', Tenancy::current()?->id)->find($this->selectedClientId);
    }

    public function getMessagesProperty()
    {
        if (! $this->selectedClientId) {
            return collect();
        }

        $areas = $this->visibleAreas();

        return ClientMessage::withoutGlobalScope('tenant')
            ->where('tenant_id', Tenancy::current()?->id)
            ->where('client_id', $this->selectedClientId)
            ->where(fn ($q) => $q->whereIn('area', $areas)->orWhereNull('area'))
            ->with('media')
            ->orderBy('created_at')
            ->get();
    }

    public function getPendingRequestsProperty(): array
    {
        if (! $this->selectedClientId) {
            return [];
        }

        $tenantId = Tenancy::current()?->id;
        $clientId = $this->selectedClientId;

        return [
            'solicitacoes' => SolicitacaoLocacao::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenantId)->where('customer_id', $clientId)
                ->whereIn('status_comercial', ['proposta_em_andamento', 'reserva_manutencao'])
                ->get(),
            'ordens' => MaintenanceOrder::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenantId)->where('client_id', $clientId)
                ->whereNotIn('status', ['Concluída', 'Completado', 'Cancelada'])
                ->get(),
            'retiradas' => EquipmentPickupRequest::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenantId)->where('client_id', $clientId)
                ->where('status', '!=', EquipmentPickupRequest::STATUS_CONCLUIDO)
                ->get(),
        ];
    }

    /**
     * Reclassifica a área de uma mensagem já recebida -- não guarda quem
     * reclassificou nem quando (não pedido nesta fase). A partir daqui o
     * filtro de área já reflete a mudança: quem via a área antiga deixa
     * de contar, quem vê a nova passa a contar.
     */
    public function reclassify(string $messageId, string $area): void
    {
        ClientMessage::withoutGlobalScope('tenant')
            ->where('tenant_id', Tenancy::current()?->id)
            ->where('id', $messageId)
            ->update(['area' => $area]);

        Notification::make()->title('Mensagem encaminhada')->success()->send();
    }

    public function selectClient(string $clientId): void
    {
        $this->selectedClientId = $clientId;

        ClientMessage::withoutGlobalScope('tenant')
            ->where('tenant_id', Tenancy::current()?->id)
            ->where('client_id', $clientId)
            ->where('sender_type', ClientMessage::SENDER_CLIENT)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function reply(): void
    {
        if (! $this->selectedClientId) {
            return;
        }

        $state = $this->replyForm->getState();

        if (blank($state['body'])) {
            return;
        }

        // Herda a área da última mensagem do Client na conversa -- sem
        // isso a resposta ficaria com area=null (visível a todos),
        // "vazando" a resposta de uma área restrita pra fora do filtro.
        $lastClientArea = ClientMessage::withoutGlobalScope('tenant')
            ->where('tenant_id', Tenancy::current()?->id)
            ->where('client_id', $this->selectedClientId)
            ->where('sender_type', ClientMessage::SENDER_CLIENT)
            ->whereNotNull('area')
            ->latest('created_at')
            ->value('area');

        ClientMessage::create([
            'tenant_id' => Tenancy::current()?->id,
            'client_id' => $this->selectedClientId,
            'area' => $lastClientArea,
            'sender_type' => ClientMessage::SENDER_USER,
            'sender_id' => Auth::id(),
            'body' => $state['body'],
        ]);

        $this->replyForm->fill();

        Notification::make()->title('Resposta enviada')->success()->send();
    }

    public function sendCommunication(): void
    {
        $state = $this->communicationForm->getState();
        $tenantId = Tenancy::current()?->id;

        $clients = filled($state['client_ids'] ?? null)
            ? Client::whereIn('id', $state['client_ids'])->where('tenant_id', $tenantId)->get()
            : Client::where('tenant_id', $tenantId)->whereNotNull('portal_access_enabled_at')->get();

        $sent = 0;
        foreach ($clients as $client) {
            if (! $client->portal_access_enabled_at) {
                continue;
            }

            try {
                $client->notify(new ClientCommunicationNotification($state['subject'], $state['body']));
                $sent++;
            } catch (Throwable $e) {
                Log::warning('GestaoClientes: falha ao enviar comunicado.', [
                    'client_id' => $client->id, 'error' => $e->getMessage(),
                ]);
            }
        }

        $this->communicationForm->fill();

        Notification::make()->title("Comunicado enviado para {$sent} cliente(s)")->success()->send();
    }

    private function pendingCountFor(string $clientId, ?string $tenantId): int
    {
        $solicitacoes = SolicitacaoLocacao::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)->where('customer_id', $clientId)
            ->whereIn('status_comercial', ['proposta_em_andamento', 'reserva_manutencao'])
            ->count();

        $ordens = MaintenanceOrder::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)->where('client_id', $clientId)
            ->whereNotIn('status', ['Concluída', 'Completado', 'Cancelada'])
            ->count();

        $retiradas = EquipmentPickupRequest::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)->where('client_id', $clientId)
            ->where('status', '!=', EquipmentPickupRequest::STATUS_CONCLUIDO)
            ->count();

        return $solicitacoes + $ordens + $retiradas;
    }

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('viewAny', Client::class);
    }

    public function getMaxContentWidth(): MaxWidth
    {
        return MaxWidth::Full;
    }
}
