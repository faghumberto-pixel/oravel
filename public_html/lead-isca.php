<?php
// Oravel — recebe o formulário de qualquer ISCA (material gratuito) de /materiais-gratuitos/.
// (1) guarda o lead em arquivo, (2) entrega ao CRM do app (POST /api/inbound/leads, com a origem da isca).
// Quem ENVIA o material por e-mail é o app (App\Support\Iscas): o app já tem a caixa de e-mail funcionando e o
// site não guarda senha de SMTP (a que ficava aqui parou de autenticar e a planilha deixou de sair). A resposta do
// app traz `email_enviado`; o visitante só vê "enviamos" se for true. A planilha fica no app (storage/app/private).
// Credenciais do canal vêm de inc/lead-config.php, que existe só no servidor (fora do git).
//
// Nova isca = 1 item em $iscas abaixo + 1 linha em App\Support\Iscas::mails() + a página em
// /materiais-gratuitos/<slug>/ (com <input name="origem"> igual à chave) + uma linha no sitemap e no índice.

declare(strict_types=1);

$config = require __DIR__ . '/inc/lead-config.php';

// origem => dados da isca. Lista fechada: nunca redirecionar para URL nem ler arquivo vindos do POST.
$iscas = [
    'isca-planilha-manutencao' => [
        'label'   => 'Isca: planilha de manutenção preventiva',
        'pagina'  => '/materiais-gratuitos/planilha-manutencao-preventiva/',
        'arquivo' => 'Oravel_Controle_Manutencao_Preventiva.xlsx', // fica no app, em storage/app/private
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

// POST /api/inbound/leads (canal identificado pelo token). lead_id torna o reenvio idempotente. Devolve o JSON do app
// (success=true) ou null. O app envia o e-mail da isca dentro desta chamada (SMTP), por isso o timeout maior.
function enviar_para_app(array $config, array $payload): ?array
{
    $url = (string) ($config['app_leads_url'] ?? '');
    $token = (string) ($config['app_leads_token'] ?? '');
    if ($url === '' || $token === '') {
        return null;
    }

    for ($tentativa = 1; $tentativa <= 2; $tentativa++) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 25,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json', 'X-Channel-Token: ' . $token],
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);
        $resp = curl_exec($ch);
        $codigo = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $erro = curl_error($ch);
        curl_close($ch);

        $json = $resp !== false ? json_decode($resp, true) : null;
        if (is_array($json) && $codigo >= 200 && $codigo < 300 && (bool) ($json['success'] ?? false)) {
            return $json;
        }
        error_log("lead-isca: app tentativa {$tentativa} falhou (HTTP {$codigo} {$erro})");
        if ($codigo >= 400 && $codigo < 500) {
            return null; // erro do nosso lado (token/validação): repetir não adianta
        }
    }

    return null;
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

// 2) CRM do app + e-mail com a planilha (o app envia). O app exige segmento e porte: a isca não pergunta, vão como "não informado".
$resposta = enviar_para_app($config, array_filter([
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

$entreguePeloApp = $resposta !== null;
$enviadoPorEmail = $entreguePeloApp && ($resposta['email_enviado'] ?? false) === true;

guardar_lead(['id' => $lead['id'], 'quando' => date('c'), 'status' => $enviadoPorEmail ? 'entregue' : ($entreguePeloApp ? 'lead_ok_sem_planilha' : 'falha_total'),
    'canais' => $entreguePeloApp ? ['app'] : []] + ($enviadoPorEmail ? ['email' => 'enviado pelo app'] : []));

// O visitante só vê "enviamos" se a planilha de fato saiu; se só o lead foi registrado, a equipe manda à mão.
voltar($pagina, $enviadoPorEmail ? 'enviado=1' : 'erro=envio');
