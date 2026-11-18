#!/bin/bash
# Deploy diario do oravel.org: publica os posts agendados cuja data chegou
# (o sitemap e o feed so listam posts com data <= hoje, avaliada no build).
# Roda pelo cron todo dia as 08:05 (America/Sao_Paulo). Log: logs/deploy-diario.log
export PATH=/usr/local/bin:/usr/bin:/bin
cd "$(dirname "$0")/.." || exit 1
{
  echo "=== $(date '+%F %T') ==="
  npm run deploy 2>&1 | tail -15
  echo "exit=${PIPESTATUS[0]}"
} >> logs/deploy-diario.log 2>&1
