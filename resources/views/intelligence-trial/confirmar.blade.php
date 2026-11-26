@extends('intelligence-trial.layout')
@section('titulo', 'Confirmar e-mail - Oravel Intelligence')
@section('conteudo')
    <h1 class="text-2xl font-bold">Confirmar e-mail</h1>
    <p class="mt-3 text-sm text-white/80">Clique no botão para confirmar seu e-mail. Depois você entra com o e-mail e a senha que criou.</p>
    <form method="POST" action="{{ route('intelligence.trial.confirmar.enviar', $token) }}" class="mt-6">
        @csrf
        <button class="w-full rounded-lg bg-indigo-500 px-4 py-3 font-semibold hover:bg-indigo-400">Confirmar e-mail</button>
    </form>
@endsection
