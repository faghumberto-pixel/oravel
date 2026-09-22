<footer>
  <div class="foot-inner">
    <div class="foot-top">
      <div class="foot-brand">
        <a class="logo" href="/index.php#inicio">O<span>r</span>avel</a>
        <p><?= $slogan ?>. Do pátio ao contrato fechado, numa plataforma só.</p>
      </div>
      <div class="foot-col">
        <h5>Soluções</h5>
        <ul>
          <li><a href="/solucoes.php">Gestão de Ativos</a></li>
          <li><a href="/solucoes.php">Manutenção Preventiva</a></li>
          <li><a href="/solucoes.php">Comercial e Locação</a></li>
          <li><a href="/index.php#diferencial">Diferenciais</a></li>
        </ul>
      </div>
      <div class="foot-col">
        <h5>Institucional</h5>
        <ul>
          <li><a href="/index.php#faq">Perguntas Frequentes</a></li>
          <li><a href="/contato.php">Contato</a></li>
        </ul>
      </div>
      <div class="foot-col">
        <h5>Contato</h5>
        <ul>
          <li><a href="/contato.php">Agendar Demo</a></li>
          <li><a href="mailto:<?= $contact_mail ?>?subject=Suporte">Suporte</a></li>
          <li><a href="mailto:<?= $contact_mail ?>"><?= $contact_mail ?></a></li>
        </ul>
      </div>
    </div>
    <div class="foot-bottom">
      <p>Copyright &copy; <?= date('Y') ?> <?= $site_name ?>. Todos os direitos reservados.</p>
      <div class="foot-socials">
        <a href="#" title="LinkedIn">in</a>
        <a href="#" title="Instagram">ig</a>
      </div>
    </div>
  </div>
</footer>

<script>
const io = new IntersectionObserver(entries => {
  entries.forEach(e => {
    if (e.isIntersecting) {
      e.target.style.opacity = '1';
      e.target.style.transform = 'translateY(0)';
    }
  });
}, { threshold: 0.1 });

document.querySelectorAll('.anim').forEach(el => {
  el.style.opacity = '0';
  el.style.transform = 'translateY(24px)';
  el.style.transition = 'opacity .5s ease, transform .5s ease';
  io.observe(el);
});
</script>

<?php include __DIR__ . '/whatsapp-chat.php'; ?>
</body>
</html>
