<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CrmMetaResource\Pages;
use App\Models\CrmMeta;
use App\Models\User;
use App\Services\CrmMetasService;
use App\Support\Tenancy;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/** Metas mensais de vendas: valor vendido, negócios ganhos e visitas realizadas, por vendedor ou da equipe. */
class CrmMetaResource extends BaseResource
{
    protected static ?string $model = CrmMeta::class;

    protected static ?string $navigationIcon = 'heroicon-o-flag';

    protected static ?string $navigationLabel = 'Metas';

    protected static ?string $navigationGroup = 'Comercial';

    protected static ?string $navigationParentItem = 'Gestão CRM';

    protected static ?int $navigationSort = 14;

    protected static ?string $modelLabel = 'Meta';

    protected static ?string $pluralModelLabel = 'Metas';

    private static function veTudo(): bool
    {
        return (bool) auth()->user()?->canSeeAllCrmLeads();
    }

    /** Vendedor sem "ver todos" só enxerga a própria meta. */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user && ! $user->canSeeAllCrmLeads()) {
            $query->where('user_id', $user->id);
        }

        return $query;
    }

    // Só quem vê tudo define metas; vendedor consulta a própria.
    public static function canCreate(): bool
    {
        return parent::canCreate() && static::veTudo();
    }

    public static function canEdit(Model $record): bool
    {
        return parent::canEdit($record) && static::veTudo();
    }

    public static function canDelete(Model $record): bool
    {
        return parent::canDelete($record) && static::veTudo();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('user_id')->label('Vendedor')->placeholder('Empresa / equipe (total do time)')->searchable()
                ->options(fn () => User::where('tenant_id', Tenancy::current()?->id)->orderBy('name')->pluck('name', 'id')),
            Forms\Components\DatePicker::make('mes')->label('Mês')->required()->displayFormat('m/Y')->default(now()->startOfMonth())
                ->helperText('Vale para o mês inteiro (use qualquer dia dele).')
                ->rules([fn (Forms\Get $get, ?CrmMeta $record) => function (string $attribute, $value, \Closure $fail) use ($get, $record) {
                    $existe = CrmMeta::query()
                        ->whereDate('mes', Carbon::parse($value)->startOfMonth()->toDateString())
                        ->when($get('user_id'), fn ($q, $u) => $q->where('user_id', $u), fn ($q) => $q->whereNull('user_id'))
                        ->when($record, fn ($q) => $q->whereKeyNot($record->id))->exists();

                    $existe && $fail('Já existe uma meta deste vendedor (ou da equipe) para este mês.');
                }]),
            Forms\Components\TextInput::make('meta_valor')->label('Meta de vendas (R$)')->numeric()->prefix('R$')->minValue(0),
            Forms\Components\TextInput::make('meta_ganhos')->label('Negócios ganhos')->numeric()->minValue(0),
            Forms\Components\TextInput::make('meta_visitas')->label('Visitas realizadas')->numeric()->minValue(0),
            Forms\Components\Textarea::make('observacoes')->label('Observações')->rows(2)->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        $servico = fn () => app(CrmMetasService::class);
        $ind = fn (CrmMeta $r) => $servico()->indicadores((string) $r->tenant_id, $r->user_id, $r->mes);

        return $table
            ->defaultSort('mes', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('mes')->label('Mês')->date('m/Y')->sortable(),
                Tables\Columns\TextColumn::make('vendedor.name')->label('Vendedor')->placeholder('Empresa / equipe'),
                Tables\Columns\TextColumn::make('meta_valor')->label('Meta (R$)')->money('BRL')->placeholder('—'),
                Tables\Columns\TextColumn::make('realizado')->label('Vendido (R$)')->state(fn (CrmMeta $r) => $ind($r)['realizado']['valor'])->money('BRL'),
                Tables\Columns\TextColumn::make('atingimento')->label('Atingimento')->badge()
                    ->state(fn (CrmMeta $r) => $ind($r)['atingimento']['valor'])
                    ->formatStateUsing(fn ($state) => $state === null ? '—' : number_format($state, 0, ',', '.').'%')
                    ->color(fn ($state) => match (true) {
                        $state === null => 'gray', $state >= 100 => 'success', $state >= 60 => 'warning', default => 'danger'
                    }),
                Tables\Columns\TextColumn::make('ganhos')->label('Ganhos')->state(fn (CrmMeta $r) => $ind($r)['realizado']['ganhos'].($r->meta_ganhos ? ' / '.$r->meta_ganhos : '')),
                Tables\Columns\TextColumn::make('visitas')->label('Visitas')->state(fn (CrmMeta $r) => $ind($r)['realizado']['visitas'].($r->meta_visitas ? ' / '.$r->meta_visitas : '')),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('user_id')->label('Vendedor')
                    ->options(fn () => User::where('tenant_id', Tenancy::current()?->id)->orderBy('name')->pluck('name', 'id'))
                    ->visible(fn () => static::veTudo()),
            ])
            ->actions([
                Tables\Actions\Action::make('replicar')->label('Replicar p/ o próximo mês')->icon('heroicon-o-document-duplicate')->color('gray')
                    ->visible(fn () => static::veTudo())
                    ->action(function (CrmMeta $r) {
                        $criada = static::copiar($r, $r->mes->copy()->addMonth());
                        Notification::make()->title($criada ? 'Meta replicada para '.$r->mes->copy()->addMonth()->format('m/Y').'.' : 'Já existe meta no mês seguinte.')->{$criada ? 'success' : 'warning'}()->send();
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('copiarMesAnterior')->label('Copiar metas do mês anterior')->icon('heroicon-o-arrow-path')->color('gray')
                    ->visible(fn () => static::veTudo())
                    ->form([Forms\Components\DatePicker::make('destino')->label('Mês de destino')->displayFormat('m/Y')->default(now()->startOfMonth())->required()])
                    ->action(function (array $data) {
                        $destino = Carbon::parse($data['destino'])->startOfMonth();
                        $copiadas = 0;

                        foreach (CrmMeta::query()->whereDate('mes', $destino->copy()->subMonth()->toDateString())->get() as $meta) {
                            static::copiar($meta, $destino) && $copiadas++;
                        }

                        Notification::make()->title("{$copiadas} meta(s) copiada(s) para ".$destino->format('m/Y').'.')->success()->send();
                    }),
            ]);
    }

    /** Copia a meta para outro mês sem sobrescrever uma já existente. Devolve se criou. */
    public static function copiar(CrmMeta $origem, Carbon $mes): bool
    {
        $existe = CrmMeta::query()->whereDate('mes', $mes->copy()->startOfMonth()->toDateString())
            ->when($origem->user_id, fn ($q, $u) => $q->where('user_id', $u), fn ($q) => $q->whereNull('user_id'))->exists();

        if ($existe) {
            return false;
        }

        CrmMeta::create($origem->only(['tenant_id', 'user_id', 'meta_valor', 'meta_ganhos', 'meta_visitas']) + ['mes' => $mes->copy()->startOfMonth()]);

        return true;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCrmMetas::route('/'),
            'create' => Pages\CreateCrmMeta::route('/create'),
            'edit' => Pages\EditCrmMeta::route('/{record}/edit'),
        ];
    }
}
