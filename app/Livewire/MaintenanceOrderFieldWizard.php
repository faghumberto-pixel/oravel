<?php

namespace App\Livewire;

use App\Livewire\Concerns\HandlesChecklistItems;
use App\Models\EquipmentReplacement;
use App\Models\MaintenanceOrder;
use App\Models\MaintenanceOrderMaterial;
use App\Models\Material;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * "Modo Campo" -- execucao da O.S. no celular do tecnico, uma etapa por tela.
 *
 * Existe porque o form da O.S. (MaintenanceOrderResource::form()) tem 7 abas
 * simultaneas, denso demais pra usar em campo (luva, sol na tela, conexao
 * oscilando) e sem nocao de "o que falta". Aqui a O.S. e' SO executada -- abrir
 * O.S. continua sendo trabalho de gestao, porque exige decisoes que nao sao de
 * campo (tipo de operacao, prazo fatal, matriz ABC, cliente, retrabalho).
 *
 * Autoriza por 'update', nao por 'view': esta tela escreve na O.S. do inicio ao
 * fim. (MaintenanceChecklistMobile, mais antiga, autoriza por 'view' apesar de
 * tambem escrever -- nao copiar isso.)
 *
 * Sem offline real de proposito: cada etapa persiste no servidor ao avancar,
 * entao fechar o app no meio nao perde nada. Fila local/service worker ficaria
 * em cima de public/sw.js, que hoje e' um kill switch justamente porque a
 * versao anterior interceptava as chamadas do Livewire e quebrava o app.
 */
#[Layout('layouts.checklist-mobile')]
class MaintenanceOrderFieldWizard extends Component
{
    use HandlesChecklistItems;
    use WithFileUploads;

    public const TOTAL_STEPS = 5;

    /**
     * Mesma lista da action 'iniciar' em EditMaintenanceOrder -- status a
     * partir dos quais faz sentido comecar/retomar o servico.
     */
    private const STARTABLE_STATUSES = ['Aberto', 'Pendente', 'Pausada', 'Reprogramado', 'Reprogramada'];

    public MaintenanceOrder $maintenanceOrder;

    /**
     * Na URL pra que recarregar a pagina (ou perder a conexao e voltar) caia na
     * mesma etapa, sem coluna nova no banco.
     */
    #[Url]
    public int $step = 1;

    // --- Etapa 1: equipamento ---
    public ?string $horimetroEntry = null;

    public ?string $fuelLevel = null;

    // --- Etapa 2: checklist (compartilhado via trait) ---
    public ?string $expandedItemId = null;

    public string $newObservation = '';

    public $newPhoto = null;

    // --- Etapa 3: avarias/observações ---
    public string $damageDescription = '';

    public string $technicalNotes = '';

    public $damagePhotoBefore = null;

    public $damagePhotoAfter = null;

    public bool $shouldRegisterDamage = false;

    /**
     * Urgência da troca de equipamento (se Preventiva + dano encontrado)
     * Mapeia a 2h|8h|48h de SLA: critico|urgente|normal
     */
    public string $replacementUrgency = 'normal';

    // --- Etapa 4: materiais ---
    public string $materialSearch = '';

    public $selectedMaterialId = null;

    public int $materialQuantity = 1;

    // --- Etapa 5: assinatura ---
    public ?string $technicianSignature = null;

    public ?string $clientSignature = null;

    /**
     * idle | saving | saved | error -- alimenta o indicador no rodape. 'error'
     * e' o caso de campo: gravacao falhou (rede caiu no meio), o tecnico ve o
     * aviso e toca pra tentar de novo, sem perder o que digitou.
     */
    public string $saveState = 'idle';

    public function mount(MaintenanceOrder $maintenanceOrder): void
    {
        Gate::authorize('update', $maintenanceOrder);

        $this->maintenanceOrder = $maintenanceOrder;

        $this->horimetroEntry = $maintenanceOrder->horimetro_entry !== null
            ? (string) $maintenanceOrder->horimetro_entry
            : null;
        $this->fuelLevel = $maintenanceOrder->fuel_level !== null
            ? (string) $maintenanceOrder->fuel_level
            : null;

        $this->step = $this->clampStep($this->step);
    }

