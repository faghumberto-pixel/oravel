# Cobertura de PMP: tela de visão geral + aba PMP executável na OS

Data: 2026-08-29
Status: aprovado para implementação

## Contexto

O mecanismo de "importar catálogo de família para um Grupo" (documentado no
guia PMP publicado nesta sessão) já cria `MaintenancePlan` reais vinculados
ao `checklist_group_id` — e um ativo daquele grupo já herda esses planos
automaticamente via `MaintenancePlan::applicableFor(Asset $asset)`
(`app/Models/MaintenancePlan.php:322`). Isso já funciona tecnicamente, mas
o usuário não tinha como *ver* esse resultado: hoje o único lugar que usa
esse método é um `Forms\Components\Placeholder` somente-leitura dentro da
aba "Dados Gerais" da OS (`MaintenanceOrderResource.php:349-379`,
"Preventivas Sugeridas (por Horímetro)") — texto informativo, sem ação,
enterrado dentro de uma OS que já precisa existir.

Faltam duas coisas:
1. Uma tela que mostre, para **todos os ativos de uma vez**, quais têm PMP
   cobrindo, quais estão vencendo, quais estão vencidos — com ação de abrir
   OS direto dali.
2. Uma aba própria na OS para **executar** o PMP (toggle de conformidade,
   observação, foto) — igual à aba "Vistoria / Checklist" já existente
   (`MaintenanceOrderResource.php:503-549`), não um texto passivo.

## Escopo

1. Nova página `PMP → Cobertura de PMP`.
2. Ação "Abrir OS" que cria a `MaintenanceOrder` na hora, com todos os
   planos vencidos/vencendo daquele ativo entrando na mesma OS.
3. Nova aba "PMP" no `MaintenanceOrderResource`, populada automaticamente
   na criação da OS (snapshot, mesmo padrão da aba Checklist).

Fora de escopo: mudar `MaintenancePlan::applicableFor()`/
`dueStatusForAsset()` em si (já funcionam corretamente, confirmado pelo
usuário) — este design só constrói UI em cima do que já existe.

## 1. Página "Cobertura de PMP"

`app/Filament/Pages/CoberturaPmp.php`, `navigationGroup = 'PMP'`, ao lado
de `PainelPmp` e `AlocacaoTecnicosPmp`.

Tabela (não Kanban) com uma linha por `Asset` do tenant:

| Coluna | Origem |
|---|---|
| Ativo | `Asset::name` + patrimônio |
| Grupo | `Asset::checklistGroup->name` ou "Sem grupo" |
| Status PMP | calculado (ver abaixo) |
| Próximo vencimento | menor `due_at_hours`/`due_at_date` entre os planos aplicáveis |
| Planos aplicáveis | contagem de `MaintenancePlan::applicableFor($asset)` |

**Status PMP** (badge colorido, mesma paleta de cores já usada em
`Asset::statusColor()`). Correção de suposição inicial: não existe uma
"janela de alerta em horas" configurável no código — `CheckMaintenanceDueAlerts`
só distingui vencido/não-vencido (`is_overdue`). O sinal certo de
"vencendo em breve" já existe pronto em
`MaintenancePlan::projectedDueDates($asset, $months = 3)`
(`app/Models/MaintenancePlan.php:258-307`), que projeta o mês de
vencimento usando a mesma regra de `dueStatusForAsset()` + uso médio
diário do horímetro (`Asset::getAverageHourlyUsage()`). "Vencendo" =
alguma projeção com `month_offset === 0` (vence ainda este mês, mas não
está vencido agora):
- `sem_grupo` (cinza) — `checklist_group_id` nulo, ou grupo sem nenhum
  `MaintenancePlan` ativo.
- `em_dia` (verde) — nenhum plano vencido, e nenhuma projeção com
  `month_offset === 0`.
- `vencendo` (amarelo) — nenhum plano vencido, mas `projectedDueDates()`
  retorna ao menos uma entrada com `month_offset === 0`.
- `vencido` (vermelho) — algum plano com `is_overdue = true`.

Filtro por Status PMP (`Tables\Filters\SelectFilter`) e por Grupo.

**Ação "Abrir OS"** — `Tables\Actions\Action::make('abrir_os')`, visível só
quando Status PMP é `vencendo` ou `vencido`:

```php
Tables\Actions\Action::make('abrir_os')
    ->label('Abrir OS')
    ->icon('heroicon-o-wrench-screwdriver')
    ->color('warning')
    ->requiresConfirmation()
    ->modalDescription(fn (Asset $record) => 'Cria uma OS preventiva para "'.$record->name.'" cobrindo todos os planos vencidos/vencendo, e já entra na Fila de Alocação de Técnicos.')
    ->action(function (Asset $record) {
        $order = MaintenanceOrder::create([
            'tenant_id' => $record->tenant_id,
            'asset_id' => $record->id,
            'client_id' => $record->client_id,
            'maintenance_type' => MaintenanceOrder::TYPE_PREVENTIVE,
            'status' => 'Aberto',
            'internal_status' => 'aguardando_diagnostico',
            'scheduled_at' => now(),
            'description' => 'Planejada via Cobertura de PMP.',
        ]);

        Notification::make()->title('OS criada')->success()->send();

        return redirect(MaintenanceOrderResource::getUrl('edit', ['record' => $order]));
    }),
```

Reaproveita o mesmo padrão de criação já usado em
`PainelPmp::createOrderFromAlert()` (`app/Filament/Pages/PainelPmp.php:440-470`)
— não duplica um segundo mecanismo de criação de OS preventiva, só troca a
origem do gatilho (clique na tela de Cobertura, em vez de arrastar um
card no Kanban). A criação da `MaintenanceOrder` já dispara sozinha o
`MaintenanceOrderChecklistSnapshotObserver` (seção 2 abaixo) e a entrada
automática na Fila de Alocação (já documentada no guia PMP, passo 5).

