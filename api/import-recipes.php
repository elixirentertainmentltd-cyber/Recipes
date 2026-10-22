<?php
ini_set('display_errors', '0');
require dirname(__DIR__) . '/lib.php';
require dirname(__DIR__) . '/import-lib.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function import_response(int $status, array $body): never {
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST'); import_response(405, ['error'=>'Use POST.']);
}
$https = ($_SERVER['HTTPS'] ?? '') === 'on' || (string)($_SERVER['SERVER_PORT'] ?? '') === '443' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
if (!$https) import_response(403, ['error'=>'HTTPS is required.']);
$hash = (string)(config()['import_api_key_hash'] ?? '');
$auth = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
if (!admin_configured() || !preg_match('/^[a-f0-9]{64}$/', $hash) || !preg_match('/^Bearer ([A-Za-z0-9_-]{32,256})$/D', $auth, $match) || !hash_equals($hash, hash('sha256', $match[1]))) {
    header('WWW-Authenticate: Bearer'); import_response(401, ['error'=>'An authorised import key is required.']);
}
if (strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/json') import_response(415, ['error'=>'Use application/json.']);
if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > IMPORT_MAX_BYTES) import_response(413, ['error'=>'Import exceeds 20 MB.']);
$input = fopen('php://input', 'rb');
$json = $input ? stream_get_contents($input, IMPORT_MAX_BYTES + 1) : false;
if ($json === false) import_response(400, ['error'=>'Request could not be read.']);
if (strlen($json) > IMPORT_MAX_BYTES) import_response(413, ['error'=>'Import exceeds 20 MB.']);
try {
    $payload = import_payload($json);
    import_response(200, import_recipes($payload));
} catch (InvalidArgumentException $e) {
    import_response(422, ['error'=>$e->getMessage()]);
} catch (Throwable $e) {
    error_log('Recipe import failed; inspect private storage permissions.');
    import_response(500, ['error'=>'Import could not be completed. Check storage and backup permissions before retrying.']);
}
