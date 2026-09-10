<?php

namespace App\Filament\Central\Pages;

use Filament\Pages\Page;

/**
 * Documentação técnica interna dos módulos do Oravel, pro time que opera
 * o SaaS (painel central) -- não é conteúdo do tenant, por isso vive aqui
 * e não no painel admin. Primeiro artigo: Gestão de EPI (ver conversa que
 * implementou o módulo). Conteúdo hoje é estático (array em
 * getArtigos()) -- se crescer pra precisar de edição pelo time sem
 * deploy, aí sim vira um Model/Resource com CRUD.
 */
class CentralConhecimento extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationLabel = 'Central de Conhecimento';

    protected static ?string $navigationGroup = 'Documentação';

    protected static ?string $title = 'Central de Conhecimento';

    protected static string $view = 'filament.central.pages.central-conhecimento';

    /**
     * @return array<int, array{slug: string, titulo: string, resumo: string, tags: array<int, string>}>
     */
    public function getArtigos(): array
    {
        return [
            [
                'slug' => 'gestao-epi',
                'titulo' => 'Gestão de EPI',
                'resumo' => 'Ciclo de vida do Equipamento de Proteção Individual: cadastro com CA e vida útil, reposição via Compras, ficha de entrega com assinatura digital, e alertas de vencimento — compliance NR-6.',
                'tags' => ['Departamento Pessoal', 'Compliance', 'Suprimentos'],
            ],
        ];
    }
}
