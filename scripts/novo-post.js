#!/usr/bin/env node
/**
 * Script para criar novo post: npm run novo -- "Título do post" tema
 * Cria arquivo Markdown em content/<tema>/ com data/hora atual (America/Sao_Paulo)
 * Front matter: title, date, theme, summary, source (opcional), readingTime
 */

const fs = require('fs');
const path = require('path');
const { DateTime } = require('luxon');

const args = process.argv.slice(2);

if (args.length < 2) {
  console.error('Uso: npm run novo -- "Título do post" tema');
  console.error('Temas: reforma-tributaria, tecnologia, la-fora, vendas, gestao-e-operacao, comunidade');
  process.exit(1);
}

const title = args[0];
const theme = args[1];

const validThemes = [
  'reforma-tributaria',
  'tecnologia',
  'la-fora',
  'vendas',
  'gestao-e-operacao',
  'comunidade'
];

if (!validThemes.includes(theme)) {
  console.error(`Tema inválido: ${theme}`);
  console.error('Temas válidos:', validThemes.join(', '));
  process.exit(1);
}

// Data/hora atual no fuso America/Sao_Paulo
const now = DateTime.now().setZone('America/Sao_Paulo');
const dateISO = now.toISO(); // ex: 2026-11-10T22:46:00-03:00
const dateForFilename = dateISO.replace(/:/g, '-'); // para nome de arquivo

// Slug do título
const slug = title
  .toLowerCase()
  .normalize('NFD').replace(/[̀-ͯ]/g, '') // remove acentos
  .replace(/[^a-z0-9]+/g, '-')
  .replace(/(^-|-$)/g, '');

const filename = `${dateForFilename}-${slug}.md`;
const filepath = path.join('content', theme, filename);

const frontMatter = `---
title: "${title}"
date: ${dateISO}
theme: "${theme}"
summary: ""
source: ""
readingTime: ""
---

`;

try {
  fs.writeFileSync(filepath, frontMatter, 'utf8');
  console.log(`✓ Post criado: ${filepath}`);
  console.log(`  Edite o arquivo e preencha summary, source e readingTime.`);
} catch (err) {
  console.error('Erro ao criar arquivo:', err.message);
  process.exit(1);
}