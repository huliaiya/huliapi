<?php
function huli_github_api($path, $method = 'GET', $body = null) {
    $url = 'https://api.github.com' . $path;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'huliapi-updater');
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/vnd.github+json']);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'body' => $response];
}

function huli_fetch_latest_release($repo) {
    $res = huli_github_api('/repos/' . $repo . '/releases/latest');
    if ($res['code'] === 200 && $res['body']) {
        $data = json_decode($res['body'], true);
        if (is_array($data) && !empty($data['tag_name'])) {
            return [
                'source' => 'release',
                'version' => ltrim($data['tag_name'], 'v'),
                'name' => $data['name'] ?? '',
                'body' => $data['body'] ?? '',
                'published_at' => $data['published_at'] ?? '',
                'zipball_url' => $data['zipball_url'] ?? '',
                'tarball_url' => $data['tarball_url'] ?? '',
            ];
        }
    }
    return null;
}

function huli_fetch_branch_commit($repo, $branch) {
    $res = huli_github_api('/repos/' . $repo . '/commits/' . urlencode($branch));
    if ($res['code'] === 200 && $res['body']) {
        $data = json_decode($res['body'], true);
        if (is_array($data) && !empty($data['sha'])) {
            $commit = $data['commit'] ?? [];
            return [
                'source' => 'branch',
                'version' => substr($data['sha'], 0, 7),
                'name' => $commit['message'] ?? '',
                'body' => '',
                'published_at' => $commit['author']['date'] ?? '',
                'zipball_url' => 'https://github.com/' . $repo . '/archive/refs/heads/' . $branch . '.zip',
                'tarball_url' => 'https://github.com/' . $repo . '/archive/refs/heads/' . $branch . '.tar.gz',
            ];
        }
    }
    return null;
}

function huli_detect_update_info() {
    $repo = defined('SENLIN_CLIENT_REPO') ? SENLIN_CLIENT_REPO : 'huliaiya/huliapi';
    $branch = defined('SENLIN_CLIENT_REPO_BRANCH') ? SENLIN_CLIENT_REPO_BRANCH : 'main';
    $update_branch = defined('SENLIN_CLIENT_UPDATE_BRANCH') ? SENLIN_CLIENT_UPDATE_BRANCH : $branch;
    $info = huli_fetch_latest_release($repo);
    if (!$info) {
        $info = huli_fetch_branch_commit($repo, $update_branch);
    }
    if (!$info) {
        $info = huli_fetch_branch_commit($repo, $branch);
    }
    if (!$info) {
        return null;
    }
    $info['download_url'] = 'https://github.com/' . $repo . '/archive/refs/heads/' . $update_branch . '.zip';
    if ($info['source'] === 'release' && !empty($info['zipball_url'])) {
        $info['download_url'] = $info['zipball_url'];
    }
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
