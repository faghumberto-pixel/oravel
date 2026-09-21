<?php
// Oravel — recebe o formulário curto das páginas de segmento e envia por e-mail.
// Mesma lógica do contato.php (PHPMailer + SMTP Titan, Turnstile + honeypot), mas com os
// campos extras dessas páginas (segmento e porte da operação). As credenciais NÃO ficam neste
// arquivo: vêm de inc/lead-config.php, que existe só no servidor (fora do git).

declare(strict_types=1);

require __DIR__ . '/inc/PHPMailer/Exception.php';
require __DIR__ . '/inc/PHPMailer/PHPMailer.php';
require __DIR__ . '/inc/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

$config = require __DIR__ . '/inc/lead-config.php';

// origem => página de retorno. Lista fechada: nunca redirecionar para URL vinda do POST.
$paginas = [
    'manutencao-industrial' => '/software-manutencao-industrial/',
    'montagem-industrial'   => '/software-montagem-industrial/',
    'logistica-transportes' => '/software-gestao-frota-logistica/',
    'gestao-servicos'       => '/software-gestao-de-servicos/',
];
$segmentos = [
    'locacao' => 'Locação de Equipamentos',
    'manutencao-industrial' => 'Manutenção Industrial',
    'montagem-industrial' => 'Montagem Industrial',
    'logistica-transportes' => 'Logística e Transportes',
    'gestao-servicos' => 'Gestão de Serviços',
    'distribuidor-atacadista' => 'Distribuidor e Atacadista',
    'outro' => 'Outro segmento',
];
$portes = ['1 a 5', '6 a 15', '16 a 50', 'Mais de 50'];

function limpar(mixed $v, int $max): string
{
    $v = is_string($v) ? $v : '';
    $v = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $v) ?? '';

    return mb_substr(trim($v), 0, $max);
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

function voltar(string $pagina, string $query): never
{
    header('Location: ' . $pagina . '?' . $query . '#contato', true, 303);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: /segmentos/', true, 303);
    exit;
}

$origem = limpar($_POST['origem'] ?? '', 40);
$pagina = $paginas[$origem] ?? '/segmentos/';

// Campo armadilha: bot preenche, humano não vê. Finge sucesso para não dar pista.
if (limpar($_POST['website'] ?? '', 200) !== '') {
    voltar($pagina, 'enviado=1');
}

$nome    = limpar($_POST['name'] ?? '', 120);
$empresa = limpar($_POST['company'] ?? '', 120);
$email   = limpar($_POST['email'] ?? '', 160);
$fone    = limpar($_POST['phone'] ?? '', 30);
$segKey  = limpar($_POST['segmento'] ?? '', 40);
$porte   = limpar($_POST['porte'] ?? '', 20);

if (
    mb_strlen($nome) < 2 || mb_strlen($empresa) < 2
    || !filter_var($email, FILTER_VALIDATE_EMAIL)
    || !isset($segmentos[$segKey]) || !in_array($porte, $portes, true)
    || ($fone !== '' && !preg_match('/^[0-9 ()+\-.]{8,30}$/', $fone))
) {
    voltar($pagina, 'erro=dados');
}

if (!turnstile_ok((string) ($_POST['cf-turnstile-response'] ?? ''), $config['turnstile_secret'], $_SERVER['REMOTE_ADDR'] ?? '')) {
    voltar($pagina, 'erro=captcha');
}

$rotuloPorte = $origem === 'logistica-transportes' ? 'Nº de veículos' : 'Nº de técnicos';
$corpo  = "Nome: {$nome}\n";
$corpo .= "Empresa: {$empresa}\n";
$corpo .= "E-mail: {$email}\n";
$corpo .= 'WhatsApp: ' . ($fone !== '' ? $fone : '(não informado)') . "\n";
$corpo .= 'Segmento: ' . $segmentos[$segKey] . "\n";
$corpo .= "{$rotuloPorte}: {$porte}\n";
$corpo .= "Origem: página {$origem}\n";

try {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = $config['smtp_host'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $config['smtp_user'];
    $mail->Password   = $config['smtp_password'];
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = (int) $config['smtp_port'];
    $mail->CharSet    = 'UTF-8';
    $mail->setFrom($config['smtp_user'], 'Oravel');
    $mail->addAddress('contato@oravel.com.br');
    $mail->addReplyTo($email, $nome);
    $mail->Subject = "Novo lead ({$origem}) — {$empresa}";
    $mail->Body    = $corpo;
    $mail->send();
} catch (PHPMailerException $e) {
    voltar($pagina, 'erro=envio');
}

voltar($pagina, 'enviado=1');
