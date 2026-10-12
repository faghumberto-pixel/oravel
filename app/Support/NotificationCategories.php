<?php

namespace App\Support;

use App\Models\User;
use App\Notifications\ContaPagarNotification;
use App\Notifications\ContaReceberNotification;
use App\Notifications\ContractOverageCalculatedNotification;
use App\Notifications\MaintenanceDueNotification;

/**
 * Categorias de aviso do sino que cada pessoa pode ligar/desligar em "Minhas notificações".
 * Avisos de avaria e de contrato assinado são obrigatórios (fluxo jurídico/segurança) e nunca
 * são bloqueados, mesmo que a preferência esteja gravada. A classificação é pelo título (os avisos
 * do Filament não têm tipo) ou pela classe, quando é uma Notification própria.
 */
class NotificationCategories
{
    /** @var array<string, array{label: string, mandatory: bool, pattern: ?string}> */
    private const CATEGORIES = [
        'avarias' => ['label' => 'Avarias de equipamento', 'mandatory' => true, 'pattern' => '/avaria/iu'],
        'contrato_assinado' => ['label' => 'Contrato assinado', 'mandatory' => true, 'pattern' => '/^Contrato assinado/iu'],
        'compromissos' => ['label' => 'Compromissos de vendas (vencidos / em breve)', 'mandatory' => false, 'pattern' => '/^Compromisso (vencido|em breve)/iu'],
        'pendencias' => ['label' => 'Pendências de ordens de serviço', 'mandatory' => false, 'pattern' => '/^Nova pend[êe]ncia/iu'],
        'ordens_servico' => ['label' => 'Ordens de serviço e manutenção preventiva', 'mandatory' => false, 'pattern' => '/\bO\.?S\b|preventiva|manuten[çc][ãa]o/iu'],
        'estoque' => ['label' => 'Estoque e materiais', 'mandatory' => false, 'pattern' => '/estoque|saldo negativo|almoxarifado|material/iu'],
        'mensagens' => ['label' => 'Mensagens internas e WhatsApp', 'mandatory' => false, 'pattern' => '/whatsapp|^Nova mensagem/iu'],
        'comercial' => ['label' => 'Propostas, reservas e trocas de equipamento', 'mandatory' => false, 'pattern' => '/proposta|reserva|despacho|troca de equipamento|local de instala/iu'],
        'ativos' => ['label' => 'Alertas de ativos', 'mandatory' => false, 'pattern' => '/alerta cr[íi]tico|ativo/iu'],
        'financeiro' => ['label' => 'Financeiro (contas e cobranças)', 'mandatory' => false, 'pattern' => null],
        'outros' => ['label' => 'Outros avisos e comunicados', 'mandatory' => false, 'pattern' => null],
    ];

    private const BY_CLASS = [
        ContaPagarNotification::class => 'financeiro',
        ContaReceberNotification::class => 'financeiro',
        ContractOverageCalculatedNotification::class => 'financeiro',
        MaintenanceDueNotification::class => 'ordens_servico',
    ];

    /** @return array<string, string> chave => rótulo, só das categorias que podem ser desligadas */
    public static function optional(): array
    {
        $options = [];
        foreach (self::CATEGORIES as $key => $category) {
            if (! $category['mandatory']) {
                $options[$key] = $category['label'];
            }
        }

        return $options;
    }

    /** @return array<string, string> */
    public static function mandatory(): array
    {
        $options = [];
        foreach (self::CATEGORIES as $key => $category) {
            if ($category['mandatory']) {
                $options[$key] = $category['label'];
            }
        }

        return $options;
    }

    public static function classify(?string $title, ?string $notificationClass = null): string
    {
        // Obrigatórias primeiro: "Avaria aguardando revisão" tem que cair em avarias, não em outra.
        foreach (self::CATEGORIES as $key => $category) {
            if ($category['mandatory'] && $title !== null && preg_match($category['pattern'], $title)) {
                return $key;
            }
        }

        if ($notificationClass !== null && isset(self::BY_CLASS[$notificationClass])) {
            return self::BY_CLASS[$notificationClass];
        }

        if ($title !== null) {
            foreach (self::CATEGORIES as $key => $category) {
                if (! $category['mandatory'] && $category['pattern'] && preg_match($category['pattern'], $title)) {
                    return $key;
                }
            }
        }

        return 'outros';
    }

    public static function isMuted(User $user, string $category): bool
    {
        if (self::CATEGORIES[$category]['mandatory'] ?? false) {
            return false;
        }

        return in_array($category, $user->muted_notification_categories ?? [], true);
    }
}
