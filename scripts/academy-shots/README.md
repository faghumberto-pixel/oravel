Ferramentas para gerar as telas reais da Academia (não rodam em produção).

1. Crie uma base descartável (`createdb oravel_demo`), clone/semeie dados de demonstração e confirme com
   `DB_DATABASE=oravel_demo php artisan config:show database.connections.pgsql.database`.
2. Suba o servidor com `DB_DATABASE=oravel_demo MAIL_MAILER=log QUEUE_CONNECTION=sync php artisan serve --port=8099`.
3. Ajuste login/ids em `lib.js` e rode, por exemplo, `OUT=public/academy/img node cat-sup.js`
   (precisa de `playwright-core` e do Chromium).
- `snap.js` desenha destaques numerados (`hl`), esconde o menu lateral, troca "tenant" por "empresa" na imagem.
- `A.py`/`sup.py` geram o HTML das aulas (fluxos, etiquetas, quadros, figuras com legenda).
