<div style="page-break-before: always; padding: 40px; font-family: Arial, sans-serif; line-height: 1.6; background: #f5f5f5;">
    
    <!-- Cabeçalho da Página de Auditoria -->
    <div style="border-bottom: 2px solid #123028; padding-bottom: 20px; margin-bottom: 30px;">
        <h1 style="color: #123028; font-size: 24px; margin: 0 0 10px 0;">📋 PÁGINA DE AUDITORIA DE ASSINATURA</h1>
        <p style="color: #666; margin: 0; font-size: 13px;">Documento assinado eletronicamente conforme Lei nº 14.063/2020</p>
    </div>

    <!-- Informações do Signatário -->
    <div style="margin-bottom: 25px;">
        <h2 style="color: #123028; font-size: 14px; font-weight: bold; margin-bottom: 10px; border-bottom: 1px solid #ddd; padding-bottom: 8px;">INFORMAÇÕES DO SIGNATÁRIO</h2>
        
        <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
            <tr>
                <td style="padding: 8px 0; font-weight: bold; width: 35%; color: #333;">Nome Completo:</td>
                <td style="padding: 8px 0; color: #555;">{{ $signature->signer_name }}</td>
            </tr>
            @if($signature->signer_document)
            <tr>
                <td style="padding: 8px 0; font-weight: bold; color: #333;">CPF/CNPJ:</td>
                <td style="padding: 8px 0; color: #555;">{{ $signature->signer_document }}</td>
            </tr>
            @endif
            @if($signature->signer_email)
            <tr>
                <td style="padding: 8px 0; font-weight: bold; color: #333;">E-mail:</td>
                <td style="padding: 8px 0; color: #555;">{{ $signature->signer_email }}</td>
            </tr>
            @endif
        </table>
    </div>

    <!-- Data e Hora -->
    <div style="margin-bottom: 25px;">
        <h2 style="color: #123028; font-size: 14px; font-weight: bold; margin-bottom: 10px; border-bottom: 1px solid #ddd; padding-bottom: 8px;">DATA E HORA DA ASSINATURA</h2>
        
        <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
            <tr>
                <td style="padding: 8px 0; font-weight: bold; width: 35%; color: #333;">Data:</td>
                <td style="padding: 8px 0; color: #555;">{{ $signature->signed_at->format('d/m/Y') }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; font-weight: bold; color: #333;">Hora (UTC-3):</td>
                <td style="padding: 8px 0; color: #555;">{{ $signature->signed_at->timezone('America/Sao_Paulo')->format('H:i:s') }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; font-weight: bold; color: #333;">Timestamp UTC:</td>
                <td style="padding: 8px 0; color: #555;">{{ $signature->signed_at->format('c') }}</td>
            </tr>
        </table>
    </div>

    <!-- Localização e IP -->
    <div style="margin-bottom: 25px;">
        <h2 style="color: #123028; font-size: 14px; font-weight: bold; margin-bottom: 10px; border-bottom: 1px solid #ddd; padding-bottom: 8px;">LOCALIZAÇÃO E DISPOSITIVO</h2>
        
        <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
            <tr>
                <td style="padding: 8px 0; font-weight: bold; width: 35%; color: #333;">Endereço IP:</td>
                <td style="padding: 8px 0; color: #555;">{{ $signature->ip_address ?? 'Não capturado' }}</td>
            </tr>
            @if($signature->geolocation)
            <tr>
                <td style="padding: 8px 0; font-weight: bold; color: #333;">Latitude:</td>
                <td style="padding: 8px 0; color: #555;">{{ $signature->geolocation['lat'] ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; font-weight: bold; color: #333;">Longitude:</td>
                <td style="padding: 8px 0; color: #555;">{{ $signature->geolocation['lng'] ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; font-weight: bold; color: #333;">Precisão:</td>
                <td style="padding: 8px 0; color: #555;">{{ $signature->geolocation['accuracy'] ?? 'N/A' }} metros</td>
            </tr>
            @else
            <tr>
                <td style="padding: 8px 0; font-weight: bold; color: #333;">Geolocalização:</td>
                <td style="padding: 8px 0; color: #555;">Não disponível</td>
            </tr>
            @endif
        </table>
    </div>

    <!-- Hash SHA-256 -->
    <div style="margin-bottom: 25px;">
        <h2 style="color: #123028; font-size: 14px; font-weight: bold; margin-bottom: 10px; border-bottom: 1px solid #ddd; padding-bottom: 8px;">INTEGRIDADE DO DOCUMENTO</h2>
        
        <div style="font-size: 11px; color: #333;">
            <p style="margin: 0 0 8px 0; font-weight: bold;">Hash SHA-256 (verificação de integridade):</p>
            <div style="background: #fff; border: 1px solid #ddd; padding: 10px; border-radius: 4px; word-break: break-all; font-family: monospace; color: #555;">
                {{ $signature->document_hash }}
            </div>
        </div>
    </div>

    <!-- Validação Legal -->
    <div style="background: #e8f5e9; border-left: 4px solid #10b981; padding: 15px; margin-bottom: 25px; border-radius: 4px;">
        <p style="font-size: 12px; color: #1b5e20; margin: 0;">
            <strong>✓ VALIDADE JURÍDICA:</strong><br>
            Este documento foi assinado eletronicamente conforme a Lei nº 14.063/2020 (que regulamenta assinaturas eletrônicas no Brasil). O arquivo PDF é autêntico e a assinatura tem validade legal equivalente à assinatura de próprio punho.
        </p>
    </div>

    <!-- User Agent (Informações do Navegador) -->
    <div style="margin-bottom: 25px;">
        <h2 style="color: #123028; font-size: 14px; font-weight: bold; margin-bottom: 10px; border-bottom: 1px solid #ddd; padding-bottom: 8px;">INFORMAÇÕES DO NAVEGADOR</h2>
        
        <div style="font-size: 11px; background: #fafafa; padding: 10px; border-radius: 4px; word-break: break-all; color: #555;">
            {{ $signature->user_agent ?? 'Não disponível' }}
        </div>
    </div>

    <!-- Footer -->
    <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #123028; text-align: center; font-size: 11px; color: #999;">
        <p style="margin: 0;">
            <strong>Documento de Auditoria Automático</strong><br>
            Gerado por Oravel — Sistema de Assinatura Eletrônica<br>
            {{ now()->format('d/m/Y H:i:s') }}
        </p>
        <p style="margin: 8px 0 0 0;">
            Para verificar a autenticidade deste documento, consulte o comprovante de assinatura único.<br>
            <strong>ID de Verificação:</strong> {{ substr($signature->token, 0, 16) }}...
        </p>
    </div>

</div>
