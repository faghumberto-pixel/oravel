<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Console\Command;

class BackfillEmployeesFromUsers extends Command
{
    protected $signature = 'tenant:backfill-employees {--apply : Sem essa flag roda em modo dry-run (só lista, não grava nada)}';

    protected $description = 'Cria um Employee (status incompleto, CPF placeholder) para todo User com tenant que ainda não tem Employee vinculado';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        $users = User::withoutGlobalScopes()
            ->whereNotNull('tenant_id')
            ->orderBy('tenant_id')
            ->get();

        $orphans = $users->reject(
            fn (User $user) => Employee::withoutGlobalScopes()->where('user_id', $user->id)->exists()
        );

        if ($orphans->isEmpty()) {
            $this->info('Nenhum User sem Employee. Nada a fazer.');

            return self::SUCCESS;
        }

        $this->warn(($apply ? 'Criando' : '[DRY-RUN] Encontrados').' '.$orphans->count().' User(s) sem Employee:');
        $this->newLine();

        // Contador de placeholder por tenant, pra respeitar o unique(tenant_id, cpf).
        $sequenceByTenant = [];

        foreach ($orphans->groupBy('tenant_id') as $tenantId => $tenantUsers) {
            $this->line("Tenant {$tenantId}:");

            foreach ($tenantUsers as $user) {
                $sequenceByTenant[$tenantId] = ($sequenceByTenant[$tenantId] ?? 0) + 1;
                $placeholderCpf = Employee::CPF_PLACEHOLDER_PREFIX.str_pad((string) $sequenceByTenant[$tenantId], 6, '0', STR_PAD_LEFT);

                $this->line("  - {$user->name} ({$user->email})".($apply ? " -> Employee cpf={$placeholderCpf}" : ''));

                if ($apply) {
                    Employee::create([
                        'tenant_id' => $tenantId,
                        'user_id' => $user->id,
                        'department_id' => $user->department_id,
                        'name' => $user->name,
                        'cpf' => $placeholderCpf,
                        'status' => Employee::STATUS_INCOMPLETO,
                    ]);
                }
            }
        }

        $this->newLine();

        if (! $apply) {
            $this->comment('Modo dry-run -- nada foi gravado. Rode com --apply para criar os registros.');

            return self::SUCCESS;
        }

        $this->info('Backfill concluído. Os Employees criados têm CPF placeholder (prefixo '.Employee::CPF_PLACEHOLDER_PREFIX.') e status "Incompleto" -- precisam ser completados com o CPF real em Departamento Pessoal > Colaboradores antes de bater ponto de verdade.');

        return self::SUCCESS;
    }
}
