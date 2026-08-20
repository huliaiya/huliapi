<?php
if (!defined('HULI_MCP_SERVER')) { define('HULI_MCP_SERVER', 1); }

require_once __DIR__ . '/mcp_lib.php';

define('HULI_MCP_VERSION', '1.0.0');
define('HULI_MCP_PROTOCOL_VERSION', '2024-11-05');

function huli_mcp_error($code, $message, $id = null) {
    return ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message]];
}

function huli_mcp_result($result, $id) {
    return ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result];
}

function huli_mcp_get_token() {
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if ($auth === '' && function_exists('getallheaders')) {
        $headers = getallheaders();
        if (isset($headers['Authorization'])) {
            $auth = $headers['Authorization'];
        } elseif (isset($headers['authorization'])) {
            $auth = $headers['authorization'];
        }
    }
    if (preg_match('/^Bearer\s+(.+)$/i', trim($auth), $m)) {
        return trim($m[1]);
    }
    if (isset($_GET['token']) && is_string($_GET['token'])) {
        return trim($_GET['token']);
    }
    return '';
}

function huli_mcp_handle_message($msg, $ctx) {
    $startMs = (int)(microtime(true) * 1000);
    if (!is_array($msg) || !isset($msg['jsonrpc']) || $msg['jsonrpc'] !== '2.0') {
        huli_mcp_log($ctx, 'invalid', null, 'invalid', 'Invalid Request', (int)(microtime(true) * 1000) - $startMs);
        return huli_mcp_error(-32600, 'Invalid Request');
    }
    $method = isset($msg['method']) ? (string)$msg['method'] : '';
    $id = array_key_exists('id', $msg) ? $msg['id'] : null;
    $params = isset($msg['params']) && is_array($msg['params']) ? $msg['params'] : [];
    $isNotification = !array_key_exists('id', $msg);
    $toolName = ($method === 'tools/call' && isset($params['name'])) ? (string)$params['name'] : null;

    switch ($method) {
        case 'initialize':
            $capabilities = ['tools' => ['listChanged' => true]];
            $result = [
                'protocolVersion' => HULI_MCP_PROTOCOL_VERSION,
                'capabilities' => $capabilities,
                'serverInfo' => ['name' => 'huliapi-mcp', 'version' => HULI_MCP_VERSION],
            ];
            huli_mcp_log($ctx, $method, null, 'success', '', (int)(microtime(true) * 1000) - $startMs);
            return huli_mcp_result($result, $id);

        case 'notifications/initialized':
            huli_mcp_log($ctx, $method, null, 'success', '', (int)(microtime(true) * 1000) - $startMs);
            if ($isNotification) {
                return null;
            }
            return huli_mcp_result([], $id);

        case 'ping':
            huli_mcp_log($ctx, $method, null, 'success', '', (int)(microtime(true) * 1000) - $startMs);
            return huli_mcp_result([], $id);

        case 'tools/list':
            $tools = huli_mcp_list_tools();
            $result = ['tools' => $tools];
            if (isset($params['cursor'])) {
                $result['nextCursor'] = null;
            }
            huli_mcp_log($ctx, $method, null, 'success', '', (int)(microtime(true) * 1000) - $startMs);
            return huli_mcp_result($result, $id);

        case 'tools/call':
            $name = isset($params['name']) ? (string)$params['name'] : '';
            $args = isset($params['arguments']) && is_array($params['arguments']) ? $params['arguments'] : [];
            $tool = $GLOBALS['HULI_MCP_TOOLS'][$name] ?? null;
            if (!$tool) {
                huli_mcp_log($ctx, $method, $name, 'error', 'Unknown tool', (int)(microtime(true) * 1000) - $startMs);
                return huli_mcp_error(-32602, 'Unknown tool: ' . $name, $id);
            }
            try {
                $result = call_user_func($tool['callable'], $ctx, $args);
                huli_mcp_log($ctx, $method, $name, 'success', '', (int)(microtime(true) * 1000) - $startMs);
                return huli_mcp_result([
                    'content' => [['type' => 'text', 'text' => is_string($result) ? $result : huli_mcp_json($result)]],
                    'isError' => false,
                ], $id);
            } catch (Throwable $e) {
                $isDbError = $e instanceof PDOException;
                $clientMsg = $isDbError ? '工具执行失败: 内部数据库错误' : ('工具执行失败: ' . $e->getMessage());
                huli_mcp_log($ctx, $method, $name, 'error', $e->getMessage(), (int)(microtime(true) * 1000) - $startMs);
                return huli_mcp_result([
                    'content' => [['type' => 'text', 'text' => $clientMsg]],
                    'isError' => true,
                ], $id);
            }

        case 'resources/list':
            huli_mcp_log($ctx, $method, null, 'success', '', (int)(microtime(true) * 1000) - $startMs);
            return huli_mcp_result(['resources' => []], $id);

        case 'resources/read':
            huli_mcp_log($ctx, $method, null, 'error', 'No resources available', (int)(microtime(true) * 1000) - $startMs);
            return huli_mcp_error(-32602, 'No resources available', $id);

        case 'prompts/list':
            huli_mcp_log($ctx, $method, null, 'success', '', (int)(microtime(true) * 1000) - $startMs);
            return huli_mcp_result(['prompts' => []], $id);

        default:
            huli_mcp_log($ctx, $method, null, 'error', 'Method not found', (int)(microtime(true) * 1000) - $startMs);
            return huli_mcp_error(-32601, 'Method not found: ' . $method, $id);
    }
}

