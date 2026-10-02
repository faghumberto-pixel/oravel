# Academia Oravel

Página própria em `/academia` (entrada pública; estudo exige cadastro no app + módulo Academia no contrato).
Super admin (SUPER_ADMINS) vê todos os cursos, inclusive rascunhos, sem filtro de contrato.

## Conteúdo (versionado em `database/data/academy/`)
- `courses.json` – cursos, aulas, resumo e módulo do contrato de cada aula
- `lessons/*.html` – corpo de cada aula (telas reais com destaques + mockups + texto limpo)
- `quiz.json` – perguntas das provas
- `public/academy/img/*.jpg` – telas reais usadas nas aulas

`php artisan academy:import [--refresh-content] [--publish-only="Curso A,Curso B"]` lê esses arquivos (igual em DEV e PROD).
Nunca sobrescreve texto editado na Central, exceto com `--refresh-content`.

## Regerar o conteúdo (quem mantém a Academia)
1. Espelhar o site `academy.oravel.com.br` numa pasta (páginas + `assets/js/nav-data.js`).
2. Escrever/ajustar os "visuais" de cada aula (ver `scripts/academy-shots/sup.py` como modelo).
3. `php artisan academy:build-content --site=<pasta> --visuals=<pasta-dos-visuais>`
   – tira links, títulos, assuntos internos (tenant, painel central…) e insere os visuais.
4. Telas reais: ver `scripts/academy-shots/` (Playwright + base descartável com dados de demonstração).
   Use SEMPRE uma cópia descartável do banco (nunca `oravel_db`) e `MAIL_MAILER=log`.

## Regras
- Prova entregue de uma vez; questão em branco vale zero; não refaz. Nota mínima do certificado: `oravel.academy.passing_grade` (7,0).
- Pontos: leitura 10, acerto 10, 1/min de estudo (teto 10 por aula), bônus de curso 50 (ver `config/oravel.php`).
