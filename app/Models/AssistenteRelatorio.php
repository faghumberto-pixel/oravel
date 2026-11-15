<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Support\Str;

/**
 * Foto de uma consulta do Assistente, aberta por link publico (token longo +
 * validade). Criada no momento da resposta; o link de WhatsApp aponta pra ca.
 * A pagina publica le SEM o escopo de tenant (o destinatario nao tem login).
 */
class AssistenteRelatorio extends Model
{
    use BelongsToTenant;
    use HasUuids;
    use Prunable;

    public const VALIDADE_DIAS = 7;

    protected $table = 'assistente_relatorios';

    protected $fillable = ['token', 'tenant_id', 'user_id', 'area', 'titulo', 'resumo', 'colunas', 'linhas', 'total_linhas', 'expires_at'];

    protected function casts(): array
    {
        return ['colunas' => 'array', 'linhas' => 'array', 'expires_at' => 'datetime'];
    }

    /**
     * @param  array<int, array<string, mixed>>  $linhas
     */
    public static function criar(string $area, string $titulo, ?string $resumo, array $linhas, ?string $userId = null): self
    {
        return static::create([
            'token' => Str::random(48),
            'user_id' => $userId,
            'area' => $area,
            'titulo' => Str::limit($titulo, 250, '…'),
            'resumo' => $resumo,
            'colunas' => array_keys($linhas[0] ?? []),
            'linhas' => array_map('array_values', $linhas),
            'total_linhas' => count($linhas),
            'expires_at' => now()->addDays(self::VALIDADE_DIAS),
        ]);
    }

    /** Busca pelo token ignorando o escopo de tenant e a validade vencida. */
    public static function porToken(string $token): ?self
    {
        return static::withoutGlobalScopes()
            ->where('token', $token)
            ->where('expires_at', '>', now())
            ->first();
    }

    public function url(): string
    {
        return route('assistente.relatorio', $this->token);
    }

    public function prunable(): Builder
    {
        return static::withoutGlobalScopes()->where('expires_at', '<', now()->subDay());
    }
}
