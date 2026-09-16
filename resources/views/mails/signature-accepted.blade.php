@component('mail::message')
# Assinatura Eletrônica Confirmada

Olá **{{ $signature->name }}**,

Confirmamos que sua assinatura eletrônica foi registrada com sucesso em nossos sistemas.

## Detalhes da Assinatura

**Empresa:** {{ $signature->company }}
**Email:** {{ $signature->email }}
**Responsável:** {{ $signature->name }}
**Data & Hora:** {{ $signature->signed_at->format('d/m/Y H:i:s') }} (UTC)
**Hash (SHA-256):** `{{ $signature->hash }}`

## Documentos Aceitos

✓ SLA (Acordo de Nível de Serviço)
✓ DPA (Contrato de Processamento de Dados)
✓ Conformidade com LGPD

## Próximos Passos

Sua empresa agora pode acessar a plataforma Oravel. Se tiver dúvidas ou precisar de suporte, entre em contato:

@component('mail::button', ['url' => 'mailto:suporte@oravel.com.br', 'color' => 'primary'])
Contatar Suporte
@endcomponent

---

**Oravel — ERP para Locadoras**
[suporte@oravel.com.br](mailto:suporte@oravel.com.br)
+55 19 99933-2615

Este é um email automático. Não responda diretamente; use os contatos acima.
@endcomponent
