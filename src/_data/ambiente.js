// Indica se o site esta sendo gerado para uso local (npm run dev) ou para publicacao.
// Dados de demonstracao (src/_data/ranking.json com demo: true) so aparecem no uso local.
module.exports = function () {
  const modo = process.env.ELEVENTY_RUN_MODE || 'build';
  return { modo, local: modo === 'serve' || modo === 'watch' };
};
