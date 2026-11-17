# Plano de Implementação — Roadmap Oravel.org

## Visão Geral
Transformar o oravel.org de "blog" para **centro de inteligência** para locadoras de equipamentos, seguindo as 7 fases do roadmap.

---

## Fase 1 — Fundação: Fazer parecer uma instituição

### 1.1 Páginas Institucionais Obrigatórias
| Página | Status | Ação |
|--------|--------|------|
| Manifesto | ❌ | Criar `/manifesto/` — propósito, valores, compromissos |
| Missão | ❌ | Integrar no `/sobre/` ou página dedicada |
| Quem somos | ✅ | Já existe em `/sobre/` — expandir |
| Política editorial | ❌ | Criar `/politica-editorial/` — regras de fonte, correção, independência |
| Critérios das fontes | ❌ | Integrar na política editorial ou página dedicada |
| Como produzimos conteúdo | ❌ | Criar `/como-produzimos/` — processo editorial |
| Quem escreve | ❌ | Página de autores/colaboradores |
| Quem revisa | ❌ | Conselho editorial / revisores |
| Periodicidade | ❌ | Calendário editorial público |

### 1.2 Identidade Visual — "Revista / Centro de Inteligência"
- **Hero da home**: Substituir texto atual por:
  > **Inteligência para quem administra locadoras de equipamentos.**
  > Pesquisamos. Traduzimos. Explicamos. Testamos. Compartilhamos.
  > Tudo o que pode ajudar sua locadora a tomar decisões melhores.
- **Nome da comunidade**: Definir (ex: "Rede Oravel", "Observatório Oravel", "Inteligência Oravel")
- **Tagline consistente** em todas as páginas

### 1.3 Arquitetura de Informação
```
/
├── /manifesto/
├── /politica-editorial/
├── /como-produzimos/
├── /autores/
├── /conselho-editorial/
├── /calendario-editorial/
├── /sobre/ (expandir)
├── /reforma-tributaria/
├── /tecnologia/
├── /la-fora/
├── /vendas/
├── /gestao-e-operacao/
├── /comunidade/ (reformular)
├── /observatorio/ (Fase 6)
├── /ferramentas/ (Fase 4 - hub)
├── /painel-mercado/ (Fase 3)
├── /gazeta/
├── /publicacoes/
├── /glossario/
└── /busca/
```

---

## Fase 2 — Autoridade: 50 Artigos Completos

### Meta por Tema (6 categorias × ~8-9 artigos = 50+)

| Tema | Atual | Meta | Gap | Prioridade |
|------|-------|------|-----|------------|
| Reforma tributária | 4 | 9 | +5 | 🔴 Alta |
| Tecnologia | 3 | 9 | +6 | 🔴 Alta |
| Lá fora | 4 | 8 | +4 | 🟡 Média |
| Vendas | 5 | 8 | +3 | 🟡 Média |
| Gestão e operação | 5 | 8 | +3 | 🟡 Média |
| Comunidade | 2 | 8 | +6 | 🟢 Baixa |

### Tópicos Sugeridos por Tema

#### Reforma Tributária (+5)
- [ ] Crédito de IBS/CBS na locação — casos práticos
- [ ] Split payment na locadora — fluxo de caixa
- [ ] Regime específico vs. regime geral — simulação
- [ ] Contratos vigentes — cláusulas de revisão tributária
- [ ] Cronograma 2026-2033 — marcos para a locadora

#### Tecnologia (+6)
- [ ] Telemetria: ROI por tipo de equipamento
- [ ] QR Code / RFID no controle de frota
- [ ] NFe/NFSe na locação — automação fiscal
- [ ] IA na precificação dinâmica de diárias
- [ ] Integração ERP + telemetria + financeiro
- [ ] LGPD na telemetria de equipamentos

#### Lá Fora (+4)
- [ ] ARA (American Rental Association) — benchmarks 2024
- [ ] ERA (European Rental Association) — relatório de mercado
- [ ] Locação na América Latina — México, Chile, Colômbia
- [ ] Sustentabilidade na locação europeia — lições

#### Vendas (+3)
- [ ] Precificação por valor — além da diária
- [ ] Pipeline de vendas B2B para locadoras
- [ ] Renovação de contratos — gestão de churn

#### Gestão e Operação (+3)
- [ ] Maturidade de manutenção — modelo de 5 níveis
- [ ] KPIs operacionais — dashboard mínimo viável
- [ ] Estoque de peças — curva ABC na locadora

#### Comunidade (+6)
- [ ] Como montar associação regional de locadoras
- [ ] Benchmarking entre locadoras — metodologia
- [ ] Sucessão familiar na locadora
- [ ] Gestão de pessoas na locadora média
- [ ] Seguros na locação — o que contratar
- [ ] Locadora como plataforma — novos modelos

---

## Fase 3 — Inteligência: Painel do Mercado

