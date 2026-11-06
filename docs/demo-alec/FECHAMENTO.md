# Demo ALEC (segunda, 09/11): custo de manutenção e localização dos equipamentos

Promessa única da demo: **"saber onde está cada equipamento e quanto ele custa de manutenção"**.
Branch: `feat/demo-alec-custos-mapa`. Nada disto foi para produção até alguém mandar o deploy.

## (a) O que foi feito e o que ficou de fora

### Feito
1. **Visão de custo por equipamento ao longo do tempo** — tela *Inteligência de Ativos → Custo de Manutenção*:
   ranking de quem mais gasta (período de 6/12/18/24 meses), barrinhas dos últimos 12 meses, acumulado,
   % do valor de aquisição, tendência (últimos 6 meses × 6 anteriores), retrabalho e sinalização
   **Considerar trocar ou vender** (≥ 60% do valor), **Revisar** (≥ 40%) ou **Custo sob controle**
   (limiares em `config/custo_manutencao.php`). Cards clicáveis filtram por sinalização.
2. **Histórico e custo acumulado direto no cadastro do equipamento** — aba **Custos e histórico**:
   sinalização, números (acumulado, % do valor, custo médio por OS, retrabalho, mão de obra/material/logística),
   **linha do tempo** (locações × ordens de serviço no mesmo eixo, ponto maior = OS mais cara, anel vermelho = retrabalho),
   receita × manutenção, custo mês a mês com acumulado e histórico de OS com link.
3. **Mapa de localização** — *Inteligência de Ativos → Localização dos Equipamentos*: posição pelo **rastreador (Traccar)**;
   sem aparelho, pela **obra do contrato ativo**; depois cadastro, unidade e cliente. Pino colorido por status, popup com
   cliente/obra/contrato/custo acumulado/sinalização, busca e filtros, camadas ruas/satélite/escuro/OSM, atualização a cada 30 s.
   Na demo o rastreador é **simulado** (2 equipamentos em deslocamento com trilha, 3 parados), com aviso na tela.
4. **Ligado por cliente, pela Central** — as telas **não aparecem para ninguém por padrão**:
   - *Central → Empresas → Inteligência de Ativos* (ação por cliente; coluna "Inteligência de Ativos" na lista). Se o cliente
     está num contrato compartilhado (vitrine do pacote), ele ganha uma **cópia exclusiva do contrato**: os outros clientes não mudam.
   - *Central → Contratos → grupo "Inteligência de Ativos"* (liga para todos os clientes daquele contrato; o grupo avisa disso).
   - Fora de qualquer pacote vendável. A troca de produtos (*Alterar produtos*) **preserva** a chave.
5. **Dashboard "Inteligência de Ativos — custo e localização"** (sistema de dashboards personalizados): **modelo da plataforma**, visível em
   *Central → Dashboards personalizados*, para atribuir a qualquer cliente que tenha as chaves do contrato (sem elas, nenhum bloco aparece).
   Na Locadora Demo ele é atribuído **só a ela, como tela Início** (o login da demo já cai nele). Formatos visuais: KPIs com minigráfico e
   comparação de tendência, **mapa** com os equipamentos por situação, **mapa de calor** equipamento × mês, ranking com **linhas de limiar**
   (40% revisar / 60% trocar ou vender), **anéis** (situação da frota e para onde vai o dinheiro), tabela de retorno (receita − manutenção) e alertas.
   Comando: `php artisan dashboards:inteligencia-ativos [--empresa=slug] [--inicio]` (cria/atualiza o modelo; repetir não duplica).
6. **Dados de demo** — empresa fictícia **Locadora Demo** (`tenants.is_demo = true`): 25 equipamentos, 8 clientes e 10 obras
   em cidades de SP, 18 meses de OS (≈190) e contratos (≈130), 4 problemáticos. Comandos `demo:alec-criar` e `demo:alec-apagar`.
7. **Testes** novos para tudo acima (cálculo, sinalização, visibilidade por contrato, 403 sem chave, isolamento do tenant demo,
   ativação por cliente, preservação na troca de produtos, mapa/rastreador simulado e real mockado).

### Ficou de fora (de propósito)
- **Rastreador real na demo**: não há aparelho; a posição é simulada (marcada como tal). Nos clientes, usa o Traccar quando há vínculo
  (hoje o vínculo GPS ↔ ativo é feito pela tela da Frota, que só oferece veículos).
