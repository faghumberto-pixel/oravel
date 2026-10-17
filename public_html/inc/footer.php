<style data-site="footer-css">footer.ifoot[data-site="footer"] { display:block !important; background:#fff !important; border-top:1px solid #e4e7ec !important; padding:48px 0 !important; margin:0 !important; font-family:Inter,"Segoe UI",system-ui,sans-serif !important; color:#0f172a; } footer.ifoot .ft-wrap { max-width:1180px !important; margin:0 auto !important; padding:0 28px !important; box-sizing:border-box; } footer.ifoot .ft-grid { display:grid !important; grid-template-columns:1.3fr 1.2fr 1fr 1fr; gap:32px; margin-bottom:32px; } footer.ifoot .ft-col h4 { font-size:14px !important; font-weight:700 !important; margin:0 0 16px !important; color:#0f172a !important; } footer.ifoot .ft-col a { display:block !important; font-size:13px !important; color:#5b6472 !important; line-height:1.8 !important; text-decoration:none !important; transition:color .2s; } footer.ifoot .ft-col a:hover { color:#0f172a !important; } footer.ifoot .ft-brand { font-weight:800; font-size:20px; margin-bottom:12px; font-family:Manrope,sans-serif; } footer.ifoot .ft-brand i { color:#f97316; font-style:normal; } footer.ifoot .ft-col p { font-size:13px !important; color:#5b6472 !important; line-height:1.6 !important; margin:0 !important; } footer.ifoot .ft-bottom { border-top:1px solid #e4e7ec; padding-top:24px; display:flex; justify-content:space-between; flex-wrap:wrap; gap:6px 24px; font-size:12px; color:#8891a0; } footer.ifoot .ft-bottom p { margin:0 !important; font-size:12px !important; color:#8891a0 !important; } footer.ifoot .ft-legal { width:100%; margin-top:6px !important; font-size:11.5px !important; } @media (max-width:768px) { footer.ifoot .ft-grid { grid-template-columns:1fr; } }</style><footer class="ifoot" data-site="footer"><div class="ft-wrap"><div class="ft-grid"><div class="ft-col"><div class="ft-brand">O<i>r</i>avel</div><p>Plataforma de gestão com inteligência artificial para locação, manutenção, serviços, vendas (CRM), logística e estoque em todo o Brasil.</p></div><div class="ft-col"><h4>Soluções</h4><a href="/erp-cmms/">ERP Locadoras</a><a href="/software-manutencao-industrial/">Manutenção</a><a href="/software-gestao-de-servicos/">Serviços</a><a href="/software-gestao-frota-logistica/">Logística</a><a href="/wms/">Estoque</a><a href="/crm/">Vendas</a><a href="/departamento-pessoal/">Departamento Pessoal</a></div><div class="ft-col"><h4>Empresa</h4><a href="/planos/">Planos</a><a href="/inteligencia-artificial/">Inteligência artificial</a><a href="/academia/">Academia</a><a href="/sobre/">Sobre</a><a href="/contato.php">Contato</a></div><div class="ft-col"><h4>Legal</h4><a href="/politica-de-privacidade/">Privacidade</a><a href="/termos-de-uso/">Termos de Uso</a></div></div><div class="ft-bottom"><p>© 2026 Oravel Software. Todos os direitos reservados.</p><p>Feito por quem entende de operação em campo</p><p class="ft-legal">Oravel Desenvolvimento de Software Ltda · CNPJ 65.707.953/0001-30 · Rua Pais Leme, 215, conj. 1713, Pinheiros, São Paulo/SP · CEP 05424-150</p></div></div></footer>

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
<script async src="https://app.oravel.com.br/t.js?v=2"></script>
</body>
</html>
