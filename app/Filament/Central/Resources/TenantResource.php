<?php

namespace App\Filament\Central\Resources;

use App\Filament\Central\Resources\TenantResource\Pages;
use App\Models\Client;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CnpjLookupService;
use App\Support\CrmPalette;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
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
                Forms\Components\TextInput::make('implementation_fee')
                    ->label('Taxa de Implantação (R$)')
                    ->helperText('Cobrança única via Asaas. Vazio = usa o valor do contrato. Depois de gerada a cobrança, alterar aqui não muda a cobrança já emitida.')
                    ->numeric()->step(0.01)->minValue(0),
                Forms\Components\Select::make('implementation_installments')
                    ->label('Parcelas da Implantação')
                    ->options([1 => 'À vista (1x)', 2 => 'Em 2 vezes'])
                    ->placeholder('Usar o do contrato')
                    ->native(false),
                Forms\Components\Select::make('implementation_billing_mode')
                    ->label('Como cobrar a Implantação')
                    ->options(['separada' => 'Cobrança separada (avulsa)', 'somada' => 'Somada à mensalidade'])
                    ->placeholder('Usar o do contrato')
                    ->native(false),
                Forms\Components\Placeholder::make('implementation_status_info')
                    ->label('Cobranças de implantação')
                    ->content(function (?Tenant $record) {
                        $charges = $record?->implementationCharges()->get() ?? collect();

                        if ($charges->isEmpty()) {
                            return 'Ainda não cobrada';
                        }

                        return new HtmlString($charges->map(fn ($c) => e("{$c->installment_number}/{$c->installments_total} — R$ ".number_format((float) $c->amount, 2, ',', '.').($c->included_in_subscription ? ' (somada à mensalidade)' : ' — vence '.$c->due_date->format('d/m/Y')).' — '.ucfirst($c->status))
                            .($c->invoice_url ? ' — <a href="'.e($c->invoice_url).'" target="_blank" class="underline">link</a>' : ''))->implode('<br>'));
                    })
                    ->visibleOn('edit'),
                Forms\Components\TextInput::make('cpf_cnpj')
                    ->label('CPF/CNPJ')
                    ->helperText('Exigido pra criar a cobrança recorrente na Asaas -- sem isso, a assinatura SaaS deste tenant não é sincronizada com o gateway de pagamento.')
                    ->maxLength(20)
                    ->suffixAction(
                        Forms\Components\Actions\Action::make('lookup_cnpj')
                            ->label('Buscar dados pelo CNPJ')
                            ->icon('heroicon-o-magnifying-glass')
                            ->action(function (Forms\Get $get, Forms\Set $set) {
                                $data = app(CnpjLookupService::class)->lookup((string) $get('cpf_cnpj'));

                                if (! $data) {
                                    Notification::make()->title('CNPJ não encontrado')->body('Confira o número (14 dígitos) e tente de novo.')->warning()->send();

                                    return;
                                }

                                foreach ($data as $field => $value) {
                                    if (filled($value)) {
                                        $set($field, $value);
                                    }
                                }

                                Notification::make()->title('Dados preenchidos pelo CNPJ')->body('Revise antes de salvar.')->success()->send();
                            })
                    ),
                Forms\Components\TextInput::make('razao_social')->label('Razão social')->maxLength(255),
                Forms\Components\TextInput::make('nome_fantasia')->label('Nome fantasia')->maxLength(255),
                Forms\Components\TextInput::make('natureza_juridica')->label('Natureza jurídica')->maxLength(255),
                Forms\Components\TextInput::make('inscricao_estadual')->label('Inscrição estadual')->maxLength(30),
                Forms\Components\TextInput::make('email_contato')->label('E-mail de contato da empresa')->email()->maxLength(255),
                Forms\Components\TextInput::make('representante_nome')->label('Representante legal')->maxLength(255),
                Forms\Components\TextInput::make('representante_cpf')->label('CPF do representante')->maxLength(20),
                Forms\Components\TextInput::make('representante_cargo')->label('Cargo do representante')->maxLength(255),
                Forms\Components\TextInput::make('telefone')
                    ->label('Telefone')
                    ->helperText('Também exigido pela Asaas pra gerar o Checkout de pagamento.')
                    ->maxLength(20),
                Forms\Components\Toggle::make('onboarding_completed')->label('Onboarding Completo')->default(false),
            ])->columns(2),

            // CEP/logradouro/número/UF também são exigidos pela Asaas pra
            // criar o Checkout de pagamento (achado real em PROD
            // 2026-09-23) -- sem esta seção, um tenant criado manualmente
            // pela Central nunca conseguia gerar o link de pagamento
            // depois de assinar o contrato.
            Forms\Components\Section::make('Endereço')
                ->description('Exigido pela Asaas pra gerar o Checkout de pagamento.')
                ->schema([
                    Forms\Components\TextInput::make('cep')->label('CEP')->maxLength(9),
                    Forms\Components\TextInput::make('logradouro')->label('Logradouro')->maxLength(255),
                    Forms\Components\TextInput::make('numero')->label('Número')->maxLength(20),
                    Forms\Components\TextInput::make('complemento')->label('Complemento')->maxLength(255),
                    Forms\Components\TextInput::make('bairro')->label('Bairro')->maxLength(255),
                    Forms\Components\TextInput::make('cidade')->label('Cidade')->maxLength(255),
                    Forms\Components\TextInput::make('uf')->label('UF')->maxLength(2),
                ])->columns(3),

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
                Tables\Columns\TextColumn::make('implementation_summary')
                    ->label('Implantação')
                    ->badge()
                    ->state(fn (Tenant $record) => $record->implementationSummary()['label'])
                    ->color(fn (Tenant $record) => $record->implementationSummary()['color']),
                Tables\Columns\TextColumn::make('created_at')->label('Criado em')->dateTime('d/m/Y H:i')->sortable(),
            ])->filters([
                Tables\Filters\SelectFilter::make('status')->label('Status')->options(['active' => 'Ativo', 'trial' => 'Teste']),
                Tables\Filters\SelectFilter::make('segment')->label('Segmento')->options(Client::nicheLabels()),
                // Usado pelos cards de resumo em ContratoResource ("Pagos"
                // / "Em Aberto", pedido do usuário 2026-09-23) pra linkar
                // direto num Tenants já filtrado.
                Tables\Filters\SelectFilter::make('asaas_payment_status')
                    ->label('Pagamento')
                    ->options([
                        Tenant::PAYMENT_STATUS_EM_DIA => 'Em dia',
                        Tenant::PAYMENT_STATUS_ATRASADO => 'Atrasado',
                        Tenant::PAYMENT_STATUS_CANCELADO => 'Cancelado',
                    ]),
            ])->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])->bulkActions([
                Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()]),
            ])->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Central\Resources\TenantResource\RelationManagers\EventsRelationManager::class,
        ];
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
