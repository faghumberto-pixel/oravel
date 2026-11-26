@props(['app', 't', 'prefixo', 'turnstileKey' => null])
{{-- Formulário de cadastro do teste grátis. Usado no hero e no pop-up: mesmo destino, mesmos campos, mesma regra (e-mail confirmado + anti-robô). --}}
<form method="post" action="{{ $app }}/intelligence/teste-gratis" data-cadastro class="cadastro">
    <div class="campos">
        <label class="c" for="{{ $prefixo }}-nome">Seu nome<input id="{{ $prefixo }}-nome" type="text" name="nome" required autocomplete="name" maxlength="120"></label>
        <label class="c" for="{{ $prefixo }}-empresa">Empresa<input id="{{ $prefixo }}-empresa" type="text" name="empresa" required autocomplete="organization" maxlength="120"></label>
        <label class="c" for="{{ $prefixo }}-whats">WhatsApp<input id="{{ $prefixo }}-whats" type="tel" name="whatsapp" required inputmode="tel" autocomplete="tel" placeholder="(19) 99999-9999" maxlength="20"></label>
        <label class="c" for="{{ $prefixo }}-email">E-mail<input id="{{ $prefixo }}-email" type="email" name="email" required autocomplete="email" inputmode="email" placeholder="voce@suaempresa.com.br" maxlength="160"></label>
    </div>
    @if (! empty($turnstileKey))
        <div class="cf-turnstile" data-sitekey="{{ $turnstileKey }}" data-language="pt-br"></div>
    @endif
    <label class="aceite"><input type="checkbox" name="aceite" value="1" required><span>Li e aceito os <a href="/termos-de-uso/" target="_blank" rel="noopener">Termos de Uso</a> e a <a href="/politica-de-privacidade/" target="_blank" rel="noopener">Política de Privacidade</a> (LGPD).</span></label>
    <div class="hp" aria-hidden="true"><label>Não preencha<input type="text" name="site" tabindex="-1" autocomplete="off"></label></div>
    <button class="btn" type="submit">{{ $t['cta'] }}</button>
</form>
