{{-- Formulário de Captura de Leads para Landing Pages --}}
<form id="landing-page-form" class="space-y-4">
  @csrf

  <div>
    <label for="name" class="block text-sm font-medium">Nome</label>
    <input type="text" id="name" name="name" required class="w-full px-4 py-2 border rounded-lg">
  </div>

  <div>
    <label for="email" class="block text-sm font-medium">Email</label>
    <input type="email" id="email" name="email" required class="w-full px-4 py-2 border rounded-lg">
  </div>

  <div>
    <label for="phone" class="block text-sm font-medium">Telefone</label>
    <input type="tel" id="phone" name="phone" required class="w-full px-4 py-2 border rounded-lg">
  </div>

  <div>
    <label for="company" class="block text-sm font-medium">Empresa</label>
    <input type="text" id="company" name="company" required class="w-full px-4 py-2 border rounded-lg">
  </div>

  <div>
    <label for="segment" class="block text-sm font-medium">Segmento</label>
    <select id="segment" name="segment" required class="w-full px-4 py-2 border rounded-lg">
      <option value="">Selecione...</option>
      <option value="Locadora">Locadora</option>
      <option value="Transportadora">Transportadora</option>
      <option value="Distribuição">Distribuição</option>
      <option value="Varejo">Varejo</option>
      <option value="Outro">Outro</option>
    </select>
  </div>

  <input type="hidden" id="product" name="product" value="crm">

  <button type="submit" class="w-full bg-orange-600 hover:bg-orange-700 text-white font-bold py-2 px-4 rounded-lg">
    Solicitar Demo
  </button>
</form>

<script>
document.getElementById('landing-page-form').addEventListener('submit', async (e) => {
  e.preventDefault();

  const formData = {
    name: document.getElementById('name').value,
    email: document.getElementById('email').value,
    phone: document.getElementById('phone').value,
    company: document.getElementById('company').value,
    segment: document.getElementById('segment').value,
    product: document.getElementById('product').value,
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
      alert('✅ Lead registrado com sucesso! Entraremos em contato em breve.');
      document.getElementById('landing-page-form').reset();
    } else {
      alert('❌ Erro ao registrar: ' + (data.message || 'Tente novamente'));
    }
  } catch (error) {
    console.error('Erro:', error);
    alert('❌ Erro ao enviar formulário. Tente novamente.');
  }
});
</script>
