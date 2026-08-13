<?php
if (!defined('HULI_AFDIAN_CLIENT_LOADED')) {
    define('HULI_AFDIAN_CLIENT_LOADED', true);

    class AfdianClient
    {
        const API_BASE = 'https://ifdian.net/api/open';
        const ORDER_ENDPOINT = '/query-order';

        private $userId;
        private $token;
        private $timeout;

        public function __construct($userId, $token, $timeout = 10)
        {
            $this->userId = (string)$userId;
            $this->token = (string)$token;
            $this->timeout = max(3, intval($timeout));
        }

        private function buildSign(array $payload)
        {
            ksort($payload);
            $kvString = '';
            foreach ($payload as $key => $value) {
                $kvString .= $key . $value;
            }
            return md5($this->token . $kvString);
        }

        private function request($endpoint, array $params)
        {
            $paramsJson = json_encode($params);
            if ($paramsJson === false) {
                throw new Exception('爱发电请求参数序列化失败');
            }
            $ts = time();
            $payload = [
                'user_id' => $this->userId,
                'params' => $paramsJson,
                'ts' => $ts,
            ];
            $payload['sign'] = $this->buildSign($payload);

            $ch = curl_init(self::API_BASE . $endpoint);
            $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($body),
                ],
                CURLOPT_TIMEOUT => $this->timeout,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_USERAGENT => 'huliapi/afdian-client',
            ]);
            $response = curl_exec($ch);
            $curlError = curl_error($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($curlError !== '') {
                throw new Exception('爱发电 API 请求失败: ' . $curlError);
            }
            if ($httpCode < 200 || $httpCode >= 300) {
                throw new Exception('爱发电 API 返回异常状态码: ' . $httpCode);
            }
            $data = json_decode($response, true);
            if (!is_array($data)) {
                throw new Exception('爱发电 API 响应解析失败');
            }
            if ((int)($data['ec'] ?? 0) !== 200) {
                throw new Exception('爱发电 API 错误: ' . ($data['em'] ?? 'unknown error'));
            }
            return $data['data'] ?? [];
        }

        public function queryOrderByOutTradeNo($outTradeNo)
        {
            $data = $this->request(self::ORDER_ENDPOINT, ['out_trade_no' => $outTradeNo]);
            $list = $data['list'] ?? [];
            foreach ($list as $order) {
                if (isset($order['out_trade_no']) && $order['out_trade_no'] === $outTradeNo) {
                    return $order;
                }
            }
            return null;
        }

        public function queryOrdersByPage($page = 1)
        {
            $data = $this->request(self::ORDER_ENDPOINT, ['page' => intval($page)]);
            return [
                'list' => $data['list'] ?? [],
                'total_count' => (int)($data['total_count'] ?? 0),
                'total_page' => (int)($data['total_page'] ?? 1),
            ];
        }

        public function findOrderByRemark($remark, $maxPages = 2)
        {
            $remark = trim((string)$remark);
            if ($remark === '') {
                return null;
            }
            $maxPages = max(1, min(10, intval($maxPages)));
            for ($page = 1; $page <= $maxPages; $page++) {
                $result = $this->queryOrdersByPage($page);
                foreach ($result['list'] as $order) {
                    if (trim((string)($order['remark'] ?? '')) === $remark) {
                        return $order;
                    }
                }
                if ($page >= $result['total_page']) {
                    break;
                }
            }
            return null;
        }
    }
}
