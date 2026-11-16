<?php
// Airtel Money Open API client (Collection). The only file that talks to Airtel.
require_once __DIR__ . '/helpers.php';   // also provides normalize_msisdn()

function airtel_cfg(): array { global $CONFIG; return $CONFIG['airtel']; }

function airtel_base(): string {
  $a = airtel_cfg();
  return strtoupper($a['env']) === 'PROD' ? $a['base_prod'] : $a['base_uat'];
}

function airtel_http(string $method, string $url, array $headers, ?array $body = null): array {
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_CUSTOMREQUEST  => $method,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_HTTPHEADER     => $headers,
  ]);
  if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
  $res  = curl_exec($ch);
  $err  = curl_error($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  if ($res === false) throw new RuntimeException("Network error: $err");
  if ($code >= 400)   throw new RuntimeException("Airtel HTTP $code: " . $res);
  $data = json_decode($res, true);
  return is_array($data) ? $data : [];
}

// OAuth2 client-credentials; token cached in a temp file until shortly before expiry.
function airtel_token(): string {
  $cache = sys_get_temp_dir() . '/ats_airtel_token.json';
  if (is_file($cache)) {
    $c = json_decode(file_get_contents($cache), true);
    if ($c && $c['expires_at'] > time()) return $c['token'];
  }
  $a = airtel_cfg();
  $r = airtel_http('POST', airtel_base() . '/auth/oauth2/token',
    ['Content-Type: application/json'],
    ['client_id' => $a['client_id'], 'client_secret' => $a['client_secret'], 'grant_type' => 'client_credentials']);
  if (empty($r['access_token'])) throw new RuntimeException('No access_token returned by Airtel.');
  file_put_contents($cache, json_encode([
    'token'      => $r['access_token'],
    'expires_at' => time() + ((int)($r['expires_in'] ?? 180)) - 30,
  ]));
  return $r['access_token'];
}

function airtel_auth_headers(): array {
  $a = airtel_cfg();
  return ['Content-Type: application/json', 'Accept: */*',
          'Authorization: Bearer ' . airtel_token(),
          'X-Country: ' . $a['country'], 'X-Currency: ' . $a['currency']];
}

// Pushes the "Please Confirm" PIN prompt to the customer's phone.
function airtel_request_payment(string $phone, float $amount, string $txnId): array {
  $a = airtel_cfg();
  return airtel_http('POST', airtel_base() . '/merchant/v2/payments/', airtel_auth_headers(), [
    'reference'   => "ATS-$txnId",
    'subscriber'  => ['country' => $a['country'], 'currency' => $a['currency'], 'msisdn' => normalize_msisdn($phone)],
    'transaction' => ['amount' => $amount, 'country' => $a['country'], 'currency' => $a['currency'], 'id' => $txnId],
  ]);
}

function airtel_check_status(string $txnId): array {
  return airtel_http('GET', airtel_base() . "/standard/v1/payments/$txnId", airtel_auth_headers());
}
