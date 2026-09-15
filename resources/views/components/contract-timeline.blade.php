<!-- Contract Timeline Infographic Component -->
<div class="contract-timeline-infographic">
  <h3 class="timeline-title">CICLO DE VIDA DO CONTRATO</h3>

  <!-- Timeline container -->
  <div class="timeline-container">

    <!-- Linha conectora horizontal -->
    <div class="timeline-line"></div>

    <!-- Eventos da timeline -->
    <div class="timeline-events">

      <!-- 1. CONTRATAÇÃO (Início) -->
      <div class="timeline-event" style="--color: #ff6a1a;">
        <div class="event-circle">
          <div class="event-date">{{ $startDate->format('d/m') }}</div>
        </div>
        <div class="event-label">CONTRATAÇÃO</div>
        <div class="event-details">
          <p class="event-year">{{ $startDate->format('Y') }}</p>
          <p class="event-description">
            <strong>Cliente:</strong> {{ $contract->client->name ?? 'N/A' }}<br>
            <strong>Equipamento:</strong> {{ $contract->asset->name ?? 'N/A' }}
          </p>
        </div>
      </div>

      <!-- 2. MANUTENÇÕES (com múltiplos eventos) -->
      @forelse($maintenanceEvents as $maintenance)
        <div class="timeline-event" style="--color: {{ $maintenance['color'] }};">
          <div class="event-circle">
            <div class="event-icon">
              @if($maintenance['type'] === 'preventiva')
                🔧
              @elseif($maintenance['type'] === 'corretiva')
                ⚠️
              @else
                🔍
              @endif
            </div>
          </div>
          <div class="event-label">
            {{ strtoupper($maintenance['type']) }}
          </div>
          <div class="event-details">
            <p class="event-date">{{ $maintenance['date'] }}</p>
            <p class="event-description">{{ Str::limit($maintenance['description'], 30) }}</p>
          </div>
        </div>
      @empty
        <div class="timeline-event" style="--color: #d1d5db;">
          <div class="event-circle">
            <span class="event-icon">—</span>
          </div>
          <div class="event-label">SEM MANUTENÇÕES</div>
          <div class="event-details">
            <p class="event-description">Período sem manutenções</p>
          </div>
        </div>
      @endforelse

      <!-- 3. RENOVAÇÃO SUGERIDA -->
      <div class="timeline-event" style="--color: #fbbf24;">
        <div class="event-circle">
          <div class="event-icon">🔄</div>
        </div>
        <div class="event-label">RENOVAÇÃO</div>
        <div class="event-details">
          <p class="event-date">{{ $renewalSuggestedDate->format('d/m/Y') }}</p>
          <p class="event-description">
            <strong>60 dias antes</strong>
          </p>
        </div>
      </div>

      <!-- 4. VENCIMENTO (Final) -->
      <div class="timeline-event active" style="--color: #0066cc;">
        <div class="event-circle">
          <div class="event-date">{{ $endDate->format('d/m') }}</div>
        </div>
        <div class="event-label">VENCIMENTO</div>
        <div class="event-details">
          <p class="event-year">{{ $endDate->format('Y') }}</p>
          <p class="event-countdown">
            <strong style="font-size: 20px; color: #0066cc;">
              {{ $daysRemaining }}
            </strong><br>
            <span style="font-size: 12px; color: #666;">DIAS</span>
          </p>
        </div>
      </div>
    </div>
  </div>

  <!-- Progress Bar -->
  <div class="timeline-progress">
    <div class="progress-bar">
      <div class="progress-fill" style="width: {{ $progress }}%"></div>
    </div>
    <div class="progress-labels">
      <span class="progress-start">{{ $startDate->format('d/m/Y') }}</span>
      <span class="progress-today">HOJE</span>
      <span class="progress-end">{{ $endDate->format('d/m/Y') }}</span>
    </div>
  </div>

  <!-- Status Info Cards -->
  <div class="timeline-stats">
    <div class="stat-card">
      <div class="stat-icon">📦</div>
      <div class="stat-content">
        <div class="stat-label">EQUIPAMENTO</div>
        <div class="stat-value">{{ $contract->asset->category->name ?? 'N/A' }}</div>
      </div>
    </div>

    <div class="stat-card">
      <div class="stat-icon">🔧</div>
      <div class="stat-content">
        <div class="stat-label">MANUTENÇÕES</div>
        <div class="stat-value">{{ $maintenanceCount }}</div>
      </div>
    </div>

    <div class="stat-card">
      <div class="stat-icon">📅</div>
      <div class="stat-content">
        <div class="stat-label">DIAS DECORRIDOS</div>
        <div class="stat-value">{{ $daysElapsed }}</div>
      </div>
    </div>

    <div class="stat-card active">
      <div class="stat-icon">⏰</div>
      <div class="stat-content">
        <div class="stat-label">DIAS RESTANTES</div>
        <div class="stat-value" style="color: #0066cc;">{{ $daysRemaining }}</div>
      </div>
    </div>
  </div>
