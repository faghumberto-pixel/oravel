<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Plan;
use App\Models\Tenant;
use App\Rules\CpfCnpj;
use App\Services\AsaasService;
use App\Services\TenantProvisioner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Autoatendimento: cliente clica "Assinar" num plano no site institucional
 * (oravel.com.br) e cai aqui, sem precisar de um operador criando o
 * Tenant manualmente no painel Central (fluxo que já existia --
 * ver TenantResource\Pages\CreateTenant).
 *
 * Escopo corrigido 2026-08-13 (a primeira versão liberava acesso
 * imediato via Auth::login() logo no cadastro -- usuário pediu pra
 * corrigir: cadastrar NÃO pode logar automaticamente no sistema, só
 * deve mandar pra tela de pagamento da Asaas). O admin nasce com
 * is_approved=false (canAccessPanel() em User já bloqueia por isso) e
 * só é aprovado quando o webhook da Asaas confirmar o primeiro
 * pagamento (ver AsaasWebhookController::process()).
 */
class AsaasCheckoutController extends Controller
{
    /**
     * Formulário de cadastro -- o plano vem travado do link "Assinar" do
     * site (?plano={uuid}), sem opção de troca aqui. A escolha do plano
     * acontece só no site institucional (oravel.com.br#planos); sem um
     * ?plano= válido, manda de volta pra lá em vez de deixar escolher
     * nesta tela (pedido do usuário 2026-08-14).
     */
    public function create(Request $request): View|RedirectResponse
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

        if (! $selectedPlan) {
            return redirect()->away('https://oravel.com.br/#planos');
        }

        return view('checkout.create', [
            'selectedPlan' => $selectedPlan,
            'segments' => Client::nicheLabels(),
            'equipmentTypes' => self::EQUIPMENT_TYPES,
        ]);
    }

    /**
     * Lista fixa pedida pelo usuário -- não existe um enum de "tipo de
     * equipamento" reaproveitável em nenhum outro lugar do sistema
     * (AssetCategory é cadastro livre por tenant, não serve aqui porque
     * o Tenant ainda não existe neste ponto do fluxo).
     */
    private const EQUIPMENT_TYPES = [
        'gerador' => 'Gerador',
        'compressor' => 'Compressor',
        'plataforma' => 'Plataforma',
        'munk' => 'Munk',
        'empilhadeira' => 'Empilhadeira',
        'outro' => 'Outro',
    ];

    public function store(Request $request, AsaasService $asaas): RedirectResponse
    {
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'segment' => ['required', 'string', Rule::in(array_keys(Client::nicheLabels()))],
            'equipment_types' => ['required', 'array', 'min:1'],
            'equipment_types.*' => ['string', Rule::in(array_keys(self::EQUIPMENT_TYPES))],
            'cpf_cnpj' => ['required', 'string', new CpfCnpj],
            'cep' => ['required', 'string', 'regex:/^\d{5}-?\d{3}$/'],
            'logradouro' => ['required', 'string', 'max:255'],
            'numero' => ['required', 'string', 'max:20'],
            'complemento' => ['nullable', 'string', 'max:255'],
            'bairro' => ['required', 'string', 'max:255'],
            'cidade' => ['required', 'string', 'max:255'],
            'uf' => ['required', 'string', 'size:2'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'admin_password' => ['required', 'string', 'min:8'],
            'plan_id' => ['required', 'uuid', 'exists:plans,id'],
            'terms_accepted' => ['accepted'],
        ]);

        $plan = Plan::findOrFail($data['plan_id']);

        $tenant = Tenant::create([
            'name' => $data['company_name'],
            'slug' => $this->uniqueSlug($data['company_name']),
            'plan_id' => $plan->id,
            'status' => 'trial',
            'mrr_value' => $plan->price,
            'cpf_cnpj' => $data['cpf_cnpj'],
            'segment' => $data['segment'],
            'equipment_types' => $data['equipment_types'],
            'cep' => $data['cep'],
            'logradouro' => $data['logradouro'],
            'numero' => $data['numero'],
            'complemento' => $data['complemento'] ?? null,
            'bairro' => $data['bairro'],
            'cidade' => $data['cidade'],
            'uf' => strtoupper($data['uf']),
            'terms_accepted_at' => now(),
        ]);

        $admin = TenantProvisioner::provision($tenant, [
            'name' => $data['admin_name'],
            'email' => $data['admin_email'],
            'password' => $data['admin_password'],
        ]);

        // Acesso fica bloqueado até o webhook da Asaas confirmar o
        // primeiro pagamento -- ver canAccessPanel() em User.
        $admin->forceFill(['is_approved' => false])->save();

        $asaas->syncTenantCustomer($tenant);
        $tenant->refresh();

        $invoiceUrl = $tenant->asaas_subscription_id
            ? $asaas->getFirstInvoiceUrl($tenant->asaas_subscription_id)
            : null;

        if ($invoiceUrl) {
            // Sem login algum -- a fatura abre fora do domínio Oravel,
            // pro cliente pagar. O acesso ao painel só é liberado pelo
            // webhook (AsaasWebhookController) quando o pagamento cair.
            return redirect()->away($invoiceUrl);
        }

        // Assinatura falhou ao sincronizar (Asaas fora do ar, etc) --
        // sem link de fatura pra mandar o cliente, mas o cadastro em si
        // foi criado. Mostra uma tela de confirmação em vez de tentar
        // levar pro painel (que estaria bloqueado mesmo, is_approved=false).
        return redirect()->route('checkout.pending');
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
