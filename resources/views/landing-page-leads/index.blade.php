<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leads - Landing Pages</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-7xl mx-auto">
            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900">Leads das Landing Pages</h1>
                <p class="mt-2 text-gray-600">Gerenciar leads recebidos de todas as landing pages</p>
            </div>

            <!-- Search & Filter -->
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <form method="GET" class="space-y-4 sm:space-y-0 sm:flex sm:gap-4">
                    <input type="text" name="search" placeholder="Buscar por nome, email..."
                        value="{{ request('search') }}" class="flex-1 px-4 py-2 border rounded-lg">

                    <select name="product" class="px-4 py-2 border rounded-lg">
                        <option value="">Todos os produtos</option>
                        <option value="crm" {{ request('product') === 'crm' ? 'selected' : '' }}>CRM</option>
                        <option value="wms" {{ request('product') === 'wms' ? 'selected' : '' }}>WMS</option>
                        <option value="frota" {{ request('product') === 'frota' ? 'selected' : '' }}>Frota</option>
                        <option value="os" {{ request('product') === 'os' ? 'selected' : '' }}>OS</option>
                    </select>

                    <select name="status" class="px-4 py-2 border rounded-lg">
                        <option value="">Todos os status</option>
                        <option value="novo" {{ request('status') === 'novo' ? 'selected' : '' }}>Novo</option>
                        <option value="contatado" {{ request('status') === 'contatado' ? 'selected' : '' }}>Contatado</option>
                        <option value="convertido" {{ request('status') === 'convertido' ? 'selected' : '' }}>Convertido</option>
                    </select>

                    <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        Filtrar
                    </button>
                </form>
            </div>

            <!-- Table -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-sm font-medium text-gray-700">Nome</th>
                            <th class="px-6 py-3 text-left text-sm font-medium text-gray-700">Email</th>
                            <th class="px-6 py-3 text-left text-sm font-medium text-gray-700">Produto</th>
                            <th class="px-6 py-3 text-left text-sm font-medium text-gray-700">Status</th>
                            <th class="px-6 py-3 text-left text-sm font-medium text-gray-700">Recebido em</th>
                            <th class="px-6 py-3 text-left text-sm font-medium text-gray-700">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($leads as $lead)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm text-gray-900">{{ $lead->name }}</td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ $lead->email }}</td>
                            <td class="px-6 py-4 text-sm">
                                <span class="px-3 py-1 rounded-full text-sm font-medium
                                    @if($lead->product === 'crm') bg-blue-100 text-blue-800
                                    @elseif($lead->product === 'wms') bg-purple-100 text-purple-800
                                    @elseif($lead->product === 'frota') bg-orange-100 text-orange-800
                                    @elseif($lead->product === 'os') bg-green-100 text-green-800
                                    @endif">
                                    {{ ucfirst($lead->product) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <form method="POST" action="{{ route('landing-page-leads.update-status', $lead) }}" class="inline">
                                    @csrf
                                    <select name="status" onchange="this.form.submit()"
                                        class="px-2 py-1 rounded text-sm
                                        @if($lead->status === 'novo') bg-blue-100 text-blue-800
                                        @elseif($lead->status === 'contatado') bg-yellow-100 text-yellow-800
                                        @elseif($lead->status === 'convertido') bg-green-100 text-green-800
                                        @endif">
                                        <option value="novo" {{ $lead->status === 'novo' ? 'selected' : '' }}>Novo</option>
                                        <option value="contatado" {{ $lead->status === 'contatado' ? 'selected' : '' }}>Contatado</option>
                                        <option value="convertido" {{ $lead->status === 'convertido' ? 'selected' : '' }}>Convertido</option>
                                    </select>
                                </form>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                {{ $lead->created_at->format('d/m/Y H:i') }}
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <a href="mailto:{{ $lead->email }}" class="text-blue-600 hover:text-blue-800">
                                    Contatar
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>

                @if($leads->isEmpty())
                <div class="text-center py-12">
                    <p class="text-gray-500">Nenhum lead encontrado</p>
                </div>
                @endif
            </div>

            <!-- Pagination -->
            @if($leads->hasPages())
            <div class="mt-6">
                {{ $leads->links() }}
            </div>
            @endif
        </div>
    </div>
</body>
</html>
