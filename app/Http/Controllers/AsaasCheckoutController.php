<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Tenant;
use App\Services\AsaasService;
use App\Services\TenantProvisioner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Autoatendimento: cliente clica "Assinar" num plano no site institucional
 * (oravel.com.br) e cai aqui, sem precisar de um operador criando o
 * Tenant manualmente no painel Central (fluxo que já existia --
 * ver TenantResource\Pages\CreateTenant). Escopo confirmado com o
 * usuário 2026-08-13: acesso ao painel é liberado IMEDIATAMENTE ao
 * cadastrar (cobrança acontece de forma assíncrona -- sem bloqueio
 * automático por inadimplência ainda, fica pra depois).
 */
class AsaasCheckoutController extends Controller
{
    /**
     * Formulário de cadastro, com o plano pré-selecionado vindo do link
     * "Assinar" do site (?plano={uuid}) -- se o id não existir/estiver
     * ausente, mostra a lista de planos ativos pra escolher.
     */
    public function create(Request $request): View
    {
        $planoParam = $request->query('plano');

        // Str::isUuid() antes de bater no banco -- ?plano=qualquer-coisa
        // (não-UUID) faz Postgres rejeitar a query inteira com
        // "invalid input syntax for type uuid", derrubando a
        // transação por completo (achado real 2026-08-13: o erro se
        // propaga até o TrackSiteVisit no fim do request, mascarando a
        // causa raiz). find() com string vazia/null já retorna null sem
        // problema, só string não-UUID quebra.
        $selectedPlan = (is_string($planoParam) && Str::isUuid($planoParam))
            ? Plan::where('is_active', true)->find($planoParam)
            : null;

        return view('checkout.create', [
            'selectedPlan' => $selectedPlan,
            'plans' => Plan::where('is_active', true)->orderBy('level')->get(),
        ]);
    }

    public function store(Request $request, AsaasService $asaas): RedirectResponse
    {
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'admin_password' => ['required', 'string', 'min:8'],
            'cpf_cnpj' => ['required', 'string', 'max:20'],
            'plan_id' => ['required', 'uuid', 'exists:plans,id'],
        ]);

        $plan = Plan::findOrFail($data['plan_id']);

        $tenant = Tenant::create([
            'name' => $data['company_name'],
            'slug' => $this->uniqueSlug($data['company_name']),
            'plan_id' => $plan->id,
            'status' => 'trial',
            'mrr_value' => $plan->price,
            'cpf_cnpj' => $data['cpf_cnpj'],
        ]);

        $admin = TenantProvisioner::provision($tenant, [
            'name' => $data['admin_name'],
            'email' => $data['admin_email'],
            'password' => $data['admin_password'],
        ]);

        $asaas->syncTenantCustomer($tenant);
        $tenant->refresh();

        Auth::login($admin);
        $request->session()->regenerate();

        $invoiceUrl = $tenant->asaas_subscription_id
            ? $asaas->getFirstInvoiceUrl($tenant->asaas_subscription_id)
            : null;

        if ($invoiceUrl) {
            // Cliente já está logado (acesso liberado) -- a fatura abre
            // numa aba/redirecionamento próprio, fora do domínio Oravel,
            // pra ele efetivamente pagar. Sem link (assinatura falhou ao
            // sincronizar, Asaas fora do ar, etc), cai direto no painel
            // mesmo assim -- cobrança pode ser resolvida depois, o acesso
            // não fica bloqueado por causa disso (mesmo padrão de
            // degradação suave do resto do projeto).
            return redirect()->away($invoiceUrl);
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    private function uniqueSlug(string $companyName): string
    {
        $base = Str::slug($companyName);
        $slug = $base;
        $suffix = 1;

        while (Tenant::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
