{{-- Manifesto do app instalavel conforme o perfil do usuario logado
     (Colaborador/Tecnico/Administrador) -- ver App\Support\AppProfile. --}}
{{-- O navegador busca o manifesto SEM cookie por padrão; como a rota dinâmica exige login, sem use-credentials ela redirecionava ao login e dava "Manifest: Syntax error". --}}
<link rel="manifest" href="{{ \App\Support\AppProfile::manifestUrl(auth()->user()) }}" @auth crossorigin="use-credentials" @endauth>
<link rel="apple-touch-icon" href="{{ asset('icon.png') }}">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
