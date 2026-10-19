<?php

namespace Tests\Feature;

use Tests\TestCase;

class CheckoutBoasVindasTest extends TestCase
{
    public function test_page_after_payment_welcomes_and_guides_next_steps(): void
    {
        $this->get(route('checkout.success'))
            ->assertOk()
            ->assertSee('Bem-vindo à Oravel!')
            ->assertSee('Seus próximos passos')
            ->assertSee('Siga os Primeiros passos')
            ->assertSee(url('/admin/login'), false)
            ->assertSee('wa.me/5519999332615', false);
    }
}
