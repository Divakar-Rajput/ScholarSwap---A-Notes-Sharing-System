<?php
require_once ("config/connection.php");

function _getActiveKey(): array
{
    static $cached = null;
    if ($cached !== null) return $cached;

    global $conn;

    $st = $conn->prepare("SELECT key_id, secret_key FROM encryption_keys WHERE is_active = 1 LIMIT 1");
    $st->execute();
    $row = $st->fetch(PDO::FETCH_ASSOC);

    if (!$row) throw new RuntimeException('No active encryption key found.');

    $cached = $row;
    return $cached;
}

function _getKeyById(int $keyId): ?array
{
    static $cache = [];
    if (isset($cache[$keyId])) return $cache[$keyId];

    global $conn;

    $st = $conn->prepare("SELECT key_id, secret_key FROM encryption_keys WHERE key_id = ? LIMIT 1");
    $st->execute([$keyId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    $cache[$keyId] = $row ?: null;
    return $cache[$keyId];
}

function encryptId(int|string $id): string
{
    $activeKey = _getActiveKey();
    $key       = hash('sha256', $activeKey['secret_key'], true);
    $iv        = random_bytes(16);
    $payload   = (is_int($id) ? 'i:' : 's:') . $id;

    $encrypted = openssl_encrypt($payload, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

    return strtr(base64_encode(pack('n', $activeKey['key_id']) . $iv . $encrypted), '+/=', '-_~');
}

function decryptId(string $token): int|string|null
{
    if (empty($token)) return null;

    $combined = base64_decode(strtr($token, '-_~', '+/='));
    if ($combined === false || strlen($combined) < 19) return null;

    $keyId     = unpack('n', substr($combined, 0, 2))[1];
    $iv        = substr($combined, 2, 16);
    $encrypted = substr($combined, 18);

    $keyRow = _getKeyById($keyId);
    if (!$keyRow) return null;

    $payload = openssl_decrypt($encrypted, 'AES-256-CBC', hash('sha256', $keyRow['secret_key'], true), OPENSSL_RAW_DATA, $iv);
    if ($payload === false) return null;

    if (str_starts_with($payload, 'i:')) return (int)    substr($payload, 2);
    if (str_starts_with($payload, 's:')) return (string) substr($payload, 2);

    return null;
}
?>