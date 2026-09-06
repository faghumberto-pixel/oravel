#!/usr/bin/env bash
# Wrapper para `composer dev` que garante a limpeza de public/hot mesmo se o
# processo for interrompido de forma abrupta (Ctrl+C, kill do concurrently
# quando o Vite falha, terminal fechado, etc).
#
# Por que isso existe: public/hot e' criado pelo Vite quando "npm run dev"
# sobe, e diz pro Laravel (@vite() no Blade) carregar TODO CSS/JS do dev
# server (127.0.0.1:5173) em vez dos arquivos ja compilados em
# public/build/assets/*. Se esse arquivo fica orfao no disco (processo
# morreu sem o Vite ter chance de limpar sozinho), toda pagina do app perde
# o CSS/JS silenciosamente -- sem erro visivel no HTML, so falha de conexao
# na URL do asset (curl mostra HTTP 000). Ja custou ~2h de debugging errado
# (2026-09-06, ver memoria "public/hot ghost file"), confundido com bug de
# Blade/Livewire porque o HTML em si sempre parece correto.
#
# `trap ... EXIT` garante que isso roda ao sair do script por QUALQUER
# motivo (Ctrl+C = SIGINT, `kill` normal = SIGTERM, ou saida normal) --
# so' nao cobre `kill -9` (SIGKILL nao pode ser interceptado por nenhum
# processo, e' o proprio SO que mata na hora).
cleanup() {
    if [ -f public/hot ]; then
        rm -f public/hot
        echo ""
        echo "🧹 public/hot removido (Vite dev server encerrado) -- assets voltam a vir de public/build/"
    fi
}
trap cleanup EXIT

npx concurrently -c "#93c5fd,#c4b5fd,#fb7185,#fdba74" \
    "php artisan serve" \
    "php artisan queue:listen --tries=1 --timeout=0" \
    "php artisan pail --timeout=0" \
    "npm run dev" \
    --names=server,queue,logs,vite --kill-others
