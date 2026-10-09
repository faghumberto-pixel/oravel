<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ClientPortalDocumentsPageTest extends TestCase
{
    use DatabaseTransactions;

    public function test_pagina_documentos_do_portal_abre_sem_erro(): void
    {
        $plan = Plan::create(['name' => 'P'.uniqid(), 'price' => 0, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_clients' => true]]);
        $tenant = Tenant::create(['name' => 'L'.uniqid(), 'slug' => 'l-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Docs', 'email' => 'docs'.uniqid().'@t.com', 'password' => 'x12345678', 'portal_access_enabled_at' => now()]);

        $this->actingAs($client, 'client')->get('/cliente/meus-documentos')->assertOk()->assertSee('Documentos');
    }
}
