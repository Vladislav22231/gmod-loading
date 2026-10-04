<?php
// Реальный онлайн GMod-сервера через Source Query (A2S_INFO).
// Положи рядом с HTML и впиши IP и порт СВОЕГО игрового сервера.
const SERVER_IP   = '192.168.0.2';
const SERVER_PORT = 27015;
const CACHE_SEC   = 5;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('Access-Control-Allow-Origin: *');

$cache = sys_get_temp_dir() . '/ps_player_count.json';
if (is_file($cache) && time() - filemtime($cache) < CACHE_SEC) {
    readfile($cache);
    exit;
}

function readStr($d, &$o) {
    $e = strpos($d, "\0", $o);
    if ($e === false) return '';
    $s = substr($d, $o, $e - $o);
    $o = $e + 1;
    return $s;
}

function a2s($ip, $port) {
    $s = @stream_socket_client("udp://$ip:$port", $en, $es, 1);
    if (!$s) return null;
    stream_set_timeout($s, 1);
    $req = "\xFF\xFF\xFF\xFFTSource Engine Query\0";
    fwrite($s, $req);
    $r = fread($s, 1400);
    // сервер может попросить challenge
    if ($r !== false && strlen($r) >= 9 && $r[4] === 'A') {
        fwrite($s, $req . substr($r, 5, 4));
        $r = fread($s, 1400);
    }
    fclose($s);
    if (!$r || strlen($r) < 6 || $r[4] !== 'I') return null;
    $o = 6;               // после заголовка и protocol
    readStr($r, $o);      // name
    readStr($r, $o);      // map
    readStr($r, $o);      // folder
    readStr($r, $o);      // game
    $o += 2;              // app id
    if (strlen($r) < $o + 3) return null;
    $players = ord($r[$o]);
    $max     = ord($r[$o + 1]);
    $bots    = ord($r[$o + 2]);
    return ['players' => max(0, $players - $bots), 'max' => $max];
}

$info = a2s(SERVER_IP, SERVER_PORT);
$out  = json_encode($info ?: ['players' => null]);
if ($info) @file_put_contents($cache, $out);
echo $out;
