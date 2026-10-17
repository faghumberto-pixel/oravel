// TODOS os seletores do WhatsApp Web ficam aqui. Quando o layout deles mudar e a extensão disser
// "layout não reconhecido", é este arquivo que se ajusta (e só ele).
(function (root) {
  root.OravelSelectors = {
    chat: '#main',                              // painel da conversa aberta
    titulo: '#main header span[title]',         // nome (ou telefone) no cabeçalho
    linhas: '#main [data-id]',                  // cada mensagem tem data-id "false_<n>@c.us_<id>"
    prefixo: '[data-pre-plain-text]',           // "[14:32, 16/10/2026] Maria: "
    texto: 'span.selectable-text',              // texto da mensagem
  };
})(self);