    /**
     * ?step=99 ou ?step=-1 na URL nao pode quebrar a tela nem pular etapa.
     */
    public function updatedStep(): void
    {
        $this->step = $this->clampStep($this->step);
    }

    private function clampStep(mixed $step): int
    {
        return max(1, min(self::TOTAL_STEPS, (int) $step));
    }

    public function getTotalStepsProperty(): int
    {
        return self::TOTAL_STEPS;
    }

    public function getStepLabelProperty(): string
    {
        return match ($this->step) {
            1 => 'Equipamento',
            2 => 'Vistoria / Checklist',
            3 => 'Avarias e observações',
            4 => 'Materiais',
            default => 'Assinatura e envio',
        };
    }

    public function getStepProgressProperty(): int
    {
        return (int) round($this->step / self::TOTAL_STEPS * 100);
    }

    /**
     * Horimetro da ultima leitura conhecida do ativo -- mesma fonte que o form
     * desktop usa pra preencher "Hor. Anterior" (MaintenanceOrderResource,
     * afterStateUpdated do asset_id).
     */
    public function getHorimetroAnteriorProperty(): ?float
    {
        $last = $this->maintenanceOrder->asset?->last_horimetro;

        return $last !== null ? (float) $last : null;
    }

    /**
     * Aviso, nao trava: leitura menor que a anterior costuma ser erro de
     * digitacao, mas troca de painel/horimetro zerado acontece de verdade. O
     * hook em MaintenanceOrder::booted() ja ignora leitura menor pra nao
     * retroceder o horimetro do ativo, entao aqui e' so' pro tecnico conferir.
     */
    public function getHorimetroSuspeitoProperty(): bool
    {
        $anterior = $this->horimetroAnterior;

        return $anterior !== null
            && is_numeric($this->horimetroEntry)
            && (float) $this->horimetroEntry < $anterior;
    }

    /**
     * Historico recente do ativo -- contexto secundario ("ja mexeram nisso
     * semana passada?"), nunca no caminho da acao principal.
     */
    public function getRecentOrdersProperty()
    {
        if (! $this->maintenanceOrder->asset_id) {
            return collect();
        }

        return MaintenanceOrder::query()
            ->where('asset_id', $this->maintenanceOrder->asset_id)
            ->whereKeyNot($this->maintenanceOrder->getKey())
            ->latest('created_at')
            ->limit(3)
            ->get(['id', 'os_number', 'maintenance_type', 'status', 'created_at']);
    }

    public function getCanStartProperty(): bool
    {
        return in_array($this->maintenanceOrder->status, self::STARTABLE_STATUSES, true);
    }

    public function getMaintenanceTypeProperty(): ?string
    {
        return $this->maintenanceOrder->maintenance_type;
    }

    public function getMaintenanceTypeLabelProperty(): string
    {
        return match ($this->maintenanceOrder->maintenance_type) {
            MaintenanceOrder::TYPE_PREVENTIVE => 'PREVENTIVA',
            MaintenanceOrder::TYPE_CORRECTIVE => 'CORRETIVA',
            MaintenanceOrder::TYPE_AVARIA => 'AVARIA',
            MaintenanceOrder::TYPE_EMERGENCIA => 'EMERGÊNCIA',
            default => 'SERVIÇO',
        };
    }

    public function getMaintenanceTypeColorProperty(): string
    {
        return match ($this->maintenanceOrder->maintenance_type) {
            MaintenanceOrder::TYPE_PREVENTIVE => 'emerald',
            MaintenanceOrder::TYPE_CORRECTIVE => 'amber',
            MaintenanceOrder::TYPE_AVARIA => 'red',
            MaintenanceOrder::TYPE_EMERGENCIA => 'red',
            default => 'zinc',
        };
    }