</div>

<style>
.contract-timeline-infographic {
  background: linear-gradient(135deg, #f8f9fa, #fff);
  border-radius: 16px;
  padding: 40px;
  border: 1px solid #e0e0e0;
  margin-bottom: 24px;
}

.timeline-title {
  text-align: center;
  font-size: 24px;
  font-weight: 700;
  color: #333;
  margin-bottom: 8px;
}

.timeline-title::after {
  content: '';
  display: block;
  width: 60px;
  height: 4px;
  background: linear-gradient(90deg, #ff6a1a, #fbbf24, #0066cc);
  margin: 12px auto 0;
  border-radius: 2px;
}

.timeline-container {
  position: relative;
  margin: 40px 0;
  padding: 20px 0;
}

.timeline-line {
  position: absolute;
  top: 30px;
  left: 0;
  right: 0;
  height: 2px;
  background: #e0e0e0;
  z-index: 1;
}

.timeline-events {
  display: flex;
  justify-content: space-between;
  position: relative;
  z-index: 2;
  padding: 0 20px;
  gap: 20px;
  overflow-x: auto;
  padding-bottom: 20px;
}

.timeline-event {
  flex: 1;
  text-align: center;
  min-width: 150px;
}

.event-circle {
  width: 60px;
  height: 60px;
  border-radius: 50%;
  background: white;
  border: 4px solid var(--color);
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 12px;
  font-weight: 700;
  color: var(--color);
  font-size: 14px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
  transition: all 0.3s ease;
}

.timeline-event.active .event-circle {
  width: 70px;
  height: 70px;
  font-size: 16px;
  box-shadow: 0 4px 16px rgba(0,102,204,0.3);
}

.event-icon {
  font-size: 24px;
}

.event-date {
  font-weight: 700;
  color: var(--color);
  font-size: 12px;
}

.event-year {
  font-size: 12px;
  color: #999;
  margin: 4px 0;
}

.event-label {
  font-weight: 600;
  color: var(--color);
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 8px;
  min-height: 24px;
}

.event-details {
  font-size: 12px;
  color: #666;
  line-height: 1.4;
}

.event-description {
  margin-top: 6px;
  font-size: 11px;
}

.timeline-progress {
  margin-top: 40px;
}

.progress-bar {
  height: 8px;
  background: #e0e0e0;
  border-radius: 4px;
  overflow: hidden;
  margin-bottom: 12px;
}

.progress-fill {
  height: 100%;
  background: linear-gradient(90deg, #ff6a1a, #fbbf24);
  transition: width 0.3s ease;
}

.progress-labels {
  display: flex;
  justify-content: space-between;
  font-size: 12px;
  color: #666;
  padding: 0 4px;
}

.progress-today {
  font-weight: 600;
  color: #333;
}

.timeline-stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
  gap: 12px;
  margin-top: 32px;
}

.stat-card {
  background: white;
  border: 1px solid #e0e0e0;
  border-radius: 8px;
  padding: 16px;
  display: flex;
  align-items: center;
  gap: 12px;
  transition: all 0.3s ease;
}

.stat-card.active {
  background: linear-gradient(135deg, #f0f7ff, #e6f2ff);
  border-color: #0066cc;
  box-shadow: 0 2px 8px rgba(0,102,204,0.15);
}

.stat-icon {
  font-size: 24px;
}

.stat-content {
  flex: 1;
}

.stat-label {
  font-size: 11px;
  text-transform: uppercase;
  color: #999;
  letter-spacing: 0.5px;
  margin-bottom: 4px;
}

.stat-value {
  font-size: 20px;
  font-weight: 700;
  color: #333;
}

@media (max-width: 768px) {
  .contract-timeline-infographic {
    padding: 24px;
  }

  .timeline-events {
    flex-wrap: wrap;
    gap: 12px;
  }

  .timeline-event {
    min-width: 120px;
  }

  .event-circle {
    width: 50px;
    height: 50px;
    font-size: 12px;
  }

  .timeline-stats {
    grid-template-columns: repeat(2, 1fr);
  }
}
</style>
