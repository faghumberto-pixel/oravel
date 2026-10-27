<?php

namespace App\Filament\Central\Resources;

use App\Dashboards\BlockRegistry;
use App\Dashboards\Personalizacao\DashboardTemplateService;
use App\Filament\Central\Resources\DashboardTemplateResource\Pages;
use App\Filament\Central\Resources\DashboardTemplateResource\RelationManagers\AssignmentsRelationManager;
use App\Models\DashboardTemplate;
use App\Models\Tenant;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use InvalidArgumentException;

/**
 * Central: monta os dashboards personalizados (blocos de vários módulos, em ordem, com ajustes e ficha) e os atribui
 * às empresas. Só aparece com DASHBOARDS_PERSONALIZADOS ligado.
 */
class DashboardTemplateResource extends Resource
{
    protected static ?string $model = DashboardTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected static ?string $navigationGroup = 'Gestão SaaS';

    protected static ?string $navigationLabel = 'Dashboards Personalizados';

    protected static ?string $modelLabel = 'Dashboard';

    protected static ?string $pluralModelLabel = 'Dashboards Personalizados';

    protected static ?int $navigationSort = 80;

    public static function canAccess(): bool
    {
        return (bool) config('oravel.dashboards_personalizados') && (bool) auth()->user()?->isSuperAdmin();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    /** @return array<string, array<string, string>> módulo => [chave do bloco => nome] */
    public static function opcoesDeBlocos(): array
    {
        $opcoes = [];
        foreach (BlockRegistry::all() as $chave => $classe) {
            $opcoes[$classe::module()][$chave] = $classe::title();
        }
        ksort($opcoes);

        return $opcoes;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Dashboard')->columns(2)->schema([
                Forms\Components\TextInput::make('name')->label('Nome')->required()->maxLength(120),
                Forms\Components\Select::make('profile')->label('Perfil de quem vê')->options(DashboardTemplate::PROFILES)->native(false),
                Forms\Components\Select::make('tenant_id')
                    ->label('Empresa (só para dashboard exclusivo)')
                    ->options(fn () => Tenant::query()->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->placeholder('Modelo da plataforma (vale para várias empresas)')
                    ->helperText('Deixe vazio para um modelo da plataforma. Preenchido, o dashboard é exclusivo da empresa e não pode ser atribuído a outra. Não muda depois de criado.')
                    ->disabled(fn (string $operation) => $operation === 'edit')
                    ->dehydrated(fn (string $operation) => $operation === 'create'),
                Forms\Components\Toggle::make('is_active')->label('Ativo')->default(true)->inline(false),
                Forms\Components\Toggle::make('usar_como_inicio')
                    ->label('Usar como tela Início da empresa (no lugar do Painel de Controle e da Gestão à Vista)')
                    ->helperText('Só para dashboard exclusivo, na criação. Desmarcado, a empresa segue com o Início padrão. Depois dá para trocar na lista de empresas atribuídas.')
                    ->visible(fn (string $operation, Get $get) => $operation === 'create' && filled($get('tenant_id')))
                    ->dehydrated(fn (string $operation) => $operation === 'create')
                    ->inline(false),
                Forms\Components\Textarea::make('description')->label('Descrição (aparece para a empresa)')->rows(2)->columnSpanFull(),
            ]),

            Forms\Components\Section::make('Blocos')
                ->description('A ordem aqui é a ordem na tela. A empresa só vê os blocos dos módulos que contratou.')
                ->schema([
                    Forms\Components\Repeater::make('blocks')
                        ->label('')
                        ->relationship()
                        ->orderColumn('position')
                        ->reorderable()
                        ->collapsible()
                        ->collapsed(fn (string $operation) => $operation === 'edit')
                        ->addActionLabel('Adicionar bloco')
                        ->itemLabel(fn (array $state) => static::tituloDoBloco($state['block_key'] ?? null))
                        ->schema([
                            Forms\Components\Select::make('block_key')
                                ->label('Bloco')
                                ->options(fn () => static::opcoesDeBlocos())
                                ->searchable()
                                ->required()
                                ->live(),
                            Forms\Components\Placeholder::make('ajustes_aceitos')
                                ->label('Ajustes que este bloco aceita')
                                ->content(fn (Get $get) => new HtmlString(e(static::descricaoDosAjustes($get('block_key'))))),
                            Forms\Components\KeyValue::make('config')
                                ->label('Ajustes (limites, período, quantidade de linhas)')
                                ->keyLabel('Ajuste')
                                ->valueLabel('Valor')
                                ->rules([fn (Get $get) => function (string $attribute, $value, \Closure $fail) use ($get) {
                                    $erro = static::validarAjustes($get('block_key'), is_array($value) ? $value : []);
                                    if ($erro) {
                                        $fail($erro);
                                    }
                                }]),
                            Forms\Components\Fieldset::make('Ficha do bloco (opcional, só a Central vê)')->schema([
                                Forms\Components\Textarea::make('ficha_dor')->label('Dor do cliente')->rows(2)->columnSpanFull(),
                                Forms\Components\TextInput::make('ficha_indicador')->label('Indicador')->maxLength(160),
                                Forms\Components\TextInput::make('ficha_meta')->label('Meta')->maxLength(160),
                                Forms\Components\DatePicker::make('ficha_revisao_em')->label('Data de revisão (60 a 90 dias)'),
                            ])->columns(3),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('tenant')->withCount(['blocks', 'assignments']))
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nome')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('profile')->label('Perfil')->badge()->formatStateUsing(fn (?string $state) => DashboardTemplate::PROFILES[$state] ?? '—'),
                Tables\Columns\TextColumn::make('tenant.name')->label('Tipo')->placeholder('Modelo da plataforma')->description(fn (DashboardTemplate $r) => $r->tenant_id ? 'Exclusivo' : null),
                Tables\Columns\TextColumn::make('blocks_count')->label('Blocos')->alignEnd(),
                Tables\Columns\TextColumn::make('assignments_count')->label('Empresas')->alignEnd(),
                Tables\Columns\IconColumn::make('is_active')->label('Ativo')->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('exclusivo')->label('Tipo')->placeholder('Todos')->trueLabel('Exclusivos de uma empresa')->falseLabel('Modelos da plataforma')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('tenant_id'),
                        false: fn (Builder $query) => $query->whereNull('tenant_id'),
                        blank: fn (Builder $query) => $query,
                    ),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('duplicar')
                        ->label('Duplicar')->icon('heroicon-o-document-duplicate')
                        ->action(function (DashboardTemplate $record) {
                            $copia = DashboardTemplateService::duplicar($record);
                            Notification::make()->title('Dashboard duplicado')->body('“'.$copia->name.'” foi criado com os mesmos blocos.')->success()->send();
                        }),
                    Tables\Actions\Action::make('exclusivo')
                        ->label('Criar exclusivo para uma empresa')->icon('heroicon-o-building-office')
                        ->visible(fn (DashboardTemplate $record) => $record->isPlatform())
                        ->form([
                            Forms\Components\Select::make('tenant_id')->label('Empresa')->options(fn () => Tenant::query()->orderBy('name')->pluck('name', 'id'))->searchable()->required(),
                            Forms\Components\Toggle::make('inicio')->label('Usar como tela Início da empresa (no lugar do Painel de Controle e da Gestão à Vista)'),
                        ])
                        ->modalDescription('Copia este modelo para a empresa, já atribuído a ela, para você ajustar só para ela sem mexer no modelo.')
                        ->action(function (DashboardTemplate $record, array $data) {
                            $copia = DashboardTemplateService::exclusivoPara($record, Tenant::query()->findOrFail($data['tenant_id']), null, (bool) ($data['inicio'] ?? false));
                            Notification::make()->title('Dashboard exclusivo criado')->body('“'.$copia->name.'” já está atribuído à empresa.')->success()->send();
                        }),
                    Tables\Actions\Action::make('atribuir')
                        ->label('Atribuir a empresas')->icon('heroicon-o-link')
                        ->form(fn (DashboardTemplate $record) => [
                            Forms\Components\Select::make('tenant_ids')->label('Empresas')->multiple()->searchable()->required()
                                ->options(fn () => Tenant::query()->when($record->tenant_id, fn ($q) => $q->whereKey($record->tenant_id))->orderBy('name')->pluck('name', 'id')),
                            Forms\Components\Toggle::make('inicio')->label('Usar como tela Início da empresa (no lugar do Painel de Controle e da Gestão à Vista)'),
                        ])
                        ->action(function (DashboardTemplate $record, array $data) {
                            try {
                                $novas = DashboardTemplateService::atribuir($record, $data['tenant_ids'], (bool) ($data['inicio'] ?? false));
                            } catch (InvalidArgumentException $e) {
                                Notification::make()->title('Não foi possível atribuir')->body($e->getMessage())->danger()->send();

                                return;
                            }
                            Notification::make()->title($novas.' atribuição(ões) nova(s)')->success()->send();
                        }),
                ]),
                Tables\Actions\DeleteAction::make()->modalDescription('Apaga o dashboard, os blocos e as atribuições. As empresas voltam a ver só os dashboards padrão dos módulos.'),
            ])
            ->defaultSort('name');
    }

    public static function getRelations(): array
    {
        return [AssignmentsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDashboardTemplates::route('/'),
            'create' => Pages\CreateDashboardTemplate::route('/create'),
            'edit' => Pages\EditDashboardTemplate::route('/{record}/edit'),
        ];
    }

    private static function tituloDoBloco(?string $chave): string
    {
        $classe = $chave ? BlockRegistry::find($chave) : null;

        return $classe ? $classe::title().' · '.$classe::module() : 'Novo bloco';
    }

    private static function descricaoDosAjustes(?string $chave): string
    {
        $classe = $chave ? BlockRegistry::find($chave) : null;
        if (! $classe) {
            return 'Escolha um bloco.';
        }

        $padrao = $classe::defaultConfig();

        return $padrao
            ? implode('; ', array_map(fn ($k, $v) => $k.' (padrão '.$v.')', array_keys($padrao), $padrao))
            : 'Este bloco não tem ajustes.';
    }

    /** @param array<string, mixed> $ajustes */
    private static function validarAjustes(?string $chave, array $ajustes): ?string
    {
        $classe = $chave ? BlockRegistry::find($chave) : null;
        if (! $classe || $ajustes === []) {
            return null;
        }

        $padrao = $classe::defaultConfig();

        foreach ($ajustes as $nome => $valor) {
            if (! array_key_exists($nome, $padrao)) {
                return 'O ajuste “'.$nome.'” não existe neste bloco. Aceita: '.static::descricaoDosAjustes($chave).'.';
            }
            if ((is_int($padrao[$nome]) || is_float($padrao[$nome])) && $valor !== '' && $valor !== null && ! is_numeric($valor)) {
                return 'O ajuste “'.$nome.'” precisa ser um número.';
            }
        }

        return null;
    }
}
