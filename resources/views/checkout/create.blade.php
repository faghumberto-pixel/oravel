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
            <x-input-label for="cpf_cnpj" value="CPF ou CNPJ" />
            <x-text-input id="cpf_cnpj" class="block mt-1 w-full" type="text" name="cpf_cnpj" :value="old('cpf_cnpj')" required placeholder="Necessário para a cobrança recorrente" />
            <x-input-error :messages="$errors->get('cpf_cnpj')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="segment" value="Segmento" />
            <select id="segment" name="segment" required
                class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                <option value="" disabled @selected(old('segment') === null)>Selecione...</option>
                @foreach($segments as $value => $label)
                    <option value="{{ $value }}" @selected(old('segment') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('segment')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label value="Tipos de Equipamento" />
            <div class="mt-1 grid grid-cols-2 gap-2">
                @foreach($equipmentTypes as $value => $label)
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="equipment_types[]" value="{{ $value }}"
                            @checked(collect(old('equipment_types', []))->contains($value))
                            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                        {{ $label }}
                    </label>
                @endforeach
            </div>
            <x-input-error :messages="$errors->get('equipment_types')" class="mt-2" />
        </div>

        <div class="mt-6 border-t pt-4">
            <div class="text-sm font-semibold text-gray-700">Endereço</div>

            <div class="mt-2">
                <x-input-label for="cep" value="CEP" />
                <x-text-input id="cep" class="block mt-1 w-full max-w-[10rem]" type="text" name="cep" :value="old('cep')" required placeholder="00000-000" autocomplete="postal-code" />
                <x-input-error :messages="$errors->get('cep')" class="mt-2" />
            </div>

            <div class="mt-4">
                <x-input-label for="logradouro" value="Logradouro" />
                <x-text-input id="logradouro" class="block mt-1 w-full" type="text" name="logradouro" :value="old('logradouro')" required />
                <x-input-error :messages="$errors->get('logradouro')" class="mt-2" />
            </div>

            <div class="mt-4 grid grid-cols-2 gap-4">
                <div>
                    <x-input-label for="numero" value="Número" />
                    <x-text-input id="numero" class="block mt-1 w-full" type="text" name="numero" :value="old('numero')" required />
                    <x-input-error :messages="$errors->get('numero')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="complemento" value="Complemento" />
                    <x-text-input id="complemento" class="block mt-1 w-full" type="text" name="complemento" :value="old('complemento')" />
                    <x-input-error :messages="$errors->get('complemento')" class="mt-2" />
                </div>
            </div>

            <div class="mt-4">
                <x-input-label for="bairro" value="Bairro" />
                <x-text-input id="bairro" class="block mt-1 w-full" type="text" name="bairro" :value="old('bairro')" required />
                <x-input-error :messages="$errors->get('bairro')" class="mt-2" />
            </div>

            <div class="mt-4 grid grid-cols-3 gap-4">
                <div class="col-span-2">
                    <x-input-label for="cidade" value="Cidade" />
                    <x-text-input id="cidade" class="block mt-1 w-full" type="text" name="cidade" :value="old('cidade')" required />
                    <x-input-error :messages="$errors->get('cidade')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="uf" value="UF" />
                    <x-text-input id="uf" class="block mt-1 w-full" type="text" name="uf" :value="old('uf')" required maxlength="2" />
                    <x-input-error :messages="$errors->get('uf')" class="mt-2" />
                </div>
            </div>
        </div>

        <div class="mt-6 border-t pt-4">
            <x-input-label for="admin_name" value="Seu Nome Completo" />
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

        <div class="mt-6 border-t pt-4">
            <label class="flex items-start gap-2 text-sm text-gray-700">
                <input type="checkbox" name="terms_accepted" value="1" required
                    @checked(old('terms_accepted'))
                    class="mt-0.5 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                <span>Li e aprovo as condições de contratação do plano selecionado.</span>
            </label>
            <x-input-error :messages="$errors->get('terms_accepted')" class="mt-2" />
        </div>

        <p class="mt-4 text-xs text-gray-500">
            Você será redirecionado para a página de pagamento da Asaas para concluir a assinatura.
            Seu acesso ao painel é liberado assim que o pagamento for confirmado.
        </p>

        <div class="flex items-center justify-end mt-4">
            <x-primary-button>
                Assinar agora
            </x-primary-button>
        </div>
    </form>

    <script>
        document.getElementById('cep').addEventListener('blur', function () {
            var cep = this.value.replace(/\D/g, '');
            if (cep.length !== 8) {
                return;
            }

            fetch('https://viacep.com.br/ws/' + cep + '/json/')
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (data.erro) {
                        return;
                    }

                    document.getElementById('logradouro').value = data.logradouro || '';
                    document.getElementById('bairro').value = data.bairro || '';
                    document.getElementById('cidade').value = data.localidade || '';
                    document.getElementById('uf').value = data.uf || '';
                })
                .catch(function () {
                    // Falha na busca (offline, API fora do ar) não deve
                    // travar o cadastro -- cliente preenche manualmente.
                });
        });
    </script>
</x-guest-layout>
