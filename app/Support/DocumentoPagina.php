<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\Response;

/**
 * Documento (antes um PDF para baixar) mostrado como página, com botão
 * Imprimir -- mesmo padrão do contrato assinado. O PDF gerado pelo
 * servidor perdia acentos e símbolos; o navegador imprime e salva em PDF
 * com a mesma fonte do restante do sistema.
 */
class DocumentoPagina
{
    /**
     * @param  array<string, mixed>  $dados  variáveis da view do documento
     * @param  array<string, string>  $resumo  linhas de destaque no topo (rótulo => valor)
     */
    public static function responder(string $titulo, string $view, array $dados, array $resumo = []): Response
    {
        return response()->view('documentos.visualizar', [
            'titulo' => $titulo,
            'voltar' => url()->previous(),
            'resumo' => $resumo,
            'secoes' => [['titulo' => '', 'html' => view($view, $dados)->render()]],
        ]);
    }
}
