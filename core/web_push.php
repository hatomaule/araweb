<?php
declare(strict_types=1);

function web_push_base64url_decode(string $value): string
{
    $decoded = base64_decode(strtr($value, '-_', '+/') . str_repeat('=', (4 - strlen($value) % 4) % 4), true);
    if ($decoded === false) {
        throw new InvalidArgumentException('Invalid Web Push base64url value.');
    }

    return $decoded;
}

function web_push_base64url_encode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function web_push_is_configured(): bool
{
    return (bool) (getenv('KOSKOSAN_VAPID_PUBLIC_KEY')
        && getenv('KOSKOSAN_VAPID_PRIVATE_KEY')
        && getenv('KOSKOSAN_VAPID_SUBJECT'));
}

function web_push_public_key(): ?string
{
    $key = getenv('KOSKOSAN_VAPID_PUBLIC_KEY');
    return is_string($key) && $key !== '' ? $key : null;
}

function web_push_der_length(int $length): string
{
    if ($length < 128) {
        return chr($length);
    }
    $encoded = '';
    while ($length > 0) {
        $encoded = chr($length & 0xff) . $encoded;
        $length >>= 8;
    }

    return chr(0x80 | strlen($encoded)) . $encoded;
}

function web_push_der_tlv(int $tag, string $value): string
{
    return chr($tag) . web_push_der_length(strlen($value)) . $value;
}

function web_push_p256_public_pem(string $rawKey): string
{
    if (strlen($rawKey) !== 65 || $rawKey[0] !== "\x04") {
        throw new InvalidArgumentException('Web Push P-256 public key has an invalid size.');
    }
    $algorithm = web_push_der_tlv(
        0x30,
        hex2bin('06072a8648ce3d0201') . hex2bin('06082a8648ce3d030107')
    );
    $subjectPublicKey = web_push_der_tlv(0x03, "\x00" . $rawKey);

    return "-----BEGIN PUBLIC KEY-----\n"
        . chunk_split(base64_encode(web_push_der_tlv(0x30, $algorithm . $subjectPublicKey)), 64, "\n")
        . "-----END PUBLIC KEY-----\n";
}

function web_push_ec_coordinate(mixed $coordinate): string
{
    if (!is_string($coordinate)) {
        throw new RuntimeException('OpenSSL did not return a P-256 coordinate.');
    }
    if (strlen($coordinate) === 32) {
        return $coordinate;
    }
    if (preg_match('/^[a-f0-9]{64}$/i', $coordinate)) {
        $decoded = hex2bin($coordinate);
        if ($decoded !== false) {
            return $decoded;
        }
    }

    throw new RuntimeException('OpenSSL returned a P-256 coordinate in an unsupported format.');
}

function web_push_hkdf(string $inputKey, string $salt, string $info, int $length): string
{
    $pseudorandomKey = hash_hmac('sha256', $inputKey, $salt, true);
    $output = '';
    $previous = '';
    for ($block = 1; strlen($output) < $length; $block++) {
        $previous = hash_hmac('sha256', $previous . $info . chr($block), $pseudorandomKey, true);
        $output .= $previous;
    }

    return substr($output, 0, $length);
}

function web_push_asn1_integer(string $value): string
{
    $value = ltrim($value, "\x00");
    if ($value === '') {
        $value = "\x00";
    }
    if (ord($value[0]) > 0x7f) {
        $value = "\x00" . $value;
    }

    return web_push_der_tlv(0x02, $value);
}

function web_push_ecdsa_der_to_raw(string $signature): string
{
    if (strlen($signature) < 8 || ord($signature[0]) !== 0x30) {
        throw new RuntimeException('OpenSSL returned an invalid VAPID signature.');
    }
    $offset = 2;
    if ((ord($signature[1]) & 0x80) !== 0) {
        $offset += (ord($signature[1]) & 0x7f);
    }
    if (ord($signature[$offset]) !== 0x02) {
        throw new RuntimeException('OpenSSL returned an invalid VAPID signature.');
    }
    $rLength = ord($signature[$offset + 1]);
    $r = substr($signature, $offset + 2, $rLength);
    $offset += 2 + $rLength;
    if (ord($signature[$offset]) !== 0x02) {
        throw new RuntimeException('OpenSSL returned an invalid VAPID signature.');
    }
    $sLength = ord($signature[$offset + 1]);
    $s = substr($signature, $offset + 2, $sLength);

    return str_pad(ltrim($r, "\x00"), 32, "\x00", STR_PAD_LEFT)
        . str_pad(ltrim($s, "\x00"), 32, "\x00", STR_PAD_LEFT);
}

function web_push_vapid_token(string $audience): string
{
    $publicKey = (string) getenv('KOSKOSAN_VAPID_PUBLIC_KEY');
    $privateKeyPem = (string) getenv('KOSKOSAN_VAPID_PRIVATE_KEY');
    $subject = (string) getenv('KOSKOSAN_VAPID_SUBJECT');
    $header = web_push_base64url_encode(json_encode(['typ' => 'JWT', 'alg' => 'ES256'], JSON_THROW_ON_ERROR));
    $claims = web_push_base64url_encode(json_encode([
        'aud' => $audience,
        'exp' => time() + 43200,
        'sub' => $subject,
    ], JSON_THROW_ON_ERROR));
    $unsignedToken = $header . '.' . $claims;
    $privateKey = openssl_pkey_get_private($privateKeyPem);
    if ($privateKey === false || !openssl_sign($unsignedToken, $derSignature, $privateKey, OPENSSL_ALGO_SHA256)) {
        throw new RuntimeException('Unable to sign Web Push VAPID token.');
    }
    $publicRaw = web_push_base64url_decode($publicKey);
    if (strlen($publicRaw) !== 65) {
        throw new RuntimeException('Configured Web Push public key is not a P-256 key.');
    }

    return $unsignedToken . '.' . web_push_base64url_encode(web_push_ecdsa_der_to_raw($derSignature));
}

