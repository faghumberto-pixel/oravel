<?php

namespace App\Support\Colaborador;

use App\Models\Absence;
use App\Models\Asset;
use App\Models\FrotaAbastecimento;
use App\Models\FrotaSaidaVeiculo;
use App\Models\FrotaVinculoMotorista;
use App\Models\MaintenanceOrder;
use App\Models\Tenant;
use App\Models\TimeClock;
use App\Models\User;
use App\Support\AppProfile;

/**
 * Funções do app do colaborador (cada uma vira um botão no início). Cada função declara o PERFIL MÍNIMO que a vê
 * (colaborador < técnico < gestão, mesma classificação de App\Support\AppProfile) e uma regra de permissão.
 * Para adicionar uma função nova: um item aqui + a rota em routes/colaborador.php.
 */
class Modulos
{
    /** Chave do Contrato que libera o app inteiro para a empresa (Central > Contratos > Configurações). */
    public const FEATURE = 'modulo_app_colaborador';

    public static function liberadoPara(User $user): bool
    {
        return $user->isSuperAdmin() || (bool) $user->tenant?->hasFeature(self::FEATURE);
    }

    /** @return array<int, array{chave: string, area: string, perfil: string, titulo: string, descricao: string, icone: string, rota: string, pode: callable}> */
    public static function todos(): array
    {
        return [
            [
                'chave' => 'logistica',
                'modelo' => FrotaSaidaVeiculo::class,
                'area' => 'Logística',
                'perfil' => AppProfile::COLABORADOR,
                'titulo' => 'Logística',
                'descricao' => 'Entrada e saída de veículos, com motivo e equipamento levado',
                'icone' => '🚚',
                'rota' => 'colab.logistica',
                // Funções dentro do módulo (cada uma pode ser liberada separadamente por usuário): chave => [título, modelo que a contrato libera].
                'recursos' => [
                    'saida' => ['Entrada e saída de veículos', FrotaSaidaVeiculo::class],
                    'abastecimento' => ['Abastecimento', FrotaAbastecimento::class],
                    'km' => ['Marcar km', FrotaSaidaVeiculo::class],
                    'motorista' => ['Motorista do veículo', FrotaVinculoMotorista::class],
                    'km-rodado' => ['Km rodado e percursos', FrotaSaidaVeiculo::class],
                    'vencimentos' => ['Vencimentos da documentação', Asset::class],
                ],
                'pode' => fn (User $u) => $u->can('viewAny', FrotaSaidaVeiculo::class),
                'selo' => fn (User $u) => FrotaSaidaVeiculo::fora()->count(),
            ],
            [
                'chave' => 'minhas_os',
                'modelo' => MaintenanceOrder::class,
                'area' => 'Manutenção',
                'perfil' => AppProfile::TECNICO,
                'titulo' => 'Minhas ordens de serviço',
                'descricao' => 'As OS atribuídas a você',
                'icone' => '🧰',
                'rota' => 'colab.minhas-os',
                'pode' => fn (User $u) => $u->can('viewAny', MaintenanceOrder::class),
                'selo' => fn (User $u) => MaintenanceOrder::query()->where('technician_id', $u->id)->whereNotIn('status', SituacaoOs::FECHADAS)->count(),
            ],
            [
                'chave' => 'escanear',
                'modelo' => Asset::class,
                'area' => 'Ativos',
                'perfil' => AppProfile::TECNICO,
                'titulo' => 'Ler QR do equipamento',
                'descricao' => 'Aponte a câmera para a etiqueta do ativo',
                'icone' => '📷',
                'rota' => 'colab.escanear',
                'pode' => fn (User $u) => $u->can('viewAny', Asset::class),
            ],
            [
                'chave' => 'dossie',
                'modelo' => Asset::class,
                'area' => 'Ativos',
                'perfil' => AppProfile::TECNICO,
                'titulo' => 'Dossiê do equipamento',
                'descricao' => 'Busque pelo patrimônio, TAG ou nome',
                'icone' => '🔎',
                'rota' => 'colab.dossie',
                'pode' => fn (User $u) => $u->can('viewAny', Asset::class),
            ],
            [
                'chave' => 'os',
                'modelo' => MaintenanceOrder::class,
                'area' => 'Manutenção',
                'perfil' => AppProfile::TECNICO,
                'titulo' => 'OS do equipamento',
                'descricao' => 'Veja e abra as OS pelo QR, patrimônio ou placa',
                'icone' => '🛠️',
                'rota' => 'colab.os.buscar',
                'pode' => fn (User $u) => $u->can('viewAny', MaintenanceOrder::class),
            ],
            [
                'chave' => 'ponto',
                'modelo' => TimeClock::class,
                'area' => 'Departamento Pessoal',
                'perfil' => AppProfile::COLABORADOR,
                'titulo' => 'Registro de ponto',
                'descricao' => 'Entrada, saída e intervalos',
                'icone' => '⏱️',
                'rota' => 'colab.ponto',
                'pode' => fn (User $u) => $u->can('viewAny', TimeClock::class),
            ],
            [
                'chave' => 'ausencias',
                'modelo' => Absence::class,
                'area' => 'Departamento Pessoal',
                'perfil' => AppProfile::COLABORADOR,
                'titulo' => 'Faltas e ausências',
                'descricao' => 'Comunique uma falta com foto do atestado e CID',
                'icone' => '🩺',
                'rota' => 'colab.ausencias',
                'pode' => fn (User $u) => $u->can('viewAny', Absence::class),
            ],
        ];
    }

