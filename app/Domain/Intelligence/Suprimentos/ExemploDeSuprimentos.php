<?php

namespace App\Domain\Intelligence\Suprimentos;

use App\Domain\Intelligence\Suprimentos\Models\SuprimentosCarga;
use App\Domain\Intelligence\Trial\Models\IntelligenceTrial;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;
use Throwable;

/**
 * Dados de EXEMPLO do módulo Suprimentos (mesmas regras dos do Intelligence geral): entram pelo importador de verdade, marcados com
 * origem 'demonstracao'; aparecem sozinhos na 1ª abertura da tela (uma vez), o cliente limpa e pode pedir de volta enquanto não tiver
 * dado próprio, e somem de vez N dias depois da 1ª abertura (intelligence_trial.exemplo_dias).
 * Dados fictícios, determinísticos (o mesmo resultado a cada geração) e com datas relativas a hoje.
 */
class ExemploDeSuprimentos
{
    public const ORIGEM = 'demonstracao';

    private const FORNECEDORES = ['Auto Peças Silva', 'Auto Peças Silva', 'Auto Peças Silva', 'Distribuidora Central', 'Distribuidora Central', 'Hidráulica Norte', 'Ferramentas União', 'EPI Brasil', 'Papelaria Beta'];

    private const DESTINOS = ['Oficina', 'Oficina', 'Obra - Cliente A', 'Obra - Cliente B', 'Munck 12', 'Frota leve', 'Escritório'];

    /** categoria => [prefixo, [[descrição, unidade, custo]...]] */
    private const CATALOGO = [
        'Peças' => ['PEC', [['Filtro de óleo', 'un', 48], ['Filtro de ar', 'un', 95], ['Filtro de combustível', 'un', 62], ['Correia dentada', 'un', 130], ['Mangueira hidráulica 1/2"', 'm', 38], ['Rolamento 6205', 'un', 27], ['Bomba hidráulica', 'un', 1480], ['Cilindro hidráulico', 'un', 920], ['Pastilha de freio', 'jg', 210], ['Lâmpada de farol', 'un', 22], ['Vela de ignição', 'un', 34], ['Retentor', 'un', 18], ['Junta do cabeçote', 'un', 160], ['Cabo de aço 12mm', 'm', 24], ['Elo de corrente', 'un', 15], ['Pino e bucha', 'jg', 185], ['Bateria 90Ah', 'un', 520], ['Alternador', 'un', 690], ['Motor de partida', 'un', 740], ['Radiador', 'un', 860], ['Sensor de pressão', 'un', 210], ['Válvula direcional', 'un', 470], ['Terminal de direção', 'un', 115], ['Pneu 17.5R25', 'un', 3200], ['Câmara de ar', 'un', 140]]],
        'Materiais' => ['MAT', [['Óleo hidráulico 68', 'L', 16], ['Óleo motor 15W40', 'L', 22], ['Graxa azul', 'kg', 29], ['Eletrodo de solda 6013', 'kg', 24], ['Disco de corte', 'un', 9], ['Tinta esmalte amarelo', 'L', 58], ['Fita isolante', 'un', 6], ['Abraçadeira', 'un', 3], ['Parafuso M12', 'un', 2.2], ['Arruela lisa', 'un', 0.4], ['Lona de cobertura', 'un', 150], ['Diesel S10', 'L', 6.2]]],
        'Itens de Escritório' => ['ESC', [['Papel A4', 'cx', 210], ['Caneta esferográfica', 'cx', 38], ['Toner da impressora', 'un', 320], ['Pasta suspensa', 'cx', 74], ['Grampeador', 'un', 45], ['Bloco de notas', 'un', 12], ['Clips', 'cx', 9], ['Envelope', 'cx', 55]]],
        'Equipamentos' => ['EQP', [['Compressor portátil', 'un', 2100], ['Gerador 5 kVA', 'un', 6800], ['Esmerilhadeira', 'un', 540], ['Furadeira de impacto', 'un', 690], ['Lavadora de alta pressão', 'un', 1250]]],
        'Ferramentas' => ['FER', [['Jogo de chaves combinadas', 'jg', 380], ['Chave de impacto', 'un', 1450], ['Torquímetro', 'un', 690], ['Macaco hidráulico 10t', 'un', 720], ['Alicate universal', 'un', 48], ['Trena 5m', 'un', 22]]],
        'EPI e Uniformes' => ['EPI', [['Capacete de segurança', 'un', 34], ['Luva de raspa', 'par', 18], ['Bota de segurança', 'par', 140], ['Óculos de proteção', 'un', 9], ['Protetor auricular', 'un', 6], ['Uniforme (camisa)', 'un', 58]]],
        'Limpeza e Copa' => ['LIM', [['Detergente', 'L', 7], ['Papel toalha', 'fardo', 64], ['Café', 'kg', 34], ['Copo descartável', 'cx', 22]]],
        'Outros' => ['OUT', [['Cadeado', 'un', 28], ['Etiqueta de patrimônio', 'rl', 45]]],
    ];

