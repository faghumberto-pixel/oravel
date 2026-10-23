@extends('layouts.colaborador')
@section('conteudo')
    <header class="-mx-5 -mt-5 mb-2 flex items-center justify-between rounded-b-3xl bg-gradient-to-r from-blue-600 to-blue-500 px-5 pb-7 pt-6 text-white">
        <div>
            <div class="text-xl font-black">O<span class="text-orange-300">r</span>avel</div>
            <p class="text-sm text-blue-100">Olá, {{ auth()->user()->name }}</p>
        </div>
        <div class="flex items-center gap-2">
        <a href="{{ route('colab.notificacoes') }}" class="relative rounded-lg border border-white/40 bg-white/10 px-3 py-2 text-base active:bg-white/20" aria-label="Notificações">🔔
            @if($naoLidas > 0)<span class="absolute -right-1 -top-1 min-w-[1.1rem] rounded-full bg-orange-500 px-1 text-center text-[10px] font-bold text-white">{{ $naoLidas }}</span>@endif
        </a>
        <form method="POST" action="{{ route('colab.logout') }}" onsubmit="event.preventDefault(); const f = this; Promise.resolve(window.caches ? caches.keys().then((n) => Promise.all(n.filter((x) => x.startsWith('oravel-colab-paginas-')).map((x) => caches.delete(x)))) : null).catch(() => {}).finally(() => f.submit());">
            @csrf
            <button type="submit" class="rounded-lg border border-white/40 bg-white/10 px-3 py-2 text-xs font-bold text-white active:bg-white/20">Sair</button>
        </form>
        </div>
    </header>

    @if(filled(config('services.webpush.public')))
        <div id="push-card" class="mt-4 hidden rounded-xl border border-zinc-200 bg-white p-4">
            <div class="text-sm font-bold">Receber avisos no celular</div>
            <p id="push-texto" class="mt-1 text-xs text-zinc-600">Ative para ser avisado de mensagens e ordens de serviço, mesmo com o app fechado.</p>
            <button id="push-botao" type="button" class="mt-3 min-h-[2.75rem] w-full rounded-xl bg-blue-600 text-sm font-bold text-white active:bg-blue-600">Ativar notificações</button>
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
            const ativo = (ok) => { botao.textContent = ok ? 'Desativar notificações' : 'Ativar notificações'; botao.className = 'mt-3 min-h-[2.75rem] w-full rounded-xl text-sm font-bold ' + (ok ? 'bg-zinc-100 text-zinc-800' : 'bg-blue-600 text-white'); texto.textContent = ok ? 'Notificações ativadas neste aparelho.' : 'Ative para ser avisado de mensagens e ordens de serviço, mesmo com o app fechado.'; };
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
            <h2 class="mb-2 text-xs font-bold uppercase tracking-wide text-zinc-600">{{ $grupo }}</h2>
            <div class="space-y-3">
                @foreach($modulos as $m)
                    <a href="{{ route($m['rota']) }}" class="flex items-center gap-4 rounded-xl bg-white p-4 active:bg-zinc-100 border border-zinc-200 shadow-sm">
                        <span class="text-3xl">{{ $m['icone'] }}</span>
                        <span class="flex-1">
                            <span class="block text-base font-bold">{{ $m['titulo'] }}</span>
                            <span class="block text-xs text-zinc-600">{{ $m['descricao'] }}</span>
                        </span>
                        @php $selo = isset($m['selo']) ? ($m['selo'])(auth()->user()) : 0; @endphp
                        @if($selo > 0)<span class="min-w-[1.5rem] rounded-full bg-blue-600 px-2 py-0.5 text-center text-xs font-bold text-white">{{ $selo }}</span>@endif
                    </a>
                @endforeach
            </div>
        </section>
    @empty
        <p class="mt-10 rounded-xl bg-white p-4 text-sm text-zinc-600 border border-zinc-200 shadow-sm">Nenhuma função disponível para o seu perfil. Fale com o administrador da sua empresa.</p>
    @endforelse

    @php
        $telasParaGuardar = collect($grupos)->flatten(1)->map(fn ($m) => route($m['rota'], [], false))
            ->push(route('colab.notificacoes', [], false))->push(route('colab.offline', [], false))
            ->when(collect($grupos)->flatten(1)->contains('chave', 'ausencias'), fn ($c) => $c->push(route('colab.ausencias.nova', [], false)))
            ->when(collect($grupos)->flatten(1)->contains('chave', 'logistica'), fn ($c) => $c->push(route('colab.logistica.vencimentos', [], false))->push(route('colab.logistica.km-rodado', [], false)))
            ->values()->all();
    @endphp
    <script>
        // Deixa as telas do app guardadas no aparelho para abrirem sem internet (o service worker faz o cache).
        window.addEventListener('load', () => {
            if (!navigator.onLine || !('serviceWorker' in navigator)) { return; }
            const telas = @json($telasParaGuardar);
            navigator.serviceWorker.ready.then(() => setTimeout(() => telas.forEach((u) => fetch(u, { credentials: 'same-origin', headers: { 'Accept': 'text/html' } }).catch(() => {})), 1500));
        });
    </script>
@endsection
