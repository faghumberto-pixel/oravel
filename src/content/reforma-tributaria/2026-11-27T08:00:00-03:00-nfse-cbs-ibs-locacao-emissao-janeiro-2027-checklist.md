---
image: "/assets/img/nfse-cbs-ibs-locacao-2027-checklist.jpg"
title: "NFS-e com CBS/IBS na locação: o que muda na emissão a partir de janeiro de 2027"
date: 2026-11-27T08:00:00-03:00
theme: "reforma-tributaria"
summary: "Layout da NFS-e com campos CBS/IBS, CST/CFOP, crédito do tomador, testes em 2026; checklist de preparação do sistema emissor e do financeiro para o split payment."
source: "FazNota; PraticoLocacoes; Contábeis; TaxUp; Gov.br; Conexês Contabilidade; Advanced Contabil"
readingTime: "5 min"
---

## O que muda na nota fiscal de locação em 2027

A partir de **01/01/2027**, a **NFS-e nacional** (padrão unificado) passa a exigir **campos específicos para CBS e IBS**, além do **split payment** (pagamento dividido). Quem emite nota hoje via prefeitura ou software próprio precisa testar em 2026 para não parar em janeiro.

## Novos campos obrigatórios na NFS-e (locação de bens móveis)

| Campo | Descrição | Exemplo locação |
|-------|-----------|-----------------|
| `tribIBS` | Grupo IBS | Obrigatório |
| `tribCBS` | Grupo CBS | Obrigatório |
| `vBC_IBS` | Base de cálculo IBS | R$ 10.000,00 |
| `vBC_CBS` | Base de cálculo CBS | R$ 10.000,00 |
| `pAliq_IBS` | Alíquota IBS (statutária) | 17,7%* |
| `pAliq_CBS` | Alíquota CBS (statutária) | 8,8%* |
| `vIBS` | Valor IBS (base × alíquota) | R$ 1.770,00 |
| `vCBS` | Valor CBS (base × alíquota) | R$ 880,00 |
| `cSitTrib` | Código de situação tributária (CST) | Ver tabela abaixo |
| `cBenefFisc` | Código de benefício fiscal (se houver) | — |
| `indSplit` | Indicador split payment | 1 = Sim |

*Alíquotas de teste 2026; definitivas virão em lei complementar.

### CST para locação de bens móveis (sugestão baseada em discussões técnicas 2026)

| Situação | CST CBS | CST IBS | Observação |
|----------|---------|---------|------------|
| Locação padrão (Lucro Real/Presumido) | **01** (Operação tributável com crédito) | **01** | Regra geral |
| Locação para Simples Nacional (tomador) | **01** | **01** | Crédito do tomador limitado |
| Locação com imunidade/isenção | **02** / **03** | **02** / **03** | Raro em locação |
| Locação exportação (raro) | **04** | **04** | Não incide |

> **Atenção**: CST definitivo será publicado em ato do Comitê Gestor do IBS. Use a tabela acima como **referência de trabalho para testes em 2026**.

## Split payment na prática: o que a nota dispara

1. **Emissão da NFS-e** com `indSplit = 1` + valores de CBS/IBS
2. **Sistema bancário (SPB/Pix)** lê a nota → gera **QR Code Pix duplo**:
   - Parte 1: valor líquido → chave Pix da locadora
   - Parte 2: CBS + IBS → conta única da União/Estados/Municípios
3. **Cliente paga um único Pix** → sistema divide automaticamente
4. **Locadora recebe só o líquido** no extrato
5. **Comprovante do split** (gerado pelo banco) = prova de recolhimento (substitui DARF/GNRE)

## Checklist de preparação do sistema emissor (teste em 2026)

### Até 30/06/2026
- [ ] Contate seu **fornecedor de software emissor** (NFS-e): "Já têm versão homologada com CBS/IBS/split?"
- [ ] Peça **ambiente de homologação (sandbox)** para testar emissão.
- [ ] Valide: CST, alíquotas, base de cálculo, QR Code split, XML/JSON padrão.

### Até 30/09/2026
- [ ] **Teste emissão completa**: locação R$ 10.000 → NFS-e com CBS/IBS → QR Code split → pagamento simulado → conciliação.
- [ ] Verifique **CFOP** para locação: **5.929** (prestação de serviço de locação de bens móveis) ou o que seu contador definir.
- [ ] Teste **crédito do tomador**: emita nota para CNPJ seu (simulação) → veja se crédito aparece na DCTFWeb dele.

### Até 30/11/2026
- [ ] **Treine equipe de faturamento**: novos campos, CST, split, o que explicar ao cliente.
- [ ] Atualize **modelo de contrato**: cláusula de pagamento split (já vimos no post anterior).
- [ ] Alinhe com **contador**: como a locadora vai apurar crédito próprio (peças, diesel, energia, aluguel) na DCTFWeb nova.

