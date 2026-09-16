@component('mail::message')
# Lembrete: Assinatura de Contrato de Serviço

Olá,

Sua empresa **{{ $tenant->name }}** ainda não assinou o contrato de serviço (SLA + LGPD) da Oravel.

## Informações

- **Prazo:** {{ $tenant->signature_required_by->format('d/m/Y') }}
- **Dias restantes:** {{ $daysLeft > 0 ? $daysLeft : 'VENCIDO' }}

## O que é necessário?

Assinar o contrato de serviço formaliza:
- ✅ Acordo de Nível de Serviço (99.9% uptime, backup automático)
- ✅ Conformidade com LGPD (proteção de dados, privacidade)
- ✅ Direitos e responsabilidades de ambas as partes

## Próximos Passos

@component('mail::button', ['url' => route('filament.admin.pages.contract-signature', [], false, 'admin'), 'color' => 'primary'])
Assinar Contrato Agora
@endcomponent

Se precisar de ajuda ou tiver dúvidas, entre em contato com nosso suporte.

---

**Oravel — ERP para Locadoras**
suporte@oravel.com.br
+55 19 99933-2615
@endcomponent
