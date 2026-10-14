@extends('treinamento.layout')

@section('titulo', $titulo)

@section('conteudo')
    <h1>{{ $titulo }}</h1>
    <p class="suave">Teste de 15 perguntas. Você tem <strong>uma tentativa</strong> por envio e a nota mínima é <strong>{{ number_format($notaMinima, 1, ',', '') }}</strong>. Pergunta em branco vale zero. O resultado aparece assim que você enviar.</p>

    @if ($temPdf)
        <p><a class="btn sec" href="{{ route('treinamento.pdf') }}">Baixar o material do treinamento (PDF)</a></p>
    @endif

    @if ($errors->any())
        <div class="erro" role="alert">
            @foreach ($errors->all() as $erro)
                <div>{{ $erro }}</div>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('treinamento.enviar') }}" autocomplete="off">
        @csrf

        <div class="card">
            <label for="nome">Nome completo</label>
            <input type="text" id="nome" name="nome" value="{{ old('nome') }}" maxlength="120" required>

            <label for="funcao">Função</label>
            <input type="text" id="funcao" name="funcao" value="{{ old('funcao') }}" maxlength="120" required>

            <label for="empresa">Empresa</label>
            <input type="text" id="empresa" name="empresa" value="{{ old('empresa') }}" maxlength="120" required>
        </div>

        {{-- Honeypot: invisível para pessoas; robôs costumam preencher. --}}
        <div class="oculto-robo" aria-hidden="true">
            <label for="website">Não preencha este campo</label>
            <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
        </div>

        <div class="card">
            @foreach ($perguntas as $pergunta)
                <div class="pergunta">
                    <h3>{{ $pergunta['n'] }}. {{ $pergunta['q'] }}</h3>
                    @foreach ($pergunta['alternativas'] as $posicao => $texto)
                        <label class="alt">
                            <input type="radio" name="r[{{ $pergunta['n'] }}]" value="{{ $posicao }}" @checked((string) old('r.'.$pergunta['n']) === (string) $posicao)>
                            <span>{{ $texto }}</span>
                        </label>
                    @endforeach
                </div>
            @endforeach
        </div>

        <button type="submit" class="btn">Enviar respostas</button>
    </form>
@endsection
