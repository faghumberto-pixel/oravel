{{-- Botao "Instalar app" na tela de login do Oravel Mobile: o usuario instala
     direto do link, antes de entrar. Android/Chrome: prompt nativo capturado
     (beforeinstallprompt). iPhone/Safari: nao existe prompt, mostra o passo a passo.
     Some se o app ja esta instalado (display-mode: standalone). --}}
<div id="instalar-app" class="hidden mt-6 text-center">
    <button type="button" id="instalar-btn"
            class="hidden w-full border border-orange-600 text-orange-600 font-bold text-sm py-3 rounded-xl">
        Instalar app no celular
    </button>
    <p id="instalar-ios" class="hidden text-xs text-gray-500 leading-relaxed">
        Para instalar no iPhone: toque em <strong>Compartilhar</strong>
        (o quadrado com a seta) e depois em <strong>Adicionar à Tela de Início</strong>.
    </p>
</div>
<script>
    (function () {
        var box = document.getElementById('instalar-app');
        var btn = document.getElementById('instalar-btn');
        var ios = document.getElementById('instalar-ios');
        var standalone = (window.matchMedia && matchMedia('(display-mode: standalone)').matches) || navigator.standalone === true;
        if (standalone) return;

        var deferred = null;
        window.addEventListener('beforeinstallprompt', function (e) {
            e.preventDefault();
            deferred = e;
            box.classList.remove('hidden');
            btn.classList.remove('hidden');
        });
        btn.addEventListener('click', function () {
            if (!deferred) return;
            deferred.prompt();
            deferred.userChoice.finally(function () { deferred = null; box.classList.add('hidden'); });
        });
        window.addEventListener('appinstalled', function () { box.classList.add('hidden'); });

        if (/iphone|ipad|ipod/i.test(navigator.userAgent)) {
            box.classList.remove('hidden');
            ios.classList.remove('hidden');
        }
    })();
</script>
