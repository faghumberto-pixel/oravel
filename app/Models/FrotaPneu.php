<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/** Pneu individual da frota: vida, sulco, posição no veículo, km rodado e custo por km. */
class FrotaPneu extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;

    protected static ?string $saasFeatureKey = 'tabela_frota_pneus';

    protected static ?string $saasPermissionSlug = 'pneu_frota';

    protected static ?string $saasModuleLabel = 'Pneus da Frota';

    protected $table = 'frota_pneus';

    public const VIDA_NOVO = 'novo';

    public const VIDA_RECAPADO_1 = 'recapado_1';

    public const VIDA_RECAPADO_2 = 'recapado_2';

    public const VIDA_RECAPADO_3 = 'recapado_3';

    public const ESTOQUE = 'estoque';

    public const MONTADO = 'montado';

    public const EM_RECAPAGEM = 'em_recapagem';

    public const SUCATEADO = 'sucateado';

    /** Limites dos alertas (constantes simples por enquanto). */
    public const SULCO_ATENCAO_MM = 3.0;

    public const SULCO_CRITICO_MM = 1.6;

    public const IDADE_MAXIMA_ANOS = 5;

    public const RODIZIO_A_CADA_KM = 10000;

    /** Padrões também na instância recém-criada (o banco já tem o mesmo padrão). */
    protected $attributes = ['situacao' => self::ESTOQUE, 'vida' => self::VIDA_NOVO];

    protected $fillable = [
        'tenant_id', 'numero_fogo', 'marca', 'modelo', 'medida', 'dot', 'vida', 'sulco_inicial_mm', 'custo',
        'pressao_min_psi', 'pressao_max_psi', 'situacao',
    ];

    protected $casts = ['sulco_inicial_mm' => 'decimal:1', 'custo' => 'decimal:2', 'pressao_min_psi' => 'decimal:1', 'pressao_max_psi' => 'decimal:1'];

    /** @return array<string, string> */
    public static function vidaLabels(): array
    {
        return [
            self::VIDA_NOVO => 'Novo', self::VIDA_RECAPADO_1 => '1ª recapagem',
            self::VIDA_RECAPADO_2 => '2ª recapagem', self::VIDA_RECAPADO_3 => '3ª recapagem',
        ];
    }

    /** @return array<string, string> */
    public static function situacaoLabels(): array
    {
        return [self::ESTOQUE => 'Em estoque', self::MONTADO => 'Montado', self::EM_RECAPAGEM => 'Em recapagem', self::SUCATEADO => 'Sucateado'];
    }

    /** @return array<string, string> */
    public static function motivoRemocaoLabels(): array
    {
        return [
            'rodizio' => 'Rodízio', 'desgaste' => 'Desgaste', 'furo' => 'Furo / avaria', 'recapagem' => 'Enviado para recapagem',
            'descarte' => 'Descarte (sucata)', 'garantia' => 'Garantia', 'outro' => 'Outro',
        ];
    }

    public function instalacoes(): MorphMany
    {
        return $this->morphMany(FrotaInstalacaoComponente::class, 'componente')->orderByDesc('instalado_em');
    }

    public function instalacaoAberta(): MorphOne
    {
        return $this->morphOne(FrotaInstalacaoComponente::class, 'componente')->whereNull('removido_em');
    }

    public function inspecoes(): HasMany
    {
        return $this->hasMany(FrotaInspecaoPneu::class, 'pneu_id')->orderByDesc('inspecionado_em');
    }

    /** Km rodado: soma dos intervalos de todas as instalações (a aberta vai até o odômetro atual do veículo). */
    public function kmRodado(): int
    {
        $instalacoes = $this->relationLoaded('instalacoes') ? $this->instalacoes : $this->instalacoes()->with('ativo:id,odometro_atual')->get();

        return (int) $instalacoes->sum(function (FrotaInstalacaoComponente $i) {
            $fim = $i->removido_em ? $i->odometro_remocao : (int) floor((float) $i->ativo?->odometro_atual);

            return max(0, (int) $fim - (int) $i->odometro_instalacao);
        });
    }

    /** Custo por km = custo ÷ km rodado (null enquanto não rodou ou sem custo). */
    public function custoPorKm(): ?float
    {
        $km = $this->kmRodado();

        return $this->custo !== null && $km > 0 ? round((float) $this->custo / $km, 4) : null;
    }

    public function ultimaInspecao(): ?FrotaInspecaoPneu
    {
        return $this->relationLoaded('inspecoes') ? $this->inspecoes->first() : $this->inspecoes()->first();
    }

    /** Idade em anos pelo DOT (semana + ano: 3524 = semana 35 de 2024); null se não informado/ inválido. */
    public function idadeAnos(): ?float
    {
        if (! $this->dot || ! preg_match('/^(0[1-9]|[1-4]\d|5[0-3])(\d{2})$/', $this->dot, $m)) {
            return null;
        }

        $fabricado = now()->setISODate(2000 + (int) $m[2], (int) $m[1], 1);

        return round($fabricado->diffInDays(now(), false) / 365.25, 1);
    }

    /**
     * Alertas calculados ao vivo (sem tabela).
     *
     * @return array<int, array{tipo: string, gravidade: string, mensagem: string}>
     */
    public function alertas(): array
    {
        if ($this->situacao === self::SUCATEADO) {
            return [];
        }

        $alertas = [];
        $ultima = $this->ultimaInspecao();

        if ($ultima?->sulco_mm !== null) {
            $sulco = (float) $ultima->sulco_mm;
            if ($sulco <= self::SULCO_CRITICO_MM) {
                $alertas[] = ['tipo' => 'sulco', 'gravidade' => 'critica', 'mensagem' => "Sulco em {$sulco} mm (limite legal 1,6 mm): trocar"];
            } elseif ($sulco <= self::SULCO_ATENCAO_MM) {
                $alertas[] = ['tipo' => 'sulco', 'gravidade' => 'atencao', 'mensagem' => "Sulco em {$sulco} mm: perto do limite"];
            }
        }

        $idade = $this->idadeAnos();
        if ($idade !== null && $idade > self::IDADE_MAXIMA_ANOS) {
            $alertas[] = ['tipo' => 'idade', 'gravidade' => 'atencao', 'mensagem' => "Pneu com {$idade} anos (mais de ".self::IDADE_MAXIMA_ANOS.' anos pelo DOT)'];
        }

        if ($ultima?->pressao_psi !== null && $this->pressao_min_psi !== null && $this->pressao_max_psi !== null) {
            $psi = (float) $ultima->pressao_psi;
            if ($psi < (float) $this->pressao_min_psi || $psi > (float) $this->pressao_max_psi) {
                $alertas[] = ['tipo' => 'pressao', 'gravidade' => 'atencao', 'mensagem' => "Pressão {$psi} psi fora da faixa ({$this->pressao_min_psi} a {$this->pressao_max_psi})"];
            }
        }

        $aberta = null;
        if ($this->situacao === self::MONTADO) {
            $aberta = $this->relationLoaded('instalacoes')
                ? $this->instalacoes->first(fn (FrotaInstalacaoComponente $i) => $i->removido_em === null)
                : $this->instalacaoAberta()->with('ativo:id,odometro_atual')->first();
        }
        if ($aberta && $aberta->posicao !== 'ESTEPE') {
            $rodado = max(0, (int) floor((float) $aberta->ativo?->odometro_atual) - (int) $aberta->odometro_instalacao);
            if ($rodado >= self::RODIZIO_A_CADA_KM) {
                $alertas[] = ['tipo' => 'rodizio', 'gravidade' => 'atencao', 'mensagem' => 'Rodízio vencido: '.number_format($rodado, 0, ',', '.').' km na mesma posição'];
            }
        }

        return $alertas;
    }
}
