<?php

namespace App\Support\Colaborador;

use App\Models\Absence;
use App\Models\Asset;
use App\Models\FrotaSaidaVeiculo;
use App\Models\MaintenanceOrder;
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
                'area' => 'Logística',
                'perfil' => AppProfile::COLABORADOR,
                'titulo' => 'Logística',
                'descricao' => 'Entrada e saída de veículos, com motivo e equipamento levado',
                'icone' => '🚚',
                'rota' => 'colab.logistica',
                'pode' => fn (User $u) => $u->can('viewAny', FrotaSaidaVeiculo::class),
                'selo' => fn (User $u) => FrotaSaidaVeiculo::fora()->count(),
            ],
            [
                'chave' => 'minhas_os',
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

    /** @return array<int, array<string, mixed>> só o que o perfil do usuário alcança e a permissão dele permite */
    public static function para(User $user): array
    {
        $nivel = self::NIVEL[AppProfile::for($user)];

        return array_values(array_filter(
            self::todos(),
            fn (array $m) => self::NIVEL[$m['perfil']] <= $nivel && ($m['pode'])($user),
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
