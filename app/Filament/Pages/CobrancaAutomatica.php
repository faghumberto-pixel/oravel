<?php

namespace App\Filament\Pages;

use App\Models\TenantAsaasSetting;
use App\Services\Cobranca\AsaasEmpresaClient;
use App\Services\Cobranca\CobrancaException;
use App\Support\Tenancy;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Complemento "Cobrança automática": a empresa liga a PRÓPRIA conta Asaas (a cobrança e o dinheiro ficam com ela; a Oravel só
 * gera e acompanha). A chave é guardada criptografada e nunca é mostrada de novo.
 */
class CobrancaAutomatica extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Configurações';

    protected static ?string $navigationLabel = 'Cobrança automática';

    protected static ?int $navigationSort = 6;

    protected static ?string $title = 'Cobrança automática (Asaas)';

    protected static ?string $slug = 'cobranca-automatica';

    protected static string $view = 'filament.pages.cobranca-automatica';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isAdmin() && TenantAsaasSetting::moduloLiberado(Tenancy::current()?->id);
    }

    public function mount(): void
    {
        $c = $this->config();

        $this->form->fill($c ? $c->only(['ambiente', 'forma_padrao', 'multa_percentual', 'juros_mensal']) : ['ambiente' => 'sandbox', 'forma_padrao' => 'UNDEFINED']);
    }

    public function config(): ?TenantAsaasSetting
    {
        return TenantAsaasSetting::daEmpresa(Tenancy::current()?->id);
    }

    public function form(Form $form): Form
    {
        return $form->statePath('data')->schema([
            Placeholder::make('situacao')->label('')->content(fn () => $this->situacao()),
            Section::make('Conta Asaas da empresa')
                ->description('Crie a conta em asaas.com (ou use a que já tem) e gere uma chave de API em Integrações. As cobranças saem no nome da sua empresa e o dinheiro cai direto na sua conta Asaas.')
                ->schema([
                    Select::make('ambiente')->label('Ambiente')->native(false)->required()
                        ->options(['sandbox' => 'Teste (sandbox): não cobra de verdade', 'producao' => 'Produção: cobranças reais'])->default('sandbox'),
                    TextInput::make('api_key')->label('Chave de API do Asaas')->password()->revealable()->autocomplete('new-password')
                        ->required(fn () => blank($this->config()?->api_key))
                        ->helperText(fn () => $this->config()?->api_key ? 'Deixe em branco para manter a atual. Fica guardada criptografada.' : 'Fica guardada criptografada e nunca é mostrada de novo.'),
                ])->columns(2),
            Section::make('Como cobrar')
                ->schema([
                    Select::make('forma_padrao')->label('Forma de pagamento')->native(false)->required()->default('UNDEFINED')
                        ->options(['UNDEFINED' => 'O cliente escolhe (boleto, Pix ou cartão)', 'BOLETO' => 'Boleto', 'PIX' => 'Pix', 'CREDIT_CARD' => 'Cartão de crédito']),
                    TextInput::make('multa_percentual')->label('Multa por atraso (%)')->numeric()->minValue(0)->maxValue(20)->helperText('Vazio = usa a multa do contrato, se houver.'),
                    TextInput::make('juros_mensal')->label('Juros ao mês (%)')->numeric()->minValue(0)->maxValue(20),
                ])->columns(3),
        ]);
    }

    private function situacao(): HtmlString
    {
        $c = $this->config();

        if (! $c || blank($c->api_key)) {
            return new HtmlString('<b>Cobrança automática ainda não ligada.</b> Informe a chave de API e use "Testar conexão e ligar".');
        }

        if ($c->enabled) {
            return new HtmlString('<b style="color:#15803d">Ligada</b> em ambiente <b>'.($c->ambiente === 'producao' ? 'de produção' : 'de teste').'</b>.');
        }

        return new HtmlString('<b>Desligada.</b>'.($c->last_error ? ' Último erro: '.e($c->last_error) : ''));
    }

    public function salvar(): void
    {
        $this->gravar();

        Notification::make()->title('Configuração salva')->success()->send();
    }

    public function testarEAtivar(): void
    {
        $config = $this->gravar();

        try {
            $saldo = (new AsaasEmpresaClient($config))->testar();
        } catch (CobrancaException $e) {
            $config->update(['enabled' => false, 'last_test_at' => now(), 'last_test_ok' => false, 'last_error' => mb_substr($e->getMessage(), 0, 300)]);
            Notification::make()->title('O Asaas recusou a chave')->body($e->getMessage())->danger()->persistent()->send();

            return;
        } catch (\Throwable $e) {
            report($e);
            $config->update(['enabled' => false, 'last_test_at' => now(), 'last_test_ok' => false, 'last_error' => 'Sem conexão com o Asaas.']);
            Notification::make()->title('Não foi possível falar com o Asaas')->body('Tente de novo em instantes.')->danger()->send();

            return;
        }

        $config->update(['enabled' => true, 'last_test_at' => now(), 'last_test_ok' => true, 'last_error' => null]);

        Notification::make()->title('Cobrança automática ligada')
            ->body('Conexão ok'.(isset($saldo['balance']) ? ' (saldo R$ '.number_format((float) $saldo['balance'], 2, ',', '.').')' : '').'. Agora cadastre o webhook no Asaas para receber os pagamentos.')
            ->success()->persistent()->send();
    }

    public function desligar(): void
    {
        $this->config()?->update(['enabled' => false]);

        Notification::make()->title('Cobrança automática desligada')->success()->send();
    }

    public function novoTokenDoWebhook(): void
    {
        $this->config()?->update(['webhook_token' => Str::random(48)]);

        Notification::make()->title('Novo token gerado')->body('Atualize o token no webhook do Asaas, senão os pagamentos deixam de chegar.')->warning()->persistent()->send();
    }

    private function gravar(): TenantAsaasSetting
    {
        $tenantId = Tenancy::current()?->id;
        abort_unless($tenantId && auth()->user()?->isAdmin() && TenantAsaasSetting::moduloLiberado($tenantId), 403);

        $d = $this->form->getState();
        $campos = [
            'ambiente' => $d['ambiente'] ?? 'sandbox', 'forma_padrao' => $d['forma_padrao'] ?? 'UNDEFINED',
            'multa_percentual' => $d['multa_percentual'] ?: null, 'juros_mensal' => $d['juros_mensal'] ?: null,
        ];

        $atual = $this->config();

        if (filled($d['api_key'] ?? null)) {
            $campos['api_key'] = $d['api_key'];
            $campos['enabled'] = false; // chave nova: só liga depois de testar
        }

        return $atual
            ? tap($atual)->update($campos)->refresh()
            : TenantAsaasSetting::withoutGlobalScopes()->create($campos + ['tenant_id' => $tenantId, 'enabled' => false]);
    }
}
