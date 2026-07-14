<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class SolicitacaoLocacao extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasSaaSMetadata;

    protected static ?string $saasFeatureKey = 'tabela_solicitacao_locacao';

    protected static ?string $saasPermissionSlug = 'solicitacao_locacao';

    protected static ?string $saasModuleLabel = 'Solicitacoes de Locacao';

    use HasUuids;

    protected $table = 'solicitacoes_locacao';

    /**
     * Campos permitidos para atribuição em massa.
     */
    protected $fillable = [
        'tenant_id',
        'user_id',
        'customer_id',
        'contract_id',
        'category_id',
        'asset_id',
        'purpose',
        'data_saida_prevista',
        'status_comercial',
        'cancellation_reason_id', // Novo campo para rastreabilidade de perdas
        'observations',
        'logistics_cost',
    ];

    /**
     * Define a conversão de tipos para campos específicos.
     */
    protected function casts(): array
    {
        return [
            'data_saida_prevista' => 'date',
            'logistics_cost' => 'decimal:2',
        ];
    }

    /**
     * Duas travas de negocio, validadas aqui (nao so no form do Resource)
     * pra valerem em qualquer via de escrita:
     *
     * 1. So pode existir solicitacao pra cliente com contrato ja fechado
     *    (contract_id preenchido) OU como reserva com prazo determinado
     *    (data_saida_prevista preenchida) -- cliente potencial sem contrato
     *    nao pode reservar por prazo indeterminado.
     * 2. So pode fechar o contrato (status_comercial=contrato_fechado) se
     *    o(s) ativo(s) envolvido(s) estiver(em) Disponivel -- nao deixa
     *    "liberar" um equipamento que ainda esta em manutencao/operando.
     */
    protected static function booted(): void
    {
        static::saving(function (SolicitacaoLocacao $solicitacao) {
            if (blank($solicitacao->contract_id) && blank($solicitacao->data_saida_prevista)) {
                throw ValidationException::withMessages([
                    'data_saida_prevista' => 'Informe o prazo da reserva (cliente ainda sem contrato fechado) ou vincule um contrato existente.',
                ]);
            }

            if ($solicitacao->isDirty('status_comercial') && $solicitacao->status_comercial === 'contrato_fechado') {
                $unavailable = collect();

                if ($solicitacao->asset_id) {
                    $asset = Asset::find($solicitacao->asset_id);
                    if ($asset && $asset->status !== Asset::STATUS_DISPONIVEL) {
                        $unavailable->push($asset->name);
                    }
                }

                if ($solicitacao->exists) {
                    foreach ($solicitacao->assets as $asset) {
                        if ($asset->status !== Asset::STATUS_DISPONIVEL) {
                            $unavailable->push($asset->name);
                        }
                    }
                }

                if ($unavailable->isNotEmpty()) {
                    throw ValidationException::withMessages([
                        'status_comercial' => 'Não é possível fechar o contrato: '.$unavailable->unique()->implode(', ').' ainda não está disponível (verifique o status no pátio).',
                    ]);
                }
            }
        });
    }

    /**
     * IDs de Asset com uma Solicitacao de Locacao urgente aguardando (status
     * "reserva de manutencao") -- mesma consulta que ja alimentava o banner
     * "Locacao Urgente" no Kanban (MaintenanceKanban::getUrgentAssetIds()),
     * centralizada aqui pra tambem alimentar o aviso/bloqueio na criacao de
     * O.S. sem duplicar a query em dois lugares.
     *
     * @return array<int, string>
     */
    public static function assetIdsComReservaUrgente(string $tenantId): array
    {
        return static::where('tenant_id', $tenantId)
            ->where('status_comercial', 'reserva_manutencao')
            ->whereNotNull('asset_id')
            ->pluck('asset_id')
            ->all();
    }

    /**
     * Autoriza criar OS num ativo com reserva urgente: a senha precisa bater
     * com a de ALGUM usuario do tenant com a role "Supervisor de Manutenção"
     * (mesmo padrao de confirmacao usado em PatioAprovacoes::approve()).
     * Extraido como metodo estatico puro (sem depender do Form/Livewire da
     * pagina) pra ser testavel isoladamente.
     */
    public static function autorizaOverrideReserva(string $tenantId, ?string $password): bool
    {
        if (! $password) {
            return false;
        }

        $supervisorRole = Role::where('name', 'Supervisor de Manutenção')
            ->where('guard_name', 'web')
            ->where('tenant_id', $tenantId)
            ->first();

        if (! $supervisorRole) {
            return false;
        }

        return User::role($supervisorRole)
            ->where('tenant_id', $tenantId)
            ->get()
            ->contains(fn (User $supervisor) => Hash::check($password, $supervisor->password));
    }

    // --- RELACIONAMENTOS ---

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'category_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }

    public function customer(): BelongsTo
    {
        // Mantido 'customer' como nome do método para compatibilidade com o Resource,
        // mas apontando corretamente para o modelo Client.
        return $this->belongsTo(Client::class, 'customer_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'contract_id');
    }

    /**
     * Relacionamento para análise estatística de motivos de cancelamento.
     */
    public function cancellationReason(): BelongsTo
    {
        return $this->belongsTo(CancellationReason::class, 'cancellation_reason_id');
    }

    /**
     * Ativos do "combo" desta solicitacao -- aditivo ao asset_id legado
     * (que continua servindo pra solicitacao de um unico equipamento
     * especifico). Uma solicitacao "combo"/lote usa so este relacionamento.
     */
    public function assets(): BelongsToMany
    {
        return $this->belongsToMany(Asset::class, 'solicitacao_locacao_assets');
    }

    /**
     * Todo o combo esta pronto pra embarque simultaneo? (todos os ativos
     * vinculados estao com status Disponivel agora).
     */
    public function isKitComplete(): bool
    {
        $assets = $this->assets;

        if ($assets->isEmpty()) {
            return false;
        }

        return $assets->every(fn (Asset $asset) => $asset->status === Asset::STATUS_DISPONIVEL);
    }
}
