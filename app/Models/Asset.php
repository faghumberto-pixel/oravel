<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\URL;

class Asset extends Model
{
    use HasUuids, SoftDeletes, BelongsToTenant;

    protected $keyType = 'string';
    public $incrementing = false;

    // Constantes de Status para padronização
    const STATUS_DISPONIVEL = 'disponivel';
    const STATUS_LOCADO     = 'locado';
    const STATUS_MANUTENCAO = 'manutencao';
    const STATUS_OPERANDO   = 'operando';

    protected $fillable = [
        'name', 
        'patrimonio', 
        'asset_tag',      
        'serial_number',   
        'asset_category', 
        'criticality_level', 
        'status', 
        'tenant_id', 
        'description', 
        'checklist',
        'horimetro_inicial', // Ponto zero ROI
        'last_horimetro'     // Atualizado via OS
    ];

    protected $casts = [
        'checklist' => 'array',
        'horimetro_inicial' => 'decimal:2',
        'last_horimetro' => 'decimal:2',
    ];

    /**
     * ACESSOR PARA QR CODE
     */
    public function getQrCodeUrlAttribute(): string
    {
        return URL::to("/admin/assets/{$this->id}");
    }

    /**
     * RELACIONAMENTOS
     */

    public function tenant(): BelongsTo 
    { 
        return $this->belongsTo(Tenant::class, 'tenant_id'); 
    }

    public function maintenanceOrders(): HasMany
    {
        return $this->hasMany(MaintenanceOrder::class, 'asset_id')->latest();
    }

    public function criticalityLevel(): BelongsTo
    {
        return $this->belongsTo(CriticalityLevel::class, 'criticality_level');
    }

    /**
     * RELACIONAMENTO DE LOGS
     * Redirecionado para o seu AssetLog original para evitar erros de Trait faltando
     */
    public function activities(): HasMany
    {
        return $this->hasMany(AssetLog::class, 'asset_id')->latest();
    }

    public function logs(): HasMany
    {
        return $this->hasMany(AssetLog::class, 'asset_id')->latest();
    }

    /**
     * SCOPES
     */

    public function scopeWithChecklistIssues(Builder $query): Builder
    {
        return $query->whereJsonContains('checklist', [['status' => false]]);
    }

    /**
     * MÉTODOS ESTÁTICOS
     */

    public static function getCriticalityLevels(): array
    {
        try {
            return \App\Models\CriticalityLevel::pluck('name', 'id')->toArray();
        } catch (\Exception $e) {
            return ['baixa' => 'Baixa', 'media' => 'Média', 'alta' => 'Alta'];
        }
    }

    public static function getCategories(): array
    {
        return [
            '1. Acesso e Segurança' => [
                'andaimes' => 'Andaimes (Tubulares, fachadeiros, multidirecionais)',
                'plataformas' => 'Plataformas Elevatórias',
                'balancins' => 'Balancins (Manuais ou elétricos)',
                'escoras' => 'Escoras Metálicas',
            ],
            '2. Concretagem e Estrutura' => [
                'betoneiras' => 'Betoneiras',
                'vibradores' => 'Vibradores de Imersão',
                'guinchos' => 'Guinchos de Coluna',
                'reguas' => 'Réguas Vibratórias',
            ],
            '3. Furação e Demolição' => [
                'marteletes' => 'Marteletes (Rompedores e perfuradores)',
                'furadeiras' => 'Furadeiras',
                'rompedores' => 'Rompedores Pneumáticos',
            ],
            '4. Terraplenagem e Compactação' => [
                'compactadores' => 'Compactadores de Solo (Sapo)',
                'mini_maquinas' => 'Mini-escavadeiras e Carregadeiras',
                'rolos' => 'Rolos Compactadores',
            ],
            '5. Corte e Acabamento' => [
                'cortadoras' => 'Cortadoras de Piso',
                'serras' => 'Serras (Mármore, circular, tico-tico)',
                'lixadeiras' => 'Lixadeiras e Esmerilhadeiras',
                'alisadoras' => 'Alisadoras de Concreto',
            ],
            '6. Apoio Logístico' => [
                'geradores' => 'Geradores de Energia',
                'compressores' => 'Compressores de Ar',
                'containers' => 'Contêineres e Banheiros',
                'bombas' => 'Bombas d\'Água',
            ],
        ];
    }

