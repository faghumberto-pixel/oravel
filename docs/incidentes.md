# Incidentes de produção

Registro de incidentes de infraestrutura/produção resolvidos, para consulta rápida quando algo parecido acontecer de novo.

## app.oravel.com.br fora do ar (HTTP 522)

**Data:** 17/09/2026

**Sintoma:** app.oravel.com.br retornando erro 522 (Cloudflare não conseguia conectar na origem).

**Diagnóstico:**
- Nginx e PHP-FPM 8.4 estavam rodando normalmente na VM oravel-prod-v2 (systemctl status confirmou ambos "active/running")
- IP externo da VM confirmado: 35.199.70.237
- Causa raiz: a instância oravel-prod-v2 (VM nova, substituta da oravel-prod original) não possuía nenhuma tag de rede associada (`curl metadata/v1/instance/tags?recursive=true` retornou `[]`)
- As regras de firewall `default-allow-http` e `default-allow-https` do projeto exigem a tag de destino `http-server`/`https-server` para liberar as portas 80/443
- Sem essas tags, todo o tráfego HTTP/HTTPS de entrada era bloqueado pelo firewall da VPC, mesmo com os serviços web funcionando internamente

**Resolução:**
- No Console GCP, em Compute Engine > oravel-prod-v2 > Editar > seção Firewalls, marcadas as opções "Allow HTTP traffic" e "Allow HTTPS traffic" (isso aplica automaticamente as tags de rede corretas à instância)
- Salvo, e o acesso via app.oravel.com.br voltou a funcionar

**Lição aprendida:** ao provisionar uma nova VM de produção (troca de instância, ex. v2), sempre conferir se as tags de rede http-server/https-server estão habilitadas antes de apontar o DNS/Cloudflare para ela. Adicionar esse item ao checklist de deploy/migração de VM.
