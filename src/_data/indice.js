// Indice Oravel: pesquisa publica e anonima, respondida nesta pagina e gravada no Observatorio.
//   INDICE_API_URL            endereco da API do Observatorio (padrao: producao)
//   INDICE_TURNSTILE_SITEKEY  chave publica do Cloudflare Turnstile; vazio = sem desafio (o servidor so exige quando tem as chaves)
module.exports = {
  api: process.env.INDICE_API_URL || 'https://admin.observatorio.oravel.org/api/public/v1/indice',
  turnstile: process.env.INDICE_TURNSTILE_SITEKEY || '',
};
