<?php

namespace App\Http\Controllers;

use App\Models\AccountReceivable;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Carbon\Carbon;

class BancaryReconciliationExportController extends Controller
{
    public function print()
    {
        $tenant = auth()->user()?->tenant_id;
        if (!$tenant) {
            abort(403);
        }

        $dateStart = request('dateStart') ? Carbon::parse(request('dateStart')) : now()->subDays(30);
        $dateEnd = request('dateEnd') ? Carbon::parse(request('dateEnd')) : now();

        $syncStatus = request('syncStatus');

        $automaticallySettled = $this->getAutomaticallySettled($tenant, $dateStart, $dateEnd, $syncStatus);
        $pendingConfirmation = $this->getPendingConfirmation($tenant, $dateStart, $dateEnd, $syncStatus);
        $manualSettlement = $this->getManualSettlement($tenant, $dateStart, $dateEnd, $syncStatus);

        $summary = [
            'automaticallySettled' => count($automaticallySettled),
            'automaticallySettledAmount' => $automaticallySettled->sum('amount'),
            'pendingConfirmation' => count($pendingConfirmation),
            'pendingConfirmationAmount' => $pendingConfirmation->sum('amount'),
            'manualSettlement' => count($manualSettlement),
            'manualSettlementAmount' => $manualSettlement->sum('amount'),
        ];

        return view('prints.bancary-reconciliation-print', compact(
            'automaticallySettled',
            'pendingConfirmation',
            'manualSettlement',
            'summary',
            'dateStart',
            'dateEnd',
            'syncStatus'
        ));
    }

    public function excel(): StreamedResponse
    {
        $tenant = auth()->user()?->tenant_id;
        if (!$tenant) {
            abort(403);
        }

        $dateStart = request('dateStart') ? Carbon::parse(request('dateStart')) : now()->subDays(30);
        $dateEnd = request('dateEnd') ? Carbon::parse(request('dateEnd')) : now();

        $syncStatus = request('syncStatus');

        $automaticallySettled = $this->getAutomaticallySettled($tenant, $dateStart, $dateEnd, $syncStatus);
        $pendingConfirmation = $this->getPendingConfirmation($tenant, $dateStart, $dateEnd, $syncStatus);
        $manualSettlement = $this->getManualSettlement($tenant, $dateStart, $dateEnd, $syncStatus);

        $response = new StreamedResponse(function () use ($automaticallySettled, $pendingConfirmation, $manualSettlement) {
            $handle = fopen('php://output', 'w');

            // BOM para UTF-8 no Excel
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            // Baixadas Automaticamente
            fputcsv($handle, ['BAIXADAS AUTOMATICAMENTE'], ';');
            fputcsv($handle, ['Descrição', 'Cliente', 'Valor', 'Vencimento', 'Recebimento', 'Asaas ID'], ';');
            foreach ($automaticallySettled as $record) {
                fputcsv($handle, [
                    $record->description,
                    $record->client?->name ?? '—',
                    'R$ ' . number_format($record->amount, 2, ',', '.'),
                    $record->due_date->format('d/m/Y'),
                    $record->payment_date?->format('d/m/Y') ?? '—',
                    substr($record->asaas_payment_id, 0, 12) . '...',
                ], ';');
            }
            fputcsv($handle, []);

            // Pendentes de Confirmação
            fputcsv($handle, ['PENDENTES DE CONFIRMAÇÃO'], ';');
            fputcsv($handle, ['Descrição', 'Cliente', 'Valor', 'Vencimento', 'Status', 'Asaas ID'], ';');
            foreach ($pendingConfirmation as $record) {
                fputcsv($handle, [
                    $record->description,
                    $record->client?->name ?? '—',
                    'R$ ' . number_format($record->amount, 2, ',', '.'),
                    $record->due_date->format('d/m/Y'),
                    ucfirst($record->status),
                    substr($record->asaas_payment_id, 0, 12) . '...',
                ], ';');
            }
            fputcsv($handle, []);

            // Cobrança Manual
            fputcsv($handle, ['COBRANÇA MANUAL'], ';');
            fputcsv($handle, ['Descrição', 'Cliente', 'Valor', 'Vencimento', 'Status'], ';');
            foreach ($manualSettlement as $record) {
                fputcsv($handle, [
                    $record->description,
                    $record->client?->name ?? '—',
                    'R$ ' . number_format($record->amount, 2, ',', '.'),
                    $record->due_date->format('d/m/Y'),
                    ucfirst($record->status),
                ], ';');
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="conciliacao-bancaria-' . now()->format('Y-m-d') . '.csv"');

        return $response;
    }

    private function getAutomaticallySettled($tenant, $dateStart, $dateEnd, $syncStatus)
    {
        $query = AccountReceivable::where('tenant_id', $tenant)
            ->whereNotNull('asaas_payment_id')
            ->where('status', 'pago')
            ->whereBetween('payment_date', [$dateStart, $dateEnd]);

        if ($syncStatus && $syncStatus !== 'automatic') {
            return collect();
        }

        return $query->orderBy('payment_date', 'desc')->get();
    }

    private function getPendingConfirmation($tenant, $dateStart, $dateEnd, $syncStatus)
    {
        $query = AccountReceivable::where('tenant_id', $tenant)
            ->whereNotNull('asaas_payment_id')
            ->whereIn('status', ['pendente', 'atrasado'])
            ->whereBetween('due_date', [$dateStart, $dateEnd]);

        if ($syncStatus && $syncStatus !== 'pending') {
            return collect();
        }

        return $query->orderBy('due_date', 'desc')->get();
    }

    private function getManualSettlement($tenant, $dateStart, $dateEnd, $syncStatus)
    {
        $query = AccountReceivable::where('tenant_id', $tenant)
            ->whereNull('asaas_payment_id')
            ->whereBetween('due_date', [$dateStart, $dateEnd]);

        if ($syncStatus && $syncStatus !== 'manual') {
            return collect();
        }

        return $query->orderBy('due_date', 'desc')->get();
    }
}
