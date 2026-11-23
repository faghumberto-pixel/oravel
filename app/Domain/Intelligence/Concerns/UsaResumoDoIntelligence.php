<?php

namespace App\Domain\Intelligence\Concerns;

use App\Domain\Intelligence\Models\IntelligenceSnapshot;
use App\Domain\Intelligence\Services\EquipamentosService;
use App\Domain\Intelligence\Services\EvolucaoService;
use App\Domain\Intelligence\Services\InsightsDeEquipamentos;
use App\Filament\Pages\IntelligenceDashboard;
use App\Filament\Resources\IntelligenceImportResource;
use App\Support\Tenancy;
use Filament\Actions\Action;

/** O que toda tela do Intelligence compartilha: acesso, o resumo da empresa (lido uma vez por requisição) e os botões de relatório. */
trait UsaResumoDoIntelligence
{
    private ?array $resumoMemo = null;

    private bool $resumoCarregado = false;

    private ?array $insightsMemo = null;

    private bool $insightsCarregados = false;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('viewAny', IntelligenceSnapshot::class);
    }

    /** @return ?array<string, mixed> */
    public function resumo(): ?array
    {
        if (! $this->resumoCarregado) {
            $tenant = Tenancy::current();
            $this->resumoMemo = $tenant ? app(EvolucaoService::class)->resumo($tenant->id) : null;
            $this->resumoCarregado = true;
        }

        return $this->resumoMemo;
    }

    /**
     * Achados por equipamento do snapshot mais recente que tem a aba (vazio se a empresa não tem a aba ou o contrato não libera a tela).
     *
     * @return array{snapshot: array{id: string, data: string}, itens: list<array<string, mixed>>}|array{}
     */
    public function insightsDeEquipamentos(): array
    {
        if (! $this->insightsCarregados) {
            $this->insightsCarregados = true;
            $t = Tenancy::current();
            $snap = ($t && $t->hasFeature('menu_intelligence_equipamentos') && $t->hasFeature('tabela_intelligence_equipamentos')) ? app(EquipamentosService::class)->snapshot($t->id) : null;
            $this->insightsMemo = $snap ? ['snapshot' => $snap, 'itens' => app(InsightsDeEquipamentos::class)->do_snapshot($t->id, $snap['id'])] : [];
        }

        return $this->insightsMemo ?? [];
    }

    public function urlImportacoes(): string
    {
        return IntelligenceImportResource::getUrl();
    }

    /** O chat de IA só aparece para usuário de cliente (o super admin fica na Central). */
    public function podeUsarChat(): bool
    {
        $u = auth()->user();

        return $u && ! $u->isSuperAdmin() && filled($u->tenant_id);
    }

    /** WhatsApp + versão para imprimir do relatório `$tipo` (painel, diagnostico, plano). Abrem em nova aba. */
    protected function acoesDeRelatorio(string $tipo, ?\Closure $visivel = null): array
    {
        $tem = $visivel ?? fn () => $this->resumo() !== null;
        $base = IntelligenceDashboard::getUrl().'/relatorio';

        return [
            Action::make('whatsapp')->label('Compartilhar no WhatsApp')->icon('heroicon-o-paper-airplane')->color('success')
                ->url(fn () => "{$base}/zap?tipo={$tipo}", shouldOpenInNewTab: true)->visible($tem),
            Action::make('imprimir')->label('Visualizar / imprimir')->icon('heroicon-o-printer')->color('gray')
                ->url(fn () => "{$base}/ver?tipo={$tipo}", shouldOpenInNewTab: true)->visible($tem),
        ];
    }
}
