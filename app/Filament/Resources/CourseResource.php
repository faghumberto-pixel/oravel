<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CourseResource\Pages;
use App\Models\Course;
use Illuminate\Database\Eloquent\Builder;

/**
 * Entrada da Academia Oravel no menu do app. A Academia em si e' uma pagina propria
 * (/academia, ver App\Livewire\Academy\*); este Resource existe so' para o menu e para a
 * autorizacao padrao do sistema (modulo no contrato via HasSaaSMetadata + permissao em Perfis
 * de Acesso, ver CoursePolicy). Conteudo global, cadastrado na Central.
 */
class CourseResource extends BaseResource
{
    protected static ?string $model = Course::class;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationLabel = 'Academia Oravel';

    protected static ?string $modelLabel = 'curso';

    protected static ?string $pluralModelLabel = 'Academia Oravel';

    protected static ?int $navigationSort = 99;

    // O atalho "Academia Oravel" fica fixo no topo da sidebar, abaixo de "Início"
    // (vendor/filament-panels/components/sidebar/index.blade.php), sem grupo no menu.
    protected static bool $shouldRegisterNavigation = false;

    protected static bool $isScopedToTenant = false;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->visibleToUser();
    }

    /** O menu leva direto pra pagina propria da Academia. */
    public static function getNavigationUrl(): string
    {
        return url('/academia');
    }

    /**
     * Quem tem cadastro no app acessa a Academia: so' depende do modulo estar ligado no contrato
     * (super admin sempre). Nao usa a permissao de Perfis de Acesso, de proposito.
     */
    public static function canViewAny(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->isSuperAdmin() || (bool) $user->tenant?->hasFeature(Course::saasFeatureKey());
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCourses::route('/'),
        ];
    }
}
