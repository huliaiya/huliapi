<?php

function huli_site_settings(PDO $pdo)
{
    static $cache = null;
    if ($cache === null) {
        $cache = $pdo->query("SELECT setting_key, setting_value FROM huli_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
    }
    return $cache;
}

function huli_site_setting(PDO $pdo, $key, $default = '')
{
    $settings = huli_site_settings($pdo);
    return isset($settings[$key]) && $settings[$key] !== '' ? $settings[$key] : $default;
}
