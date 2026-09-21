<?php

namespace Tests\Unit;

use App\Support\ImageDownscaler;
use PHPUnit\Framework\TestCase;

/**
 * Sem app/banco: só arquivos temporários e GD.
 */
class ImageDownscalerTest extends TestCase
{
    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    private function tmp(string $ext): string
    {
        return $this->files[] = sys_get_temp_dir().'/dwn-'.uniqid().'.'.$ext;
    }

    /** Imagem "cheia" (formas aleatórias) para o JPEG/PNG ficar pesado como uma foto de verdade. */
    private function busy(int $w, int $h)
    {
        mt_srand(42);
        $img = imagecreatetruecolor($w, $h);
        for ($i = 0; $i < 2500; $i++) {
            imagefilledellipse($img, mt_rand(0, $w), mt_rand(0, $h), mt_rand(20, 300), mt_rand(20, 300),
                imagecolorallocate($img, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255)));
        }

        return $img;
    }

    private function jpeg(int $w, int $h, ?int $exifOrientation = null): string
    {
        $path = $this->tmp('jpg');
        imagejpeg($this->busy($w, $h), $path, 95);

        if ($exifOrientation !== null) {
            // APP1/EXIF mínimo (big-endian) com a tag Orientation logo após o SOI.
            $tiff = 'MM'.pack('n', 42).pack('N', 8).pack('n', 1).pack('nnN', 0x0112, 3, 1).pack('n', $exifOrientation)."\0\0".pack('N', 0);
            $app1 = "\xFF\xE1".pack('n', 2 + 6 + strlen($tiff))."Exif\0\0".$tiff;
            $bytes = file_get_contents($path);
            file_put_contents($path, substr($bytes, 0, 2).$app1.substr($bytes, 2));
        }

        return $path;
    }

    public function test_foto_grande_e_reduzida_para_o_lado_maximo_mantendo_proporcao_e_ficando_menor(): void
    {
        $path = $this->jpeg(3000, 2000);
        $before = filesize($path);

        $result = ImageDownscaler::downscale($path, maxSide: 1600);

        $this->assertNotNull($result);
        [$w, $h] = getimagesize($path);
        $this->assertSame(1600, $w);
        $this->assertSame(1067, $h); // 2000 * 1600/3000
        $this->assertLessThan($before, filesize($path));
        $this->assertSame(filesize($path), $result['to_bytes']);
        $this->assertSame('3000x2000', $result['from']);
        $this->assertSame('1600x1067', $result['to']);
        $this->assertFileDoesNotExist($path.'.downscale.tmp');
    }

    public function test_foto_pequena_nao_e_tocada(): void
    {
        $path = $this->tmp('jpg');
        imagejpeg(imagecreatetruecolor(800, 600), $path, 80);
        $hash = md5_file($path);

        $this->assertNull(ImageDownscaler::downscale($path, maxSide: 1600));
        $this->assertSame($hash, md5_file($path));
    }

    public function test_orientacao_exif_e_aplicada_antes_de_descartar_os_metadados(): void
    {
        // Paisagem 2400x1200 marcada como "girar 90° horário" (6): na tela é retrato.
        $path = $this->jpeg(2400, 1200, exifOrientation: 6);
        $this->assertSame(6, exif_read_data($path)['Orientation']);

        $this->assertNotNull(ImageDownscaler::downscale($path, maxSide: 1600));

        [$w, $h] = getimagesize($path);
        $this->assertLessThan($h, $w, 'deveria ter ficado em retrato');
        $this->assertSame(1600, $h);
        $this->assertSame(800, $w);
        $this->assertArrayNotHasKey('Orientation', @exif_read_data($path) ?: []);
    }

    public function test_orientacao_espelhada_nao_e_tratada_e_o_original_fica_intacto(): void
    {
        $path = $this->jpeg(2400, 1200, exifOrientation: 5);
        $hash = md5_file($path);

        $this->assertNull(ImageDownscaler::downscale($path, maxSide: 1600));
        $this->assertSame($hash, md5_file($path));
    }

    public function test_png_mantem_o_formato_e_a_transparencia(): void
    {
        $path = $this->tmp('png');
        $img = $this->busy(2400, 1200);
        imagealphablending($img, false);
        imagesavealpha($img, true);
        imagefilledrectangle($img, 0, 0, 1199, 1199, imagecolorallocatealpha($img, 0, 0, 0, 127)); // metade esquerda transparente
        imagepng($img, $path, 0);

        $this->assertNotNull(ImageDownscaler::downscale($path, maxSide: 1600));

        $this->assertSame(IMAGETYPE_PNG, getimagesize($path)[2]);
        $out = imagecreatefrompng($path);
        $this->assertSame(127, (imagecolorat($out, 100, 100) >> 24) & 0x7F, 'lado esquerdo deveria continuar transparente');
    }

    public function test_arquivo_que_nao_e_imagem_devolve_null_sem_alterar(): void
    {
        $path = $this->tmp('pdf');
        file_put_contents($path, "%PDF-1.4\n".str_repeat('x', 500000));
        $hash = md5_file($path);

        $this->assertNull(ImageDownscaler::downscale($path));
        $this->assertNull(ImageDownscaler::downscale('/tmp/nao-existe-'.uniqid().'.jpg'));
        $this->assertSame($hash, md5_file($path));
    }

    public function test_nunca_piora_se_o_resultado_nao_fica_menor_o_original_e_mantido(): void
    {
        // Foto "cheia" salva em qualidade 1 (bem quantizada = pequena). Sem redimensionar (maxSide = lado),
        // reencodar a 100 preserva os blocos e ENGORDA: o original tem de ser mantido.
        $path = $this->tmp('jpg');
        imagejpeg($this->busy(2000, 2000), $path, 1);
        $hash = md5_file($path);

        $this->assertNull(ImageDownscaler::downscale($path, maxSide: 2000, quality: 100, minBytes: 1));
        $this->assertSame($hash, md5_file($path));
        $this->assertFileDoesNotExist($path.'.downscale.tmp');
    }
}
