{{-- Manifesto do app instalavel conforme o perfil do usuario logado
     (Colaborador/Tecnico/Administrador) -- ver App\Support\AppProfile. --}}
<link rel="manifest" href="{{ \App\Support\AppProfile::manifestUrl(auth()->user()) }}">
<link rel="apple-touch-icon" href="{{ asset('icon.png') }}">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
