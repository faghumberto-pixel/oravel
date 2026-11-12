// Descobre a edição mais recente em src/assets/gazeta/gazeta-oravel-edicao-N.pdf
const fs = require('fs'), path = require('path');
module.exports = () => {
  const dir = path.join(__dirname, '../assets/gazeta');
  const nums = fs.existsSync(dir) ? fs.readdirSync(dir).map(f => (f.match(/^gazeta-oravel-edicao-(\d+)\.pdf$/) || [])[1]).filter(Boolean).map(Number) : [];
  const numero = nums.length ? Math.max(...nums) : null;
  return { numero, atual: '/assets/gazeta/gazeta-oravel-atual.pdf', url: numero ? `/assets/gazeta/gazeta-oravel-edicao-${numero}.pdf` : null };
};
