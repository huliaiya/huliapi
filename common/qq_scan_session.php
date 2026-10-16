<?php
// QQ Space scan session manager
// Uses ptlogin2 protocol: ptqrshow -> ptqrlogin -> check_sig (extracts uin)

function huli_qq_hash33(string $str): int {
    $e = 0;
    for ($i = 0, $len = strlen($str); $i < $len; $i++) {
        $e = (($e << 5) + ord($str[$i])) & 0x7FFFFFFF;
    }
    return $e;
}

// Mark a uin as authorized by a successful QR scan so the confirm step
// only accepts QQ numbers that actually came back from the scan.
function huli_qq_mark_authorized(string $uin): void {
    if ($uin === '') {
        return;
    }
    $_SESSION['huli_qq_authorized'][$uin] = time();
    // Keep the authorized set small (last 20 entries)
    if (count($_SESSION['huli_qq_authorized']) > 20) {
        asort($_SESSION['huli_qq_authorized']);
        $_SESSION['huli_qq_authorized'] = array_slice($_SESSION['huli_qq_authorized'], -20, null, true);
    }
}

// Returns true when the QQ number was returned by a scan in the last 10 minutes.
function huli_qq_is_authorized(string $uin): bool {
    if ($uin === '' || !isset($_SESSION['huli_qq_authorized'][$uin])) {
        return false;
    }
    return (time() - (int)$_SESSION['huli_qq_authorized'][$uin]) <= 600;
}

