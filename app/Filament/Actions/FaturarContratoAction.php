<?php

namespace App\Filament\Actions;

use App\Domain\Fleet\Models\ContractMeasurement;
use App\Filament\Resources\AccountReceivableResource;
use App\Models\AccountReceivable;
use App\Models\Contract;
use App\Models\User;
use App\Services\ContractCostService;
use App\Services\ContractMeasurementService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Components\Component;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Filament\Tables;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Botão "Faturar" do Contrato: gera a cobrança no Contas a Receber sem o
 * usuário passar pela tela Medições de Contrato. Por baixo cria a mesma
 * ContractMeasurement e percorre o mesmo fluxo (rascunho -> enviada ->
 * aprovada -> faturada) numa única transação, então o histórico e as
 * travas de ContractMeasurement continuam valendo.
 */
class FaturarContratoAction
{
    public static function table(): Tables\Actions\Action
    {
        return static::configure(Tables\Actions\Action::make('faturar'));
    }

    public static function header(): Action
    {
        return static::configure(Action::make('faturar'));
    }

    /**
     * @template T of \Filament\Actions\Action|Tables\Actions\Action
     *
     * @param  T  $action
     * @return T
     */
    protected static function configure($action)
    {
        return $action
            ->label('Faturar')
            ->icon('heroicon-o-banknotes')
            ->color('success')
            ->modalHeading('Faturar contrato')
            ->modalSubmitActionLabel('Faturar')
            ->visible(fn (Contract $record) => static::canFaturar($record))
            ->form(fn (Contract $record) => static::formSchema($record))
            ->action(function (Contract $record, array $data) {
                static::executar($record, $data);
            });
    }

    public static function canFaturar(Contract $contract): bool
    {
        $user = auth()->user();

        return $contract->status === 'Ativo'
            && $user
            && $user->can('create', ContractMeasurement::class)
            && $user->can('update', new ContractMeasurement(['tenant_id' => $contract->tenant_id]));
    }

    /**
     * @return array<int, Component>
     */
    protected static function formSchema(Contract $contract): array
    {
        $manual = $contract->billing_type === Contract::BILLING_POR_HORA;
        $inicio = now()->subMonth()->startOfMonth();
        $fim = now()->subMonth()->endOfMonth();

        return [
            Forms\Components\DatePicker::make('periodo_inicio')
                ->label('Início do período')
                ->default($inicio->toDateString())
                ->required()->live(),
            Forms\Components\DatePicker::make('periodo_fim')
                ->label('Fim do período')
                ->default($fim->toDateString())
                ->required()->live()
                ->afterOrEqual('periodo_inicio'),
            Forms\Components\DatePicker::make('vencimento')
                ->label('Vencimento')
                ->default(now()->addDays(15)->toDateString())
                ->required(),
            Forms\Components\Toggle::make('incluir_servicos')
                ->label('Faturar também os serviços vinculados')
                ->helperText(fn () => 'Cada serviço gera a própria cobrança: '.static::servicosVinculados($contract)
                    ->map(fn (Contract $c) => (Contract::serviceCategoryLabels()[$c->service_category] ?? $c->service_category).' (#'.$c->contract_number.')')
                    ->implode(', ').'.')
                ->default(true)
                ->visible(fn () => static::servicosVinculados($contract)->isNotEmpty()),
            $manual
                ? Forms\Components\TextInput::make('valor_manual')
                    ->label('Valor a cobrar (R$)')
                    ->helperText('Contratos por hora não têm cálculo automático: informe o valor do período.')
                    ->numeric()->minValue(0.01)->required()->prefix('R$')
                : Forms\Components\Placeholder::make('previa')
                    ->label('Prévia do valor')
                    ->content(fn (Forms\Get $get) => static::previa($contract, $get('periodo_inicio'), $get('periodo_fim'))),
        ];
    }

