<?php

// namespace App\modules;

class WeichatHelpe
{
    //ase秘钥
    public $key;

    public function __construct()
    {
        //等号是需要自己拼接上去，不在微信后台登记的秘钥中
        $this->key = base64_decode('=');
    }
    /**
     * 对明文进行加密
     * @param string $text 需要加密的明文
     * @return string 加密后的密文
     */
    public function encrypt($text, $appid)
    {
        try {
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
    public function signature($token, $timestamp, $nonce, $encrypt_msg)
    {
        $array = array($encrypt_msg, $token, $timestamp, $nonce);
        sort($array, SORT_STRING);
        $str = implode($array);
        return sha1($str);
    }
}
