<?php
declare(strict_types=1);
require_once __DIR__ . '/../IndoorServiceAuth.php';

function expect(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
$secret = str_repeat('s', 32);
$method = 'POST'; $path = '/api/indoor/v1/imports/tvsumare';
$body = '{"cursor":"1"}'; $ts = 1800000000;
$nonce = str_repeat('a', 32);
$signature = IndoorServiceAuth::signature($secret,$method,$path,$body,$ts,$nonce);
$claimed = [];
$claim = static function(string $hash, int $timestamp) use (&$claimed): bool {
    if (isset($claimed[$hash])) return false;
    $claimed[$hash] = $timestamp;
    return true;
};
expect(IndoorServiceAuth::verify($secret,$method,$path,$body,$ts,$nonce,$signature,$claim,$ts),'Valid');
expect(!IndoorServiceAuth::verify($secret,$method,$path,$body,$ts,$nonce,$signature,$claim,$ts),'Replay');
expect(!IndoorServiceAuth::verify($secret,$method,$path,'{}',$ts,$nonce,$signature,$claim,$ts),'Body tampering');
expect(!IndoorServiceAuth::verify($secret,'GET',$path,$body,$ts,$nonce,$signature,$claim,$ts),'Method tampering');
expect(!IndoorServiceAuth::verify($secret,$method,$path,$body,$ts,$nonce,$signature,$claim,$ts+121),'Expired');
expect(!IndoorServiceAuth::verify($secret,$method,$path,$body,$ts,$nonce,str_repeat('0',64),$claim,$ts),'Wrong signature');
try { IndoorServiceAuth::signature($secret, 'POST', '/api/indoor/v1/../private', $body, $ts, $nonce); throw new RuntimeException('Unsafe path accepted'); }
catch (InvalidArgumentException $expected) {}
try { IndoorServiceAuth::signature('short', $method, $path, $body, $ts, $nonce); throw new RuntimeException('Short key accepted'); }
catch (InvalidArgumentException $expected) {}
echo "OK: Indoor service auth valid/replay/tamper/expiry/secret/path\n";
