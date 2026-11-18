const { DateTime } = require('luxon');
const fs = require('fs');
const path = require('path');

module.exports = function(eleventyConfig) {
  // Passthrough copies
  eleventyConfig.addPassthroughCopy({ 'favicon.svg': 'favicon.svg' });
  eleventyConfig.addPassthroughCopy({ 'downloads': 'downloads' });
  eleventyConfig.addPassthroughCopy({ 'robots.txt': 'robots.txt' });
  eleventyConfig.addPassthroughCopy({ 'src/assets': 'assets' });

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
        .filter(item => !item.data.draft && item.date <= new Date())
        .sort((a, b) => b.date - a.date);
    });
  });

  // Collection: todos os posts (para home e RSS)
  eleventyConfig.addCollection('posts', function(collectionApi) {
    return collectionApi.getFilteredByGlob('src/content/**/*.md')
      .filter(item => !item.data.draft && item.data.theme && item.date <= new Date())
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
    return dt.setLocale('pt-BR').toFormat("d 'de' LLLL 'de' yyyy");
  });

  eleventyConfig.addFilter('dateISO', (dateObj) => {
    if (!dateObj) return '';
    return DateTime.fromJSDate(dateObj, { zone: 'America/Sao_Paulo' }).toISO();
  });

  eleventyConfig.addFilter('dateRSS', (dateObj) => {
    if (!dateObj) return '';
    return DateTime.fromJSDate(dateObj, { zone: 'America/Sao_Paulo' }).toFormat('EEE, dd MMM yyyy HH:mm:ss ZZZ');
  });

  // Remove itens com data no futuro (usado no sitemap, que so lista o que ja foi publicado)
  eleventyConfig.addFilter('jaPublicados', (posts) => posts.filter(p => p.date <= new Date()));

  eleventyConfig.addFilter('limit', (arr, n) => arr.slice(0, n));

  // Ranking da home: junta posts com dados de src/_data/ranking.json.
  // campo: 'leituras' ou 'nota'. Para 'nota', exige um minimo de votos.
  eleventyConfig.addFilter('topPorRanking', (posts, ranking, campo, n = 5) => {
    const itens = (ranking && ranking.itens) || {};
    const minVotos = (ranking && ranking.notaMinimaVotos) || 0;
    return posts
      .map(post => ({ post, r: itens[post.fileSlug] }))
      .filter(x => x.r && (campo !== 'nota' || x.r.votos >= minVotos))
      .sort((a, b) => b.r[campo] - a.r[campo] || b.r.votos - a.r.votos)
      .slice(0, n);
  });
  eleventyConfig.addFilter('numBr', (n) => Number(n).toLocaleString('pt-BR'));
  eleventyConfig.addFilter('notaBr', (n) => Number(n).toFixed(1).replace('.', ','));

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

  // JSON-LD (dados estruturados) para SEO
  const ld = (obj) => '<script type="application/ld+json">' + JSON.stringify(obj).replace(/</g, '\\u003c') + '</script>';
  const THEME_LABELS = { 'reforma-tributaria': 'Reforma tributária', 'tecnologia': 'Tecnologia', 'la-fora': 'Lá fora', 'vendas': 'Vendas', 'gestao-e-operacao': 'Gestão e operação', 'comunidade': 'Comunidade' };
  eleventyConfig.addShortcode('ldHome', () => ld({
    '@context': 'https://schema.org',
    '@graph': [
      { '@type': 'Organization', '@id': 'https://oravel.org/#org', name: 'oravel.org', url: 'https://oravel.org/', logo: 'https://oravel.org/favicon.svg' },
      { '@type': 'WebSite', '@id': 'https://oravel.org/#site', url: 'https://oravel.org/', name: 'oravel.org', inLanguage: 'pt-BR', publisher: { '@id': 'https://oravel.org/#org' } }
    ]
  }));
  eleventyConfig.addShortcode('ldArticle', (title, desc, date, image, theme, url) => {
    const iso = DateTime.fromJSDate(date, { zone: 'America/Sao_Paulo' }).toISO();
    const article = { '@type': 'Article', headline: title, description: String(desc).replace(/<[^>]*>/g, ''), datePublished: iso, dateModified: iso, inLanguage: 'pt-BR', mainEntityOfPage: url, author: { '@type': 'Organization', name: 'oravel.org', url: 'https://oravel.org/' }, publisher: { '@id': 'https://oravel.org/#org' } };
    if (image) article.image = 'https://oravel.org' + image;
    return ld({ '@context': 'https://schema.org', '@graph': [article, { '@type': 'BreadcrumbList', itemListElement: [
      { '@type': 'ListItem', position: 1, name: 'Início', item: 'https://oravel.org/' },
      { '@type': 'ListItem', position: 2, name: THEME_LABELS[theme] || theme, item: 'https://oravel.org/' + theme + '/' },
      { '@type': 'ListItem', position: 3, name: title, item: url } ] }] });
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