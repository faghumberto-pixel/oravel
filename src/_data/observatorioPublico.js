// Dados PUBLICADOS do Observatorio Oravel, lidos no build a partir da API publica do Backoffice.
//   OBSERVATORIO_PUBLIC_URL  ex.: https://admin.observatorio.oravel.org/api/public/v1   (vazio = bloco nao aparece)
// A API so serve snapshots aprovados por um Administrador Oravel, ja agregados (minimo de 10 empresas por grupo).
// Aqui ha uma SEGUNDA verificacao no consumo: qualquer grupo abaixo do minimo e descartado, e um snapshot que nao
// segue o formato esperado e ignorado. Falha de rede nunca quebra o build: o bloco simplesmente nao aparece.
const TIMEOUT_MS = 8000;
const K_PISO = 10;

function ok(snap) {
  return snap && snap.schema_version === '1' && snap.escopo === 'geral' && snap.resumo && snap.metodologia
    && Array.isArray(snap.evolucao) && snap.radar;
}

// Remove grupos com menos que o piso de empresas (defesa em profundidade; o Backoffice ja filtra).
function limpaBloco(b, k) {
  if (!b) return { grupos: [], base: null, suficiente: false, omitidos: 0 };
  const grupos = (b.grupos || []).filter(g => Number(g.empresas) >= k);
  return { ...b, grupos, suficiente: !!b.suficiente && grupos.length > 0 };
}

// Benchmark: so celulas com pelo menos k empresas e so os campos n, quartis e media (nada de min/max).
function celula(c, k) {
  if (!c || Number(c.n) < k) return null;
  return { n: c.n, p25: c.p25, p50: c.p50, p75: c.p75, media: c.media };
}
function lista(cs, k) {
  return (cs || []).map(c => ({ rotulo: String(c.rotulo), ...celula(c, k) })).filter(c => c.n !== undefined);
}
function benchmark(bs, k) {
  return (Array.isArray(bs) ? bs : []).map(b => ({
    slug: b.slug, nome: b.nome, unidade: b.unidade, sentido: b.sentido,
    nacional: celula(b.nacional, k), porPorte: lista(b.por_porte, k), porRegiao: lista(b.por_regiao, k),
  })).filter(b => b.nacional || b.porPorte.length || b.porRegiao.length);
}

module.exports = async function () {
  const base = (process.env.OBSERVATORIO_PUBLIC_URL || '').replace(/\/+$/, '');
  if (!base) return { disponivel: false };

  try {
    const ctl = new AbortController();
    const t = setTimeout(() => ctl.abort(), TIMEOUT_MS);
    const resp = await fetch(base + '/indicadores/geral', { signal: ctl.signal, headers: { Accept: 'application/json' } });
    clearTimeout(t);
    if (resp.status === 404) return { disponivel: false }; // nada publicado (ou retirado)
    if (!resp.ok) throw new Error('HTTP ' + resp.status);
    const snap = await resp.json();
    if (!ok(snap)) throw new Error('formato inesperado');

    const k = Math.max(Number(snap.metodologia.k_minimo) || 0, K_PISO);
    const bloco = n => limpaBloco(snap[n], k);
    return {
      disponivel: true,
      versao: snap.versao,
      publicadoEm: new Date(snap.publicado_em),
      apuradoEm: new Date(snap.apurado_em),
      k,
      unidade: snap.metodologia.unidade,
      resumo: snap.resumo,
      porEstado: bloco('por_estado'),
      porPorte: bloco('por_porte'),
      temas: bloco('temas'),
      evolucao: snap.evolucao.filter(m => !m.suprimido && Number(m.empresas) >= k),
      benchmark: benchmark(snap.benchmark, k),
      radar: snap.radar.suficiente
        ? { ...snap.radar, temas: (snap.radar.temas || []).filter(t => ['novo', 'crescendo', 'diminuindo', 'recorrente'].includes(t.status)) }
        : { suficiente: false, temas: [], motivo: snap.radar.motivo, janela_dias: snap.radar.janela_dias },
    };
  } catch (e) {
    console.warn('[observatorio] snapshot indisponivel, bloco omitido: ' + e.message);
    return { disponivel: false };
  }
};
