<?php

namespace Tests\Feature;

use App\Models\WebPageview;
use App\Models\WebVisit;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** Resumo de visitas ao Indice Oravel para o painel do Observatorio: so' agregados, protegido por chave. */
class ObservatorioVisitasIndiceApiTest extends TestCase
{
    use DatabaseTransactions;

    private function visita(string $site, string $path, array $extra = []): WebVisit
    {
        $v = WebVisit::create($extra + [
            'site' => $site, 'visitor_token' => 'v'.uniqid(), 'session_token' => 's'.uniqid(), 'ip_address' => '203.0.113.9',
            'city' => 'Campinas', 'state' => 'SP', 'device_type' => 'desktop', 'landing_path' => $path, 'started_at' => now(), 'last_activity_at' => now(), 'page_views' => 1,
        ]);
        WebPageview::create(['web_visit_id' => $v->id, 'page_token' => 'p'.uniqid(), 'path' => $path, 'entered_at' => $extra['started_at'] ?? now()]);

        return $v;
    }

    private function chamar(?string $token = 'chave-teste', string $qs = '')
    {
        return $this->withHeaders($token ? ['Authorization' => 'Bearer '.$token] : [])->getJson('/api/internal/observatorio/visitas-indice'.$qs);
    }

    public function test_sem_chave_configurada_a_rota_nao_existe(): void
    {
        config(['oravel.observatorio_stats.token' => null]);
        $this->chamar('qualquer')->assertNotFound();
    }

    public function test_chave_errada_ou_ausente_e_recusada_sem_detalhes(): void
    {
        config(['oravel.observatorio_stats.token' => 'chave-teste']);
        $this->chamar('errada')->assertStatus(401)->assertExactJson(['erro' => 'nao_autorizado']);
        $this->chamar(null)->assertStatus(401);
    }

    public function test_devolve_agregados_so_do_oravel_org_na_pagina_do_indice_e_sem_ip(): void
    {
        config(['oravel.observatorio_stats.token' => 'chave-teste']);
        $this->visita(WebVisit::SITE_ORG, '/indice/', ['utm_source' => 'linkedin', 'utm_medium' => 'social']);
        $this->visita(WebVisit::SITE_ORG, '/indice/', ['city' => 'Curitiba', 'state' => 'PR', 'device_type' => 'mobile', 'referrer_host' => 'google.com']);
        $this->visita(WebVisit::SITE_ORG, '/publicacoes/');                    // outra pagina: fora
        $this->visita(WebVisit::SITE_INSTITUCIONAL, '/indice/');                // outro site: fora
        $this->visita(WebVisit::SITE_ORG, '/indice/', ['started_at' => now()->subDays(60)]); // fora do periodo

        $r = $this->chamar()->assertOk();
        $r->assertJsonPath('visitas', 2)->assertJsonPath('pageviews', 2);
        $this->assertEqualsCanonicalizing([['SP', 1], ['PR', 1]], array_map(fn ($e) => [$e['rotulo'], $e['n']], $r->json('estados')));
        $this->assertContains('Campinas (SP)', array_column($r->json('cidades'), 'rotulo'));
        $this->assertEqualsCanonicalizing(['desktop', 'mobile'], array_column($r->json('dispositivos'), 'rotulo'));
        $this->assertEqualsCanonicalizing(['linkedin / social', 'google.com'], array_column($r->json('origens'), 'rotulo'));
        $this->assertSame(2, array_sum(array_column($r->json('por_dia'), 'n')));
        $this->assertStringNotContainsString('203.0.113.9', $r->getContent(), 'nunca devolve IP');
    }

    public function test_periodo_e_limitado(): void
    {
        config(['oravel.observatorio_stats.token' => 'chave-teste']);
        $this->chamar('chave-teste', '?dias=9999')->assertJsonPath('dias', 90);
        $this->chamar('chave-teste', '?dias=0')->assertJsonPath('dias', 1);
    }
}
