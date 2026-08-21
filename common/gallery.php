<?php
if (!defined('HULI_GALLERY')) { define('HULI_GALLERY', 1); }

function huli_gallery_dir() {
    return dirname(__DIR__) . '/assets/images/gallery/';
}

function huli_gallery_url_path() {
    return '/assets/images/gallery/';
}

function huli_list_gallery_images() {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $dir = huli_gallery_dir();
    if (!is_dir($dir)) {
        return $cache = [];
    }
    $allow = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'avif'];
    $files = @scandir($dir);
    if (!is_array($files)) {
        return $cache = [];
    }
    $out = [];
    foreach ($files as $f) {
        if ($f === '.' || $f === '..') continue;
        $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
        if (!in_array($ext, $allow, true)) continue;
        $full = $dir . $f;
        if (!is_file($full)) continue;
        $out[] = $f;
    }
    return $cache = $out;
}

function huli_random_gallery_image() {
    $files = huli_list_gallery_images();
    if (empty($files)) {
        return '';
    }
    $pick = $files[array_rand($files)];
    return huli_gallery_url_path() . $pick;
}

function huli_session_gallery_image() {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }
    if (!empty($_SESSION['huli_gallery_bg']) && is_string($_SESSION['huli_gallery_bg'])) {
        return huli_gallery_url_path() . $_SESSION['huli_gallery_bg'];
    }
    $files = huli_list_gallery_images();
    if (empty($files)) {
        return '';
    }
    $pick = $files[array_rand($files)];
    $_SESSION['huli_gallery_bg'] = $pick;
    return huli_gallery_url_path() . $pick;
}