    // CORREÇÃO: Adicionado "?" antes de string para aceitar null
    public static function getDefaultChecklist(?string $category): array
    {
        $lista = [];

        // CORREÇÃO: Fallback caso a categoria seja null ou vazia
        $category = $category ?? 'default';

        $lista[] = ['item' => '--- 1. DOCUMENTAÇÃO E ADMINISTRATIVO ---', 'status' => true];
        $lista[] = ['item' => 'Contrato/Pedido de Compra validado', 'status' => true];
        $lista[] = ['item' => 'Operador habilitado (CNH/ASO/NRs)', 'status' => true];
        $lista[] = ['item' => 'Seguro e Manuais presentes no equipamento', 'status' => true];

        $lista[] = ['item' => '--- 2. MOBILIZAÇÃO (ENTRADA) ---', 'status' => true];
        $lista[] = ['item' => 'Inspeção visual (vazamentos/trincas/pintura)', 'status' => true];
        $lista[] = ['item' => 'Níveis de fluidos (Óleo/Hidráulico/Arrefecimento)', 'status' => true];
        $lista[] = ['item' => 'Pneus/Lagartas (Calibragem/Cortes/Desgaste)', 'status' => true];
        $lista[] = ['item' => 'Botão de emergência/Alarmes/Giroflex', 'status' => true];
        $lista[] = ['item' => 'Extintor de incêndio (Carga e Validade)', 'status' => true];

        $lista[] = ['item' => '--- 3. ESPECÍFICOS DA CATEGORIA ---', 'status' => true];
        
        $especificos = match ($category) {
            'geradores' => [
                ['item' => 'Teste de aterramento e estado dos cabos', 'status' => true],
                ['item' => 'Painel de transferência e conexões', 'status' => true],
            ],
            'compressores' => [
                ['item' => 'Inspeção do reservatório (Vaso/NR-13)', 'status' => true],
                ['item' => 'Mangueiras e conexões cam-lock com travas', 'status' => true],
            ],
            'plataformas' => [
                ['item' => 'Teste de inclinação e sensor de carga', 'status' => true],
                ['item' => 'Pontos de ancoragem para cinto de segurança', 'status' => true],
            ],
            'guindastes' => [
                ['item' => 'Gráfico de carga visível e legível', 'status' => true],
                ['item' => 'Inspeção de cabos de aço e patolas', 'status' => true],
            ],
            'marteletes', 'rompedores' => [
                ['item' => 'Estado da ponteira e lubrificação pneumática', 'status' => true],
            ],
            'betoneiras' => [
                ['item' => 'Condição da cremalheira, pinhão e correia', 'status' => true],
            ],
            default => [
                ['item' => 'Integridade de cabos e plugs elétricos', 'status' => true],
                ['item' => 'Funcionamento do botão Liga/Desliga', 'status' => true],
            ],
        };
        $lista = array_merge($lista, $especificos);

        $lista[] = ['item' => '--- 4. DESMOBILIZAÇÃO (SAÍDA) ---', 'status' => true];
        $lista[] = ['item' => 'Limpeza geral (Equipamento livre de detritos)', 'status' => true];
        $lista[] = ['item' => 'Conferência de avarias (Comparar com fotos)', 'status' => true];
        $lista[] = ['item' => 'Acessórios (Chaves/Cabos/Controles/Ponteiras)', 'status' => true];
        $lista[] = ['item' => 'Protocolo assinado pelo transportador', 'status' => true];

        return $lista;
    }
}