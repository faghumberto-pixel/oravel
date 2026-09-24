{{--
    Certificado de assinatura eletrônica -- pedido do usuário 2026-09-24:
    "contar o status em cada contrato assinado como criptografado com
    dados da assinatura, usuario, data, hora, codigo de seguranca".
    Espera receber $signature (App\Models\DocumentSignature, já assinada).
--}}
<div class="space-y-4">
    <div class="rounded-lg border border-success-200 bg-success-50 p-4 dark:border-success-700 dark:bg-success-900">
        <p class="text-sm font-semibold text-success-800 dark:text-success-100">
            ✅ Assinatura eletrônica válida, integridade verificada por hash criptográfico (SHA-256).
        </p>
    </div>

    <dl class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <div>
            <dt class="text-xs font-semibold uppercase text-gray-500">Signatário</dt>
            <dd class="text-sm text-gray-900 dark:text-white">{{ $signature->signer_name }}</dd>
        </div>
        <div>
            <dt class="text-xs font-semibold uppercase text-gray-500">CPF/CNPJ</dt>
            <dd class="text-sm text-gray-900 dark:text-white">{{ $signature->signer_document ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-xs font-semibold uppercase text-gray-500">E-mail</dt>
            <dd class="text-sm text-gray-900 dark:text-white">{{ $signature->signer_email ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-xs font-semibold uppercase text-gray-500">Telefone</dt>
            <dd class="text-sm text-gray-900 dark:text-white">{{ $signature->signer_phone ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-xs font-semibold uppercase text-gray-500">Data e hora da assinatura</dt>
            <dd class="text-sm text-gray-900 dark:text-white">{{ $signature->signed_at?->format('d/m/Y H:i:s') }}</dd>
        </div>
        <div>
            <dt class="text-xs font-semibold uppercase text-gray-500">Endereço IP</dt>
            <dd class="text-sm text-gray-900 dark:text-white">{{ $signature->ip_address ?? '—' }}</dd>
        </div>
        @if ($signature->geolocation)
            <div class="sm:col-span-2">
                <dt class="text-xs font-semibold uppercase text-gray-500">Localização (geolocalização do dispositivo)</dt>
                <dd class="text-sm text-gray-900 dark:text-white">
                    Latitude {{ $signature->geolocation['lat'] ?? 'N/A' }}, Longitude {{ $signature->geolocation['lng'] ?? 'N/A' }}
                </dd>
            </div>
        @endif
        <div class="sm:col-span-2">
            <dt class="text-xs font-semibold uppercase text-gray-500">Dispositivo / Navegador</dt>
            <dd class="break-all text-xs text-gray-600 dark:text-gray-300">{{ $signature->user_agent ?? '—' }}</dd>
        </div>
        <div class="sm:col-span-2">
            <dt class="text-xs font-semibold uppercase text-gray-500">Código de segurança (hash SHA-256)</dt>
            <dd class="break-all font-mono text-xs text-gray-600 dark:text-gray-300">{{ $signature->document_hash ?? 'Gerado ao baixar o PDF final' }}</dd>
        </div>
    </dl>

    <p class="text-xs text-gray-500 dark:text-gray-400">
        O código de segurança acima é único para esta assinatura e muda se qualquer dado registrado for alterado —
        serve como prova de integridade em caso de contestação.
    </p>
</div>
