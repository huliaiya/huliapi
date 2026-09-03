<?php

if (!defined('HULI_REDIS_CLIENT')) {
    define('HULI_REDIS_CLIENT', 1);
}

function huli_redis_config(array $settings) {
    $hostValue = trim((string)($settings['redis_url'] ?? $settings['redis_host'] ?? '127.0.0.1'));
    $config = [
        'scheme' => 'redis',
        'host' => '127.0.0.1',
        'port' => (int)($settings['redis_port'] ?? 6379),
        'username' => trim((string)($settings['redis_username'] ?? '')),
        'password' => (string)($settings['redis_password'] ?? ''),
        'database' => (int)($settings['redis_database'] ?? $settings['redis_db'] ?? 0),
        'timeout' => (float)($settings['redis_timeout'] ?? 0.5),
    ];

    if (preg_match('#^rediss?://#i', $hostValue)) {
        $parts = parse_url($hostValue);
        if ($parts === false || empty($parts['host'])) {
            throw new InvalidArgumentException('Redis 连接串格式无效');
        }

        $scheme = strtolower((string)($parts['scheme'] ?? 'redis'));
        if (!in_array($scheme, ['redis', 'rediss'], true)) {
            throw new InvalidArgumentException('Redis 连接串仅支持 redis:// 或 rediss://');
        }

        $config['scheme'] = $scheme;
        $config['host'] = (string)$parts['host'];
        $config['port'] = (int)($parts['port'] ?? ($scheme === 'rediss' ? 6380 : 6379));
        if (isset($parts['user'])) {
            $config['username'] = rawurldecode((string)$parts['user']);
        }
        if (isset($parts['pass'])) {
            $config['password'] = rawurldecode((string)$parts['pass']);
        }
        if (isset($parts['path']) && preg_match('#^/(\d+)$#', (string)$parts['path'], $matches)) {
            $config['database'] = (int)$matches[1];
        }
    } else {
        $config['host'] = $hostValue !== '' ? $hostValue : '127.0.0.1';
    }

    if ($config['port'] < 1 || $config['port'] > 65535) {
        throw new InvalidArgumentException('Redis 端口无效');
    }
    if ($config['database'] < 0) {
        throw new InvalidArgumentException('Redis 数据库编号无效');
    }
    if ($config['timeout'] <= 0) {
        $config['timeout'] = 0.5;
    }

    return $config;
}

function huli_redis_connect(array $settings) {
    $config = huli_redis_config($settings);
    if (!class_exists('Redis')) {
        throw new RuntimeException('当前环境缺少可用的 Redis 客户端');
    }

    $redis = new Redis();
    $host = $config['scheme'] === 'rediss' ? 'tls://' . $config['host'] : $config['host'];
    if (!$redis->connect($host, $config['port'], $config['timeout'])) {
        throw new RuntimeException('Redis 连接失败');
    }

    if ($config['password'] !== '') {
        $credentials = $config['username'] !== ''
            ? [$config['username'], $config['password']]
            : $config['password'];
        if (!$redis->auth($credentials)) {
            $redis->close();
            throw new RuntimeException('Redis 身份验证失败');
        }
    }

    if ($config['database'] > 0 && !$redis->select($config['database'])) {
        $redis->close();
        throw new RuntimeException('Redis 数据库选择失败');
    }

    return $redis;
}

function huli_redis_raw_encode(array $args) {
    $out = '*' . count($args) . "\r\n";
    foreach ($args as $arg) {
        $arg = (string)$arg;
        $out .= '$' . strlen($arg) . "\r\n" . $arg . "\r\n";
    }
    return $out;
}

function huli_redis_raw_read($fp) {
    if (!is_resource($fp)) throw new RuntimeException('Redis 连接已关闭');
    $line = fgets($fp);
    if ($line === false) {
        $meta = stream_get_meta_data($fp);
        if (!empty($meta['timed_out'])) throw new RuntimeException('Redis 读取响应超时');
        throw new RuntimeException('Redis 读取响应失败');
    }
    $type = $line[0];
    $rest = trim(substr($line, 1));
    if ($type === '-') throw new RuntimeException('Redis 命令错误: ' . $rest);
    if ($type === '+') return $rest;
    if ($type === ':') return (int)$rest;
    if ($type === '$') {
        $len = (int)$rest;
        if ($len < 0) return null;
        $buf = '';
        while (strlen($buf) < $len + 2) {
            $chunk = fread($fp, $len + 2 - strlen($buf));
            if ($chunk === false || $chunk === '') {
                $meta = stream_get_meta_data($fp);
                if (!empty($meta['timed_out'])) throw new RuntimeException('Redis 读取响应超时');
                throw new RuntimeException('Redis 读取响应失败');
            }
            $buf .= $chunk;
        }
        return substr($buf, 0, $len);
    }
    throw new RuntimeException('Redis 响应格式异常');
}

function huli_redis_raw_open(array $settings) {
    $config = huli_redis_config($settings);
    if ($config['scheme'] !== 'redis') {
        throw new RuntimeException('内置 Redis 连接不支持 rediss 加密地址，请改用普通地址或安装 phpredis 扩展');
    }
    $errno = 0;
    $errstr = '';
    $fp = @fsockopen($config['host'], $config['port'], $errno, $errstr, $config['timeout']);
    if ($fp === false) {
        $detail = $errstr !== '' ? $errstr : $config['host'] . ':' . $config['port'] . ' 无法连接';
        throw new RuntimeException('Redis 连接失败: ' . $detail);
    }
    $timeout = (float)$config['timeout'];
    $whole = (int)$timeout;
    $frac = (int)(($timeout - $whole) * 1000000);
    stream_set_timeout($fp, $whole, $frac);
    try {
        if ($config['password'] !== '') {
            $auth = $config['username'] !== ''
                ? ['AUTH', $config['username'], $config['password']]
                : ['AUTH', $config['password']];
            fwrite($fp, huli_redis_raw_encode($auth));
            huli_redis_raw_read($fp);
        }
        if ($config['database'] > 0) {
            fwrite($fp, huli_redis_raw_encode(['SELECT', (string)$config['database']]));
            huli_redis_raw_read($fp);
        }
    } catch (Throwable $e) {
        fclose($fp);
        throw $e;
    }
    return $fp;
}

function huli_redis_raw_ping(array $settings) {
    $fp = huli_redis_raw_open($settings);
    try {
        fwrite($fp, huli_redis_raw_encode(['PING']));
        $reply = huli_redis_raw_read($fp);
        return $reply === 'PONG';
    } finally {
        fclose($fp);
    }
}

function huli_redis_raw_incr(array $settings, $key, $window = 0) {
    $fp = huli_redis_raw_open($settings);
    try {
        fwrite($fp, huli_redis_raw_encode(['INCR', $key]));
        $count = (int)huli_redis_raw_read($fp);
        if ($count === 1 && $window > 0) {
            fwrite($fp, huli_redis_raw_encode(['EXPIRE', $key, (string)(int)$window]));
            huli_redis_raw_read($fp);
        }
        return $count;
    } finally {
        fclose($fp);
    }
}