    public function getSlaRemainingProperty(): ?array
    {
        if (! $this->maintenanceOrder->sla_target_minutes) {
            return null;
        }

        $minutesElapsed = $this->maintenanceOrder->created_at->diffInMinutes(now());
        $minutesRemaining = $this->maintenanceOrder->sla_target_minutes - $minutesElapsed;

        if ($minutesRemaining <= 0) {
            return ['hours' => 0, 'minutes' => 0, 'exceeded' => true];
        }

        $hours = (int) floor($minutesRemaining / 60);
        $minutes = $minutesRemaining % 60;

        return ['hours' => $hours, 'minutes' => $minutes, 'exceeded' => false];
    }

    public function getSlaColorProperty(): string
    {
        return $this->maintenanceOrder->slaColor() ?? 'zinc';
    }

    public function getPrimaryLabelProperty(): string
    {
        if ($this->step === 1 && $this->canStart) {
            return $this->maintenanceOrder->started_at ? 'RETOMAR E CONTINUAR' : 'INICIAR SERVIÇO';
        }

        if ($this->step === 5) {
            return 'ENVIAR O.S.';
        }

        return 'CONTINUAR';
    }

    public function next(): void
    {
        if (! $this->persistCurrentStep()) {
            return;
        }

        // Etapa 1 acumula o "Iniciar Servico": em campo o tecnico nao deveria
        // ter que lembrar de apertar um botao separado antes de trabalhar.
        if ($this->step === 1 && $this->canStart) {
            $this->startService();
        }

        // Etapa 5 eh a ultima e conclui a O.S. -- nao pula pra etapa 6.
        if ($this->step === 5) {
            $this->completeOrder();

            return;
        }

        $this->step = $this->clampStep($this->step + 1);
    }

    public function back(): void
    {
        // Nao re-salva: a etapa que esta sendo abandonada ja persistiu quando o
        // tecnico avancou, entao voltar nunca perde dado nem re-valida.
        if ($this->step > 1) {
            $this->step = $this->clampStep($this->step - 1);

            return;
        }

        $this->redirectRoute(
            'filament.admin.resources.maintenance-orders.edit',
            ['record' => $this->maintenanceOrder],
            navigate: false
        );
    }

    /**
     * Nova tentativa depois de falha de gravacao, sem avancar de etapa.
     */
    public function retry(): void
    {
        $this->persistCurrentStep();
    }

    private function startService(): void
    {
        $oldStatus = $this->maintenanceOrder->status;

        // Só 'status' + logStatusChange, igual a action 'iniciar' do
        // EditMaintenanceOrder: o timer (started_at/last_timer_start/
        // total_time_seconds) e' responsabilidade unica do hook em
        // MaintenanceOrder::booted(). Recalcular aqui tambem ja foi frágil
        // antes -- ver o comentario em EditMaintenanceOrder::getHeaderActions().
        $this->maintenanceOrder->update(['status' => 'Em Andamento']);
        $this->maintenanceOrder->logStatusChange('Em Andamento', $oldStatus);
    }

    private function persistCurrentStep(): bool
    {
        $this->saveState = 'saving';

        try {
            match ($this->step) {
                1 => $this->persistEquipment(),
                2 => $this->persistChecklist(),
                3 => $this->persistDamages(),
                4 => $this->persistMaterials(),
                5 => $this->persistSignature(),
                default => null,
            };
        } catch (ValidationException $e) {
            // Erro de preenchimento nao e' falha de conexao -- deixa o Livewire
            // mostrar a mensagem no campo e nao acende o aviso de "sem conexao".
            $this->saveState = 'idle';

            throw $e;
        } catch (\Throwable $e) {
            report($e);
            $this->saveState = 'error';

            return false;
        }

        $this->saveState = 'saved';

        return true;
    }

