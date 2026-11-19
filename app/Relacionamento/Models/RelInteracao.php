<?php

namespace App\Relacionamento\Models;

use App\Models\Prospect;
use App\Models\SalesLead;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma acao de uma empresa/pessoa no ecossistema Oravel. Fonte unica da linha do tempo, do score
 * (Fase 2) e da inteligencia de mercado (Fase 6). Tabela GLOBAL, so' super admin na Central.
 */
class RelInteracao extends Model
{
    use HasUuids;

    public const TIPO_VISITA = 'visita_portal';

    public const TIPO_ARTIGO = 'artigo';

    public const TIPO_VIDEO = 'video';

    public const TIPO_FERRAMENTA = 'ferramenta';

    public const TIPO_PDF = 'pdf';

    public const TIPO_GAZETA = 'gazeta_inscricao';

    public const TIPO_EMAIL_ABERTO = 'email_aberto';

    public const TIPO_EMAIL_CLIQUE = 'email_clique';

    public const TIPO_WHATSAPP_RESPOSTA = 'whatsapp_resposta';

    public const TIPO_REUNIAO = 'reuniao_solicitada';

    public const TIPO_CONTATO_COMERCIAL = 'contato_comercial';

    /** tipo => [rotulo, cor Filament, icone heroicon]. Unica lista: validacao, UI e (Fase 2) score partem daqui. */
    public const TIPOS = [
        self::TIPO_VISITA => ['Visitou o portal', 'gray', 'heroicon-o-globe-alt'],
        self::TIPO_ARTIGO => ['Leu artigo', 'info', 'heroicon-o-document-text'],
        self::TIPO_VIDEO => ['Assistiu vídeo', 'primary', 'heroicon-o-play-circle'],
        self::TIPO_FERRAMENTA => ['Usou ferramenta', 'success', 'heroicon-o-calculator'],
        self::TIPO_PDF => ['Baixou PDF', 'warning', 'heroicon-o-arrow-down-tray'],
        self::TIPO_GAZETA => ['Entrou na Gazeta', 'success', 'heroicon-o-newspaper'],
        self::TIPO_EMAIL_ABERTO => ['Abriu e-mail', 'gray', 'heroicon-o-envelope-open'],
        self::TIPO_EMAIL_CLIQUE => ['Clicou em e-mail', 'info', 'heroicon-o-cursor-arrow-rays'],
        self::TIPO_WHATSAPP_RESPOSTA => ['Respondeu WhatsApp', 'success', 'heroicon-o-chat-bubble-left-right'],
        self::TIPO_REUNIAO => ['Solicitou reunião', 'danger', 'heroicon-o-calendar-days'],
        self::TIPO_CONTATO_COMERCIAL => ['Contato comercial', 'gray', 'heroicon-o-phone'],
    ];

    protected $table = 'rel_interacoes';

    protected $guarded = [];

    protected $casts = [
        'dados' => 'array',
        'ocorrido_em' => 'datetime',
        'duracao_segundos' => 'integer',
    ];

    public static function rotulo(string $tipo): string
    {
        return self::TIPOS[$tipo][0] ?? $tipo;
    }

    public static function cor(string $tipo): string
    {
        return self::TIPOS[$tipo][1] ?? 'gray';
    }

    public static function icone(string $tipo): string
    {
        return self::TIPOS[$tipo][2] ?? 'heroicon-o-bolt';
    }

    public function conteudo(): BelongsTo
    {
        return $this->belongsTo(RelConteudo::class, 'conteudo_slug', 'slug');
    }

    public function contato(): BelongsTo
    {
        return $this->belongsTo(RelContato::class, 'contato_id');
    }

    public function prospect(): BelongsTo
    {
        return $this->belongsTo(Prospect::class);
    }

    public function salesLead(): BelongsTo
    {
        return $this->belongsTo(SalesLead::class);
    }
}
