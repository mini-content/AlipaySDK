<?php
/**
 * 支付宝SDK
 * 该SDK仅支持PHP7.0及以上版本的环境下使用！
 */
class AlipaySDK
{
    /**
     * 应用编号
     * @var string
     */
    protected $appId;

    /**
     * 请求接口
     * @var string
     */
    protected $method;

    /**
     * 请求参数编码格式
     * @var string
     */
    protected $charset = 'UTF-8';

    /**
     * 签名加密方式 - 推荐启用RSA2
     * 支持RSA、RSA2；
     * @var string
     */
    protected $signType = 'RSA2';

    /**
     * 签名秘钥类型 - 推荐“Cert”类型
     * 支持Key（秘钥类型）、Cert（证书类型）
     * @var string
     */
    protected $signKeyType = 'key';

    // 私钥值
    protected $rsaPrivateKey;

    // 支付宝根证书SN
    protected $alipayRootCertSn='';

    // 应用证书SN
    protected $appCertSn='';

    public function setAlipayRootCertSn($alipayRootCertSn)
	{
		$this->alipayRootCertSn = $alipayRootCertSn;
	}

	public function setAppCertSn($appCertSn)
	{
		$this->appCertSn = $appCertSn;
    }
    
    /**
     * 构造方法
     * @param string $appid 应用编号
     * @param string $method 接口名称
     * @param string $signType 加密方式
     */
    public function __construct(string $appid,string $method,string $signKeyType = 'Cert')
    {
        $this->appId = $appid;
        $this->method = $method;
        $this->signKeyType = $signKeyType;
    }

    /**
     * 统一请求入口
     */
    public function toRequest(array $requestConfigs,array $commonConfigs = [],string $signType = 'RSA2')
    {
        $commonConfigs = $this->commonConfigs($commonConfigs);
        $commonConfigs['biz_content'] = json_encode($requestConfigs);
        $commonConfigs['sign'] = $this->generateSign($commonConfigs, $commonConfigs['sign_type']);
        $result = $this->curlPost('https://openapi.alipay.com/gateway.do?charset='.$this->charset,$commonConfigs);
        return json_decode($result,true);
    }

    /**
     * 公共请求参数生成
     */
    protected function commonConfigs(array $commonConfigs = [])
    {
        $data = [
            'app_id' => $this->appId,
            'method' => $this->method,
            'format' => 'JSON',
            'charset' => $this->charset,
            'sign_type' => $this->signType,
            'timestamp'=>date('Y-m-d H:i:s'),
            'version' => '1.0'
        ];
        if($this->signKeyType == 'Cert'){
            $data['alipay_root_cert_sn'] = $this->alipayRootCertSn;
        }
        if($commonConfigs && !empty($commonConfigs)){
            $data = array_merge($data,$commonConfigs);
        }
        return $data;
    }

    public function generateSign($params, $signType = "RSA") {
        return $this->sign($this->getSignContent($params), $signType);
    }

    protected function sign($data, $signType = "RSA") {
        $priKey = $this->rsaPrivateKey;
        $res = "-----BEGIN RSA PRIVATE KEY-----\n" .
            wordwrap($priKey, 64, "\n", true) .
            "\n-----END RSA PRIVATE KEY-----";

        ($res) or die('您使用的私钥格式错误，请检查RSA私钥配置');
        if ("RSA2" == $signType) {
            openssl_sign($data, $sign, $res, version_compare(PHP_VERSION,'7.0.0', '<') ? SHA256 : OPENSSL_ALGO_SHA256); //OPENSSL_ALGO_SHA256是php5.4.8以上版本才支持
        } else {
            openssl_sign($data, $sign, $res);
        }
        $sign = base64_encode($sign);
        return $sign;
    }

    /**
     * 校验$value是否非空
     *  if not set ,return true;
     *    if is null , return true;
     **/
    protected function checkEmpty($value) {
        if (!isset($value))
            return true;
        if ($value === null)
            return true;
        if (trim($value) === "")
            return true;

        return false;
    }

    public function getSignContent($params) {
        ksort($params);
        $stringToBeSigned = "";
        $i = 0;
        foreach ($params as $k => $v) {
            if (false === $this->checkEmpty($v) && "@" != substr($v, 0, 1)) {
                // 转换成目标字符集
                $v = $this->characet($v, $this->charset);
                if ($i == 0) {
                    $stringToBeSigned .= "$k" . "=" . "$v";
                } else {
                    $stringToBeSigned .= "&" . "$k" . "=" . "$v";
                }
                $i++;
            }
        }

        unset ($k, $v);
        return $stringToBeSigned;
    }

    /**
     * 转换字符集编码
     * @param $data
     * @param $targetCharset
     * @return string
     */
    function characet($data, $targetCharset) {
        if (!empty($data)) {
            $fileType = $this->charset;
            if (strcasecmp($fileType, $targetCharset) != 0) {
                $data = mb_convert_encoding($data, $targetCharset, $fileType);
                //$data = iconv($fileType, $targetCharset.'//IGNORE', $data);
            }
        }
        return $data;
    }

    public function curlPost($url = '', $postData = '', $options = array())
    {
        if (is_array($postData)) {
            $postData = http_build_query($postData);
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30); //设置cURL允许执行的最长秒数
        if (!empty($options)) {
            curl_setopt_array($ch, $options);
        }
        //https请求 不验证证书和host
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
    }
}