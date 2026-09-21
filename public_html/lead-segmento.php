<?php
// Oravel — recebe o formulário curto das páginas de segmento do site.
// Ordem: (1) guarda o lead em arquivo, (2) entrega ao CRM do app (canal principal, não depende de
// e-mail), (3) avisa por e-mail (SMTP; mail() só se app E SMTP falharem). O visitante vê sucesso se
// qualquer canal entregou. Campos extras: segmento e porte da operação. As credenciais NÃO ficam
// neste arquivo: vêm de inc/lead-config.php, que existe só no servidor (fora do git).

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
$origemLabels = [
    'manutencao-industrial' => 'Manutenção Industrial',
    'montagem-industrial'   => 'Montagem Industrial',
    'logistica-transportes' => 'Logística e Transportes',
    'gestao-servicos'       => 'Gestão de Serviços',
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

// Entrega o lead ao CRM do app (POST /api/inbound/leads, canal identificado pelo token). O lead_id torna o
// reenvio idempotente no app, então 1 nova tentativa é segura. Devolve true só com HTTP 2xx + success.
function enviar_para_app(array $config, array $payload): bool
{
    $url = (string) ($config['app_leads_url'] ?? '');
    $token = (string) ($config['app_leads_token'] ?? '');
    if ($url === '' || $token === '') {
        return false; // canal não configurado neste ambiente
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
        error_log("lead-segmento: app tentativa {$tentativa} falhou (HTTP {$codigo} {$erro})");
        if ($codigo >= 400 && $codigo < 500) {
            return false; // erro do nosso lado (token/validação): repetir não adianta
        }
    }

    return false;
}

// Cópia de segurança do lead, ANTES de tentar o e-mail. Fica fora do docroot
// (/home2/<user>/leads-site/, nunca servido pelo web) e é só de acréscimo (JSON por linha):
// se o SMTP cair, nenhum lead se perde. Melhor esforço: falha aqui só vai para o error_log.
function guardar_lead(array $registro): void
{
    $dir = dirname(__DIR__) . '/leads-site';
    if (!is_dir($dir) && !@mkdir($dir, 0700, true)) {
        error_log('lead-segmento: não consegui criar ' . $dir);

        return;
    }
    $arquivo = $dir . '/leads.jsonl';
    if (@file_put_contents($arquivo, json_encode($registro, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX) === false) {
        error_log('lead-segmento: não consegui gravar ' . $arquivo);

        return;
    }
    @chmod($arquivo, 0600);
}

// 2º canal: mail() do PHP. Cai mais em spam que o SMTP autenticado, mas chega mesmo com
// a senha do SMTP quebrada. $nome/$email já passaram por limpar()/FILTER_VALIDATE_EMAIL.
function enviar_fallback_mail(string $assunto, string $corpo, string $email, string $nome): bool
{
    $cabecalhos = "From: Oravel <contato@oravel.com.br>\r\n"
        . 'Reply-To: ' . mb_encode_mimeheader($nome) . " <{$email}>\r\n"
        . "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\n";

    return @mail('contato@oravel.com.br', mb_encode_mimeheader($assunto), $corpo, $cabecalhos);
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
$assunto = "Novo lead ({$origem}) — {$empresa}";

// 1) guarda o lead antes de qualquer tentativa de envio
$lead = [
    'id' => bin2hex(random_bytes(6)), 'quando' => date('c'), 'origem' => $origem,
    'nome' => $nome, 'empresa' => $empresa, 'email' => $email, 'whatsapp' => $fone,
    'segmento' => $segKey, 'porte' => $porte,
];
guardar_lead($lead + ['status' => 'recebido']);

// 2) canal principal: CRM do app (notifica a equipe no sino). Não depende de e-mail.
$entreguePeloApp = enviar_para_app($config, array_filter([
    'lead_id'      => $lead['id'],
    'origem'       => $origem,
    'origem_label' => $origemLabels[$origem] ?? $origem,
    'name'         => $nome,
    'company'      => $empresa,
    'email'        => $email,
    'phone'        => $fone,
    'segmento'     => $segmentos[$segKey],
    'porte'        => $porte,
    'porte_label'  => $rotuloPorte,
], fn ($v) => $v !== ''));

// 3) aviso por e-mail (extra): SMTP; se falhar E o app também, mail() do PHP como último recurso.
$enviadoPorEmail = false;
$erroSmtp = '';
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
    $mail->Timeout    = 15;
    $mail->setFrom($config['smtp_user'], 'Oravel');
    $mail->addAddress('contato@oravel.com.br');
    $mail->addReplyTo($email, $nome);
    $mail->Subject = $assunto;
    $mail->Body    = $corpo;
    $mail->send();
    $enviadoPorEmail = true;
} catch (PHPMailerException $e) {
    $erroSmtp = mb_substr($e->getMessage(), 0, 200);
    error_log('lead-segmento: SMTP falhou: ' . $erroSmtp);
    if (! $entreguePeloApp && enviar_fallback_mail($assunto, $corpo, $email, $nome)) {
        $enviadoPorEmail = true;
    }
}

$canais = array_keys(array_filter(['app' => $entreguePeloApp, 'email' => $enviadoPorEmail]));
$status = $canais === [] ? 'falha_total' : 'entregue';
guardar_lead(['id' => $lead['id'], 'quando' => date('c'), 'status' => $status, 'canais' => $canais]
    + ($erroSmtp !== '' ? ['erro_smtp' => $erroSmtp] : []));

voltar($pagina, $status === 'falha_total' ? 'erro=envio' : 'enviado=1');