### 3.1 Dados a Coletar (Comunidade Alimentada)
| Indicador | Fonte | Frequência |
|-----------|-------|------------|
| Diária média por categoria | Pesquisa anônima | Trimestral |
| Taxa de utilização média | Telemetria (opcional) / Declarada | Mensal |
| Disponibilidade de frota | Declarada | Mensal |
| Custo de manutenção / receita | Declarada | Trimestral |
| Tempo médio de parada | Declarada | Mensal |
| Principais dores (ranking) | Enquete | Mensal |
| Tecnologias adotadas | Checklist | Semestral |
| Maturidade digital | Autoavaliação | Anual |

### 3.2 Visualizações
- **Ranking** de dores do setor (atualizado mensal)
- **Mapa** de locadoras por estado/região (heatmap)
- **Gráficos** de tendências (diária, utilização, disponibilidade)
- **Indicadores** de maturidade por porte/região
- **Benchmarking** anônimo (percentis P25, P50, P75)

### 3.3 Implementação Técnica
- Formulário anônimo de contribuição (Google Forms / Typeform / custom)
- Armazenamento: Firestore / Supabase / JSON estático + rebuild
- Página `/painel-mercado/` com gráficos (Chart.js / Recharts)
- Atualização: rebuild semanal ou mensal via GitHub Actions

---

## Fase 4 — Ferramentas: Hub de Calculadoras Gratuitas

### 4.1 Lista de Ferramentas (Priorizadas)

| Ferramenta | Tema | Complexidade | Status |
|------------|------|--------------|--------|
| Calculadora de Preço da Diária | Vendas | Média | 🔴 Prioridade 1 |
| Calculadora de Margem por Equipamento | Vendas | Média | 🔴 Prioridade 1 |
| Simulador Impacto Reforma 2027 | Reforma | Alta | 🟡 Prioridade 2 |
| Calculadora de Disponibilidade | Gestão | Baixa | 🟡 Prioridade 2 |
| ROI da Manutenção Preventiva | Gestão | Média | 🟡 Prioridade 2 |
| Payback de Aquisição | Gestão | Baixa | 🟢 Prioridade 3 |
| Checklist de Devolução/Inspeção | Gestão | Baixa | ✅ Existe (HTML) |
| Calculadora de Maturidade | Gestão | Média | 🟢 Prioridade 3 |
| KPIs Dashboard (auto-preenchido) | Gestão | Alta | 🟢 Prioridade 3 |
| Simulador de Crédito IBS/CBS | Reforma | Média | 🟡 Prioridade 2 |

### 4.2 Arquitetura Técnica
- Página hub: `/ferramentas/`
- Cada ferramenta: página própria `/ferramentas/nome/`
- Stack: HTML + CSS + Vanilla JS (sem build, roda no browser)
- Estado: localStorage para salvar inputs do usuário
- Compartilhamento: URL com parâmetros (?param=value)

### 4.3 Calculadora Preço da Diária — Especificação
**Inputs:**
- Custo de aquisição
- Vida útil estimada (meses)
- Taxa de utilização esperada (%)
- Custo mensal de manutenção
- Custo mensal de seguro
- Custo mensal de rastreador/telemetria
- Custo de capital (% a.a.)
- Margem desejada (%)

**Outputs:**
- Custo total de posse (mensal)
- Custo por hora disponível
- Preço mínimo da diária (8h)
- Preço sugerido com margem
- Ponto de equilíbrio (dias/mês)

---

## Fase 5 — Comunidade: Gerar Conteúdo

### 5.1 Formatos Recorrentes
| Formato | Frequência | Responsável | Destino |
|---------|------------|-------------|---------|
| Pergunta da Semana | Semanal | Moderador | Portal + E-mail |
| Enquete Rápida | Quinzenal | Moderador | Painel Mercado |
| Mesa Redonda | Mensal | Convidados | Artigo + Áudio |
| Especialista Convidado | Mensal | Convidado | Artigo/Entrevista |
| Live / Podcast | Quinzenal | Host + Convidado | YouTube/Spotify + Resumo |
| Resumo Semanal | Semanal | Curadoria | Newsletter |

### 5.2 Mecânica de "Volta para o Portal"
- Toda live → artigo resumo no dia seguinte
- Toda enquete → dado no Painel do Mercado
- Toda mesa redonda → artigo sintetizado
- Pergunta da semana → tópico de artigo futuro

### 5.3 Plataforma Técnica
- **Comunidade**: Discord (privado) ou Circle.so ou Discourse
- **Agendamento**: Calendly / Cal.com
- **Transmissão**: StreamYard / Riverside
- **Podcast**: Anchor / Spotify for Podcasters
- **Newsletter**: Sender (já configurado) / Beehiiv

---

## Fase 6 — Produção Própria: Observatório Oravel

