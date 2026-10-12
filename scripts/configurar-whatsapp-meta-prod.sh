#!/bin/bash
# Grava em PRODUCAO as chaves do app da Oravel na Meta (WhatsApp) sem o segredo aparecer na tela,
# no historico do terminal, na lista de processos nem em nenhum arquivo local.
#
# Uso (no seu computador, na pasta do projeto):
#   bash scripts/configurar-whatsapp-meta-prod.sh
#
# O que faz:
#   1. pede o ID do app (aparece na tela) e o SEGREDO do app (digitacao oculta; digite duas vezes);
#   2. envia pelo SSH (entrada padrao, nao pela linha de comando);
#   3. na VM: faz copia de seguranca do .env, grava WHATSAPP_APP_ID e WHATSAPP_APP_SECRET, cria o
#      WHATSAPP_WEBHOOK_TOKEN (token de verificacao do webhook) se ainda nao existir, atualiza o cache
#      e CONFERE o ID + segredo junto a Meta (nao envia mensagem nenhuma);
#   4. se a Meta recusar, volta o .env anterior sozinho.
# No fim mostra a URL do webhook e o token de verificacao (esse token nao e segredo: voce o cola no painel da Meta).
set -euo pipefail

VM_INSTANCE="oravel-prod-v2"
VM_ZONE="southamerica-east1-c"
PROD_PATH="/var/www/oravel"

read -rp "ID do app da Meta (Configuracoes do app > Basico): " APP_ID
read -rsp "Segredo do app (nao aparece na tela): " SEGREDO; echo
read -rsp "Digite de novo para confirmar: " SEGREDO2; echo
if [[ -z "$APP_ID" || -z "$SEGREDO" || "$SEGREDO" != "$SEGREDO2" ]]; then
    echo "Dados vazios ou o segredo nao confere. Nada foi alterado." >&2
    exit 1
fi
unset SEGREDO2

REMOTO=$(cat <<'REMOTE'
set -euo pipefail
cd "$PROD_PATH"
IFS= read -r APP_ID
IFS= read -r SEGREDO
[[ -n "$APP_ID" && -n "$SEGREDO" ]] || { echo "dados vazios"; exit 1; }

BACKUP=".env.bak.whatsapp.$(date +%Y%m%d%H%M%S)"
cp .env "$BACKUP"

APP_ID="$APP_ID" SEGREDO="$SEGREDO" python3 - <<'PY'
import os, re, secrets
texto = open('.env', encoding='utf-8').read()

def definir(chave, valor, so_se_ausente=False):
    global texto
    linha = chave + '=' + valor
    if re.search(r'^' + chave + r'=.*$', texto, re.M):
        if so_se_ausente and re.search(r'^' + chave + r'=\S+', texto, re.M):
            return
        texto = re.sub(r'^' + chave + r'=.*$', lambda m: linha, texto, count=1, flags=re.M)
    else:
        texto = texto.rstrip('\n') + '\n' + linha + '\n'

definir('WHATSAPP_APP_ID', os.environ['APP_ID'])
definir('WHATSAPP_APP_SECRET', os.environ['SEGREDO'])
definir('WHATSAPP_WEBHOOK_TOKEN', secrets.token_urlsafe(24), so_se_ausente=True)
open('.env', 'w', encoding='utf-8').write(texto)
PY
unset SEGREDO

php artisan config:clear >/dev/null 2>&1 || true
php artisan config:cache >/dev/null 2>&1 || true

echo "Conferindo o ID e o segredo junto a Meta..."
RESULTADO=$(php artisan tinker --execute='$r = Illuminate\Support\Facades\Http::timeout(20)->get("https://graph.facebook.com/oauth/access_token", ["client_id" => config("services.whatsapp.app_id"), "client_secret" => config("services.whatsapp.app_secret"), "grant_type" => "client_credentials"]); echo $r->successful() && $r->json("access_token") ? "OK" : "RECUSADO: ".substr((string) $r->json("error.message"), 0, 120);' 2>&1 </dev/null | tail -1)

if [[ "$RESULTADO" == "OK" ]]; then
    echo "A Meta aceitou o ID e o segredo."
    echo
    echo "Cadastre no painel da Meta (WhatsApp > Configuracao > Webhook):"
    echo "  URL de retorno de chamada: https://app.oravel.com.br/api/webhooks/whatsapp-meta"
    echo "  Token de verificacao:      $(php artisan tinker --execute='echo config("services.whatsapp.webhook_token");' </dev/null 2>&1 | tail -1)"
    echo "  Campo a assinar: messages"
    exit 0
fi

echo "A Meta recusou ($RESULTADO). Voltando o .env anterior..."
cp "$BACKUP" .env
php artisan config:clear >/dev/null 2>&1 || true
php artisan config:cache >/dev/null 2>&1 || true
exit 2
REMOTE
)

printf '%s\n%s\n' "$APP_ID" "$SEGREDO" | gcloud compute ssh "oravel@${VM_INSTANCE}" --zone="$VM_ZONE" --tunnel-through-iap --command \
    "sudo -u www-data env HOME=/tmp PROD_PATH='$PROD_PATH' bash -c $(printf '%q' "$REMOTO")"
