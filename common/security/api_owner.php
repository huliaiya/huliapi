<?php
if (!function_exists('huli_api_owner_user_id')) {
    /**
     * 返回已购私有接口的归属用户 ID，非已购私有接口返回 null。
     *
     * 购买克隆由 common/ajax/buy_api.php 生成，endpoint 固定为 u{user_id}_xxx，
     * 且 admin_id 显式写为 0，两者同时满足才认定为用户已购接口。
     */
    function huli_api_owner_user_id(array $api) {
        if (($api['visibility'] ?? '') !== 'private') {
            return null;
        }
        if ((int)($api['admin_id'] ?? 0) !== 0) {
            return null;
        }
        $endpoint = (string)($api['endpoint'] ?? '');
        if (preg_match('/^u(\d+)_/', $endpoint, $matches)) {
            return (int)$matches[1];
        }
        return null;
    }
}
