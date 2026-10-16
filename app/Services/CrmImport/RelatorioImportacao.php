<?php

namespace App\Services\CrmImport;

/** Resultado de uma importação (ou simulação): totais + uma linha de relatório por registro da planilha. */
class RelatorioImportacao
{
    public const CRIADO = 'criado';

    public const ATUALIZADO = 'atualizado';

    public const IGNORADO = 'ignorado';

    public const ERRO = 'erro';

    public int $criados = 0;

    public int $atualizados = 0;

    public int $ignorados = 0;

    public int $erros = 0;

    public int $avisos = 0;

    /** @var array<int, array{linha: int, acao: string, nome: string, mensagem: string}> */
    public array $linhas = [];

    public function __construct(public readonly bool $simulacao = false) {}

    public function adicionar(int $linha, string $acao, string $nome = '', string $mensagem = '', int $avisos = 0): void
    {
        match ($acao) {
            self::CRIADO => $this->criados++,
            self::ATUALIZADO => $this->atualizados++,
            self::IGNORADO => $this->ignorados++,
            default => $this->erros++,
        };

        $this->avisos += $avisos;
        $this->linhas[] = ['linha' => $linha, 'acao' => $acao, 'nome' => $nome, 'mensagem' => $mensagem];
    }

    public function totais(): array
    {
        return ['criados' => $this->criados, 'atualizados' => $this->atualizados, 'ignorados' => $this->ignorados, 'erros' => $this->erros, 'avisos' => $this->avisos];
    }

    /** Linhas que pedem atenção primeiro (erros, ignorados, avisos), depois as demais. */
    public function linhasParaExibir(int $limite = 50): array
    {
        $ordem = fn (array $l) => match (true) {
            $l['acao'] === self::ERRO => 0,
            $l['acao'] === self::IGNORADO => 1,
            $l['mensagem'] !== '' => 2,
            default => 3,
        };

        $linhas = $this->linhas;
        usort($linhas, fn ($a, $b) => [$ordem($a), $a['linha']] <=> [$ordem($b), $b['linha']]);

        return array_slice($linhas, 0, $limite);
    }

    /** CSV (`;`, UTF-8 com BOM, abre direto no Excel). */
    public function paraCsv(): string
    {
        $out = "\xEF\xBB\xBF";
        $out .= $this->linhaCsv(['Linha', 'Resultado', 'Registro', 'Observação']);

        foreach ($this->linhas as $l) {
            $out .= $this->linhaCsv([$l['linha'], $l['acao'], $l['nome'], $l['mensagem']]);
        }

        return $out;
    }

    private function linhaCsv(array $campos): string
    {
        return implode(';', array_map(fn ($c) => '"'.str_replace('"', '""', (string) $c).'"', $campos))."\r\n";
    }

    /** Versão compacta para o histórico (até 500 linhas, as que pedem atenção primeiro). */
    public function paraHistorico(int $limite = 500): array
    {
        return $this->linhasParaExibir($limite);
    }
}
