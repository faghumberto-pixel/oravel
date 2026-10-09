#!/bin/bash
# Atualiza a senha do e-mail (MAIL_PASSWORD) em PRODUCAO sem a senha aparecer na tela,
# no historico do terminal, na lista de processos nem em nenhum arquivo local.
#
# Uso (no seu computador, na pasta do projeto):
#   bash scripts/atualizar-senha-email-prod.sh
#
# O que faz:
#   1. pede a senha NOVA do contato@oravel.com.br (digitacao oculta; digite duas vezes);
#   2. envia a senha pelo SSH (entrada padrao, nao pela linha de comando);
#   3. na VM: faz copia de seguranca do .env, troca so a linha MAIL_PASSWORD, limpa o cache
#      de configuracao e TESTA o login no servidor de e-mail (nao envia nenhum e-mail);
#   4. se o teste falhar, volta o .env anterior sozinho.
#
# Opcional: informe um e-mail para receber uma mensagem de teste de verdade:
#   bash scripts/atualizar-senha-email-prod.sh seu.email@exemplo.com
set -euo pipefail

VM_INSTANCE="oravel-prod-v2"
VM_ZONE="southamerica-east1-c"
PROD_PATH="/var/www/oravel"
DESTINO_TESTE="${1:-}"

read -rsp "Nova senha do e-mail (nao aparece na tela): " SENHA; echo
read -rsp "Digite de novo para confirmar: " SENHA2; echo
if [[ -z "$SENHA" || "$SENHA" != "$SENHA2" ]]; then
    echo "As senhas nao conferem ou estao vazias. Nada foi alterado." >&2
    exit 1
fi
unset SENHA2

# Script que roda na VM. Recebe a senha na primeira linha da entrada padrao.
REMOTO=$(cat <<'REMOTE'
set -euo pipefail
cd "$PROD_PATH"
IFS= read -r NOVA_SENHA
[[ -n "$NOVA_SENHA" ]] || { echo "senha vazia"; exit 1; }

BACKUP=".env.bak.mail.$(date +%Y%m%d%H%M%S)"
cp .env "$BACKUP"

NOVA_SENHA="$NOVA_SENHA" python3 - <<'PY'
import os, re
nova = os.environ["NOVA_SENHA"]
# aspas duplas protegem espacos, # e $ ; escapa \ " e $
valor = '"' + nova.replace('\\', '\\\\').replace('"', '\\"').replace('$', '\\$') + '"'
texto = open('.env', encoding='utf-8').read()
linha = 'MAIL_PASSWORD=' + valor
if re.search(r'^MAIL_PASSWORD=.*$', texto, re.M):
    texto = re.sub(r'^MAIL_PASSWORD=.*$', lambda m: linha, texto, count=1, flags=re.M)
else:
    texto = texto.rstrip('\n') + '\n' + linha + '\n'
open('.env', 'w', encoding='utf-8').write(texto)
PY
unset NOVA_SENHA

php artisan config:clear >/dev/null 2>&1 || true
php artisan config:cache >/dev/null 2>&1 || true

echo "Testando o login no servidor de e-mail..."
RESULTADO=$(php artisan tinker --execute='try { $t = Illuminate\Support\Facades\Mail::mailer("smtp")->getSymfonyTransport(); $t->start(); $t->stop(); echo "OK"; } catch (Throwable $e) { echo "FALHOU: ".substr($e->getMessage(), 0, 160); }' 2>&1 </dev/null | tail -1)

if [[ "$RESULTADO" == "OK" ]]; then
    echo "Login no servidor de e-mail: OK. Senha atualizada."
    if [[ -n "${DESTINO_TESTE:-}" ]]; then
        php artisan tinker --execute="Illuminate\\Support\\Facades\\Mail::raw('Teste de envio da plataforma Oravel. Se voce recebeu, o e-mail esta funcionando.', fn(\$m) => \$m->to('$DESTINO_TESTE')->subject('Teste Oravel')); echo 'Enviado para $DESTINO_TESTE';" </dev/null 2>&1 | tail -1
    fi
    exit 0
fi

echo "O servidor recusou a senha ($RESULTADO). Voltando o .env anterior..."
cp "$BACKUP" .env
php artisan config:clear >/dev/null 2>&1 || true
php artisan config:cache >/dev/null 2>&1 || true
exit 2
REMOTE
)

printf '%s\n' "$SENHA" | gcloud compute ssh "oravel@${VM_INSTANCE}" --zone="$VM_ZONE" --tunnel-through-iap --command \
    "sudo -u www-data env HOME=/tmp PROD_PATH='$PROD_PATH' DESTINO_TESTE='$DESTINO_TESTE' bash -c $(printf '%q' "$REMOTO")"
unset SENHA
