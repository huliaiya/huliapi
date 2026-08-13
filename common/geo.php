<?php
if (!defined('HULI_GEO_LIB')) { define('HULI_GEO_LIB', 1); }

function huli_pconline_geo($ip) {
    $url = 'https://whois.pconline.com.cn/ipJson.jsp?json=true';
    if ($ip !== '') {
        $url .= '&ip=' . urlencode($ip);
    }
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    $data = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErrno = curl_errno($ch);
    curl_close($ch);
    if ($curlErrno !== 0 || $httpCode !== 200 || !$data) {
        return null;
    }
    $decoded = json_decode($data, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $data = mb_convert_encoding($data, 'UTF-8', 'GBK');
        $decoded = json_decode($data, true);
    } elseif (is_array($decoded) && !empty($decoded['pro'])) {
        $converted = mb_convert_encoding($data, 'UTF-8', 'GBK');
        $decoded = json_decode($converted, true) ?: $decoded;
    }
    return is_array($decoded) ? $decoded : null;
}
