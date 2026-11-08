<?php

namespace App\Services\Catalogo;

/**
 * Levanta, direto dos YAML do template, TUDO o que o responsavel tecnico precisa validar antes de ligar a chave
 * `tabela_catalogo_equipamentos` em cliente real (pendencia bloqueante da G1): itens que citam norma, regras, operadores,
 * duvidas de nivel de grupo e intervalos de preventiva provisorios (a validar com o manual do fabricante).
 * Nenhuma norma e afirmada como exigencia legal: tudo aqui e hipotese.
 */
class PendenciasDeNorma
{
    public function __construct(private CatalogoInstalador $instalador) {}

    /** @return array<int, array{grupo: string, tipo: string, item: string, norma: string, validar: string}> */
    public function levantar(?string $dir = null): array
    {
        $tpl = $this->instalador->carregarTemplate($dir);
        $nomesItens = collect($tpl['biblioteca']['itens'] ?? [])->pluck('nome', 'slug')->all();
        $linhas = [];

        foreach ($tpl['grupos'] as $arq) {
            $grupo = $arq['grupo']['nome'];

            if (! empty($arq['grupo']['validar'])) {
                $linhas[] = ['grupo' => $grupo, 'tipo' => 'Grupo', 'item' => $grupo, 'norma' => '—', 'validar' => (string) $arq['grupo']['validar']];
            }

            foreach ($arq['requisitos'] ?? [] as $r) {
                if (! empty($r['validar_norma'])) {
                    $linhas[] = ['grupo' => $grupo, 'tipo' => 'Requisito ('.$r['efeito'].')', 'item' => $nomesItens[$r['item']] ?? $r['item'], 'norma' => (string) ($r['norma'] ?? '—'),
                        'validar' => 'A norma se aplica a este grupo? Qual a periodicidade/validade? (a validade é informada no registro do cumprimento)'];
                }
            }

            foreach ($arq['operadores'] ?? [] as $o) {
                if (! empty($o['validar_norma'])) {
                    $linhas[] = ['grupo' => $grupo, 'tipo' => 'Operador', 'item' => $o['habilitacao'], 'norma' => (string) ($o['norma'] ?? '—'),
                        'validar' => 'Qual habilitação/capacitação é exigida e por quanto tempo vale?'];
                }
            }

            foreach ($arq['regras'] ?? [] as $r) {
                if (! empty($r['validar_norma'])) {
                    $linhas[] = ['grupo' => $grupo, 'tipo' => 'Regra', 'item' => $r['nome'], 'norma' => '—', 'validar' => 'A regra (e o efeito sobre a aptidão) está correta para o grupo?'];
                }
            }

            foreach ($arq['preventivas'] ?? [] as $p) {
                if (! empty($p['parametros']['validar_manual'])) {
                    $linhas[] = ['grupo' => $grupo, 'tipo' => 'Preventiva (intervalo provisório)', 'item' => $p['nome'].' — '.$p['intervalo'].' '.($p['medidor'] === 'km' ? 'km' : 'h'),
                        'norma' => 'Manual do fabricante', 'validar' => 'Conferir/ajustar o intervalo conforme o manual do fabricante deste modelo.'];
                }
            }
        }

        return $linhas;
    }

    /** Documento em Markdown para o responsavel tecnico preencher (gerado: nao editar a mao; rode `php artisan catalogo:pendencias-norma --md`). */
    public function markdown(?string $dir = null): string
    {
        $porGrupo = collect($this->levantar($dir))->groupBy('grupo');
        $total = $porGrupo->flatten(1)->count();

        $md = "# G1 — Validação das normas pelo responsável técnico\n\n";
        $md .= "> Gerado por `php artisan catalogo:pendencias-norma --md` a partir de `database/seeders/catalog/*.yaml`. **Não editar à mão.**\n\n";
        $md .= "**Pendência bloqueante:** a chave `tabela_catalogo_equipamentos` não deve ser ligada em contrato de cliente real antes de todos os itens abaixo serem validados.\n";
        $md .= "As normas citadas (NR-10, NR-11, NR-12, NR-13, NR-18, NR-35, CTB/ANTT) são **hipóteses** do template, não afirmações de exigência legal. Os valores são editáveis por empresa.\n\n";
        $md .= "Total a validar: **{$total}** item(ns).\n";

        foreach ($porGrupo as $grupo => $itens) {
            $md .= "\n## {$grupo}\n\n| ✔ | Tipo | Item | Norma | O que validar | Validado por / data |\n|---|---|---|---|---|---|\n";

            foreach ($itens as $i) {
                $md .= '| ☐ | '.$this->celula($i['tipo']).' | '.$this->celula($i['item']).' | '.$this->celula($i['norma']).' | '.$this->celula($i['validar'])." |  |\n";
            }
        }

        return $md;
    }

    private function celula(string $v): string
    {
        return str_replace(['|', "\n"], ['\\|', ' '], $v);
    }
}
