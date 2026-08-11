<?php
/**
 * player_count.php — ретранслятор A2S_INFO для загрузочного экрана GMod.
 *
 * Возвращает JSON: { "players": 3, "maxplayers": 20 }
 *
 * Как подключить:
 *   1. Залей этот файл на свой веб-хостинг В ТУ ЖЕ ПАПКУ, где лежит loading.html
 *   2. Пропиши ниже IP и порт своего игрового сервера
 *   3. В loading.html запрос уже настроен (PLAYERS_API = 'player_count.php')
 *
 * Требования хостинга: PHP 5+ (обычный PHP-хостинг подходит).
 * Скрипт не требует API-ключей, кэширует ответ 5 секунд — не грузит сервер.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store');

/* ================= НАСТРОЙКИ ================= */
$SERVER_IP   = '127.0.0.1';   // ← IP твоего игрового сервера
$SERVER_PORT = 27015;         // ← порт твоего сервера (обычно 27015)
/* ============================================= */

$ip   = isset($_GET['ip'])   ? preg_replace('/[^0-9a-fA-F.:]/', '', $_GET['ip']) : $SERVER_IP;
$port = isset($_GET['port']) ? (int)$_GET['port'] : $SERVER_PORT;
if ($port < 1 || $port > 65535) $port = $SERVER_PORT;
// IPv6 нужно оборачивать в квадратные скобки
if (strpos($ip, ':') !== false && $ip[0] !== '[') $ip = '[' . $ip . ']';

$out = array('players' => null, 'maxplayers' => null);

/* Короткий кэш (5 сек) — чтобы много игроков не долбили сервер запросами */
$cacheFile = sys_get_temp_dir() . '/player_count_' . md5($ip . ':' . $port) . '.json';
if (is_file($cacheFile)){
    $mtime = @filemtime($cacheFile);
    if ($mtime !== false && time() - $mtime < 5){
        $cached = @file_get_contents($cacheFile);
        if ($cached !== false){ echo $cached; exit; }
    }
}

$sock = @fsockopen('udp://' . $ip, $port, $errno, $errstr, 3);
if (!$sock){
    echo json_encode($out);
    exit;
}
stream_set_timeout($sock, 3);

$ping = "\xFF\xFF\xFF\xFF\x54Source Engine Query\x00";
fwrite($sock, $ping);
$resp = fread($sock, 4096);

// Если сервер просит challenge (отвечает 0x41 'A') — отправляем ещё раз с кодом
if (strlen($resp) >= 6 && ord($resp[4]) === 0x41){
    $challenge = substr($resp, 5, 4);
    fwrite($sock, $ping . $challenge);
    $resp = fread($sock, 4096);
}

// Парсим A2S_INFO: заголовок FF FF FF FF + 0x49 'I'
if (strlen($resp) >= 9 && ord($resp[4]) === 0x49){
    $p = 5;                 // пропускаем заголовок и байт типа
    $p += 1;                // protocol
    for ($i = 0; $i < 4; $i++){        // name, map, folder, game — 4 строки
        while ($p < strlen($resp) && $resp[$p] !== "\x00") $p++;
        $p++;
    }
    $p += 2;                // appid (short LE)
    if ($p + 1 < strlen($resp)){
        $out['players']    = ord($resp[$p]);
        $out['maxplayers'] = ord($resp[$p + 1]);
    }
}
fclose($sock);

$json = json_encode($out);
@file_put_contents($cacheFile, $json, LOCK_EX);
echo $json;
