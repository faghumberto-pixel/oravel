<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\EquipmentDamage;
use App\Models\MaintenanceOrder;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Popula os 4 itens novos da auditoria POP (Quote/Orçamento, causa
 * estruturada em EquipmentDamage, ponte de orçamento indenizatório,
 * e-mail geral de contato, fila real do Financeiro) nos 5 tenants de
 * demonstração já existentes -- não cria tenant novo, só enriquece os
 * que já têm Client/Asset/MaintenanceOrder reais pra ancorar os dados.
 *
 * Idempotente por tenant: se o tenant já tem algum Quote, pula (não
 * duplica a cada rodada).
 *
 * Uso: php artisan db:seed --class=QuoteAndFinanceiroDemoSeeder
 */
class QuoteAndFinanceiroDemoSeeder extends Seeder
{
    private const SLUGS = [
        'torres-guindastes',
        'geradores-rmc',
        'construtora-alicerce-locacoes',
        'eventos-show-geradores',
        'hospital-vida-plena-energia',
    ];

    /**
     * Causa da avaria pré-existente de cada tenant -- varia de propósito
     * pra cobrir os 3 valores possíveis na demo (mau_uso e dano_cliente
     * disparam orçamento indenizatório; desgaste_natural não).
     */
    private const DAMAGE_CAUSES = [
        'torres-guindastes' => EquipmentDamage::CAUSE_MAU_USO,
        'geradores-rmc' => EquipmentDamage::CAUSE_DANO_CLIENTE,
        'construtora-alicerce-locacoes' => EquipmentDamage::CAUSE_DESGASTE_NATURAL,
    ];

    /** Vocabulário de peça/serviço por tenant, pra manter o sotaque de cada nicho já estabelecido pelos seeders originais. */
    private const ITEM_FLAVOR = [
        'torres-guindastes' => ['peca' => 'Cabo de aço para guindaste', 'servico' => 'Inspeção e revisão do sistema hidráulico'],
        'geradores-rmc' => ['peca' => 'Filtro de óleo Cummins', 'servico' => 'Troca de óleo e revisão do motor'],
        'construtora-alicerce-locacoes' => ['peca' => 'Vedação hidráulica para prensa', 'servico' => 'Reparo do sistema hidráulico'],
        'eventos-show-geradores' => ['peca' => 'Bateria de partida 12V', 'servico' => 'Instalação elétrica temporária do evento'],
        'hospital-vida-plena-energia' => ['peca' => 'Bateria reserva 24V', 'servico' => 'Manutenção preventiva do gerador crítico'],
    ];

    public function run(): void
    {
        foreach (self::SLUGS as $slug) {
            $tenant = Tenant::where('slug', $slug)->first();

            if (! $tenant) {
                $this->command?->warn("Tenant '{$slug}' não encontrado -- rode o seeder de demo dele antes deste.");

                continue;
            }

            $this->backfillClientEmails($tenant);
            $this->seedIndemnityQuoteFromExistingDamage($tenant);
            $this->seedCommercialQuotes($tenant);
        }
    }

    private function backfillClientEmails(Tenant $tenant): void
    {
        Client::where('tenant_id', $tenant->id)->whereNull('email')->get()->each(function (Client $client) {
            $client->update(['email' => 'contato@'.Str::slug($client->name, '').'.com.br']);
        });
    }

    /**
     * Se o tenant já tinha uma EquipmentDamage sem causa (todas tinham,
     * antes do campo existir), classifica ela e -- quando a causa
     * responsabiliza o cliente -- gera o orçamento indenizatório de
     * verdade e leva ele até "encaminhado ao Financeiro" (fecha o ciclo
     * dos itens 6, 7 e 9 juntos, na mesma avaria real).
     */
    private function seedIndemnityQuoteFromExistingDamage(Tenant $tenant): void
    {
        $damage = EquipmentDamage::where('tenant_id', $tenant->id)->whereNull('cause')->first();

        if (! $damage) {
            return;
        }

        $damage->update(['cause' => self::DAMAGE_CAUSES[$tenant->slug] ?? EquipmentDamage::CAUSE_DESGASTE_NATURAL]);

        if (! $damage->isBillableToClient()) {
            return;
        }

        // O ativo dessa avaria nasceu sem client_id (dado anterior ao
        // campo de causa); herda do cliente da própria O.S. que gerou o
        // dano -- é o cliente onde o equipamento estava operando na
        // hora da avaria, então é a fonte correta pra backfill.
        $damage->loadMissing('asset', 'maintenanceOrder');
        if (! $damage->asset?->client_id && $damage->maintenanceOrder?->client_id) {
            $damage->asset->update(['client_id' => $damage->maintenanceOrder->client_id]);
        }

        if (! $damage->asset?->client_id) {
            return;
        }

        $admin = User::where('tenant_id', $tenant->id)->whereHas('roles', fn ($q) => $q->where('name', 'admin'))->first();
        $flavor = self::ITEM_FLAVOR[$tenant->slug] ?? ['peca' => 'Peça de reposição', 'servico' => 'Mão de obra'];

        $quote = Quote::create([
            'tenant_id' => $tenant->id,
            'quotable_type' => EquipmentDamage::class,
            'quotable_id' => $damage->id,
            'client_id' => $damage->asset->client_id,
            'assigned_user_id' => $admin?->id,
            'type' => Quote::TYPE_INDENIZATORIO,
        ]);

        $quote->items()->create([
            'tenant_id' => $tenant->id, 'type' => QuoteItem::TYPE_PECA,
            'description' => $flavor['peca'], 'quantity' => 1, 'unit_price' => 890,
        ]);
        $quote->items()->create([
            'tenant_id' => $tenant->id, 'type' => QuoteItem::TYPE_SERVICO,
            'description' => $flavor['servico'].' -- avaria por '.EquipmentDamage::causeLabels()[$damage->cause],
            'quantity' => 1, 'unit_price' => 450,
        ]);

        $quote->send();
        $quote->approve();
        $quote->forwardToFinanceiro(now()->addDays(15));
    }

