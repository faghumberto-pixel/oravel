<?php

namespace App\Filament\Client\Pages\Auth;

use App\Models\Client;
use Filament\Pages\Auth\Login as BaseLogin;

/**
 * Sobrescreve getCredentialsFromFormData() em vez de confiar em sintaxe de
 * operador (['!=', null]) nas credenciais passadas pro Auth::attempt() --
 * essa sintaxe depende de comportamento do EloquentUserProvider que não
 * pôde ser confirmado na versão instalada (leitura do vendor bloqueada
 * nesta sessão). Checagem explícita aqui é portável e não depende disso:
 * se o Client existe mas não tem portal_access_enabled_at, a senha
 * devolvida nunca bate com o hash real, então attempt() falha do mesmo
 * jeito que "credenciais inválidas" -- sem vazar se o e-mail existe ou
 * não, mesma UX de erro que o Filament já usa por padrão.
 */
class Login extends BaseLogin
{
    /**
     * Sobrescreve só o heading -- o título da aba (getTitle()) e o resto do
     * texto de login (labels dos campos, "Login" do botão etc) continuam
     * vindo da tradução padrão do Filament (vendor, nunca editada direto).
     */
    public function getHeading(): string
    {
        return 'Portal do Cliente';
    }

    protected function getCredentialsFromFormData(array $data): array
    {
        $client = Client::where('email', $data['email'])->first();

        if (! $client || ! $client->portal_access_enabled_at) {
            return [
                'email' => $data['email'],
                'password' => $data['password'].'-portal-access-not-granted',
            ];
        }

        return [
            'email' => $data['email'],
            'password' => $data['password'],
        ];
    }
}
