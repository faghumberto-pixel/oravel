<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Assine o Oravel</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        <script src="https://cdn.tailwindcss.com"></script>

        <style>
            .oravel-glass-card :where(.oravel-auth-input) {
                border-radius: 0.75rem;
                background-color: rgba(255, 255, 255, 0.12);
                border: 1px solid rgba(255, 255, 255, 0.25);
                color: #fff;
            }

            .oravel-auth-input::placeholder { color: rgba(255, 255, 255, 0.55); }

            .oravel-auth-input:focus {
                outline: none;
                --tw-ring-color: rgba(249, 115, 22, 0.6);
                box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.35);
                border-color: rgba(249, 115, 22, 0.6);
            }

            select.oravel-auth-input option { color: #111827; }

            .oravel-auth-field {
                display: flex;
                align-items: center;
                gap: 0.625rem;
                border-radius: 0.75rem;
                border: 1px solid rgba(255, 255, 255, 0.25);
                background-color: rgba(255, 255, 255, 0.12);
                padding: 0.625rem 1rem;
            }

            .oravel-auth-field:focus-within {
                --tw-ring-color: rgba(249, 115, 22, 0.6);
                box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.35);
                border-color: rgba(249, 115, 22, 0.6);
            }

            .oravel-auth-field-icon { height: 1.1rem; width: 1.1rem; flex-shrink: 0; color: rgba(255, 255, 255, 0.7); }

            .oravel-auth-input-raw {
                flex: 1 1 auto;
                border: none;
                background: transparent;
                color: #fff;
                font-size: 0.875rem;
                outline: none;
            }

            .oravel-auth-input-raw::placeholder { color: rgba(255, 255, 255, 0.55); }

            .oravel-auth-label { font-size: 0.8rem; font-weight: 500; color: rgba(255, 255, 255, 0.75); margin-bottom: 0.375rem; display: block; }

            .oravel-auth-error { margin-top: 0.375rem; font-size: 0.75rem; color: #fca5a5; }

            .oravel-checkbox {
                border-radius: 0.25rem;
                border-color: rgba(255, 255, 255, 0.4);
                background-color: rgba(255, 255, 255, 0.1);
            }
        </style>
    </head>
    <body class="font-sans antialiased">
        <div
            class="relative min-h-screen w-full overflow-hidden bg-slate-950"
            style="background-image: linear-gradient(to bottom right, rgba(0,0,0,0.8), rgba(0,0,0,0.55), rgba(67,20,7,0.3)), url('{{ asset('images/login-bg.jpg') }}'); background-size: cover; background-position: center;"
        >
            <div class="relative z-10 flex min-h-screen flex-col items-center justify-center px-6 py-12">
                <a href="https://oravel.com.br" class="mb-6 flex items-center gap-3">
                    <span class="flex h-14 w-14 items-center justify-center rounded-full border border-white/30 bg-white/10">
                        <x-heroicon-o-bolt class="h-7 w-7 text-white" />
                    </span>
                    <span class="text-2xl font-bold text-white">Oravel</span>
                </a>

                <div class="oravel-glass-card w-full max-w-2xl rounded-3xl border border-white/25 bg-white/15 p-8 shadow-2xl backdrop-blur-xl sm:p-10">
                    <h1 class="text-2xl font-bold text-white sm:text-3xl">Bem-vindo ao Oravel!</h1>
                    <p class="mt-2 max-w-lg text-sm text-white/75">
                        Falta pouco para simplificar a gestão da sua frota e manutenção. Preencha seus
                        dados para ativar o plano <strong class="text-white">{{ $selectedPlan?->name ?? 'escolhido' }}</strong>.
                    </p>

                    <div class="my-6 h-px w-full bg-white/15"></div>

                    <form method="POST" action="{{ route('checkout.store') }}" class="space-y-5">
                        @csrf

                        <div>
                            <label for="plan_id" class="oravel-auth-label">Plano</label>
                            <select id="plan_id" name="plan_id" required class="oravel-auth-input w-full px-4 py-2.5 text-sm">
                                @foreach($plans as $plan)
                                    <option value="{{ $plan->id }}" @selected(old('plan_id', $selectedPlan?->id) === $plan->id)>
                                        {{ $plan->name }} -- R$ {{ number_format($plan->price, 2, ',', '.') }}/{{ $plan->billing_cycle === 'monthly' ? 'mês' : $plan->billing_cycle }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('plan_id')" class="oravel-auth-error" />
                        </div>

                        <div class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <label class="oravel-auth-label">Nome da Empresa</label>
                                <div class="oravel-auth-field">
                                    <x-heroicon-o-building-office-2 class="oravel-auth-field-icon" />
                                    <input type="text" name="company_name" value="{{ old('company_name') }}" required autofocus
                                        placeholder="Ex: Locações Silva Ltda" class="oravel-auth-input-raw" />
                                </div>
                                <x-input-error :messages="$errors->get('company_name')" class="oravel-auth-error" />
                            </div>

                            <div>
                                <label class="oravel-auth-label">CPF ou CNPJ</label>
                                <div class="oravel-auth-field">
                                    <x-heroicon-o-identification class="oravel-auth-field-icon" />
                                    <input type="text" name="cpf_cnpj" value="{{ old('cpf_cnpj') }}" required
                                        placeholder="Necessário para a cobrança" class="oravel-auth-input-raw" />
                                </div>
                                <x-input-error :messages="$errors->get('cpf_cnpj')" class="oravel-auth-error" />
                            </div>
                        </div>

                        <div>
                            <label for="segment" class="oravel-auth-label">Segmento</label>
                            <select id="segment" name="segment" required class="oravel-auth-input w-full px-4 py-2.5 text-sm">
                                <option value="" disabled @selected(old('segment') === null)>Selecione...</option>
                                @foreach($segments as $value => $label)
                                    <option value="{{ $value }}" @selected(old('segment') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('segment')" class="oravel-auth-error" />
                        </div>

                        <div>
                            <span class="oravel-auth-label">Tipos de Equipamento</span>
                            <div class="grid grid-cols-2 gap-x-4 gap-y-2 sm:grid-cols-3">
                                @foreach($equipmentTypes as $value => $label)
                                    <label class="flex items-center gap-2 text-sm text-white/85">
                                        <input type="checkbox" name="equipment_types[]" value="{{ $value }}"
                                            @checked(collect(old('equipment_types', []))->contains($value))
                                            class="oravel-checkbox">
                                        {{ $label }}
                                    </label>
                                @endforeach
                            </div>
                            <x-input-error :messages="$errors->get('equipment_types')" class="oravel-auth-error" />
                        </div>

                        <div class="border-t border-white/15 pt-5">
                            <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-white/50">Endereço</p>

                            <div class="grid gap-5 sm:grid-cols-3">
                                <div>
                                    <label class="oravel-auth-label">CEP</label>
                                    <div class="oravel-auth-field">
                                        <x-heroicon-o-map-pin class="oravel-auth-field-icon" />
                                        <input type="text" id="cep" name="cep" value="{{ old('cep') }}" required
                                            placeholder="00000-000" autocomplete="postal-code" class="oravel-auth-input-raw">
                                    </div>
                                    <x-input-error :messages="$errors->get('cep')" class="oravel-auth-error" />
                                </div>

                                <div class="sm:col-span-2">
                                    <label class="oravel-auth-label">Logradouro</label>
                                    <div class="oravel-auth-field">
                                        <x-heroicon-o-map class="oravel-auth-field-icon" />
                                        <input type="text" id="logradouro" name="logradouro" value="{{ old('logradouro') }}" required class="oravel-auth-input-raw">
                                    </div>
                                    <x-input-error :messages="$errors->get('logradouro')" class="oravel-auth-error" />
                                </div>
                            </div>

                            <div class="mt-5 grid gap-5 sm:grid-cols-2">
                                <div>
                                    <label class="oravel-auth-label">Número</label>
                                    <div class="oravel-auth-field">
                                        <input type="text" id="numero" name="numero" value="{{ old('numero') }}" required class="oravel-auth-input-raw">
                                    </div>
                                    <x-input-error :messages="$errors->get('numero')" class="oravel-auth-error" />
                                </div>
                                <div>
                                    <label class="oravel-auth-label">Complemento</label>
                                    <div class="oravel-auth-field">
                                        <input type="text" id="complemento" name="complemento" value="{{ old('complemento') }}" class="oravel-auth-input-raw">
                                    </div>
                                    <x-input-error :messages="$errors->get('complemento')" class="oravel-auth-error" />
                                </div>
                            </div>

                            <div class="mt-5 grid gap-5 sm:grid-cols-3">
                                <div class="sm:col-span-1">
                                    <label class="oravel-auth-label">Bairro</label>
                                    <div class="oravel-auth-field">
                                        <input type="text" id="bairro" name="bairro" value="{{ old('bairro') }}" required class="oravel-auth-input-raw">
                                    </div>
                                    <x-input-error :messages="$errors->get('bairro')" class="oravel-auth-error" />
                                </div>
                                <div>
                                    <label class="oravel-auth-label">Cidade</label>
                                    <div class="oravel-auth-field">
                                        <input type="text" id="cidade" name="cidade" value="{{ old('cidade') }}" required class="oravel-auth-input-raw">
                                    </div>
                                    <x-input-error :messages="$errors->get('cidade')" class="oravel-auth-error" />
                                </div>
                                <div>
                                    <label class="oravel-auth-label">UF</label>
                                    <div class="oravel-auth-field">
                                        <input type="text" id="uf" name="uf" value="{{ old('uf') }}" required maxlength="2" class="oravel-auth-input-raw">
                                    </div>
                                    <x-input-error :messages="$errors->get('uf')" class="oravel-auth-error" />
                                </div>
                            </div>
                        </div>

                        <div class="border-t border-white/15 pt-5">
                            <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-white/50">Seus Dados de Acesso</p>

                            <div class="space-y-5">
                                <div>
                                    <label class="oravel-auth-label">Nome Completo</label>
                                    <div class="oravel-auth-field">
                                        <x-heroicon-o-user class="oravel-auth-field-icon" />
                                        <input type="text" name="admin_name" value="{{ old('admin_name') }}" required class="oravel-auth-input-raw">
                                    </div>
                                    <x-input-error :messages="$errors->get('admin_name')" class="oravel-auth-error" />
                                </div>

                                <div class="grid gap-5 sm:grid-cols-2">
                                    <div>
                                        <label class="oravel-auth-label">E-mail</label>
                                        <div class="oravel-auth-field">
                                            <x-heroicon-o-envelope class="oravel-auth-field-icon" />
                                            <input type="email" name="admin_email" value="{{ old('admin_email') }}" required
                                                autocomplete="username" class="oravel-auth-input-raw">
                                        </div>
                                        <x-input-error :messages="$errors->get('admin_email')" class="oravel-auth-error" />
                                    </div>

                                    <div x-data="{ show: false }">
                                        <label class="oravel-auth-label">Senha</label>
                                        <div class="oravel-auth-field">
                                            <x-heroicon-o-lock-closed class="oravel-auth-field-icon" />
                                            <input :type="show ? 'text' : 'password'" name="admin_password" required
                                                autocomplete="new-password" class="oravel-auth-input-raw">
                                            <button type="button" @click="show = !show" class="flex-shrink-0 text-white/60">
                                                <x-heroicon-o-eye x-show="!show" class="h-4 w-4" />
                                                <x-heroicon-o-eye-slash x-show="show" x-cloak class="h-4 w-4" />
                                            </button>
                                        </div>
                                        <x-input-error :messages="$errors->get('admin_password')" class="oravel-auth-error" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="border-t border-white/15 pt-5">
                            <label class="flex items-start gap-2 text-sm text-white/80">
                                <input type="checkbox" name="terms_accepted" value="1" required
                                    @checked(old('terms_accepted')) class="oravel-checkbox mt-0.5">
                                <span>Li e aprovo as condições de contratação do plano selecionado.</span>
                            </label>
                            <x-input-error :messages="$errors->get('terms_accepted')" class="oravel-auth-error" />
                        </div>

                        <p class="text-xs text-white/50">
                            Você será redirecionado para a página de pagamento da Asaas para concluir a assinatura.
                            Seu acesso ao painel é liberado assim que o pagamento for confirmado.
                        </p>

                        <button type="submit"
                            class="w-full rounded-full px-6 py-3 text-sm font-bold uppercase tracking-wide text-white shadow-lg transition hover:opacity-90"
                            style="background-color: #ea580c;">
                            Assinar agora
                        </button>
                    </form>
                </div>

                <p class="relative z-10 mt-8 text-center text-xs text-white/50">
                    © {{ now()->year }} Oravel. Todos os direitos reservados.
                </p>
            </div>
        </div>

        <script src="//unpkg.com/alpinejs" defer></script>
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
    </body>
</html>
