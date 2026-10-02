<?php

namespace App\Filament\Resources\CourseResource\Pages;

use App\Filament\Resources\CourseResource;
use Filament\Resources\Pages\ListRecords;

/**
 * A Academia tem pagina propria (/academia). Esta entrada do Filament so' existe para o menu e
 * a autorizacao (modulo no contrato + permissao): quem chegar aqui e' levado para a Academia.
 */
class ListCourses extends ListRecords
{
    protected static string $resource = CourseResource::class;

    public function mount(): void
    {
        parent::mount();

        $this->redirect(url('/academia'));
    }
}
