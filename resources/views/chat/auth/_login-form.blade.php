    <div class="min-h-screen flex flex-col items-center justify-center px-6">
        <div class="w-full max-w-sm">
            <div class="flex flex-col items-center mb-8">
                <div class="w-14 h-14 rounded-2xl bg-orange-600 flex items-center justify-center text-white font-black text-xl mb-4">
                    OR
                </div>
                <h1 class="text-xl font-bold text-white">Oravel Chat</h1>
                <p class="text-sm text-zinc-400 mt-1">Entre para conversar com sua equipe</p>
            </div>

            @if (session('status'))
                <div class="mb-4 text-sm text-emerald-400 bg-emerald-950/40 border border-emerald-800 rounded-lg px-3 py-2">
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('chat.login') }}" class="space-y-4">
                @csrf

                <div>
                    <label for="email" class="block text-xs font-semibold text-zinc-400 mb-1.5">E-mail</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                           autocomplete="username"
                           class="w-full bg-zinc-900 text-white placeholder-zinc-500 text-sm px-3.5 py-3 rounded-xl border border-zinc-700 focus:ring-2 focus:ring-orange-500 focus:border-orange-500 outline-none transition">
                    @error('email')
                        <p class="mt-1.5 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="block text-xs font-semibold text-zinc-400 mb-1.5">Senha</label>
                    <input id="password" type="password" name="password" required
                           autocomplete="current-password"
                           class="w-full bg-zinc-900 text-white placeholder-zinc-500 text-sm px-3.5 py-3 rounded-xl border border-zinc-700 focus:ring-2 focus:ring-orange-500 focus:border-orange-500 outline-none transition">
                    @error('password')
                        <p class="mt-1.5 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit"
                        class="w-full bg-orange-600 hover:bg-orange-700 text-white font-bold text-sm py-3 rounded-xl transition">
                    Entrar
                </button>

                <p class="text-center text-[11px] text-zinc-500 pt-2">
                    Você continuará conectado neste aparelho até sair manualmente.
                </p>
            </form>
        </div>
    </div>
