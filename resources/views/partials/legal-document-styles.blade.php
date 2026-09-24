{{--
    CSS compartilhado entre as páginas de documento legal (SLA, LGPD,
    Licença de Uso) -- mesmo visual do painel de leitura do Contrato de
    Assinatura (resources/views/signature/form.blade.php), extraído aqui
    pra não triplicar o mesmo bloco de estilo nos 3 documentos.
--}}
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        background: #eef0f3;
        min-height: 100vh;
        padding: 40px 20px;
    }

    .legal-container { width: 100%; max-width: 820px; margin: 0 auto; }

    .legal-panel {
        background: white;
        border-radius: 12px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.12);
        padding: 40px;
    }

    .legal-print-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        margin-bottom: 24px;
        padding-bottom: 16px;
        border-bottom: 2px solid #f0f0f0;
    }

    .legal-print-bar .btn {
        padding: 8px 14px;
        border-radius: 6px;
        border: none;
        background: #e0e0e0;
        color: #333;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
    }

    .legal-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        border-bottom: 2px solid #E8541A;
        padding-bottom: 15px;
        margin-bottom: 25px;
        flex-wrap: wrap;
        gap: 12px;
    }

    .legal-logo { font-size: 26px; font-weight: 800; color: #111827; letter-spacing: -1px; }
    .legal-logo .accent { color: #E8541A; }

    .legal-doc-title { font-size: 14px; font-weight: bold; text-transform: uppercase; color: #111827; text-align: right; }
    .legal-doc-tag { font-family: 'Courier New', monospace; font-size: 13px; color: #E8541A; font-weight: bold; text-align: right; }

    .clause { margin-bottom: 14px; text-align: justify; font-size: 13px; line-height: 1.6; color: #374151; }
    .clause-title { font-weight: bold; color: #111827; display: block; margin-bottom: 2px; }

    @media (max-width: 640px) {
        .legal-panel { padding: 20px; }
    }

    @media print {
        .no-print { display: none !important; }
        body { background: white; padding: 0; }
        .legal-panel { box-shadow: none; padding: 0; }
    }
</style>
