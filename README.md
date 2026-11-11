# oravel.org (blog)

Site estático feito com Eleventy + busca Pagefind. Sem autor; cada artigo tem data e hora.

## Comandos (terminal Ubuntu, dentro desta pasta)

    npm install                          # só na primeira vez
    npm run dev                          # ver o site no navegador (localhost:8080)
    npm run novo -- "Título" tema        # cria um artigo novo com data e hora de agora
    npm run publicar                     # monta o site e envia ao Firebase (oravel-org)

Temas: reforma-tributaria, tecnologia, la-fora, vendas, gestao-e-operacao, comunidade.

Os artigos ficam em `src/content/<tema>/AAAA-MM-DD-titulo.md`. Edite o texto e rode `npm run publicar`.
Páginas estáticas antigas (glossário, downloads etc.) podem ser copiadas para a pasta `estatico/`.
