<?php

namespace App\Filament\Pages;

use App\Models\Client;
use App\Models\TenantWhatsappSetting;
use App\Models\WhatsappConversa;
use App\Models\WhatsappNumero;
use App\Services\WhatsAppEmpresaService;
use App\Support\Tenancy;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Conversas de WhatsApp da empresa. Cada pessoa vê e responde as conversas do PRÓPRIO
 * número; quem não tem número próprio usa o da empresa. Administradores podem ver todas.
 */
class CaixaWhatsApp extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-ellipsis';

    protected static ?string $navigationGroup = 'Comercial';

    protected static ?string $navigationLabel = 'WhatsApp';

    protected static ?int $navigationSort = 12;

    protected static ?string $title = 'WhatsApp';

    protected static ?string $slug = 'whatsapp';

    protected static string $view = 'filament.pages.caixa-whatsapp';

    public ?string $conversaId = null;

    public string $texto = '';

    public string $busca = '';

    public bool $verTodas = false;

    public string $novoTelefone = '';

    public string $novoNome = '';

    public static function canAccess(): bool
    {
        return (bool) auth()->user() && WhatsAppEmpresaService::moduloLiberado(Tenancy::current()?->id) && TenantWhatsappSetting::withoutGlobalScopes()->where('tenant_id', Tenancy::current()?->id)->where('enabled', true)->exists();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    /** Quem atende a fila do número da empresa: os atendentes escolhidos; sem escolha, quem não tem número próprio. */
    private function atendeAFila(): bool
    {
        $usuario = auth()->user();
        $config = TenantWhatsappSetting::withoutGlobalScopes()->where('tenant_id', $usuario->tenant_id)->first();
        $atendentes = (array) ($config?->atendentes ?? []);

        if ($atendentes !== []) {
            return in_array($usuario->id, $atendentes, true);
        }

        return ! WhatsappNumero::withoutGlobalScopes()->where('tenant_id', $usuario->tenant_id)->where('user_id', $usuario->id)->exists();
    }

    /**
     * Conversas que a pessoa pode ver: as dela, e a fila do número da empresa (sem responsável) quando ela é
     * atendente. Conversas atribuídas a outra pessoa só o administrador vê, com "Ver as conversas de todos".
     */
    private function consulta(): Builder
    {
        $usuario = auth()->user();
        $fila = $this->atendeAFila();

        return WhatsappConversa::withoutGlobalScopes()
            ->where('tenant_id', $usuario->tenant_id)
            ->when(! ($this->verTodas && $usuario->isAdmin()), function (Builder $q) use ($usuario, $fila) {
                $q->where(function (Builder $q) use ($usuario, $fila) {
                    $q->where('responsavel_user_id', $usuario->id);

                    if ($fila || $usuario->isAdmin()) {
                        $q->orWhere(fn (Builder $f) => $f->whereNull('responsavel_user_id')->whereHas('numero', fn (Builder $n) => $n->whereNull('user_id')));
                    }
                });
            });
    }

    /** @return Collection<int, WhatsappConversa> */
    public function conversas(): Collection
    {
        return $this->consulta()
            ->with(['client', 'lead', 'responsavel', 'numero'])
            ->when(filled($this->busca), function (Builder $q) {
                $b = '%'.mb_strtolower(trim($this->busca)).'%';
                $q->where(fn (Builder $q) => $q->whereRaw('lower(nome) like ?', [$b])->orWhere('telefone', 'like', '%'.preg_replace('/\D+/', '', $this->busca).'%'));
            })
            ->orderByDesc('ultima_mensagem_em')
            ->limit(80)
            ->get();
    }

    public function ativa(): ?WhatsappConversa
    {
        return $this->conversaId ? $this->consulta()->with(['mensagens.enviadaPor', 'client', 'lead', 'numero'])->find($this->conversaId) : null;
    }

    public function seuNumero(): ?WhatsappNumero
    {
        $usuario = auth()->user();
        $numeros = WhatsappNumero::withoutGlobalScopes()->where('tenant_id', $usuario->tenant_id)->where('enabled', true);

        return (clone $numeros)->where('user_id', $usuario->id)->first() ?? (clone $numeros)->whereNull('user_id')->first();
    }

    public function selecionar(string $id): void
    {
        $conversa = $this->consulta()->find($id);

        if ($conversa) {
            $this->conversaId = $conversa->id;
            $conversa->update(['nao_lidas' => 0]);
        }
    }

    public function enviar(): void
    {
        $conversa = $this->conversaId ? $this->consulta()->with('numero')->find($this->conversaId) : null;
        $texto = trim($this->texto);

        if (! $conversa || $texto === '') {
            return;
        }

        $servico = WhatsAppEmpresaService::paraNumero($conversa->numero);

        if (! $servico) {
            Notification::make()->title('WhatsApp desligado')->body('Peça ao administrador para ligar o WhatsApp da empresa.')->warning()->send();

            return;
        }

        $mensagem = $servico->enviarTexto($conversa, $texto, auth()->user());
        $this->texto = '';

        if ($mensagem->status === 'falhou') {
            Notification::make()->title('Não foi enviada')->body((string) $mensagem->erro)->danger()->send();
        }
    }

    /** Fora da janela de 24 h: abre a conversa com o modelo aprovado. */
    public function enviarAbertura(): void
    {
        $conversa = $this->conversaId ? $this->consulta()->with('numero')->find($this->conversaId) : null;
        $config = TenantWhatsappSetting::withoutGlobalScopes()->where('tenant_id', Tenancy::current()?->id)->first();
        $servico = $conversa ? WhatsAppEmpresaService::paraNumero($conversa->numero) : null;

        if (! $servico || blank($config?->template_abertura)) {
            Notification::make()->title('Modelo de abertura não cadastrado')->body('O administrador precisa informar o modelo aprovado em Configurações → WhatsApp da Empresa.')->warning()->send();

            return;
        }

        $nome = explode(' ', trim($conversa->titulo()))[0];
        $mensagem = $servico->enviarModelo($conversa, $config->template_abertura, [$nome], auth()->user());

        if ($mensagem->status === 'falhou') {
            Notification::make()->title('Não foi enviada')->body((string) $mensagem->erro)->danger()->send();
        }
    }

    /** Pega uma conversa da fila para si. */
    public function assumir(string $id): void
    {
        $conversa = $this->consulta()->whereNull('responsavel_user_id')->find($id);

        if (! $conversa) {
            Notification::make()->title('Esta conversa já tem responsável')->warning()->send();

            return;
        }

        $conversa->update(['responsavel_user_id' => auth()->id(), 'atribuida_em' => now(), 'nao_lidas' => 0]);
        Notification::make()->title('Conversa sua agora')->success()->send();
    }

    /** Passa a conversa para outra pessoa (quem atende a conversa ou o administrador). */
    public function transferir(string $id, string $paraUserId): void
    {
        $usuario = auth()->user();
        $conversa = $this->consulta()->find($id);
        $destino = \App\Models\User::withoutGlobalScopes()->where('tenant_id', $usuario->tenant_id)->where('is_approved', true)->find($paraUserId);

        if (! $conversa || ! $destino || ! ($usuario->isAdmin() || $conversa->responsavel_user_id === $usuario->id)) {
            Notification::make()->title('Não foi possível transferir')->warning()->send();

            return;
        }

        $conversa->update(['responsavel_user_id' => $destino->id, 'atribuida_em' => now()]);

        Notification::make()->title('Nova conversa de WhatsApp para você')
            ->body($conversa->titulo().' foi passada por '.$usuario->name.'.')->info()->sendToDatabase($destino);
        Notification::make()->title('Conversa transferida para '.$destino->name)->success()->send();

        if (! $this->verTodas && ! $usuario->isAdmin()) {
            $this->conversaId = null;
        }
    }

    /** Devolve ao número da empresa sem responsável (só conversas do número da empresa). */
    public function devolverFila(string $id): void
    {
        $usuario = auth()->user();
        $conversa = $this->consulta()->with('numero')->find($id);

        if (! $conversa || $conversa->numero?->user_id || ! ($usuario->isAdmin() || $conversa->responsavel_user_id === $usuario->id)) {
            Notification::make()->title('Não foi possível devolver à fila')->warning()->send();

            return;
        }

        $conversa->update(['responsavel_user_id' => null, 'atribuida_em' => null]);
        $this->conversaId = null;
        Notification::make()->title('Conversa devolvida à fila')->success()->send();
    }

    /** @return array<string, string> pessoas para quem dá para transferir */
    public function colegas(): array
    {
        return \App\Models\User::withoutGlobalScopes()->where('tenant_id', auth()->user()->tenant_id)->where('is_approved', true)->where('id', '!=', auth()->id())->orderBy('name')->pluck('name', 'id')->all();
    }

    public function novaConversa(): void
    {
        $servico = WhatsAppEmpresaService::paraUsuario(auth()->user());
        $telefone = WhatsAppEmpresaService::normalizarTelefone($this->novoTelefone);

        if (! $servico || strlen($telefone) < 12) {
            Notification::make()->title('Informe um telefone válido')->body('Com DDD, por exemplo (19) 99999-0000.')->warning()->send();

            return;
        }

        $conversa = $servico->conversa($telefone, filled($this->novoNome) ? trim($this->novoNome) : null);
        $this->novoTelefone = $this->novoNome = '';
        $this->conversaId = $conversa->id;
    }

    /** Clientes da empresa com WhatsApp, para começar conversa com um clique. */
    public function clientesComWhatsapp(): Collection
    {
        return Client::withoutGlobalScopes()->where('tenant_id', auth()->user()->tenant_id)->whereNotNull('whatsapp')->where('whatsapp', '!=', '')->orderBy('name')->limit(200)->get(['id', 'name', 'whatsapp']);
    }

    public function comecarComCliente(string $clienteId): void
    {
        $cliente = Client::withoutGlobalScopes()->where('tenant_id', auth()->user()->tenant_id)->find($clienteId);

        if ($cliente) {
            $this->novoTelefone = (string) $cliente->whatsapp;
            $this->novoNome = $cliente->name;
            $this->novaConversa();
        }
    }
}
