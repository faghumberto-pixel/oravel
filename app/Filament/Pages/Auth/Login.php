<?php

namespace App\Filament\Pages\Auth;

use App\Models\User;
use App\Support\AppProfile;
use App\Support\Campo;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Filament\Models\Contracts\FilamentUser;
use Filament\Pages\Auth\Login as BaseLogin;
use Illuminate\Validation\ValidationException;

/**
 * Casca visual (foto industrial com camada verde da marca, ver
 * resources/views/filament/pages/auth/login.blade.php) por cima da
 * autenticacao real do Filament -- authenticate()/rate limit/2FA
 * continuam herdados sem alteracao (so authenticate() e sobrescrito, ver
 * comentario no metodo), so trocamos $view + a aparencia dos campos do
 * form. Nao ha autocadastro: contas sao criadas internamente.
 */
class Login extends BaseLogin
{
    protected static string $view = 'filament.pages.auth.login';

    /**
     * SimplePage (pai da classe base) usa
     * 'filament-panels::components.layout.simple', que forca um card
     * branco centralizado unico -- vamos direto no layout.base (so
     * <html>/<head>/assets do Filament) pra poder desenhar as duas
     * colunas por conta propria na view.
     */
    protected static string $layout = 'filament-panels::components.layout.base';

    public static function getRouteMiddleware(): array
    {
        return ['guest'];
    }

    protected function getLayoutData(): array
    {
        return [];
    }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label('E-mail')
            ->hiddenLabel()
            ->placeholder('E-mail')
            ->prefixIcon('heroicon-o-envelope')
            ->email()
            ->required()
            ->autocomplete()
            ->autofocus()
            ->extraInputAttributes(['tabindex' => 1, 'class' => 'oravel-auth-input']);
    }

    protected function getPasswordFormComponent(): Component
    {
        return TextInput::make('password')
            ->label('Senha')
            ->hiddenLabel()
            ->placeholder('Senha')
            ->prefixIcon('heroicon-o-lock-closed')
            ->password()
            ->revealable(filament()->arePasswordsRevealable())
            ->autocomplete('current-password')
            ->required()
            ->extraInputAttributes(['tabindex' => 2, 'class' => 'oravel-auth-input']);
    }

    /**
     * "Lembrar de mim" sai do form gerenciado pelo Filament -- vira um
     * checkbox simples com wire:model="data.remember" na view, pra poder
     * ficar na mesma linha dos links "Esqueceu a senha?"
     * (layout do spec). authenticate() na classe pai ja le
     * $data['remember'] ?? false, entao continua funcionando sem mudar
     * aquele metodo.
     *
     * @return array<int|string, string|Form>
     */
    protected function getForms(): array
    {
        return [
            'form' => $this->form(
                $this->makeForm()
                    ->schema([
                        $this->getEmailFormComponent(),
                        $this->getPasswordFormComponent(),
                    ])
                    ->statePath('data'),
            ),
        ];
    }

    protected function getAuthenticateFormAction(): Action
    {
        return Action::make('authenticate')
            ->label('LOGIN')
            ->submit('authenticate')
            ->extraAttributes(['class' => 'oravel-auth-submit']);
    }

    /**
     * Copia o corpo curto de BaseLogin::authenticate() -- so acrescenta a
     * checagem de is_approved ANTES da checagem generica de
     * canAccessPanel(), pra mostrar uma mensagem especifica ("aguardando
     * aprovacao") em vez da generica de credenciais invalidas que o pai
     * usa pra qualquer motivo de recusa. canAccessPanel() continua sendo
     * a trava de verdade (User.php) -- essa sobrescrita e' so pela
     * mensagem certa nessa tela.
     */
    public function authenticate(): ?LoginResponse
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return null;
        }

        $data = $this->form->getState();

        if (! Filament::auth()->attempt($this->getCredentialsFromFormData($data), $data['remember'] ?? false)) {
            $this->throwFailureValidationException();
        }

        $user = Filament::auth()->user();

        if ($user instanceof User && ! $user->is_approved) {
            Filament::auth()->logout();

            throw ValidationException::withMessages([
                'data.email' => 'Sua conta ainda está aguardando aprovação do administrador da sua empresa.',
            ]);
        }

        if (($user instanceof FilamentUser) && (! $user->canAccessPanel(Filament::getCurrentPanel()))) {
            Filament::auth()->logout();
            $this->throwFailureValidationException();
        }

        session()->regenerate();

        // Técnico comum (não admin, sem departamentos supervisionados) vai direto para "Minhas Ordens de Serviço"
        // Precisa ser $this->redirect() (Livewire), nao redirect()->route() puro: dentro de uma
        // action Livewire, redirect() ja registra o effect de navegacao como side-effect da chamada;
        // o valor de retorno da action nunca vira navegacao, so alimenta o effect "returns" do
        // $wire.call() -- daí o retorno aqui tem que ser null (compativel com ?LoginResponse).
        // Reativado 28/09/2026 -- ver App\Filament\Pages\TechnicianDailyTasks::canAccess().
        // Tecnico -> Minhas Ordens de Servico; colaborador (sem acesso a OS) -> App do
        // Colaborador (AppProfile; antes todo nao-admin caia na tela do tecnico).
        // Celular + área de campo ligada na empresa (e sem escolha manual pela versão completa): abre o campo.
        if ($user instanceof User && Campo::deveAbrirNoLogin($user, request())) {
            $this->redirect(route('campo.home'));

            return null;
        }

        if ($user instanceof User && AppProfile::for($user) !== AppProfile::ADMIN) {
            $this->redirect(AppProfile::homeUrl($user, AppProfile::for($user)));

            return null;
        }

        // Administrador: abre na tela inicial certa do contrato (Painel de Controle, tela do pacote, Guia...) em vez do primeiro item
        // do menu, a não ser que ele tenha vindo de um link específico (url.intended). Vale também sem o Painel de Controle no
        // contrato (ex.: pacote Intelligence): o retorno padrão do Filament leva ao 1º item do menu, que pode dar 403 (Gestão de Ativos).
        if ($user instanceof User) {
            $this->redirect(session()->pull('url.intended', AppProfile::homeUrl($user, AppProfile::ADMIN)));

            return null;
        }

        return app(LoginResponse::class);
    }
}
