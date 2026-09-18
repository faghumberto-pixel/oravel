<?php

namespace App\Http\Controllers;

use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CashflowExportController extends Controller
{
    public function print()
    {
        $tenant = auth()->user()?->tenant_id;
        if (!$tenant) {
            abort(403);
        }

        $dateStart = request('dateStart') ? \Carbon\Carbon::parse(request('dateStart')) : now()->subDays(90);
        $dateEnd = request('dateEnd') ? \Carbon\Carbon::parse(request('dateEnd')) : now();

        $statusFilter = [];
        if (request('status') === 'pendente') {
            $statusFilter = ['pendente'];
        } elseif (request('status') === 'atrasado') {
            $statusFilter = ['atrasado'];
        } elseif (request('status') === 'pago') {
            $statusFilter = ['pago'];
        } else {
            $statusFilter = ['pendente', 'atrasado', 'pago'];
        }

        $ar = \App\Models\AccountReceivable::where('tenant_id', $tenant)
            ->whereBetween('due_date', [$dateStart, $dateEnd])
            ->when(request('status'), fn ($q) => $q->whereIn('status', $statusFilter))
            ->when(request('type') && request('type') !== 'AR', fn ($q) => $q->where(false))
            ->select(
                'due_date as date',
                'description',
                'amount',
                'status',
                'client_id',
                \Illuminate\Support\Facades\DB::raw("'AR' as type")
            );

        $ap = \App\Models\AccountPayable::where('tenant_id', $tenant)
            ->whereBetween('due_date', [$dateStart, $dateEnd])
            ->when(request('status'), fn ($q) => $q->whereIn('status', $statusFilter))
            ->when(request('type') && request('type') !== 'AP', fn ($q) => $q->where(false))
            ->select(
                'due_date as date',
                'description',
                'amount',
                'status',
                \Illuminate\Support\Facades\DB::raw("null as client_id"),
                \Illuminate\Support\Facades\DB::raw("'AP' as type")
            );

        $records = $ar->union($ap)->orderBy('date', 'desc')->get();

        return view('prints.cashflow-print', compact('records', 'dateStart', 'dateEnd'));
    }

    public function excel(): StreamedResponse
    {
        $tenant = auth()->user()?->tenant_id;
        if (!$tenant) {
            abort(403);
        }

        $dateStart = request('dateStart') ? \Carbon\Carbon::parse(request('dateStart')) : now()->subDays(90);
        $dateEnd = request('dateEnd') ? \Carbon\Carbon::parse(request('dateEnd')) : now();

        $statusFilter = [];
        if (request('status') === 'pendente') {
            $statusFilter = ['pendente'];
        } elseif (request('status') === 'atrasado') {
            $statusFilter = ['atrasado'];
        } elseif (request('status') === 'pago') {
            $statusFilter = ['pago'];
        } else {
            $statusFilter = ['pendente', 'atrasado', 'pago'];
        }

        $ar = AccountReceivable::where('tenant_id', $tenant)
            ->whereBetween('due_date', [$dateStart, $dateEnd])
            ->when(request('status'), fn ($q) => $q->whereIn('status', $statusFilter))
            ->when(request('type') && request('type') !== 'AR', fn ($q) => $q->where(false))
            ->select('due_date as date', 'description', 'amount', 'status', 'client_id')
            ->addSelect(\Illuminate\Support\Facades\DB::raw("'AR' as type"))
            ->with('client');

        $ap = AccountPayable::where('tenant_id', $tenant)
            ->whereBetween('due_date', [$dateStart, $dateEnd])
            ->when(request('status'), fn ($q) => $q->whereIn('status', $statusFilter))
            ->when(request('type') && request('type') !== 'AP', fn ($q) => $q->where(false))
            ->select('due_date as date', 'description', 'amount', 'status')
            ->addSelect(\Illuminate\Support\Facades\DB::raw("'AP' as type"), \Illuminate\Support\Facades\DB::raw("null as client_id"));

        $records = $ar->union($ap)->orderBy('date', 'desc')->get()->toArray();

        $response = new StreamedResponse(function () use ($records) {
            $handle = fopen('php://output', 'w');

            // BOM para UTF-8 no Excel
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            // Headers
            fputcsv($handle, ['Data', 'Cliente', 'Descrição', 'Tipo', 'Valor', 'Status'], ';');

            // Dados
            foreach ($records as $record) {
                fputcsv($handle, [
                    \Carbon\Carbon::parse($record['date'])->format('d/m/Y'),
                    $record['type'] === 'AR' && $record['client_id'] ?
                        \App\Models\Client::find($record['client_id'])?->name ?? '-' : '-',
                    $record['description'],
                    $record['type'] === 'AR' ? 'Receber' : 'Pagar',
                    'R$ ' . number_format($record['amount'], 2, ',', '.'),
                    ucfirst($record['status']),
                ], ';');
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="fluxo-caixa-' . now()->format('Y-m-d') . '.csv"');

        return $response;
    }
}
