# Módulo de Assinatura — Testes em PROD

## ⚠️ Aviso Importante

**PROD tem cliente real. Este seeder é 100% seguro pois:**
- ✅ Usa tenants EXISTENTES (não cria novos)
- ✅ Usa clientes EXISTENTES (não os modifica)
- ✅ Apenas ADICIONA contratos com prefix `CTR-TEST-PROD-`
- ✅ Pode ser revertido facilmente com SQL
- ✅ Pede confirmação antes de continuar

---

## Opção 1: Após Deploy Automático

O seeder pode ser adicionado ao `deploy.sh` para rodar automaticamente:

```bash
# Adicionar ao deploy.sh após o "php artisan migrate --force"
echo "yes" | php artisan db:seed --class=ContractSignatureProdSeeder
```

---

## Opção 2: Manual via SSH

### Conectar à PROD

```bash
gcloud compute ssh --zone=southamerica-east1-c --tunnel-through-iap \
  oravel-prod-new --project=YOUR_PROJECT
```

### Executar Seeder

```bash
cd /var/www/oravel

# Com confirmação interativa
php artisan db:seed --class=ContractSignatureProdSeeder

# Ou confirmação automática (via pipe)
echo "yes" | php artisan db:seed --class=ContractSignatureProdSeeder
```

---

## Dados que Serão Criados

### Contrato 1 — Assinatura Pendente
```
Número:      CTR-TEST-PROD-{RANDOM}
Status:      Ativo (pending)
Signatário:  Signatário Teste PROD
Email:       teste.prod@oravel.com.br
Válido até:  +30 dias
```

**Token para testar:**
```
http://app.oravel.com.br/assinatura/{TOKEN}
```

### Contrato 2 — Assinatura Assinada
```
Número:      CTR-TEST-PROD-{RANDOM}
Status:      Ativo (signed)
Signatário:  Signatário Teste PROD
Assinado em: -7 dias
IP:          192.168.1.100
```

---

## Rollback (Se Necessário)

Se precisar remover os dados de teste:

### Via SSH

```bash
mysql -u root -p oravel_prod << 'EOF'
-- Remover assinaturas de teste
DELETE FROM document_signatures 
WHERE signable_type = 'App\\Models\\Contract' 
AND signable_id IN (
  SELECT id FROM contracts 
  WHERE contract_number LIKE 'CTR-TEST-PROD-%'
);

-- Remover contratos de teste
DELETE FROM contracts 
WHERE contract_number LIKE 'CTR-TEST-PROD-%';
EOF
```

### Verificar Rollback

```bash
php artisan tinker

# Verificar se foram removidos
$count = \App\Models\Contract::where('contract_number', 'LIKE', 'CTR-TEST-PROD-%')->count();
echo "Contratos de teste restantes: " . $count;
```

---

## Testes Após Seeder

### 1. Acessar Formulário de Assinatura

```
http://app.oravel.com.br/assinatura/{TOKEN}
```

Você deve ver:
- ✓ Nome do contrato
- ✓ Nome do cliente
- ✓ Canvas para assinatura
- ✓ Campos de preenchimento

### 2. Testar Assinatura

- Preencher nome
- Desenhar assinatura (ou digitar)
- Submeter
- Ver página de sucesso

### 3. Baixar PDF Assinado

Verificar:
- ✓ PDF foi gerado
- ✓ Página de auditoria está presente
- ✓ Metadados capturados (IP, geolocation, hash)

---

## Monitoring

### Verificar Assinaturas em PROD

```bash
php artisan tinker

# Listar assinaturas de teste
$sigs = \App\Models\DocumentSignature::whereHas('signable', function($q) {
  $q->where('contract_number', 'LIKE', 'CTR-TEST-PROD-%');
})->get();

foreach ($sigs as $sig) {
  echo "Token: {$sig->token}\n";
  echo "Status: {$sig->status}\n";
  echo "Contrato: {$sig->signable->contract_number}\n\n";
}
```

### Logs de Erro

```bash
tail -f /var/www/oravel/storage/logs/laravel.log | grep signature
```

---

## Checklist

- [ ] Deploy executado com sucesso
- [ ] Seeder executado com confirmação
- [ ] Nenhum erro no terminal
- [ ] Contratos visíveis no database
- [ ] Formulário acessível via token
- [ ] PDF gerado com sucesso
- [ ] Rollback realizado (se necessário)

---

## Contatos & Suporte

**Arquivo do Seeder:** `database/seeders/ContractSignatureProdSeeder.php`  
**Guia Completo:** `SIGNATURE_MODULE_GUIDE.md`  
**Deploy Script:** `deploy.sh`

**Qualquer erro:** Executar rollback e investigar logs em `/var/www/oravel/storage/logs/`
