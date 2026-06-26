<x-filament-panels::page class="!p-0">
    @php
        $isAdmin = auth()->user() && auth()->user()->hasRole('admin');
        $user = auth()->user();
    @endphp
    
    <div style="display: flex; height: 100vh; background-color: #111827;">
        
        <!-- SIDEBAR -->
        <div style="width: 224px; background-color: #1f2937; border-right: 4px solid #374151; display: flex; flex-direction: column; overflow-y: auto;">
            <div style="padding: 16px; border-bottom: 1px solid #374151;">
                <div style="text-align: center;">
                    <img src="https://api.dicebear.com/7.x/avataaars/svg?seed={{ urlencode($user->name) }}" 
                         style="width: 48px; height: 48px; border-radius: 50%; margin: 0 auto 12px;">
                    <div style="color: white; font-weight: 600; font-size: 14px;">{{ $user->name }}</div>
                    <div style="color: #9ca3af; font-size: 12px; margin-top: 4px;">
                        {{ $isAdmin ? '👨‍💼 Administrador' : '👨‍🔧 Técnico' }}
                    </div>
                </div>
            </div>

            @if($isAdmin)
                <div style="padding: 16px; flex: 1; overflow-y: auto;">
                    <h3 style="font-size: 12px; font-weight: bold; color: #9ca3af; margin-bottom: 12px; text-transform: uppercase;">Técnicos</h3>
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        @foreach($this->technicians as $tech)
                            <button 
                                wire:click="setSelectedTechnician('{{ $tech['id'] }}')"
                                style="width: 100%; padding: 12px; border-radius: 8px; display: flex; align-items: center; gap: 12px; cursor: pointer; border: none; background-color: {{ $selectedTechnician == $tech['id'] ? '#1e40af' : '#374151' }}; color: white; transition: all 0.2s;">
                                <img src="https://api.dicebear.com/7.x/avataaars/svg?seed={{ urlencode($tech['name']) }}" 
                                     style="width: 32px; height: 32px; border-radius: 50%;">
                                <div style="flex: 1; text-align: left;">
                                    <div style="font-size: 14px; font-weight: 600;">{{ $tech['name'] }}</div>
                                    <div style="font-size: 11px; color: {{ $tech['pending_count'] > 0 ? '#ef4444' : '#10b981' }}; font-weight: bold;">
                                        {{ $tech['pending_count'] > 0 ? '🔴 ' . $tech['pending_count'] . ' pendência' . ($tech['pending_count'] !== 1 ? 's' : '') : '✅ Tudo ok' }}
                                    </div>
                                </div>
                                @if($selectedTechnician == $tech['id'])
                                    <div style="font-size: 12px; color: #60a5fa;">✓</div>
                                @endif
                            </button>
                        @endforeach
                    </div>
                </div>
            @else
                <div style="padding: 16px; flex: 1;"></div>
            @endif
        </div>

        <!-- CONTEÚDO -->
        <div style="flex: 1; display: flex; flex-direction: column; background-color: #1f2937;">
            
            <!-- CABEÇALHO -->
            <div style="background-color: #374151; border-bottom: 4px solid #4b5563; padding: 24px 32px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                    <div style="display: flex; align-items: center; gap: 16px;">
                        <h1 style="font-size: 28px; font-weight: bold; color: white;">📅 Programação</h1>
                        <button wire:click="openPendenciesModal()" style="background-color: {{ $this->pendingCountForTechnician > 0 ? '#ef4444' : '#10b981' }}; color: white; padding: 8px 12px; border-radius: 20px; font-weight: bold; font-size: 14px; border: none; cursor: pointer;">
                            {{ $this->pendingCountForTechnician > 0 ? '🔴 ' . $this->pendingCountForTechnician . ' pendência' . ($this->pendingCountForTechnician !== 1 ? 's' : '') : '✅ Tudo em dia' }}
                        </button>
                    </div>
                    <button wire:click="$set('showAppointmentModal', true)" style="padding: 12px 24px; background-color: #2563eb; color: white; font-weight: bold; border-radius: 8px; border: none; cursor: pointer;">
                        + Novo Agendamento
                    </button>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div style="display: flex; gap: 8px; background-color: #1f2937; padding: 8px; border-radius: 8px;">
                        <button wire:click="$set('activeTab', 'hoje')" style="padding: 8px 16px; font-weight: 600; border-radius: 6px; cursor: pointer; border: none; background-color: {{ $activeTab === 'hoje' ? '#2563eb' : 'transparent' }}; color: {{ $activeTab === 'hoje' ? 'white' : '#9ca3af' }};">Hoje</button>
                        <button wire:click="$set('activeTab', 'semana')" style="padding: 8px 16px; font-weight: 600; border-radius: 6px; cursor: pointer; border: none; background-color: {{ $activeTab === 'semana' ? '#2563eb' : 'transparent' }}; color: {{ $activeTab === 'semana' ? 'white' : '#9ca3af' }};">Semana</button>
                        <button wire:click="$set('activeTab', 'mes')" style="padding: 8px 16px; font-weight: 600; border-radius: 6px; cursor: pointer; border: none; background-color: {{ $activeTab === 'mes' ? '#2563eb' : 'transparent' }}; color: {{ $activeTab === 'mes' ? 'white' : '#9ca3af' }};">Mês</button>
                    </div>

                    <div style="display: flex; align-items: center; gap: 24px;">
                        <button wire:click="setWeekOffset(-1)" style="padding: 8px; cursor: pointer; border: none; background-color: transparent; color: white; font-size: 16px;">◀</button>
                        <div style="text-align: center; min-width: 224px;">
                            @php $weekStart = \Carbon\Carbon::parse($weekStartDate); @endphp
                            <div style="color: white; font-weight: bold; font-size: 16px;">{{ $weekStart->format('d M, Y') }}</div>
                        </div>
                        <button wire:click="setWeekOffset(1)" style="padding: 8px; cursor: pointer; border: none; background-color: transparent; color: white; font-size: 16px;">▶</button>
                    </div>
                </div>
            </div>

            <!-- GRID -->
            <div style="flex: 1; overflow: auto; background-color: #111827;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead style="position: sticky; top: 0; z-index: 20;">
                        <tr style="background-color: #1f2937; border-bottom: 3px solid #374151;">
                            <th style="width: 96px; padding: 16px; text-align: left; font-size: 12px; font-weight: bold; color: #9ca3af; background-color: #0f1419; border-right: 3px solid #374151;">HORÁRIO</th>
                            @foreach($this->weekDays as $day)
                                <th style="width: 224px; padding: 16px; text-align: center; border-left: 3px solid #374151; background-color: {{ $day['is_today'] ? '#1e3a8a' : '#1f2937' }};">
                                    <div style="font-size: 12px; font-weight: bold; color: #9ca3af; text-transform: uppercase;">{{ $day['day_name'] }}</div>
                                    <div style="font-size: 20px; font-weight: bold; color: {{ $day['is_today'] ? '#60a5fa' : 'white' }}; margin-top: 4px;">{{ $day['full_date'] }}</div>
                                    @if($day['is_today'])<div style="font-size: 10px; color: #60a5fa; font-weight: bold; margin-top: 4px;">HOJE</div>@endif
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($this->getTimeSlots() as $time)
                            <tr style="border-bottom: 3px solid #374151; height: 192px;">
                                <td style="padding: 12px; font-size: 12px; font-weight: bold; color: #9ca3af; background-color: #0f1419; border-right: 3px solid #374151; vertical-align: top;">{{ $time }}</td>
                                @foreach($this->weekDays as $day)
                                    <td wire:click="openAppointmentModal('{{ $day['date'] }}', '{{ $time }}')" style="padding: 12px; border-left: 3px solid #374151; background-color: {{ $day['is_today'] ? 'rgba(30, 58, 138, 0.1)' : '#1f2937' }}; vertical-align: top; cursor: pointer;">
                                        @php
                                            $events = [];
                                            foreach ($this->gridEvents as $key => $event) {
                                                if (str_contains($key, $day['date']) && str_contains($key, $time)) {
                                                    if ($isAdmin && $selectedTechnician) {
                                                        if (str_contains($key, "_{$selectedTechnician}_")) {
                                                            $events[] = $event;
                                                        }
                                                    } else {
                                                        if (str_contains($key, "_{$user->id}_")) {
                                                            $events[] = $event;
                                                        }
                                                    }
                                                }
                                            }
                                        @endphp
                                        @foreach($events as $event)
                                            @if($event['type'] === 'appointment')
                                                <button wire:click="toggleAppointmentComplete('{{ $event['appointmentId'] }}')" onclick="event.stopPropagation()" style="display: block; width: 100%; padding: 8px; border-radius: 8px; color: white; font-size: 12px; font-weight: bold; cursor: pointer; margin-bottom: 8px; border: none;" class="{{ $event['color'] }}">
                                                    <div style="font-weight: 900; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $event['title'] }}</div>
                                                    <div style="font-size: 11px; opacity: 0.9; margin-top: 4px;">{{ $event['asset'] }}</div>
                                                </button>
                                            @else
                                                <a href="{{ $event['uri'] }}" style="display: block; padding: 8px; border-radius: 8px; color: white; font-size: 12px; font-weight: bold; cursor: pointer; margin-bottom: 8px; text-decoration: none;" class="{{ $event['color'] }}">
                                                    <div style="font-weight: 900; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $event['title'] }}</div>
                                                    <div style="font-size: 11px; opacity: 0.9; margin-top: 4px;">{{ $event['asset'] }}</div>
                                                </a>
                                            @endif
                                        @endforeach
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- MODAL NOVO AGENDAMENTO -->
    @if($showAppointmentModal)
        <div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.7); z-index: 100; display: flex; align-items: center; justify-content: center;">
            <div style="background-color: #1f2937; border-radius: 12px; padding: 32px; width: 100%; max-width: 500px; border: 2px solid #374151;">
                <h2 style="color: white; font-size: 24px; font-weight: bold; margin-bottom: 24px;">📅 Novo Agendamento</h2>
                <form wire:submit="saveAppointment" style="display: flex; flex-direction: column; gap: 16px;">
                    <div>
                        <label style="color: #9ca3af; font-size: 12px; font-weight: bold;">Data</label>
                        <input type="date" wire:model="appointmentDate" style="width: 100%; padding: 12px; background-color: #374151; border: 1px solid #4b5563; color: white; border-radius: 6px; margin-top: 4px;">
                    </div>
                    <div>
                        <label style="color: #9ca3af; font-size: 12px; font-weight: bold;">Hora</label>
                        <input type="time" wire:model="appointmentTime" style="width: 100%; padding: 12px; background-color: #374151; border: 1px solid #4b5563; color: white; border-radius: 6px; margin-top: 4px;">
                    </div>
                    <div>
                        <label style="color: #9ca3af; font-size: 12px; font-weight: bold;">Assunto *</label>
                        <input type="text" wire:model="appointmentSubject" placeholder="Ex: Revisão periódica" style="width: 100%; padding: 12px; background-color: #374151; border: 1px solid #4b5563; color: white; border-radius: 6px; margin-top: 4px;">
                    </div>
                    <div>
                        <label style="color: #9ca3af; font-size: 12px; font-weight: bold;">Descrição</label>
                        <textarea wire:model="appointmentDescription" style="width: 100%; padding: 12px; background-color: #374151; border: 1px solid #4b5563; color: white; border-radius: 6px; margin-top: 4px; resize: vertical; height: 80px;"></textarea>
                    </div>
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <input type="checkbox" id="urgentCheckbox" wire:model="appointmentUrgent" style="width: 20px; height: 20px; cursor: pointer;">
                        <label for="urgentCheckbox" style="color: white; cursor: pointer;">🔴 URGENTE</label>
                    </div>
                    <div style="display: flex; gap: 12px; margin-top: 24px;">
                        <button type="button" wire:click="$set('showAppointmentModal', false)" style="flex: 1; padding: 12px; background-color: #4b5563; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: bold;">✖️ Cancelar</button>
                        <button type="submit" style="flex: 1; padding: 12px; background-color: #2563eb; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: bold;">✅ Salvar</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- MODAL PENDÊNCIAS -->
    @if($showPendenciesModal)
        <div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.7); z-index: 100; display: flex; align-items: center; justify-content: center;">
            <div style="background-color: #1f2937; border-radius: 12px; padding: 32px; width: 100%; max-width: 700px; max-height: 80vh; overflow-y: auto; border: 2px solid #374151;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 24px;">
                    <h2 style="color: white; font-size: 24px; font-weight: bold;">📋 Resumo de Pendências</h2>
                    <button wire:click="closePendenciesModal()" style="background-color: #4b5563; color: white; border: none; padding: 8px 12px; border-radius: 6px; cursor: pointer;">✖️</button>
                </div>

                <div style="display: flex; gap: 16px; margin-bottom: 24px;">
                    <div style="flex: 1; background-color: #374151; padding: 16px; border-radius: 8px; border-left: 4px solid #ef4444;">
                        <div style="color: #9ca3af; font-size: 12px; font-weight: bold;">PENDENTES</div>
                        <div style="color: #ef4444; font-size: 28px; font-weight: bold; margin-top: 8px;">{{ $this->selectedPendingCount }}</div>
                    </div>
                    <div style="flex: 1; background-color: #374151; padding: 16px; border-radius: 8px; border-left: 4px solid #10b981;">
                        <div style="color: #9ca3af; font-size: 12px; font-weight: bold;">CONCLUÍDOS</div>
                        <div style="color: #10b981; font-size: 28px; font-weight: bold; margin-top: 8px;">{{ $this->selectedCompletedCount }}</div>
                    </div>
                    <div style="flex: 1; background-color: #374151; padding: 16px; border-radius: 8px; border-left: 4px solid #60a5fa;">
                        <div style="color: #9ca3af; font-size: 12px; font-weight: bold;">TOTAL</div>
                        <div style="color: #60a5fa; font-size: 28px; font-weight: bold; margin-top: 8px;">{{ $this->selectedPendingCount + $this->selectedCompletedCount }}</div>
                    </div>
                </div>

                <div style="display: flex; flex-direction: column; gap: 12px;">
                    @forelse($this->selectedTechnicianAppointments as $appointment)
                        <div style="background-color: #374151; padding: 16px; border-radius: 8px; display: flex; gap: 12px; align-items: flex-start; border-left: 4px solid {{ $appointment->completed ? '#10b981' : ($appointment->urgente ? '#ef4444' : '#60a5fa') }};">
                            <button wire:click="toggleAppointmentComplete('{{ $appointment->id }}')" onclick="event.stopPropagation()" style="margin-top: 4px; width: 24px; height: 24px; border-radius: 6px; border: 2px solid {{ $appointment->completed ? '#10b981' : '#4b5563' }}; background-color: {{ $appointment->completed ? '#10b981' : 'transparent' }}; cursor: pointer; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; flex-shrink: 0;">{{ $appointment->completed ? '✓' : '' }}</button>
                            <div style="flex: 1;">
                                <div style="color: white; font-weight: bold; font-size: 14px; text-decoration: {{ $appointment->completed ? 'line-through' : 'none' }}; opacity: {{ $appointment->completed ? '0.6' : '1' }};">{{ $appointment->assunto }}</div>
                                <div style="color: #9ca3af; font-size: 12px; margin-top: 4px;">📅 {{ $appointment->scheduled_at->format('d/m/Y H:i') }}</div>
                                @if($appointment->descricao)<div style="color: #9ca3af; font-size: 12px; margin-top: 4px;">{{ $appointment->descricao }}</div>@endif
                            </div>
                            <div style="display: flex; gap: 8px; flex-direction: column;">
                                @if($appointment->urgente)<span style="background-color: #ef4444; color: white; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold;">🔴 URGENTE</span>@endif
                                @if($appointment->completed)<span style="background-color: #10b981; color: white; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold;">✅ FEITO</span>@else<span style="background-color: #60a5fa; color: white; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold;">📅 PENDENTE</span>@endif
                            </div>
                        </div>
                    @empty
                        <div style="text-align: center; padding: 32px; color: #9ca3af;"><div style="font-size: 48px; margin-bottom: 16px;">✨</div><div style="font-size: 16px; font-weight: bold;">Nenhum agendamento</div></div>
                    @endforelse
                </div>

                <div style="display: flex; gap: 12px; margin-top: 24px;">
                    <button wire:click="closePendenciesModal()" style="flex: 1; padding: 12px; background-color: #2563eb; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: bold;">✅ Fechar</button>
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
