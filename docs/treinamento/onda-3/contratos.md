# Onda 3.1 — Treinamento: Contratos

Onda 3 = Comercial e contratos (3.1 Contratos, 3.2 Orçamentos e Proposta Comercial, 3.3 Solicitações de Locação).

## 1. Conferência da aula contra o sistema (feita em 14/10/2026)

Fonte conferida: `ContractResource` (+ páginas, `ContractStats`), `Contract`, `ContractObserver`, `FaturarContratoAction`, `PropostaComercial::criarContratosDeServico`, `Asset::activeContract`, `MaintenanceOrder` (Interno/Externo).

| # | O que a aula dizia | O que o sistema faz | Ação |
|---|---|---|---|
| 1 | Botão "Criar contract"; cartões "total, ativos, a vencer e valor mensal" | Botão **Criar**; cartões Total de Contratos, Contratos Ativos, A Vencer em 30 dias, Valor em Contratos Ativos | Corrigido |
| 2 | Status Rascunho/Ativo/Encerrado, mas **nenhuma tela mudava o status** | O formulário não tinha campo de situação: todo contrato criado pela tela ficava em Rascunho para sempre, sem faturar, sem contar como ativo e sem como encerrar (os 3 contratos do DEV foram gravados direto no banco) | **Corrigido no sistema** (ver abaixo) |
| 3 | Só 5 campos | 5 seções: partes, objeto e prazos, local de instalação, manutenção/logística/riscos, itens agregados, responsabilidades e rescisão, histórico de substituição | Tabela nova |
| 4 | Não citava modalidade de cobrança | Mensal Fixo, Por Hora, Franquia + Excedente, Diária (com total calculado e fim da vigência obrigatório) | Seção nova + 3 perguntas |
| 5 | Não citava a trava de PMP | Recusa equipamento com preventiva crítica vencida ao criar | Seção nova + pergunta |
| 6 | Não citava assinatura, impressão, faturamento, custo e margem, timeline | Gerar/Copiar link, Ver Certificado, Ver e imprimir, Faturar, Custo e margem, Timeline | Seção nova + 4 perguntas |
| 7 | Não citava tipo de venda | Locação ou serviço (4 categorias); proposta aceita gera contratos de serviço em Rascunho (`SV-AAAAMM-XXXXX`); equipamento não obrigatório neles | Seção nova + pergunta |
| 8 | "Encerrado (marcar como inativo)" | Encerrar = situação Encerrado (que desliga `is_active`) | Corrigido |
| 9 | Texto duplicado (versão antiga colada embaixo) | — | Removida a duplicata |

Prova: de 9 para 26 perguntas.

### Correção feita no sistema (14/10/2026)

- `ContractResource`: novo campo **Situação do Contrato** (Rascunho, Ativo, Encerrado), padrão Ativo, obrigatório.
- `Contract::saving`: mudar a situação ajusta `is_active` (Encerrado → inativo; demais → ativo), que é o que o `ContractObserver` já usava para liberar o equipamento.
- `ContractObserver::updated`: reabrir um contrato encerrado trava o equipamento de novo (Locado, cliente e local).
- Testes: `tests/Feature/ContractStatusTest.php` (5; 3 falham sem a correção).

### Achados que continuam em aberto

1. **Contratos de serviço gerados por proposta nascem em Rascunho**, e agora têm como ser ativados, mas nada avisa o comercial disso. Vale um aviso ou ativar junto com a proposta.
2. **Criar já trava o ativo mesmo em Rascunho** (o observer só olha a criação, não a situação).
3. **Contrato vencido não encerra sozinho**: a data de fim passa e o ativo continua Locado até alguém marcar Encerrado. Falta aviso ou rotina.
4. **Os contratos do DEV** (3) foram gravados direto no banco, então podem não refletir o fluxo da tela.
5. **Imagens:** `contratos-lista.jpg`, `contrato-novo.jpg`, `contrato-timeline.jpg` não foram conferidas; o formulário mudou (campo Situação, seções novas).
6. **Falha antiga:** 5 testes de assinatura (`DocumentSignatureTest`) falham por `Tenant::factory()` inexistente.

## 2. Roteiro de aula (35 a 45 min)

1. O papel do contrato: o ativo muda de situação por causa dele. 5 min
2. Criar uma locação mensal: partes, objeto, local, manutenção/riscos, valor. 12 min
3. Modalidades de cobrança, com exemplo de diária. 6 min
4. Assinatura, imprimir, faturar e custo/margem. 10 min
5. Situação: Rascunho, Ativo, Encerrado; encerrar e reabrir. 6 min
6. Contratos de serviço vindos da proposta. 4 min

## 3. Exercício prático (empresa "Oravel", dados fictícios)

**Parte A — locação mensal.** Criar um contrato mensal para um cliente e um gerador disponível. Conferir: o ativo vira Locado, com o cliente.
**Parte B — diária.** Criar outro, por diária, de 01/10 a 05/10 com diária de R$ 200. O total calculado deve mostrar R$ 1.000.
**Parte C — trava.** Tentar locar um ativo com preventiva crítica vencida. Anotar a mensagem.
**Parte D — assinatura.** Gerar o link e copiá-lo; abrir "Ver e imprimir".
**Parte E — faturar.** Faturar o mês passado do contrato mensal e abrir o Contas a Receber. Tentar faturar de novo o mesmo período e anotar a mensagem.
**Parte F — encerrar e reabrir.** Mudar para Encerrado: o ativo deve voltar a Disponível. Reabrir: volta a Locado.
**Parte G — rascunho.** Criar um contrato em Rascunho e conferir que o botão Faturar não aparece.

## 4. Guia rápido (1 página)

**Situação:** Rascunho (não vale), Ativo (vale), Encerrado (libera o ativo). Só Ativo fatura e conta.
**Criar já trava o ativo.** Encerrar = situação Encerrado; a data de fim passar não basta.
**Cobrança:** mensal fixo, por hora (valor manual), franquia + excedente, diária (fim obrigatório).
**Faturar:** período dentro da vigência, sem sobrepor outro; gera medição e cobrança no Contas a Receber.
**Proposta aceita:** serviços viram contratos próprios em Rascunho.
**Local mudou:** o ativo atualiza e os responsáveis são avisados.

## 5. Capturas e vídeos a produzir

Capturas: lista com cartões e filtros; formulário (seções 1, 2 com Situação, local, 3 e 4); total da diária; trava de preventiva; ações da linha (Ver e imprimir, Faturar, assinatura); janela Faturar com prévia; Custo e margem; Timeline; histórico de substituição.
Vídeos (3 a 5 min): (1) criar e ativar uma locação; (2) faturar um período; (3) encerrar e reabrir.
