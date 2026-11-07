<?php

namespace App\Services\Catalogo\Aptidao;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\CatalogoCumprimento;
use App\Models\CatalogoPreventiva;
use App\Models\CatalogoRegra;
use App\Models\CatalogoRequisito;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * G1 -- MOTOR UNICO de aptidao (Apto / Apto com restricao / Inapto). Le o PERFIL do grupo do ativo (dado, nao codigo)
 * e as evidencias do ativo: cumprimentos (documento/inspecao/checklist/preventiva), NR-13 existente e medidores atuais.
 *
 * Fase 3 cobre: atributo (aviso), documento, inspecao, preventiva (tolerancia), checklist IMP/ALR, acessorio, regras em
 * dados + strategy nr13. Operador, peca critica, liberacao excepcional e bloqueio de despacho entram nas fases 5-7.
 * Toda consulta e explicita por tenant_id (roda em job, sem usuario logado, e com super admin).
 */
class AssetFitnessService
{
    /** Evidencia ausente (nunca registrada) NAO e "vencida": padrao = Apto com restricao; configuravel por requisito/preventiva (parametros.sem_registro: inapto|restricao|ignorar). */
    public const SEM_REGISTRO_PADRAO = 'restricao';

    public function calcular(Asset $ativo, ?CarbonInterface $agora = null): ResultadoAptidao
    {
        $grupo = $ativo->asset_category_id
            ? AssetCategory::withoutGlobalScopes()->withTrashed()->where('tenant_id', $ativo->tenant_id)->find($ativo->asset_category_id)
            : null;

        if (! $grupo) {
            return ResultadoAptidao::incompleto('Ativo sem grupo de equipamento: cadastro incompleto.');
        }

        if ($grupo->trashed()) {
            return ResultadoAptidao::incompleto("O grupo \"{$grupo->name}\" foi arquivado: cadastro incompleto.");
        }

        return $this->avaliar($ativo, PerfilDoGrupo::doGrupo($grupo), $agora);
    }

    public function avaliar(Asset $ativo, PerfilDoGrupo $perfil, ?CarbonInterface $agora = null): ResultadoAptidao
    {
        $agora = $agora ? $agora->copy() : now();
        $r = new ResultadoAptidao;

        if ($perfil->requisitos->isEmpty() && $perfil->preventivas->isEmpty() && $perfil->regras->isEmpty()) {
            $r->avisos[] = "O grupo \"{$perfil->grupo->name}\" ainda não tem requisitos, preventivas nem regras configurados.";
        }

        $cumprimentos = CatalogoCumprimento::withoutGlobalScopes()->where('tenant_id', $ativo->tenant_id)->where('asset_id', $ativo->id)
            ->orderByDesc('realizado_em')->get();
        $porRequisito = $cumprimentos->whereNotNull('requisito_id')->groupBy('requisito_id');
        $porPreventiva = $cumprimentos->whereNotNull('preventiva_id')->groupBy('preventiva_id');

        $this->atributos($ativo, $perfil, $r);

        foreach ($perfil->requisitos as $req) {
            $this->requisito($ativo, $req, $porRequisito->get($req->id, collect())->first(), $agora, $r);
        }

        foreach ($perfil->preventivas as $prev) {
            $this->preventiva($ativo, $prev, $porPreventiva->get($prev->id, collect())->firstWhere('resultado', CatalogoCumprimento::OK), $agora, $r);
        }

        foreach ($perfil->regras as $regra) {
            $this->regra($ativo, $regra, $perfil, $porRequisito, $agora, $r);
        }

        return $r;
    }

    /** Calcula e grava o cache no ativo (query builder: sem eventos, sem loop de observers). */
    public function recalcular(Asset $ativo, ?CarbonInterface $agora = null): ResultadoAptidao
    {
        $r = $this->calcular($ativo, $agora);

        $dados = [
            'aptidao_status' => $r->status,
            'aptidao_motivos' => json_encode(['motivos' => $r->motivos, 'avisos' => $r->avisos, 'os_a_gerar' => $r->osAGerar], JSON_UNESCAPED_UNICODE),
            'aptidao_bloqueia_alocacao' => $r->bloqueiaAlocacao,
            'aptidao_calculada_em' => now(),
        ];

        Asset::withoutGlobalScopes()->whereKey($ativo->id)->update($dados);
        $ativo->setRawAttributes(array_merge($ativo->getAttributes(), $dados), true);

        return $r;
    }

    // ------------------------------------------------------------------ atributos

