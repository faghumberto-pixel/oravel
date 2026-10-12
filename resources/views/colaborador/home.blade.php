@extends('layouts.colaborador')
@section('conteudo')
    <header class="flex items-center justify-between">
        <div>
            <div class="text-xl font-black">O<span class="text-orange-500">r</span>avel</div>
            <p class="text-sm text-zinc-400">Olá, {{ auth()->user()->name }}</p>
        </div>
        <div class="flex items-center gap-2">
        <a href="{{ route('colab.notificacoes') }}" class="relative rounded-lg border border-zinc-700 px-3 py-2 text-base active:bg-zinc-800" aria-label="Notificações">🔔
            @if($naoLidas > 0)<span class="absolute -right-1 -top-1 min-w-[1.1rem] rounded-full bg-orange-500 px-1 text-center text-[10px] font-bold text-white">{{ $naoLidas }}</span>@endif
        </a>
        <form method="POST" action="{{ route('colab.logout') }}">
            @csrf
            <button type="submit" class="rounded-lg border border-zinc-700 px-3 py-2 text-xs font-bold text-zinc-300 active:bg-zinc-800">Sair</button>
        </form>
        </div>
    </header>

    @if(filled(config('services.webpush.public')))
        <div id="push-card" class="mt-4 hidden rounded-2xl border border-zinc-800 bg-zinc-900 p-4">
            <div class="text-sm font-bold">Receber avisos no celular</div>
            <p id="push-texto" class="mt-1 text-xs text-zinc-400">Ative para ser avisado de mensagens e ordens de serviço, mesmo com o app fechado.</p>
            <button id="push-botao" type="button" class="mt-3 min-h-[2.75rem] w-full rounded-xl bg-orange-500 text-sm font-bold text-white active:bg-orange-600">Ativar notificações</button>
        </div>
        <script>
        (function () {
            const card = document.getElementById('push-card'), botao = document.getElementById('push-botao'), texto = document.getElementById('push-texto');
            const chave = @json(config('services.webpush.public'));
            const csrf = document.querySelector('meta[name=csrf-token]').content;
            const ios = /iPhone|iPad|iPod/.test(navigator.userAgent), instalado = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone;
            if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
                if (ios && !instalado) { card.classList.remove('hidden'); botao.classList.add('hidden'); texto.textContent = 'No iPhone: toque em Compartilhar e em "Adicionar à Tela de Início", depois abra o app por lá para ativar os avisos.'; }
                return;
            }
            const b64 = (s) => Uint8Array.from(atob((s + '='.repeat((4 - s.length % 4) % 4)).replace(/-/g, '+').replace(/_/g, '/')), c => c.charCodeAt(0));
            const enviar = (url, corpo) => fetch(url, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify(corpo) });
            const ativo = (ok) => { botao.textContent = ok ? 'Desativar notificações' : 'Ativar notificações'; botao.className = 'mt-3 min-h-[2.75rem] w-full rounded-xl text-sm font-bold ' + (ok ? 'bg-zinc-800 text-zinc-200' : 'bg-orange-500 text-white'); texto.textContent = ok ? 'Notificações ativadas neste aparelho.' : 'Ative para ser avisado de mensagens e ordens de serviço, mesmo com o app fechado.'; };
            navigator.serviceWorker.register(@json(route('colab.sw', [], false))).then(async (reg) => {
                card.classList.remove('hidden');
                let sub = await reg.pushManager.getSubscription();
                ativo(!!sub && Notification.permission === 'granted');
                botao.addEventListener('click', async () => {
                    botao.disabled = true;
                    try {
                        sub = await reg.pushManager.getSubscription();
                        if (sub) { await enviar(@json(route('colab.push.cancelar', [], false)), { endpoint: sub.endpoint }); await sub.unsubscribe(); ativo(false); return; }
                        if (await Notification.requestPermission() !== 'granted') { texto.textContent = 'Permissão negada. Libere as notificações nas configurações do navegador.'; return; }
                        sub = await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: b64(chave) });
                        await enviar(@json(route('colab.push.assinar', [], false)), sub.toJSON());
                        ativo(true);
                    } catch (e) { texto.textContent = 'Não foi possível ativar agora. Tente de novo.'; } finally { botao.disabled = false; }
                });
            });
        })();
        </script>
    @endif

    @forelse($grupos as $grupo => $modulos)
        <section class="mt-6">
            <h2 class="mb-2 text-xs font-bold uppercase tracking-wide text-zinc-500">{{ $grupo }}</h2>
            <div class="space-y-3">
                @foreach($modulos as $m)
                    <a href="{{ route($m['rota']) }}" class="flex items-center gap-4 rounded-2xl bg-zinc-900 p-4 active:bg-zinc-800">
                        <span class="text-3xl">{{ $m['icone'] }}</span>
                        <span class="flex-1">
                            <span class="block text-base font-bold">{{ $m['titulo'] }}</span>
                            <span class="block text-xs text-zinc-400">{{ $m['descricao'] }}</span>
                        </span>
                        @php $selo = isset($m['selo']) ? ($m['selo'])(auth()->user()) : 0; @endphp
                        @if($selo > 0)<span class="min-w-[1.5rem] rounded-full bg-orange-500 px-2 py-0.5 text-center text-xs font-bold text-white">{{ $selo }}</span>@endif
                    </a>
                @endforeach
            </div>
        </section>
    @empty
        <p class="mt-10 rounded-xl bg-zinc-900 p-4 text-sm text-zinc-400">Nenhuma função disponível para o seu perfil. Fale com o administrador da sua empresa.</p>
    @endforelse
@endsection
