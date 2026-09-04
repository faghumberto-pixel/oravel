@extends('layouts.app-signature')

@section('content')
<div class="error-container">
    <div class="error-card">
        <div class="error-icon">❌</div>
        <h1 class="error-title">Ops! Algo deu errado</h1>
        <p class="error-message">{{ $message }}</p>

        <div class="error-suggestions">
            <h3>O que fazer?</h3>
            <ul>
                <li>✓ Verifique se o link está correto</li>
                <li>✓ Confirme se a assinatura não expirou</li>
                <li>✓ Tente novamente em alguns instantes</li>
                <li>✓ Entre em contato com o suporte se o problema persistir</li>
            </ul>
        </div>

        <div class="error-actions">
            <a href="/" class="btn btn-primary">
                Voltar ao Início
            </a>
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

    .error-container {
        width: 100%;
        max-width: 600px;
    }

    .error-card {
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

    .error-icon {
        font-size: 64px;
        margin-bottom: 20px;
        animation: shake 0.5s;
    }

    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-10px); }
        75% { transform: translateX(10px); }
    }

    .error-title {
        font-size: 28px;
        font-weight: 700;
        color: #dc3545;
        margin-bottom: 10px;
    }

    .error-message {
        font-size: 16px;
        color: #666;
        margin-bottom: 30px;
        padding: 15px;
        background: #f8d7da;
        border: 1px solid #f5c6cb;
        border-radius: 6px;
        color: #721c24;
    }

    .error-suggestions {
        background: #f9f9f9;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 30px;
        text-align: left;
    }

    .error-suggestions h3 {
        font-size: 14px;
        font-weight: 600;
        color: #333;
        margin-bottom: 12px;
    }

    .error-suggestions ul {
        list-style: none;
        font-size: 14px;
        color: #666;
    }

    .error-suggestions li {
        padding: 6px 0;
    }

    .error-actions {
        display: flex;
        gap: 10px;
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
    }

    .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
    }

    @media (max-width: 480px) {
        .error-card {
            padding: 20px;
        }

        .error-icon {
            font-size: 48px;
        }

        .error-title {
            font-size: 24px;
        }
    }
</style>
@endsection
