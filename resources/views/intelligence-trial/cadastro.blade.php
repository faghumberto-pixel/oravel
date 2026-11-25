@extends('intelligence-trial.layout')
@section('titulo', 'Teste grátis - Oravel Intelligence')
@section('conteudo')
    <h1 class="text-2xl font-bold">Teste grátis por {{ config('intelligence_trial.dias') }} dias</h1>
    <p class="mt-2 text-sm text-white/70">Sem senha e sem cartão. Confirme seu e-mail, envie sua planilha e veja a primeira leitura.</p>

    @if ($errors->any())
        <div class="mt-4 rounded-lg bg-red-500/20 p-3 text-sm text-red-100">
            @foreach ($errors->all() as $erro)<p>{{ $erro }}</p>@endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('intelligence.trial.store') }}" class="mt-6 space-y-4">
        @csrf
        <input type="text" name="site" value="" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true">
        <label class="block text-sm">Seu nome
            <input name="nome" value="{{ old('nome') }}" required class="mt-1 w-full rounded-lg border-0 bg-white/10 px-3 py-2 text-white" autocomplete="name">
        </label>
        <label class="block text-sm">Empresa
            <input name="empresa" value="{{ old('empresa') }}" required class="mt-1 w-full rounded-lg border-0 bg-white/10 px-3 py-2 text-white" autocomplete="organization">
        </label>
        <label class="block text-sm">WhatsApp
            <input name="whatsapp" value="{{ old('whatsapp') }}" required inputmode="tel" placeholder="(19) 99999-9999" class="mt-1 w-full rounded-lg border-0 bg-white/10 px-3 py-2 text-white" autocomplete="tel">
        </label>
        <label class="block text-sm">E-mail
            <input name="email" type="email" value="{{ old('email') }}" required placeholder="voce@suaempresa.com.br" class="mt-1 w-full rounded-lg border-0 bg-white/10 px-3 py-2 text-white" autocomplete="email">
            <span class="mt-1 block text-xs text-white/60">Vamos enviar um link para você confirmar e entrar.</span>
        </label>
        @if (config('services.turnstile.site_key'))
            <div class="cf-turnstile" data-sitekey="{{ config('services.turnstile.site_key') }}" data-theme="dark"></div>
            <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
        @endif
        <label class="flex items-start gap-2 text-xs text-white/80">
            <input type="checkbox" name="aceite" value="1" required class="mt-0.5">
            <span>Li e aceito os <a class="underline" target="_blank" href="{{ config('intelligence_trial.termos_url') }}">Termos de Uso</a> e a <a class="underline" target="_blank" href="{{ config('intelligence_trial.privacidade_url') }}">Política de Privacidade</a> (LGPD).</span>
        </label>
        <button class="w-full rounded-lg bg-indigo-500 px-4 py-3 font-semibold hover:bg-indigo-400">Receber link de acesso</button>
    </form>
    <p class="mt-4 text-center text-xs text-white/60">Já tem teste? <a class="underline" href="{{ route('intelligence.trial.novo-link') }}">Receber meu link de acesso</a></p>
@endsection
