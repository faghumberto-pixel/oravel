<?php

namespace App\Support\Colaborador;

use App\Models\Asset;
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
    /** @return array<int, array{chave: string, perfil: string, titulo: string, descricao: string, icone: string, rota: string, pode: callable}> */
    public static function todos(): array
    {
        return [
            [
                'chave' => 'escanear',
                'perfil' => AppProfile::TECNICO,
                'titulo' => 'Ler QR do equipamento',
                'descricao' => 'Aponte a câmera para a etiqueta do ativo',
                'icone' => '📷',
                'rota' => 'colab.escanear',
                'pode' => fn (User $u) => $u->can('viewAny', Asset::class),
            ],
            [
                'chave' => 'dossie',
                'perfil' => AppProfile::TECNICO,
                'titulo' => 'Dossiê do equipamento',
                'descricao' => 'Busque pelo patrimônio, TAG ou nome',
                'icone' => '🔎',
                'rota' => 'colab.dossie',
                'pode' => fn (User $u) => $u->can('viewAny', Asset::class),
            ],
            [
                'chave' => 'ponto',
                'perfil' => AppProfile::COLABORADOR,
                'titulo' => 'Registro de ponto',
                'descricao' => 'Entrada, saída e intervalos',
                'icone' => '⏱️',
                'rota' => 'colab.ponto',
                'pode' => fn (User $u) => $u->can('viewAny', TimeClock::class),
            ],
        ];
    }

    private const NIVEL = [AppProfile::COLABORADOR => 0, AppProfile::TECNICO => 1, AppProfile::ADMIN => 2];

    public const GRUPOS = [AppProfile::COLABORADOR => 'Geral', AppProfile::TECNICO => 'Técnico', AppProfile::ADMIN => 'Gestão'];

    /** @return array<int, array<string, mixed>> só o que o perfil do usuário alcança e a permissão dele permite */
    public static function para(User $user): array
    {
        $nivel = self::NIVEL[AppProfile::for($user)];

        return array_values(array_filter(
            self::todos(),
            fn (array $m) => self::NIVEL[$m['perfil']] <= $nivel && ($m['pode'])($user),
        ));
    }

    /** @return array<string, array<int, array<string, mixed>>> módulos agrupados por seção (Geral / Técnico / Gestão) */
    public static function agrupados(User $user): array
    {
        $grupos = [];
        foreach (self::para($user) as $modulo) {
            $grupos[self::GRUPOS[$modulo['perfil']]][] = $modulo;
        }

        return $grupos;
    }
}
