<?php

namespace App\Filament\Central\Resources;

use App\Filament\Central\Resources\TenantResource\Pages;
use App\Models\Client;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Support\CrmPalette;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office';

    protected static ?string $navigationLabel = 'Empresas (Tenants)';

    protected static ?string $modelLabel = 'Empresa';

    protected static ?string $pluralModelLabel = 'Empresas';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationGroup = 'Gestão SaaS';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Informações da Empresa')->schema([
                Forms\Components\TextInput::make('name')->label('Nome')->required()->maxLength(255),
                Forms\Components\TextInput::make('slug')->label('Slug')->unique(Tenant::class, 'slug', ignoreRecord: true)->required()->maxLength(255),
                Forms\Components\Select::make('plan_id')->label('Plano')->relationship('plan', 'name')->searchable()->preload()->live(),
                Forms\Components\Select::make('status')->label('Status')->options(['active' => 'Ativo', 'trial' => 'Teste', 'suspended' => 'Suspenso', 'canceled' => 'Cancelado'])->default('trial')->required(),
                Forms\Components\TextInput::make('mrr_value')->label('MRR (R$)')->numeric()->step(0.01)->default(0),
                Forms\Components\TextInput::make('cpf_cnpj')
                    ->label('CPF/CNPJ')
                    ->helperText('Exigido pra criar a cobrança recorrente na Asaas -- sem isso, a assinatura SaaS deste tenant não é sincronizada com o gateway de pagamento.')
                    ->maxLength(20),
                Forms\Components\Toggle::make('onboarding_completed')->label('Onboarding Completo')->default(false),
            ])->columns(2),

            Forms\Components\Section::make('Administrador do Tenant')
                ->description('Este usuário nasce com o papel "admin": acesso total a tudo que o plano contratado libera, e pode criar outros usuários e perfis de acesso personalizados dentro da própria empresa.')
                ->schema([
                    Forms\Components\TextInput::make('admin_name')
                        ->label('Nome do Administrador')
                        ->required()
                        ->visibleOn('create'),

                    Forms\Components\TextInput::make('admin_email')
                        ->label('E-mail do Administrador')
                        ->email()
                        ->required()
                        ->unique(User::class, 'email')
                        ->visibleOn('create'),

                    Forms\Components\TextInput::make('admin_password')
                        ->label('Senha')
                        ->password()
                        ->revealable()
                        ->required()
                        ->minLength(8)
                        ->visibleOn('create'),
                ])
                ->visibleOn('create')
                ->columns(3),

            Forms\Components\Section::make('🔐 Recursos Adicionais')
                ->description('Libere aqui módulos além do que o plano contratado já concede. Deixar desmarcado não bloqueia nada do plano — só o plano define o que é negado.')
                ->schema([
                    Forms\Components\CheckboxList::make('features')
                        ->label('Módulos extras liberados para este tenant')
                        ->options(Plan::getAvailableFeaturesOptions())
                        ->live()
                        ->columns(2),

                    Forms\Components\Placeholder::make('overrides_warning')
                        ->label('')
                        ->content(function (Get $get) {
                            $selected = $get('features') ?? [];
                            if (empty($selected)) {
                                return null;
                            }

                            $plan = ($planId = $get('plan_id')) ? Plan::find($planId) : null;

                            $labels = Plan::getAvailableFeaturesOptions();
                            $extra = collect($selected)
                                ->filter(fn ($feature) => ! $plan?->hasFeature($feature))
                                ->map(fn ($feature) => $labels[$feature] ?? $feature)
                                ->values();

                            if ($extra->isEmpty()) {
                                return null;
                            }

                            $planPhrase = $plan
                                ? 'além do que o plano "'.e($plan->name).'" concede'
                                : 'sem nenhum plano selecionado ainda pra comparar';

                            return new HtmlString(
                                '<div class="rounded-lg bg-amber-50 dark:bg-amber-500/10 ring-1 ring-amber-600/20 dark:ring-amber-400/20 p-3 text-sm text-amber-800 dark:text-amber-300">'
                                .'⚠️ Estes módulos estão liberados <strong>só por override deste tenant</strong>, '.$planPhrase.': '
                                .e($extra->implode(', ')).'. '
                                .'Se o plano for restringido depois, eles continuam liberados aqui até serem desmarcados manualmente.'
                                .'</div>'
                            );
                        }),
                ]),
        ]);
    }

    /**
     * Mesma linguagem visual de SalesLeadResource: borda lateral colorida
     * na linha inteira, pra bater o olho e já saber o status sem ler a
     * coluna -- pedido do usuário 2026-08-04.
     */
    private static function statusBorderClass(?string $status): string
    {
        return match ($status) {
            'active' => 'border-emerald-600',
            'trial' => 'border-amber-500',
            'suspended' => 'border-red-600',
            'canceled' => 'border-gray-600',
            default => 'border-gray-700',
        };
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordClasses(fn (Tenant $record) => 'border-s-4 '.self::statusBorderClass($record->status))
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Empresa')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('slug')->label('Slug')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('plan.name')->label('Plano')->sortable(),
                Tables\Columns\TextColumn::make('segment')
                    ->label('Segmento')
                    ->badge()
                    ->color(fn (?string $state) => CrmPalette::segment($state)['filament'])
                    ->formatStateUsing(fn (?string $state) => $state ? (Client::nicheLabels()[$state] ?? $state) : null)
                    ->placeholder('—'),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->icons(['heroicon-o-check-circle' => 'active', 'heroicon-o-clock' => 'trial', 'heroicon-o-exclamation-triangle' => 'suspended', 'heroicon-o-x-circle' => 'canceled'])
                    ->colors(['success' => 'active', 'warning' => 'trial', 'danger' => 'suspended', 'gray' => 'canceled']),
                Tables\Columns\TextColumn::make('created_at')->label('Criado em')->dateTime('d/m/Y H:i')->sortable(),
            ])->filters([
                Tables\Filters\SelectFilter::make('status')->label('Status')->options(['active' => 'Ativo', 'trial' => 'Teste']),
                Tables\Filters\SelectFilter::make('segment')->label('Segmento')->options(Client::nicheLabels()),
            ])->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])->bulkActions([
                Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()]),
            ])->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTenants::route('/'),
            'create' => Pages\CreateTenant::route('/create'),
            'edit' => Pages\EditTenant::route('/{record}/edit'),
        ];
    }
}
