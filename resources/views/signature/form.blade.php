@extends('layouts.app-signature')

@section('content')
<div class="signature-container">
    <div class="signature-wrapper">
        <!-- Header -->
        <div class="signature-header">
            <h1 class="signature-title">Assinatura Eletrônica</h1>
            <p class="signature-subtitle">
                {{ $document::class === 'App\\Models\\Contract' ? 'Contrato de Locação' : 'Ordem de Serviço' }}
            </p>
        </div>

        <!-- Dados do Documento -->
        <div class="signature-document-info">
            @if ($document::class === 'App\\Models\\Contract')
                <div class="info-row">
                    <span class="info-label">Contrato:</span>
                    <span class="info-value">{{ $document->contract_number }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Cliente:</span>
                    <span class="info-value">{{ $document->client?->name ?? 'N/A' }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Equipamento:</span>
                    <span class="info-value">{{ $document->asset?->name ?? 'N/A' }}</span>
                </div>
            @else
                <div class="info-row">
                    <span class="info-label">Ordem de Serviço:</span>
                    <span class="info-value">{{ $document->os_number ?? $document->id }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Equipamento:</span>
                    <span class="info-value">{{ $document->asset?->name ?? 'N/A' }}</span>
                </div>
            @endif

            @if ($signature->expires_at)
                <div class="info-row">
                    <span class="info-label">Válido até:</span>
                    <span class="info-value">{{ $signature->expires_at->format('d/m/Y') }}</span>
                </div>
            @endif
        </div>

        <!-- Form -->
        <form id="signatureForm" class="signature-form">
            @csrf

            <!-- Nome do Signatário -->
            <div class="form-group">
                <label for="signer_name" class="form-label">
                    Nome Completo do Signatário <span class="required">*</span>
                </label>
                <input
                    type="text"
                    id="signer_name"
                    name="signer_name"
                    class="form-control"
                    placeholder="Digite seu nome completo"
                    value="{{ $signature->signer_name }}"
                    required
                >
                <small class="form-text">Nome que aparecerá na assinatura</small>
            </div>

            <!-- CPF/CNPJ (Opcional) -->
            <div class="form-group">
                <label for="signer_document" class="form-label">
                    CPF / CNPJ <span class="optional">(opcional)</span>
                </label>
                <input
                    type="text"
                    id="signer_document"
                    name="signer_document"
                    class="form-control"
                    placeholder="000.000.000-00"
                    value="{{ $signature->signer_document ?? '' }}"
                    maxlength="20"
                >
            </div>

            <!-- Email (Opcional) -->
            <div class="form-group">
                <label for="signer_email" class="form-label">
                    E-mail <span class="optional">(opcional)</span>
                </label>
                <input
                    type="email"
                    id="signer_email"
                    name="signer_email"
                    class="form-control"
                    placeholder="seu@email.com"
                    value="{{ $signature->signer_email ?? '' }}"
                >
            </div>

            <!-- Telefone (Opcional) -->
            <div class="form-group">
                <label for="signer_phone" class="form-label">
                    Telefone <span class="optional">(opcional)</span>
                </label>
                <input
                    type="tel"
                    id="signer_phone"
                    name="signer_phone"
                    class="form-control"
                    placeholder="(11) 99999-9999"
                    value="{{ $signature->signer_phone ?? '' }}"
                >
            </div>

            <!-- Modo de Assinatura -->
            <div class="signature-mode-selector">
                <div class="mode-tabs">
                    <button
                        type="button"
                        class="mode-tab active"
                        data-mode="draw"
                        aria-label="Desenhar assinatura"
                    >
                        <span class="mode-icon">✍️</span>
                        <span class="mode-text">Desenhar</span>
                    </button>
                    <button
                        type="button"
                        class="mode-tab"
                        data-mode="type"
                        aria-label="Digitar nome"
                    >
                        <span class="mode-icon">⌨️</span>
                        <span class="mode-text">Digitar</span>
                    </button>
                </div>
            </div>

            <!-- Canvas para desenho da assinatura -->
            <div id="drawMode" class="signature-mode active">
                <label class="form-label">
                    Assine aqui <span class="required">*</span>
                </label>
                <div class="canvas-wrapper">
                    <canvas
                        id="signatureCanvas"
                        class="signature-canvas"
                        width="100"
                        height="100"
                    ></canvas>
                    <div class="canvas-hint">Clique ou toque aqui para assinar</div>
                </div>
                <button type="button" id="clearCanvas" class="btn btn-secondary btn-sm">
                    Limpar
                </button>
            </div>

            <!-- Modo de digitação -->
            <div id="typeMode" class="signature-mode">
                <label for="typedSignature" class="form-label">
                    Assinatura (Digitada) <span class="required">*</span>
                </label>
                <input
                    type="text"
                    id="typedSignature"
                    name="typed_signature"
                    class="form-control signature-type-input"
                    placeholder="Digite seu nome completo"
                >
                <small class="form-text">
                    A assinatura será renderizada em estilo cursivo
                </small>
                <div id="signaturePreview" class="signature-preview"></div>
            </div>

            <!-- Campos ocultos -->
            <input type="hidden" id="signatureBase64" name="signature_base64">
            <input type="hidden" id="geolocation" name="geolocation">

            <!-- Termos de Aceite -->
            <div class="form-group form-check">
                <input
                    type="checkbox"
                    id="acceptTerms"
                    class="form-check-input"
                    required
                >
                <label for="acceptTerms" class="form-check-label">
                    Eu concordo que minha assinatura eletrônica tem validade jurídica
                    e confirmo a autenticidade deste documento.
                </label>
            </div>

            <!-- Botões de Ação -->
            <div class="form-actions">
                <button
                    type="submit"
                    id="submitBtn"
                    class="btn btn-primary btn-lg"
                    disabled
                >
                    <span class="btn-text">Assinar Documento</span>
                    <span class="btn-spinner" style="display: none;">
                        <i class="spinner"></i>
                    </span>
                </button>
            </div>

            <!-- Mensagem de erro -->
            <div id="errorMessage" class="alert alert-danger" style="display: none;"></div>

            <!-- Mensagem de sucesso -->
            <div id="successMessage" class="alert alert-success" style="display: none;"></div>
        </form>

        <!-- Informações de Privacidade -->
        <div class="signature-privacy">
            <small>
                <strong>Privacidade:</strong> Seus dados e assinatura serão criptografados e armazenados
                com segurança. A localização é capturada apenas se você permitir.
            </small>
        </div>
    </div>
</div>

@endsection

@section('styles')
<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }

    .signature-container {
        width: 100%;
        max-width: 600px;
    }

    .signature-wrapper {
        background: white;
        border-radius: 12px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        padding: 40px;
        animation: slideUp 0.3s ease-out;
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

    .signature-header {
        text-align: center;
        margin-bottom: 30px;
        border-bottom: 2px solid #f0f0f0;
        padding-bottom: 20px;
    }

    .signature-title {
        font-size: 28px;
        font-weight: 700;
        color: #333;
        margin-bottom: 8px;
    }

    .signature-subtitle {
        font-size: 16px;
        color: #666;
    }

    .signature-document-info {
        background: #f9f9f9;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 30px;
    }

    .info-row {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
        font-size: 14px;
    }

    .info-row:not(:last-child) {
        border-bottom: 1px solid #e0e0e0;
    }

    .info-label {
        font-weight: 600;
        color: #555;
    }

    .info-value {
        color: #333;
        text-align: right;
        flex: 1;
        margin-left: 20px;
    }

    .signature-form {
        margin-top: 30px;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-label {
        display: block;
        font-weight: 600;
        margin-bottom: 8px;
        color: #333;
        font-size: 14px;
    }

    .required {
        color: #dc3545;
    }

    .optional {
        color: #999;
        font-weight: normal;
    }

    .form-control {
        width: 100%;
        padding: 12px 14px;
        border: 2px solid #e0e0e0;
        border-radius: 6px;
        font-size: 14px;
        transition: border-color 0.3s;
        font-family: inherit;
    }

    .form-control:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .form-text {
        display: block;
        margin-top: 6px;
        font-size: 12px;
        color: #999;
    }

    /* Seletor de Modo */
    .signature-mode-selector {
        margin: 30px 0;
    }

    .mode-tabs {
        display: flex;
        gap: 10px;
        border-bottom: 2px solid #e0e0e0;
    }

    .mode-tab {
        flex: 1;
        padding: 12px 20px;
        background: none;
        border: none;
        border-bottom: 3px solid transparent;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
        color: #999;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }

    .mode-tab.active {
        color: #667eea;
        border-bottom-color: #667eea;
    }

    .mode-icon {
        font-size: 18px;
    }

    .mode-text {
        display: none;
    }

    @media (min-width: 480px) {
        .mode-text {
            display: inline;
        }
    }

    /* Canvas de Assinatura */
    .signature-mode {
        display: none;
    }

    .signature-mode.active {
        display: block;
    }

    .canvas-wrapper {
        position: relative;
        border: 2px dashed #667eea;
        border-radius: 8px;
        background: #fafafa;
        margin-bottom: 15px;
        overflow: hidden;
        touch-action: none;
    }

    .signature-canvas {
        display: block;
        width: 100% !important;
        height: auto !important;
        cursor: crosshair;
        background: white;
    }

    .canvas-hint {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        color: #bbb;
        font-size: 14px;
        pointer-events: none;
    }

    .canvas-hint.hidden {
        display: none;
    }

    .btn {
        padding: 10px 20px;
        border: none;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        font-family: inherit;
    }

    .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }

    .btn-primary:hover:not(:disabled) {
        transform: translateY(-2px);
        box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
    }

    .btn-primary:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .btn-secondary {
        background: #e0e0e0;
        color: #333;
    }

    .btn-secondary:hover {
        background: #d0d0d0;
    }

    .btn-lg {
        width: 100%;
        padding: 14px 20px;
        font-size: 16px;
    }

    .btn-sm {
        padding: 8px 12px;
        font-size: 12px;
    }

    /* Modo Digitação */
    .signature-type-input {
        font-size: 16px;
        font-family: 'Georgia', serif;
        letter-spacing: 1px;
    }

    .signature-preview {
        margin-top: 20px;
        padding: 30px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        background: #fafafa;
        text-align: center;
        min-height: 80px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .signature-preview-text {
        font-family: 'Great Vibes', cursive;
        font-size: 36px;
        color: #333;
    }

    /* Checkboxes */
    .form-check {
        display: flex;
        align-items: flex-start;
        gap: 10px;
    }

    .form-check-input {
        width: 18px;
        height: 18px;
        cursor: pointer;
        margin-top: 2px;
        flex-shrink: 0;
    }

    .form-check-label {
        font-size: 14px;
        color: #666;
        cursor: pointer;
    }

    /* Botões de Ação */
    .form-actions {
        margin-top: 30px;
    }

    .btn-text {
        display: inline-block;
    }

    .btn-spinner {
        display: inline-block;
        width: 16px;
        height: 16px;
        border: 2px solid rgba(255, 255, 255, 0.3);
        border-top: 2px solid white;
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
    }

    @keyframes spin {
        to { transform: rotate(360deg); }
    }

    /* Alertas -->
    .alert {
        padding: 12px 16px;
        border-radius: 6px;
        margin-bottom: 20px;
        font-size: 14px;
    }

    .alert-danger {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }

    .alert-success {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }

    /* Privacidade -->
    .signature-privacy {
        margin-top: 30px;
        padding-top: 20px;
        border-top: 1px solid #e0e0e0;
        text-align: center;
        color: #999;
    }

    /* Responsivo -->
    @media (max-width: 480px) {
        .signature-wrapper {
            padding: 20px;
        }

        .signature-title {
            font-size: 24px;
        }

        .form-control {
            padding: 10px 12px;
            font-size: 16px; /* previne zoom no iOS */
        }

        .mode-tab {
            flex-direction: column;
            padding: 10px 16px;
        }

        .signature-preview-text {
            font-size: 28px;
        }
    }
</style>

{{-- Google Fonts para fontes cursivas --}}
<link href="https://fonts.googleapis.com/css2?family=Great+Vibes&display=swap" rel="stylesheet">
@endsection

@section('scripts')
<script>
    // ===== INICIALIZAÇÃO DO CANVAS =====
    const canvas = document.getElementById('signatureCanvas');
    const ctx = canvas.getContext('2d');
    const clearBtn = document.getElementById('clearCanvas');
    const signatureForm = document.getElementById('signatureForm');
    const submitBtn = document.getElementById('submitBtn');
    const signatureBase64Input = document.getElementById('signatureBase64');
    const geolocationInput = document.getElementById('geolocation');
    const acceptTermsCheckbox = document.getElementById('acceptTerms');
    const errorDiv = document.getElementById('errorMessage');
    const successDiv = document.getElementById('successMessage');
    const canvasHint = document.querySelector('.canvas-hint');

    let isDrawing = false;
    let hasSignature = false;

    // Redimensiona canvas para device pixel ratio (melhor qualidade)
    function resizeCanvas() {
        const rect = canvas.getBoundingClientRect();
        const dpr = window.devicePixelRatio || 1;

        canvas.width = rect.width * dpr;
        canvas.height = rect.height * dpr;

        ctx.scale(dpr, dpr);
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.lineWidth = 2;
        ctx.strokeStyle = '#333';
    }

    resizeCanvas();
    window.addEventListener('resize', resizeCanvas);

    // ===== EVENTOS DE DESENHO =====
    function startDrawing(e) {
        isDrawing = true;
        const { x, y } = getCoordinates(e);
        ctx.beginPath();
        ctx.moveTo(x, y);

        if (canvasHint) {
            canvasHint.classList.add('hidden');
        }
    }

    function draw(e) {
        if (!isDrawing) return;

        const { x, y } = getCoordinates(e);
        ctx.lineTo(x, y);
        ctx.stroke();
        hasSignature = true;
        updateSubmitButton();
    }

    function stopDrawing() {
        isDrawing = false;
        ctx.closePath();
    }

    function getCoordinates(e) {
        const rect = canvas.getBoundingClientRect();
        const clientX = e.type.includes('touch') ? e.touches[0].clientX : e.clientX;
        const clientY = e.type.includes('touch') ? e.touches[0].clientY : e.clientY;

        return {
            x: clientX - rect.left,
            y: clientY - rect.top,
        };
    }

    function clearCanvas() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        hasSignature = false;
        canvasHint?.classList.remove('hidden');
        updateSubmitButton();
    }

    // Event listeners para mouse
    canvas.addEventListener('mousedown', startDrawing);
    canvas.addEventListener('mousemove', draw);
    canvas.addEventListener('mouseup', stopDrawing);
    canvas.addEventListener('mouseout', stopDrawing);

    // Event listeners para touch
    canvas.addEventListener('touchstart', (e) => {
        e.preventDefault();
        startDrawing(e);
    });
    canvas.addEventListener('touchmove', (e) => {
        e.preventDefault();
        draw(e);
    });
    canvas.addEventListener('touchend', (e) => {
        e.preventDefault();
        stopDrawing();
    });

    clearBtn.addEventListener('click', clearCanvas);

    // ===== MODO DE DIGITAÇÃO =====
    const modeTabs = document.querySelectorAll('.mode-tab');
    const modeContainers = document.querySelectorAll('.signature-mode');
    const typedSignatureInput = document.getElementById('typedSignature');
    const signaturePreview = document.getElementById('signaturePreview');

    modeTabs.forEach((tab) => {
        tab.addEventListener('click', (e) => {
            e.preventDefault();
            const mode = tab.dataset.mode;

            // Remove active de todos
            modeTabs.forEach((t) => t.classList.remove('active'));
            modeContainers.forEach((c) => c.classList.remove('active'));

            // Adiciona active ao selecionado
            tab.classList.add('active');
            document.getElementById(`${mode}Mode`).classList.add('active');

            hasSignature = false;
            updateSubmitButton();
        });
    });

    // Previewde assinatura digitada
    typedSignatureInput.addEventListener('input', (e) => {
        const text = e.target.value;

        if (text) {
            signaturePreview.innerHTML = `<div class="signature-preview-text">${text}</div>`;
            hasSignature = true;
        } else {
            signaturePreview.innerHTML = '';
            hasSignature = false;
        }

        updateSubmitButton();
    });

    // ===== ATUALIZAR BOTÃO SUBMIT =====
    function updateSubmitButton() {
        const isValid = hasSignature && acceptTermsCheckbox.checked;
        submitBtn.disabled = !isValid;
    }

    acceptTermsCheckbox.addEventListener('change', updateSubmitButton);

    // ===== CAPTURA DE GEOLOCALIZAÇÃO =====
    function captureGeolocation() {
        return new Promise((resolve) => {
            if (!navigator.geolocation) {
                resolve(null);
                return;
            }

            navigator.geolocation.getCurrentPosition(
                (position) => {
                    resolve({
                        lat: position.coords.latitude,
                        lng: position.coords.longitude,
                        accuracy: position.coords.accuracy,
                    });
                },
                () => {
                    // Erro ao capturar - continua mesmo assim
                    resolve(null);
                },
                { timeout: 5000 }
            );
        });
    }

    // ===== SUBMISSÃO DO FORMULÁRIO =====
    signatureForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        errorDiv.style.display = 'none';
        submitBtn.disabled = true;
        submitBtn.querySelector('.btn-spinner').style.display = 'inline-block';
        submitBtn.querySelector('.btn-text').style.display = 'none';

        try {
            // Captura geolocalização
            const geolocation = await captureGeolocation();
            if (geolocation) {
                geolocationInput.value = JSON.stringify(geolocation);
            }

            // Gera assinatura em base64
            const mode = document.querySelector('.mode-tab.active').dataset.mode;

            if (mode === 'draw') {
                signatureBase64Input.value = canvas.toDataURL('image/png');
            } else {
                // Renderiza assinatura digitada em canvas temporário
                const tempCanvas = document.createElement('canvas');
                tempCanvas.width = 400;
                tempCanvas.height = 150;
                const tempCtx = tempCanvas.getContext('2d');
                tempCtx.font = 'italic 48px "Great Vibes", cursive';
                tempCtx.fillStyle = '#333';
                tempCtx.fillText(typedSignatureInput.value, 20, 100);
                signatureBase64Input.value = tempCanvas.toDataURL('image/png');
            }

            // Submete formulário
            const response = await fetch(`/assinatura/{{ $token }}/assinar`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': document.querySelector('input[name="_token"]').value,
                },
                body: JSON.stringify({
                    signature_base64: signatureBase64Input.value,
                    signer_name: document.getElementById('signer_name').value,
                    signer_document: document.getElementById('signer_document').value || null,
                    signer_email: document.getElementById('signer_email').value || null,
                    signer_phone: document.getElementById('signer_phone').value || null,
                    geolocation: geolocationInput.value ? JSON.parse(geolocationInput.value) : null,
                }),
            });

            const result = await response.json();

            if (!response.ok) {
                throw new Error(result.message || 'Erro ao assinar documento');
            }

            // Sucesso!
            successDiv.textContent = result.message;
            successDiv.style.display = 'block';

            // Redireciona após 2 segundos
            setTimeout(() => {
                window.location.href = result.redirect;
            }, 2000);

        } catch (error) {
            errorDiv.textContent = error.message || 'Erro ao processar assinatura';
            errorDiv.style.display = 'block';
        } finally {
            submitBtn.disabled = false;
            submitBtn.querySelector('.btn-spinner').style.display = 'none';
            submitBtn.querySelector('.btn-text').style.display = 'inline-block';
            updateSubmitButton();
        }
    });

    // Inicializa estado do botão
    updateSubmitButton();
</script>
@endsection