- **Preço/cobrança** das telas: ligar não gera cobrança. Se virar produto pago, usar o fluxo de Complementos (decisão comercial).
- Exportar/imprimir o ranking, alertas automáticos (e-mail/WhatsApp) de equipamento caro, layout mobile ajustado.
- O "Mapa de Equipamentos" antigo (Ativos) continua como era (marcadores pelo cadastro/cliente).
- **Provedor de mapas**: camadas Esri/OSM sem chave, ok para a demo. Para uso comercial em escala, contratar provedor com chave (MapTiler, Stadia ou Google).
- Suíte completa de testes não foi rodada de ponta a ponta; rodei as áreas tocadas. Falhas que **já existiam na `main`** (conferidas lá):
  `StockMovement` (constante), `UserResourceEmployeeSync` (3), `WhatsAppChat` (2), `TenantResourceOverrideWarning`, impressão de Planos Preventivos,
  `ReservasUrgentes` (2), `HourMeterAccessPoints` (menu), `CrmPacotes`/`PacotesCheckout` (2), `Asaas/Webhook`, `PermissionAudit` (2).

## Para subir (só com ordem explícita)
1. **Backup do banco de produção** (o `deploy.sh` salva só código/config). Checar disco antes (incidente de disco cheio):
   `gcloud compute ssh oravel@oravel-prod-v2 --zone=southamerica-east1-c --tunnel-through-iap --command='df -h /var/backups && sudo /var/www/oravel/scripts/backup-prod-database.sh && ls -lh /var/backups/db | tail -3'`
   (dump `pg_dump -Fc` em `/var/backups/db`, registrado em *Central → Backups do Banco*).
2. PR → merge → `./deploy.sh` (a migration é só `tenants.is_demo`, aditiva; o build `public/build` vai commitado).
3. Conferir: `php artisan migrate:status | grep is_demo`, login 200, e que um cliente **sem** a chave não vê o grupo *Inteligência de Ativos* (403 nas URLs).
4. **Dashboard na Central** (a flag `DASHBOARDS_PERSONALIZADOS` já está ligada em produção): `sudo -u www-data php artisan dashboards:inteligencia-ativos`
   cria o modelo (aparece em *Central → Dashboards personalizados*). A demo já existente recebe o dashboard com
   `... dashboards:inteligencia-ativos --empresa=locadora-demo --inicio` (sem recriar a demo).
5. **Criar a demo na manhã da segunda** (as datas são relativas a "hoje"):
   `sudo -u www-data php artisan demo:alec-criar --senha='<defina uma senha forte>'` — login `demo@locadorademo.test`.
6. Treinar o roteiro uma vez, logado como administrador da demo.

## (b) Roteiro de cliques — 10 minutos

Antes: internet funcionando (os mapas carregam da internet), navegador em tela cheia, login `demo@locadorademo.test`.
Valores abaixo são os da demo recém-criada; podem variar um pouco no dia.

| Tempo | Onde | O que clicar | O que dizer |
|---|---|---|---|
| 0:00 | **Início** (o dashboard já abre) | Mostrar os 4 números do topo, o **mapa**, o **mapa de calor** e o ranking com as linhas de 40% e 60%. | "Duas perguntas que todo locador faz: onde está meu equipamento e quanto ele me custa de manutenção. Esta é a tela da direção, em uma olhada." |
| 0:30 | **Inteligência de Ativos → Localização dos Equipamentos** | Esperar o mapa carregar (25 equipamentos). | "Cada pino é um equipamento; a cor é o status (locado, disponível, em manutenção)." |
| 1:15 | Mapa | Clicar no filtro **Locado (13)**, depois **Com rastreador (5)**. Abrir o popup de **PA-01 (Articulada 15m)** (pino com pulso). | "Os com rastreador mostram velocidade, última atualização e o caminho percorrido. Os demais usam o endereço da obra do contrato ativo — sem hardware." |
| 2:30 | Mapa | Botão de camadas (canto superior direito) → **Satélite**. Voltar para **Ruas**. | "Vista de rua ou satélite, como no Google." |
| 3:00 | Mapa | Buscar **PT-02**, clicar no item da lista: popup com cliente, obra, contrato e **manutenção acumulada + sinalização**. | "Já aqui eu vejo quanto esse equipamento custou e se vale a pena continuar com ele." |
| 4:00 | **Inteligência de Ativos → Custo de Manutenção** | Olhar os 4 cards; clicar em **Considerar trocar ou vender**. | "3 equipamentos já custaram mais de 60% do que valem. 1 pede revisão." |
| 5:00 | Custo de Manutenção | Clicar em **Todos os equipamentos**; ordenar por **Maior % do valor do equipamento**. Mostrar **C-02 (91%)**, **G-03 (65%)**, **PT-02 (63%)**, **PA-02 (56%)**. | "O compressor C-02 já custou R$ 38 mil de R$ 42 mil. A coluna de tendência mostra se piora." |
| 6:00 | Custo de Manutenção | Passar o mouse nas barrinhas dos 12 meses de **G-03**. | "Mês a mês: dá para ver quando a manutenção disparou." |
| 6:30 | Custo de Manutenção | Clicar no nome **PT-02 (Plataforma Tesoura 10m)** → abre o cadastro; clicar na aba **Custos e histórico**. | "Tudo do equipamento em uma tela, sem caçar ordem de serviço." |
| 7:00 | Aba **Custos e histórico** | Mostrar o banner vermelho, a **linha do tempo** (barras = locações, bolas = OS) e os 4 cards: **locado 89% do tempo, receita × manutenção**. | "Rendeu R$ 53 mil e custou R$ 49 mil de manutenção: sobrou quase nada. Hora de decidir." |
| 8:30 | Aba | Rolar até **Custo mês a mês** e **Histórico de ordens de serviço**; clicar numa OS. | "Cada custo tem a OS por trás, com técnico e descrição." |
| 9:15 | — | Voltar ao mapa e deixar aberto. | "Isso é ligado por cliente, direto na Central: cada locadora escolhe ter ou não." |
| 9:45 | — | Perguntas. | Se perguntarem do GPS: "Usamos o rastreador (Traccar) onde houver; sem aparelho, a obra do contrato." |