function huli_mcp_emit_sse($data) {
    echo "event: message\n";
    echo "data: " . str_replace("\n", "\ndata: ", huli_mcp_json($data)) . "\n\n";
    if (ob_get_level() > 0) {
        ob_flush();
    }
    flush();
}

function huli_mcp_send_response($payload, $useSse) {
    if ($payload === null) {
        http_response_code(202);
        if ($useSse) {
            huli_mcp_emit_sse(null);
        }
        return;
    }
    if ($useSse) {
        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        huli_mcp_emit_sse($payload);
    } else {
        header('Content-Type: application/json; charset=utf-8');
        echo huli_mcp_json($payload);
    }
}

function huli_mcp_handle_sse_endpoint($ctx) {
    huli_mcp_log($ctx, 'sse/open', null, 'success', '', 0);
    header('Content-Type: text/event-stream; charset=utf-8');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');
    header('X-Accel-Buffering: no');
    $sessionId = bin2hex(random_bytes(16));
    echo "event: endpoint\ndata: " . huli_mcp_json([
        'uri' => 'mcp.php?transport=message&sessionId=' . $sessionId,
        'sessionId' => $sessionId,
    ]) . "\n\n";
    if (ob_get_level() > 0) {
        ob_flush();
    }
    flush();
    while (true) {
        if (connection_aborted()) {
            break;
        }
        echo ": keep-alive\n\n";
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
        usleep(15000000);
    }
}

function huli_mcp_handle_request() {
    @error_reporting(0);
    @ini_set('display_errors', 'Off');
    if (ob_get_level() === 0) {
        ob_start();
    } else {
        while (ob_get_level() > 0 && ob_get_length() === 0) {
            ob_end_clean();
        }
        ob_start();
    }

    require_once dirname(__DIR__, 2) . '/config.php';
    huli_mcp_ensure_schema();

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $transport = isset($_GET['transport']) ? $_GET['transport'] : '';

    $token = huli_mcp_get_token();
    $ctx = huli_mcp_validate_token($token);
    if (!$ctx) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(401);
        echo huli_mcp_json(huli_mcp_error(-32001, 'Unauthorized: 无效或缺失的 MCP Token', null));
        try {
            $pdo = huli_mcp_pdo();
            $stmt = $pdo->prepare("INSERT INTO huli_mcp_logs (role, user_id, username, method, tool_name, ip_address, status, error_msg, latency_ms) VALUES ('user', 0, '', 'auth', NULL, ?, 'error', 'Unauthorized', 0)");
            $stmt->execute([(string)($_SERVER['REMOTE_ADDR'] ?? '')]);
        } catch (Throwable $e) {}
        exit;
    }
    if (($ctx['role'] === 'user' && $ctx['status'] !== 'active')) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(403);
        echo huli_mcp_json(huli_mcp_error(-32003, '账号状态不允许使用 MCP 服务', null));
        exit;
    }

    if ($ctx['role'] === 'admin') {
        require_once __DIR__ . '/mcp_tools_admin.php';
    } else {
        require_once __DIR__ . '/mcp_tools_user.php';
    }

    if ($method === 'GET' && $transport === 'sse') {
        huli_mcp_handle_sse_endpoint($ctx);
        exit;
    }

    if ($method === 'POST' && $transport === 'message') {
        $raw = file_get_contents('php://input');
        $body = $raw === '' ? [] : json_decode($raw, true);
        if (!is_array($body)) {
            header('Content-Type: application/json; charset=utf-8');
            echo huli_mcp_json(huli_mcp_error(-32700, 'Parse error'));
            exit;
        }
        $result = huli_mcp_handle_message($body, $ctx);
        if ($result !== null) {
            header('Content-Type: application/json; charset=utf-8');
            echo huli_mcp_json($result);
        } else {
            http_response_code(202);
        }
        exit;
    }

    if ($method !== 'POST') {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(405);
        echo huli_mcp_json(huli_mcp_error(-32600, 'Method not allowed'));
        exit;
    }

    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $useSse = (strpos($accept, 'text/event-stream') !== false);

    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true);
    if (!is_array($body)) {
        huli_mcp_send_response(huli_mcp_error(-32700, 'Parse error'), $useSse);
        exit;
    }

    $isBatch = array_keys($body) === range(0, count($body) - 1);

    if ($isBatch) {
        $responses = [];
        foreach ($body as $msg) {
            $r = huli_mcp_handle_message($msg, $ctx);
            if ($r !== null) {
                $responses[] = $r;
            }
        }
        huli_mcp_send_response($responses, $useSse);
    } else {
        $r = huli_mcp_handle_message($body, $ctx);
        huli_mcp_send_response($r, $useSse);
    }
}
