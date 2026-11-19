#!/bin/bash
# Compara o sitemap no ar com o esperado (paginas fixas + posts com data <= agora).
# Log: logs/conferencia-sitemap.log
export PATH=/usr/local/bin:/usr/bin:/bin
cd "$(dirname "$0")/.." || exit 1
mkdir -p logs
AGORA=$(date +%s)
FIXAS=$(grep -c '<loc>' <(sed '/{% for post/,$d' src/sitemap.njk))
POSTS=$(ls src/content/*/*.md | grep -v "/pages/" | while read -r f; do
  ts=$(basename "$f" | grep -oE '^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}-[0-9]{2}:[0-9]{2}')
  e=$(date -d "$ts" +%s 2>/dev/null); [ -n "$e" ] && [ "$e" -le "$AGORA" ] && echo x
done | wc -l)
FERR=$(ls src/ferramentas/*/index.njk | wc -l)
ESPERADO=$((FIXAS+POSTS+FERR))
NOAR=$(curl -s --max-time 30 https://oravel.org/sitemap.xml | grep -c '<loc>')
FUTURAS=$(curl -s --max-time 30 https://oravel.org/sitemap.xml | grep -o '<loc>[^<]*' | grep -oE '/2026-[0-9]{2}-[0-9]{2}T[0-9:]{8}-03:00' | while read -r t; do e=$(date -d "${t:1}" +%s); [ "$e" -gt "$AGORA" ] && echo x; done | wc -l)
[ "$NOAR" -eq "$ESPERADO" ] && [ "$FUTURAS" -eq 0 ] && R=OK || R=DIVERGENTE
echo "$(date '+%F %T') no_ar=$NOAR esperado=$ESPERADO urls_futuras_no_ar=$FUTURAS => $R" >> logs/conferencia-sitemap.log
