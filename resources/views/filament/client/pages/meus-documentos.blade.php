@extends('filament-panels::page')

@section('content')
<div class="space-y-6">
    <div>
        <h2 class="text-2xl font-bold">Documentos</h2>
        <p class="text-sm text-gray-600">Seus contratos e documentos assinados</p>
    </div>

    @if($has_signed && $signature)
        <!-- Contrato Assinado -->
        <div class="rounded-lg border border-green-200 bg-green-50 p-6">
            <div class="flex items-start justify-between">
                <div class="flex-1">
                    <div class="flex items-center gap-3">
                        <svg class="h-6 w-6 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        <div>
                            <h3 class="font-bold text-green-900">SLA + LGPD Contrato de Serviço</h3>
                            <p class="text-sm text-green-700">✅ Assinado com sucesso</p>
                        </div>
                    </div>
                </div>
                <div class="text-right">
                    <p class="text-sm font-medium text-gray-900">
                        {{ $signature->signed_at->format('d/m/Y H:i') }}
                    </p>
                    <p class="text-xs text-gray-500">
                        por {{ $signature->name }}
                    </p>
                </div>
            </div>

            <!-- Detalhes da Assinatura -->
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div class="rounded bg-white/50 p-3">
                    <p class="text-xs font-semibold text-gray-600">RESPONSÁVEL</p>
                    <p class="font-medium text-gray-900">{{ $signature->name }}</p>
                </div>
                <div class="rounded bg-white/50 p-3">
                    <p class="text-xs font-semibold text-gray-600">EMAIL</p>
                    <p class="font-medium text-gray-900">{{ $signature->email }}</p>
                </div>
                <div class="rounded bg-white/50 p-3">
                    <p class="text-xs font-semibold text-gray-600">IP ORIGEM</p>
                    <p class="font-mono text-xs text-gray-900">{{ $signature->ip_origin }}</p>
                </div>
                <div class="rounded bg-white/50 p-3">
                    <p class="text-xs font-semibold text-gray-600">HASH (AUDITORIA)</p>
                    <p class="font-mono text-xs text-gray-900">{{ substr($signature->hash, 0, 16) }}...</p>
                </div>
            </div>

            <!-- Aviso de Auditoria -->
            <div class="mt-4 rounded bg-white p-3 text-xs text-gray-700">
                <p class="font-semibold">🔐 Segurança & Auditoria</p>
                <p class="mt-1">
                    Esta assinatura é criptografada e rastreada para fins de conformidade.
                    O hash SHA-256 garante a integridade do contrato.
                </p>
            </div>
        </div>

        <!-- Resumo dos Termos -->
        <div class="rounded-lg border border-gray-200 bg-white p-6">
            <h3 class="text-lg font-bold mb-4">Termos Acordados</h3>

            <div class="space-y-4">
                <div>
                    <h4 class="font-semibold text-gray-900">📊 SLA (Service Level Agreement)</h4>
                    <ul class="mt-2 space-y-1 text-sm text-gray-700">
                        <li>✅ Disponibilidade: 99.9% de uptime</li>
                        <li>✅ RTO (Recovery Time Objective): 4 horas</li>
                        <li>✅ RPO (Recovery Point Objective): 1 hora</li>
                        <li>✅ Backup diário de dados</li>
                        <li>✅ Suporte técnico 24/7</li>
                    </ul>
                </div>

                <div>
                    <h4 class="font-semibold text-gray-900">🔒 LGPD (Lei Geral de Proteção de Dados)</h4>
                    <ul class="mt-2 space-y-1 text-sm text-gray-700">
                        <li>✅ Dados criptografados em trânsito e em repouso</li>
                        <li>✅ Direito de acesso aos seus dados</li>
                        <li>✅ Direito de correção de dados incorretos</li>
                        <li>✅ Direito à exclusão (direito ao esquecimento)</li>
                        <li>✅ Auditoria e conformidade contínua</li>
                    </ul>
                </div>
            </div>
        </div>

    @else
        <!-- Não Assinado -->
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-6">
            <div class="flex items-start gap-4">
                <svg class="mt-1 h-6 w-6 text-amber-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
                <div class="flex-1">
                    <h3 class="font-bold text-amber-900">Contrato Pendente</h3>
                    <p class="text-sm text-amber-700 mt-1">
                        Você ainda não assinou o contrato de SLA + LGPD.
                        @if($signature_required_by)
                            Prazo: <strong>{{ $signature_required_by->format('d/m/Y') }}</strong>
                        @endif
                    </p>
                    <a href="/admin/contract-signature" class="mt-3 inline-flex items-center gap-2 rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700">
                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"/>
                        </svg>
                        Assinar Agora
                    </a>
                </div>
            </div>
        </div>

        <!-- Info sobre Assinatura -->
        <div class="rounded-lg border border-blue-200 bg-blue-50 p-6">
            <h3 class="text-lg font-bold text-blue-900 mb-4">ℹ️ Por que Assinar?</h3>
            <ul class="space-y-2 text-sm text-blue-900">
                <li class="flex gap-2">
                    <span class="flex-shrink-0">✓</span>
                    <span>Garante sua conformidade com a LGPD</span>
                </li>
                <li class="flex gap-2">
                    <span class="flex-shrink-0">✓</span>
                    <span>Estabelece níveis de serviço (SLA) 99.9% de disponibilidade</span>
                </li>
                <li class="flex gap-2">
                    <span class="flex-shrink-0">✓</span>
                    <span>Define direitos e responsabilidades de ambas as partes</span>
                </li>
                <li class="flex gap-2">
                    <span class="flex-shrink-0">✓</span>
                    <span>Fornece documentação legal para fins de auditoria</span>
                </li>
            </ul>
        </div>
    @endif
</div>
@endsection
