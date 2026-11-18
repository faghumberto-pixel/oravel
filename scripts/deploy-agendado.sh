#!/bin/bash
# Publica posts agendados no horario: roda a cada 15 min (cron) e so faz deploy
# se algum post tem data/hora entre o ultimo deploy agendado e agora.
# Se o computador ficou desligado, o proximo ciclo recupera os posts atrasados.
# Estado: logs/ultimo-deploy-epoch. Log: logs/deploy-agendado.log
export PATH=/usr/local/bin:/usr/bin:/bin
cd "$(dirname "$0")/.." || exit 1
mkdir -p logs
ESTADO=logs/ultimo-deploy-epoch
AGORA=$(date +%s)
[ -f "$ESTADO" ] || { echo "$AGORA" > "$ESTADO"; exit 0; }
ULTIMO=$(cat "$ESTADO")

# epoch de cada post, lido do prefixo ISO do nome do arquivo
PENDENTES=$(ls src/content/*/*.md 2>/dev/null | while read -r f; do
  ts=$(basename "$f" | grep -oE '^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}-[0-9]{2}:[0-9]{2}')
  [ -n "$ts" ] && e=$(date -d "$ts" +%s 2>/dev/null) && [ "$e" -gt "$ULTIMO" ] && [ "$e" -le "$AGORA" ] && echo "$f"
done | wc -l)

[ "$PENDENTES" -gt 0 ] || exit 0

{
  echo "=== $(date '+%F %T') | $PENDENTES post(s) a publicar ==="
  if npm run deploy 2>&1 | tail -12 | tee /dev/stderr | grep -q 'Deploy concluido'; then
    echo "$AGORA" > "$ESTADO"; echo "ok"
  else
    echo "FALHOU - tenta de novo no proximo ciclo"
  fi
} >> logs/deploy-agendado.log 2>&1
