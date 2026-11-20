<?php

namespace App\Filament\Central\Resources;

use App\Filament\Central\Resources\WebIpIgnoradoResource\Pages\ManageWebIpsIgnorados;
use App\Models\WebIpIgnorado;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * IPs (ou faixas) da equipe e da empresa cujas visitas NÃO entram nas estatísticas dos sites (oravel.com.br e oravel.org).
 * Só super admin.
 */
class WebIpIgnoradoResource extends Resource
{
    protected static ?string $model = WebIpIgnorado::class;

    protected static ?string $navigationIcon = 'heroicon-o-eye-slash';

    protected static ?string $navigationGroup = 'Site Institucional';

    protected static ?string $navigationLabel = 'IPs ignorados';

    protected static ?int $navigationSort = 6;

    protected static ?string $modelLabel = 'IP ignorado';

    protected static ?string $pluralModelLabel = 'IPs ignorados';

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->isSuperAdmin();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('ip')->label('IP ou faixa de rede')->required()->maxLength(64)
                ->default(fn () => request()->ip())
                ->helperText(fn () => 'Já vem preenchido com o IP desta conexão ('.request()->ip().'). Para a rede inteira da empresa use uma faixa, por exemplo 200.10.20.0/24. Quem tem IP dinâmico precisa atualizar quando mudar.')
                ->unique(ignoreRecord: true)
                ->rules([fn () => function (string $attribute, $value, \Closure $fail) {
                    if (! WebIpIgnorado::entradaValida((string) $value)) {
                        $fail('Informe um IP válido ou uma faixa como 200.10.20.0/24 (IPv4 de /16 a /32; IPv6 de /32 a /128).');
                    }
                }]),
            Forms\Components\TextInput::make('rotulo')->label('Quem é')->maxLength(120)->placeholder('Ex.: Meu IP, Escritório, Casa do João'),
            Forms\Components\Toggle::make('apagar_historico')->label('Apagar as visitas já registradas deste IP')
                ->helperText('Remove das estatísticas e da lista de visitas o que este IP já gerou, inclusive o acesso de agora.')
                ->default(true)->visibleOn('create'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->description('Visitas vindas destes IPs não são contadas, em nenhum dos sites. A mudança vale em até 1 minuto.')
            ->columns([
                Tables\Columns\TextColumn::make('ip')->label('IP ou faixa')->weight('bold')->copyable()->searchable(),
                Tables\Columns\TextColumn::make('rotulo')->label('Quem é')->placeholder('—')->searchable(),
                Tables\Columns\TextColumn::make('created_at')->label('Adicionado em')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
            ->emptyStateHeading('Nenhum IP ignorado')
            ->emptyStateDescription('Adicione o seu IP para as suas visitas não inflarem os números.');
    }

    public static function getPages(): array
    {
        return ['index' => ManageWebIpsIgnorados::route('/')];
    }
}
