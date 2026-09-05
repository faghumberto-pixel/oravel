#!/bin/bash

##############################################################################
# PRE-DEPLOY VALIDATION SCRIPT
#
# Verifica se o código está pronto para deploy em PRODUÇÃO antes de fazer push
# para o Git. Detecta problemas de Vite assets, configuração de ambiente, etc.
#
# Uso: bash scripts/pre-deploy-validation.sh
##############################################################################

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}PRE-DEPLOY VALIDATION${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""

ERRORS=0
WARNINGS=0

# ============================================================================
# 1. Verificar se os assets foram buildados (manifest.json existe)
# ============================================================================
echo -e "${YELLOW}[1/5]${NC} Verificando se manifest.json existe..."
if [[ ! -f "public/build/manifest.json" ]]; then
    echo -e "${RED}❌ ERRO CRÍTICO:${NC} public/build/manifest.json não encontrado!"
    echo "    Execute: npm run build"
    ((ERRORS++))
else
    echo -e "${GREEN}✅ manifest.json encontrado${NC}"
fi

# ============================================================================
# 2. Verificar se há assets em public/build/assets
# ============================================================================
echo -e "${YELLOW}[2/5]${NC} Verificando se assets existem..."
if [[ ! -d "public/build/assets" ]] || [[ ! "$(ls -A public/build/assets 2>/dev/null)" ]]; then
    echo -e "${RED}❌ ERRO CRÍTICO:${NC} public/build/assets vazio ou não existe!"
    echo "    Execute: npm run build"
    ((ERRORS++))
else
    ASSET_COUNT=$(find public/build/assets -type f | wc -l)
    echo -e "${GREEN}✅ ${ASSET_COUNT} assets encontrados${NC}"
fi

# ============================================================================
# 3. Validar manifest.json é um JSON válido
# ============================================================================
echo -e "${YELLOW}[3/5]${NC} Validando manifest.json é JSON válido..."
if ! php -r "json_decode(file_get_contents('public/build/manifest.json'), true); echo 'OK';" > /dev/null 2>&1; then
    echo -e "${RED}❌ ERRO CRÍTICO:${NC} manifest.json não é um JSON válido!"
    ((ERRORS++))
else
    echo -e "${GREEN}✅ manifest.json é um JSON válido${NC}"
fi

# ============================================================================
# 4. Verificar .env.production (deve existir com APP_ENV=production)
# ============================================================================
echo -e "${YELLOW}[4/5]${NC} Verificando configuração de ambiente..."
if [[ ! -f ".env.production" ]]; then
    echo -e "${YELLOW}⚠️  AVISO:${NC} .env.production não encontrado"
    echo "    Este arquivo será usado em PROD via deploy.sh"
    echo "    (não é obrigatório estar no git, mas confirme que existe no servidor)"
else
    if ! grep -q "APP_ENV=production" .env.production; then
        echo -e "${RED}❌ ERRO:${NC} .env.production não tem APP_ENV=production!"
        ((ERRORS++))
    else
        echo -e "${GREEN}✅ APP_ENV=production configurado em .env.production${NC}"
    fi

    if grep -q "APP_DEBUG=true" .env.production; then
        echo -e "${RED}❌ ERRO:${NC} APP_DEBUG=true em .env.production (deve ser false)!"
        ((ERRORS++))
    else
        echo -e "${GREEN}✅ APP_DEBUG não está true em .env.production${NC}"
    fi
fi

# ============================================================================
# 5. Verificar se há um health-check.sh disponível
# ============================================================================
echo -e "${YELLOW}[5/5]${NC} Verificando se health-check-post-deploy.sh existe..."
if [[ ! -f "scripts/health-check-post-deploy.sh" ]]; then
    echo -e "${YELLOW}⚠️  AVISO:${NC} scripts/health-check-post-deploy.sh não encontrado"
    echo "    (será criado durante a configuração inicial)"
else
    echo -e "${GREEN}✅ health-check-post-deploy.sh disponível${NC}"
fi

# ============================================================================
# Resultado final
# ============================================================================
echo ""
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

if [[ $ERRORS -eq 0 ]]; then
    echo -e "${GREEN}✅ VALIDAÇÃO PASSOU - SEGURO FAZER DEPLOY${NC}"
    echo ""
    exit 0
else
    echo -e "${RED}❌ ${ERRORS} ERRO(S) ENCONTRADO(S)${NC}"
    if [[ $WARNINGS -gt 0 ]]; then
        echo -e "${YELLOW}⚠️  ${WARNINGS} AVISO(S)${NC}"
    fi
    echo ""
    echo "Corrija os erros acima antes de fazer deploy."
    exit 1
fi
