<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;

class ContratoCluster extends Cluster
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Contratos';

    protected static ?string $navigationGroup = 'Comercial';

    protected static ?int $navigationSort = 1;
}
