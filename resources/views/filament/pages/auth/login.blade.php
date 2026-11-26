    <div class="oravel-login-root relative min-h-screen w-full overflow-hidden">
        {{-- IMPORTANTE: o Livewire exige UM ÚNICO elemento raiz. Estes <link> ficavam soltos antes do <div> e viravam a "raiz" do
             componente: o formulário ficava de fora, o Livewire não o interceptava e o login enviava POST nativo (erro 405). --}}
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=playfair-display:600,700&display=swap" rel="stylesheet" />
        {{-- Foto do patio de equipamentos + camada verde da marca (maturidade, sem perder o setor) --}}
        <div
            class="absolute inset-0 bg-cover bg-center"
            style="background-image: url('{{ asset('images/login-bg.jpg').'?v=3' }}')"
        ></div>
        <div class="oravel-login-overlay absolute inset-0"></div>

        <div class="relative z-10 flex min-h-screen flex-col md:flex-row">
            {{-- Painel institucional --}}
            <div class="flex flex-col justify-center px-6 pt-12 md:w-3/5 md:px-16 md:pt-0 lg:px-24">
                <p class="oravel-login-eyebrow">Oravel</p>

                <h1 class="oravel-login-title">
                    Gestão com a solidez de quem conhece a locação.
                </h1>

                <div class="oravel-login-divider my-6 h-px w-20"></div>

                <p class="oravel-login-lead max-w-lg">
                    Manutenção, contratos, frota, estoque e financeiro da sua locadora de equipamentos em um só lugar.
                </p>
            </div>

            {{-- Card de acesso (sem cadastro: as contas sao criadas internamente) --}}
            <div class="flex flex-1 items-center justify-center px-6 py-10 md:px-12">
                <div class="oravel-glass-card w-full max-w-md p-8 sm:p-10">
                    <div class="oravel-login-logo mx-auto mb-2 flex h-14 w-14 items-center justify-center rounded-full">
                        <img src="{{ asset('images/oravel-logo-or.png') }}" alt="Oravel" class="h-9 w-9">
                    </div>

                    <h2 class="oravel-card-title">Acesse sua conta</h2>

                    @if (request()->query('confirmado') === '1')
                        <p class="mb-4 rounded-lg px-3 py-2 text-sm" style="background:rgba(16,185,129,.16);border:1px solid rgba(16,185,129,.5)" data-confirmado>E-mail confirmado. Entre com o seu e-mail e a senha que você criou.</p>
                    @elseif (request()->query('confirmado') === '0')
                        <p class="mb-4 rounded-lg px-3 py-2 text-sm" style="background:rgba(245,158,11,.16);border:1px solid rgba(245,158,11,.5)" data-link-encerrado>O acesso por link foi encerrado. Entre com o seu e-mail e a sua senha. Se esqueceu a senha, use "Esqueci minha senha".</p>
                    @endif

                    <x-filament-panels::form id="form" wire:submit="authenticate" class="space-y-5">
                        {{ $this->form }}

                        <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
                            <label class="oravel-login-muted flex items-center gap-2">
                                <input type="checkbox" wire:model="data.remember" class="oravel-checkbox" />
                                Lembrar de mim
                            </label>

                            {{-- O painel não usa o reset do Filament: a redefinição é a da própria aplicação (/forgot-password), já no tema da Oravel. --}}
                            @if (Route::has('password.request'))
                                <a href="{{ route('password.request') }}" class="oravel-login-muted hover:underline" data-esqueci-senha>
                                    Esqueci minha senha
                                </a>
                            @endif
                        </div>

                        <x-filament-panels::form.actions
                            :actions="$this->getCachedFormActions()"
                            :full-width="$this->hasFullWidthFormActions()"
                        />
                    </x-filament-panels::form>
                </div>
            </div>
        </div>

        <p class="oravel-login-muted relative z-10 pb-6 text-center text-xs">
            © {{ now()->year }} Oravel. Todos os direitos reservados.
        </p>
    </div>

    @push('styles')
        <style>
            .oravel-login-root { background: #0B2B2A; }

            /* Verde da marca por cima da foto: escuro a esquerda (texto), mais aberto a direita */
            .oravel-login-overlay {
                background:
                    linear-gradient(100deg, rgba(11, 43, 42, 0.94) 0%, rgba(11, 43, 42, 0.82) 45%, rgba(11, 43, 42, 0.62) 100%);
            }

            .oravel-login-eyebrow {
                margin: 0 0 1rem;
                font-size: 0.8rem;
                font-weight: 600;
                letter-spacing: 0.28em;
                text-transform: uppercase;
                color: #F28C1B;
            }

            .oravel-login-title {
                max-width: 34rem;
                margin: 0;
                font-family: 'Playfair Display', Georgia, serif;
                font-weight: 700;
                font-size: clamp(2rem, 4.2vw, 3.4rem);
                line-height: 1.12;
                color: #FFFFFF;
            }

            .oravel-login-divider { background: #F28C1B; }
            .oravel-login-lead { font-size: 1.05rem; line-height: 1.6; color: rgba(255, 255, 255, 0.78); }
            .oravel-login-muted { color: rgba(255, 255, 255, 0.7); }
            .oravel-login-logo { background: #0B2B2A; border: 1px solid rgba(242, 140, 27, 0.5); }

            .oravel-card-title {
                margin: 0 0 1.5rem;
                text-align: center;
                font-family: 'Playfair Display', Georgia, serif;
                font-size: 1.5rem;
                font-weight: 600;
                color: #FFFFFF;
            }

            .oravel-glass-card {
                background: rgba(11, 43, 42, 0.78);
                border: 1px solid rgba(255, 255, 255, 0.14);
                border-radius: 16px;
                box-shadow: 0 20px 50px rgba(0, 0, 0, 0.35);
                backdrop-filter: blur(10px);
            }

            .oravel-glass-card :where(.oravel-auth-input, .oravel-auth-input-raw) {
                background-color: transparent !important;
                color: #FFFFFF !important;
            }

            .oravel-glass-card :where(.oravel-auth-input, .oravel-auth-input-raw)::placeholder {
                color: rgba(255, 255, 255, 0.55) !important;
            }

            .oravel-glass-card .fi-input-wrp {
                border-radius: 9999px !important;
                background-color: rgba(255, 255, 255, 0.08) !important;
                --tw-ring-color: rgba(255, 255, 255, 0.25) !important;
                --tw-ring-shadow: 0 0 0 1px rgba(255, 255, 255, 0.25) !important;
                box-shadow: 0 0 0 1px rgba(255, 255, 255, 0.25) !important;
            }

            .oravel-glass-card .fi-input-wrp:focus-within {
                --tw-ring-color: #F28C1B !important;
                --tw-ring-shadow: 0 0 0 1px #F28C1B !important;
                box-shadow: 0 0 0 3px rgba(242, 140, 27, 0.25), 0 0 0 1px #F28C1B !important;
            }

            .oravel-glass-card .fi-input-wrp input { color: #FFFFFF !important; }

            .oravel-glass-card .fi-input-wrp .fi-icon,
            .oravel-glass-card .fi-input-wrp svg { color: rgba(255, 255, 255, 0.7) !important; }

            .oravel-glass-card .fi-btn {
                border-radius: 9999px !important;
                background-color: #F28C1B !important;
                color: #0B2B2A !important;
                font-weight: 700;
                letter-spacing: 0.04em;
            }

            .oravel-glass-card .fi-btn:hover { background-color: #D97A0C !important; }

            .oravel-checkbox {
                border-radius: 0.25rem;
                border-color: rgba(255, 255, 255, 0.4);
                background-color: rgba(255, 255, 255, 0.1);
            }

            .oravel-glass-card .fi-fo-field-wrp-label,
            .oravel-glass-card .fi-fo-field-wrp-error-message { color: rgba(255, 255, 255, 0.85); }
            .oravel-glass-card .fi-fo-field-wrp-error-message { color: #FCA5A5; }
        </style>
    @endpush