    public static function existe(string $tenantId): bool
    {
        return SuprimentosCarga::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('origem', self::ORIGEM)->exists();
    }

    public static function temDadoReal(string $tenantId): bool
    {
        return SuprimentosCarga::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('origem', '!=', self::ORIGEM)->exists();
    }

    public static function soExemplo(string $tenantId): bool
    {
        return self::existe($tenantId) && ! self::temDadoReal($tenantId);
    }

    public static function expirou(IntelligenceTrial $trial): bool
    {
        return $trial->exemplo_suprimentos_oferecido_em !== null
            && $trial->exemplo_suprimentos_oferecido_em->copy()->addDays((int) config('intelligence_trial.exemplo_dias', 30))->isPast();
    }

    public static function diasRestantes(IntelligenceTrial $trial): ?int
    {
        if ($trial->exemplo_suprimentos_oferecido_em === null) {
            return null;
        }
        $fim = $trial->exemplo_suprimentos_oferecido_em->copy()->addDays((int) config('intelligence_trial.exemplo_dias', 30));

        return max(0, (int) ceil(now()->diffInSeconds($fim, false) / 86400));
    }

    public static function podeGerar(IntelligenceTrial $trial): bool
    {
        return ! self::expirou($trial) && ! self::existe((string) $trial->tenant_id) && ! self::temDadoReal((string) $trial->tenant_id);
    }

    /** @return int cargas criadas (0 se já havia exemplo) */
    public function gerar(Tenant $tenant): int
    {
        if (self::existe((string) $tenant->id)) {
            return 0;
        }
        $arquivo = $this->planilha(today());
        try {
            $r = app(ImportadorSuprimentos::class)->importar($arquivo, $tenant, null, false, 'exemplo-suprimentos.xlsx', self::ORIGEM);
        } finally {
            @unlink($arquivo);
        }
        if (! $r['ok']) {
            throw new RuntimeException('Exemplo de Suprimentos falhou: '.implode(' | ', $r['erros']));
        }

        return 1;
    }

    public function limpar(Tenant|string $tenant): int
    {
        $id = $tenant instanceof Tenant ? (string) $tenant->id : $tenant;

        return SuprimentosCarga::withoutGlobalScopes()->where('tenant_id', $id)->where('origem', self::ORIGEM)->delete(); // as linhas saem em cascata
    }

    /** Na 1ª abertura da tela: gera o exemplo (se o cliente não tem dado próprio). Depois do prazo, apaga o que sobrou. Nunca derruba a tela. */
    public function aoAbrir(IntelligenceTrial $trial): void
    {
        try {
            $tenant = $trial->tenant;
            if (! $tenant) {
                return;
            }
            if ($trial->exemplo_suprimentos_oferecido_em === null) {
                $trial->forceFill(['exemplo_suprimentos_oferecido_em' => now()])->save();
                if (! self::temDadoReal((string) $tenant->id)) {
                    $this->gerar($tenant);
                }

                return;
            }
            if (self::expirou($trial)) {
                $this->limpar($tenant);
            }
        } catch (Throwable $e) {
            Log::warning('Intelligence Suprimentos: exemplo não gerado/limpo.', ['trial_id' => $trial->id, 'erro' => $e->getMessage()]);
        }
    }

