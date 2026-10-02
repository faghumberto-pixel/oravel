<?php

namespace App\Livewire\Academy;

use App\Models\Course;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Pagina de ENTRADA da Academia: publica, acessivel so' com a URL (/academia), sem estar logado
 * no app. Mostra o que a Academia oferece e leva para o login; quem ja esta logado vai direto
 * para o inicio. Nao expoe nada de nenhum cliente: so' titulos/descricoes dos cursos publicados.
 */
#[Layout('academy.layout')]
class Landing extends Component
{
    public string $code = '';

    public ?string $error = null;

    public function mount()
    {
        if (auth()->check()) {
            return redirect('/academia/inicio');
        }
    }

    public function verify()
    {
        $code = strtoupper(trim($this->code));

        if (! preg_match('/^OA-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $code)) {
            $this->error = 'Código inválido. Ele tem o formato OA-XXXX-XXXX.';

            return null;
        }

        return redirect('/certificado/'.$code);
    }

    public function render()
    {
        return view('livewire.academy.landing', [
            'courses' => Course::published()->orderBy('position')->get(['id', 'title', 'description']),
        ]);
    }
}
