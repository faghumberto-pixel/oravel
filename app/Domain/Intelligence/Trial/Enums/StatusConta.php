<?php

namespace App\Domain\Intelligence\Trial\Enums;

enum StatusConta: string
{
    case Trial = 'trial';
    case TrialExpiring = 'trial_expiring';
    case Active = 'active';
    case Overdue = 'overdue';
    case Canceled = 'canceled';
    case Expired = 'expired';

    public function rotulo(): string
    {
        return match ($this) {
            self::Trial => 'Em teste',
            self::TrialExpiring => 'Teste acabando',
            self::Active => 'Plano ativo',
            self::Overdue => 'Pagamento em atraso',
            self::Canceled => 'Cancelado',
            self::Expired => 'Teste encerrado',
        };
    }

    public function cor(): string
    {
        return match ($this) {
            self::Trial => 'info',
            self::TrialExpiring => 'warning',
            self::Active => 'success',
            self::Overdue => 'danger',
            self::Canceled => 'gray',
            self::Expired => 'danger',
        };
    }

    public static function opcoes(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $s) => [$s->value => $s->rotulo()])->all();
    }
}
