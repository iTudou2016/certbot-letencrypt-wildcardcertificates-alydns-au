<?php

date_default_timezone_set("GMT");

############ 请在腾讯云申请“API密钥”，替换下面两个常量
//去 https://console.cloud.tencent.com/cam/capi 页面申请 
define("txyaccessKeyId", "");
define("txyaccessSecrec", "");

######### 类测试

/*
  $obj = new TxyDns(txyaccessKeyId, txyaccessSecrec, "yudadan.com");
  //显示所有域名
  //$obj->DomainList();
  //添加域名 TXT 记录
  //$obj->RecordCreate("www3","TXT","s");
  //显示某个域名所有的 TXT 记录
  //$obj->RecordList("www3","TXT");
 */

###### 代码运行
// php txydns.php  "simplehttps.com" "txtname" "txtvalue"  
//$argv[1] = "simplehttps.com";
//$argv[2] = "www3";
//$argv[3] = "ssssss";

$obj = new TxyDns(txyaccessKeyId, txyaccessSecrec, $argv[1]);
$data = $obj->RecordList($argv[2], "TXT");
if ($data["code"] != "0") {
    $obj->error($data["code"], $data["message"]);
}

$records = $data["data"]["records"];
foreach ($records as $k => $v) {
    // 如果存在记录，则直接修改。
    if ($v["name"] == $argv[2]) {
        $data = $obj->RecordModify($argv[2], "TXT", $argv[3], $v["id"]);
        if ($data["code"] != "0") {
            $obj->error($data["code"], $data["message"]);
        }
        //$obj->RecordDelete($v["id"]);
        exit;
    }
}
//如果不存在，就增加 TXT 记录
$data = $obj->RecordCreate($argv[2], "TXT", $argv[3]);
if ($data["code"] != "0") {
    //失败，则记录日志
    $obj->error($data["code"], $data["message"]);
}

####### 基于腾讯云 DNS API 实现的 PHP 类，参考 https://cloud.tencent.com/document/product/302/4032

class TxyDns {

    private $accessKeyId = null;
    private $accessSecrec = null;
    private $DomainName = null;
    private $Host = "cns.api.qcloud.com";
    private $Path = "/v2/index.php";

    public function __construct($accessKeyId, $accessSecrec, $domain = "") {
        $this->accessKeyId = $accessKeyId;
        $this->accessSecrec = $accessSecrec;
        $this->DomainName = $domain;
    }

    public function error($code, $str) {
        echo "操作错误:" . $code . ":" . $str;
        exit;
    }

    public function RecordDelete($recordId) {
        $param["domain"] = $this->DomainName;
        $param["recordId"] = $recordId;

        $data = $this->send("RecordDelete", "GET", $param);
        return ($this->out($data));
    }

    public function RecordList($subDomain, $recordType = "") {

        if ($recordType != "")
            $param["recordType"] = $recordType;
        $param["subDomain"] = $subDomain;
        $param["domain"] = $this->DomainName;

        $data = $this->send("RecordList", "GET", $param);
        return ($this->out($data));
    }

    public function RecordModify($subDomain, $recordType = "TXT", $value, $recordId) {
        $param["recordType"] = $recordType;
        $param["subDomain"] = $subDomain;
        $param["recordId"] = $recordId;
        $param["domain"] = $this->DomainName;
        $param["recordLine"] = "默认";
        $param["value"] = $value;

        $data = $this->send("RecordModify", "GET", $param);
        return ($this->out($data));
    }

    public function RecordCreate($subDomain, $recordType = "TXT", $value) {
        $param["recordType"] = $recordType;
        $param["subDomain"] = $subDomain;
        $param["domain"] = $this->DomainName;
        $param["recordLine"] = "默认";
        $param["value"] = $value;

        $data = $this->send("RecordCreate", "GET", $param);
        return ($this->out($data));
    }

    public function DomainList() {

        $data = $this->send("DomainList", "GET", array());
        return ($this->out($data));
    }

    private function send($action, $reqMethod, $requestParams) {

        $params = $this->formatRequestData($action, $requestParams, $reqMethod);

        $uri = http_build_query($params);
        $url = "https://" . $this->Host . "" . $this->Path . "?" . $uri;
        return $this->curl($url);
    }

    private function formatRequestData($action, $request, $reqMethod) {
        $param = $request;
        $param["Action"] = ucfirst($action);
//$param["RequestClient"] = $this->sdkVersion;
        $param["Nonce"] = rand();
        $param["Timestamp"] = time();
//$param["Version"] = $this->apiVersion;

        $param["SecretId"] = $this->accessKeyId;

        $signStr = $this->formatSignString($this->Host, $this->Path, $param, $reqMethod);
        $param["Signature"] = $this->sign($signStr);
        return $param;
    }

//签名
    private function formatSignString($host, $path, $param, $requestMethod) {
        $tmpParam = [];
        ksort($param);
        foreach ($param as $key => $value) {
            array_push($tmpParam, str_replace("_", ".", $key) . "=" . $value);
        }
        $strParam = join("&", $tmpParam);
        $signStr = strtoupper($requestMethod) . $host . $path . "?" . $strParam;
        return $signStr;
    }

    private function sign($signStr) {

        $signature = base64_encode(hash_hmac("sha1", $signStr, $this->accessSecrec, true));
        return $signature;
    }

    private function curl($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    private function out($msg) {
        return json_decode($msg, true);
    }

}
