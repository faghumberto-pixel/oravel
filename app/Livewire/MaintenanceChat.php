<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads; // Trait vital para uploads de arquivos
use App\Models\ChatRoom;
use Filament\Facades\Filament;

class MaintenanceChat extends Component
{
    use WithFileUploads; // Ativa o motor de arquivos temporários do Livewire

    public $maintenance_order_id;
    public $message = '';
    public $photos = []; // Array que recebe os anexos do pátio

    public function render()
    {
        // 🛡️ SEGURANÇA ESTRETA: Bloqueia o carregamento se o funcionário não tiver permissão de leitura
        if (!auth()->user()?->can('ler_chat')) {
            return view('livewire.maintenance-chat', [
                'messages' => collect([]),
                'unauthorized' => true
            ]);
        }

        // BLINDAGEM: Se não houver ID (UUID), retorna uma coleção vazia sem consultar o banco
        if (empty($this->maintenance_order_id)) {
            return view('livewire.maintenance-chat', [
                'messages' => collect([]),
                'unauthorized' => false
            ]);
        }

        // Recupera a sala de chat exclusiva desta OS ou cria uma nova em tempo real se for o primeiro acesso
        $chatRoom = ChatRoom::firstOrCreate(
            [
                'maintenance_order_id' => $this->maintenance_order_id,
                'tenant_id' => Filament::getTenant()?->id ?? auth()->user()->tenant_id,
            ],
            [
                'type' => 'maintenance',
                'title' => "Chat da OS: " . substr($this->maintenance_order_id, 0, 8),
            ]
        );

        // Garante o vínculo do usuário logado na tabela pivot de segurança do chat
        $chatRoom->users()->syncWithoutDetaching([auth()->id()]);

        // Carrega o histórico de mensagens trazendo as mídias anexadas para não engasgar o banco
        $messages = $chatRoom->messages()
            ->with(['user', 'media']) 
            ->oldest()
            ->get();

        return view('livewire.maintenance-chat', [
            'messages' => $messages,
            'chatRoom' => $chatRoom,
            'unauthorized' => false
        ]);
    }

    /**
     * Fluxo de envio de mensagens integrado ao ChatRoom com suporte a mídias
     */
    public function sendMessage()
    {
        // 🛡️ SEGURANÇA BACKEND: Impede a execução de requisições maliciosas direto pelo console
        if (!auth()->user()?->can('ler_chat')) {
            abort(403, 'Ação não autorizada.');
        }

        // Validação flexível: exige texto APENAS se não houver foto anexada
        $this->validate([
            'message' => empty($this->photos) ? 'required|string|max:5000' : 'nullable|string|max:5000',
            'photos.*' => 'nullable|file|mimes:jpg,jpeg,png,mp3,wav,ogg,pdf|max:20480',
        ]);

        if (empty($this->maintenance_order_id)) return;

        $chatRoom = ChatRoom::where('maintenance_order_id', $this->maintenance_order_id)->first();

        if ($chatRoom) {
            // FORÇA BRUTA: Instanciação direta usando o modelo correto ChatMessage
            $chatMessage = new \App\Models\ChatMessage();
            $chatMessage->chat_room_id = $chatRoom->id;
            // $chatMessage->maintenance_order_id foi removido, pois o ChatMessage original não precisa dessa coluna (ele herda da sala)
            $chatMessage->user_id = auth()->id();
            $chatMessage->message = $this->message ?? '';
            // Se o seu ChatMessage original tiver o campo 'type', mantenha. Caso dê erro, pode remover a linha abaixo.
            // $chatMessage->type = 'user'; 
            $chatMessage->save(); // Grava no banco à força

            // Processamento de mídias (Fotos/Áudios) pela Spatie Media Library
            if (!empty($this->photos)) {
                foreach ($this->photos as $photo) {
                    $chatMessage->addMedia($photo)
                        ->toMediaCollection('chat_attachments');
                }
            }

            // Limpa os campos para o próximo envio
            $this->message = '';
            $this->photos = [];
            
            // Dispara o evento para atualização reativa no Livewire v3
            $this->dispatch('messageSent');
        }
    }
}