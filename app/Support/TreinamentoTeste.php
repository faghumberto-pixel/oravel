<?php

namespace App\Support;

use RuntimeException;

/**
 * Teste público "Cadastros, Propostas e Contas a Receber": carrega as
 * perguntas do quiz.json (entradas /treinamento/*.html, ordenadas pelo campo
 * "n"), embaralha as alternativas a cada exibição e corrige no servidor. O
 * gabarito nunca vai para o navegador antes do envio.
 */
class TreinamentoTeste
{
    public const SLUG = 'cadastros-propostas-receber';

    public const TITULO = 'Cadastros, Propostas e Contas a Receber';

    public const PDF = 'treinamento_cadastros_propostas_receber_oravel.pdf';

    /**
     * @return array<int, array{n: int, q: string, options: array<int, string>, correct: int, why: string}>
     */
    public static function perguntas(): array
    {
        $arquivo = database_path('data/academy/quiz.json');
        $entradas = json_decode((string) file_get_contents($arquivo), true);

        if (! is_array($entradas)) {
            throw new RuntimeException('quiz.json inválido.');
        }

        $perguntas = [];

        foreach ($entradas as $entrada) {
            if (! str_starts_with((string) ($entrada['page'] ?? ''), '/treinamento/')) {
                continue;
            }

            foreach ($entrada['questions'] as $pergunta) {
                $perguntas[] = [
                    'n' => (int) $pergunta['n'],
                    'q' => (string) $pergunta['q'],
                    'options' => array_values($pergunta['options']),
                    'correct' => (int) $pergunta['correct'],
                    'why' => (string) ($pergunta['why'] ?? ''),
                ];
            }
        }

        usort($perguntas, fn ($a, $b) => $a['n'] <=> $b['n']);

        return $perguntas;
    }

    /**
     * Ordem das alternativas de cada pergunta nesta exibição: n => [índices
     * originais na ordem em que aparecem na tela]. Fica só na sessão do servidor.
     *
     * @return array<int, array<int, int>>
     */
    public static function sortearOrdem(array $perguntas): array
    {
        $ordem = [];

        foreach ($perguntas as $pergunta) {
            $indices = array_keys($pergunta['options']);
            shuffle($indices);
            $ordem[$pergunta['n']] = $indices;
        }

        return $ordem;
    }

    /**
     * Corrige. $respostas = n => posição escolhida na tela (ou ausente/nula =
     * em branco). Devolve o que a gravação e o e-mail precisam.
     *
     * @param  array<int, array<int, int>>  $ordem
     * @param  array<int|string, mixed>  $respostas
     * @return array{acertos: int, total: int, nota: float, aprovado: bool, respostas: array<int, ?int>}
     */
    public static function corrigir(array $perguntas, array $ordem, array $respostas): array
    {
        $acertos = 0;
        $gravadas = [];

        foreach ($perguntas as $pergunta) {
            $n = $pergunta['n'];
            $posicao = $respostas[$n] ?? null;
            $original = ($posicao !== null && $posicao !== '' && isset($ordem[$n][(int) $posicao]))
                ? $ordem[$n][(int) $posicao]
                : null;

            $gravadas[$n] = $original;

            if ($original !== null && $original === $pergunta['correct']) {
                $acertos++;
            }
        }

        $total = count($perguntas);
        $nota = $total > 0 ? round($acertos / $total * 10, 1) : 0.0;

        return [
            'acertos' => $acertos,
            'total' => $total,
            'nota' => $nota,
            'aprovado' => $nota >= (float) config('oravel.academy.passing_grade', 7.0),
            'respostas' => $gravadas,
        ];
    }

    public static function caminhoPdf(): string
    {
        return public_path('treinamento/'.self::PDF);
    }

    public static function temPdf(): bool
    {
        return is_file(self::caminhoPdf());
    }
}