    /**
     * 3 orçamentos comerciais "comuns" (não-indenizatórios) por tenant,
     * cobrindo os 3 estágios que mais aparecem no dia a dia: rascunho
     * (recém montado), enviado (aguardando resposta do cliente) e
     * terceiro aprovado+encaminhado ao Financeiro (POP 2 + POP 4).
     */
    private function seedCommercialQuotes(Tenant $tenant): void
    {
        // Marcador específico (não "algum Quote existe") -- um tenant pode já
        // ter só o orçamento indenizatório criado por
        // seedIndemnityQuoteFromExistingDamage() e ainda precisar destes 3.
        if (Quote::where('tenant_id', $tenant->id)->where('type', Quote::TYPE_INTERNO)->where('status', Quote::STATUS_RASCUNHO)->exists()) {
            return;
        }

        $admin = User::where('tenant_id', $tenant->id)->whereHas('roles', fn ($q) => $q->where('name', 'admin'))->first();
        $client = Client::where('tenant_id', $tenant->id)->first();
        $order = MaintenanceOrder::where('tenant_id', $tenant->id)->whereNotNull('client_id')->first();

        if (! $admin || ! $client) {
            return;
        }

        $flavor = self::ITEM_FLAVOR[$tenant->slug] ?? ['peca' => 'Peça de reposição', 'servico' => 'Mão de obra'];

        // Rascunho: recém montado pelo Analista de Pós-Venda, ainda coletando valores.
        $draft = Quote::create([
            'tenant_id' => $tenant->id,
            'quotable_type' => $order ? MaintenanceOrder::class : null,
            'quotable_id' => $order?->id,
            'client_id' => $client->id,
            'assigned_user_id' => $admin->id,
            'type' => Quote::TYPE_INTERNO,
        ]);
        $draft->items()->create([
            'tenant_id' => $tenant->id, 'type' => QuoteItem::TYPE_PECA,
            'description' => $flavor['peca'], 'quantity' => 2, 'unit_price' => 320,
        ]);

        // Enviado: já tem link público de aprovação ativo, aguardando o cliente.
        $sent = Quote::create([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'assigned_user_id' => $admin->id,
            'type' => Quote::TYPE_INTERNO,
        ]);
        $sent->items()->create([
            'tenant_id' => $tenant->id, 'type' => QuoteItem::TYPE_SERVICO,
            'description' => $flavor['servico'], 'quantity' => 1, 'unit_price' => 780,
        ]);
        $sent->send();

        // A terceiro, com laudo técnico prévio, aprovado e já encaminhado ao Financeiro.
        $supplier = Supplier::where('tenant_id', $tenant->id)->first();
        $terceiro = Quote::create([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'assigned_user_id' => $admin->id,
            'third_party_supplier_id' => $supplier?->id,
            'type' => Quote::TYPE_TERCEIRO,
            'technical_report' => 'Avaliação técnica prévia: equipamento avaliado em campo, reparo requer '.
                'especialista externo. Peças via Almoxarifado interno, serviço executado pelo fornecedor terceirizado.',
        ]);
        $terceiro->items()->create([
            'tenant_id' => $tenant->id, 'type' => QuoteItem::TYPE_PECA,
            'description' => $flavor['peca'], 'quantity' => 1, 'unit_price' => 610,
        ]);
        $terceiro->items()->create([
            'tenant_id' => $tenant->id, 'type' => QuoteItem::TYPE_SERVICO,
            'description' => $flavor['servico'].' (terceirizado)', 'quantity' => 1, 'unit_price' => 1200,
        ]);
        $terceiro->send();
        $terceiro->approve();
        $terceiro->forwardToFinanceiro(now()->addDays(30));
    }
}
