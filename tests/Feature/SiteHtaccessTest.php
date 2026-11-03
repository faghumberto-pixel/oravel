<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * O .htaccess do site (HostGator) carrega regras de SEO que ja' foram perdidas uma vez por existirem so' no
 * servidor: se alguem subir um .htaccess antigo por SFTP, os redirecionamentos somem. Este teste trava o minimo.
 */
class SiteHtaccessTest extends TestCase
{
    public function test_htaccess_do_site_mantem_as_regras_de_seo(): void
    {
        $h = file_get_contents(base_path('public_html/.htaccess'));

        $this->assertStringContainsString('RewriteCond %{HTTP_HOST} ^www\.oravel\.com\.br$', $h);       // www -> sem www
        $this->assertStringContainsString('RewriteRule ^(.+[^/])$ https://oravel.com.br/$1/ [R=301,L]', $h); // diretorio sem barra, em https
        $this->assertStringContainsString('RewriteRule ^solucoes/crm/?$ https://oravel.com.br/crm/ [R=301,L]', $h);
        $this->assertStringContainsString('RewriteRule ^sobre-nos(\.html)?/?$ https://oravel.com.br/sobre/ [R=301,L]', $h);
        $this->assertStringContainsString('Cache-Control "no-cache, must-revalidate"', $h);
        $this->assertStringContainsString('llms.txt', $h);
    }
}
