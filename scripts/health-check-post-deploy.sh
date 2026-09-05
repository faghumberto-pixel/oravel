#!/bin/bash

##############################################################################
# HEALTH CHECK PÓS-DEPLOY
#
# Roda após deploy em PROD. Verifica:
# 1. Se o servidor está respondendo
# 2. Se os assets estão carregando corretamente
# 3. Se NÃO está tentando usar localhost:5173 (dev server)
# 4. Se há erros 500 ou other critical issues
#
# Retorna exit code 0 se OK, 1 se falhou
# Uso: bash scripts/health-check-post-deploy.sh [URL]
#      (default URL é https://app.oravel.com.br)
##############################################################################

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

URL="${1:-https://app.oravel.com.br}"
TIMEOUT=10
FAILURES=0
WARNINGS=0

echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}HEALTH CHECK PÓS-DEPLOY${NC}"
echo -e "${BLUE}URL: ${URL}${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""

# ============================================================================
# 1. Verificar se o servidor está respondendo (HTTP 200)
# ============================================================================
echo -e "${YELLOW}[1/4]${NC} Testando conectividade e resposta HTTP..."

HTTP_CODE=$(curl -s -o /tmp/health_check_login.html -w "%{http_code}" \
    --max-time $TIMEOUT \
    "$URL/login" 2>/dev/null || echo "000")

if [[ "$HTTP_CODE" == "200" ]]; then
    echo -e "${GREEN}✅ Servidor respondendo com HTTP 200${NC}"
elif [[ "$HTTP_CODE" == "302" ]] || [[ "$HTTP_CODE" == "301" ]]; then
    echo -e "${YELLOW}⚠️  Servidor respondendo com redirect ($HTTP_CODE)${NC}"
    # Tenta GET sem follow redirect pra ter o HTML
    curl -s -o /tmp/health_check_login.html \
        --max-time $TIMEOUT \
        -L "$URL/login" 2>/dev/null || true
    ((WARNINGS++))
elif [[ "$HTTP_CODE" == "000" ]]; then
    echo -e "${RED}❌ ERRO CRÍTICO: Servidor não está respondendo!${NC}"
    echo "    Verifique se a VM está online e o nginx está rodando."
    ((FAILURES++))
else
    echo -e "${RED}❌ ERRO: HTTP $HTTP_CODE (esperava 200)${NC}"
    ((FAILURES++))
fi

# ============================================================================
# 2. Verificar se está tentando usar localhost:5173 (Vite dev server)
# ============================================================================
echo -e "${YELLOW}[2/4]${NC} Verificando se há referências a localhost:5173..."

if [[ -f "/tmp/health_check_login.html" ]]; then
    if grep -q "localhost:5173\|@localhost:5173" /tmp/health_check_login.html 2>/dev/null; then
        echo -e "${RED}❌ ERRO CRÍTICO: Página está tentando carregar de localhost:5173!${NC}"
        echo "    Isso significa que APP_ENV=local ou APP_DEBUG=true em PROD."
        echo "    Verifique a configuração de ambiente em /var/www/oravel/.env"
        grep -o "localhost:5173[^\"]*" /tmp/health_check_login.html | head -3
        ((FAILURES++))
    else
        echo -e "${GREEN}✅ Nenhuma referência a localhost:5173 encontrada${NC}"
    fi
else
    echo -e "${YELLOW}⚠️  Não conseguiu salvar HTML para inspeção${NC}"
    ((WARNINGS++))
fi

# ============================================================================
# 3. Verificar se os assets estão carregando (não há erros 404)
# ============================================================================
echo -e "${YELLOW}[3/4]${NC} Verificando se assets estão carregando..."

