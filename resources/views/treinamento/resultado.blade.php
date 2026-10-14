@extends('treinamento.layout')

@section('titulo', 'Resultado — '.$titulo)

@section('conteudo')
    <h1>Resultado</h1>
    <p class="suave">{{ $resultado->nome }} · {{ $resultado->funcao }}</p>

    <div class="card">
        <p class="nota">{{ number_format((float) $resultado->nota, 1, ',', '') }}</p>
        <p>
            <span class="selo {{ $resultado->aprovado ? 'ok' : 'nao' }}">{{ $resultado->aprovado ? 'Aprovado' : 'Precisa refazer' }}</span>
            &nbsp; {{ $resultado->acertos }} de {{ $resultado->total }} acertos · nota mínima {{ number_format($notaMinima, 1, ',', '') }}
        </p>
        @unless ($resultado->aprovado)
            <p class="aviso">Revise o material do treinamento e responda de novo quando quiser.</p>
        @endunless
    </div>

    <h2>Correção</h2>
    @foreach ($revisao as $item)
        <div class="rev {{ $item['certa'] ? 'certa' : 'errada' }}">
            <p><strong>{{ $item['n'] }}. {{ $item['q'] }}</strong></p>
            @if ($item['certa'])
                <p>✔ Você acertou: {{ $item['escolhida'] }}</p>
            @else
                <p>✘ Sua resposta: {{ $item['escolhida'] ?? '(em branco)' }}</p>
                <p>✔ Resposta certa: <strong>{{ $item['resposta_certa'] }}</strong></p>
                @if ($item['why'])
                    <p class="suave">{{ $item['why'] }}</p>
                @endif
            @endif
        </div>
    @endforeach
@endsection
