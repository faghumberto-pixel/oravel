# API do CRM Oravel — v1

Base: `https://app.oravel.com.br/api/crm/v1` · JSON · português nos campos de negócio.

## Autenticação

Token pessoal (Sanctum), enviado em todo pedido:

```
Authorization: Bearer <token>
Accept: application/json
```

O token é gerado **dentro do painel**, em *Comercial → Gestão CRM → Integração / API* (não existe login por e-mail e senha na API, para manter a 2FA do painel). Ele tem **validade** (30 a 365 dias) e **habilidades**:

| Habilidade | Permite |
|---|---|
| `crm:read` | todas as consultas (`GET`) |
| `crm:write` | criar lead, mover etapa, registrar interação (implica `crm:read`) |

O token age **como o usuário que o gerou**: as permissões do perfil e a regra "vendedor só vê os leads dele" valem na API do mesmo jeito que no painel. Tudo é isolado por empresa (tenant).

## Limites

Leitura: 120 pedidos/min por token. Escrita: 30 pedidos/min por token. Acima disso: `429`.
Áudio: até 16 MB (ogg/opus, mp3, m4a, webm, wav).

## Formato das respostas

Sucesso: `{ "data": … }` (listas e fichas dentro de `data`).
Erro: `{ "erro": { "codigo": "…", "mensagem": "…", "campos": { … } } }`.

| HTTP | `codigo` | Quando |
|---|---|---|
| 401 | `nao_autenticado` | token ausente, inválido, expirado ou revogado |
| 403 | `sem_habilidade` | token sem a habilidade da rota |
| 403 | `usuario_nao_aprovado`, `sem_empresa`, `conta_bloqueada`, `modulo_desligado` | usuário/empresa não pode usar o CRM |
| 403 | `sem_permissao`, `movimento_negado` | o perfil do usuário não permite a ação |
| 404 | `nao_encontrado` | registro inexistente **ou de outra empresa/vendedor** |
| 409 | `duplicado`, `ja_e_conta` | já existe lead (documento/telefone) ou conta/cliente do CRM (documento); envie `forcar=true` para criar mesmo assim |
| 422 | `dados_invalidos` | validação (`campos` traz o detalhe), `cnpj_invalido` |
| 429 | `muitas_requisicoes` | limite por minuto |

## Endpoints

### `GET /eu` — valida o token
Devolve `usuario`, `empresa`, `ve_todos_os_leads`, `permissoes` (`criar_lead`, `editar_lead`, `ver_contas`, `ver_produtos`, `ver_propostas`) e `token` (`habilidades`, `expira_em`).

### `GET /configuracao` — listas para os seletores
`funis[]` (com `etapas[]`: `id`, `nome`, `tipo` = aberta|ganho|perdido, `probabilidade`), `origens[]`, `motivos_perda[]` (`exige_detalhe`), `responsaveis[]`, `campos_personalizados[]` (do lead), `canais`. O catálogo de produtos e serviços vem em `GET /produtos`.

### `GET /contatos/buscar?telefone=` — acha quem é o contato aberto no WhatsApp
Procura em leads, contatos de leads, **contas** (clientes do CRM), contatos de contas e conversas de WhatsApp da empresa. Compara pelos **8 últimos dígitos** (tolera +55, DDD e 9º dígito). Devolve até 20 itens `{ tipo: "lead"|"conta", id, nome, … , url }`. Vendedor só recebe os leads e as contas dele.

### `GET /contatos/{tipo}/{id}/oportunidades` — o que está em andamento
`tipo` = `lead` ou `conta`. Devolve `leads_abertos[]` e `propostas_em_andamento[]` (rascunho, enviada ao comercial, aguardando o cliente).

### `GET /cnpj/{cnpj}` — dados da Receita
`{ documento, dados: { company_name, address, city, uf, cep, phone, email }, duplicidade }` (`duplicidade` é um aviso em texto quando já existe lead ou cliente com esse CNPJ). `422 cnpj_invalido` / `404 cnpj_nao_encontrado`.

### `GET /contas/{id}` — ficha da conta (cliente do CRM)
Dados, `contatos[]` e `negocios[]` (leads ligados). A conta nasce sozinha quando um lead é ganho, ou é cadastrada à mão/importada.

### `GET /produtos` — catálogo de produtos e serviços
Itens ativos: `id`, `codigo`, `nome`, `tipo` (produto|servico), `unidade`, `preco`.

### `GET /leads/{id}` — ficha
Dados do lead, `contatos[]`, `interacoes[]` (últimas 10) e `propostas_em_andamento[]`.

### `POST /leads` — cria lead (`crm:write`)
```json
{
  "nome": "Maria Souza", "empresa": "Acme Locações", "telefone": "5519999990000",
  "documento": "11.222.333/0001-81", "email": "maria@acme.com.br", "valor": 15000,
  "responsavel_id": "…", "funil_id": "…", "etapa_id": "…", "origem": "WhatsApp",
  "previsao_fechamento": "2026-12-20", "proximo_contato": "2026-10-25",
  "observacao": "Pediu orçamento de 2 geradores", "custom": { "frota": 12 },
  "preencher_cnpj": true, "forcar": false
}
```
Só `nome` (ou `empresa`) é obrigatório. Sem `etapa_id`/`funil_id` entra na 1ª etapa do funil padrão. Vendedor sem acesso a todos os leads sempre fica como responsável. `preencher_cnpj` completa só os campos vazios. `observacao`/`proximo_contato` criam a primeira interação (e o lembrete na agenda). Resposta `201` com a ficha.

### `PATCH /leads/{id}/etapa` — move no funil (`crm:write`)
`{ "etapa_id": "…", "motivo": "preco", "detalhe": "…" }` — `motivo` é obrigatório ao mover para uma etapa "Perdido" (e `detalhe` quando o motivo pede, ex.: concorrência).

### `POST /leads/{id}/interacoes` — registra conversa (`crm:write`)
JSON ou `multipart/form-data`:

| Campo | |
|---|---|
| `canal` | padrão `whatsapp` |
| `direcao` | `entrada` (recebida) ou `saida` (enviada) |
| `resumo` | texto da conversa (obrigatório quando não há áudio) |
| `transcricao` | texto do áudio, se a extensão tiver |
| `proximo_passo`, `proximo_contato` | próxima ação e data (vira lembrete) |
| `audio` | arquivo de áudio (guardado em disco privado; abre só por link temporário no painel) |

## Exemplo

```bash
curl -s https://app.oravel.com.br/api/crm/v1/contatos/buscar?telefone=19999990000 \
  -H "Authorization: Bearer $TOKEN" -H "Accept: application/json"
```