function huli_qq_scan_new_session() {
    $url = 'https://ssl.ptlogin2.qq.com/ptqrshow?appid=549000912&e=2&l=M&s=3&d=72&v=4&t=' . (string)(microtime(true)) . '&daid=5&pt_3rd_aid=0&u1=https://qzs.qq.com/qzone/v5/loginsucc.html?para=izone';
    $headers = [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Referer: https://xui.ptlogin2.qq.com/',
        'Accept: image/avif,image/webp,image/png,image/*;q=0.8,*/*;q=0.7',
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    $resp = curl_exec($ch);
    $error = curl_error($ch);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    if ($resp === false || $resp === '' || $header_size <= 0) {
        return ['ok' => false, 'error' => $error !== '' ? $error : '无法获取 QQ 扫码二维码'];
    }

    $raw_headers = substr($resp, 0, $header_size);
    $img = substr($resp, $header_size);
    $qrsig = '';
    foreach (explode("\n", $raw_headers) as $line) {
        if (stripos($line, 'Set-Cookie:') === 0 && preg_match('/qrsig=([a-f0-9]+)/i', $line, $m)) {
            $qrsig = $m[1];
            break;
        }
    }

    if ($qrsig === '') {
        return ['ok' => false, 'error' => '未获取到 QR 会话参数'];
    }

    $ptqrtoken = (string)huli_qq_hash33($qrsig);
    $sid = md5(uniqid((string)random_int(1, PHP_INT_MAX), true));
    $_SESSION['huli_qq_scan'][$sid] = [
        'qrsig' => $qrsig,
        'ptqrtoken' => $ptqrtoken,
        'created' => time(),
        'last_status' => 'waiting',
        'uin' => '',
    ];

    return [
        'ok' => true,
        'sid' => $sid,
        'qr_png' => base64_encode($img),
        'ptqrtoken' => $ptqrtoken,
    ];
}

function huli_qq_scan_poll(string $sid): array {
    $sess = $_SESSION['huli_qq_scan'][$sid] ?? null;
    if ($sess === null) {
        return ['status' => 'invalid_session'];
    }

    // Auto-expire sessions older than 5 min
    if (time() - (int)$sess['created'] > 300) {
        $sess['last_status'] = 'expired';
        $_SESSION['huli_qq_scan'][$sid] = $sess;
        return ['status' => 'expired'];
    }

    if ($sess['last_status'] === 'expired' || $sess['last_status'] === 'failed') {
        return ['status' => $sess['last_status'], 'uin' => $sess['uin']];
    }

    // If already succeeded but uin was not extracted yet, keep polling to retry
    if ($sess['last_status'] === 'success' && $sess['uin'] === '') {
        // fall through to poll again
    } else {
        $sess = $_SESSION['huli_qq_scan'][$sid];
    }

    $qrsig     = $sess['qrsig'];
    $ptqrtoken = $sess['ptqrtoken'];

    $poll_url = 'https://ssl.ptlogin2.qq.com/ptqrlogin'
        . '?u1=https://qzs.qq.com/qzone/v5/loginsucc.html?para=izone'
        . '&ptqrtoken=' . rawurlencode($ptqrtoken)
        . '&ptredirect=0&h=1&t=1&g=1&from_ui=1&ptlang=2052'
        . '&action=0-6-' . (int)(microtime(true) * 1000)
        . '&js_ver=24090913&js_type=1&login_sig=&pt_uistyle=40'
        . '&aid=549000912&daid=5';

    $cookie_header = 'qrsig=' . $qrsig;
    $headers = [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Referer: https://xui.ptlogin2.qq.com/',
        'Cookie: ' . $cookie_header,
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
    ];

    $ctx = stream_context_create(['http' => ['header' => implode("\n", $headers), 'timeout' => 10]]);
    $resp = @file_get_contents($poll_url, false, $ctx);
    $poll_headers = is_array($http_response_header ?? null) ? $http_response_header : [];

    if ($resp === false) {
        return ['status' => $sess['last_status']]; // keep last known state
    }

    // Parse ptuiCB('66') / ptuiCB('67') / ptuiCB('0') / ptuiCB('65')
    if (preg_match("/ptuiCB\('(\d+)'\)/", $resp, $m)) {
        $code = (int)$m[1];
        switch ($code) {
            case 0:
                // Success: the check_sig URL in the response contains uin as a query param
                // e.g. ptuiCB('0','0','https://ptlogin2.qzone.qq.com/check_sig?pttype=1&uin=123456789&...')
                $uin = '';
                // Primary: extract uin from the check_sig URL embedded in ptuiCB response
                if (preg_match('/check_sig[^\'"]*?\buin=(\d+)/', $resp, $um1)) {
                    $uin = $um1[1];
                }
                // Fallback: look for uin in response body directly
                if ($uin === '' && preg_match('/\buin=(\d+)/', $resp, $um2)) {
                    $uin = $um2[1];
                }
                // Fallback: look for uin in poll response headers
                if ($uin === '') {
                    foreach ($poll_headers as $h) {
                        if (stripos($h, 'Set-Cookie:') === 0 && preg_match('/\buin=(\d+)/', $h, $um3)) {
                            $uin = $um3[1];
                            break;
                        }
                    }
                }
                if ($uin !== '' && $sess['uin'] === '') {
                    $sess['uin'] = $uin;
                }
                if ($sess['uin'] !== '') {
                    huli_qq_mark_authorized($sess['uin']);
                }
                $sess['last_status'] = 'success';
                $_SESSION['huli_qq_scan'][$sid] = $sess;
                if ($sess['uin'] === '') {
                    return ['status' => 'success'];
                }
                return ['status' => 'success', 'uin' => $sess['uin']];

            case 66:
                if ($sess['last_status'] !== 'success') {
                    $sess['last_status'] = 'waiting';
                }
                break;
            case 67:
                if ($sess['last_status'] !== 'success') {
                    $sess['last_status'] = 'scanned';
                }
                break;
            case 65:
                $sess['last_status'] = 'expired';
                break;
        }
        $_SESSION['huli_qq_scan'][$sid] = $sess;
        return ['status' => $sess['last_status'], 'uin' => $sess['uin']];
    }

    // Unrecognized response — keep last status
    return ['status' => $sess['last_status'], 'uin' => $sess['uin']];
}
