<?php

namespace App\Filament\Central\Resources\TenantResource\Pages;

use App\Filament\Central\Resources\TenantResource;
use App\Services\AsaasService;
use App\Services\SignatureService;
use App\Services\TenantProvisioner;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateTenant extends CreateRecord
{
    protected static string $resource = TenantResource::class;

    protected array $adminData = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (isset($data['features']) && ! is_array($data['features'])) {
            $data['features'] = [];
        }

        $this->adminData = [
            'name' => $data['admin_name'],
            'email' => $data['admin_email'],
            'password' => $data['admin_password'],
        ];
        unset($data['admin_name'], $data['admin_email'], $data['admin_password']);

        return $data;
    }

    /**
     * Contrato de Assinatura obrigatório também no cadastro manual pela
     * Central (2026-09-23, pedido do usuário) -- antes, um tenant criado
     * aqui nascia com o admin já liberado (is_approved=true por default da
     * coluna), sem nenhum contrato assinado. Agora segue exatamente o
     * mesmo mecanismo do autoatendimento (/assinar): o admin nasce
     * bloqueado, e só é liberado depois que o Contrato de Assinatura é
     * assinado E o Checkout da Asaas é pago -- reaproveita o MESMO
     * redirecionamento genérico de PublicSignatureController::store()
     * (que já manda qualquer assinatura de Tenant para
     * AsaasCheckoutController::continueAfterSignature()), sem precisar de
     * nenhuma lógica nova lá. O operador só recebe o link pra copiar e
     * mandar pro cliente -- pedir CPF/CNPJ e MRR (R$) no formulário
     * continua obrigatório na prática, porque sem eles o Checkout não é
     * criado (mesma trava de AsaasService::createTenantCheckout()).
     */
    protected function afterCreate(): void
    {
        $admin = TenantProvisioner::provision($this->record, $this->adminData);
        $admin->forceFill(['is_approved' => false])->save();

        app(AsaasService::class)->syncTenantCustomer($this->record);

        $signatureLink = app(SignatureService::class)->generateSignatureLink($this->record, [
            'name' => $this->adminData['name'],
            'email' => $this->adminData['email'],
        ]);

        Notification::make()
            ->title('Tenant criado -- falta assinar o contrato')
            ->body("Envie este link pro cliente. O acesso só é liberado depois que ele assinar o contrato e pagar pelo Checkout:\n\n{$signatureLink}")
            ->warning()
            ->persistent()
            ->send();
    }
}
