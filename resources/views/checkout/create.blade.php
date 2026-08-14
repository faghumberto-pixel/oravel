<x-guest-layout>
    <div class="mb-4 text-lg font-bold text-gray-900">
        Assine o Oravel
    </div>

    <form method="POST" action="{{ route('checkout.store') }}">
        @csrf

        <div>
            <x-input-label for="plan_id" value="Plano" />
            <select id="plan_id" name="plan_id" required
                class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                @foreach($plans as $plan)
                    <option value="{{ $plan->id }}" @selected(old('plan_id', $selectedPlan?->id) === $plan->id)>
                        {{ $plan->name }} -- R$ {{ number_format($plan->price, 2, ',', '.') }}/{{ $plan->billing_cycle === 'monthly' ? 'mês' : $plan->billing_cycle }}
                    </option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('plan_id')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="company_name" value="Nome da Empresa" />
            <x-text-input id="company_name" class="block mt-1 w-full" type="text" name="company_name" :value="old('company_name')" required autofocus />
            <x-input-error :messages="$errors->get('company_name')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="cpf_cnpj" value="CPF/CNPJ" />
            <x-text-input id="cpf_cnpj" class="block mt-1 w-full" type="text" name="cpf_cnpj" :value="old('cpf_cnpj')" required placeholder="Necessário para a cobrança recorrente" />
            <x-input-error :messages="$errors->get('cpf_cnpj')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="admin_name" value="Seu Nome" />
            <x-text-input id="admin_name" class="block mt-1 w-full" type="text" name="admin_name" :value="old('admin_name')" required />
            <x-input-error :messages="$errors->get('admin_name')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="admin_email" value="Seu E-mail" />
            <x-text-input id="admin_email" class="block mt-1 w-full" type="email" name="admin_email" :value="old('admin_email')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('admin_email')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="admin_password" value="Senha" />
            <x-text-input id="admin_password" class="block mt-1 w-full" type="password" name="admin_password" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('admin_password')" class="mt-2" />
        </div>

        <p class="mt-4 text-xs text-gray-500">
            Seu acesso ao painel é liberado imediatamente após o cadastro. Você será redirecionado
            para a página de pagamento da Asaas para concluir a assinatura.
        </p>

        <div class="flex items-center justify-end mt-4">
            <x-primary-button>
                Assinar agora
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