Múltiplos planos vencidos do mesmo ativo entram todos na mesma OS — não
cria uma OS por plano.

## 2. Aba "PMP" na Ordem de Serviço

### 2.1 Estender o snapshot observer

`app/Observers/MaintenanceOrderChecklistSnapshotObserver.php::created()`
ganha um segundo bloco, depois do snapshot de checklist do Grupo (linhas
30-42 atuais), que itera os planos aplicáveis e vencidos/vencendo do
ativo e cria um `MaintenanceOrderChecklist` por plano, com
`checklist_type = 'pmp'` (valor novo — a coluna já existe e já
diferencia por tipo, não precisa migration):

```php
$plans = MaintenancePlan::applicableFor($asset)->where('is_active', true);

foreach ($plans as $plan) {
    $status = $plan->dueStatusForAsset($asset);
    $vencendoEsteMes = collect($plan->projectedDueDates($asset, 0))
        ->contains(fn (array $p) => $p['month_offset'] === 0);

    if (! $status['is_overdue'] && ! $vencendoEsteMes) {
        continue; // nem vencido nem vencendo este mês, não polui a aba PMP com planos ainda distantes
    }

    MaintenanceOrderChecklist::create([
        'tenant_id' => $order->tenant_id,
        'maintenance_order_id' => $order->id,
        'item_name' => $plan->name,
        'instructions' => $plan->notes,
        'checklist_type' => 'pmp',
        'is_template' => false,
        'is_completed' => false,
    ]);
}
```

Mesmo critério `month_offset === 0` usado na Parte 1 (seção "Status PMP"),
via `projectedDueDates($asset, 0)` — só precisa do mês atual aqui, não
dos 3 meses de projeção padrão.

### 2.2 Nova aba no formulário

`MaintenanceOrderResource.php`, nova `Tabs\Tab::make('PMP')` ao lado da
aba "Vistoria / Checklist" (linha 503), mesma estrutura de repeater —
`Forms\Components\Repeater::make('checklists')` filtrado por
`checklist_type = 'pmp'`. A aba "Vistoria / Checklist" existente não
precisa de nenhum filtro adicional: hoje `checklist_type` só recebe
`maintenance_type` da própria OS (`'Preventiva'`/`'Corretiva'`, ver
`MaintenanceOrderChecklistSnapshotObserver.php:52` atual), nunca o valor
literal `'pmp'` — os dois conjuntos já são naturalmente disjuntos, sem
necessidade de exclusão explícita.

```php
Forms\Components\Tabs\Tab::make('PMP')
    ->visible(fn (Get $get) => MaintenanceOrderChecklist::where('maintenance_order_id', $get('id'))->where('checklist_type', 'pmp')->exists())
    ->schema([
        Forms\Components\Repeater::make('pmp_items')
            ->relationship('checklists', modifyQueryUsing: fn (Builder $query) => $query->where('checklist_type', 'pmp'))
            ->label('Planos de Manutenção Preventiva aplicáveis')
            ->itemLabel(fn (array $state): ?string => $state['item_name'])
            ->schema([
                Forms\Components\TextInput::make('item_name')->label('Plano')->disabled()->dehydrated(true),
                Forms\Components\ToggleButtons::make('status')
                    ->label('Conformidade')
                    ->options(['conforme' => 'Conforme', 'nao_conforme' => 'Não Conforme', 'nao_aplicavel' => 'N/A'])
                    ->colors(['conforme' => 'success', 'nao_conforme' => 'danger', 'nao_aplicavel' => 'gray'])
                    ->inline(),
                Forms\Components\TextInput::make('notes')->label('Observações / Evidência'),
                Forms\Components\SpatieMediaLibraryFileUpload::make('photos')
                    ->collection('photos')
                    ->label('Foto')
                    ->image()
                    ->imageResizeMode('contain')
                    ->imageResizeTargetWidth('1600')
                    ->imageResizeTargetHeight('1600')
                    ->imageResizeUpscale(false),
            ])->columns(3)->disableItemCreation()->disableItemDeletion(),
    ]),
```

Réplica direta do padrão de campos já usado na aba Checklist (toggle +
observação + foto redimensionada no navegador, mesma configuração de
`SpatieMediaLibraryFileUpload` documentada com o histórico do bug de
upload de 2026-07-29) — sem inventar um componente novo.

A aba só aparece (`->visible()`) quando a OS tem pelo menos 1 item do tipo
`pmp` — não polui o formulário de OS corretivas/outras que nunca tiveram
plano PMP aplicável.

## Testes

Seguir `superpowers:test-driven-development`:
- `CoberturaPmpTest`: cálculo correto do Status PMP (sem_grupo/em_dia/
  vencendo/vencido) para diferentes combinações de planos e horímetro;
  ação "Abrir OS" cria a `MaintenanceOrder` com os campos certos e
  redireciona; ação não visível quando Status PMP é `em_dia`/`sem_grupo`.
- `MaintenanceOrderChecklistSnapshotObserverTest` (estender o existente,
  se já houver): OS criada para ativo com planos vencidos gera itens
  `checklist_type = 'pmp'` na mesma quantidade de planos aplicáveis;
  OS para ativo sem grupo/sem plano vencido não gera nenhum item pmp;
  múltiplos planos vencidos do mesmo ativo geram múltiplos itens na
  mesma OS (não múltiplas OS).
- `MaintenanceOrderResource` (Filament test): aba "PMP" visível só quando
  há item `checklist_type = pmp`; toggle/observação/foto gravam
  corretamente no `MaintenanceOrderChecklist` certo.