### Dezembro/2026
- [ ] **Emissão paralela** (produção + homologação) por 15 dias — compare XMLs.
- [ ] **Go-live** 01/01/2027 com monitoramento diário primeira semana.

## Checklist do financeiro (conciliação split)

| Antes (2026) | Depois (2027) | Ação |
|--------------|---------------|------|
| Cliente paga R$ 12.650 → locadora | Cliente paga R$ 10.000 (locadora) + R$ 2.650 (gov) | Ajuste conciliação: **só o líquido entra no contas a receber** |
| Locadora guarda R$ 2.650 → recolhe DARF dia 20 | Recolhimento **instantâneo/automático** no pagamento | Elimine tarefa "recolher PIS/COFINS/ICMS/ISS" |
| Comprovante = DARF pago | Comprovante = **recibo do split (banco/SPB)** | Arquive PDF do split no lugar do DARF |
| Crédito = compensação mensal | Crédito = **apuração na DCTFWeb mensal** | Contador deve configurar nova rotina |

## O que explicar ao cliente (1 página, anexo ao contrato/aditivo)

> **A partir de 01/01/2027, o pagamento da locação muda:**
>
> 1. **Valor do contrato NÃO muda** (a diária continua a mesma).
> 2. Na hora de pagar, o **Pix vai gerar 2 destinos automáticos**: sua locadora (líquido) + governo (CBS/IBS).
> 3. Você **não precisa fazer nada diferente** — paga o Pix único que o banco divide.
> 4. O **comprovante do banco** vale como prova de que o imposto foi recolhido.
> 5. Dúvidas? Fale com seu contador ou chame a gente.

## Armadilhas comuns em 2026 (evite)

| Armadilha | Consequência | Prevenção |
|-----------|--------------|-----------|
| Não testar em homologação | Nota rejeitada em jan/2027; emissão manual de emergência | Teste **agosto a novembro** |
| CST errado | Crédito do tomador não entra; fiscalização | Valide com contador + software |
| Financeiro não preparado para só líquido | Fluxo de caixa "some" R$ 2.650 por R$ 10.000 | Simule no caixa: receita líquida = 73,5% do bruto |
| Não comunicar cliente | Cliente acha que é "aumento disfarçado" | Envie 1-pager em nov/2026 + cláusula no aditivo |

## Como aplicar na sua locadora

1. **Esta semana**: ligue para o fornecedor do emissor NFS-e e peça cronograma de homologação CBS/IBS.
2. **Agende com contador** simulação de carga tributária 2027 (Lucro Real/Presumido/Simples) — base para precificação.
3. **Monte grupo WhatsApp/Equipe**: faturamento + financeiro + contador + TI/fornecedor software.
4. **Rodar piloto** em setembro: 10 notas teste no sandbox → valide XML, QR Code split, conciliação.
5. **Novembro**: treine equipe, envie comunicado clientes, assine aditivos.
6. **01/01/2027**: monitore primeiras 50 notas — qualquer erro, acione suporte do software em < 1 h.

## Fontes

- [FazNota: Locação de máquinas e veículos: NFS-e com IBS e CBS a partir de 01/01/2027](https://faznota.com.br/blog/locacao-de-bens-moveis-nfse-ibs-cbs.php)
- [PraticoLocacoes: Reforma Tributária na Locação de Bens Móveis | Guia Completo](https://praticolocacoes.com.br/reforma-tributaria-locacao-equipamentos-ibs-cbs/)
- [Contábeis: Reforma Tributária: locação de bens móveis e crédito tributário](https://www.contabeis.com.br/artigos/79053/reforma-tributaria-locacao-de-bens-moveis-e-credito-tributario/)
- [TaxUp: Reforma Tributária na locação de máquinas e equipamentos](https://taxup.com.br/solucoes/reforma-tributaria/locacao-maquinas-equipamentos/)
- [Gov.br: Receita Federal e Comitê Gestor do IBS publicam documentação técnica da plataforma pública do split payment (jun/2026)](https://www.gov.br/fazenda/pt-br/assuntos/noticias/2026/junho/receita-federal-e-comite-gestor-do-ibs-publicam-documentacao-tecnica-da-plataforma-publica-do-split-payment)
- [Conexês Contabilidade: Locadoras na reforma: IBS e CBS no aluguel e na venda da frota](https://conexescontabilidade.com.br/br/seo/locadoras-de-veiculos-e-de-equipamentos-ibs-e-cbs-na-locacao-de-bens-moveis/)
- [Advanced Contabil: Locação de Equipamentos e Máquinas: CBS/IBS para Construtoras](https://advancedcontabil.com.br/blog/locacao-equipamentos-maquinas-cbs-ibs)