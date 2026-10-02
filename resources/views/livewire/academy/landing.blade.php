<div>
    <section class="ac-hero" style="padding:44px 38px">
        <div style="max-width:720px">
            <div class="ac-crumb" style="font-size:13px;letter-spacing:.12em;text-transform:uppercase;opacity:.85">Oravel Academy</div>
            <h1 style="font-size:36px">Aprenda a usar a Oravel, no seu ritmo.</h1>
            <p style="font-size:17px;margin-top:10px">Cursos curtos e práticos sobre cada módulo do sistema, com provas, pontos e certificado de conclusão.</p>
            <div style="margin-top:24px;display:flex;gap:12px;flex-wrap:wrap">
                <a class="ac-btn alt" style="font-size:16px;padding:13px 26px" href="{{ url('/academia/inicio') }}">Entrar para estudar →</a>
                <a class="ac-btn" style="font-size:16px;padding:13px 22px;background:rgba(255,255,255,.16)" href="#cursos">Ver os cursos</a>
            </div>
        </div>
    </section>

    <div class="ac-cards" style="margin-bottom:28px">
        <div class="ac-card"><div style="font-size:30px">🧭</div><h3 style="margin:6px 0">Estude do seu jeito</h3><p style="margin:0;color:var(--muted);font-size:14px;line-height:1.5">Todos os módulos ficam no menu lateral. Escolha o que precisa, na hora que quiser — sem ordem obrigatória.</p></div>
        <div class="ac-card"><div style="font-size:30px">📝</div><h3 style="margin:6px 0">Prova com gabarito na hora</h3><p style="margin:0;color:var(--muted);font-size:14px;line-height:1.5">Responda, entregue e veja o gabarito comentado. Questão em branco vale zero. Cada acerto vale pontos.</p></div>
        <div class="ac-card"><div style="font-size:30px">🎓</div><h3 style="margin:6px 0">Certificado de conclusão</h3><p style="margin:0;color:var(--muted);font-size:14px;line-height:1.5">Conclua o curso, atinja a nota mínima e baixe o seu certificado, com código para conferir a autenticidade.</p></div>
    </div>

    <h2 class="ac-h2" id="cursos" style="font-size:20px">Cursos disponíveis</h2>
    <div class="ac-cards" style="margin-bottom:30px" data-testid="landing-courses">
        @foreach ($courses as $c)
            <div class="ac-card" wire:key="lc-{{ $c->id }}">
                <h3 style="margin:0 0 6px;font-size:16px">{{ $c->title }}</h3>
                @if ($c->description) <p style="margin:0;color:var(--muted);font-size:13.5px;line-height:1.5">{{ $c->description }}</p> @endif
            </div>
        @endforeach
    </div>
    <p style="color:var(--muted);font-size:13px;margin-top:-14px;margin-bottom:26px">Os cursos que aparecem para você ao entrar dependem do que está incluído no seu contrato.</p>

    <div class="ac-card" style="max-width:560px">
        <h2>🔎 Verificar um certificado</h2>
        <p style="margin:0 0 12px;color:var(--muted);font-size:14px">Tem um certificado e quer conferir se é autêntico? Digite o código que aparece nele.</p>
        <form wire:submit="verify" class="ac-inputs" style="margin:0">
            <input type="text" wire:model="code" placeholder="OA-XXXX-XXXX" style="flex:1;min-width:180px;text-transform:uppercase" data-testid="code">
            <button type="submit" class="ac-btn">Verificar</button>
        </form>
        @if ($error) <p class="ac-bad" style="margin:8px 0 0">{{ $error }}</p> @endif
    </div>
</div>
