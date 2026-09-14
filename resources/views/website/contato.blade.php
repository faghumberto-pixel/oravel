@extends('layouts.website')

@section('title', 'Solicitar Demonstração - Oravel')
@section('description', 'Agende uma demonstração gratuita da plataforma Oravel para sua empresa.')

@section('content')

<section class="py-20 md:py-28 bg-white">
    <div class="wrap">
        <div class="max-w-2xl mx-auto">
            <h1 class="text-4xl md:text-5xl font-bold mb-6 text-center">Solicitar Demonstração</h1>
            <p class="text-lg text-ink-soft text-center mb-12">Preencha o formulário abaixo. Nossa equipe entrará em contato em breve para agendar sua demonstração.</p>

            <form id="demo-form" class="space-y-6 bg-surface rounded-xl p-8 border border-border">
                @csrf

                <div>
                    <label for="name" class="block text-sm font-semibold mb-2">Nome completo *</label>
                    <input type="text" id="name" name="name" required class="w-full px-4 py-3 border border-border rounded-lg focus:outline-none focus:border-accent focus:ring-1 focus:ring-orange-200">
                </div>

                <div>
                    <label for="email" class="block text-sm font-semibold mb-2">Email *</label>
                    <input type="email" id="email" name="email" required class="w-full px-4 py-3 border border-border rounded-lg focus:outline-none focus:border-accent focus:ring-1 focus:ring-orange-200">
                </div>

                <div>
                    <label for="phone" class="block text-sm font-semibold mb-2">Telefone *</label>
                    <input type="tel" id="phone" name="phone" required placeholder="(11) 98765-4321" class="w-full px-4 py-3 border border-border rounded-lg focus:outline-none focus:border-accent focus:ring-1 focus:ring-orange-200">
                </div>

                <div>
                    <label for="company" class="block text-sm font-semibold mb-2">Empresa *</label>
                    <input type="text" id="company" name="company" required class="w-full px-4 py-3 border border-border rounded-lg focus:outline-none focus:border-accent focus:ring-1 focus:ring-orange-200">
                </div>

                <div>
                    <label for="segment" class="block text-sm font-semibold mb-2">Segmento *</label>
                    <select id="segment" name="segment" required class="w-full px-4 py-3 border border-border rounded-lg focus:outline-none focus:border-accent focus:ring-1 focus:ring-orange-200">
                        <option value="">Selecione seu segmento...</option>
                        <option value="Locadora de Equipamento">Locadora de Equipamento</option>
                        <option value="Frota/Transportadora">Frota/Transportadora</option>
                        <option value="Distribuição">Distribuição</option>
                        <option value="Construção Civil">Construção Civil</option>
                        <option value="Logística">Logística</option>
                        <option value="Varejo">Varejo</option>
                        <option value="Outro">Outro</option>
                    </select>
                </div>

                <div>
                    <label for="product" class="block text-sm font-semibold mb-2">Qual solução te interessa? *</label>
                    <select id="product" name="product" required class="w-full px-4 py-3 border border-border rounded-lg focus:outline-none focus:border-accent focus:ring-1 focus:ring-orange-200">
                        <option value="">Selecione a solução...</option>
                        <option value="Oravel ERP">Oravel ERP</option>
                        <option value="Oravel Small">Oravel Small</option>
                        <option value="Oravel CRM">Oravel CRM</option>
                        <option value="Oravel Frota">Oravel Frota</option>
                        <option value="Oravel WMS">Oravel WMS</option>
                        <option value="Oravel OS">Oravel OS</option>
                        <option value="Oravel Recebimentos">Oravel Recebimentos</option>
                        <option value="Oravel RH">Oravel RH</option>
                        <option value="Oravel Analytics">Oravel Analytics</option>
                        <option value="Não sei, quer me ajudar?">Não sei, quer me ajudar?</option>
                    </select>
                </div>

                <div>
                    <label for="message" class="block text-sm font-semibold mb-2">Algum comentário adicional?</label>
                    <textarea id="message" name="message" rows="4" class="w-full px-4 py-3 border border-border rounded-lg focus:outline-none focus:border-accent focus:ring-1 focus:ring-orange-200" placeholder="Conte-nos um pouco sobre seu desafio..."></textarea>
                </div>

                <button type="submit" class="w-full bg-accent hover:bg-orange-600 text-white font-semibold py-3 px-6 rounded-lg transition">
                    Agendar Demonstração
                </button>

                <p class="text-xs text-ink-faint text-center">Responderemos em até 24 horas. Seus dados são seguros conosco.</p>
            </form>
        </div>
    </div>
</section>

<script>
document.getElementById('demo-form').addEventListener('submit', async (e) => {
    e.preventDefault();

    const formData = {
        name: document.getElementById('name').value,
        email: document.getElementById('email').value,
        phone: document.getElementById('phone').value,
        company: document.getElementById('company').value,
        segment: document.getElementById('segment').value,
        product: document.getElementById('product').value,
        message: document.getElementById('message').value || '',
        _token: document.querySelector('[name="_token"]').value,
    };

    try {
        const response = await fetch('/api/landing-page/leads', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify(formData),
        });

        const data = await response.json();

        if (response.ok) {
            alert('✅ Registrado com sucesso! Nossa equipe entrará em contato em breve.');
            document.getElementById('demo-form').reset();
        } else {
            alert('❌ Erro ao registrar: ' + (data.message || 'Tente novamente'));
        }
    } catch (error) {
        console.error('Erro:', error);
        alert('❌ Erro ao enviar formulário. Tente novamente.');
    }
});
</script>

@endsection
