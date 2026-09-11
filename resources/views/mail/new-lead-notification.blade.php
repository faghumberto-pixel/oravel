<?php
$product = str_contains($lead->source, 'wms') ? 'WMS' : 'CRM';
?>
<h2>Novo Lead - Oravel {{ $product }}</h2>

<p><strong>Nome:</strong> {{ $lead->name }}</p>
<p><strong>Email:</strong> {{ $lead->email }}</p>
<p><strong>Telefone:</strong> {{ $lead->phone }}</p>
<p><strong>Empresa:</strong> {{ $lead->company_name }}</p>
<p><strong>Segmento:</strong> {{ $lead->segment }}</p>
<p><strong>Produto:</strong> Oravel {{ $product }}</p>
<p><strong>Data:</strong> {{ $lead->created_at->format('d/m/Y H:i') }}</p>

<p>---</p>
<p>Entre em contato com este lead para iniciar o teste grátis.</p>
