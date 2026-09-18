# Menu Horizontal Layout - Backup (2026-09-18)

Este arquivo contém o código de configuração do menu horizontal atual, guardado antes de testar a migração para sidebar.

**Quando reabilitar:** Se o teste de sidebar não der certo, use estes códigos para reverter.

---

## AdminPanelProvider.php

Localização: `app/Providers/Filament/AdminPanelProvider.php`

A configuração atual do menu está na linha 41:

```php
->topNavigation()
```

Esta é a linha que ativa o menu horizontal (top navigation). Para reabilitá-lo após testar sidebar, certifique-se que esta linha está presente.

### Configuração completa da navegação (linhas 57-105):

```php
->navigationGroups([
    NavigationGroup::make('PMP'),
    NavigationGroup::make('Manutenção'),
    NavigationGroup::make('Logística'),
    NavigationGroup::make('Ativos e Materiais'),
    NavigationGroup::make('Equipe'),
    NavigationGroup::make('Departamento Pessoal'),
    NavigationGroup::make('Comercial'),
    NavigationGroup::make('Financeiro'),
    NavigationGroup::make('Relatórios'),
    NavigationGroup::make('Configurações'),
])
->navigationItems([
    // Tela dedicada de registro de horimetro (offline-first, JS
    // puro) -- rota comum (HourMeterOfflineController), nao uma
    // Filament Page, entao entra no menu via NavigationItem em
    // vez de discoverPages(). Mesma visibilidade de "Ativos"
    // (viewAny Asset), nao a permissao granular restrita que
    // ApontamentoHorimetro (pagina desktop) exige -- essa aqui
    // e' pensada pro tecnico comum, nao so pra quem tem
    // 'criar_apontamento_horimetro'.
    NavigationItem::make('Registrar Horímetro')
        ->icon('heroicon-o-clock')
        ->group('Manutenção')
        ->sort(-8)
        ->url(fn () => route('hour-meter.offline'))
        ->visible(fn () => (bool) auth()->user()?->can('viewAny', Asset::class)),

    // Mesmo padrão -- so' aparece pra quem tem Employee vinculado
    // ao próprio User (TimeClockOfflineController::show() aborta
    // 404 sem isso, o link nem precisa aparecer nesse caso).
    NavigationItem::make('Bater Ponto')
        ->icon('heroicon-o-finger-print')
        ->group('Departamento Pessoal')
        ->sort(-8)
        ->url(fn () => route('time-clock.offline'))
        ->visible(fn () => Employee::where('user_id', auth()->id())->exists()),

    // 3 novas features financeiras
    NavigationItem::make('Fluxo de Caixa')
        ->icon('heroicon-o-chart-bar')
        ->group('Financeiro')
        ->url('/admin/fluxo-de-caixa'),

    NavigationItem::make('Conciliação Bancária')
        ->icon('heroicon-o-arrow-path')
        ->group('Financeiro')
        ->url('/admin/conciliacao-bancaria'),
])
```

---

## Menu Items por Grupo

### Grupos de Navegação (linha 57-68):
- **PMP** - Planning & Project Management
- **Manutenção** - Manutenção e Manutenção Preventiva
- **Logística** - Logística e Suprimentos
- **Ativos e Materiais** - Gerenciamento de Ativos
- **Equipe** - Gestão de Pessoal (inclui FSM após 2026-09-18)
- **Departamento Pessoal** - RH
- **Comercial** - CRM e Vendas
- **Financeiro** - Contas a Receber/Pagar, Fluxo de Caixa, Conciliação
- **Relatórios** - Relatórios
- **Configurações** - Configurações do Sistema

### Items Customizados (linha 69-105):
1. **Registrar Horímetro** → Manutenção (sort: -8, requer viewAny Asset)
2. **Bater Ponto** → Departamento Pessoal (sort: -8, requer Employee vinculado)
3. **Fluxo de Caixa** → Financeiro
4. **Conciliação Bancária** → Financeiro

---

## Como Reverter para Sidebar (Se Necessário)

Se o teste de sidebar não der certo:

1. **Remova a linha 41:**
   ```php
   ->topNavigation()
   ```

2. **Adicione no lugar (ou use método alternativo):**
   ```php
   ->sidebarCollapsibleOnDesktop()
   ```

3. **Mantenha toda a configuração de navigationGroups e navigationItems** (linhas 57-105)

---

## Referência de Alterações Recentes (2026-09-18)

- ✅ "Assinar Contrato" removido do menu (ContractSignature::$shouldRegisterNavigation = false)
- ✅ "FSM (PMOC)" movido de 'FSM — Serviços Técnicos' para 'Equipe'

---

## Data do Backup
**2026-09-18** - Após deploy das mudanças de navegação
