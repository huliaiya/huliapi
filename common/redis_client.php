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
