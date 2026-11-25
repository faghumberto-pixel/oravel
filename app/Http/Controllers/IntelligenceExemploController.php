<?php

namespace App\Http\Controllers;

use App\Domain\Intelligence\Services\DadosDeExemplo;
use App\Domain\Intelligence\Trial\Services\TrialCiclo;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Botões "Limpar dados de exemplo" e "Ver dados de exemplo" do cliente do teste do Intelligence. */
class IntelligenceExemploController extends Controller
{
    public function limpar(Request $request, DadosDeExemplo $exemplo): RedirectResponse
    {
        $trial = TrialCiclo::doTenant($request->user()->tenant_id) ?? abort(404);
        $n = $exemplo->limpar((string) $trial->tenant_id);

        Notification::make()->success()
            ->title($n > 0 ? 'Dados de exemplo apagados' : 'Não havia dados de exemplo')
            ->body($n > 0 ? 'Agora é só enviar a sua planilha para ver os seus números.' : null)->send();

        return back();
    }

    public function gerar(Request $request, DadosDeExemplo $exemplo): RedirectResponse
    {
        $trial = TrialCiclo::doTenant($request->user()->tenant_id) ?? abort(404);

        if (! DadosDeExemplo::podeGerar($trial)) {
            $motivo = DadosDeExemplo::expirou($trial) ? 'O prazo dos dados de exemplo já terminou.' : (DadosDeExemplo::temDadoReal((string) $trial->tenant_id) ? 'Você já tem dados próprios na conta.' : 'Os dados de exemplo já estão na conta.');
            Notification::make()->warning()->title('Não foi possível mostrar o exemplo')->body($motivo)->send();

            return back();
        }

        try {
            $exemplo->gerar($trial->tenant);
            Notification::make()->success()->title('Dados de exemplo carregados')->body('São fictícios. Limpe-os quando for começar com os seus.')->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->danger()->title('Não consegui carregar o exemplo agora. Tente de novo em instantes.')->send();
        }

        return back();
    }
}
