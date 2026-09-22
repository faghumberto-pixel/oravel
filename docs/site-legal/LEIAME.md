# Política de Privacidade e Termos de Uso: rascunhos

**Status: RASCUNHO. Não publicar sem preencher os dados e sem revisão de um advogado.** O texto foi escrito com base no
que o sistema realmente faz (conferido no código e na produção em 21/09/2026), mas cláusulas como responsabilidade,
foro, prazos e bases legais são decisões jurídicas e comerciais suas.

## Como usar

```bash
python3 docs/site-legal/build.py                # PREVIEW em docs/site-legal/preview/ (faixa de rascunho, noindex, campos em amarelo)
python3 docs/site-legal/build.py --publish      # grava public_html/politica-de-privacidade/ e public_html/termos-de-uso/
```

`--publish` **recusa** se faltar qualquer dado ou sobrar marcador. Depois de gerar, o envio ao servidor segue o processo
de sempre (SFTP com backup, md5 e verificação).

## Dados a preencher (`dados-empresa.json`)

| Campo | Observação |
|---|---|
| `RAZAO_SOCIAL`, `CNPJ`, `ENDERECO` | Do cartão CNPJ. Use o endereço cadastrado na Receita (se for residência, considere um endereço comercial). |
| `EMAIL_PRIVACIDADE`, `ENCARREGADO_NOME` | Precisa ser uma caixa que alguém lê (sugestão: privacidade@oravel.com.br). Pode ser o próprio sócio. |
| `FORO_CIDADE` | Comarca para disputas. |
| `DATA_VIGENCIA` | Data de publicação. |
| `PRAZO_TRIAL` | O site já menciona "testar grátis por 14 dias" em um formulário; confirme o número real. |
| `PRAZO_REAJUSTE`, `CONDICAO_CANCELAMENTO`, `LIMITE_RESPONSABILIDADE_MESES` | Definições comerciais/jurídicas. |
| `RETENCAO_LEADS`, `RETENCAO_CLIENTES`, `RETENCAO_POS_CONTRATO`, `RETENCAO_BACKUPS` | Por quanto tempo guarda. Registros de acesso: mínimo 6 meses (Marco Civil). |
| `SUPORTE_CANAIS_HORARIOS` | Descreva o suporte que existe de verdade (não prometa 24/7 sem ter). |

## Opções (`FLAGS`) que precisam bater com a realidade

| Flag | Estado hoje | Por quê |
|---|---|---|
| `ANALYTICS_GA` / `ANALYTICS_CLARITY` | `true` | Google Analytics 4 (`G-L79HHRE3ZC`) e Microsoft Clarity (grava sessões) foram removidos do `/contato.php` em 21/09/2026, mas **continuam** em `/sobre/`, `/faq.php`, `/gestao-locadoras/`, `/locadoras/` e `/nova/`. Só desligue as flags depois de remover de todas. |
| `IA_ANTHROPIC` | `true` | A produção tem `ANTHROPIC_API_KEY` e há serviços de análise por IA (`ai_analyses` ainda com 0 linhas). |
| `COOKIE_BANNER` | `false` | **Não existe aviso de cookies.** Enquanto for `false` e Clarity/GTM estiverem ativos, há descompasso com a LGPD. |
| `MARKETING_EMAILS` | `false` | Ligue só se for enviar newsletter/comercial. |
| `TRIAL` | `true` | Desligue se não houver período de teste. |

## Pendências fora destes textos

1. **Aviso de cookies** (com consentimento) ou tirar o Clarity/Google Analytics das 5 páginas que ainda os carregam (decida antes de publicar a política).
2. **Rodapé com razão social, CNPJ e endereço** em todas as páginas (o montador só cobre estas duas).
3. **Link para a Política nos formulários** ("Ao enviar, você concorda com a Política de Privacidade") e no
   `/assinar` (o checkout já grava `terms_accepted_at`, mas hoje não há Termos publicados).
4. Confirmar o **contrato de operador** com os clientes (a cláusula 5 dos Termos cobre o essencial).
5. Rever `TrackSiteVisit` (IP/navegador/UTM/cookie de visita) e `user_activity_logs` se quiser reduzir o que é guardado.
