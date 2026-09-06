<x-guest-layout>
    <h1 class="text-lg font-semibold text-gray-900 mb-2">Entrar sem senha</h1>
    <p class="text-sm text-gray-600 mb-4">
        Informe o e-mail cadastrado no Portal do Cliente. Vamos enviar um link de acesso válido por 15 minutos.
    </p>

    @if (session('status'))
        <div class="mb-4 rounded-md bg-green-50 p-3 text-sm text-green-700">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-4 rounded-md bg-red-50 p-3 text-sm text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('cliente.magic-link.send') }}" class="space-y-4">
        @csrf

        <div>
            <label for="email" class="block text-sm font-medium text-gray-700">E-mail</label>
            <input
                id="email"
                type="email"
                name="email"
                value="{{ old('email') }}"
                required
                autofocus
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-orange-500 focus:ring-orange-500 sm:text-sm"
            >
        </div>

        <button
            type="submit"
            class="w-full rounded-md bg-orange-600 px-4 py-2 text-sm font-semibold text-white hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2"
        >
            Enviar link de acesso
        </button>
    </form>

    <div class="mt-4 text-center text-sm">
        <a href="{{ url('/cliente/login') }}" class="text-gray-600 hover:underline">
            Voltar para o login com senha
        </a>
    </div>
</x-guest-layout>
