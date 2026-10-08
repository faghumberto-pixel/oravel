<?php

namespace Tests\Feature;

use App\Models\DocumentSignature;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ContratoAssinadoCentralTest extends TestCase
{
    use DatabaseTransactions;

    private function assinatura(): DocumentSignature
    {
        $tenant = Tenant::withoutGlobalScopes()->create(['name' => 'Cliente Teste Pagina', 'slug' => 'cliente-teste-pagina-'.uniqid()]);

        return DocumentSignature::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'signable_type' => Tenant::class,
            'signable_id' => $tenant->id,
            'token' => bin2hex(random_bytes(16)),
            'signer_name' => 'Fulano de Tal',
            'signer_document' => '000.000.000-00',
            'signer_email' => 'fulano@example.com',
            'ip_address' => '127.0.0.1',
            'status' => 'signed',
            'signed_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);
    }

    public function test_super_admin_ve_o_contrato_como_pagina_para_imprimir(): void
    {
        config(['oravel.super_admins' => ['admin-teste@oravel.test']]);
        $assinatura = $this->assinatura();
        $admin = User::factory()->create(['email' => 'admin-teste@oravel.test']);

        $resposta = $this->actingAs($admin)->get(route('central.contrato-assinado.ver', $assinatura->id));

        $resposta->assertOk();
        $this->assertStringContainsString('text/html', $resposta->headers->get('Content-Type'));
        $resposta->assertSee('Cliente Teste Pagina');
        $resposta->assertSee('Fulano de Tal');
        $resposta->assertSee('Comprovante de assinatura');
        $resposta->assertSee('Imprimir');
        $resposta->assertDontSee('Baixar PDF');
    }

    public function test_so_super_admin_ve(): void
    {
        $assinatura = $this->assinatura();
        $comum = User::factory()->create();

        $this->actingAs($comum)->get(route('central.contrato-assinado.ver', $assinatura->id))->assertForbidden();
    }
}
