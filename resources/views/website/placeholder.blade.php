@extends('layouts.website')
@section('title', '{{ $title }} - Oravel')
@section('content')

<section class="py-20 md:py-28 bg-white">
    <div class="wrap">
        <h1 class="text-5xl font-bold mb-8">{{ $title }}</h1>
        <p class="text-xl text-ink-soft max-w-3xl mb-8">Em breve</p>
        <a href="/" class="text-accent font-semibold hover:text-orange-600">← Voltar para home</a>
    </div>
</section>

@endsection
