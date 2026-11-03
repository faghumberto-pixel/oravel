<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SupplierResource\Pages;
use App\Models\Supplier;
use App\Support\CrmPalette;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationGroup = 'Materiais e Peças';

    protected static ?string $navigationParentItem = 'Gestão de Compras';

    protected static ?int $navigationSort = 0;

    protected static ?string $modelLabel = 'Fornecedor';

    protected static ?string $pluralModelLabel = 'Fornecedores';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Identificação do Fornecedor')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Razão Social / Nome')
                                    ->required()
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('document')
                                    ->label('CNPJ / CPF')
                                    ->required(),

                                Forms\Components\TextInput::make('email')
                                    ->label('E-mail')
                                    ->email()
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('phone')
                                    ->label('Telefone / WhatsApp')
                                    ->tel(),
                            ])->columns(2),

                        Forms\Components\Section::make('Dados Bancários')
                            ->schema([
                                Forms\Components\TextInput::make('bank_account_pix')
                                    ->label('Conta Bancária / Chave PIX')
                                    ->maxLength(255),
                            ]),
                    ])->columnSpan(['lg' => 2]),

                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Critérios de Homologação')
                            ->description('Avaliação estrita de requisitos da empresa')
                            ->schema([
                                Forms\Components\Toggle::make('compliance_ceis_cnep')
                                    ->label('Regularidade CEIS/CNEP')
                                    ->helperText('Consulta de empresas inidôneas e suspensas')
                                    ->inline(false),

                                Forms\Components\Toggle::make('lista_trabalho_escravo')
                                    ->label('Fora da Lista de Trabalho Escravo')
                                    ->helperText('Validação do Ministério do Trabalho')
                                    ->inline(false),

                                Forms\Components\Toggle::make('termo_lgpd')
                                    ->label('Termo de Consentimento LGPD')
                                    ->helperText('Assinado e em conformidade')
                                    ->inline(false),
                            ]),

                        Forms\Components\Section::make('Arquivos e Certidões')
                            ->schema([
                                Forms\Components\TextInput::make('cnpj_card')->label('Cartão CNPJ (Link/Ref)'),
                                Forms\Components\TextInput::make('inscricao_estadual')->label('Inscrição Estadual'),
                                Forms\Components\TextInput::make('contrato_social')->label('Contrato Social'),
                                Forms\Components\TextInput::make('cnd_federal')->label('CND Federal'),
                                Forms\Components\TextInput::make('crf_fgts')->label('CRF FGTS'),
                                Forms\Components\TextInput::make('cndt')->label('CNDT Trabalhista'),
                            ])->collapsed(),
                    ])->columnSpan(['lg' => 1]),
            ])->columns(3);
    }

    /** Falta CND Federal, FGTS ou CNDT (mesma regra do filtro "Certidoes pendentes"). */
    public static function certidoesPendentes(Supplier $s): bool
    {
        return $s->cnd_federal === null || $s->crf_fgts === null || $s->cndt === null;
    }

    /** Cor do cartao: sem termo LGPD = vermelho; pendencia de compliance/certidao = amarelo; tudo em dia = verde. */
    public static function corDoFornecedor(Supplier $s): string
    {
        if (! $s->termo_lgpd) {
            return 'red';
        }

        $completo = $s->compliance_ceis_cnep && $s->lista_trabalho_escravo && $s->termo_lgpd;

        return $completo && ! self::certidoesPendentes($s) ? 'emerald' : 'amber';
    }

    public static function table(Table $table): Table
    {
        return $table
            // Lista em cartoes (padrao do CRM Comercial): verde = compliance e certidoes em dia; vermelho = sem termo LGPD.
            ->recordClasses(fn (Supplier $record) => 'border-s-4 hover:!bg-transparent '
                .CrmPalette::cor(self::corDoFornecedor($record))['border'].' '
                .CrmPalette::cor(self::corDoFornecedor($record))['soft'])
            ->contentGrid(['md' => 2, '2xl' => 3])
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->persistSortInSession()
            ->columns([
                Tables\Columns\Layout\Stack::make([
                    Tables\Columns\TextColumn::make('name')
                        ->label('Fornecedor')
                        ->searchable()
                        ->sortable()
                        ->weight('bold')->size('lg')->color('crmOrange'),
                    Tables\Columns\TextColumn::make('document')
                        ->label('Documento')
                        ->searchable()
                        ->color('gray')->placeholder('Sem documento'),
                    // STATUS DE COMPLIANCE VISUAL
                    Tables\Columns\Layout\Split::make([
                        Tables\Columns\IconColumn::make('termo_lgpd')
                            ->label('LGPD')
                            ->boolean()
                            ->grow(false),
                        Tables\Columns\TextColumn::make('lgpd_texto')->label('Termo LGPD')
                            ->state(fn (Supplier $r): string => $r->termo_lgpd ? 'Termo LGPD assinado' : 'Sem termo LGPD')
                            ->color(fn (Supplier $r): string => $r->termo_lgpd ? 'success' : 'danger'),
                    ]),
                    Tables\Columns\Layout\Split::make([
                        Tables\Columns\IconColumn::make('lista_trabalho_escravo')
                            ->label('Ficha Limpa MTE')
                            ->boolean()
                            ->grow(false),
                        Tables\Columns\TextColumn::make('mte_texto')->label('Ficha Limpa MTE')
                            ->state(fn (Supplier $r): string => $r->lista_trabalho_escravo ? 'Ficha limpa (MTE)' : 'Ficha limpa pendente')
                            ->color(fn (Supplier $r): string => $r->lista_trabalho_escravo ? 'success' : 'warning'),
                    ]),
                    Tables\Columns\TextColumn::make('certidoes_texto')->label('Certidões')->icon('heroicon-m-document-check')
                        ->state(fn (Supplier $r): string => self::certidoesPendentes($r) ? 'Certidões pendentes' : 'Certidões em dia')
                        ->color(fn (Supplier $r): string => self::certidoesPendentes($r) ? 'warning' : 'success'),
                ])->space(2),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('termo_lgpd')
                    ->label('Apenas com Termo LGPD'),
                Tables\Filters\Filter::make('compliance_completo')
                    ->label('Compliance completo (CEIS/CNEP + MTE + LGPD)')
                    ->toggle()
                    ->query(fn ($query) => $query->where('compliance_ceis_cnep', true)->where('lista_trabalho_escravo', true)->where('termo_lgpd', true)),
                Tables\Filters\Filter::make('certidoes_pendentes')
                    ->label('Certidões pendentes (CND Federal, FGTS ou CNDT)')
                    ->toggle()
                    ->query(fn ($query) => $query->where(fn ($q) => $q->whereNull('cnd_federal')->orWhereNull('crf_fgts')->orWhereNull('cndt'))),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSuppliers::route('/'),
            'create' => Pages\CreateSupplier::route('/create'),
            'edit' => Pages\EditSupplier::route('/{record}/edit'),
        ];
    }
}
