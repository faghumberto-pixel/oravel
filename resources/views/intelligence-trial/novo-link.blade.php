@extends('intelligence-trial.layout')
@section('titulo', 'Meu link de acesso - Oravel Intelligence')
@section('conteudo')
    <h1 class="text-2xl font-bold">Meu link de acesso</h1>
    <p class="mt-2 text-sm text-white/70">Informe o WhatsApp usado no cadastro.</p>

    @if ($errors->any())
        <div class="mt-4 rounded-lg bg-red-500/20 p-3 text-sm text-red-100">@foreach ($errors->all() as $erro)<p>{{ $erro }}</p>@endforeach</div>
    @endif

    @if (session('link'))
        <div class="mt-4 rounded-lg bg-emerald-500/20 p-3 text-sm">
            <p>Seu link pessoal. Guarde-o e não compartilhe:</p>
            <a class="mt-2 block break-all underline" href="{{ session('link') }}">{{ session('link') }}</a>
        </div>
    @elseif (session('enviado'))
        <div class="mt-4 rounded-lg bg-emerald-500/20 p-3 text-sm">Se esse número tem uma conta, enviamos o link de acesso.</div>
    @endif

    <form method="POST" action="{{ route('intelligence.trial.novo-link.enviar') }}" class="mt-6 space-y-4">
        @csrf
        <input type="text" name="site" value="" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true">
        <label class="block text-sm">WhatsApp
            <input name="whatsapp" value="{{ old('whatsapp') }}" required inputmode="tel" placeholder="(19) 99999-9999" class="mt-1 w-full rounded-lg border-0 bg-white/10 px-3 py-2 text-white">
        </label>
        <button class="w-full rounded-lg bg-indigo-500 px-4 py-3 font-semibold hover:bg-indigo-400">Receber link</button>
    </form>
@endsection
