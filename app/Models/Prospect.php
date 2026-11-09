<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Prospect da Oravel (empresa que ainda nao e' cliente) com o acompanhamento da abordagem comercial.
 * Tabela GLOBAL (sem tenant_id): controle interno da Oravel, so' super admin.
 */
class Prospect extends Model
{
    use HasUuids;

    public const STATUS = [
        'nao_enviado' => 'Não enviei',
        'enviado' => 'Enviei',
        'respondeu' => 'Respondeu',
        'em_teste' => 'Em teste',
        'sem_interesse' => 'Sem interesse',
        'convertido' => 'Virou lead',
    ];

    /** Estagio / resultado do ultimo contato (campo livre de funil, alem da "situacao" da abordagem). */
    public const RESULTADOS = [
        'nao_atendeu' => 'Não atendeu a ligação',
        'nao_respondeu' => 'Não respondeu a mensagem',
        'numero_invalido' => 'Telefone inválido / não é a empresa',
        'ja_tem_sistema' => 'Já tem sistema',
        'sem_interesse_momento' => 'Sem interesse no momento',
        'sem_interesse' => 'Sem interesse',
        'pediu_email' => 'Pediu para enviar e-mail',
        'quer_reuniao' => 'Quer agendar reunião',
        'reuniao_agendada' => 'Reunião agendada',
        'proposta_enviada' => 'Proposta enviada',
        'contrato_andamento' => 'Contrato em andamento',
        'fechado' => 'Fechado (virou cliente)',
    ];

    /** Cor (Filament) da etiqueta de cada situacao e resultado na lista de Prospects. */
    public const STATUS_CORES = [
        'nao_enviado' => 'orange', 'enviado' => 'blue', 'respondeu' => 'teal',
        'em_teste' => 'primary', 'sem_interesse' => 'danger', 'convertido' => 'success',
    ];

    public const RESULTADO_CORES = [
        'nao_atendeu' => 'danger', 'nao_respondeu' => 'danger', 'numero_invalido' => 'danger',
        'ja_tem_sistema' => 'gray', 'sem_interesse_momento' => 'warning', 'sem_interesse' => 'danger',
        'pediu_email' => 'info', 'quer_reuniao' => 'success', 'reuniao_agendada' => 'success',
        'proposta_enviada' => 'primary', 'contrato_andamento' => 'primary', 'fechado' => 'success',
    ];

    public const CANAIS = ['whatsapp' => 'WhatsApp', 'telefone' => 'Telefone', 'email' => 'E-mail', 'visita' => 'Visita', 'outro' => 'Outro'];

    public const FOLLOW_UP = 'Olá, {nome}. Só passando para dizer que o convite dos 30 dias grátis continua de pé. Se quiser, deixo o cadastro dos seus equipamentos montado e você só confere. Se não for o momento, sem problema.';

    /** Respostas prontas para quando o socio responde (abordagem interna, so' super admin). */
    public const OBJECOES = [
        '"Quanto custa?"' => 'R$ 280 por mês com 3 módulos: manutenção, ativos e logística. Os 30 dias são grátis e a implantação só é combinada depois do teste.',
        '"Já uso planilha" ou "meu contador cuida"' => 'A planilha não avisa a manutenção que vence nem mostra o equipamento parado. Ofereça montar os equipamentos dele no sistema para ele ver isso com dados dele, sem compromisso.',
        '"Não tenho tempo"' => 'Você faz o cadastro dos equipamentos e ele só confere. Peça 10 minutos por WhatsApp ou na visita.',
        '"Não conheço a Oravel"' => 'Conte que administrou uma locadora por muitos anos e criou o sistema a partir disso. Como ainda não tem cliente de referência para mostrar, sugira uma conversa rápida no escritório dele.',
    ];

    protected $table = 'prospects';

    protected $guarded = [];

    protected $attributes = ['status' => 'nao_enviado'];

    protected $casts = [
        'regiao_campinas' => 'boolean',
        'empresa_nova' => 'boolean',
        'proximo_contato' => 'date',
        'ultimo_contato_em' => 'datetime',
    ];

    public function scopeNotaAlta(Builder $q): Builder
    {
        return $q->where('nota', '>=', 8);
    }

    public function scopeRetornoVencido(Builder $q): Builder
    {
        return $q->whereNotNull('proximo_contato')->whereDate('proximo_contato', '<=', now());
    }

    public function scopeAnoAbertura(Builder $q, $de, $ate): Builder
    {
        return $q->when(filled($de), fn ($q) => $q->where('ano_abertura', '>=', (int) $de))
            ->when(filled($ate), fn ($q) => $q->where('ano_abertura', '<=', (int) $ate));
    }

    public function scopeBusca(Builder $q, ?string $termo): Builder
    {
        $termo = trim((string) $termo);

        return $q->when($termo !== '', fn ($q) => $q->where(fn ($w) => $w
            ->where('nome', 'ilike', "%{$termo}%")->orWhere('razao_social', 'ilike', "%{$termo}%")
            ->orWhere('cnpj', 'ilike', "%{$termo}%")->orWhere('socio', 'ilike', "%{$termo}%")
            ->orWhere('telefone', 'ilike', "%{$termo}%")));
    }

    /** Aplica o estado dos filtros da tabela (mesmas chaves dos filtros do Resource); usado na impressão. */
    public function scopeFiltrar(Builder $q, ?array $f): Builder
    {
        $f = $f ?? [];
        $v = fn (string $k) => $f[$k]['value'] ?? null;

        foreach (['status', 'resultado', 'lote', 'porte', 'atividade', 'origem'] as $campo) {
            if (filled($v($campo))) {
                $q->where($campo, $v($campo));
            }
        }
        foreach (['empresa_nova', 'regiao_campinas'] as $campo) {
            if (filled($v($campo))) {
                $q->where($campo, (bool) $v($campo));
            }
        }
        if (! empty($f['nota_alta']['isActive'])) {
            $q->notaAlta();
        }
        if (! empty($f['retorno_vencido']['isActive'])) {
            $q->retornoVencido();
        }

        return $q->anoAbertura($f['ano_abertura']['de'] ?? null, $f['ano_abertura']['ate'] ?? null);
    }

    /** Filtros ativos em forma normalizada (chave => valor), para comparar com os cards. */
    public static function filtrosAtivos(?array $f): array
    {
        $f = $f ?? [];
        $r = [];
        foreach (['status', 'resultado', 'lote', 'porte', 'atividade', 'origem', 'empresa_nova', 'regiao_campinas'] as $k) {
            if (filled($f[$k]['value'] ?? null)) {
                $r[$k] = (string) $f[$k]['value'];
            }
        }
        foreach (['nota_alta', 'retorno_vencido'] as $k) {
            if (! empty($f[$k]['isActive'])) {
                $r[$k] = '1';
            }
        }
        foreach (['de', 'ate'] as $k) {
            if (filled($f['ano_abertura'][$k] ?? null)) {
                $r['ano_'.$k] = (string) $f['ano_abertura'][$k];
            }
        }

        return $r;
    }

    protected static function booted(): void
    {
        static::saved(function (Prospect $p): void {
            if ($p->wasRecentlyCreated || $p->wasChanged('proximo_contato')) {
                $p->sincronizarCompromisso();
            }
        });
    }

    public function compromissos(): HasMany
    {
        return $this->hasMany(SalesLeadAppointment::class);
    }

    /**
     * "Proximo contato" vira compromisso na Programacao (calendario): cria/move o compromisso
     * pendente do prospect, ou remove quando a data e' apagada. Compromissos ja concluidos ficam.
     */
    public function sincronizarCompromisso(): void
    {
        $aberto = $this->compromissos()->where('status', '!=', SalesLeadAppointment::STATUS_CONCLUIDO)->orderBy('scheduled_at');

        if (! $this->proximo_contato) {
            $aberto->delete();

            return;
        }

        $quando = $this->proximo_contato->copy()->setTime(9, 0);
        $existente = $aberto->first();
        $dados = ['scheduled_at' => $quando, 'sales_lead_id' => $this->sales_lead_id];

        if ($existente) {
            // preserva hora ajustada na agenda se o dia nao mudou
            if ($existente->scheduled_at->isSameDay($quando)) {
                unset($dados['scheduled_at']);
            }
            $existente->update($dados);

            return;
        }

        $this->compromissos()->create($dados + [
            'title' => 'Contato com prospect',
            'type' => SalesLeadAppointment::TYPE_LIGACAO,
            'status' => SalesLeadAppointment::STATUS_PENDENTE,
            'assigned_user_id' => auth()->id(),
        ]);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(SalesLead::class, 'sales_lead_id');
    }

    public function contatos(): HasMany
    {
        return $this->hasMany(ProspectContato::class);
    }

    public function primeiroNomeSocio(): string
    {
        $primeiro = explode(' ', trim((string) $this->socio))[0] ?? '';

        return $primeiro !== '' ? mb_convert_case($primeiro, MB_CASE_TITLE, 'UTF-8') : 'tudo bem';
    }

    /** Celular (9 apos o DDD) permite WhatsApp direto; fixo pede ligacao. */
    public function ehCelular(): bool
    {
        return (bool) preg_match('/^\(\d\d\) 9/', (string) $this->telefone);
    }

    public function mensagemAbordagem(): string
    {
        if (filled($this->mensagem_personalizada)) {
            return $this->mensagem_personalizada;
        }

        $abertura = $this->ano_abertura ? ' abriu em '.$this->ano_abertura : ' está começando';

        return 'Olá, '.$this->primeiroNomeSocio().'. Aqui é o Humberto, de Campinas. Administrei uma locadora por muitos anos e sei como a bagunça com contrato, manutenção e equipamento parado aparece quando a operação começa a crescer.'
            ."\n\nVi que a ".$this->nome.$abertura.'. Criei o Oravel para quem está nessa fase. Posso deixar o cadastro dos seus equipamentos montado e você usa 30 dias grátis, com treinamento incluso.'
            ."\n\nSe não fizer sentido, é só me avisar.";
    }

    public function mensagemFollowUp(): string
    {
        return str_replace('{nome}', $this->primeiroNomeSocio(), self::FOLLOW_UP);
    }
}
