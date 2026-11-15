<?php

namespace App\Livewire\Central;

use App\Models\Prospect;
use App\Services\ProspectChatService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProspectChat extends Component
{
    public bool $isOpen = false;

    public string $question = '';

    public array $messages = [];

    public bool $isLoading = false;

    public array $lastData = [];

    public string $lastSql = '';

    public function mount(): void
    {
        abort_unless(Auth::user()?->isSuperAdmin(), 403);
    }

    public function toggle(): void
    {
        $this->isOpen = ! $this->isOpen;

        if ($this->isOpen && empty($this->messages)) {
            $this->messages[] = [
                'role' => 'assistant',
                'content' => "Olá! Sou seu assistente de prospecção. Pergunte-me sobre os prospects, por exemplo:\n\n• **\"Quais prospects são EPP e não contatei ainda?\"**\n• **\"Quais estão aguardando retorno?\"**\n• **\"Qual o status dos prospects?\"**\n• **\"Prospects com nota >= 8 na região de Campinas\"**\n• **\"Quantos prospects por lote?\"**\n\nPosso responder em linguagem natural e gerar CSV/Excel para exportar.",
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

        $this->isLoading = true;
        $history = $this->messages;
        $this->messages[] = ['role' => 'user', 'content' => $question, 'data' => [], 'sql' => ''];
        $this->question = '';

        try {
            $service = app(ProspectChatService::class);
            $result = $service->ask($question, $history);

            $this->messages[] = [
                'role' => 'assistant',
                'content' => $result['answer'],
                'data' => $result['data'],
                'sql' => $result['sql'],
                'insights' => $result['raw']['insights'] ?? [],
                'actions' => $result['raw']['suggested_actions'] ?? [],
            ];

            $this->lastData = $result['data'];
            $this->lastSql = $result['sql'];
        } catch (\Throwable $e) {
            $this->messages[] = [
                'role' => 'assistant',
                'content' => 'Erro ao processar: ' . $e->getMessage(),
                'data' => [],
                'sql' => '',
            ];
        } finally {
            $this->isLoading = false;
        }
    }

    public function exportCsv(): ?StreamedResponse
    {
        if (empty($this->lastData)) {
            $this->dispatch('notify', type: 'warning', message: 'Nenhum dado para exportar. Faça uma pergunta que retorne resultados primeiro.');

            return null;
        }

        return app(ProspectChatService::class)->exportCsv($this->lastData, 'prospects-chat');
    }

    public function exportExcel(): ?StreamedResponse
    {
        if (empty($this->lastData)) {
            $this->dispatch('notify', type: 'warning', message: 'Nenhum dado para exportar. Faça uma pergunta que retorne resultados primeiro.');

            return null;
        }

        return app(ProspectChatService::class)->exportExcel($this->lastData, 'prospects-chat');
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

    public function render()
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

        return view('livewire.central.prospect-chat', [
            'totalProspects' => $totalProspects,
            'prospectsAguardando' => $prospectsAguardando,
            'prospectsSemContato' => $prospectsSemContato,
            'prospectsEppNaoContatados' => $prospectsEppNaoContatados,
        ]);
    }
}