<?php

namespace App\Livewire\Central;

use App\Models\AssistenteRelatorio;
use App\Models\Prospect;
use App\Services\ProspectChatService;
use App\Support\Assistente\Areas;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProspectChat extends Component
{
    public bool $isOpen = false;

    /** Tela cheia (app do celular /assistente): ja abre aberto, sem fechar/gatilho. */
    public bool $fullscreen = false;

    /** Embutido numa página (ex.: Dashboard do Intelligence): painel inline, já aberto, sem gatilho flutuante nem fundo escurecido. */
    public bool $embutido = false;

    public string $question = '';

    public array $messages = [];

    public bool $isLoading = false;

    public array $lastData = [];

    public string $lastSql = '';

    public function mount(bool $fullscreen = false, bool $embutido = false): void
    {
        $this->autorizar();

        if ($fullscreen || $embutido) {
            $this->fullscreen = $fullscreen;
            $this->embutido = $embutido;
            $this->toggle(); // abre e monta a mensagem de boas-vindas
        }
    }

    public function toggle(): void
    {
        $this->isOpen = ! $this->isOpen;

        if ($this->isOpen && empty($this->messages)) {
            $this->messages[] = [
                'role' => 'assistant',
                'content' => $this->boasVindas(),
                'data' => [],
                'sql' => '',
            ];
        }
    }

    public function send(): void
    {
        $question = trim($this->question);

        if ($question === '' || $this->isLoading) {
            return;
        }

        if (($limite = $this->limitePorHora()) !== null && ! RateLimiter::attempt('assistente:'.static::class.':'.Auth::id(), $limite, fn () => true, 3600)) {
            $this->messages[] = ['role' => 'assistant', 'content' => 'Você atingiu o limite de perguntas por hora. Tente de novo mais tarde.', 'data' => [], 'sql' => ''];

            return;
        }

        if (($bloqueio = $this->reservarCota()) !== null) {
            $this->messages[] = ['role' => 'assistant', 'content' => $bloqueio, 'data' => [], 'sql' => ''];

            return;
        }

        $this->isLoading = true;
        $history = $this->messages;
        $this->messages[] = ['role' => 'user', 'content' => $question, 'data' => [], 'sql' => ''];
        $this->question = '';

        try {
            $result = $this->servico()->ask($question, $history);

            // Falha da IA volta como resultado (raw.error), sem exceção: o cliente não recebeu resposta, a pergunta do dia é devolvida.
            if (! empty($result['raw']['error'])) {
                $this->devolverCota();
            }

            $mensagem = [
                'role' => 'assistant',
                'content' => $result['answer'],
                'data' => $result['data'],
                'sql' => $result['sql'],
                'insights' => $result['raw']['insights'] ?? [],
                'actions' => $result['raw']['suggested_actions'] ?? [],
            ];

            // Relatorio compartilhavel (so nas areas que usam): foto da consulta com link.
            if ($this->comRelatorio() && ! empty($result['data'])) {
                $resumo = trim((string) Str::of($result['answer'])->replaceMatches('/[^.!?]*(botões|botão|baixar)[^.!?]*[.!?]/iu', ''));
                $relatorio = AssistenteRelatorio::criar($this->area(), $question, $resumo, $result['data'], (string) Auth::id());
                $mensagem['relatorio'] = [
                    'url' => $relatorio->url(),
                    'whatsapp' => 'https://wa.me/?text='.rawurlencode("Relatório Oravel — {$relatorio->titulo}\n{$relatorio->url()}"),
                ];
            }

            $this->messages[] = $mensagem;

            $this->lastData = $result['data'];
            $this->lastSql = $result['sql'];
        } catch (\Throwable $e) {
            $this->devolverCota();
            $this->messages[] = [
                'role' => 'assistant',
                'content' => $this->mensagemErro($e),
                'data' => [],
                'sql' => '',
            ];
        } finally {
            $this->isLoading = false;
        }
    }

    /**
     * Imprimir/salvar em PDF a conversa (perguntas e respostas): vira um relatório compartilhável (a mesma página das respostas com
     * tabela, que já tem "Imprimir / salvar PDF") com uma linha por pergunta. O link entra na própria conversa. Só nas áreas com relatório.
     */
    public function imprimirConversa(): void
    {
        $this->autorizar(); // o estado do componente vem do navegador: reconfere quem está pedindo
        if (! $this->comRelatorio()) {
            return;
        }

        $linhas = [];
        $pergunta = null;
        foreach ($this->messages as $m) {
            $papel = $m['role'] ?? '';
            if ($papel === 'user') {
                $pergunta = (string) ($m['content'] ?? '');
            } elseif ($papel === 'assistant' && $pergunta !== null) {
                $linhas[] = ['Pergunta' => Str::limit($pergunta, 2000, '…'), 'Resposta' => Str::limit(trim(str_replace('**', '', (string) ($m['content'] ?? ''))), 8000, '…')];
                $pergunta = null;
            }
        }
        if ($linhas === []) {
            $this->dispatch('notify', type: 'warning', message: 'Faça uma pergunta primeiro: ainda não há conversa para imprimir.');

            return;
        }

        $relatorio = AssistenteRelatorio::criar($this->area(), 'Conversa com o assistente, '.now()->format('d/m/Y H:i'), 'Perguntas e respostas desta conversa.', array_slice($linhas, -100), (string) Auth::id());
        $this->messages[] = [
            'role' => 'assistant',
            'content' => 'Conversa pronta. Abra o relatório e use "Imprimir / salvar PDF". O link vale por '.AssistenteRelatorio::VALIDADE_DIAS.' dias.',
            'data' => [], 'sql' => '',
            'relatorio' => ['url' => $relatorio->url(), 'whatsapp' => 'https://wa.me/?text='.rawurlencode("Conversa Oravel\n{$relatorio->url()}")],
        ];
    }

    public function exportCsv(?int $index = null): ?StreamedResponse
    {
        $data = $index !== null ? ($this->messages[$index]['data'] ?? []) : $this->lastData;

        if (empty($data)) {
            $this->dispatch('notify', type: 'warning', message: 'Nenhum dado para exportar. Faça uma pergunta que retorne resultados primeiro.');

            return null;
        }

        return $this->servico()->exportCsv($data, $this->area().'-chat');
    }

    public function exportExcel(?int $index = null): ?StreamedResponse
    {
        $data = $index !== null ? ($this->messages[$index]['data'] ?? []) : $this->lastData;

        if (empty($data)) {
            $this->dispatch('notify', type: 'warning', message: 'Nenhum dado para exportar. Faça uma pergunta que retorne resultados primeiro.');

            return null;
        }

        return $this->servico()->exportExcel($data, $this->area().'-chat');
    }

    public function clearChat(): void
    {
        $this->messages = [];
        $this->lastData = [];
        $this->lastSql = '';
    }

    #[On('send-question')]
    public function handleSendQuestion(string $question): void
    {
        $this->question = $question;
        $this->send();
    }

    // ---- Ganchos: a area Suprimentos (e outras) sobrescreve so isto ----

    protected function autorizar(): void
    {
        abort_unless(Auth::user()?->isSuperAdmin(), 403);
    }

    protected function servico(): ProspectChatService
    {
        return app(ProspectChatService::class);
    }

    protected function area(): string
    {
        return 'prospects';
    }

    protected function comRelatorio(): bool
    {
        return false;
    }

    /** Maximo de perguntas por usuario por hora (custo de IA); null = sem limite. */
    protected function limitePorHora(): ?int
    {
        return null;
    }

    /** Gancho de cota própria da área: devolve a mensagem de bloqueio quando a cota acabou (null = pode perguntar). */
    protected function reservarCota(): ?string
    {
        return null;
    }

    /** Gancho: a pergunta falhou, devolve a cota reservada em reservarCota(). */
    protected function devolverCota(): void {}

    protected function mensagemErro(\Throwable $e): string
    {
        return 'Erro ao processar: '.$e->getMessage();
    }

    protected function boasVindas(): string
    {
        return "Olá! Sou seu assistente de prospecção. Pergunte-me sobre os prospects, por exemplo:\n\n• **\"Quais prospects são EPP e não contatei ainda?\"**\n• **\"Quais estão aguardando retorno?\"**\n• **\"Qual o status dos prospects?\"**\n• **\"Prospects com nota >= 8 na região de Campinas\"**\n• **\"Quantos prospects por lote?\"**\n\nPosso responder em linguagem natural e gerar CSV/Excel para exportar.";
    }

    /** @return array<string, mixed> variaveis extras da tela (titulo, estatisticas...) */
    protected function dadosDaTela(): array
    {
        $totalProspects = Prospect::count();
        $prospectsAguardando = Prospect::whereNotNull('proximo_contato')
            ->where('proximo_contato', '<=', now())
            ->count();
        $prospectsSemContato = Prospect::whereNull('ultimo_contato_em')
            ->where('status', 'nao_enviado')
            ->count();
        // SQLite não suporta ILIKE, usa LOWER() LIKE para compatibilidade
        $prospectsEppNaoContatados = Prospect::where(function ($q) {
            $q->whereRaw('LOWER(porte) LIKE ?', ['%epp%'])
                ->orWhereRaw('LOWER(atividade) LIKE ?', ['%epp%']);
        })
            ->where('status', 'nao_enviado')
            ->whereNull('ultimo_contato_em')
            ->count();

        return [
            'tituloApp' => 'Assistente de Prospecção',
            'subtituloApp' => "{$totalProspects} prospects",
            'placeholder' => 'Digite sua pergunta sobre os prospects...',
            'mostrarStats' => true,
            'badge' => $prospectsAguardando + $prospectsSemContato,
            'totalProspects' => $totalProspects,
            'prospectsAguardando' => $prospectsAguardando,
            'prospectsSemContato' => $prospectsSemContato,
            'prospectsEppNaoContatados' => $prospectsEppNaoContatados,
        ];
    }

    public function render()
    {
        return view('livewire.central.prospect-chat', $this->dadosDaTela() + ['tema' => Areas::tema($this->area())]);
    }
}
