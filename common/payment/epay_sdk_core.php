<?php
class EpaySubmit {
    protected $epay_config;
    function __construct($epay_config){
        $this->epay_config = $epay_config;
    }
    function buildRequestMysign($para_sort) {
        $prestr = "";
        foreach ($para_sort as $key => $value) {
            if ($key != "sign" && $key != "sign_type" && $value != "") {
                $prestr .= $key . "=" . $value . "&";
            }
        }
        $prestr = substr($prestr, 0, -1);
        return md5($prestr . $this->epay_config['key']);
    }
    function buildRequestPara($para_temp) {
        $para_filter = [];
        foreach ($para_temp as $key => $value) {
            if ($key == "sign" || $key == "sign_type" || $value == "") {
                continue;
            }
            $para_filter[$key] = $value;
        }
        ksort($para_filter);
        reset($para_filter);
        $mysign = $this->buildRequestMysign($para_filter);
        $para_filter['sign'] = $mysign;
        $para_filter['sign_type'] = 'MD5';
        return $para_filter;
    }
    function buildRequestForm($para_temp, $method, $button_name) {
        $para = $this->buildRequestPara($para_temp);
        $sHtml = "<form id='epaysubmit' name='epaysubmit' action='".$this->epay_config['apiurl']."' method='".$method."'>";
        foreach ($para as $key => $value) {
            $sHtml .= "<input type='hidden' name='".$key."' value='".$value."'/>";
        }
        $sHtml = $sHtml."<input type='submit' value='".$button_name."' style='display:none;'></form>";
        $sHtml = $sHtml."<script>document.forms['epaysubmit'].submit();</script>";
        return $sHtml;
    }
}
class EpayNotify {
    protected $epay_config;
    function __construct($epay_config){
        $this->epay_config = $epay_config;
    }
    function verifyNotify(){
        if(empty($_GET)) {
            return false;
        } else {
            $isSign = $this->getSignVeryfy($_GET, $_GET["sign"]);
            return $isSign;
        }
    }
    function getSignVeryfy($para_temp, $sign) {
        $para_filter = [];
        foreach ($para_temp as $key => $value) {
            if($key == "sign" || $key == "sign_type" || $value == ""){
                continue;
            }
            $para_filter[$key] = $value;
        }
        ksort($para_filter);
        reset($para_filter);
        $prestr = "";
        foreach ($para_filter as $key => $value) {
            $prestr .= $key."=".$value."&";
        }
        $prestr = substr($prestr, 0, -1);
        $mysign = md5($prestr . $this->epay_config['key']);
        if ($mysign == $sign) {
            return true;
        } else {
            return false;
        }
    }
}
function get_epay_config($pdo) {
    $stmt_settings = $pdo->query("SELECT setting_key, setting_value FROM sl_settings WHERE setting_key IN ('epay_pid', 'epay_key', 'epay_url')");
    $db_settings = $stmt_settings->fetchAll(PDO::FETCH_KEY_PAIR);
    if (empty($db_settings['epay_pid']) || empty($db_settings['epay_key']) || empty($db_settings['epay_url'])) {
        throw new Exception("支付网关未在后台配置完整。");
    }
    return [
        'pid' => trim($db_settings['epay_pid']),
        'key' => trim($db_settings['epay_key']),
        'apiurl' => trim($db_settings['epay_url'])
    ];
}
?>