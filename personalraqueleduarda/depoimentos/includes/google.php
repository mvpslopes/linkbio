<?php
declare(strict_types=1);

function http_post_form(string $url, array $fields): array {
    $body = http_build_query($fields);
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($raw === false) {
            throw new RuntimeException('Falha ao contactar o Google.');
        }
        return ['code' => $code, 'body' => $raw];
    }

    $ctx = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $body,
            'timeout' => 20,
        ],
    ]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false) {
        throw new RuntimeException('Falha ao contactar o Google.');
    }
    return ['code' => 200, 'body' => $raw];
}

function http_get_json(string $url, string $accessToken): array {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $accessToken],
        ]);
        $raw = curl_exec($ch);
        curl_close($ch);
        if ($raw === false) {
            throw new RuntimeException('Falha ao obter dados do Google.');
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new RuntimeException('Resposta inválida do Google.');
        }
        return $data;
    }

    $ctx = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "Authorization: Bearer {$accessToken}\r\n",
            'timeout' => 20,
        ],
    ]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false) {
        throw new RuntimeException('Falha ao obter dados do Google.');
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        throw new RuntimeException('Resposta inválida do Google.');
    }
    return $data;
}

function google_auth_url(string $state): string {
    $params = [
        'client_id'     => cfg('google_client_id'),
        'redirect_uri'  => redirect_uri(),
        'response_type' => 'code',
        'scope'         => 'openid email profile',
        'access_type'   => 'online',
        'include_granted_scopes' => 'true',
        'prompt'        => 'select_account',
        'state'         => $state,
    ];
    return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
}

function google_exchange_code(string $code): array {
    $res = http_post_form('https://oauth2.googleapis.com/token', [
        'code'          => $code,
        'client_id'     => cfg('google_client_id'),
        'client_secret' => cfg('google_client_secret'),
        'redirect_uri'  => redirect_uri(),
        'grant_type'    => 'authorization_code',
    ]);
    $data = json_decode($res['body'], true);
    if (!is_array($data) || empty($data['access_token'])) {
        $err = is_array($data) ? ($data['error_description'] ?? $data['error'] ?? 'token_error') : 'token_error';
        throw new RuntimeException('Não foi possível autenticar: ' . $err);
    }
    return $data;
}

function google_fetch_userinfo(string $accessToken): array {
    $info = http_get_json('https://openidconnect.googleapis.com/v1/userinfo', $accessToken);
    if (empty($info['sub']) || empty($info['name'])) {
        throw new RuntimeException('Conta Google incompleta (nome obrigatório).');
    }
    return [
        'sub'     => (string) $info['sub'],
        'name'    => (string) $info['name'],
        'email'   => isset($info['email']) ? (string) $info['email'] : null,
        'picture' => isset($info['picture']) ? (string) $info['picture'] : null,
    ];
}
