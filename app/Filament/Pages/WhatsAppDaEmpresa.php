<?php

namespace App\Filament\Pages;

use App\Models\TenantWhatsappSetting;
use App\Models\User;
use App\Models\WhatsappNumero;
use App\Services\WhatsAppEmpresaService;
use App\Support\Tenancy;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Toggle;
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
 * A empresa liga o WhatsApp (API oficial da Meta): credenciais da empresa + o número de
 * cada usuário (e, se quiser, um número da empresa). Ligado, cada pessoa envia e recebe
 * pelo próprio número, na tela Comercial → WhatsApp.
 */
class WhatsAppDaEmpresa extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationGroup = 'Configurações';

    protected static ?string $navigationLabel = 'WhatsApp da Empresa';

    protected static ?int $navigationSort = 5;

    protected static ?string $title = 'WhatsApp da Empresa';

    protected static ?string $slug = 'whatsapp-da-empresa';

    protected static string $view = 'filament.pages.whatsapp-da-empresa';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    public function mount(): void
    {
        $c = $this->config();

        $this->form->fill(array_merge(
            $c ? $c->only(['waba_id', 'template_abertura', 'template_proposta', 'template_language', 'distribuicao', 'atendentes', 'aviso_cobranca', 'aviso_cobranca_dias_antes', 'template_cobranca', 'aviso_os', 'template_os_concluida']) : ['template_language' => 'pt_BR'],
            ['numeros' => $this->numeros()->map(fn (WhatsappNumero $n) => ['user_id' => $n->user_id, 'phone_number_id' => $n->phone_number_id, 'rotulo' => $n->rotulo])->all()],
        ));
    }

    public function config(): ?TenantWhatsappSetting
    {
        return TenantWhatsappSetting::withoutGlobalScopes()->where('tenant_id', Tenancy::current()?->id)->first();
    }

    public function numeros()
    {
        return WhatsappNumero::withoutGlobalScopes()->where('tenant_id', Tenancy::current()?->id)->with('user')->orderBy('created_at')->get();
    }

    public function urlDoWebhook(): string
    {
        return url('/api/webhooks/whatsapp-empresa/'.Tenancy::current()?->id);
    }

    public function form(Form $form): Form
    {
        $usuarios = User::withoutGlobalScopes()->where('tenant_id', Tenancy::current()?->id)->where('is_approved', true)->orderBy('name')->pluck('name', 'id')->all();

        return $form->statePath('data')->schema([
            Placeholder::make('situacao')->label('')->content(fn () => $this->situacao()),
            Section::make('Dados da empresa na Meta')
                ->description('Valem para todos os números. Você encontra na conta da Meta (WhatsApp Business Platform).')
                ->schema([
                    TextInput::make('waba_id')->label('ID da conta do WhatsApp Business (opcional)')->maxLength(60),
                    TextInput::make('access_token')->label('Token de acesso permanente')->password()->revealable()->autocomplete('new-password')
                        ->required(fn () => ! $this->config())
                        ->helperText(fn () => $this->config() ? 'Deixe em branco para manter o atual. Fica guardado criptografado.' : 'Fica guardado criptografado e nunca é mostrado de novo.'),
                    TextInput::make('app_secret')->label('Segredo do app da Meta')->password()->revealable()->autocomplete('new-password')
                        ->required(fn () => ! $this->config())
                        ->helperText('Serve para conferir que as mensagens recebidas vêm mesmo da Meta.'),
                ])->columns(3),
            Section::make('Números')
                ->description('Um número por pessoa: cada usuário envia e recebe pelo dele. Deixe o campo "Usuário" vazio para cadastrar um número da empresa, usado por quem não tem número próprio. Cada número precisa estar cadastrado na API oficial da Meta.')
                ->schema([
                    Repeater::make('numeros')->label('')->addActionLabel('Adicionar número')->defaultItems(0)->schema([
                        Select::make('user_id')->label('Usuário')->options($usuarios)->searchable()->placeholder('Número da empresa'),
                        TextInput::make('phone_number_id')->label('ID do número de telefone (na Meta)')->required()->maxLength(60),
                        TextInput::make('rotulo')->label('Nome (opcional)')->maxLength(60),
                    ])->columns(3),
                ]),
            Section::make('Número da empresa: quem atende')
                ->description('Vale para as conversas que chegam num número sem usuário (o número da empresa). Se a conversa já tem alguém ligado a ela (responsável do lead ou quem já atendeu esse telefone), continua com a mesma pessoa.')
                ->schema([
                    Select::make('distribuicao')->label('Como distribuir')->native(false)->required()
                        ->options(['fila' => 'Fila: quem atende assume a conversa', 'rodizio' => 'Rodízio: cada nova conversa vai para o próximo atendente'])->default('fila'),
                    Select::make('atendentes')->label('Atendentes')->multiple()->searchable()->options($usuarios)
                        ->helperText('Quem pode ver e assumir a fila e entra no rodízio. Sem ninguém escolhido, atende quem não tem número próprio.'),
                ])->columns(2),
            Section::make('Avisos automáticos aos clientes')
                ->description('Saem pelo número da empresa, só para clientes que aceitaram receber avisos por WhatsApp (marcado no cadastro do cliente) e uma única vez por cobrança ou ordem de serviço. Cada aviso precisa de um modelo aprovado pela Meta.')
                ->schema([
                    Toggle::make('aviso_cobranca')->label('Lembrar cobranças (vencimento e atraso)')->live(),
                    TextInput::make('aviso_cobranca_dias_antes')->label('Avisar quantos dias antes de vencer')->numeric()->minValue(0)->maxValue(30)->default(3)->visible(fn ($get) => (bool) $get('aviso_cobranca')),
                    TextInput::make('template_cobranca')->label('Modelo de cobrança')->helperText('4 variáveis: nome, descrição, valor e vencimento.')->maxLength(120)->visible(fn ($get) => (bool) $get('aviso_cobranca')),
                    Toggle::make('aviso_os')->label('Avisar quando a ordem de serviço for concluída')->live(),
                    TextInput::make('template_os_concluida')->label('Modelo de OS concluída')->helperText('3 variáveis: nome, número da OS e equipamento.')->maxLength(120)->visible(fn ($get) => (bool) $get('aviso_os')),
                    Placeholder::make('textos_sugeridos')->label('Textos sugeridos para cadastrar na Meta')->columnSpanFull()->content(new HtmlString(
                        '<div style="font-size:12px;line-height:1.6">'
                        .'<b>Cobrança</b> (categoria: Utilidade): <code>Olá {{1}}, lembrete da cobrança "{{2}}" no valor de {{3}}, com vencimento em {{4}}. Em caso de dúvida, é só responder esta mensagem.</code><br>'
                        .'<b>OS concluída</b> (categoria: Utilidade): <code>Olá {{1}}, a ordem de serviço {{2}} do equipamento {{3}} foi concluída. Qualquer dúvida, responda esta mensagem.</code><br>'
                        .'<b>Iniciar conversa</b> (categoria: Utilidade): <code>Olá {{1}}, aqui é da nossa equipe. Posso ajudar em algo? Responda esta mensagem para conversarmos.</code><br>'
                        .'<b>Proposta</b> (categoria: Utilidade): <code>Olá {{1}}, segue a nossa proposta comercial. Para ver e responder, acesse: {{2}}</code>'
                        .'</div>'
                    )),
                ])->columns(2),
            Section::make('Modelos de mensagem aprovados')
                ->description('Para iniciar uma conversa, ou responder depois de 24 horas, o WhatsApp só aceita modelos aprovados pela Meta. Informe o nome exato de cada um.')
                ->schema([
                    TextInput::make('template_abertura')->label('Modelo para iniciar conversa')->helperText('Uma variável: o nome do cliente ({{1}}).')->maxLength(120),
                    TextInput::make('template_proposta')->label('Modelo para enviar proposta')->helperText('Duas variáveis: nome do cliente ({{1}}) e link da proposta ({{2}}).')->maxLength(120),
                    TextInput::make('template_language')->label('Idioma dos modelos')->default('pt_BR')->required()->maxLength(10),
                ])->columns(3),
        ]);
    }

    private function situacao(): HtmlString
    {
        $c = $this->config();

        if (! $c) {
            return new HtmlString('<b>WhatsApp ainda não ligado.</b> Enquanto isso, os botões de WhatsApp do sistema só abrem o aplicativo com a mensagem pronta.');
        }

        if ($c->enabled) {
            $ok = $this->numeros()->where('last_test_ok', true)->count();

            return new HtmlString('<b style="color:#16a34a">Ligado.</b> '.$ok.' número(s) funcionando. Veja as conversas em <b>Comercial → WhatsApp</b>.');
        }

        return new HtmlString('<b>Cadastrado, mas desligado.</b>'.($c->last_test_ok === false ? ' O último teste falhou: '.e((string) $c->last_error) : ' Faça o teste para ligar.'));
    }

    public function salvar(): void
    {
        $this->gravar();

        Notification::make()->title('Dados salvos')->body('Falta cadastrar o webhook na Meta e fazer o teste de conexão.')->success()->send();
    }

    /** Testa cada número na Meta (sem enviar nada); liga se pelo menos um funcionar. */
    public function testarEAtivar(): void
    {
        $config = $this->gravar();
        $funcionando = 0;
        $problemas = [];

        foreach ($this->numeros() as $numero) {
            try {
                $info = (new WhatsAppEmpresaService($config, $numero))->testarConexao();
                $numero->update(['display_phone' => $info['display_phone_number'] ?? null, 'last_test_at' => now(), 'last_test_ok' => true, 'last_error' => null]);
                $funcionando++;
            } catch (\Throwable $e) {
                $numero->update(['last_test_at' => now(), 'last_test_ok' => false, 'last_error' => mb_substr($e->getMessage(), 0, 300)]);
                $problemas[] = $numero->nome();
            }
        }

        $config->update(['enabled' => $funcionando > 0, 'last_test_at' => now(), 'last_test_ok' => $funcionando > 0, 'last_error' => $funcionando > 0 ? null : 'Nenhum número foi aceito pela Meta.']);

        if ($funcionando === 0) {
            Notification::make()->title('A Meta recusou os dados')->body('Confira o token e o ID de cada número. Nada foi ligado.')->danger()->persistent()->send();

            return;
        }

        Notification::make()->title('WhatsApp ligado')
            ->body($funcionando.' número(s) funcionando.'.($problemas ? ' Com problema: '.implode(', ', $problemas).'.' : '').' Agora cadastre o webhook na Meta para receber as respostas.')
            ->success()->persistent()->send();
    }

    public function desligar(): void
    {
        $this->config()?->update(['enabled' => false]);

        Notification::make()->title('WhatsApp desligado')->success()->send();
    }

    private function gravar(): TenantWhatsappSetting
    {
        $tenantId = Tenancy::current()?->id;
        abort_unless($tenantId, 403);

        $d = $this->form->getState();
        $atual = $this->config();

        $campos = [
            'waba_id' => $d['waba_id'] ?? null, 'template_abertura' => $d['template_abertura'] ?? null,
            'template_proposta' => $d['template_proposta'] ?? null, 'template_language' => $d['template_language'] ?: 'pt_BR',
            'distribuicao' => $d['distribuicao'] ?? 'fila', 'atendentes' => array_values((array) ($d['atendentes'] ?? [])),
            'aviso_cobranca' => (bool) ($d['aviso_cobranca'] ?? false), 'aviso_cobranca_dias_antes' => (int) ($d['aviso_cobranca_dias_antes'] ?? 3), 'template_cobranca' => $d['template_cobranca'] ?? null,
            'aviso_os' => (bool) ($d['aviso_os'] ?? false), 'template_os_concluida' => $d['template_os_concluida'] ?? null,
        ];

        foreach (['access_token', 'app_secret'] as $segredo) {
            if (filled($d[$segredo] ?? null)) {
                $campos[$segredo] = $d[$segredo];
            }
        }

        if (isset($campos['access_token'])) {
            $campos['enabled'] = false;
        }

        $config = $atual
            ? tap($atual)->update($campos)->refresh()
            : TenantWhatsappSetting::withoutGlobalScopes()->create($campos + ['tenant_id' => $tenantId, 'enabled' => false, 'verify_token' => Str::random(32)]);

        $this->sincronizarNumeros($tenantId, (array) ($d['numeros'] ?? []));

        return $config;
    }

    private function sincronizarNumeros(string $tenantId, array $itens): void
    {
        $validos = User::withoutGlobalScopes()->where('tenant_id', $tenantId)->pluck('id')->all();
        $manter = [];

        foreach ($itens as $item) {
            $userId = $item['user_id'] ?? null;
            if ($userId && ! in_array($userId, $validos, true)) {
                continue;
            }

            $numero = WhatsappNumero::withoutGlobalScopes()->updateOrCreate(
                ['tenant_id' => $tenantId, 'phone_number_id' => trim($item['phone_number_id'])],
                ['user_id' => $userId ?: null, 'rotulo' => $item['rotulo'] ?? null, 'enabled' => true],
            );
            $manter[] = $numero->id;
        }

        WhatsappNumero::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereNotIn('id', $manter)->delete();
    }
}