    private function atributos(Asset $ativo, PerfilDoGrupo $perfil, ResultadoAptidao $r): void
    {
        $valores = (array) ($ativo->atributos_tecnicos ?? []);

        foreach ($perfil->atributos->where('obrigatorio', true) as $attr) {
            $v = $valores[$attr->chave] ?? null;

            if ($v === null || $v === '' || $v === []) {
                $r->avisos[] = "Campo técnico obrigatório não preenchido: {$attr->rotulo}.";
            }
        }
    }

    // ------------------------------------------------------------------ requisitos

    private function requisito(Asset $ativo, CatalogoRequisito $req, ?CatalogoCumprimento $ult, CarbonInterface $agora, ResultadoAptidao $r): void
    {
        $nome = $req->item?->nome ?? $req->slug;
        $params = (array) $req->parametros;
        $hoje = $agora->copy()->startOfDay();

        switch ($req->efeito) {
            case 'IMP':
            case 'ALR':
                if ($ult && $ult->resultado === CatalogoCumprimento::REPROVADO) {
                    $imp = $req->efeito === 'IMP';
                    $r->adicionar("req:{$req->slug}", $imp ? ResultadoAptidao::INAPTO : ResultadoAptidao::RESTRICAO,
                        $imp ? "{$nome}: reprovado no checklist (impeditivo)." : "{$nome}: reprovado no checklist (alerta).", $req->slug);

                    if ($imp && $req->gera_os) {
                        $r->osAGerar[] = $req->slug;
                    }
                }
                break;

            case 'DOC':
            case 'INSP':
                $nivelVencido = $this->nivel($params['efeito_vencido'] ?? 'inapto');

                if (($params['fonte_validade'] ?? null) === 'nr13_inspection_periodicities') {
                    $venc = EstrategiaNr13::proximoVencimento($ativo);

                    if ($venc === null) {
                        $this->semRegistro($req->slug, $nome, $params, $r, "req:{$req->slug}");
                    } elseif ($venc->lt($hoje)) {
                        $r->adicionar('nr13_vencida', $nivelVencido, 'Inspeção NR-13 vencida em '.$venc->format('d/m/Y').'.', $req->slug);
                    }

                    break;
                }

                if (! $ult) {
                    $this->semRegistro($req->slug, $nome, $params, $r, "req:{$req->slug}");
                } elseif ($ult->resultado === CatalogoCumprimento::REPROVADO) {
                    $r->adicionar("req:{$req->slug}", $nivelVencido, "{$nome}: último registro reprovado/inválido.", $req->slug);
                } elseif ($ult->valido_ate && $ult->valido_ate->lt($hoje)) {
                    $r->adicionar("req:{$req->slug}", $nivelVencido, "{$nome} vencido em ".$ult->valido_ate->format('d/m/Y').'.', $req->slug);
                }
                break;

            case 'ACESS':
                if ($ult && $ult->resultado === CatalogoCumprimento::REPROVADO) {
                    $r->adicionar("req:{$req->slug}", ! empty($params['impeditivo']) ? ResultadoAptidao::INAPTO : ResultadoAptidao::RESTRICAO,
                        "Acessório faltando: {$nome}.", $req->slug);
                }
                break;
        }
    }

    /** O requisito esta cumprido e dentro da validade? (usado pelas condicoes das regras) */
    private function cumprido(?CatalogoCumprimento $ult, CarbonInterface $agora): bool
    {
        return $ult !== null
            && $ult->resultado === CatalogoCumprimento::OK
            && (! $ult->valido_ate || $ult->valido_ate->gte($agora->copy()->startOfDay()));
    }

    // ------------------------------------------------------------------ preventiva

