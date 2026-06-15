<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Gate;
use App\Models\User;

class DebugPermissions extends Command
{
    protected $signature = 'debug:permissions';
    public function handle()
    {
        $user = User::where('name', 'Fred')->first();
        if (!$user) { $this->error('Fred nao encontrado'); return; }

        $resources = [
            'ChatRoom' => \App\Models\ChatRoom::class,
            'MaintenanceOrder' => \App\Models\MaintenanceOrder::class,
            'Material' => \App\Models\Material::class,
            'SolicitacaoLocacao' => \App\Models\SolicitacaoLocacao::class,
            'User' => \App\Models\User::class,
        ];

        foreach ($resources as $name => $class) {
            $hasPolicy = class_exists('App\\Policies\\' . class_basename($class) . 'Policy') ? 'SIM' : 'NAO';
            $canView = $user->can('viewAny', $class) ? 'PERMITE' : 'NEGA';
            $this->line("$name: policy=$hasPolicy, gate=$canView");
        }
    }
}
