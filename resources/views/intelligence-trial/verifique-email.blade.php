@extends('intelligence-trial.layout')
@section('titulo', 'Confirme seu e-mail - Oravel Intelligence')
@section('conteudo')
    <h1 class="text-2xl font-bold">Confira seu e-mail</h1>
    <p class="mt-3 text-sm text-white/80">
        @if (session('email_enviado_para'))
            Se o endereço <strong>{{ session('email_enviado_para') }}</strong> estiver correto, enviamos um link para você confirmar o e-mail.
        @else
            Se o endereço estiver correto, enviamos um link para você confirmar o e-mail.
        @endif
    </p>
    <ul class="mt-4 list-disc space-y-1 pl-5 text-sm text-white/70">
        <li>O link vale por {{ config('intelligence_trial.confirmacao_horas') }} horas.</li>
        <li>Não chegou? Olhe a caixa de spam e espere alguns minutos.</li>
        <li>Digitou o e-mail errado? <a class="underline" href="{{ route('intelligence.trial.create') }}">Faça o cadastro de novo</a>.</li>
    </ul>
@endsection
