    <div
        x-data="{ mode: 'login' }"
        x-on:oravel-registered.window="mode = 'login'"
        class="relative min-h-screen w-full overflow-hidden bg-slate-950"
    >
        <div
            class="absolute inset-0 bg-cover bg-center"
            style="background-image: url('{{ asset('images/login-bg.jpg') }}')"
        ></div>
        <div class="absolute inset-0 bg-gradient-to-br from-black/80 via-black/55 to-orange-950/30"></div>

        <div class="relative z-10 flex min-h-screen flex-col md:flex-row">
            {{-- Painel de boas-vindas --}}
            <div class="flex flex-col justify-center px-6 pt-12 md:w-3/5 md:px-16 md:pt-0 lg:px-24">
                <h1 class="text-3xl font-bold text-white md:text-5xl">
                    <span x-show="mode === 'login'">Bem-vindo ao Oravel!</span>
                    <span x-show="mode === 'register'" x-cloak>Cadastrar</span>
                </h1>

                <div class="my-5 h-px w-16 bg-white/40"></div>

                <p class="max-w-md text-base text-white/80">
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
                    class="mt-8 w-fit rounded-full bg-white px-7 py-2.5 text-sm font-bold text-slate-900 shadow-lg transition hover:bg-white/90"
                >
                    <span x-show="mode === 'login'">Cadastrar</span>
                    <span x-show="mode === 'register'" x-cloak>Já tenho conta</span>
                </button>
            </div>

            {{-- Card de login/cadastro --}}
            <div class="flex flex-1 items-center justify-center px-6 py-10 md:px-12">
                <div class="oravel-glass-card w-full max-w-md rounded-3xl border border-white/25 bg-white/15 p-8 shadow-2xl backdrop-blur-xl sm:p-10">
                    <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full border border-white/30 bg-white/10">
                        <x-heroicon-o-bolt class="h-7 w-7 text-white" />
                    </div>

                    <div class="mb-6 flex items-center justify-center gap-3 text-xs">
                        <a href="https://cliente.oravel.com.br" target="_blank" rel="noopener" class="text-white/70 hover:text-white hover:underline">
                            Painel do Cliente
                        </a>
                        <span class="text-white/30">|</span>
                        <a href="https://academy.oravel.com.br/" target="_blank" rel="noopener" class="text-white/70 hover:text-white hover:underline">
                            Oravel Academy
                        </a>
                    </div>

                    {{-- LOGIN: formulario real, autenticacao Filament sem alteracao --}}
                    <div x-show="mode === 'login'">
                        <x-filament-panels::form id="form" wire:submit="authenticate" class="space-y-5">
                            {{ $this->form }}

                            <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
                                <label class="flex items-center gap-2 text-white/80">
                                    <input type="checkbox" wire:model="data.remember" class="oravel-checkbox" />
                                    Lembrar de mim
                                </label>

                                <div class="flex items-center gap-3 text-white/80">
                                    @if (filament()->hasPasswordReset())
                                        <a href="{{ filament()->getRequestPasswordResetUrl() }}" class="hover:text-white hover:underline">
                                            Esqueceu a senha?
                                        </a>
                                        <span class="text-white/30">|</span>
                                    @endif
                                    <button type="button" @click="mode = 'register'" class="hover:text-white hover:underline">
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
                                <label class="flex items-start gap-2 text-sm text-white/80">
                                    <input type="checkbox" wire:model="registerTerms" class="oravel-checkbox mt-0.5" />
                                    <span>Eu aceito os termos de uso e política de privacidade.</span>
                                </label>
                                @error('registerTerms') <p class="oravel-auth-error">{{ $message }}</p> @enderror
                            </div>

                            <button
                                type="submit"
                                class="w-full rounded-full bg-white px-6 py-3 text-sm font-bold uppercase tracking-wide text-slate-900 shadow-lg transition hover:bg-white/90"
                            >
                                Registrar
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <p class="relative z-10 pb-6 text-center text-xs text-white/50">
            © {{ now()->year }} Oravel. Todos os direitos reservados.
        </p>
    </div>

    @push('styles')
        <style>
            .oravel-glass-card :where(.oravel-auth-input, .oravel-auth-input-raw) {
                border-radius: 9999px !important;
                background-color: rgba(255, 255, 255, 0.12) !important;
                border-color: rgba(255, 255, 255, 0.25) !important;
                color: #fff !important;
            }

            .oravel-glass-card :where(.oravel-auth-input, .oravel-auth-input-raw)::placeholder {
                color: rgba(255, 255, 255, 0.6) !important;
            }

            .oravel-glass-card :where(.oravel-auth-input, .oravel-auth-input-raw):focus {
                --tw-ring-color: rgba(249, 115, 22, 0.6) !important;
                border-color: rgba(249, 115, 22, 0.6) !important;
            }

            .oravel-glass-card .fi-input-wrp {
                border-radius: 9999px !important;
                background-color: rgba(255, 255, 255, 0.12) !important;
                border-color: rgba(255, 255, 255, 0.25) !important;
            }

            .oravel-glass-card .fi-input-wrp input {
                color: #fff !important;
            }

            .oravel-glass-card .fi-input-wrp .fi-icon,
            .oravel-glass-card .fi-input-wrp svg {
                color: rgba(255, 255, 255, 0.7) !important;
            }

            .oravel-auth-field {
                display: flex;
                align-items: center;
                gap: 0.625rem;
                border-radius: 9999px;
                border: 1px solid rgba(255, 255, 255, 0.25);
                background-color: rgba(255, 255, 255, 0.12);
                padding: 0.625rem 1.1rem;
            }

            .oravel-auth-field-icon {
                height: 1.1rem;
                width: 1.1rem;
                flex-shrink: 0;
                color: rgba(255, 255, 255, 0.7);
            }

            .oravel-auth-input-raw {
                flex: 1 1 auto;
                border: none;
                background: transparent;
                color: #fff;
                font-size: 0.875rem;
                outline: none;
            }

            .oravel-auth-field-toggle {
                flex-shrink: 0;
                color: rgba(255, 255, 255, 0.6);
            }

            .oravel-checkbox {
                border-radius: 0.25rem;
                border-color: rgba(255, 255, 255, 0.4);
                background-color: rgba(255, 255, 255, 0.1);
            }

            .oravel-glass-card .fi-fo-field-wrp-label,
            .oravel-glass-card .fi-fo-field-wrp-error-message {
                color: rgba(255, 255, 255, 0.85);
            }

            .oravel-auth-error {
                margin-top: 0.375rem;
                margin-left: 0.5rem;
                font-size: 0.75rem;
                color: #fca5a5;
            }
        </style>
    @endpush
