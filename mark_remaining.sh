#!/bin/bash

# ListAbcMatrices
sed -i '/^class ListAbcMatrices/i use App\Filament\Attributes\BelongsToFeature;\n#[BelongsToFeature('"'"'maintenance_matrix'"'"')]' app/Filament/Resources/AbcMatrixResource/Pages/ListAbcMatrices.php

# ListBillCategories
sed -i '/^class ListBillCategories/i use App\Filament\Attributes\BelongsToFeature;\n#[BelongsToFeature('"'"'bill_categories'"'"')]' app/Filament/Resources/BillCategoryResource/Pages/ListBillCategories.php

# ListCompanies
sed -i '/^class ListCompanies/i use App\Filament\Attributes\BelongsToFeature;\n#[BelongsToFeature('"'"'company'"'"')]' app/Filament/Resources/CompanyResource/Pages/ListCompanies.php

# CreateFleetStatus
sed -i '/^class CreateFleetStatus/i use App\Filament\Attributes\BelongsToFeature;\n#[BelongsToFeature('"'"'fleet'"'"')]' app/Filament/Resources/FleetStatusResource/Pages/CreateFleetStatus.php

# EditFleetStatus
sed -i '/^class EditFleetStatus/i use App\Filament\Attributes\BelongsToFeature;\n#[BelongsToFeature('"'"'fleet'"'"')]' app/Filament/Resources/FleetStatusResource/Pages/EditFleetStatus.php

# ViewFleetStatus
sed -i '/^class ViewFleetStatus/i use App\Filament\Attributes\BelongsToFeature;\n#[BelongsToFeature('"'"'fleet'"'"')]' app/Filament/Resources/FleetStatusResource/Pages/ViewFleetStatus.php

# ActivitiesRelationManager
sed -i '/^class ActivitiesRelationManager/i use App\Filament\Attributes\BelongsToFeature;\n#[BelongsToFeature('"'"'maintenance'"'"')]' app/Filament/Resources/MaintenanceOrderResource/RelationManagers/ActivitiesRelationManager.php

# TimeEntriesRelationManager
sed -i '/^class TimeEntriesRelationManager/i use App\Filament\Attributes\BelongsToFeature;\n#[BelongsToFeature('"'"'maintenance'"'"')]' app/Filament/Resources/MaintenanceOrderResource/RelationManagers/TimeEntriesRelationManager.php

# CriticalityChart
sed -i '/^class CriticalityChart/i use App\Filament\Attributes\BelongsToFeature;\n#[BelongsToFeature('"'"'maintenance'"'"')]' app/Filament/Resources/MaintenanceOrderResource/Widgets/CriticalityChart.php

# StatusChart
sed -i '/^class StatusChart/i use App\Filament\Attributes\BelongsToFeature;\n#[BelongsToFeature('"'"'maintenance'"'"')]' app/Filament/Resources/MaintenanceOrderResource/Widgets/StatusChart.php

# ListSolicitacoesLocacao
sed -i '/^class ListSolicitacoesLocacao/i use App\Filament\Attributes\BelongsToFeature;\n#[BelongsToFeature('"'"'rental_requests'"'"')]' app/Filament/Resources/SolicitacaoLocacaoResource/Pages/ListSolicitacoesLocacao.php

# BaseResource (pode ignorar, é abstrata)
# Dashboard
sed -i '/^class Dashboard/i use App\Filament\Attributes\BelongsToFeature;\n#[BelongsToFeature('"'"'users'"'"')]' app/Filament/Pages/Dashboard.php

# RegisterTenant
sed -i '/^class RegisterTenant/i use App\Filament\Attributes\BelongsToFeature;\n#[BelongsToFeature('"'"'company'"'"')]' app/Filament/Pages/Tenancy/RegisterTenant.php

echo "✅ Marcados manualmente!"
