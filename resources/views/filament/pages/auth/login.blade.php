    <div
        x-data="{ mode: 'login' }"
        x-on:oravel-registered.window="mode = 'login'"
        class="oravel-login-root relative min-h-screen w-full overflow-hidden"
    >
        <div class="relative z-10 flex min-h-screen flex-col md:flex-row">
            {{-- Painel de boas-vindas --}}
            <div class="flex flex-col justify-center px-6 pt-12 md:w-3/5 md:px-16 md:pt-0 lg:px-24">
                <h1 class="oravel-login-title text-3xl font-bold md:text-5xl">
                    <span x-show="mode === 'login'">Bem-vindo ao Oravel!</span>
                    <span x-show="mode === 'register'" x-cloak>Cadastrar</span>
                </h1>

                <div class="oravel-login-divider my-5 h-px w-16"></div>

                <p class="oravel-login-lead max-w-md text-base">
                    <span x-show="mode === 'login'">
                        Simplifique a gestão da sua locadora de equipamentos com nossa plataforma SaaS. Cadastre-se ou faça login.
                    </span>
                    <span x-show="mode === 'register'" x-cloak>
                        Crie sua conta, é rápido e leva menos de um minuto.
                    </span>
                </p>

                <button
                    type="button"
                    @click="mode = (mode === 'login' ? 'register' : 'login')"
                    class="oravel-login-btn-secondary mt-8 w-fit rounded-full px-7 py-2.5 text-sm font-bold transition"
                >
                    <span x-show="mode === 'login'">Cadastrar</span>
                    <span x-show="mode === 'register'" x-cloak>Já tenho conta</span>
                </button>
            </div>

            {{-- Card de login/cadastro --}}
            <div class="flex flex-1 items-center justify-center px-6 py-10 md:px-12">
                <div class="oravel-glass-card w-full max-w-md p-8 sm:p-10">
                    <div class="oravel-login-logo mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full">
                        <img src="{{ asset('images/oravel-logo-or.png') }}" alt="Oravel" class="h-9 w-9">
                    </div>


                    {{-- LOGIN: formulario real, autenticacao Filament sem alteracao --}}
                    <div x-show="mode === 'login'">
                        <x-filament-panels::form id="form" wire:submit="authenticate" class="space-y-5">
                            {{ $this->form }}

                            <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
                                <label class="flex items-center gap-2 oravel-login-muted">
                                    <input type="checkbox" wire:model="data.remember" class="oravel-checkbox" />
                                    Lembrar de mim
                                </label>

                                <div class="flex items-center gap-3 oravel-login-muted">
                                    @if (filament()->hasPasswordReset())
                                        <a href="{{ filament()->getRequestPasswordResetUrl() }}" class="oravel-login-link hover:underline">
                                            Esqueceu a senha?
                                        </a>
                                        <span class="oravel-login-sep">|</span>
                                    @endif
                                    <button type="button" @click="mode = 'register'" class="oravel-login-link hover:underline">
                                        Criar Conta
                                    </button>
                                </div>
                            </div>

                            <x-filament-panels::form.actions
                                :actions="$this->getCachedFormActions()"
                                :full-width="$this->hasFullWidthFormActions()"
                            />
                        </x-filament-panels::form>
                    </div>

                    {{-- CADASTRO: cria conta de verdade, pendente de aprovacao do admin do tenant --}}
                    <div x-show="mode === 'register'" x-cloak>
                        <form wire:submit="register" x-data="{ pw: false, pw2: false }" class="space-y-4">
                            <div>
                                <div class="oravel-auth-field">
                                    <x-heroicon-o-user class="oravel-auth-field-icon" />
                                    <input type="text" wire:model="registerName" placeholder="Nome Completo" class="oravel-auth-input-raw" autocomplete="name" />
                                </div>
                                @error('registerName') <p class="oravel-auth-error">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <div class="oravel-auth-field">
                                    <x-heroicon-o-building-office class="oravel-auth-field-icon" />
                                    <input type="text" wire:model="registerTenantSlug" placeholder="Código da Empresa" class="oravel-auth-input-raw" autocomplete="off" />
                                </div>
                                @error('registerTenantSlug') <p class="oravel-auth-error">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <div class="oravel-auth-field">
                                    <x-heroicon-o-envelope class="oravel-auth-field-icon" />
                                    <input type="email" wire:model="registerEmail" placeholder="E-mail" class="oravel-auth-input-raw" autocomplete="email" />
                                </div>
                                @error('registerEmail') <p class="oravel-auth-error">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <div class="oravel-auth-field">
                                    <x-heroicon-o-lock-closed class="oravel-auth-field-icon" />
                                    <input :type="pw ? 'text' : 'password'" wire:model="registerPassword" placeholder="Password" class="oravel-auth-input-raw" autocomplete="new-password" />
                                    <button type="button" @click="pw = !pw" class="oravel-auth-field-toggle">
                                        <x-heroicon-o-eye x-show="!pw" class="h-4 w-4" />
                                        <x-heroicon-o-eye-slash x-show="pw" x-cloak class="h-4 w-4" />
                                    </button>
                                </div>
                                @error('registerPassword') <p class="oravel-auth-error">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <div class="oravel-auth-field">
                                    <x-heroicon-o-lock-closed class="oravel-auth-field-icon" />
                                    <input :type="pw2 ? 'text' : 'password'" wire:model="registerPasswordConfirmation" placeholder="Confirm Password" class="oravel-auth-input-raw" autocomplete="new-password" />
                                    <button type="button" @click="pw2 = !pw2" class="oravel-auth-field-toggle">
                                        <x-heroicon-o-eye x-show="!pw2" class="h-4 w-4" />
                                        <x-heroicon-o-eye-slash x-show="pw2" x-cloak class="h-4 w-4" />
                                    </button>
                                </div>
                            </div>

                            <div>
                                <label class="flex items-start gap-2 text-sm oravel-login-muted">
                                    <input type="checkbox" wire:model="registerTerms" class="oravel-checkbox mt-0.5" />
                                    <span>Eu aceito os termos de uso e política de privacidade.</span>
                                </label>
                                @error('registerTerms') <p class="oravel-auth-error">{{ $message }}</p> @enderror
                            </div>

                            <button
                                type="submit"
                                class="oravel-login-btn-secondary w-full rounded-full px-6 py-3 text-sm font-bold uppercase tracking-wide transition"
                            >
                                Registrar
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <p class="oravel-login-muted relative z-10 pb-6 text-center text-xs">
            © {{ now()->year }} Oravel. Todos os direitos reservados.
        </p>
    </div>

    @push('styles')
        <style>
            /* Visual alinhado ao /entrar do oravel.org: creme liso + cartao branco. */
            .oravel-login-root { background: #FAF6EE; }
            .oravel-login-title { color: #0B2B2A; font-family: 'Syne', 'Inter', sans-serif; }
            .oravel-login-divider { background: #F28C1B; }
            .oravel-login-lead, .oravel-login-muted { color: #5B6B69; }
            .oravel-login-sep { color: #E4DFD3; }
            .oravel-login-link:hover { color: #0B2B2A; }
            .oravel-login-btn-secondary { background: #0B2B2A; color: #fff; box-shadow: 0 4px 20px rgba(0,0,0,.05); }
            .oravel-login-btn-secondary:hover { background: #0F3B38; }
            .oravel-login-logo { background: #0B2B2A; border: 1px solid #0B2B2A; }

            .oravel-glass-card {
                background: #FFFFFF;
                border-radius: 16px;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            }

            .oravel-glass-card :where(.oravel-auth-input, .oravel-auth-input-raw) {
                color: #12201F !important;
            }

            .oravel-glass-card :where(.oravel-auth-input, .oravel-auth-input-raw)::placeholder {
                color: #6B7280 !important;
            }

            .oravel-glass-card .fi-input-wrp {
                border-radius: 9999px !important;
                background-color: #FFFFFF !important;
                border-color: #D5DEDC !important;
                --tw-ring-color: #C3CFCC !important;
                --tw-ring-shadow: 0 0 0 1px #C3CFCC !important;
                box-shadow: 0 0 0 1px #C3CFCC !important;
            }

            .oravel-glass-card .fi-input-wrp:focus-within {
                border-color: #0F3B38 !important;
                --tw-ring-color: #0F3B38 !important;
                --tw-ring-shadow: 0 0 0 1px #0F3B38 !important;
                box-shadow: 0 0 0 3px rgba(15, 59, 56, 0.15), 0 0 0 1px #0F3B38 !important;
            }

            .oravel-glass-card .fi-input-wrp input {
                color: #12201F !important;
            }

            .oravel-glass-card .fi-input-wrp .fi-icon,
            .oravel-glass-card .fi-input-wrp svg {
                color: #5B6B69 !important;
            }

            .oravel-auth-field {
                display: flex;
                align-items: center;
                gap: 0.625rem;
                border-radius: 9999px;
                border: 1px solid #D5DEDC;
                background-color: #FFFFFF;
                padding: 0.625rem 1.1rem;
            }

            .oravel-auth-field:focus-within {
                border-color: #0F3B38;
                box-shadow: 0 0 0 3px rgba(15, 59, 56, 0.15);
            }

            .oravel-auth-field-icon {
                height: 1.1rem;
                width: 1.1rem;
                flex-shrink: 0;
                color: #5B6B69;
            }

            .oravel-auth-input-raw {
                flex: 1 1 auto;
                border: none;
                background: transparent;
                color: #12201F;
                font-size: 0.875rem;
                outline: none;
            }

            .oravel-auth-field-toggle {
                flex-shrink: 0;
                color: #5B6B69;
            }

            .oravel-checkbox {
                border-radius: 0.25rem;
                border-color: #D5DEDC;
                background-color: #FFFFFF;
            }

            .oravel-glass-card .fi-fo-field-wrp-label,
            .oravel-glass-card .fi-fo-field-wrp-error-message {
                color: #12201F;
            }

            .oravel-auth-error {
                margin-top: 0.375rem;
                margin-left: 0.5rem;
                font-size: 0.75rem;
                color: #EF4444;
            }
        </style>
    @endpush