    protected static function previa(Contract $contract, ?string $inicio, ?string $fim): string
    {
        if (! $inicio || ! $fim || $fim < $inicio) {
            return 'Informe um período válido.';
        }

        try {
            $calc = app(ContractMeasurementService::class)
                ->calculateForPeriod($contract, Carbon::parse($inicio)->startOfDay(), Carbon::parse($fim)->endOfDay());
        } catch (\Throwable $e) {
            report($e);

            return 'Não foi possível calcular o período.';
        }

        $texto = sprintf(
            'Total R$ %s — base R$ %s (%d de %d dias)',
            number_format($calc['total_amount'], 2, ',', '.'),
            number_format($calc['base_amount'], 2, ',', '.'),
            $calc['prorated_days'],
            $calc['total_days_in_period'],
        );

        if ($calc['excess_hours_amount'] > 0) {
            $texto .= ' + excedente de horas R$ '.number_format($calc['excess_hours_amount'], 2, ',', '.');
        }

        if ($calc['overage_conflict']) {
            $texto .= ' — atenção: excedente não calculado ('.$calc['overage_conflict'].')';
        }

        return $texto;
    }

    protected static function executar(Contract $contract, array $data): void
    {
        $inicio = Carbon::parse($data['periodo_inicio'])->startOfDay();
        $fim = Carbon::parse($data['periodo_fim'])->endOfDay();
        $vencimento = Carbon::parse($data['vencimento'])->startOfDay();
        $manual = $contract->billing_type === Contract::BILLING_POR_HORA;

        /** @var User $user */
        $user = auth()->user();

        if (! static::canFaturar($contract)) {
            static::erro('Você não tem permissão para faturar este contrato.');

            return;
        }

        if ($erro = static::validarPeriodo($contract, $inicio, $fim)) {
            static::erro($erro);

            return;
        }

        $contratos = collect([$contract]);
        $pulados = [];

        if (! empty($data['incluir_servicos'])) {
            foreach (static::servicosVinculados($contract) as $servico) {
                if ($motivo = static::validarPeriodo($servico, $inicio, $fim)) {
                    $pulados[] = '#'.$servico->contract_number.': '.$motivo;
                } else {
                    $contratos->push($servico);
                }
            }
        }

        try {
            $recebiveis = DB::transaction(function () use ($contratos, $inicio, $fim, $vencimento, $manual, $data, $user) {
                return $contratos->map(fn (Contract $c) => static::faturarUm(
                    $c, $inicio, $fim, $vencimento, $c->is($contratos->first()) && $manual, (float) ($data['valor_manual'] ?? 0), $user
                ));
            });
        } catch (\RuntimeException $e) {
            static::erro($e->getMessage());

            return;
        }

        $corpo = $recebiveis->count() > 1
            ? sprintf('%d cobranças, total R$ %s, a vencer em %s.', $recebiveis->count(), number_format((float) $recebiveis->sum('amount'), 2, ',', '.'), $vencimento->format('d/m/Y'))
            : sprintf('R$ %s a vencer em %s.', number_format((float) $recebiveis->first()->amount, 2, ',', '.'), $vencimento->format('d/m/Y'));

        if ($pulados) {
            $corpo .= ' Não incluídos: '.implode(' | ', $pulados);
        }

        Notification::make()
            ->title('Cobrança gerada')
            ->body($corpo)
            ->success()
            ->actions([
                NotificationAction::make('ver')
                    ->label('Ver no Contas a Receber')
                    ->url(AccountReceivableResource::getUrl('index'))
                    ->button(),
            ])
            ->send();
    }

    /**
     * Contratos de serviço (mão de obra, segurança, acessórios, insumos) vendidos junto com esta
     * locação (mesma solicitação) e já ativos. Vazio para um contrato que já é de serviço.
     *
     * @return Collection<int, Contract>
     */
    protected static function servicosVinculados(Contract $contract): Collection
    {
        if ($contract->service_category || ! $contract->solicitacao_locacao_id) {
            return collect();
        }

        return Contract::where('solicitacao_locacao_id', $contract->solicitacao_locacao_id)
            ->where('id', '!=', $contract->id)
            ->whereNotNull('service_category')
            ->where('status', 'Ativo')
            ->orderBy('service_category')
            ->get();
    }

