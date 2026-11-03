<?php
// Oravel — recebe o formulário de qualquer ISCA (material gratuito) de /materiais-gratuitos/.
// Mesmo desenho do lead-segmento.php: (1) guarda o lead em arquivo, (2) entrega ao CRM do app (com a origem da
// isca), (3) envia o material por e-mail (SMTP, anexo) ao próprio lead. Os arquivos ficam FORA do docroot
// (/home2/<user>/materiais/): nunca há link público. As credenciais vêm de inc/lead-config.php, que existe só no
// servidor (fora do git).
//
// Nova isca = 1 item em $iscas abaixo + a página em /materiais-gratuitos/<slug>/ (com <input name="origem">
// igual à chave) + o arquivo em /home2/<user>/materiais/ + uma linha no sitemap e no índice.

declare(strict_types=1);

require __DIR__ . '/inc/PHPMailer/Exception.php';
require __DIR__ . '/inc/PHPMailer/PHPMailer.php';
require __DIR__ . '/inc/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

$config = require __DIR__ . '/inc/lead-config.php';

// origem => dados da isca. Lista fechada: nunca redirecionar para URL nem ler arquivo vindos do POST.
$iscas = [
    'isca-planilha-manutencao' => [
        'label'    => 'Isca: planilha de manutenção preventiva',
        'pagina'   => '/materiais-gratuitos/planilha-manutencao-preventiva/',
        'arquivo'  => 'Oravel_Controle_Manutencao_Preventiva.xlsx',
        'mime'     => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'assunto'  => 'Sua planilha de manutenção preventiva (Oravel)',
        'material' => 'planilha de controle de manutenção preventiva',
        'miolo'    => 'Abra o arquivo, cadastre seus ativos, defina os intervalos em dias e/ou horas e a planilha mostra o que está em dia, em atenção ou vencido.',
    ],
];

function limpar(mixed $v, int $max): string
{
    $v = is_string($v) ? $v : '';
    $v = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $v) ?? '';

    return mb_substr(trim($v), 0, $max);
}

function voltar(string $pagina, string $query): never
{
    header('Location: ' . $pagina . '?' . $query . '#baixar', true, 303);
    exit;
}

function turnstile_ok(string $token, string $secret, string $ip): bool
{
    if ($token === '') {
        return false;
    }
    $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_POSTFIELDS     => http_build_query(['secret' => $secret, 'response' => $token, 'remoteip' => $ip]),
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);

    return $resp !== false && (bool) (json_decode($resp, true)['success'] ?? false);
}

// POST /api/inbound/leads (canal identificado pelo token). lead_id torna o reenvio idempotente.
function enviar_para_app(array $config, array $payload): bool
{
    $url = (string) ($config['app_leads_url'] ?? '');
    $token = (string) ($config['app_leads_token'] ?? '');
    if ($url === '' || $token === '') {
        return false;
    }

    for ($tentativa = 1; $tentativa <= 2; $tentativa++) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json', 'X-Channel-Token: ' . $token],
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);
        $resp = curl_exec($ch);
        $codigo = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $erro = curl_error($ch);
        curl_close($ch);

        if ($resp !== false && $codigo >= 200 && $codigo < 300 && (bool) (json_decode($resp, true)['success'] ?? false)) {
            return true;
        }
        error_log("lead-isca: app tentativa {$tentativa} falhou (HTTP {$codigo} {$erro})");
        if ($codigo >= 400 && $codigo < 500) {
            return false;
        }
    }

    return false;
}

