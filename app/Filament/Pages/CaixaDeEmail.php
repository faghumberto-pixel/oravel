<?php

namespace App\Filament\Pages;

use App\Models\EmailMessage;
use App\Models\User;
use App\Notifications\NewInternalEmailNotification;
use App\Support\Tenancy;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Caixa de e-mail (Enviados/Recebidos/Rascunhos) -- ver plano em
 * .claude/plans/sharded-scribbling-kettle.md pro contexto completo.
 * "Recebidos" nesta v1 so' traz e-mail interno (outro usuario do mesmo
 * tenant) -- ver App\Models\EmailMessage pro porque.
 */
class CaixaDeEmail extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';

    protected static ?string $navigationLabel = 'E-mail';

    protected static ?string $title = 'Caixa de E-mail';

    protected static ?string $slug = 'caixa-de-email';

    // Nao aparece no menu normal -- vira um icone solto no topbar, depois
    // do sino de notificacao (ver topbar/index.blade.php, ramo admin).
    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.pages.caixa-de-email';

    public string $folder = 'recebidos';

    public ?string $activeMessageId = null;

    public bool $isComposing = false;

    public ?array $composeData = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('viewAny', EmailMessage::class);
    }

    public function mount(): void
    {
        $folder = request()->query('folder');

        if (in_array($folder, ['recebidos', 'enviados', 'rascunhos'], true)) {
            $this->folder = $folder;
        }

        $relatedType = request()->query('related_type');
        $relatedId = request()->query('related_id');
        $prefillExternal = request()->query('to');

        if ($messageId = request()->query('message')) {
            $this->selectMessage($messageId);

            return;
        }

        if ($prefillExternal || $relatedType) {
            $this->newDraft($relatedType, $relatedId, $prefillExternal);
        }
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('to_user_ids')
                    ->label('Para (usuário da equipe)')
                    ->multiple()
                    ->searchable()
                    ->options(fn () => User::where('tenant_id', Tenancy::current()?->id)
                        ->where('id', '!=', auth()->id())
                        ->orderBy('name')
                        ->pluck('name', 'id')),

                TagsInput::make('to_external')
                    ->label('Para (e-mail externo)')
                    ->placeholder('email@exemplo.com')
                    ->helperText('Aperte Enter depois de cada endereço.'),

                TextInput::make('subject')
                    ->label('Assunto')
                    ->maxLength(255),

                Textarea::make('body')
                    ->label('Mensagem')
                    ->rows(8),

                SpatieMediaLibraryFileUpload::make('anexos')
                    ->label('Anexos (arquivos e imagens)')
                    ->collection('anexos')
                    ->multiple()
                    ->downloadable()
                    ->openable(),
            ])
            ->model($this->activeMessageId ? EmailMessage::find($this->activeMessageId) : null)
            ->statePath('composeData');
    }

    /**
     * Restrita ao usuario logado: remetente, destinatario interno, ou dono
     * do rascunho -- privacidade da caixa, sem bypass de admin.
     */
    public function getMessagesQuery()
    {
        $userId = auth()->id();

        $query = EmailMessage::query()->with(['fromUser', 'recipients']);

        return match ($this->folder) {
            'enviados' => $query->where('from_user_id', $userId)->where('status', '!=', EmailMessage::STATUS_RASCUNHO)->latest(),
            'rascunhos' => $query->where('from_user_id', $userId)->where('status', EmailMessage::STATUS_RASCUNHO)->latest(),
            default => $query->whereHas('recipients', fn ($q) => $q->where('users.id', $userId))->where('status', '!=', EmailMessage::STATUS_RASCUNHO)->latest(),
        };
    }

    public function setFolder(string $folder): void
    {
        $this->folder = $folder;
        $this->activeMessageId = null;
        $this->isComposing = false;
    }

    private function canView(EmailMessage $message): bool
    {
        $userId = auth()->id();

        return $message->from_user_id === $userId
            || $message->recipients->contains('id', $userId);
    }

    public function selectMessage(string $id): void
    {
        $message = EmailMessage::find($id);

        if (! $message || ! $this->canView($message)) {
            $this->activeMessageId = null;

            return;
        }

        if ($message->status === EmailMessage::STATUS_RASCUNHO && $message->from_user_id === auth()->id()) {
            $this->editDraft($message);

            return;
        }

        if ($message->recipients->contains('id', auth()->id())) {
            $message->markReadFor(auth()->user());
        }

        $this->activeMessageId = $message->id;
        $this->isComposing = false;
    }

    private function editDraft(EmailMessage $message): void
    {
        $this->activeMessageId = $message->id;
        $this->isComposing = true;

        $this->form->fill([
            'to_user_ids' => $message->recipients()->pluck('users.id')->all(),
            'to_external' => $message->to_external ?? [],
            'subject' => $message->subject,
            'body' => $message->body,
        ]);
    }

    public function newDraft(?string $relatedType = null, ?string $relatedId = null, ?string $prefillExternal = null): void
    {
        $tenant = Tenancy::current();

        $message = EmailMessage::create([
            'tenant_id' => $tenant?->id,
            'from_user_id' => auth()->id(),
            'status' => EmailMessage::STATUS_RASCUNHO,
            'to_external' => $prefillExternal ? [$prefillExternal] : [],
            'related_type' => $relatedType,
            'related_id' => $relatedId,
        ]);

        $this->folder = 'rascunhos';
        $this->editDraft($message->fresh());
    }

    public function replyTo(string $id): void
    {
        $original = EmailMessage::find($id);

        if (! $original || ! $this->canView($original)) {
            return;
        }

        $replyToUserId = $original->from_user_id === auth()->id() ? null : $original->from_user_id;

        $message = EmailMessage::create([
            'tenant_id' => Tenancy::current()?->id,
            'from_user_id' => auth()->id(),
            'status' => EmailMessage::STATUS_RASCUNHO,
            'subject' => str_starts_with((string) $original->subject, 'Re: ') ? $original->subject : 'Re: '.$original->subject,
        ]);

        if ($replyToUserId) {
            $message->recipients()->sync([$replyToUserId]);
        } else {
            $message->update(['to_external' => $original->to_external]);
        }

        $this->folder = 'rascunhos';
        $this->editDraft($message->fresh());
    }

    /**
     * @return array{to_external: array<int, string>}|null
     */
    private function extractValidatedData(): ?array
    {
        $data = $this->form->getState();

        $invalid = collect($data['to_external'] ?? [])->reject(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL));

        if ($invalid->isNotEmpty()) {
            Notification::make()
                ->title('E-mail externo inválido')
                ->body('Corrija: '.$invalid->implode(', '))
                ->danger()
                ->send();

            return null;
        }

        return $data;
    }

    public function saveDraft(): void
    {
        $message = EmailMessage::find($this->activeMessageId);

        if (! $message || $message->from_user_id !== auth()->id()) {
            return;
        }

        if (! $data = $this->extractValidatedData()) {
            return;
        }

        $message->update([
            'subject' => $data['subject'] ?? null,
            'body' => $data['body'] ?? null,
            'to_external' => $data['to_external'] ?? [],
        ]);

        $message->recipients()->sync($data['to_user_ids'] ?? []);

        Notification::make()->title('Rascunho salvo.')->success()->send();
    }

    public function sendMessage(): void
    {
        $message = EmailMessage::find($this->activeMessageId);

        if (! $message || $message->from_user_id !== auth()->id()) {
            return;
        }

        if (! $data = $this->extractValidatedData()) {
            return;
        }

        $message->update([
            'subject' => $data['subject'] ?? null,
            'body' => $data['body'] ?? null,
            'to_external' => $data['to_external'] ?? [],
        ]);

        $newRecipientIds = collect($data['to_user_ids'] ?? []);
        $message->recipients()->sync($newRecipientIds->all());

        try {
            $message->send();
        } catch (\RuntimeException $e) {
            Notification::make()->title('Não foi possível enviar')->body($e->getMessage())->warning()->send();

            return;
        }

        foreach (User::whereIn('id', $newRecipientIds)->get() as $recipient) {
            $recipient->notify(new NewInternalEmailNotification($message));
        }

        $this->isComposing = false;
        $this->folder = 'enviados';

        Notification::make()->title('E-mail enviado.')->success()->send();
    }

    public function deleteDraft(string $id): void
    {
        $message = EmailMessage::find($id);

        if (! $message || $message->from_user_id !== auth()->id() || $message->status !== EmailMessage::STATUS_RASCUNHO) {
            return;
        }

        $message->delete();

        if ($this->activeMessageId === $id) {
            $this->activeMessageId = null;
            $this->isComposing = false;
        }
    }
}
