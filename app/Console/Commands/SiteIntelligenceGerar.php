<?php

namespace App\Console\Commands;

use App\Domain\Intelligence\Support\Catalogo;
use App\Domain\Intelligence\Support\ModeloExcel;
use App\Support\ExcelImport\XlsxReader;
use App\Support\IntelligenceSite\Exemplo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\View;

/**
 * Gera a página estática oravel.com.br/intelligence a partir do Blade + config/intelligence_site.php (fonte única dos textos e do
 * exemplo fictício) + preço e prazo de config/intelligence_trial.php. O site roda no HostGator: depois de gerar, publique
 * public_html/intelligence/ por SFTP (este comando NÃO publica nada).
 */
class SiteIntelligenceGerar extends Command
{
    protected $signature = 'site:intelligence
        {--out= : pasta de saída (padrão: public_html/intelligence)}
        {--app-url= : troca o endereço do app só nesta geração (para testar o formulário em DEV)}
        {--check : não escreve; falha se o index.html versionado estiver diferente do que seria gerado}
        {--forcar : regenera também o modelo .xlsx e a imagem OG, mesmo que já existam}';

    protected $description = 'Gera a página estática do teste grátis do Intelligence (index.html, modelo da planilha e imagem OG)';

    public const ARQUIVO_MODELO = 'modelo-planilha-intelligence.xlsx';

    public const ARQUIVO_OG = 'og-intelligence.png';

    public function handle(): int
    {
        if ($url = $this->option('app-url')) {
            config(['intelligence_site.app_url' => rtrim($url, '/')]);
        }
        $pasta = rtrim($this->option('out') ?: base_path('public_html/intelligence'), '/');
        $html = self::renderizar();

        if ($this->option('check')) {
            $atual = is_file("$pasta/index.html") ? file_get_contents("$pasta/index.html") : null;
            if ($atual !== $html) {
                $this->error('public_html/intelligence/index.html está diferente do gerado. Rode: php artisan site:intelligence');

                return self::FAILURE;
            }
            $this->info('index.html em dia com o config.');

            return self::SUCCESS;
        }

        if (! is_dir($pasta)) {
            mkdir($pasta, 0775, true);
        }
        file_put_contents("$pasta/index.html", $html);
        $this->info("Página gerada: $pasta/index.html (".number_format(strlen($html) / 1024, 1, ',', '.').' KB)');

        $modelo = "$pasta/".self::ARQUIVO_MODELO;
        if ($this->option('forcar') || ! is_file($modelo) || ! self::modeloEmDia($modelo)) {
            ModeloExcel::escrever($modelo);
            $this->info('Modelo da planilha regenerado: '.self::ARQUIVO_MODELO);
        }

        $og = "$pasta/".self::ARQUIVO_OG;
        if ($this->option('forcar') || ! is_file($og)) {
            self::gerarOg($og);
            $this->info('Imagem OG gerada: '.self::ARQUIVO_OG);
        }

        return self::SUCCESS;
    }

    /** O HTML final da página (também usado pelos testes). */
    public static function renderizar(): string
    {
        $t = (array) config('intelligence_site.textos');

        return View::make('site.intelligence.page', [
            't' => $t,
            'ex' => Exemplo::doConfig(),
            'rotulo' => (string) config('intelligence_site.rotulo_exemplo'),
            'app' => rtrim((string) config('intelligence_site.app_url'), '/'),
            'site' => rtrim((string) config('intelligence_site.site_url'), '/'),
            'preco' => 'R$ '.number_format((float) config('intelligence_trial.plano.preco'), 2, ',', '.'),
            'dias' => (int) config('intelligence_trial.dias'),
            'empresa' => (array) config('oravel.company'),
            'fonteDepoimentos' => rtrim((string) config('intelligence_site.app_url'), '/').'/api/intelligence/depoimentos',
            'css' => file_get_contents(resource_path('site/intelligence.css')),
            'js' => file_get_contents(resource_path('site/intelligence.js')),
        ])->render();
    }

    /** O modelo versionado só vale se as colunas forem as do Catalogo (o importador lê essas mesmas). */
    public static function modeloEmDia(string $caminho): bool
    {
        try {
            $rows = XlsxReader::rowsDaAba($caminho, Catalogo::SHEET);
        } catch (\Throwable) {
            return false;
        }
        if (! $rows) {
            return false;
        }
        $titulos = array_column(Catalogo::campos(), 'title');
        foreach ($rows as $linha) {
            $linha = array_values(array_map(fn ($c) => trim(preg_replace('/\s*\*$/', '', (string) $c)), (array) $linha));
            if (array_slice($linha, 0, count($titulos)) === $titulos) {
                return true;
            }
        }

        return false;
    }

    private static function gerarOg(string $destino): void
    {
        $im = imagecreatetruecolor(1200, 630);
        $navy = imagecolorallocate($im, 15, 23, 42);
        $laranja = imagecolorallocate($im, 249, 115, 22);
        $branco = imagecolorallocate($im, 255, 255, 255);
        $cinza = imagecolorallocate($im, 203, 213, 225);
        imagefilledrectangle($im, 0, 0, 1200, 630, $navy);
        imagefilledrectangle($im, 0, 0, 14, 630, $laranja);
        $fonte = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';
        $fonteReg = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';
        $t = (array) config('intelligence_site.textos');
        if (is_file($fonte)) {
            imagettftext($im, 28, 0, 70, 110, $laranja, $fonte, mb_strtoupper((string) $t['marca']));
            $y = 230;
            foreach (self::quebrar((string) $t['hero_titulo'], 24) as $linha) {
                imagettftext($im, 58, 0, 70, $y, $branco, $fonte, $linha);
                $y += 80;
            }
            imagettftext($im, 28, 0, 70, 520, $cinza, is_file($fonteReg) ? $fonteReg : $fonte, 'Primeira leitura grátis. Sem senha e sem cartão.');
        } else {
            imagestring($im, 5, 70, 100, (string) $t['hero_titulo'], $branco);
        }
        imagepng($im, $destino, 9);
        imagedestroy($im);
    }

    /** @return list<string> */
    private static function quebrar(string $texto, int $largura): array
    {
        return explode("\n", wordwrap($texto, $largura, "\n", false));
    }
}
