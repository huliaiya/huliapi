<?php
if (!defined('HULI_CLIENT_IP_LIB')) { define('HULI_CLIENT_IP_LIB', 1); }

if (!function_exists('huli_cidr_match')) {
    function huli_cidr_match($ip, $cidr) {
        if (strpos($cidr, '/') === false) {
            return $ip === $cidr;
        }
        list($subnet, $bits) = explode('/', $cidr, 2);
        $bits = (int)$bits;
        $ip_bin = @inet_pton($ip);
        $subnet_bin = @inet_pton($subnet);
        if ($ip_bin === false || $subnet_bin === false || strlen($ip_bin) !== strlen($subnet_bin)) {
            return false;
        }
        $len = strlen($ip_bin) * 8;
        if ($bits < 0) { $bits = 0; }
        if ($bits > $len) { $bits = $len; }
        $bytes = intdiv($bits, 8);
        $rem = $bits % 8;
        if ($bytes > 0 && substr($ip_bin, 0, $bytes) !== substr($subnet_bin, 0, $bytes)) {
            return false;
        }
        if ($rem === 0) { return true; }
        $mask = chr((0xFF << (8 - $rem)) & 0xFF);
        return (substr($ip_bin, $bytes, 1) & $mask) === (substr($subnet_bin, $bytes, 1) & $mask);
    }
}

if (!function_exists('huli_is_trusted_proxy')) {
    function huli_is_trusted_proxy($ip, array $trusted) {
        if ($ip === '' || $ip === null) { return false; }
        foreach ($trusted as $cidr) {
            if ($cidr !== '' && huli_cidr_match($ip, $cidr)) { return true; }
        }
        return false;
    }
}

if (!function_exists('huli_cloudflare_ranges')) {
    function huli_cloudflare_ranges() {
        return [
            '173.245.48.0/20','103.21.244.0/22','103.22.200.0/22','103.31.4.0/22',
            '141.101.64.0/18','108.162.192.0/18','190.93.240.0/20','188.114.96.0/20',
            '197.234.240.0/22','198.41.128.0/17','162.158.0.0/15','104.16.0.0/13',
            '104.24.0.0/14','172.64.0.0/13','131.0.72.0/22',
            '2400:cb00::/32','2606:4700::/32','2803:f800::/32','2405:b500::/32',
            '2405:8100::/32','2a06:98c0::/29','2c0f:f248::/32',
        ];
    }
}

if (!function_exists('huli_configured_trusted_proxies')) {
    function huli_configured_trusted_proxies() {
        if (!defined('HULI_TRUSTED_PROXIES')) { return []; }
        $list = [];
        foreach (explode(',', (string)HULI_TRUSTED_PROXIES) as $item) {
            $item = trim($item);
            if ($item !== '') { $list[] = $item; }
        }
        return $list;
    }
}

if (!function_exists('huli_client_ip')) {
    function huli_client_ip() {
        $remote = $_SERVER['REMOTE_ADDR'] ?? '';
        $configured = huli_configured_trusted_proxies();
        $cloudflare = huli_cloudflare_ranges();

        $is_local_peer = filter_var(
            $remote,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false;

        $trust_forwarded = (!empty($configured) && huli_is_trusted_proxy($remote, $configured))
            || $is_local_peer
            || huli_is_trusted_proxy($remote, $cloudflare);

        if ($trust_forwarded) {
            if (huli_is_trusted_proxy($remote, $cloudflare) && !empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
                $cf = trim($_SERVER['HTTP_CF_CONNECTING_IP']);
                if (filter_var($cf, FILTER_VALIDATE_IP)) { return $cf; }
            }

            $chain = [];
            foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP'] as $header) {
                if (!empty($_SERVER[$header])) {
                    foreach (explode(',', $_SERVER[$header]) as $part) {
                        $chain[] = trim($part);
                    }
                    break;
                }
            }
            $chain[] = $remote;

            $trusted_all = array_merge($configured, $cloudflare);
            for ($i = count($chain) - 1; $i >= 0; $i--) {
                $ip = $chain[$i];
                if (!filter_var($ip, FILTER_VALIDATE_IP)) { continue; }
                if ($i === 0) { return $ip; }
                $is_trusted = huli_is_trusted_proxy($ip, $trusted_all);
                if (!$is_trusted && $is_local_peer) {
                    $is_trusted = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
                }
                if (!$is_trusted) { return $ip; }
            }
            return $remote;
        }

        return $remote !== '' ? $remote : '0.0.0.0';
    }
}