# Extrai URLs de CSS/JS da página
if [[ -f "/tmp/health_check_login.html" ]]; then
    # Procura por linhas com src= ou href= que contêm /assets/
    ASSET_URLS=$(grep -oP '(?:src|href)="\K[^"]*(?=/assets/[^"]*)' /tmp/health_check_login.html 2>/dev/null || echo "")

    if [[ -z "$ASSET_URLS" ]]; then
        echo -e "${YELLOW}⚠️  Não conseguiu extrair URLs de assets para testar${NC}"
        ((WARNINGS++))
    else
        ASSET_COUNT=0
        FAILED_ASSETS=0

        while IFS= read -r ASSET_URL; do
            if [[ -z "$ASSET_URL" ]]; then
                continue
            fi

            # Se a URL é relativa, adiciona base URL
            if [[ "$ASSET_URL" != http* ]]; then
                ASSET_URL="$URL$ASSET_URL"
            fi

            ((ASSET_COUNT++))

            ASSET_HTTP=$(curl -s -o /dev/null -w "%{http_code}" \
                --max-time 5 \
                "$ASSET_URL" 2>/dev/null || echo "000")

            if [[ "$ASSET_HTTP" != "200" ]]; then
                echo -e "${RED}  ❌ Asset carregou com HTTP $ASSET_HTTP: ${ASSET_URL:0:70}${NC}"
                ((FAILED_ASSETS++))
            fi
        done <<< "$ASSET_URLS"

        if [[ $FAILED_ASSETS -eq 0 ]]; then
            echo -e "${GREEN}✅ ${ASSET_COUNT} assets testados, todos carregando OK${NC}"
        else
            echo -e "${RED}❌ ${FAILED_ASSETS}/${ASSET_COUNT} assets com erro${NC}"
            ((FAILURES++))
        fi
    fi
else
    echo -e "${YELLOW}⚠️  Não conseguiu carregar página para testar assets${NC}"
    ((WARNINGS++))
fi

# ============================================================================
# 4. Verificar erros HTTP 500
# ============================================================================
echo -e "${YELLOW}[4/4]${NC} Verificando se há erros 500..."

# Tenta algumas rotas críticas
CRITICAL_PATHS=(
    "/login"
    "/admin"
    "/api/health"
)

CRITICAL_FAILURES=0
for PATH in "${CRITICAL_PATHS[@]}"; do
    HTTP=$(curl -s -o /dev/null -w "%{http_code}" \
        --max-time $TIMEOUT \
        "$URL$PATH" 2>/dev/null || echo "000")

    if [[ "$HTTP" == "500" ]]; then
        echo -e "${RED}  ❌ Erro 500 em $PATH${NC}"
        ((CRITICAL_FAILURES++))
    elif [[ "$HTTP" == "000" ]]; then
        echo -e "${YELLOW}  ⚠️  Timeout em $PATH${NC}"
    fi
done

if [[ $CRITICAL_FAILURES -eq 0 ]]; then
    echo -e "${GREEN}✅ Nenhum erro 500 encontrado${NC}"
else
    echo -e "${RED}❌ ${CRITICAL_FAILURES} erro(s) 500 encontrado(s)${NC}"
    ((FAILURES++))
fi

# ============================================================================
# Resultado final
# ============================================================================
echo ""
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

if [[ $FAILURES -eq 0 ]]; then
    echo -e "${GREEN}✅ HEALTH CHECK PASSOU${NC}"
    if [[ $WARNINGS -gt 0 ]]; then
        echo -e "${YELLOW}⚠️  ${WARNINGS} aviso(s)${NC}"
    fi
    echo ""
    echo -e "${GREEN}DEPLOY ESTÁ OPERACIONAL!${NC}"
    exit 0
else
    echo -e "${RED}❌ ${FAILURES} FALHA(S) DETECTADA(S)${NC}"
    if [[ $WARNINGS -gt 0 ]]; then
        echo -e "${YELLOW}⚠️  ${WARNINGS} aviso(s)${NC}"
    fi
    echo ""
    echo -e "${RED}PROBLEMAS CRÍTICOS ENCONTRADOS - REVISAR ANTES DE CONFIRMAR${NC}"
    exit 1
fi