    /** Gera a planilha de exemplo no mesmo formato do modelo que o cliente baixa. Devolve o caminho (apague depois). */
    public function planilha(Carbon $ref): string
    {
        $r = fn (string $semente, string $k, float $min, float $max): float => $min + (crc32("{$semente}-{$k}") % 1000) / 1000 * ($max - $min);
        $dia = fn (int $atras): string => $ref->copy()->subDays($atras)->format('d/m/Y');

        // ---- inventário ----
        $itens = [];
        $n = 0;
        foreach (self::CATALOGO as $categoria => [$prefixo, $lista]) {
            foreach ($lista as $k => [$descricao, $unidade, $custo]) {
                $codigo = sprintf('%s-%03d', $prefixo, $k + 1);
                $pequeno = in_array($categoria, ['Equipamentos'], true);
                $temMinimo = $n % 5 !== 4;
                $minimo = $temMinimo ? ($pequeno ? 1 + $n % 2 : max(2, round($r($codigo, 'm', 4, 40)))) : null;
                $abaixo = $temMinimo && $n % 9 === 0;
                $qtd = match (true) {
                    $abaixo => max(0, floor($minimo * $r($codigo, 'f', 0.1, 0.7))),
                    $pequeno => 1 + $n % 4,
                    $temMinimo => round($minimo * $r($codigo, 'q', 1.3, 4)),
                    default => round($r($codigo, 'q', 5, 80)),
                };
                $parado = $n % 6 === 2;
                $ultimaSaida = $parado ? ($n % 12 === 2 ? null : $dia((int) $r($codigo, 's', 100, 330))) : $dia((int) $r($codigo, 's', 2, 60));
                $itens[] = [
                    'codigo' => $codigo, 'descricao' => $descricao, 'categoria' => $categoria, 'unidade' => $unidade, 'quantidade' => $qtd, 'minimo' => $minimo,
                    'custo' => $custo, 'entrada' => $dia((int) $r($codigo, 'e', 10, 200)), 'saida' => $ultimaSaida, 'local' => ['Almoxarifado 1', 'Almoxarifado 2', 'Depósito'][$n % 3], 'parado' => $parado,
                ];
                $n++;
            }
        }
        $ativos = array_values(array_filter($itens, fn ($i) => ! $i['parado']));

        // ---- compras (90 dias) ----
        $compras = [];
        for ($k = 0; $k < 42; $k++) {
            $item = $itens[($k * 7) % count($itens)];
            $pedidoEm = 3 + ($k * 2) % 85;
            $prazoEm = $pedidoEm - (int) $r("c$k", 'p', 5, 15);
            $aberta = $k % 11 === 0 && $pedidoEm > 30;
            $atraso = $k % 4 === 0 ? (int) $r("c$k", 'a', 1, 9) : -(int) $r("c$k", 'a', 0, 2);
            $chegadaEm = $prazoEm - $atraso;
            $qtd = max(1, round($r("c$k", 'q', 4, 60)));
            $compras[] = [
                'PC-'.(1000 + $k), $dia($pedidoEm), $item['codigo'], self::FORNECEDORES[$k % count(self::FORNECEDORES)], $qtd,
                round($qtd * $item['custo'] * $r("c$k", 'v', 0.95, 1.1), 2), $dia($prazoEm),
                $aberta || $chegadaEm < 0 ? '' : $dia($chegadaEm), $k % 7 === 3 ? 'Sim' : 'Não',
            ];
            if (! $aberta && $chegadaEm < 0) {
                $compras[$k][7] = $dia(0); // chegou hoje: nada de data futura
            }
        }

        // ---- movimentações ----
        $movs = [];
        for ($m = 0; $m < 190; $m++) {
            $item = $ativos[($m * 13) % count($ativos)];
            $destino = $m % 11 === 0 ? '' : self::DESTINOS[$m % count(self::DESTINOS)];
            $movs[] = [$dia($m % 85), $item['codigo'], 'Saída', max(1, round($r("m$m", 'q', 1, 6))), $destino, 'REQ-'.(5000 + $m)];
        }
        for ($m = 0; $m < 28; $m++) {
            $item = $itens[($m * 5) % count($itens)];
            $movs[] = [$dia(($m * 3) % 85), $item['codigo'], 'Entrada', max(1, round($r("e$m", 'q', 5, 40))), '', 'NF-'.(2200 + $m)];
        }

        // ---- grava no formato do modelo ----
        $caminho = tempnam(sys_get_temp_dir(), 'sup-exemplo').'.xlsx';
        $w = new Writer;
        $w->openToFile($caminho);
        $titulos = fn (array $campos) => Row::fromValues(array_column($campos, 'title'));
        $w->getCurrentSheet()->setName(Catalogo::ABA_DADOS);
        $w->addRow($titulos(Catalogo::dados()));
        $w->addRow(Row::fromValues([$ref->format('d/m/Y'), 90]));
        $w->addNewSheetAndMakeItCurrent()->setName(Catalogo::ABA_INVENTARIO);
        $w->addRow($titulos(Catalogo::inventario()));
        foreach ($itens as $i) {
            $w->addRow(Row::fromValues([$i['codigo'], $i['descricao'], $i['categoria'], $i['unidade'], $i['quantidade'], $i['minimo'], $i['custo'], $i['entrada'], $i['saida'], $i['local']]));
        }
        $w->addNewSheetAndMakeItCurrent()->setName(Catalogo::ABA_COMPRAS);
        $w->addRow($titulos(Catalogo::compras()));
        foreach ($compras as $c) {
            $w->addRow(Row::fromValues($c));
        }
        $w->addNewSheetAndMakeItCurrent()->setName(Catalogo::ABA_MOVIMENTOS);
        $w->addRow($titulos(Catalogo::movimentos()));
        foreach ($movs as $m) {
            $w->addRow(Row::fromValues($m));
        }
        $w->close();

        return $caminho;
    }
}