    private function preventiva(Asset $ativo, CatalogoPreventiva $p, ?CatalogoCumprimento $ult, CarbonInterface $agora, ResultadoAptidao $r): void
    {
        $params = (array) $p->parametros;
        $codigo = "prev:{$p->slug}";

        if (! $ult) {
            $this->semRegistro($p->slug, "Preventiva {$p->nome}", $params, $r, $codigo);

            return;
        }

        $tol = (float) ($p->tolerancia_pct ?? 10);
        $porMedidor = null;
        $porDias = null;
        $detalhe = [];

        if (in_array($p->medidor, ['horimetro', 'km'], true)) {
            $base = $ult->medidor_leitura;

            if ($base === null) {
                $this->semRegistro($p->slug, "Preventiva {$p->nome} (sem leitura do medidor na última execução)", $params, $r, $codigo);

                return;
            }

            $atual = (float) ($p->medidor === 'km' ? $ativo->odometro_atual : $ativo->horimetro_atual);
            $excesso = ($atual - (float) $base) - $p->intervalo;
            $porMedidor = $this->nivelPorExcesso($excesso, (float) $p->intervalo, $tol);

            if ($excesso > 0) {
                $detalhe[] = round($excesso, 1).($p->medidor === 'km' ? ' km' : ' h').' acima do intervalo';
            }
        }

        $dias = $p->medidor === 'dias' ? $p->intervalo : $p->ou_dias;

        if ($dias) {
            $decorridos = (int) floor($ult->realizado_em->copy()->startOfDay()->diffInDays($agora->copy()->startOfDay()));
            $excesso = $decorridos - $dias;
            $porDias = $this->nivelPorExcesso($excesso, (float) $dias, $tol);

            if ($excesso > 0) {
                $detalhe[] = "{$excesso} dia(s) acima do prazo";
            }
        }

        $niveis = array_filter([$porMedidor, $porDias]);
        $dimensoes = ($p->medidor !== 'dias' && $dias) ? 2 : 1;

        // regra "ambos": so vence quando AS DUAS dimensoes venceram; "primeiro" (padrao): basta uma
        if ($dimensoes === 2 && $p->regra_ou === 'ambos' && count($niveis) < 2) {
            return;
        }

        if ($niveis === []) {
            return;
        }

        $nivel = in_array('inapto', $niveis, true) ? ResultadoAptidao::INAPTO : ResultadoAptidao::RESTRICAO;
        $r->adicionar($codigo, $nivel, "Preventiva {$p->nome} vencida (".implode('; ', $detalhe).').', $p->slug);
    }

    /** excesso <= 0: em dia. Ate tolerancia% do intervalo = restricao; acima = inapto. */
    private function nivelPorExcesso(float $excesso, float $base, float $tolerancia): ?string
    {
        if ($excesso <= 0 || $base <= 0) {
            return null;
        }

        return (($excesso / $base) * 100) <= $tolerancia ? 'restricao' : 'inapto';
    }

    // ------------------------------------------------------------------ regras

    /** @param Collection<string, Collection<int, CatalogoCumprimento>> $porRequisito */
    private function regra(Asset $ativo, CatalogoRegra $regra, PerfilDoGrupo $perfil, Collection $porRequisito, CarbonInterface $agora, ResultadoAptidao $r): void
    {
        if ($regra->strategy === 'nr13') {
            $venc = EstrategiaNr13::proximoVencimento($ativo);

            if ($venc && $venc->lt($agora->copy()->startOfDay())) {
                $this->aplicarEfeito($regra, 'nr13_vencida', 'Inspeção NR-13 vencida em '.$venc->format('d/m/Y').'.', $r);
            }

            return;
        }

        if ($regra->strategy) {
            return; // strategy desconhecida: nao avalia (nunca bloqueia por engano)
        }

        $cond = (array) $regra->condicao;
        $disparou = false;

        if (isset($cond['sem_cumprimento'])) {
            $req = $perfil->requisitos->firstWhere('slug', $cond['sem_cumprimento']);
            $disparou = $req && ! $this->cumprido($porRequisito->get($req->id, collect())->first(), $agora);
        } elseif (isset($cond['atributo_vazio'])) {
            $v = ((array) ($ativo->atributos_tecnicos ?? []))[$cond['atributo_vazio']] ?? null;
            $disparou = $v === null || $v === '' || $v === [];
        }

        if ($disparou) {
            $this->aplicarEfeito($regra, "regra:{$regra->slug}", $regra->nome.'.', $r);
        }
    }

    private function aplicarEfeito(CatalogoRegra $regra, string $codigo, string $texto, ResultadoAptidao $r): void
    {
        if ($regra->efeito === 'bloqueia_alocacao') {
            $r->bloqueiaAlocacao = true;
            $r->adicionar($codigo, ResultadoAptidao::APTO, "Bloqueia alocação: {$texto}", $regra->slug);

            return;
        }

        $r->adicionar($codigo, $this->nivel($regra->efeito), $texto, $regra->slug);
    }

    // ------------------------------------------------------------------ comum

    private function nivel(?string $efeito): string
    {
        return $efeito === 'restricao' ? ResultadoAptidao::RESTRICAO : ResultadoAptidao::INAPTO;
    }

    private function semRegistro(string $slug, string $nome, array $params, ResultadoAptidao $r, string $codigo): void
    {
        $modo = $params['sem_registro'] ?? self::SEM_REGISTRO_PADRAO;

        if ($modo === 'ignorar') {
            return;
        }

        $r->adicionar("sem_registro:{$slug}", $this->nivel($modo), "{$nome}: sem registro.", $slug);
    }
}
