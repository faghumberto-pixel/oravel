<?php

namespace App\Filament\Pages;

use App\Mail\SignatureAcceptedMail;
use App\Models\Signature;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Assinatura do contrato SLA/LGPD da própria Oravel (infra de onboarding,
 * mesma família de ComplianceStatus) -- não é um módulo vendável, todo
 * tenant tem que assinar isso independente do Contrato. Intencionalmente
 * sem canAccess() por feature.
 */
class ContractSignature extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-document-check';

    protected static ?string $navigationLabel = 'Assinar Contrato SLA + LGPD';

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.pages.contract-signature';

    protected static ?string $title = 'Assinatura de Contrato de Serviço';

    public ?array $data = [];

    public function mount(): void
    {
        $tenant = auth()->user()->tenant;
        $signature = $tenant?->signature;

        if ($signature) {
            Notification::make()
                ->title('Contrato já assinado')
                ->body('Você já assinou o contrato de serviço em '.$signature->signed_at->format('d/m/Y H:i'))
                ->success()
                ->send();

            $this->redirect(route('filament.admin.dashboard'));
        }

        $this->form->fill([
            'company' => $tenant->name ?? '',
            'email' => auth()->user()->email ?? '',
            'name' => auth()->user()->name ?? '',
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Contrato de Serviço SLA + LGPD')
                    ->description('Leia e assine o contrato de conformidade LGPD e SLA da Oravel')
                    ->schema([
                        Forms\Components\View::make('filament.components.sla-lgpd-summary'),

                        Forms\Components\TextInput::make('company')
                            ->label('Nome da Empresa')
                            ->required()
                            ->disabled(),

                        Forms\Components\TextInput::make('email')
                            ->label('Email do Responsável')
                            ->email()
                            ->required()
                            ->disabled(),

                        Forms\Components\TextInput::make('name')
                            ->label('Nome Completo do Responsável')
                            ->required()
                            ->disabled(),

                        Forms\Components\Checkbox::make('agree_sla')
                            ->label('Concordo com o SLA (Acordo de Nível de Serviço)')
                            ->required()
                            ->helperText('Uptime 99.9%, RTO 4 horas, RPO 1 hora, backup automático, suporte 24/7'),

                        Forms\Components\Checkbox::make('agree_lgpd')
                            ->label('Concordo com conformidade LGPD (Lei Geral de Proteção de Dados)')
                            ->required()
                            ->helperText('Armazenamento em Brasil, encriptação, isolamento de dados, direitos do titular'),

                        Forms\Components\Checkbox::make('agree_license')
                            ->label('Concordo com a Licença de Uso do software')
                            ->required()
                            ->helperText('Uso liberado enquanto o Contrato de Assinatura estiver ativo e em dia; não transfere propriedade do software'),

                        Forms\Components\Checkbox::make('agree_legal')
                            ->label('Confirmo que tenho autoridade legal para assinar este contrato')
                            ->required(),

                        Forms\Components\Checkbox::make('hide_prompt')
                            ->label('Não mostrar este aviso no próximo acesso')
                            ->helperText('Você pode acessar o status de conformidade no seu perfil a qualquer momento'),
                    ]),
            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        $data = $this->form->getState();
        $tenant = auth()->user()->tenant;

        try {
            $timestamp = now()->utc()->format('Y-m-d\TH:i:s\Z');
            $dataToSign = $data['company'].'|'.$data['email'].'|'.$data['name'].'|'.$timestamp;

            $hash = hash('sha256', $dataToSign);

            $signature = Signature::create([
                'company' => $data['company'],
                'email' => $data['email'],
                'name' => $data['name'],
                'signed_at' => $timestamp,
                'hash' => $hash,
                'ip_origin' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'metadata' => [
                    'signed_by_user_id' => auth()->id(),
                    'signed_from' => 'admin_panel',
                    'agree_sla' => (bool) ($data['agree_sla'] ?? false),
                    'agree_lgpd' => (bool) ($data['agree_lgpd'] ?? false),
                    'agree_license' => (bool) ($data['agree_license'] ?? false),
                ],
            ]);

            $tenant->update(['signature_id' => $signature->id]);

            \Mail::to('suporte@oravel.com.br')->send(
                new SignatureAcceptedMail($signature)
            );

            \Mail::to($data['email'])->send(
                new SignatureAcceptedMail($signature)
            );

            $signature->update(['email_sent' => true, 'email_sent_at' => now()]);

            if ($data['hide_prompt'] ?? false) {
                $tenant->update(['hide_signature_prompt' => true]);
            }

            Notification::make()
                ->title('✅ Contrato Assinado com Sucesso!')
                ->body('Sua assinatura foi registrada. Você receberá um email de confirmação.')
                ->success()
                ->send();

            $this->redirect(route('filament.admin.dashboard'));

        } catch (\Exception $e) {
            \Log::error('Erro ao assinar contrato no admin', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            Notification::make()
                ->title('❌ Erro ao assinar')
                ->body('Ocorreu um erro ao processar sua assinatura. Tente novamente.')
                ->danger()
                ->send();
        }
    }
}