### 6.1 Publicações Trimestrais
| Publicação | Formato | Periodicidade | Conteúdo |
|------------|---------|---------------|----------|
| Panorama do Setor | PDF + Web | Trimestral | Mercado, regulamentação, tecnologia |
| Índice de Maturidade | PDF + Web | Semestral | Nível de gestão, tecnologia, processos |
| Índice de Tecnologia | PDF + Web | Anual | Adoção de telemetria, IA, ERP, NFe |
| Índice de Crescimento | PDF + Web | Anual | Expansão de frota, receita, contratos |
| Custos Médios | PDF + Web | Trimestral | Diária, manutenção, seguro, capital |
| Disponibilidade Média | PDF + Web | Trimestral | Por categoria, porte, região |
| Tempo Médio Manutenção | PDF + Web | Trimestral | Preventiva vs corretiva, por equipamento |

### 6.2 Metodologia
- Dados primários: Painel do Mercado (Fase 3)
- Dados secundários: IBGE, ARA, ERA, associações setoriais
- Amostra: Mínimo 30 locadoras por recorte para validade estatística
- Revisão por pares: Conselho editorial + especialistas convidados

### 6.3 Página `/observatorio/`
- Índice de todas as publicações
- Download de PDFs
- Visualizações interativas dos índices
- Metodologia transparente
- Formulário para participar da próxima coleta

---

## Fase 7 — Reconhecimento: Autoridade de Mercado

### 7.1 Sinais de Sucesso
- [ ] Associações citam dados do Observatório
- [ ] Imprensa procura como fonte setorial
- [ ] Locadoras usam índices em planejamento estratégico
- [ ] Fornecedores adaptam produtos aos benchmarks
- [ ] Convites para falar em eventos do setor
- [ ] Parcerias com entidades de classe

### 7.2 Ações para Acelerar
- Press kit para imprensa
- Relatórios "executive summary" para dirigentes
- Webinars de lançamento de cada índice
- Certificação "Locadora Benchmark Oravel" (futuro)

---

## Cronograma Sugerido (12 Meses)

| Mês | Foco Principal | Entregáveis |
|-----|----------------|-------------|
| 1-2 | **Fase 1** | Páginas institucionais, identidade visual, nome da comunidade |
| 2-4 | **Fase 2** | +20 artigos (prioridade: Reforma + Tecnologia) |
| 3-4 | **Fase 4** | Hub `/ferramentas/` + 3 calculadoras (diária, margem, disponibilidade) |
| 4-5 | **Fase 3** | Formulário coleta + Painel do Mercado v1 (gráficos básicos) |
| 5-6 | **Fase 5** | Comunidade ativa (Discord) + formatos recorrentes iniciados |
| 6-8 | **Fase 2** | +20 artigos (completar 50) |
| 7-8 | **Fase 4** | +4 calculadoras (ROI manutenção, payback, simulador reforma, maturidade) |
| 8-9 | **Fase 3** | Painel do Mercado v2 (mapas, ranking, benchmarking) |
| 9-10 | **Fase 6** | Primeira edição Panorama + Índice de Maturidade |
| 10-12 | **Fase 6-7** | Publicações trimestrais + press kit + divulgação |

---

## Próximos Passos Imediatos (Esta Semana)

1. **Definir nome da comunidade** — votação interna: "Rede Oravel" vs "Observatório Oravel" vs "Inteligência Oravel"
2. **Criar `/manifesto/`** — base para todas as comunicações
3. **Criar `/politica-editorial/`** — transparência total
4. **Atualizar Hero da Home** — com o texto "Inteligência para quem administra..."
5. **Listar 5 artigos prioritários** para começar Fase 2 (Reforma + Tecnologia)
6. **Especificar Calculadora Preço da Diária** — inputs, fórmulas, outputs
7. **Configurar Discord/Circle** para comunidade (lista de espera → ativa)

---

## Decisões Pendentes (Precisam do seu input)

1. **Nome da comunidade**: Rede Oravel / Observatório Oravel / Inteligência Oravel / Comunidade Oravel?
2. **Plataforma da comunidade**: Discord (gratuito, conhecido) vs Circle.so (pago, mais profissional) vs Discourse (auto-hospedado)?
3. **Coleta de dados do Painel**: Formulário próprio (mais controle) vs Typeform/Google Forms (mais rápido)?
4. **Frequência da Gazeta**: Semanal (atual) vs Quinzenal (mais sustentável)?
5. **PDFs do Observatório**: Acesso livre vs Mediante cadastro (lead generation)?

---

## Métricas de Acompanhamento

| Métrica | Meta 6m | Meta 12m |
|---------|---------|----------|
| Artigos publicados | 30 | 50+ |
| Ferramentas ativas | 4 | 10 |
| Membros da comunidade | 100 | 500 |
| Respostas Painel/trimestre | 50 | 200 |
| Downloads Gazeta/edição | 200 | 500 |
| Referências externas (backlinks) | 10 | 50 |
| Menções na imprensa/setor | 2 | 10 |