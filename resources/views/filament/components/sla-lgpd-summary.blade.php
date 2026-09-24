<div class="space-y-4 rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
    <div>
        <h3 class="font-semibold text-gray-900 dark:text-white">📋 SLA - Acordo de Nível de Serviço</h3>
        <ul class="mt-2 space-y-1 text-sm text-gray-600 dark:text-gray-300">
            <li>✅ Uptime: 99.9% mensal garantido</li>
            <li>✅ RTO: Até 4 horas de recuperação</li>
            <li>✅ RPO: Máximo 1 hora de perda de dados</li>
            <li>✅ Backup: Automático a cada hora, 30 dias retenção</li>
            <li>✅ Suporte: 24/7 para casos críticos</li>
        </ul>
    </div>

    <div>
        <h3 class="font-semibold text-gray-900 dark:text-white">🔒 LGPD - Conformidade de Dados</h3>
        <ul class="mt-2 space-y-1 text-sm text-gray-600 dark:text-gray-300">
            <li>✅ Armazenamento: Brasil (Google Cloud, São Paulo)</li>
            <li>✅ Encriptação: AES-256 em repouso, TLS 1.3 em trânsito</li>
            <li>✅ Isolamento: Dados separados por tenant</li>
            <li>✅ Direitos: Acesso, correção, exclusão, portabilidade</li>
            <li>✅ Retenção: 30 dias após cancelamento → deleção permanente</li>
        </ul>
    </div>

    <div>
        <h3 class="font-semibold text-gray-900 dark:text-white">📄 Licença de Uso</h3>
        <ul class="mt-2 space-y-1 text-sm text-gray-600 dark:text-gray-300">
            <li>✅ Uso liberado enquanto o Contrato de Assinatura estiver ativo e em dia</li>
            <li>✅ Não exclusiva, intransferível — só pra uso próprio do Contratante</li>
            <li>✅ Seus dados continuam seus mesmo se a licença terminar</li>
        </ul>
    </div>

    <div class="rounded bg-blue-50 p-3 dark:bg-blue-900">
        <p class="text-xs text-blue-800 dark:text-blue-200">
            <strong>📄 Documentos Completos:</strong>
            <a href="{{ route('legal.sla') }}" target="_blank" class="underline">SLA</a>,
            <a href="{{ route('legal.lgpd') }}" target="_blank" class="underline">LGPD</a> e
            <a href="{{ route('legal.licenca-de-uso') }}" target="_blank" class="underline">Licença de Uso</a>
        </p>
    </div>
</div>
