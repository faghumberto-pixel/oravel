<html>
<head>
    <title>Leads - Landing Pages</title>
    <style>
        body { font-family: Arial; margin: 20px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f5f5f5; }
    </style>
</head>
<body>
    <h1>Leads das Landing Pages</h1>
    <p>Total: {{ $leads->count() }} leads nesta página</p>

    @if($leads->count() > 0)
    <table>
        <tr>
            <th>Nome</th>
            <th>Email</th>
            <th>Produto</th>
            <th>Status</th>
            <th>Data</th>
        </tr>
        @foreach($leads as $lead)
        <tr>
            <td>{{ $lead->name ?? 'N/A' }}</td>
            <td>{{ $lead->email ?? 'N/A' }}</td>
            <td>{{ strtoupper($lead->product ?? '') }}</td>
            <td>{{ $lead->status ?? 'N/A' }}</td>
            <td>{{ $lead->created_at ? $lead->created_at->format('d/m/Y H:i') : 'N/A' }}</td>
        </tr>
        @endforeach
    </table>
    @else
    <p>Nenhum lead encontrado</p>
    @endif
</body>
</html>
