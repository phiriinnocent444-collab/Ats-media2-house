<?php
// Unit 8.2 Using HTTP in PHP: a tiny cURL client (api/airtel.php uses the same technique to call Airtel).
class HttpClient {
  public static function get(string $url, int $timeout = 10): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout, CURLOPT_HEADER => false]);
    $body = curl_exec($ch);
    $info = ['ok' => $body !== false, 'status' => curl_getinfo($ch, CURLINFO_HTTP_CODE), 'body' => (string) $body, 'error' => curl_error($ch)];
    curl_close($ch);
    return $info;
  }
}
