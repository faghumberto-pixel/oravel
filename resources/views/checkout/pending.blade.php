<x-guest-layout>
    <div class="mb-4 text-lg font-bold text-gray-900">
        Cadastro recebido
    </div>

    <p class="text-sm text-gray-600">
        Sua empresa foi cadastrada, mas não conseguimos gerar o link de pagamento agora.
        Entraremos em contato para concluir a assinatura, ou você pode tentar novamente
        mais tarde.
    </p>

    <div class="flex items-center justify-end mt-6">
        <a href="{{ route('checkout.create') }}" class="text-sm text-indigo-600 hover:text-indigo-800">
            Voltar
        </a>
    </div>
</x-guest-layout>
