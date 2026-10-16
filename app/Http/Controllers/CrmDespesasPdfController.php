<?php

namespace App\Http\Controllers;

use App\Filament\Resources\CrmDespesaResource;
use App\Models\CrmVisitaDespesa;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** PDF de acerto de despesas de campo por período/vendedor. Respeita a visibilidade e limita as linhas (dompdf é pesado). */
class CrmDespesasPdfController extends Controller
{
    public const LIMITE = 300;

    public function __invoke(Request $request)
    {
        Gate::authorize('viewAny', CrmVisitaDespesa::class);

        $dados = $request->validate([
            'de' => ['required', 'date'], 'ate' => ['required', 'date', 'after_or_equal:de'], 'user_id' => ['nullable', 'uuid'],
        ]);

        $query = CrmDespesaResource::getEloquentQuery()
            ->whereDate('data', '>=', $dados['de'])->whereDate('data', '<=', $dados['ate'])
            ->when($dados['user_id'] ?? null, fn ($q, $v) => $q->where('user_id', $v));

        $total = (clone $query)->count();
        $despesas = $query->with(['vendedor', 'veiculo', 'visita'])->orderBy('user_id')->orderBy('data')->limit(self::LIMITE)->get();
        $porVendedor = $despesas->groupBy(fn ($d) => $d->vendedor?->name ?? 'Sem vendedor');

        @ini_set('memory_limit', '512M');
        @set_time_limit(120);

        $pdf = Pdf::loadView('pdf.crm-despesas', ['porVendedor' => $porVendedor, 'total' => $total, 'exibidas' => $despesas->count(), 'de' => $dados['de'], 'ate' => $dados['ate'], 'limite' => self::LIMITE])
            ->setPaper('a4', 'portrait');

        return response()->streamDownload(fn () => print ($pdf->output()), 'acerto-despesas-'.now()->format('Ymd-His').'.pdf');
    }
}
