<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NotaFiscalResource\Pages;
use App\Models\NotaFiscal;
use App\Services\Fiscal\FiscalException;
use App\Services\Fiscal\NotaFiscalService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/** Lista das NFS-e da empresa: situação, PDF e XML, atualização, cancelamento e envio ao cliente. Só aparece com o complemento no contrato. */
class NotaFiscalResource extends BaseResource
{
    protected static ?string $model = NotaFiscal::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-check';

    // Complementos nunca entram nos pacotes (config/pacotes.php): a nota só aparece para quem contratou o complemento.
    protected static ?string $navigationGroup = 'Complementos';

    protected static ?string $navigationLabel = 'Notas Fiscais (NFS-e)';

    protected static ?string $modelLabel = 'Nota Fiscal';

    protected static ?string $pluralModelLabel = 'Notas Fiscais';

    protected static ?int $navigationSort = 1;

    public static function canCreate(): bool
    {
        return false; // a nota nasce da conta a receber
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('cliente'))
            ->columns([
                Tables\Columns\TextColumn::make('numero')->label('Número')->placeholder('—')->searchable(),
                Tables\Columns\TextColumn::make('tomador.nome')->label('Cliente')->searchable(query: fn (Builder $q, string $busca) => $q->where('tomador->nome', 'ilike', "%{$busca}%")),
                Tables\Columns\TextColumn::make('valor')->label('Valor')->money('BRL')->sortable(),
                Tables\Columns\TextColumn::make('status')->label('Situação')->badge()
                    ->formatStateUsing(fn (string $state) => self::ROTULOS[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        NotaFiscal::AUTORIZADA => 'success', NotaFiscal::PROCESSANDO => 'warning', NotaFiscal::ERRO => 'danger', default => 'gray',
                    })
                    ->tooltip(fn (NotaFiscal $r) => $r->status === NotaFiscal::ERRO ? $r->erro : null),
                Tables\Columns\TextColumn::make('emitida_em')->label('Emitida em')->date('d/m/Y')->placeholder('—')->sortable(),
                Tables\Columns\IconColumn::make('enviada_em')->label('Enviada')->boolean()->getStateUsing(fn (NotaFiscal $r) => $r->enviada_em !== null),
                Tables\Columns\TextColumn::make('ambiente')->label('Ambiente')->formatStateUsing(fn (string $s) => $s === 'producao' ? 'Produção' : 'Teste')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label('Situação')->options(self::ROTULOS),
                Tables\Filters\Filter::make('mes')->label('Mês de emissão')
                    ->form([DatePicker::make('mes')->label('Mês')->native(false)->displayFormat('m/Y')])
                    ->query(fn (Builder $query, array $data) => $query->when($data['mes'] ?? null, fn (Builder $q, $m) => $q->whereBetween('emitida_em', [Carbon::parse($m)->startOfMonth(), Carbon::parse($m)->endOfMonth()]))),
            ])
            ->actions([
                Tables\Actions\Action::make('pdf')->label('PDF')->icon('heroicon-o-document-arrow-down')->color('gray')
                    ->visible(fn (NotaFiscal $r) => filled($r->pdf_url))->url(fn (NotaFiscal $r) => $r->pdf_url)->openUrlInNewTab(),
                Tables\Actions\Action::make('xml')->label('XML')->icon('heroicon-o-code-bracket')->color('gray')
                    ->visible(fn (NotaFiscal $r) => filled($r->xml_url))->url(fn (NotaFiscal $r) => $r->xml_url)->openUrlInNewTab(),
                Tables\Actions\Action::make('atualizar')->label('Atualizar')->icon('heroicon-o-arrow-path')->color('gray')
                    ->visible(fn (NotaFiscal $r) => $r->status === NotaFiscal::PROCESSANDO)
                    ->action(fn (NotaFiscal $r) => self::executar(fn () => app(NotaFiscalService::class)->reconsultar($r), 'Situação da nota atualizada')),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('enviarEmail')->label('Enviar por e-mail')->icon('heroicon-o-envelope')
                        ->requiresConfirmation()->modalDescription(fn (NotaFiscal $r) => 'Envia os links da nota para o e-mail cadastrado de '.($r->tomador['nome'] ?? 'o cliente').'.')
                        ->action(fn (NotaFiscal $r) => self::executar(fn () => app(NotaFiscalService::class)->enviarPorEmail($r), 'Nota enviada por e-mail')),
                    Tables\Actions\Action::make('enviarWhatsApp')->label('Enviar por WhatsApp')->icon('heroicon-o-chat-bubble-left-right')
                        ->requiresConfirmation()->modalDescription('Envia o link da nota pelo WhatsApp da empresa, só se o cliente aceitou receber avisos.')
                        ->action(fn (NotaFiscal $r) => self::executar(fn () => app(NotaFiscalService::class)->enviarPorWhatsApp($r), 'Nota enviada por WhatsApp')),
                ])->label('Enviar ao cliente')->icon('heroicon-o-paper-airplane')->button()->visible(fn (NotaFiscal $r) => $r->status === NotaFiscal::AUTORIZADA),
                Tables\Actions\Action::make('cancelar')->label('Cancelar nota')->icon('heroicon-o-x-circle')->color('danger')
                    ->visible(fn (NotaFiscal $r) => $r->status === NotaFiscal::AUTORIZADA)
                    ->modalHeading('Cancelar NFS-e')->modalDescription('O cancelamento vai à prefeitura e não pode ser desfeito. A conta a receber continua; para cobrar de novo é preciso emitir outra nota.')
                    ->form([Textarea::make('motivo')->label('Motivo do cancelamento')->required()->minLength(15)->maxLength(255)->rows(3)->helperText('Mínimo de 15 caracteres, exigência da prefeitura.')])
                    ->action(fn (NotaFiscal $r, array $data) => self::executar(fn () => app(NotaFiscalService::class)->cancelar($r, (string) $data['motivo']), 'Nota cancelada')),
            ])
            ->emptyStateHeading('Nenhuma nota emitida ainda')
            ->emptyStateDescription('Emita pelo botão "Emitir NFS-e" em cada conta a receber.');
    }

    public const ROTULOS = [
        NotaFiscal::RASCUNHO => 'Rascunho', NotaFiscal::PROCESSANDO => 'Em processamento', NotaFiscal::AUTORIZADA => 'Autorizada',
        NotaFiscal::ERRO => 'Com erro', NotaFiscal::CANCELADA => 'Cancelada',
    ];

    public static function executar(\Closure $acao, string $titulo): void
    {
        try {
            $acao();
            Notification::make()->title($titulo)->success()->send();
        } catch (FiscalException $e) {
            Notification::make()->title('Não foi possível')->body($e->getMessage())->danger()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title('Erro ao falar com o emissor fiscal')->body('Tente de novo em instantes.')->danger()->send();
        }
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageNotasFiscais::route('/')];
    }
}