## (c) O que NÃO clicar na demo
- **Não entrar na Central / painel de super admin**: a "Locadora Demo" aparece misturada com clientes reais. Ligar/desligar módulos mostra-se só em conversa, não na tela.
- **Não logar como super admin** (ele vê tudo e ignora a liberação por contrato): use `demo@locadorademo.test`.
- **Não clicar em Salvar alterações, Excluir, Imprimir etiqueta** no cadastro do equipamento (a demo é editável).
- **Não rolar o dashboard com o mouse sobre o mapa** (a roda do mouse não dá zoom ali, de propósito; use os botões + e −).
- **Não abrir outros módulos** (estoque, financeiro, RH, CRM, contratos assinados): o tenant demo só tem dados em ativos, clientes, contratos e OS; o resto aparece vazio.
- **Não usar o "Mapa de Equipamentos" antigo** (menu Ativos): ele posiciona pelo cadastro, não pela obra, e destoa do novo.
- **Não abrir o widget flutuante "Mensagens"** (chat) no canto da tela.
- **Não recarregar a página do mapa várias vezes seguidas** nem trocar de aba por muito tempo (o mapa precisa de internet; teste a rede antes).
- **Nunca rodar em produção**: `tenant:setup-demos` (**apaga todos os tenants**), `demo:popular-loc2`, `demo:seed-ativo-frota`, `demo:seed-historico-patrimonio`, `migrate:fresh`, `test`.
- Não prometer preço: ligar o módulo não cobra nada hoje.

## (d) Como reverter se algo der errado em produção
Do mais leve para o mais pesado:
1. **A demo está estranha** → `php artisan demo:alec-apagar --force` (remove só o tenant "Locadora Demo" e as linhas dele; recusa se o tenant não for marcado como demo) e `demo:alec-criar` de novo.
2. **Um cliente não deve ver as telas** → *Central → Empresas → Inteligência de Ativos*: desmarcar as duas. Imediato, sem deploy.
3. **As telas novas causam problema geral** → desligá-las nos contratos (item 2) já esconde menu e bloqueia as URLs (403); como estão desligadas por padrão, os clientes atuais não são afetados.
4. **Reverter o código** → `git revert -m 1 <commit do merge>` na `main` e `./deploy.sh` (restaura também o `public/build` anterior). Alternativa: restaurar o backup de código que o `deploy.sh` cria em `/var/backups/oravel_backup_<data>`.
5. **Migration** → `tenants.is_demo` é só uma coluna nova com default `false`; pode ficar. Se precisar, `php artisan migrate:rollback --step=1` (só se for a última migration aplicada).
6. **Último recurso, banco** → restaurar o dump feito antes do deploy (`/var/backups/db/oravel_db_<data>.dump`, `pg_restore -Fc`). Perde o que foi gravado depois do backup; usar só se o banco for afetado.

Nenhuma migration apaga ou altera coluna existente; o seeder só escreve no tenant demo (todas as tabelas filtradas por `tenant_id`).
