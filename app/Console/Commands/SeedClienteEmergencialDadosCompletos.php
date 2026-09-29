<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Pedido do usuário 29/09/2026: a "Construtora Resposta Rápida Ltda" (cliente
 * emergencial criado por demo:seed-ativo-frota, vinculado ao Contrato do
 * Guincho) nasceu só com name+email -- todo o resto da Ficha Cadastral ERP
 * (Identificação PJ, Endereço, Entrega, Contatos, Representante Legal,
 * Checklist de Documentos, Análise de Risco) ficava em branco. Preenche
 * tudo que o form de ClientResource expõe.
 *
 * Idempotente: sempre reaplica os mesmos valores fixos (update(), não
 * create()) -- rodar de novo não duplica nem some com edição manual feita
 * DEPOIS deste comando, mas também não preserva uma edição manual anterior
 * a ele.
 */
class SeedClienteEmergencialDadosCompletos extends Command
{
    protected $signature = 'demo:seed-cliente-emergencial-dados {email=faghumberto@gmail.com}';

    protected $description = 'Preenche a ficha cadastral completa da Construtora Resposta Rápida Ltda (cliente emergencial do seed de frota)';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('Usuário não encontrado.');

            return self::FAILURE;
        }

        $client = Client::where('tenant_id', $user->tenant_id)
            ->where('name', 'Construtora Resposta Rápida Ltda')
            ->first();

        if (! $client) {
            $this->error('Cliente "Construtora Resposta Rápida Ltda" não encontrado pra este tenant.');

            return self::FAILURE;
        }

        $client->update([
            // Identificação
            'fantasy_name' => 'Resposta Rápida Construções',
            'document' => '12.345.678/0001-90',
            'cpf_cnpj' => '12.345.678/0001-90',
            'state_registration' => '123.456.789.112',
            'municipal_registration' => '987654-3',
            'tax_regime' => 'Lucro Presumido',
            'activity_type' => Client::NICHE_CONSTRUCAO_CIVIL,

            // Endereço de faturamento
            'address' => 'Rua das Palmeiras, 450',
            'address_complement' => 'Sala 12',
            'neighborhood' => 'Jardim Chapadão',
            'city' => 'Campinas',
            'state' => 'SP',
            'uf' => 'SP',
            'zip_code' => '13070-172',
            'cep' => '13070-172',
            'latitude' => -22.9110,
            'longitude' => -47.0553,

            // Entrega / canteiro de obras
            'delivery_address' => 'Rodovia Anhanguera, km 98 — Canteiro de Obras Setor 4, Campinas - SP',
            'site_manager' => 'Marcos Antônio Ferreira',
            'site_phone' => '(19) 3251-4470',

            // Contatos e setores
            'contact_name' => 'Marcos Antônio Ferreira',
            'phone' => '(19) 3251-4400',
            'whatsapp' => '(19) 99911-2233',
            'email_financial' => 'financeiro@respostarapida.com.br',
            'email_purchasing' => 'suprimentos@respostarapida.com.br',

            // Representante legal
            'legal_name' => 'Marcos Antônio Ferreira',
            'legal_cpf' => '234.567.890-11',
            'legal_rg' => '34.567.890-1',
            'legal_role' => 'Sócio-Administrador',

            // Checklist de documentos — todos anexados
            'doc_cnpj' => true,
            'doc_statute' => true,
            'doc_id' => true,
            'doc_proxy' => true,
            'doc_address' => true,
            'doc_art' => true,
            'doc_registration_form' => true,

            // Análise de risco — aprovado
            'check_internal_fraud' => true,
            'check_blacklist' => true,
            'check_credit_bureau' => true,
            'credit_score' => 812,
        ]);

        $this->info('Ficha cadastral da Construtora Resposta Rápida Ltda completada.');

        return self::SUCCESS;
    }
}