    private function persistEquipment(): void
    {
        $this->validate([
            'horimetroEntry' => ['required', 'numeric', 'min:0'],
            'fuelLevel' => ['nullable', 'in:0,25,50,75,100'],
        ], attributes: [
            'horimetroEntry' => 'horímetro atual',
            'fuelLevel' => 'nível de combustível',
        ]);

        // horimetro_entry alimenta Asset.horimetro_atual/last_horimetro via
        // hook em MaintenanceOrder::booted() -- nao replicar isso aqui.
        $this->maintenanceOrder->update([
            'horimetro_entry' => $this->horimetroEntry,
            'fuel_level' => $this->fuelLevel,
        ]);
    }

    private function persistChecklist(): void
    {
        // Etapa 2 (checklist) nao requer persistencia especial -- cada item
        // salva seu proprio estado (status/observacao/foto) quando editado via
        // saveItemDetails(). Apenas garante que um item expandido nao foi deixado
        // em estado incompleto.
        if ($this->expandedItemId) {
            $this->collapse();
        }
    }

    private function persistDamages(): void
    {
        // Etapa 3: salva descrição e notas técnicas da O.S., e opcionalmente
        // cria uma avaria formal. Fotos sao' anexadas via media library.
        $this->maintenanceOrder->update([
            'description' => $this->damageDescription,
            'technical_notes' => $this->technicalNotes,
        ]);

        // Se fotos foram selecionadas, anexa à O.S. via media library
        if ($this->damagePhotoBefore) {
            $this->maintenanceOrder->addMedia($this->damagePhotoBefore->getRealPath())
                ->usingFileName('damage_before_'.now()->timestamp.'.'.$this->damagePhotoBefore->getClientOriginalExtension())
                ->toMediaCollection('damage_photos_before');
        }

        if ($this->damagePhotoAfter) {
            $this->maintenanceOrder->addMedia($this->damagePhotoAfter->getRealPath())
                ->usingFileName('damage_after_'.now()->timestamp.'.'.$this->damagePhotoAfter->getClientOriginalExtension())
                ->toMediaCollection('damage_photos_after');
        }

        // Se Preventiva + dano encontrado: cria solicitação de troca
        if ($this->shouldRegisterDamage &&
            $this->maintenanceOrder->maintenance_type === MaintenanceOrder::TYPE_PREVENTIVE &&
            filled($this->damageDescription)) {
            $this->createEquipmentReplacement();
        }

        // Limpa uploads temporários
        $this->damagePhotoBefore = null;
        $this->damagePhotoAfter = null;
    }

    private function createEquipmentReplacement(): void
    {
        // Cria EquipmentReplacement vinculada à O.S. com urgência mapeada para SLA
        $urgencyToSla = [
            'critico' => 120,    // 2 horas
            'urgente' => 480,    // 8 horas
            'normal' => 1440,    // 48 horas
        ];

        EquipmentReplacement::create([
            'tenant_id' => $this->maintenanceOrder->tenant_id,
            'maintenance_order_id' => $this->maintenanceOrder->id,
            'original_asset_id' => $this->maintenanceOrder->asset_id,
            'requested_by_user_id' => auth()->id(),
            'urgency' => $this->replacementUrgency,
            'reason' => $this->damageDescription,
            'status' => EquipmentReplacement::STATUS_SOLICITADO,
        ]);

        // Atualizar MaintenanceOrder com SLA
        $this->maintenanceOrder->update([
            'sla_target_minutes' => $urgencyToSla[$this->replacementUrgency] ?? 1440,
        ]);
    }

    public function clearDamagePhotoBefore(): void
    {
        $this->damagePhotoBefore = null;
    }

    public function clearDamagePhotoAfter(): void
    {
        $this->damagePhotoAfter = null;
    }

    // --- Etapa 4: materiais ---

