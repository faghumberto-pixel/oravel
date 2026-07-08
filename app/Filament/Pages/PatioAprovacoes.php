<?php

namespace App\Filament\Pages;

use App\Models\Asset;
use App\Models\EquipmentMovement;
use App\Support\Tenancy;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Segunda etapa do fluxo de despacho: o operador ja completou 100% do
 * checklist (RentalDispatchChecklistMobile), a movimentacao fica
 * "aguardando_aprovacao" e so aparece liberada (QR valido) depois que um
 * gestor do patio audita as fotos/respostas aqui e da o "OK Tecnico" --
 * com confirmacao de senha, pra ficar registrado quem autorizou.
 */
class PatioAprovacoes extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationLabel = 'Aprovações do Pátio';

    protected static ?string $title = 'Aprovações do Pátio';

    protected static ?string $navigationGroup = 'Logística';

    protected static string $view = 'filament.pages.patio-aprovacoes';

    public ?string $reviewingId = null;

    public string $approvalPassword = '';

    public string $rejectReason = '';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('viewAny', EquipmentMovement::class);
    }

    public function getPendingProperty(): Collection
    {
        $tenant = Tenancy::current();
        if (! $tenant) {
            return collect();
        }

        return EquipmentMovement::where('tenant_id', $tenant->id)
            ->where('status', EquipmentMovement::STATUS_AGUARDANDO_APROVACAO)
            ->with(['items.media', 'asset', 'solicitacaoLocacao.customer'])
            ->latest('completed_at')
            ->get();
    }

    public function review(?string $id): void
    {
        $this->reviewingId = $this->reviewingId === $id ? null : $id;
        $this->approvalPassword = '';
        $this->rejectReason = '';
    }

    public function approve(string $id): void
    {
        $movement = EquipmentMovement::where('status', EquipmentMovement::STATUS_AGUARDANDO_APROVACAO)->findOrFail($id);

        if (! Hash::check($this->approvalPassword, Auth::user()->password)) {
            Notification::make()->title('Senha incorreta.')->danger()->send();

            return;
        }

        $movement->update([
            'status' => EquipmentMovement::STATUS_CONCLUIDO,
            'approved_by_user_id' => Auth::id(),
            'approved_at' => now(),
        ]);

        // Guindaste/equipamento efetivamente sai do patio -- vira Locado.
        $movement->asset?->update(['status' => Asset::STATUS_LOCADO]);

        Notification::make()->title('OK técnico registrado. Equipamento liberado para saída.')->success()->send();

        $this->review(null);
    }

    public function reject(string $id): void
    {
        $this->validate(['rejectReason' => 'required|string|max:1000']);

        $movement = EquipmentMovement::where('status', EquipmentMovement::STATUS_AGUARDANDO_APROVACAO)->findOrFail($id);

        $movement->update([
            'status' => EquipmentMovement::STATUS_EM_ANDAMENTO,
            'rejected_reason' => $this->rejectReason,
        ]);

        Notification::make()->title('Checklist recusado e devolvido para o operador.')->warning()->send();

        $this->review(null);
    }
}
