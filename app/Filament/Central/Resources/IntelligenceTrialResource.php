<?php

namespace App\Filament\Central\Resources;

use App\Domain\Intelligence\Trial\Enums\StatusConta;
use App\Domain\Intelligence\Trial\Models\IntelligenceTrial;
use App\Domain\Intelligence\Trial\Services\TrialCiclo;
use App\Domain\Intelligence\Trial\Support\TelefoneBr;
use App\Filament\Central\Resources\IntelligenceTrialResource\Pages;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Central › Testes do Intelligence: cadastros de teste grátis, dias restantes, status, plano e link da assinatura no Asaas. Só super admin. */
class IntelligenceTrialResource extends Resource
{
    protected static ?string $model = IntelligenceTrial::class;

    protected static ?string $navigationIcon = 'heroicon-o-beaker';

    protected static ?string $navigationLabel = 'Testes do Intelligence';

    protected static ?string $navigationGroup = 'Clientes e Contratos';

    protected static ?int $navigationSort = 9;

    protected static ?string $modelLabel = 'Teste do Intelligence';

    protected static ?string $pluralModelLabel = 'Testes do Intelligence';

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->isSuperAdmin();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('empresa')->label('Empresa')->searchable()->description(fn (IntelligenceTrial $r) => $r->nome),
                Tables\Columns\TextColumn::make('whatsapp')->label('WhatsApp')->formatStateUsing(fn ($state) => TelefoneBr::formatar($state))->searchable(),
                Tables\Columns\TextColumn::make('status_atual')->label('Status')->badge()
                    ->state(fn (IntelligenceTrial $r) => $r->statusAtual())
                    ->formatStateUsing(fn (StatusConta $state) => $state->rotulo())
                    ->color(fn (StatusConta $state) => $state->cor()),
                Tables\Columns\TextColumn::make('trial_ends_at')->label('Termina em')->date('d/m/Y')->placeholder('Não começou'),
                Tables\Columns\TextColumn::make('dias')->label('Dias restantes')->state(fn (IntelligenceTrial $r) => $r->diasRestantes())->placeholder('—'),
                Tables\Columns\TextColumn::make('last_upload_at')->label('Último envio')->dateTime('d/m/Y H:i')->placeholder('Nenhum'),
                Tables\Columns\TextColumn::make('plano_nome')->label('Plano')->placeholder('Sem plano')
                    ->description(fn (IntelligenceTrial $r) => $r->plano_preco ? 'R$ '.number_format((float) $r->plano_preco, 2, ',', '.').'/mês' : null),
                Tables\Columns\TextColumn::make('asaas_subscription_id')->label('Assinatura Asaas')->placeholder('—')
                    ->url(fn (IntelligenceTrial $r) => $r->asaas_subscription_id ? self::asaasUrl($r->asaas_subscription_id) : null, true),
                Tables\Columns\TextColumn::make('created_at')->label('Cadastro')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label('Status')->options(StatusConta::opcoes()),
                Tables\Filters\Filter::make('vence_7_dias')->label('Vence nos próximos 7 dias')
                    ->query(fn (Builder $q) => $q->whereBetween('trial_ends_at', [now(), now()->addDays(7)])->whereNull('contracted_at')),
            ])
            ->actions([
                Tables\Actions\Action::make('estender')->label('Estender teste')->icon('heroicon-o-clock')
                    ->form([
                        Forms\Components\TextInput::make('dias')->label('Dias a somar')->numeric()->minValue(1)->maxValue(90)->default(7)->required(),
                        Forms\Components\Textarea::make('motivo')->label('Motivo')->required()->maxLength(300),
                    ])
                    ->action(function (IntelligenceTrial $r, array $data) {
                        app(TrialCiclo::class)->estender($r, (int) $data['dias'], $data['motivo'], auth()->user()?->email);
                        Notification::make()->title('Teste estendido até '.$r->fresh()->trial_ends_at->format('d/m/Y'))->success()->send();
                    }),
            ]);
    }

    private static function asaasUrl(string $subscriptionId): string
    {
        $host = str_contains((string) config('services.asaas.base_url'), 'sandbox') ? 'https://sandbox.asaas.com' : 'https://www.asaas.com';

        return "{$host}/subscriptions/{$subscriptionId}";
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListIntelligenceTrials::route('/')];
    }
}