    /**
     * Busca materiais pelo termo digitado (nome ou código).
     */
    public function getMaterialSearchResultsProperty()
    {
        if (blank($this->materialSearch)) {
            return collect();
        }

        return Material::query()
            ->where('tenant_id', $this->maintenanceOrder->tenant_id)
            ->where(function ($q) {
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$this->materialSearch}%"])
                    ->orWhereRaw('LOWER(sku) LIKE ?', ["%{$this->materialSearch}%"]);
            })
            ->limit(10)
            ->get();
    }

    /**
     * Material selecionado na busca.
     */
    public function getSelectedMaterialProperty()
    {
        if (! $this->selectedMaterialId) {
            return null;
        }

        return Material::find($this->selectedMaterialId);
    }

    /**
     * Materiais já aplicados à O.S.
     */
    public function getAppliedMaterialsProperty()
    {
        return $this->maintenanceOrder->materials()
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Custo total de materiais (já é calculado pelo observer, mas mostramos aqui também).
     */
    public function getMaterialCostTotalProperty(): float
    {
        return (float) $this->appliedMaterials->sum(function ($m) {
            return $m->quantity * $m->unit_price;
        });
    }

    /**
     * Custo total da O.S. (material + mão de obra + logística).
     */
    public function getOrderTotalCostProperty(): float
    {
        return (float) ($this->maintenanceOrder->total_order_cost ?? 0);
    }

    public function selectMaterial($materialId): void
    {
        $this->selectedMaterialId = $materialId;
        $this->materialSearch = '';
        $this->materialQuantity = 1;
    }

    public function clearSelectedMaterial(): void
    {
        $this->selectedMaterialId = null;
        $this->materialQuantity = 1;
    }

    public function addMaterialToOrder(): void
    {
        $this->validate([
            'materialQuantity' => ['required', 'integer', 'min:1'],
        ], attributes: [
            'materialQuantity' => 'quantidade',
        ]);

        if (! $this->selectedMaterialId) {
            return;
        }

        $material = Material::find($this->selectedMaterialId);
        if (! $material) {
            return;
        }

        // Adiciona material à O.S. -- o observer calcula o custo automaticamente
        MaintenanceOrderMaterial::create([
            'tenant_id' => $this->maintenanceOrder->tenant_id,
            'maintenance_order_id' => $this->maintenanceOrder->id,
            'material_id' => $material->id,
            'quantity' => $this->materialQuantity,
            'unit_price' => $material->unit_price,
        ]);

        $this->clearSelectedMaterial();
        $this->materialSearch = '';
    }

    public function removeMaterial(string $materialId): void
    {
        MaintenanceOrderMaterial::whereKey($materialId)->delete();
    }

    private function persistMaterials(): void
    {
        // Etapa 4: materiais já foram salvos individualmente quando adicionados,
        // não precisa fazer nada aqui. O observer cuida do cálculo de custos.
    }

    // --- Etapa 5: assinatura ---

    public function clearSignature(): void
    {
        $this->technicianSignature = null;
    }

    private function persistSignature(): void
    {
        // Etapa 5: a assinatura pode ser capturada via Alpine/JavaScript,
        // mas aqui apenas validamos que a assinatura foi fornecida antes de
        // permitir finalizar. O completeOrder() sera' chamado por next().
    }

    public function saveSignatures(string $technicianSignature, string $clientSignature): void
    {
        $this->maintenanceOrder->update([
            'technician_signature' => $technicianSignature,
            'client_signature' => $clientSignature,
        ]);

        $this->technicianSignature = $technicianSignature;
        $this->clientSignature = $clientSignature;
        $this->saveState = 'saved';
    }

    private function completeOrder(): void
    {
        $oldStatus = $this->maintenanceOrder->status;

        // Replicar exatamente o padrao da action 'concluir' em
        // EditMaintenanceOrder::getHeaderActions(): so' 'status' +
        // logStatusChange, nunca tocar em started_at/finished_at/
        // total_time_seconds -- o timer eh responsabilidade unica do hook
        // MaintenanceOrder::booted().
        $this->maintenanceOrder->update(['status' => 'Concluída']);
        $this->maintenanceOrder->logStatusChange('Concluída', $oldStatus);

        // Redireciona pro edit da O.S. como confirmacao de conclusao.
        $this->redirectRoute(
            'filament.admin.resources.maintenance-orders.edit',
            ['record' => $this->maintenanceOrder],
            navigate: false
        );
    }

    public function render()
    {
        return view('livewire.maintenance-order-field-wizard');
    }
}
