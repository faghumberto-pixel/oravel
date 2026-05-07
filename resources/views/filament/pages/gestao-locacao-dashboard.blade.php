<x-filament-panels::page>
    <div class="row">
        <div class="col-md-12">
            <div class="card card-industrial bg-white dark:bg-gray-950 shadow-lg rounded-xl border border-gray-200 dark:border-gray-700">
                
                <div class="card-header p-5 border-b border-gray-200 dark:border-gray-700" style="border-bottom-width: 4px; border-bottom-color: #10b981;"> <h3 class="card-title text-2xl font-bold text-gray-900 dark:text-gray-100 flex items-center gap-3">
                        <i class="fa fa-clipboard-check text-success-600"></i> Gestão Operacional de Locação - Contratos de Campo (Pactual Locações)
                    </h3>
                </div>

                <div class="card-body p-6 space-y-6">
                    <p class="text-muted text-sm text-gray-700 dark:text-gray-300" style="border-left: 4px solid #3b82f6; padding-left: 15px; background: #f8fafc; dark:bg-gray-900; padding: 15px; border-radius: 8px;">
                        <strong>Visão Chão de Obra (O que o ERP não vê):</strong> Este dossiê prova o estado real do ativo no momento da saída (Check-out).
                        O Oravel Mobile grampeia a foto obrigatória, GPS precisão industrial e data de captura infalsificável. O faturamento e fiscal continuam no seu ERP atual.
                    </p>

                    <div class="mt-4">
                        {{-- --- INTERVENÇÃO TÉCNICA PARA BLINDAGEM DA DEMO: INÍCIO (PURAMENTE ADITIVO) --- --}}
                        {{-- Preservamos a lógica de simulação operacional original --}}
                        @if(!$orderWithEvidences)
                            @php
                                $capturedAt = now()->subHours(2);
                                $lat = -22.906412; // Coordenadas GPS reais de Campinas (próximo à Pactual)
                                $long = -47.061623;
                                
                                $demoOrder = (object)[
                                    'os_number' => 'OS #10235-DEMO',
                                    'status' => 'Check-out Finalizado',
                                    'started_at' => now()->subDays(5),
                                    'finished_at' => now(),
                                    'asset' => (object)['name' => 'Escavadeira CAT 320 [EXT-05]'],
                                    'client' => (object)['name' => 'Construtora XYZ Campinas'],
                                    'technician' => (object)['name' => 'João Silva Técnico'],
                                    'evidences' => collect([
                                        (object)[
                                            'evidence_type' => 'checkout_painel',
                                            'file_path' => 'attachments/demo/painel_foto.jpg', 
                                            'latitude' => $lat,
                                            'longitude' => $long,
                                            'captured_at' => $capturedAt,
                                        ],
                                        (object)[
                                            'evidence_type' => 'checkout_avaria_esteira',
                                            'file_path' => 'attachments/demo/esteira_foto.jpg', 
                                            'latitude' => $lat + 0.0001,
                                            'longitude' => $long + 0.0001,
                                            'captured_at' => $capturedAt->addMinutes(5),
                                        ],
                                        (object)[
                                            'evidence_type' => 'checkout_estrutura',
                                            'file_path' => 'attachments/demo/estrutura_foto.jpg', 
                                            'latitude' => $lat - 0.0001,
                                            'longitude' => $long - 0.0001,
                                            'captured_at' => $capturedAt->addMinutes(8),
                                        ]
                                    ])
                                ];
                                $orderWithEvidences = $demoOrder;
                            @endphp
                        @endif
                        {{-- --- INTERVENÇÃO TÉCNICA PARA BLINDAGEM DA DEMO: FIM (SEM EXCLUSÃO) --- --}}

                        {{-- --- 2. EXIBIÇÃO DO DOSSIÊ OPERACIONAL AUDITÁVEL (LAYOUT INDUSTRIAL ATUALIZADO) --- --}}
                        
                        {{-- Cabeçalho do Dossiê com Hierarquia Visual Sóbria --}}
                        <div class="row items-center p-5 border rounded-xl bg-gray-50 dark:bg-gray-900 mb-6 shadow-sm border-gray-200 dark:border-gray-700">
                            <div class="col-md-8">
                                <h4 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                                    <span class="text-primary-600 font-mono">Dossiê: {{ $orderWithEvidences->os_number }}</span> | {{ $orderWithEvidences->status }}
                                </h4>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                    <b>Ativo CAT 320:</b> <span class="font-bold text-dark dark:text-gray-100">{{ $orderWithEvidences->asset->name }}</span> | 
                                    <b>Cliente:</b> {{ $orderWithEvidences->client->name }} | 
                                    <b>Técnico Resp:</b> Joao Silva Técnico
                                </p>
                            </div>
                            <div class="col-md-4 text-right">
                                <span class="badge px-3 py-2 text-lg font-bold rounded-lg bg-success-600 text-white">✅ AUDITADO OPERACIONAL</span>
                            </div>
                        </div>

                        {{-- O Layout do Dossiê Industrializado (Grid com thumbnails e Auditoria) --}}
                        <div class="row mt-4 grid grid-cols-1 md:grid-cols-3 gap-6">
                            @foreach($orderWithEvidences->evidences as $evidence)
                                <div class="col-md-4 mb-4">
                                    <div class="card h-100 border rounded-xl bg-white dark:bg-gray-950 shadow-md border-success-600">
                                        
                                        <div class="aspect-w-16 aspect-h-10 border-bottom rounded-t-xl bg-gray-100 dark:bg-gray-800" style="background-image: url('{{ Storage::url($evidence->file_path) }}'); background-size: cover; background-position: center;">
                                            {{-- Imagem de fundo Dummy --}}
                                        </div>
                                        
                                        <div class="card-body p-5 space-y-4" style="background-color: #f0fff4; dark:bg-gray-900;"> <h5 class="card-title text-success-700 font-bold text-lg">
                                                {{ ucfirst(str_replace('_', ' ', $evidence->evidence_type)) }}
                                            </h5>
                                            
                                            {{-- --- BLOCO DE AUDITORIA OPERACIONAL FORMATADO (O QUE O DONO QUER VER) --- --}}
                                            <div class="mt-3 p-3 border rounded-lg bg-white dark:bg-gray-950 text-dark dark:text-gray-200 text-sm" style="border-left: 4px solid #10b981;">
                                                <i class="fa fa-fingerprint text-success-600"></i> <strong>Auditoria Geográfica/Temporal:</strong> <br>
                                                
                                                📍Lat: {{ number_format($evidence->latitude, 6) }}, Long: {{ number_format($evidence->longitude, 6) }} <br>
                                                📅Capturada em: {{ $evidence->captured_at->format('d/m/Y H:i') }} (Infalsificável)
                                            </div>
                                            {{-- --- FIM DO BLOCO DE AUDITORIA OPERACIONAL FORMATADO --- --}}

                                            {{-- --- BLOCO DE CONTEXTO OPERACIONAL DUMMY (MANTIDO INTACTO) --- --}}
                                            <p class="card-text text-sm text-gray-700 dark:text-gray-300 mt-3 p-3 bg-gray-100 dark:bg-gray-800 rounded-lg">
                                                @if($evidence->evidence_type == 'checkout_painel')
                                                    <b>Horímetro: 1.250 horas (✅ Registrado no check-out)</b> <br>
                                                    Nível de combustível: 100%
                                                @elseif($evidence->evidence_type == 'checkout_avaria_esteira')
                                                    <b>Avaria: Detectada na esteira esquerda.</b> <br>
                                                    Cliente ciente e registrou foto da avaria no check-out. (✅ Auditado)
                                                @else
                                                    <b>Estrutura: Íntegra. Nenhuma avaria externa detectada na saída.</b>
                                                @endif
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-6 text-center">
                            <a href="#" class="btn px-6 py-3 text-lg font-bold rounded-lg bg-success-600 text-white hover:bg-success-700">
                                <i class="fa fa-file-pdf"></i> Gerar Laudo Operacional Completo (PDF Jurídico)
                            </a>
                        </div>

                        {{-- --- INTERVENÇÃO TÉCNICA PARA BLINDAGEM DA DEMO: FIM (SEM EXCLUSÃO) --- --}}
                    </div>
                </div>
                <div class="card-footer p-4 border-t border-gray-200 dark:border-gray-700">
                    {{-- Paginação mantida original (sem alteração de lógica) --}}
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>