    protected static function faturarUm(Contract $contract, Carbon $inicio, Carbon $fim, Carbon $vencimento, bool $manual, float $valorManual, User $user): AccountReceivable
    {
        $measurement = $manual
            ? static::criarMedicaoManual($contract, $inicio, $fim, $valorManual)
            : app(ContractMeasurementService::class)->generateForPeriod($contract, $inicio, $fim);

        $measurement->submit();
        $measurement->approve($user);
        $receivable = $measurement->markInvoiced($vencimento);

        activity()
            ->performedOn($contract)
            ->causedBy($user)
            ->withProperties([
                'measurement_id' => $measurement->id,
                'account_receivable_id' => $receivable->id,
                'periodo' => $inicio->toDateString().' a '.$fim->toDateString(),
                'valor' => (float) $receivable->amount,
            ])
            ->log('Contrato faturado');

        return $receivable;
    }

    protected static function validarPeriodo(Contract $contract, Carbon $inicio, Carbon $fim): ?string
    {
        if ($fim->lt($inicio)) {
            return 'O fim do período não pode ser anterior ao início.';
        }

        $inicioContrato = $contract->start_date?->copy()->startOfDay();
        $fimContrato = $contract->end_date?->copy()->endOfDay();

        if (($inicioContrato && $fim->lt($inicioContrato)) || ($fimContrato && $inicio->gt($fimContrato))) {
            return 'O período está fora da vigência do contrato.';
        }

        $sobreposta = ContractMeasurement::where('contract_id', $contract->id)
            ->where('status', '!=', ContractMeasurement::STATUS_REJECTED)
            ->where('reference_period_start', '<=', $fim->toDateString())
            ->where('reference_period_end', '>=', $inicio->toDateString())
            ->first();

        if ($sobreposta) {
            return sprintf(
                'Já existe uma medição deste contrato (%s a %s, %s) que se sobrepõe ao período. Use a tela Medições de Contrato.',
                $sobreposta->reference_period_start->format('d/m/Y'),
                $sobreposta->reference_period_end->format('d/m/Y'),
                ContractMeasurement::statusLabels()[$sobreposta->status] ?? $sobreposta->status,
            );
        }

        return null;
    }

    protected static function criarMedicaoManual(Contract $contract, Carbon $inicio, Carbon $fim, float $valor): ContractMeasurement
    {
        $dias = (int) round($inicio->copy()->startOfDay()->diffInDays($fim->copy()->startOfDay())) + 1;

        return ContractMeasurement::create([
            'tenant_id' => $contract->tenant_id,
            'contract_id' => $contract->id,
            'reference_period_start' => $inicio,
            'reference_period_end' => $fim,
            'total_days_in_period' => $dias,
            'prorated_days' => $dias,
            'total_base_amount' => $valor,
            'total_excess_hours_amount' => 0,
            'total_extras_amount' => 0,
            'total_amount' => round($valor, 2),
            'status' => ContractMeasurement::STATUS_DRAFT,
        ]);
    }

    protected static function erro(string $mensagem): void
    {
        Notification::make()->title('Não foi possível faturar')->body($mensagem)->danger()->send();
    }

    public static function custoMargem(bool $header = false)
    {
        $action = $header ? Action::make('custo_margem') : Tables\Actions\Action::make('custo_margem');

        return $action
            ->label('Custo e margem')
            ->icon('heroicon-o-calculator')
            ->color('gray')
            ->modalHeading('Custo e margem do contrato')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Fechar')
            ->modalContent(fn (Contract $record) => view('filament.contracts.custo-margem', [
                'summary' => app(ContractCostService::class)->summary($record),
            ]));
    }
}