    private const NIVEL = [AppProfile::COLABORADOR => 0, AppProfile::TECNICO => 1, AppProfile::ADMIN => 2];

    /** Seções da tela inicial do app, na ordem em que aparecem (a mesma ideia dos menus do painel). */
    public const AREAS = ['Departamento Pessoal', 'Logística', 'Manutenção', 'Ativos'];

    /**
     * Liberação por usuário, configurada pelo administrador da empresa (users.app_access). NULL = nunca configurado:
     * vale o padrão (perfil + permissão). Configurado: só o que estiver marcado — o módulo ("logistica") e, para as
     * funções internas, cada uma ("logistica.abastecimento").
     */
    public static function acesso(User $user, string $chave, ?string $recurso = null): bool
    {
        $lista = $user->app_access;

        if (! is_array($lista)) {
            return true;
        }

        return in_array($chave, $lista, true) && ($recurso === null || in_array("{$chave}.{$recurso}", $lista, true));
    }

    /** Vale para qualquer um dos módulos (telas compartilhadas, como a OS que serve a "Minhas OS" e a "OS do equipamento"). */
    public static function acessoAlgum(User $user, array $chaves, ?string $recurso = null): bool
    {
        foreach ($chaves as $chave) {
            if (self::acesso($user, $chave, $recurso)) {
                return true;
            }
        }

        return false;
    }

    /**
     * O que o administrador pode marcar para os usuários da empresa: só o que o Contrato da empresa libera.
     *
     * @return array<string, array<int, array{chave: string, titulo: string, descricao: string, recursos: array<string, string>}>> por área
     */
    public static function catalogo(?Tenant $tenant): array
    {
        $libera = fn (string $modelo) => ! $tenant || ! $modelo::saasFeatureKey() || $tenant->hasFeature($modelo::saasFeatureKey());
        $porArea = [];

        foreach (self::todos() as $m) {
            if (! $libera($m['modelo'])) {
                continue;
            }

            $recursos = collect($m['recursos'] ?? [])->filter(fn ($r) => $libera($r[1]))->map(fn ($r) => $r[0])->all();
            $porArea[$m['area']][] = ['chave' => $m['chave'], 'titulo' => $m['titulo'], 'descricao' => $m['descricao'], 'recursos' => $recursos];
        }

        return collect(self::AREAS)->filter(fn ($a) => isset($porArea[$a]))->mapWithKeys(fn ($a) => [$a => $porArea[$a]])->all();
    }

    /** @return array<int, array<string, mixed>> só o que o perfil do usuário alcança e a permissão dele permite */
    public static function para(User $user): array
    {
        $nivel = self::NIVEL[AppProfile::for($user)];

        return array_values(array_filter(
            self::todos(),
            fn (array $m) => self::NIVEL[$m['perfil']] <= $nivel && self::acesso($user, $m['chave']) && ($m['pode'])($user),
        ));
    }

    /** @return array<string, array<int, array<string, mixed>>> funções agrupadas por área (Departamento Pessoal, Logística, Manutenção, Ativos) */
    public static function agrupados(User $user): array
    {
        $porArea = [];
        foreach (self::para($user) as $modulo) {
            $porArea[$modulo['area']][] = $modulo;
        }

        // Na ordem das áreas; quem não tem nenhuma função numa área não vê o título dela.
        return collect(self::AREAS)->filter(fn ($a) => isset($porArea[$a]))->mapWithKeys(fn ($a) => [$a => $porArea[$a]])->all();
    }
}
