<?php

namespace App\Console\Commands;

use App\Models\Contract;
use App\Models\DocumentSignature;
use App\Models\MaintenanceOrder;
use App\Models\Tenant;
use App\Services\SignatureService;
use Illuminate\Console\Command;

class GenerateSignatureLink extends Command
{
    protected $signature = 'signature:generate-link
                            {--type=contract : Tipo de documento (contract ou maintenance-order)}
                            {--id= : ID do documento}
                            {--name= : Nome do signatário}
                            {--email= : E-mail do signatário}
                            {--document= : CPF/CNPJ do signatário}
                            {--phone= : Telefone do signatário}
                            {--tenant= : ID ou slug do tenant}';

    protected $description = 'Gera um link de assinatura eletrônica para um contrato ou ordem de serviço';

    public function __construct(
        private SignatureService $signatureService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $type = $this->option('type');
        $documentId = $this->option('id');
        $tenantId = $this->option('tenant');

        // Resolve tenant
        if (! $tenantId) {
            $tenants = Tenant::all();
            if ($tenants->isEmpty()) {
                $this->error('Nenhum tenant encontrado no banco de dados');
                return 1;
            }

            $tenantId = $this->choice(
                'Selecione um tenant:',
                $tenants->map(fn ($t) => "{$t->id} - {$t->name}")->toArray()
            );

            $tenantId = explode(' - ', $tenantId)[0];
        }

        $tenant = Tenant::findOrFail($tenantId);

        // Resolve documento
        $modelClass = $type === 'contract' ? Contract::class : MaintenanceOrder::class;
        $document = $modelClass::where('tenant_id', $tenant->id)
            ->find($documentId);

        if (! $document) {
            $this->error("Documento {$type} #{$documentId} não encontrado no tenant {$tenant->name}");
            return 1;
        }

        // Coleta dados do signatário
        $signerData = [];

        $signerData['name'] = $this->option('name') ?? $this->ask('Nome completo do signatário');
        $signerData['email'] = $this->option('email') ?? $this->ask('E-mail (pressione Enter para pular)', null);
        $signerData['document'] = $this->option('document') ?? $this->ask('CPF/CNPJ (pressione Enter para pular)', null);
        $signerData['phone'] = $this->option('phone') ?? $this->ask('Telefone (pressione Enter para pular)', null);

        // Gera link
        $link = $this->signatureService->generateSignatureLink($document, $signerData);

        // Output
        $this->newLine();
        $this->info('✅ Link de assinatura gerado com sucesso!');
        $this->newLine();

        $this->line("<fg=cyan>URL de Assinatura:</>", null);
        $this->line("<info>  {$link}</>");
        $this->newLine();

        $this->line("<fg=cyan>Dados da Assinatura:</>", null);
        $this->line("  Documento: <fg=yellow>{$type}</> #{$document->id}");
        $this->line("  Signatário: <fg=yellow>{$signerData['name']}</>");
        if ($signerData['email']) {
            $this->line("  E-mail: <fg=yellow>{$signerData['email']}</>");
        }
        if ($signerData['document']) {
            $this->line("  Documento: <fg=yellow>{$signerData['document']}</>");
        }
        if ($signerData['phone']) {
            $this->line("  Telefone: <fg=yellow>{$signerData['phone']}</>");
        }
        $this->newLine();

        // Oferece copiar
        if ($this->confirm('Deseja copiar o link para a área de transferência?')) {
            shell_exec("echo '{$link}' | xclip -selection clipboard");
            $this->info('Link copiado!');
        }

        return 0;
    }
}
