<?php

namespace App\Filament\Pages;

use App\Filament\Resources\AccountReceivableResource;
use App\Models\AccountReceivable;
use App\Models\TenantAsaasSetting;
use App\Services\AvisosWhatsApp;
use App\Services\Cobranca\CobrancaService;
use App\Support\Tenancy;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Painel de cobranças (complemento "Cobrança automática"): o que vai vencer, o que venceu, o que foi recebido e o que falhou,
 * com as ações do dia a dia (gerar, atualizar, reenviar o aviso por WhatsApp, cancelar).
 */
class PainelCobrancas extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-queue-list';

    protected static ?string $navigationGroup = 'Financeiro';

    protected static ?string $navigationLabel = 'Painel de Cobranças';

    protected static ?string $title = 'Painel de Cobranças';

    protected static ?string $slug = 'painel-cobrancas';

    protected static ?int $navigationSort = 6;

    protected static string $view = 'filament.pages.painel-cobrancas';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('viewAny', AccountReceivable::class) && TenantAsaasSetting::moduloLiberado(Tenancy::current()?->id);
    }

    public function getMaxContentWidth(): MaxWidth
    {
        return MaxWidth::Full;
    }

    /** @return array<string, array{label: string, total: float, qtd: int, cor: string}> */
    public function resumo(): array
    {
        $abertas = fn () => AccountReceivable::query()->where('status', '!=', 'pago');
        $linha = fn (string $label, Builder $q, string $campo, string $cor) => ['label' => $label, 'qtd' => (clone $q)->count(), 'total' => (float) (clone $q)->sum($campo), 'cor' => $cor];

        return [
            'a_vencer' => $linha('A vencer', $abertas()->whereDate('due_date', '>=', today()), 'amount', 'text-warning-600'),
            'vencidas' => $linha('Vencidas', $abertas()->whereDate('due_date', '<', today()), 'amount', 'text-danger-600'),
            'recebidas' => $linha('Recebidas no mês', AccountReceivable::query()->where('status', 'pago')->whereBetween('payment_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()]), 'amount', 'text-success-600'),
            'falhas' => $linha('Com falha na cobrança', AccountReceivable::query()->whereNotNull('cobranca_erro')->whereNull('asaas_payment_id')->where('status', '!=', 'pago'), 'amount', 'text-danger-600'),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(AccountReceivable::query()->with('client'))
            ->defaultSort('due_date')
            ->columns([
                TextColumn::make('client.name')->label('Cliente')->searchable(),
                TextColumn::make('description')->label('Descrição')->limit(40)->searchable(),
                TextColumn::make('amount')->label('Valor')->money('BRL'),
                TextColumn::make('due_date')->label('Vencimento')->date('d/m/Y')->sortable(),
                TextColumn::make('status')->label('Situação')->badge()
                    ->state(fn (AccountReceivable $r) => $r->status === 'pago' ? 'Recebida' : ($r->due_date->isPast() ? 'Vencida' : 'A vencer'))
                    ->color(fn (string $state) => match ($state) {
                        'Recebida' => 'success', 'Vencida' => 'danger', default => 'warning'
                    }),
                TextColumn::make('asaas_status')->label('Cobrança')->badge()->placeholder('Sem cobrança')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'PENDING' => 'Aguardando', 'RECEIVED', 'CONFIRMED', 'RECEIVED_IN_CASH' => 'Paga', 'OVERDUE' => 'Vencida', 'DELETED' => 'Cancelada', 'REFUNDED' => 'Estornada', default => $state,
                    })
                    ->color(fn ($state) => match ($state) {
                        'RECEIVED', 'CONFIRMED', 'RECEIVED_IN_CASH' => 'success', 'OVERDUE' => 'danger', 'PENDING' => 'warning', default => 'gray'
                    }),
                TextColumn::make('cobranca_erro')->label('Falha')->limit(35)->tooltip(fn ($state) => $state)->color('danger')->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('situacao')->label('Mostrar')->native(false)->options([
                    'a_vencer' => 'A vencer', 'vencidas' => 'Vencidas', 'recebidas' => 'Recebidas', 'falhas' => 'Com falha na cobrança', 'sem_cobranca' => 'Sem cobrança no Asaas',
                ])->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                    'a_vencer' => $query->where('status', '!=', 'pago')->whereDate('due_date', '>=', today()),
                    'vencidas' => $query->where('status', '!=', 'pago')->whereDate('due_date', '<', today()),
                    'recebidas' => $query->where('status', 'pago'),
                    'falhas' => $query->whereNotNull('cobranca_erro')->whereNull('asaas_payment_id')->where('status', '!=', 'pago'),
                    'sem_cobranca' => $query->where('status', '!=', 'pago')->whereNull('asaas_payment_id'),
                    default => $query,
                }),
            ])
            ->actions([
                Action::make('gerar')->label('Gerar cobrança')->icon('heroicon-o-qr-code')->color('info')
                    ->visible(fn (AccountReceivable $r) => blank($r->asaas_payment_id) && $r->status !== 'pago')
                    ->action(fn (AccountReceivable $r) => AccountReceivableResource::executarCobranca(fn () => app(CobrancaService::class)->gerar($r), 'Cobrança gerada')),
                Action::make('link')->label('Link')->icon('heroicon-o-link')->color('gray')
                    ->visible(fn (AccountReceivable $r) => filled($r->asaas_invoice_url))
                    ->url(fn (AccountReceivable $r) => $r->asaas_invoice_url)->openUrlInNewTab(),
                Action::make('atualizar')->label('Atualizar')->icon('heroicon-o-arrow-path')->color('gray')
                    ->visible(fn (AccountReceivable $r) => filled($r->asaas_payment_id) && $r->status !== 'pago')
                    ->action(fn (AccountReceivable $r) => AccountReceivableResource::executarCobranca(fn () => app(CobrancaService::class)->sincronizar($r), 'Situação atualizada')),
                Action::make('avisar')->label('Reenviar aviso')->icon('heroicon-o-chat-bubble-left-right')->color('success')
                    ->visible(fn (AccountReceivable $r) => $r->status !== 'pago')
                    ->requiresConfirmation()->modalDescription('Envia de novo o aviso de cobrança por WhatsApp ao cliente.')
                    ->action(function (AccountReceivable $r) {
                        try {
                            AvisosWhatsApp::reenviarCobranca($r);
                            Notification::make()->title('Aviso enviado')->success()->send();
                        } catch (\RuntimeException $e) {
                            Notification::make()->title('Não foi possível enviar')->body($e->getMessage())->warning()->send();
                        }
                    }),
                Action::make('cancelar')->label('Cancelar cobrança')->icon('heroicon-o-x-circle')->color('danger')
                    ->visible(fn (AccountReceivable $r) => filled($r->asaas_payment_id) && $r->status !== 'pago')
                    ->requiresConfirmation()
                    ->action(fn (AccountReceivable $r) => AccountReceivableResource::executarCobranca(fn () => app(CobrancaService::class)->cancelar($r), 'Cobrança cancelada')),
            ]);
    }
}
