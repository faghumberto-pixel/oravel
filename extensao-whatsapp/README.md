# Oravel CRM para WhatsApp Web (extensão Chrome)

Mostra, no WhatsApp Web, quem é o contato no CRM Oravel e deixa criar lead, registrar a conversa e mover a etapa com um clique.

## Instalar
1. No sistema: **Comercial → Gestão CRM → Integração / API** → gere um token (Consultar + Criar e registrar) e baixe a extensão (.zip).
2. Descompacte. No Chrome, abra `chrome://extensions`, ative **Modo do desenvolvedor** e clique em **Carregar sem compactação** escolhendo a pasta.
3. Abra as **Opções** da extensão, confirme o endereço (`https://app.oravel.com.br`), cole o token e clique em **Salvar e testar**.
4. Abra o WhatsApp Web, entre numa conversa e clique em **Oravel CRM** na lateral direita.

## O que a extensão faz e não faz
- **Lê** apenas o nome/telefone do chat aberto e, **somente quando você clica em “Ler mensagens visíveis na tela”**, o texto das mensagens que estão na tela. Você revisa e edita antes de salvar.
- **Grava** no CRM só quando você clica em Criar lead, Salvar no CRM ou Mover.
- **Não** envia mensagens, não clica nem digita no WhatsApp, não percorre conversas, não observa o chat em segundo plano, não captura áudio/imagens (o áudio só vai se você escolher o arquivo).
- Grupos não são suportados. Contatos salvos nem sempre expõem o telefone: nesse caso digite o número.
- O token fica só no `chrome.storage.local` deste navegador e é usado apenas pelo service worker; a página do WhatsApp nunca o vê. Revogue-o na página de Integração / API se perder o notebook.

## Aviso
O WhatsApp não autoriza automação do WhatsApp Web. Esta extensão é **assistida** (age só por clique do usuário) para reduzir esse risco, mas ele não é zero. Para registro automático use a integração oficial da Meta (WhatsApp da Empresa).

## Se parar de funcionar
O WhatsApp muda o layout de tempos em tempos. Se o painel disser “layout não reconhecido”, ajuste só o arquivo `selectors.js`.

## Desenvolvimento
`npm test` (Node 18+, sem dependências) roda os testes de `lib/` e as verificações de segurança do pacote. `./build.sh` testa e gera o .zip em `storage/app/extensao/`.
