@extends('layouts.app-signature')

@section('content')
<div class="success-container">
    <div class="success-card">
        <div class="success-icon">✅</div>
        <h1 class="success-title">Documento Assinado com Sucesso!</h1>
        <p class="success-subtitle">
            Sua assinatura eletrônica foi registrada e o documento agora possui validade jurídica.
        </p>

        <!-- Detalhes da Assinatura -->
        <div class="success-details">
            <div class="detail-row">
                <span class="detail-label">Signatário:</span>
                <span class="detail-value">{{ $signature->signer_name }}</span>
            </div>

            @if ($signature->signer_document)
                <div class="detail-row">
                    <span class="detail-label">Documento:</span>
                    <span class="detail-value">{{ $signature->signer_document }}</span>
                </div>
            @endif

            <div class="detail-row">
                <span class="detail-label">Data de Assinatura:</span>
                <span class="detail-value">{{ $signature->signed_at->format('d/m/Y H:i:s') }}</span>
            </div>

            @if ($signature->ip_address)
                <div class="detail-row">
                    <span class="detail-label">Endereço IP:</span>
                    <span class="detail-value">{{ $signature->ip_address }}</span>
                </div>
            @endif

            @if ($signature->geolocation)
                <div class="detail-row">
                    <span class="detail-label">Localização:</span>
                    <span class="detail-value">
                        Latitude: {{ $signature->geolocation['lat'] ?? 'N/A' }},
                        Longitude: {{ $signature->geolocation['lng'] ?? 'N/A' }}
                    </span>
                </div>
            @endif

            @if ($signature->document_hash)
                <div class="detail-row">
                    <span class="detail-label">Hash SHA-256:</span>
                    <span class="detail-value document-hash">{{ $signature->document_hash }}</span>
                </div>
            @endif
        </div>

        <!-- Ações -->
        <div class="success-actions">
            <a href="{{ route('signature.download', ['token' => $signature->token]) }}" class="btn btn-primary">
                📥 Baixar PDF Assinado
            </a>
            <a href="/" class="btn btn-secondary">
                Voltar ao Início
            </a>
        </div>

        <!-- Informações adicionais -->
        <div class="success-footer">
            <p>
                <strong>Próximos passos:</strong>
                Você receberá uma cópia deste documento no e-mail informado.
                Guarde este recibo para sua segurança.
            </p>
        </div>
    </div>
</div>
@endsection

@section('styles')
<style>
    body {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }

    .success-container {
        width: 100%;
        max-width: 600px;
    }

    .success-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        padding: 40px;
        text-align: center;
        animation: slideUp 0.5s ease-out;
    }

    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @keyframes pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.1); }
    }

    .success-icon {
        font-size: 64px;
        margin-bottom: 20px;
        animation: pulse 2s ease-in-out;
    }

    .success-title {
        font-size: 28px;
        font-weight: 700;
        color: #333;
        margin-bottom: 10px;
    }

    .success-subtitle {
        font-size: 16px;
        color: #666;
        margin-bottom: 30px;
    }

    .success-details {
        background: #f9f9f9;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 30px;
        text-align: left;
    }

    .detail-row {
        display: flex;
        justify-content: space-between;
        padding: 10px 0;
        font-size: 14px;
        border-bottom: 1px solid #e0e0e0;
    }

    .detail-row:last-child {
        border-bottom: none;
    }

    .detail-label {
        font-weight: 600;
        color: #555;
    }

    .detail-value {
        color: #333;
        text-align: right;
        flex: 1;
        margin-left: 20px;
        word-break: break-word;
    }

    .document-hash {
        font-family: 'Courier New', monospace;
        font-size: 12px;
    }

    .success-actions {
        display: flex;
        gap: 10px;
        margin-bottom: 30px;
    }

    .btn {
        flex: 1;
        padding: 12px 20px;
        border: none;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }

    .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
    }

    .btn-secondary {
        background: #e0e0e0;
        color: #333;
    }

    .btn-secondary:hover {
        background: #d0d0d0;
    }

    .success-footer {
        padding-top: 20px;
        border-top: 1px solid #e0e0e0;
        color: #666;
        font-size: 14px;
    }

    @media (max-width: 480px) {
        .success-card {
            padding: 20px;
        }

        .success-icon {
            font-size: 48px;
        }

        .success-title {
            font-size: 24px;
        }

        .success-actions {
            flex-direction: column;
        }
    }
</style>
@endsection
