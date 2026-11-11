// Uso: npm run novo -- "Título do artigo" tema
// Cria content/<tema>/AAAA-MM-DD-titulo.md com data e hora de agora (horário de Brasília).
import fs from "node:fs";
import path from "node:path";

const temas = ["reforma-tributaria", "tecnologia", "la-fora", "vendas", "gestao-e-operacao", "comunidade"];
const [titulo, tema] = process.argv.slice(2);
if (!titulo || !temas.includes(tema)) {
  console.error('Uso: npm run novo -- "Título do artigo" tema\nTemas: ' + temas.join(", "));
  process.exit(1);
}
const agora = new Date();
const parts = Object.fromEntries(new Intl.DateTimeFormat("sv-SE", {
  timeZone: "America/Sao_Paulo", year: "numeric", month: "2-digit", day: "2-digit",
  hour: "2-digit", minute: "2-digit", second: "2-digit", hourCycle: "h23",
}).formatToParts(agora).map((p) => [p.type, p.value]));
const dia = `${parts.year}-${parts.month}-${parts.day}`;
const iso = `${dia}T${parts.hour}:${parts.minute}:${parts.second}-03:00`;
const slug = titulo.normalize("NFD").replace(/[̀-ͯ]/g, "").toLowerCase()
  .replace(/[^a-z0-9]+/g, "-").replace(/^-+|-+$/g, "");
const dir = path.join("src", "content", tema);
fs.mkdirSync(dir, { recursive: true });
const arq = path.join(dir, `${dia}-${slug}.md`);
if (fs.existsSync(arq)) { console.error("Já existe: " + arq); process.exit(1); }
fs.writeFileSync(arq, `---
title: "${titulo.replace(/"/g, '\\"')}"
date: ${iso}
theme: ${tema}
summary: "Escreva aqui uma frase de resumo."
sources:
  - title: "Nome da fonte"
    url: "https://"
---

Escreva o texto aqui em Markdown.
`);
console.log("Criado: " + arq);
