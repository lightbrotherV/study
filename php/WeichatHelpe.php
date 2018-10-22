<?php

namespace App\modules;

class WeichatHelper
{
    //ase秘钥
    public $key;
    protected $appid;
    protected $token;

    //传递参数格式可重写
    public function __construct($config)
    {
        //等号是需要自己拼接上去，不在微信后台登记的秘钥中
        $this->key = base64_decode($config['EncodingAESKey'].'=');
        $this->appid = $config['AppID'];
        $this->token = $config['Token'];
    }
    /**
     * 对明文进行加密
     * @param string $text 需要加密的明文
     * @return string 加密后的密文
     */
    public function encrypt($text)
    {
        try {
            $appid = $this->appid;
            //获得16位随机字符串，填充到明文之前
            $random = $this->getRandomStr();
            $text = $random . pack("N", strlen($text)) . $text . $appid;
            $iv = substr($this->key, 0, 16);
            //使用自定义的填充方式对明文进行补位填充
            $pkc_encoder = function ($text) {
                $block_size = 32;
                $text_length = strlen($text);
                //计算需要填充的位数
                $amount_to_pad = 32 - ($text_length % 32);
                if ($amount_to_pad == 0) {
                    $amount_to_pad = 32;
                }
                //获得补位所用的字符
                $pad_chr = chr($amount_to_pad);
                $tmp = "";
                for ($index = 0; $index < $amount_to_pad; $index++) {
                    $tmp .= $pad_chr;
                }
                return $text . $tmp;
            };
            $text = $pkc_encoder($text);
            $encrypted = openssl_encrypt($text, 'AES-256-CBC', substr($this->key, 0, 32), OPENSSL_ZERO_PADDING, $iv);
            return $encrypted;
        } catch (Exception $e) {
            print $e->getMessage();
            exit;
        }
    }

    /**
     * 对密文进行解密
     * @param string $encrypted 需要解密的密文
     * @return string 解密得到的明文
     */
    public function decrypt($encrypted)
    {
        try {
            $iv = substr($this->key, 0, 16);
            $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', substr($this->key, 0, 32), OPENSSL_ZERO_PADDING, $iv);
        } catch (Exception $e) {
            print $e;
            exit;
        }
        try {
            //去除补位字符
            $pkc_encoder = function ($text) {

                $pad = ord(substr($text, -1));
                if ($pad < 1 || $pad > 32) {
                    $pad = 0;
                }
                return substr($text, 0, (strlen($text) - $pad));
            };
            $result = $pkc_encoder($decrypted);
            //去除16位随机字符串,网络字节序和AppId
            if (strlen($result) < 16) {
                return "";
            }
            $content = substr($result, 16, strlen($result));
            $len_list = unpack("N", substr($content, 0, 4));
            $xml_len = $len_list[1];
            $xml_content = substr($content, 4, $xml_len);
        } catch (Exception $e) {
            print $e;
            exit;
        }
        return $xml_content;
    }
    /**
     * 随机生成16位字符串
     * @return string 生成的字符串
     */
    protected function getRandomStr()
    {

        $str = "";
        $str_pol = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz";
        $max = strlen($str_pol) - 1;
        for ($i = 0; $i < 16; $i++) {
            $str .= $str_pol[mt_rand(0, $max)];
        }
        return $str;
    }

    public function httpCurl($url, $paramArray = array(), $method = 'POST')
    {
        $ch = curl_init();
        if ($method == 'POST') {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $paramArray);
        } else if (!empty($paramArray)) {
            $url .= '?' . http_build_query($paramArray);
        }
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        if (false !== strpos($url, "https")) {
            // 证书
            // curl_setopt($ch,CURLOPT_CAINFO,"ca.crt");
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }
        $resultStr = curl_exec($ch);
        curl_close($ch);
        return $resultStr;
    }

    //生成数字签名
    public function signature($encrypt_msg)
    {
        $timestamp = time();
        $nonce = rand(1, 10000);
        $token = $this->token;
        $array = array($encrypt_msg, $token, $timestamp, $nonce);
        sort($array, SORT_STRING);
        $str = implode($array);
        return [$timestamp, $nonce, sha1($str)];
    }

    //xml解析
    public function xmlDecode(string $xmlString)
    {
        $xmlclass = get_object_vars(simplexml_load_string($xmlString));
        foreach ($xmlclass as $key => $val) {
            $xmlclass[$key] = (string) $val;
        }
        return $xmlclass;
    }

    //xml生成
    public function xmlEncode(array $xmlArray)
    {
        $xmlString = '<xml>';
        foreach ($xmlArray as $key => $val) {
            $xmlString .= "<{$key}><![CDATA[{$val}]]></{$key}>";
        }
        return $xmlString.'</xml>';
    }

    //将微信传送的数据处理成数组
    public function getMsg(string $postMsg)
    {
        //解析微信传的XML
        $sercetXmlArray = $this->xmlDecode($postMsg);
        //对密文进行解码
        $xmlMsg = $this->decrypt($sercetXmlArray['Encrypt']);
        //对明文进行xml解析
        return $this->xmlDecode($xmlMsg);
    }

    //将明文数组转成密文XML
    public function setSecret(array $msgArray)
    {
        //数组转换成XML字符串
        $msgXmlString = $this->xmlEncode($msgArray);
        //xml加密成密文
        $secretMsg = $this->encrypt($msgXmlString);
        //将密文处理成微信加密模式xml
        list($timestamp, $nonce, $signation) = $this->signature($secretMsg);
        $secretXmlArray = [
            'Encrypt' => $secretMsg,
            'MsgSignature' => $signation,
            'TimeStamp' => $timestamp,
            'Nonce' => $nonce,
        ];
        return $this->xmlEncode($secretXmlArray);
    }
}
