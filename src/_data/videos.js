// Videos da faixa "Videos" da home (aparece depois da grade principal).
// A faixa so e exibida quando houver pelo menos um video publicado (data <= hoje).
//
// Para adicionar um video, inclua um item em `itens`:
//   id:         ID do video no YouTube (o trecho depois de v= na URL; use "nao listado")
//   titulo:     titulo exibido no cartao e na janela do player
//   data:       data de publicacao, "AAAA-MM-DD" (videos com data futura ficam ocultos)
//   tema:       frente editorial (chave: reforma-tributaria, tecnologia, la-fora, vendas,
//               gestao-e-operacao, comunidade) ou "ferramentas"
//   resumo:     uma frase (usada nos dados estruturados para busca)
//   duracao:    opcional, formato ISO 8601, ex.: "PT3M30S" (3 min 30 s)
//   artigo:     opcional, final do nome do arquivo do artigo em src/content (sem data).
//               O link "Ler o artigo" aparece abaixo do video so se o artigo ja estiver publicado.
//   ferramenta: opcional, slug em src/_data/ferramentas.js. Mostra "Usar a ferramenta".
module.exports = {
  itens: [
    // {
    //   id: "XXXXXXXXXXX",
    //   titulo: "Como calcular a rentabilidade da frota",
    //   data: "2026-11-20",
    //   tema: "ferramentas",
    //   resumo: "Passo a passo da Calculadora de Rentabilidade da Oravel.org.",
    //   duracao: "PT4M10S",
    //   artigo: "preco-da-diaria-a-conta-nao-termina-na-diaria",
    //   ferramenta: "calculadora-rentabilidade"
    // }
  ]
};
