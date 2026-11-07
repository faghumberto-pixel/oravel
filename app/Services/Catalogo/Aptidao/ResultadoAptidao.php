<?php

namespace App\Services\Catalogo\Aptidao;

/** Resultado da avaliacao: status + motivos (cada um com codigo, nivel e texto) + avisos que nao mudam o status. */
class ResultadoAptidao
{
    public const APTO = 'apto';

    public const RESTRICAO = 'apto_restricao';

    public const INAPTO = 'inapto';

    public const INCOMPLETO = 'cadastro_incompleto';

    private const RANK = [self::APTO => 0, self::RESTRICAO => 1, self::INAPTO => 2];

    /** @var array<int, array{codigo: string, nivel: string, texto: string, requisito: ?string}> */
    public array $motivos = [];

    /** @var array<int, string> atributos incompletos etc.: aviso, nao bloqueia */
    public array $avisos = [];

    public bool $bloqueiaAlocacao = false;

    /** @var array<int, string> slugs de requisitos IMP reprovados com gera_os (a OS automatica e criada na fase 5) */
    public array $osAGerar = [];

    public function __construct(public string $status = self::APTO) {}

    public static function incompleto(string $motivo): self
    {
        $r = new self(self::INCOMPLETO);
        $r->motivos[] = ['codigo' => 'sem_grupo', 'nivel' => self::INCOMPLETO, 'texto' => $motivo, 'requisito' => null];

        return $r;
    }

    public function adicionar(string $codigo, string $nivel, string $texto, ?string $requisito = null): void
    {
        foreach ($this->motivos as $m) {
            if ($m['codigo'] === $codigo) {
                return; // dedupe (ex.: NR-13 pelo requisito e pela strategy)
            }
        }

        $this->motivos[] = ['codigo' => $codigo, 'nivel' => $nivel, 'texto' => $texto, 'requisito' => $requisito];

        if ($this->status !== self::INCOMPLETO && self::RANK[$nivel] > self::RANK[$this->status]) {
            $this->status = $nivel;
        }
    }

    public function toArray(): array
    {
        return [
            'status' => $this->status, 'motivos' => $this->motivos, 'avisos' => $this->avisos,
            'bloqueia_alocacao' => $this->bloqueiaAlocacao, 'os_a_gerar' => $this->osAGerar,
        ];
    }
}
