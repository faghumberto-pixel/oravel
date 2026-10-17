<?php

namespace App\Support;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Plan;

/** O que a Academia mostra para um contrato: cursos publicados e aulas liberadas pelos módulos do plano (aulas sem módulo valem para todos). */
class AcademiaDoPlano
{
    /**
     * @param  array<string, mixed>  $features  mapa módulo => true (como em plans.features)
     * @return array{cursos: int, aulas: int, lista: array<string, int>}
     */
    public static function resumo(array $features): array
    {
        $liberados = collect($features)->filter(fn ($v) => $v === true || $v === 1 || $v === 'true')->keys()->all();

        if (! ($features['tabela_courses'] ?? false)) {
            return ['cursos' => 0, 'aulas' => 0, 'lista' => []];
        }

        $lista = [];

        Course::withoutGlobalScopes()->where('is_published', true)->orderBy('position')->get()->each(function (Course $curso) use ($liberados, &$lista) {
            $aulas = Lesson::withoutGlobalScopes()->where('course_id', $curso->id)
                ->where(fn ($q) => $q->whereNull('feature_key')->orWhereIn('feature_key', $liberados))->count();

            if ($aulas > 0) {
                $lista[$curso->title] = $aulas;
            }
        });

        return ['cursos' => count($lista), 'aulas' => array_sum($lista), 'lista' => $lista];
    }

    public static function doPlano(?Plan $plano): array
    {
        return static::resumo($plano?->features ?? []);
    }

    /** Texto curto para a Central. */
    public static function texto(?Plan $plano): string
    {
        $r = static::doPlano($plano);

        if ($r['cursos'] === 0) {
            return 'Academia não liberada neste plano.';
        }

        return $r['cursos'].' curso(s), '.$r['aulas'].' aula(s): '.collect($r['lista'])->map(fn ($n, $titulo) => "{$titulo} ({$n})")->implode(', ').'.';
    }
}
