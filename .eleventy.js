const { DateTime } = require('luxon');
const fs = require('fs');
const path = require('path');

module.exports = function(eleventyConfig) {
  // Passthrough copies
  eleventyConfig.addPassthroughCopy({ 'favicon.svg': 'favicon.svg' });
  eleventyConfig.addPassthroughCopy({ 'downloads': 'downloads' });
  eleventyConfig.addPassthroughCopy({ 'robots.txt': 'robots.txt' });

  // Collections: posts por tema
  const themes = [
    'reforma-tributaria',
    'tecnologia',
    'la-fora',
    'vendas',
    'gestao-e-operacao',
    'comunidade'
  ];

  themes.forEach(theme => {
    eleventyConfig.addCollection(theme, function(collectionApi) {
      return collectionApi.getFilteredByGlob(`src/content/${theme}/*.md`)
        .filter(item => !item.data.draft)
        .sort((a, b) => b.date - a.date);
    });
  });

  // Collection: todos os posts (para home e RSS)
  eleventyConfig.addCollection('posts', function(collectionApi) {
    return collectionApi.getFilteredByGlob('src/content/**/*.md')
      .filter(item => !item.data.draft && item.data.theme)
      .sort((a, b) => b.date - a.date);
  });

  // Collection: páginas institucionais
  eleventyConfig.addCollection('pages', function(collectionApi) {
    return collectionApi.getFilteredByGlob('src/content/pages/*.md');
  });

  // Filtro para obter posts de um tema pelo nome (para usar nos layouts)
  eleventyConfig.addFilter('getThemePosts', (collections, themeName) => {
    return collections[themeName] || [];
  });

  // Filtro para agrupar posts por ano/mês (para sidebar de filtros)
  eleventyConfig.addFilter('groupByDate', (posts, granularity = 'yearMonth') => {
    const groups = {};
    posts.forEach(post => {
      const dt = DateTime.fromJSDate(post.date, { zone: 'America/Sao_Paulo' });
      let key;
      if (granularity === 'yearMonth') {
        key = dt.toFormat('yyyy-MM'); // ex: 2026-11
      } else if (granularity === 'year') {
        key = dt.toFormat('yyyy');
      }
      if (!groups[key]) groups[key] = [];
      groups[key].push(post);
    });
    // Retorna array ordenado por data decrescente
    return Object.entries(groups)
      .sort(([a], [b]) => b.localeCompare(a))
      .map(([key, items]) => ({ key, items }));
  });

  // Filtro para formatar mês/ano para exibição
  eleventyConfig.addFilter('formatMonth', (key) => {
    if (!key) return '';
    const dt = DateTime.fromFormat(key, 'yyyy-MM', { zone: 'America/Sao_Paulo' });
    const months = [
      'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
      'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'
    ];
    return `${months[dt.month - 1]} ${dt.year}`;
  });

  // Filtros existentes
  eleventyConfig.addFilter('dateBr', (dateObj) => {
    if (!dateObj) return '';
    const dt = DateTime.fromJSDate(dateObj, { zone: 'America/Sao_Paulo' });
    return dt.toLocaleString(DateTime.DATETIME_SHORT_WITH_SECONDS).replace(',', ' às');
  });

  eleventyConfig.addFilter('dateISO', (dateObj) => {
    if (!dateObj) return '';
    return DateTime.fromJSDate(dateObj, { zone: 'America/Sao_Paulo' }).toISO();
  });

  eleventyConfig.addFilter('dateRSS', (dateObj) => {
    if (!dateObj) return '';
    return DateTime.fromJSDate(dateObj, { zone: 'America/Sao_Paulo' }).toFormat('EEE, dd MMM yyyy HH:mm:ss ZZZ');
  });

  eleventyConfig.addFilter('limit', (arr, n) => arr.slice(0, n));

  eleventyConfig.addFilter('themeLabel', (theme) => {
    const labels = {
      'reforma-tributaria': 'Reforma tributária',
      'tecnologia': 'Tecnologia',
      'la-fora': 'Lá fora',
      'vendas': 'Vendas',
      'gestao-e-operacao': 'Gestão e operação',
      'comunidade': 'Comunidade'
    };
    return labels[theme] || theme;
  });

  eleventyConfig.addFilter('themeColor', (theme) => {
    const colors = {
      'reforma-tributaria': '#9a3412',
      'tecnologia': '#0b5f57',
      'la-fora': '#0b5f57',
      'vendas': '#0b5f57',
      'gestao-e-operacao': '#0b5f57',
      'comunidade': '#0b5f57'
    };
    return colors[theme] || '#0b5f57';
  });

  eleventyConfig.addFilter('themeBg', (theme) => {
    const bgs = {
      'reforma-tributaria': '#fef3e7',
      'tecnologia': '#e7f1ef',
      'la-fora': '#e7f1ef',
      'vendas': '#e7f1ef',
      'gestao-e-operacao': '#e7f1ef',
      'comunidade': '#e7f1ef'
    };
    return bgs[theme] || '#e7f1ef';
  });

  // Shortcodes
  eleventyConfig.addShortcode('year', () => new Date().getFullYear());

  // Configuração de saída
  return {
    dir: {
      input: 'src',
      output: '_site',
      includes: '_includes',
      layouts: '_layouts',
      data: '_data'
    },
    templateFormats: ['md', 'njk', 'html', '11ty.js'],
    markdownTemplateEngine: 'njk',
    htmlTemplateEngine: 'njk',
    dataTemplateEngine: 'njk'
  };
};