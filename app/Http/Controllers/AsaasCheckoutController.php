<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\DocumentSignature;
use App\Models\Plan;
use App\Models\TaxaConfiguracao;
use App\Models\Tenant;
use App\Models\TenantEvent;
use App\Models\User;
use App\Rules\CepExistente;
use App\Rules\CpfCnpj;
use App\Rules\EmailSemErroDeDigitacao;
use App\Services\AsaasService;
use App\Services\SignatureService;
use App\Services\TaxasService;
use App\Services\TenantProvisioner;
use App\Services\TenantTimeline;
use App\Support\Pacotes;
use App\Support\Precos;
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
 *
 * Contrato de Assinatura obrigatório (2026-09-23, pedido do usuário:
 * "ele não pode pagar se não assinar o contrato"): desde então, store()
 * NÃO manda mais direto pro Checkout -- manda primeiro pra assinatura
 * eletrônica do contrato (mesmo mecanismo de Contract/MaintenanceOrder,
 * ver Tenant::class use HasSignatures). O Checkout só é criado depois,
 * em continueAfterSignature(), chamado pelo PublicSignatureController
 * quando a assinatura é confirmada -- o link de pagamento literalmente
 * não existe antes disso, não é uma etapa pulável.
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

        return $this->formulario($selectedPlan);
    }

    /**
     * Link estável por pacote para o site (/assinar/manutencao): resolve o plano de vitrine ATIVO do pacote, sem o
     * site precisar conhecer UUIDs. Pacote desconhecido, sem vitrine ou sem preço/à venda vai para o contato comercial (evita laço com a página de planos).
     */
    public function porPacote(string $pacote): View|RedirectResponse
    {
        $plano = Pacotes::vitrine($pacote);

        if (! $plano || ! $plano->is_active || (float) $plano->price <= 0) {
            return redirect()->away('https://oravel.com.br/contato.php');
        }

        return $this->formulario($plano);
    }

    private function formulario(Plan $selectedPlan): View
    {
        return view('checkout.create', [
            'selectedPlan' => $selectedPlan,
            'vitrine' => Pacotes::ehVitrine($selectedPlan),
            'valorCheio' => (float) $selectedPlan->base_price,
            'valorRecorrente' => Precos::recorrente((float) $selectedPlan->base_price),
            'descontoPercentual' => Precos::descontoPercentual(),
            'taxas' => TaxaConfiguracao::disponiveis()->get(),
            'outrosProdutos' => Pacotes::ehVitrine($selectedPlan) && $selectedPlan->campaign_tag === Pacotes::TAG_VITRINE
                ? collect(Pacotes::vendaveis([(string) $selectedPlan->pacote]))->map(fn (Plan $p, string $c) => [
                    'codigo' => $c, 'nome' => Pacotes::nome($c), 'cheio' => (float) $p->base_price, 'implantacao' => (float) $p->implementation_fee,
                ])->values()->all()
                : [],
            'implantacaoValor' => (float) $selectedPlan->implementation_fee,
            'implantacaoParcelas' => max(1, min(2, (int) $selectedPlan->implementation_installments)),
            'pacoteDescricao' => Pacotes::todos()[$selectedPlan->pacote ?? '']['descricao'] ?? null,
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

    public function store(Request $request, AsaasService $asaas, SignatureService $signatureService): RedirectResponse
    {
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'segment' => ['required', 'string', Rule::in(array_keys(Client::nicheLabels()))],
            'equipment_types' => ['required', 'array', 'min:1'],
            'equipment_types.*' => ['string', Rule::in(array_keys(self::EQUIPMENT_TYPES))],
            'cpf_cnpj' => ['required', 'string', new CpfCnpj],
            'cep' => ['required', 'string', 'regex:/^\d{5}-?\d{3}$/', new CepExistente],
            'logradouro' => ['required', 'string', 'max:255'],
            // O Asaas exige o número do endereço (inteiro): sem algarismos o checkout seria recusado depois da assinatura do contrato.
            'numero' => ['required', 'string', 'max:20', 'regex:/\d/'],
            'complemento' => ['nullable', 'string', 'max:255'],
            'bairro' => ['required', 'string', 'max:255'],
            'cidade' => ['required', 'string', 'max:255'],
            'uf' => ['required', 'string', 'size:2'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255', 'unique:users,email', new EmailSemErroDeDigitacao],
            // Exigido pela Asaas pra criar o Checkout de pagamento (achado
            // real em PROD 2026-09-23: "O campo phoneNumber deve ser
            // informado"). Sem regex de formato -- a Asaas já normaliza o
            // que vier.
            'telefone' => ['required', 'string', 'max:20', function ($attribute, $value, $fail) {
                // DDD + número (10 ou 11 dígitos), como o Asaas aceita; evita descobrir o erro só na hora do pagamento.
                if (! in_array(strlen(preg_replace('/\D/', '', (string) $value)), [10, 11], true)) {
                    $fail('Informe o telefone com DDD, por exemplo (19) 99999-9999.');
                }
            }],
            'admin_password' => ['required', 'string', 'min:8'],
            'plan_id' => ['required', 'uuid', 'exists:plans,id'],
            'produtos_extra' => ['nullable', 'array'],
            'produtos_extra.*' => ['string', Rule::in(array_keys(Pacotes::todos()))],
            'forma_pagamento' => ['nullable', Rule::in(['cartao', 'boleto_pix'])],
            'implantacao' => ['nullable', Rule::in(['contratar', 'nao_contratar'])],
            'taxas' => ['nullable', 'array'],
            'taxas.*' => ['string', 'max:40'],
            'terms_accepted' => ['accepted'],
        ], ['numero.regex' => 'Informe o número do endereço com algarismos (por exemplo, 595).']);

        $plan = Plan::where('is_active', true)->findOrFail($data['plan_id']);

        // Mais de um produto: contrato próprio da empresa com a soma dos valores e a união dos módulos.
        $extras = array_values(array_diff(array_unique((array) ($data['produtos_extra'] ?? [])), [(string) $plan->pacote]));
        if ($extras !== [] && $plan->campaign_tag === Pacotes::TAG_VITRINE) {
            try {
                $plan = Pacotes::planoCombinado($plan, $extras);
            } catch (\InvalidArgumentException) {
                return back()->withInput()->withErrors(['produtos_extra' => 'Um dos produtos escolhidos não está disponível agora. Desmarque-o e tente de novo.']);
            }
        }

        // Planos de vitrine têm valor cheio (a escolha de como pagar define o valor) e implantação opcional.
        // Contratos comuns (link direto) seguem como sempre: valor cobrado fixo e implantação do contrato.
        $vitrine = Pacotes::ehVitrine($plan);
        $comercial = [];

        if ($vitrine) {
            $forma = $data['forma_pagamento'] ?? null;
            $temImplantacao = (float) $plan->implementation_fee > 0;

            $request->validate([
                'forma_pagamento' => ['required'],
                'implantacao' => [$temImplantacao ? 'required' : 'nullable'],
            ], ['forma_pagamento.required' => 'Escolha como pagar a mensalidade.', 'implantacao.required' => 'Informe se quer contratar a implantação.']);

            $comercial = ['payment_method' => $forma, 'mrr_value' => Precos::paraForma((float) $plan->base_price, $forma), 'acesso_por_periodo' => $forma === 'boleto_pix'];

            if ($temImplantacao) {
                $contratar = ($data['implantacao'] ?? 'contratar') === 'contratar' || ! $plan->implantacao_opcional;
                $comercial['implantacao_contratada'] = $contratar;

                if (! $contratar) {
                    $comercial['implementation_fee'] = 0;   // 0 (e não nulo) = dispensada; nulo cairia no valor do contrato
                }
            }
        }

        $tenant = Tenant::create([
            'name' => $data['company_name'],
            'slug' => $this->uniqueSlug($data['company_name']),
            'plan_id' => $plan->id,
            'status' => 'trial',
            'mrr_value' => $plan->price,
            ...$comercial,
            'cpf_cnpj' => $data['cpf_cnpj'],
            'telefone' => $data['telefone'],
            'segment' => $data['segment'],
            'equipment_types' => $data['equipment_types'],
            'cep' => preg_replace('/^(\d{5})(\d{3})$/', '$1-$2', preg_replace('/\D/', '', $data['cep'])),
            'logradouro' => $data['logradouro'],
            'numero' => $data['numero'],
            'complemento' => $data['complemento'] ?? null,
            'bairro' => $data['bairro'],
            'cidade' => $data['cidade'],
            'uf' => strtoupper($data['uf']),
            'terms_accepted_at' => now(),
        ]);

        // Taxas de configuração opcionais (pagamento único): só os códigos escolhidos, com o valor do catálogo no servidor.
        app(TaxasService::class)->aprovar($tenant, (array) ($data['taxas'] ?? []), 'checkout', $request->ip());

        $admin = TenantProvisioner::provision($tenant, [
            'name' => $data['admin_name'],
            'email' => $data['admin_email'],
            'password' => $data['admin_password'],
        ]);

        // Acesso fica bloqueado até o webhook da Asaas confirmar o
        // primeiro pagamento -- ver canAccessPanel() em User.
        $admin->forceFill(['is_approved' => false])->save();

        // syncTenantCustomer() continua sendo chamado aqui -- best-effort,
        // grava tenant.asaas_customer_id o quanto antes (não depende de
        // esperar o webhook, útil pro resto do sistema que já lê esse
        // campo). O Checkout em si só é criado depois de assinar (ver
        // continueAfterSignature() abaixo) -- ele não referencia esse
        // customer id (AsaasService::createTenantCheckout()), então o
        // webhook casa pelo externalReference (tenant->id), não só pelo
        // customer id.
        $asaas->syncTenantCustomer($tenant);

        // Contrato de Assinatura ANTES do pagamento -- o cliente só chega
        // no Checkout depois de assinar (PublicSignatureController::store()
        // redireciona pra continueAfterSignature() quando a assinatura é
        // do tipo Tenant).
        $signatureLink = $signatureService->generateSignatureLink($tenant, [
            'name' => $data['admin_name'],
            'email' => $data['admin_email'],
        ]);

        return redirect()->away($signatureLink);
    }

    /**
     * Chamado só depois que a assinatura do Contrato de Assinatura foi
     * confirmada (ver PublicSignatureController::store()) -- é aqui que o
     * Checkout de pagamento é de fato criado. Verifica de novo se está
     * mesmo assinado (não confia só em ter chegado nesta URL) antes de
     * gerar qualquer link de pagamento.
     */
    public function continueAfterSignature(string $token, AsaasService $asaas): RedirectResponse
    {
        $signature = DocumentSignature::withoutGlobalScope('tenant')->where('token', $token)
            ->where('signable_type', Tenant::class)
            ->first();

        if (! $signature || ! $signature->is_signed) {
            return redirect()->route('checkout.pending');
        }

        $tenant = $signature->signable;

        if (! $tenant) {
            return redirect()->route('checkout.pending');
        }

        $tenant->refresh();

        // Cobranças únicas aprovadas no cadastro: taxas de configuração e implantação (esta só quando o cliente contratou
        // e não vai somada à mensalidade). Idempotentes; falha aqui nunca impede o pagamento da mensalidade.
        try {
            app(TaxasService::class)->cobrar($tenant);

            if ($tenant->implantacao_contratada === true) {
                $asaas->chargeTenantImplementation($tenant);
            }
        } catch (\Throwable $e) {
            report($e);
        }

        // Forma de pagamento definida no contrato: cartão = checkout recorrente automático;
        // boleto/Pix = assinatura mensal (cliente escolhe boleto, Pix ou cartão no link).
        // Quem já tem assinatura mensal volta para a fatura dela (createTenantBoletoPixSubscription
        // é idempotente) -- nunca ganha um checkout de cartão novo (cobraria a mensalidade em dobro).
        $checkoutUrl = ($tenant->paymentMethod() === 'boleto_pix' || filled($tenant->asaas_subscription_id))
            ? $asaas->createTenantBoletoPixSubscription($tenant)
            : $asaas->createTenantCheckout($tenant);

        if ($checkoutUrl) {
            // Sem login algum -- o checkout abre fora do domínio Oravel,
            // pro cliente pagar no cartão/Pix. O acesso ao painel só é
            // liberado pelo webhook (AsaasWebhookController) quando o
            // evento CHECKOUT_PAID chegar.
            return redirect()->away($checkoutUrl);
        }

        // Assinou, mas o Checkout falhou ao criar (Asaas fora do ar, e-mail recusado etc): o cadastro e a assinatura já existem, só falta o
        // link. A equipe é avisada na hora (sino da Central + linha do tempo) e o cliente cai numa tela que deixa tentar de novo ou corrigir o e-mail.
        $motivo = $asaas->ultimoErro;

        TenantTimeline::record(
            $tenant, TenantEvent::CHECKOUT_FALHOU, 'Link de pagamento não gerado',
            $motivo ? 'Motivo informado pelo Asaas: '.mb_substr($motivo, 0, 300) : 'O Asaas não devolveu o link de pagamento (veja o log).',
            dedupeKey: 'checkout-falhou:'.$tenant->id.':'.now()->format('YmdHi'), notify: true, level: 'danger',
        );

        $texto = mb_strtolower((string) $motivo);

        return redirect()->route('checkout.pending', ['t' => $token])->with('motivo_checkout', str_contains($texto, 'postalcode') ? 'cep' : (str_contains($texto, 'email') ? 'email' : 'geral'));
    }

    /** Tela de "pagamento pendente": tentar de novo, corrigir o e-mail (quando foi o motivo) ou falar com a equipe. */
    public function pendente(Request $request): View
    {
        $token = (string) $request->query('t', '');
        $assinatura = $token !== '' ? DocumentSignature::withoutGlobalScope('tenant')->where('token', $token)->where('signable_type', Tenant::class)->first() : null;
        $tenant = $assinatura?->is_signed ? $assinatura->signable : null;

        return view('checkout.pending', [
            'token' => $tenant ? $token : null,
            // Mesmo abrindo a tela depois (sem a sessão do erro), o e-mail com cara de digitação errada já mostra o formulário de correção.
            'motivo' => session('motivo_checkout') === 'email' || ($tenant?->adminUser && EmailSemErroDeDigitacao::sugestao($tenant->adminUser->email)) ? 'email' : session('motivo_checkout'),
            'email' => $tenant?->adminUser?->email,
            'cep' => $tenant?->cep,
            'empresa' => $tenant?->name,
        ]);
    }

    /** O CEP do cadastro foi recusado (não existe): corrige o da empresa (só enquanto nada foi pago) e tenta o pagamento de novo. */
    public function corrigirCep(Request $request, string $token): RedirectResponse
    {
        $assinatura = DocumentSignature::withoutGlobalScope('tenant')->where('token', $token)->where('signable_type', Tenant::class)->first();
        $tenant = $assinatura?->is_signed ? $assinatura->signable : null;

        abort_unless($tenant && blank($tenant->asaas_subscription_id) && blank($tenant->asaas_checkout_id), 404);

        $dados = $request->validate(['cep' => ['required', 'string', 'regex:/^\d{5}-?\d{3}$/', new CepExistente]]);

        $tenant->forceFill(['cep' => preg_replace('/^(\d{5})(\d{3})$/', '$1-$2', preg_replace('/\D/', '', $dados['cep']))])->save();

        return redirect()->route('checkout.continue', ['token' => $token]);
    }

    /** O e-mail do cadastro foi recusado: corrige o do administrador (só enquanto nada foi pago) e tenta o pagamento de novo. */
    public function corrigirEmail(Request $request, string $token): RedirectResponse
    {
        $assinatura = DocumentSignature::withoutGlobalScope('tenant')->where('token', $token)->where('signable_type', Tenant::class)->first();
        $tenant = $assinatura?->is_signed ? $assinatura->signable : null;
        $admin = $tenant?->adminUser;

        abort_unless($tenant && $admin && blank($tenant->asaas_subscription_id) && blank($tenant->asaas_checkout_id), 404);

        $dados = $request->validate(['email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($admin->id), new EmailSemErroDeDigitacao]]);

        $admin->forceFill(['email' => mb_strtolower($dados['email'])])->save();

        return redirect()->route('checkout.continue', ['token' => $token]);
    }

    /**
     * Recuperação de link pra quem já se cadastrou e assinou, mas fechou
     * a aba antes de pagar (pedido do usuário 2026-09-23: "caso o
     * cliente só faça o cadastro e deixe para pagar depois, e nao tenha
     * o link para o checkout"). Sem exigir token nenhum -- só o e-mail
     * que usou no cadastro, já que o link de assinatura/pagamento em si
     * não fica salvo em lugar nenhum acessível pelo cliente depois que a
     * página fecha.
     */
    public function recoverForm(): View
    {
        return view('checkout.recover');
    }

    /**
     * Uma vez que o pagamento é confirmado, esse fluxo de recuperação
     * "pode sumir" (pedido do usuário) -- por isso verifica is_approved
     * PRIMEIRO e manda pro login normal em vez de gerar mais um link de
     * pagamento pra quem já está liberado.
     */
    public function recoverSubmit(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $admin = User::where('email', $data['email'])
            ->where('role', 'admin')
            ->whereNotNull('tenant_id')
            ->first();

        if (! $admin) {
            return back()->withErrors(['email' => 'Não encontramos nenhum cadastro com esse e-mail.'])->withInput();
        }

        if ($admin->is_approved) {
            return back()->withErrors(['email' => 'Esse cadastro já está liberado -- faça login normalmente em app.oravel.com.br/admin/login.'])->withInput();
        }

        $tenant = $admin->tenant;

        if (! $tenant) {
            return back()->withErrors(['email' => 'Não encontramos nenhum cadastro com esse e-mail.'])->withInput();
        }

        $signature = DocumentSignature::where('signable_type', Tenant::class)
            ->where('signable_id', $tenant->id)
            ->latest('created_at')
            ->first();

        if (! $signature) {
            return back()->withErrors(['email' => 'Não encontramos nenhum contrato pendente pra esse e-mail. Entre em contato com o suporte.'])->withInput();
        }

        // Ainda não assinou o contrato -- manda pra etapa de assinatura,
        // não direto pro pagamento (a trava continua valendo aqui).
        if (! $signature->is_signed) {
            return redirect()->route('signature.sign', ['token' => $signature->token]);
        }

        return redirect()->route('checkout.continue', ['token' => $signature->token]);
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
