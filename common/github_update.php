<?php
function huli_http_get($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'huliapi-updater');
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code !== 200 || $body === false || $body === '') { return null; }
    return $body;
}

function huli_github_api($path) {
    $url = 'https://api.github.com' . $path;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'huliapi-updater');
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/vnd.github+json']);
    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code !== 200 || $response === false) { return null; }
    $data = json_decode($response, true);
    if (!is_array($data)) { return null; }
    return $data;
}

function huli_fetch_latest_release_api($repo) {
    $data = huli_github_api('/repos/' . $repo . '/releases/latest');
    if (!$data || empty($data['tag_name'])) { return null; }
    return [
        'source' => 'release',
        'version' => ltrim($data['tag_name'], 'v'),
        'name' => $data['name'] ?? '',
        'body' => $data['body'] ?? '',
        'published_at' => $data['published_at'] ?? '',
        'repo' => $repo,
    ];
}

function huli_fetch_latest_release_redirect($repo) {
    $ch = curl_init('https://github.com/' . $repo . '/releases/latest');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'huliapi-updater');
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $redirect = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
    curl_close($ch);
    if ($code === 302 && $redirect && preg_match('#/releases/tag/([^/]+)$#', $redirect, $m)) {
        return [
            'source' => 'release',
            'version' => ltrim(urldecode($m[1]), 'v'),
            'name' => '',
            'body' => '',
            'published_at' => '',
            'repo' => $repo,
        ];
    }
    return null;
}

function huli_fetch_branch_commit_api($repo, $branch) {
    $data = huli_github_api('/repos/' . $repo . '/commits/' . rawurlencode($branch));
    if (!$data || empty($data['sha'])) { return null; }
    $commit = $data['commit'] ?? [];
    return [
        'source' => 'branch',
        'version' => substr($data['sha'], 0, 7),
        'name' => $commit['message'] ?? '',
        'body' => '',
        'published_at' => $commit['author']['date'] ?? '',
        'repo' => $repo,
    ];
}

function huli_fetch_branch_commit_atom($repo, $branch) {
    $body = huli_http_get('https://github.com/' . $repo . '/commits/' . rawurlencode($branch) . '.atom');
    if (!$body) { return null; }
    if (preg_match('/<entry>(.*?)<\/entry>/is', $body, $block)) {
        $block = $block[1];
        if (preg_match('#Commit/([0-9a-f]{7,40})#i', $block, $m)) { $sha = $m[1]; }
        if (preg_match('/<updated>\s*([^<\s]+)\s*<\/updated>/i', $block, $m)) { $updated = $m[1]; }
        if (preg_match('/<title>\s*(.*?)\s*<\/title>/is', $block, $m)) { $title = trim(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8')); }
    }
    if (empty($sha)) { return null; }
    return [
        'source' => 'branch',
        'version' => substr($sha, 0, 7),
        'name' => $title ?? '',
        'body' => '',
        'published_at' => $updated ?? '',
        'repo' => $repo,
    ];
}

function huli_detect_update_info() {
    $repo = defined('SENLIN_CLIENT_REPO') ? SENLIN_CLIENT_REPO : 'huliaiya/huliapi';
    $branch = defined('SENLIN_CLIENT_REPO_BRANCH') ? SENLIN_CLIENT_REPO_BRANCH : 'main';
    $update_branch = defined('SENLIN_CLIENT_UPDATE_BRANCH') ? SENLIN_CLIENT_UPDATE_BRANCH : $branch;

    $info = huli_fetch_latest_release_api($repo);
    if (!$info) { $info = huli_fetch_latest_release_redirect($repo); }
    if (!$info) { $info = huli_fetch_branch_commit_api($repo, $update_branch); }
    if (!$info) { $info = huli_fetch_branch_commit_atom($repo, $update_branch); }
    if (!$info) { $info = huli_fetch_branch_commit_api($repo, $branch); }
    if (!$info) { $info = huli_fetch_branch_commit_atom($repo, $branch); }
    if (!$info) { return null; }

    $info['update_branch'] = $update_branch;
    $info['download_url'] = 'https://github.com/' . $repo . '/archive/refs/heads/' . $update_branch . '.zip';
    $info['published_at_human'] = !empty($info['published_at'])
        ? date('Y-m-d H:i:s', strtotime($info['published_at']))
        : '';

    if (defined('SENLIN_CLIENT_VERSION')) {
        $info['update_available'] = $info['source'] === 'release'
            ? version_compare(SENLIN_CLIENT_VERSION, $info['version'], '<')
            : ($info['version'] !== SENLIN_CLIENT_VERSION);
    } else {
        $info['update_available'] = true;
    }
    return $info;
}
