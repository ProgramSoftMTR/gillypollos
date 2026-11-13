<?php
header('Content-Type: application/json; charset=utf-8');

define('GOOGLE_PLACES_API_KEY', 'AIzaSyD-3k6TtNjKdeME0_Q6QkdCWzEYAd8aSnc');
define('CACHE_FILE', __DIR__ . '/cache_google_place.json');
define('CACHE_TTL', 300);

date_default_timezone_set('America/Bogota');

function respond($data) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function curl_post_json($url, $payload, $headers) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);
    return [$response, $httpCode, $curlErr];
}

function formatHour12($dt) {
    $hour = (int)$dt->format('g');
    $minute = $dt->format('i');
    $ampm = ($dt->format('a') === 'am') ? 'a.m.' : 'p.m.';
    if ($minute === '00') {
        return $hour . ' ' . $ampm;
    }
    return $hour . ':' . $minute . ' ' . $ampm;
}

function build_fallback($debug = null, $source = 'google_fallback') {
    $data = [
        'ok' => true,
        'is_open' => false,
        'status_text' => 'Cerrado · Abre a las 4 p.m.',
        'rating' => '4,7',
        'reviews' => 19,
        'maps_url' => null,
        'source' => $source,
    ];
    if ($debug !== null) $data['debug_error'] = $debug;
    return $data;
}

// Cache check
if (file_exists(CACHE_FILE)) {
    $raw = @file_get_contents(CACHE_FILE);
    if ($raw !== false) {
        $cached = json_decode($raw, true);
        if (is_array($cached) && isset($cached['cached_at']) && (time() - (int)$cached['cached_at']) < CACHE_TTL) {
            unset($cached['cached_at']);
            respond($cached);
        }
    }
}

$url = 'https://places.googleapis.com/v1/places:searchText';
$payload = [
    'textQuery' => 'Gil&pollos Cra 7 #11 - 23 Monteria Cordoba',
    'languageCode' => 'es',
];
$headers = [
    'Content-Type: application/json',
    'X-Goog-Api-Key: ' . GOOGLE_PLACES_API_KEY,
    'X-Goog-FieldMask: places.id,places.displayName,places.rating,places.userRatingCount,places.currentOpeningHours,places.regularOpeningHours,places.googleMapsUri',
];

list($resp, $code, $err) = curl_post_json($url, $payload, $headers);

$debugError = null;
if ($resp === false || $code !== 200) {
    $debugError = 'curl error: ' . $err . ' (HTTP ' . $code . ')';
    $data = build_fallback($debugError, 'google_fallback');
    respond($data);
}

$json = json_decode($resp, true);
if (!is_array($json) || empty($json['places'][0])) {
    $debugError = 'no place found: ' . ($json['error']['message'] ?? 'unknown');
    $data = build_fallback($debugError, 'google_fallback');
    respond($data);
}

$place = $json['places'][0];

$isOpen = false;
if (isset($place['currentOpeningHours']['openNow'])) {
    $isOpen = (bool)$place['currentOpeningHours']['openNow'];
} elseif (isset($place['regularOpeningHours']['openNow'])) {
    $isOpen = (bool)$place['regularOpeningHours']['openNow'];
}

$tz = new DateTimeZone('America/Bogota');
if ($isOpen) {
    $closeStr = $place['currentOpeningHours']['nextCloseTime'] ?? null;
    if ($closeStr) {
        $dt = new DateTime($closeStr);
        $dt->setTimezone($tz);
        $hora = formatHour12($dt);
        $h = (int)$dt->format('g');
        $prep = ($h === 1) ? 'a la' : 'a las';
        $statusText = 'Abierto · Cierra ' . $prep . ' ' . $hora;
    } else {
        $statusText = 'Abierto ahora';
    }
} else {
    $openStr = $place['currentOpeningHours']['nextOpenTime'] ?? null;
    if ($openStr) {
        $dt = new DateTime($openStr);
        $dt->setTimezone($tz);
        $hora = formatHour12($dt);
        $h = (int)$dt->format('g');
        $prep = ($h === 1) ? 'a la' : 'a las';
        $statusText = 'Cerrado · Abre ' . $prep . ' ' . $hora;
    } else {
        $statusText = 'Cerrado · Abre a las 4 p.m.';
    }
}

$rating = number_format((float)($place['rating'] ?? 4.7), 1, ',', '.');
$reviews = (int)($place['userRatingCount'] ?? 19);
$mapsUrl = $place['googleMapsUri'] ?? null;

$data = [
    'ok' => true,
    'is_open' => $isOpen,
    'status_text' => $statusText,
    'rating' => $rating,
    'reviews' => $reviews,
    'maps_url' => $mapsUrl,
    'source' => 'google_live',
];

// Save cache ONLY for google_live
$data['cached_at'] = time();
@file_put_contents(CACHE_FILE, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
unset($data['cached_at']);

respond($data);
?>