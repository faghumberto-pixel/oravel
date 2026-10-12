<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use App\Support\CentralNotifications;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/** Notificações do app do colaborador: lista (sino), limpar e inscrição de push do aparelho. */
class ColaboradorNotificacoesController extends Controller
{
    /** Avisos do próprio usuário; os da operação do SaaS (Central) nunca aparecem aqui. */
    private function avisos(Request $request)
    {
        $query = $request->user()->notifications();

        return $request->user()->isSuperAdmin() ? $query->whereRaw('1 = 0') : CentralNotifications::exclude($query);
    }

    public function contarNaoLidas(Request $request): int
    {
        return $this->avisos($request)->whereNull('read_at')->count();
    }

    public function index(Request $request): View
    {
        $avisos = $this->avisos($request)->latest()->limit(50)->get();
        $naoLidas = $avisos->whereNull('read_at')->pluck('id');

        // Abrir a lista marca como lido (o selo do início zera), mas só depois de montar a tela.
        $lista = $avisos->map(fn ($n) => [
            'titulo' => $n->data['title'] ?? 'Aviso',
            'corpo' => isset($n->data['body']) ? strip_tags((string) $n->data['body']) : null,
            'quando' => $n->created_at?->diffForHumans(),
            'nova' => $naoLidas->contains($n->id),
        ]);

        $request->user()->notifications()->whereIn('id', $naoLidas)->update(['read_at' => now()]);

        return view('colaborador.notificacoes', ['avisos' => $lista]);
    }

    public function limpar(Request $request): RedirectResponse
    {
        $this->avisos($request)->delete();

        return redirect()->route('colab.notificacoes');
    }

    public function assinar(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'endpoint' => 'required|url|max:2000',
            'keys.p256dh' => 'required|string|max:255',
            'keys.auth' => 'required|string|max:255',
        ]);

        PushSubscription::withoutGlobalScopes()->updateOrCreate(
            ['endpoint_hash' => hash('sha256', $dados['endpoint'])],
            [
                'tenant_id' => $request->user()->tenant_id,
                'user_id' => $request->user()->id,
                'endpoint' => $dados['endpoint'],
                'p256dh' => $dados['keys']['p256dh'],
                'auth' => $dados['keys']['auth'],
                'user_agent' => substr((string) $request->userAgent(), 0, 250),
            ],
        );

        return response()->json(['ok' => true]);
    }

    public function cancelar(Request $request): JsonResponse
    {
        $request->validate(['endpoint' => 'required|url|max:2000']);

        PushSubscription::withoutGlobalScopes()
            ->where('user_id', $request->user()->id)
            ->where('endpoint_hash', hash('sha256', $request->input('endpoint')))
            ->delete();

        return response()->json(['ok' => true]);
    }

    /** Service worker do app: mostra o push e abre o app ao tocar na notificação. */
    public function serviceWorker(): Response
    {
        $js = <<<'JS'
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (e) => e.waitUntil(self.clients.claim()));
self.addEventListener('push', (event) => {
    let d = {};
    try { d = event.data ? event.data.json() : {}; } catch (e) { d = { title: 'Oravel', body: event.data ? event.data.text() : '' }; }
    event.waitUntil(self.registration.showNotification(d.title || 'Oravel', {
        body: d.body || '', icon: '/icon.png', badge: '/icon.png', data: { url: d.url || '/' },
    }));
});
self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = (event.notification.data && event.notification.data.url) || '/';
    event.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((lista) => {
        for (const c of lista) { if ('focus' in c) { c.navigate(url); return c.focus(); } }
        return self.clients.openWindow(url);
    }));
});
JS;

        return response($js, 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Service-Worker-Allowed' => '/',
            'Cache-Control' => 'no-cache',
        ]);
    }
}