// Cópia de segurança (JSON por linha, fora do docroot, só de acréscimo): se tudo falhar, o lead não se perde.
function guardar_lead(array $registro): void
{
    $dir = dirname(__DIR__) . '/leads-site';
    if (!is_dir($dir) && !@mkdir($dir, 0700, true)) {
        error_log('lead-isca: não consegui criar ' . $dir);

        return;
    }
    $arquivo = $dir . '/leads.jsonl';
    if (@file_put_contents($arquivo, json_encode($registro, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX) === false) {
        error_log('lead-isca: não consegui gravar ' . $arquivo);

        return;
    }
    @chmod($arquivo, 0600);
}

function corpo_html(string $nome, array $isca): string
{
    $nome = htmlspecialchars($nome, ENT_QUOTES, 'UTF-8');

    return '<div style="font-family:Manrope,Arial,sans-serif;color:#1F2937;max-width:560px;margin:0 auto;border:1px solid #E5E7EB;border-radius:12px;overflow:hidden">'
        . '<div style="padding:20px 28px;border-bottom:1px solid #E5E7EB"><div style="font-size:24px;font-weight:800;letter-spacing:.06em;line-height:1">O<span style="color:#F26B1D">R</span>AVEL</div>'
        . '<div style="font-size:12px;color:#4B5563;margin-top:2px">Desenvolvimento e tecnologia</div></div>'
        . '<div style="padding:28px;font-size:16px;line-height:1.6"><p style="margin-top:0">Olá, ' . $nome . '!</p>'
        . '<p>Segue em anexo a <strong>' . htmlspecialchars($isca['material'], ENT_QUOTES, 'UTF-8') . '</strong>. ' . htmlspecialchars($isca['miolo'], ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p>Quando isso ficar pequeno para a sua operação, a Oravel organiza ativos, manutenção e serviços em um só sistema. É só responder este e-mail ou falar pelo <a href="https://wa.me/5519999332615" style="color:#C2410C">WhatsApp (19) 99933-2615</a>.</p>'
        . '<p style="margin-bottom:0">Equipe Oravel</p></div>'
        . '<div style="padding:16px 28px;background:#F9FAFB;font-size:12px;color:#6B7280">Oravel Desenvolvimento de Software LTDA · CNPJ 65.707.953/0001-30</div></div>';
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: /materiais-gratuitos/', true, 303);
    exit;
}

$origem = limpar($_POST['origem'] ?? '', 40);
$isca = $iscas[$origem] ?? null;
if ($isca === null) {
    header('Location: /materiais-gratuitos/', true, 303);
    exit;
}
$pagina = $isca['pagina'];

// Campo armadilha: bot preenche, humano não vê. Finge sucesso sem gravar nem enviar nada.
if (limpar($_POST['website'] ?? '', 200) !== '') {
    voltar($pagina, 'enviado=1');
}

$nome    = limpar($_POST['name'] ?? '', 120);
$empresa = limpar($_POST['company'] ?? '', 120);
$email   = limpar($_POST['email'] ?? '', 160);
$fone    = limpar($_POST['phone'] ?? '', 30);

if (
    mb_strlen($nome) < 2 || mb_strlen($empresa) < 2
    || !filter_var($email, FILTER_VALIDATE_EMAIL)
    || !preg_match('/^[0-9 ()+\-.]{8,30}$/', $fone)
) {
    voltar($pagina, 'erro=dados');
}

if (!turnstile_ok((string) ($_POST['cf-turnstile-response'] ?? ''), $config['turnstile_secret'], $_SERVER['REMOTE_ADDR'] ?? '')) {
    voltar($pagina, 'erro=captcha');
}

// Origem do anúncio (o t.js preenche os campos ocultos). Só valores simples; o resto é descartado.
$anuncio = [];
foreach (['gclid', 'gbraid', 'wbraid'] as $k) {
    $v = limpar($_POST[$k] ?? '', 200);
    if ($v !== '' && preg_match('/^[A-Za-z0-9_\-]+$/', $v)) { $anuncio[$k] = $v; }
}
foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'landing_url'] as $k) {
    $v = limpar($_POST[$k] ?? '', $k === 'landing_url' ? 255 : 200);
    if ($v !== '') { $anuncio[$k] = $v; }
}

// 1) guarda o lead antes de qualquer tentativa de envio
$lead = [
    'id' => bin2hex(random_bytes(6)), 'quando' => date('c'), 'origem' => $origem,
    'nome' => $nome, 'empresa' => $empresa, 'email' => $email, 'whatsapp' => $fone,
] + $anuncio;
guardar_lead($lead + ['status' => 'recebido']);

// 2) CRM do app (notifica a equipe). O app exige segmento e porte: a isca não pergunta, vão como "não informado".
$entreguePeloApp = enviar_para_app($config, array_filter([
    'lead_id'      => $lead['id'],
    'origem'       => $origem,
    'origem_label' => $isca['label'],
    'name'         => $nome,
    'company'      => $empresa,
    'email'        => $email,
    'phone'        => $fone,
    'segmento'     => 'Não informado',
    'porte'        => 'Não informado',
    'porte_label'  => 'Porte',
] + $anuncio, fn ($v) => $v !== ''));

// 3) planilha por e-mail, como anexo. Arquivo fora do docroot.
$enviadoPorEmail = false;
$erroSmtp = '';
$caminho = dirname(__DIR__) . '/materiais/' . $isca['arquivo'];
try {
    if (!is_file($caminho)) {
        throw new PHPMailerException('planilha ausente em ' . $caminho);
    }
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = $config['smtp_host'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $config['smtp_user'];
    $mail->Password   = $config['smtp_password'];
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = (int) $config['smtp_port'];
    $mail->CharSet    = 'UTF-8';
    $mail->Timeout    = 15;
    $mail->setFrom($config['smtp_user'], 'Oravel');
    $mail->addAddress($email, $nome);
    $mail->addBCC('contato@oravel.com.br');
    $mail->addReplyTo('contato@oravel.com.br', 'Oravel');
    $mail->Subject = $isca['assunto'];
    $mail->isHTML(true);
    $mail->Body    = corpo_html($nome, $isca);
    $mail->AltBody = "Olá, {$nome}! Segue em anexo a {$isca['material']} da Oravel. Dúvidas: WhatsApp (19) 99933-2615.";
    $mail->addAttachment($caminho, $isca['arquivo'], PHPMailer::ENCODING_BASE64, $isca['mime']);
    $mail->send();
    $enviadoPorEmail = true;
} catch (PHPMailerException $e) {
    $erroSmtp = mb_substr($e->getMessage(), 0, 200);
    error_log('lead-isca: e-mail falhou: ' . $erroSmtp);
}

$canais = array_keys(array_filter(['app' => $entreguePeloApp, 'email' => $enviadoPorEmail]));
guardar_lead(['id' => $lead['id'], 'quando' => date('c'), 'status' => $enviadoPorEmail ? 'entregue' : ($entreguePeloApp ? 'lead_ok_sem_planilha' : 'falha_total'), 'canais' => $canais]
    + ($erroSmtp !== '' ? ['erro_smtp' => $erroSmtp] : []));

// O visitante só vê "enviamos" se a planilha de fato saiu; se só o lead foi registrado, a equipe manda à mão.
voltar($pagina, $enviadoPorEmail ? 'enviado=1' : 'erro=envio');