function web_push_encrypt_payload(array $subscription, string $payload): string
{
    $userPublicKey = web_push_base64url_decode($subscription['p256dh_key']);
    $authSecret = web_push_base64url_decode($subscription['auth_secret']);
    if (strlen($userPublicKey) !== 65 || $userPublicKey[0] !== "\x04" || strlen($authSecret) < 16) {
        throw new InvalidArgumentException('Stored Web Push subscription keys are invalid.');
    }

    $ephemeralPrivate = openssl_pkey_new([
        'private_key_type' => OPENSSL_KEYTYPE_EC,
        'curve_name' => 'prime256v1',
    ]);
    if ($ephemeralPrivate === false) {
        throw new RuntimeException('Unable to generate Web Push encryption key.');
    }
    $ephemeralDetails = openssl_pkey_get_details($ephemeralPrivate);
    $ephemeralRaw = "\x04"
        . web_push_ec_coordinate($ephemeralDetails['ec']['x'] ?? null)
        . web_push_ec_coordinate($ephemeralDetails['ec']['y'] ?? null);
    $userPublic = openssl_pkey_get_public(web_push_p256_public_pem($userPublicKey));
    if ($userPublic === false) {
        throw new RuntimeException('Stored Web Push client key could not be parsed.');
    }
    $sharedSecret = openssl_pkey_derive($userPublic, $ephemeralPrivate, 32);
    if ($sharedSecret === false) {
        throw new RuntimeException('Unable to derive Web Push encryption secret.');
    }

    $inputKey = web_push_hkdf(
        $sharedSecret,
        $authSecret,
        "WebPush: info\x00" . $userPublicKey . $ephemeralRaw,
        32
    );
    $salt = random_bytes(16);
    $contentKey = web_push_hkdf($inputKey, $salt, "Content-Encoding: aes128gcm\x00", 16);
    $nonce = web_push_hkdf($inputKey, $salt, "Content-Encoding: nonce\x00", 12);
    $ciphertext = openssl_encrypt($payload . "\x02", 'aes-128-gcm', $contentKey, OPENSSL_RAW_DATA, $nonce, $tag);
    if ($ciphertext === false || strlen($tag) !== 16) {
        throw new RuntimeException('Unable to encrypt Web Push payload.');
    }

    return $salt . pack('N', 4096) . chr(strlen($ephemeralRaw)) . $ephemeralRaw . $ciphertext . $tag;
}

function web_push_endpoint_is_supported(string $endpoint): bool
{
    $parts = parse_url($endpoint);
    if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https'
        || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])
        || isset($parts['port']) || filter_var($parts['host'], FILTER_VALIDATE_IP)) {
        return false;
    }
    $host = strtolower($parts['host']);
    foreach (['fcm.googleapis.com', 'push.services.mozilla.com', 'push.apple.com', 'notify.windows.com'] as $suffix) {
        if ($host === $suffix || str_ends_with($host, '.' . $suffix)) {
            return true;
        }
    }

    return false;
}

function web_push_send_subscription(array $subscription, array $notification): int
{
    $endpoint = (string) $subscription['endpoint'];
    if (!web_push_endpoint_is_supported($endpoint)) {
        throw new InvalidArgumentException('Web Push endpoint is not an approved browser push service.');
    }
    $audienceParts = parse_url($endpoint);
    $audience = 'https://' . strtolower($audienceParts['host']);
    $body = web_push_encrypt_payload(
        $subscription,
        json_encode($notification, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
    );
    $headers = [
        'TTL: 60',
        'Content-Type: application/octet-stream',
        'Content-Encoding: aes128gcm',
        'Authorization: vapid t=' . web_push_vapid_token($audience)
            . ', k=' . (string) getenv('KOSKOSAN_VAPID_PUBLIC_KEY'),
    ];
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $headers),
            'content' => $body,
            'timeout' => 8,
            'ignore_errors' => true,
            'protocol_version' => 1.1,
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);
    $responseBody = @file_get_contents($endpoint, false, $context);
    $statusLine = $http_response_header[0] ?? '';
    if (!preg_match('/\s([1-5]\d{2})\s/', $statusLine, $matches)) {
        throw new RuntimeException('Browser push service returned no valid HTTP status.');
    }
    $status = (int) $matches[1];
    if ($status < 200 || $status >= 300) {
        throw new RuntimeException('Browser push service rejected the notification with HTTP ' . $status . '.');
    }

    return $status;
}

function web_push_notify_user(PDO $pdo, int $userId, array $notification): void
{
    if (!web_push_is_configured()) {
        return;
    }
    $statement = $pdo->prepare(
        'SELECT id,endpoint,p256dh_key,auth_secret FROM web_push_subscriptions
         WHERE user_id=:user_id ORDER BY id'
    );
    $statement->execute(['user_id' => $userId]);
    foreach ($statement->fetchAll() as $subscription) {
        try {
            web_push_send_subscription($subscription, $notification);
        } catch (Throwable $exception) {
            if (str_contains($exception->getMessage(), 'HTTP 404')
                || str_contains($exception->getMessage(), 'HTTP 410')) {
                $pdo->prepare('DELETE FROM web_push_subscriptions WHERE id=:id')
                    ->execute(['id' => $subscription['id']]);
                continue;
            }
            error_log('Web Push delivery failed for subscription ' . (int) $subscription['id']
                . ': ' . $exception->getMessage());
        }
    }
}
