<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciador de Leads</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f5f5; }
        .container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #0b0f0d 0%, #1a1a18 100%); color: white; padding: 30px; border-radius: 8px; margin-bottom: 30px; }
        .header h1 { font-size: 28px; margin-bottom: 10px; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-top: 20px; }
        .stat { background: rgba(255,255,255,0.1); padding: 15px; border-radius: 6px; text-align: center; }
        .stat-value { font-size: 24px; font-weight: bold; }
        .stat-label { font-size: 12px; opacity: 0.8; margin-top: 5px; }
        .leads-table { width: 100%; background: white; border-collapse: collapse; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .leads-table th { background: #0b0f0d; color: white; padding: 15px; text-align: left; font-weight: 600; font-size: 13px; }
        .leads-table td { padding: 15px; border-bottom: 1px solid #eee; font-size: 13px; }
        .leads-table tr:hover { background: #f9f9f9; }
        .status-badge { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .status-novo { background: #fff3cd; color: #856404; }
        .status-contatado { background: #d1ecf1; color: #0c5460; }
        .status-interessado { background: #d4edda; color: #155724; }
        .status-perdido { background: #f8d7da; color: #721c24; }
        .product-badge { display: inline-block; padding: 4px 10px; border-radius: 4px; font-size: 11px; font-weight: 600; background: #ff6a1a; color: white; }
        .actions { display: flex; gap: 5px; }
        .btn { padding: 6px 10px; border: none; border-radius: 4px; cursor: pointer; font-size: 11px; font-weight: 600; text-decoration: none; display: inline-block; }
        .btn-email { background: #ff6a1a; color: white; }
        .btn-email:hover { background: #e55a0a; }
        .no-leads { text-align: center; padding: 60px; background: white; border-radius: 8px; }
        .no-leads p { color: #999; font-size: 14px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 Leads Capturados</h1>
            <div class="stats">
                <div class="stat">
                    <div class="stat-value">{{ $leads->count() }}</div>
                    <div class="stat-label">Total de Leads</div>
                </div>
                <div class="stat">
                    <div class="stat-value">{{ $leads->where('status', 'novo')->count() }}</div>
                    <div class="stat-label">Novos</div>
                </div>
                <div class="stat">
                    <div class="stat-value">{{ $leads->where('status', 'contatado')->count() }}</div>
                    <div class="stat-label">Contatados</div>
                </div>
                <div class="stat">
                    <div class="stat-value">{{ $leads->where('status', 'interessado')->count() }}</div>
                    <div class="stat-label">Interessados</div>
                </div>
            </div>
        </div>

        @if($leads->count() > 0)
            <table class="leads-table">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Email</th>
                        <th>Telefone</th>
                        <th>Empresa</th>
                        <th>Segmento</th>
                        <th>Produto</th>
                        <th>Status</th>
                        <th>Data</th>
                        <th>Ação</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($leads as $lead)
                        <tr>
                            <td><strong>{{ $lead->name }}</strong></td>
                            <td>{{ $lead->email }}</td>
                            <td>{{ $lead->phone }}</td>
                            <td>{{ $lead->company }}</td>
                            <td>{{ $lead->segment }}</td>
                            <td><span class="product-badge">{{ strtoupper($lead->product) }}</span></td>
                            <td><span class="status-badge status-{{ $lead->status }}">{{ ucfirst($lead->status) }}</span></td>
                            <td>{{ $lead->created_at->format('d/m/Y H:i') }}</td>
                            <td>
                                <a href="mailto:{{ $lead->email }}" class="btn btn-email" title="Enviar email">📧</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="no-leads">
                <p>📭 Nenhum lead capturado ainda. Compartilhe a landing page para começar a receber leads!</p>
            </div>
        @endif
    </div>
</body>
</html>
