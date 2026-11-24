// Indice Oravel: pesquisa publica e anonima, respondida nesta pagina e gravada no Observatorio.
//   INDICE_API_URL            endereco da API do Observatorio (padrao: producao)
//   INDICE_TURNSTILE_SITEKEY  chave PUBLICA do Cloudflare Turnstile (a mesma do portal do Observatorio; vai no HTML de qualquer jeito).
//                             O padrao abaixo e' a de producao: o deploy agendado (cron) builda sem variaveis, e sem a chave a API recusa os envios.
//                             Para desligar o desafio num teste local: INDICE_TURNSTILE_SITEKEY=off
module.exports = {
  api: process.env.INDICE_API_URL || 'https://admin.observatorio.oravel.org/api/public/v1/indice',
  turnstile: process.env.INDICE_TURNSTILE_SITEKEY === 'off' ? '' : (process.env.INDICE_TURNSTILE_SITEKEY || '0x4AAAAAAGL6QHs5GfmPVu2W'),
};
