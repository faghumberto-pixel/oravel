<?php

namespace App\Http\Controllers;

use App\Support\Tenancy;
use Filament\Facades\Filament;
use Illuminate\Http\Request;

/**
 * Botao "Imprimir" generico das telas de detalhe (ViewRecord) de qualquer
 * Filament Resource -- ver App\Filament\Concerns\HasPrintAction, aplicado
 * em cada Pages\View{Model}. Reaproveita o mesmo mapeamento de colunas por
 * Model (TablePrintController::columnsFor()) e a mesma view de impressao
 * (reports.table-print) usadas pelo botao "Imprimir" das listagens --
 * aqui so' passa uma colecao de 1 registro em vez de uma lista filtrada.
 *
 * O Resource e' resolvido pelo slug (nao por FQCN cru na URL) para nao
 * permitir instanciar/consultar uma classe arbitraria a partir da
 * querystring -- so' Models que ja tem um Resource Filament registrado
 * (e portanto ja passam pela Policy/gate normal do Filament) podem ser
 * impressos por aqui.
 */
class GenericRecordPrintController extends Controller
{
    public function show(Request $request, string $resource, string $record)
    {
        $resourceClass = collect(Filament::getResources())
            ->first(fn (string $class) => $class::getSlug() === $resource);

        abort_if(! $resourceClass, 404);

        $modelClass = $resourceClass::getModel();

        // BelongsToTenant (global scope) ja restringe a consulta ao tenant
        // do usuario logado -- um findOrFail aqui nao vaza registro de
        // outro tenant, so' comporta-se como "nao encontrado" nesse caso.
        $model = $modelClass::findOrFail($record);

        abort_unless($request->user()?->can('view', $model), 403);

        [$columns, $with] = TablePrintController::columnsFor($modelClass);

        if ($with) {
            $model->load($with);
        }

        return view('reports.table-print', [
            'titulo' => $resourceClass::getModelLabel(),
            'filtros' => [],
            'columns' => $columns,
            'records' => collect([$model]),
            'generatedAt' => now(),
            'tenantName' => Tenancy::current()?->name,
        ]);
    }
}
