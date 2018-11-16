<?php

namespace App\modules;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

class WeichatHelper
{
    //ase秘钥
    public $key;
    protected $appid;
    protected $token;
    protected $appSecret;

    //传递参数格式可重写
    public function __construct($config)
    {
        //等号是需要自己拼接上去，不在微信后台登记的秘钥中
        $this->key = base64_decode($config['EncodingAESKey'].'=');
        $this->appid = $config['AppID'];
        $this->token = $config['Token'];
        $this->appSecret = $config['AppSecret'];
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

    //获取token_access
    public function getToken()
    {
        $key = $this->appid.'_token_access';
        $token_access = Redis::get($key);
        if ($token_access) {
            return $token_access;
        } else {
            $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$this->appid}&secret={$this->appSecret}";
            $curl_res = json_decode($this->httpCurl($url, '', 'GET'), true);
            if (isset($curl_res['access_token'])) {
                Redis::setex($key, $curl_res['expires_in'], $curl_res['access_token']);
                return $curl_res['access_token'];
            } else {
                //加入日志
                $message = "token_access can't get, because {$curl_res['errmsg']}";
                Log::error($message);
                exit;
            }
        }
    }

    //获取生成临时二维码ticket
    public function getQrcodeTicket($scene, $live_time = 2592000)
    {
        $url = 'https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token='.$this->getToken();
        $param = '{"expire_seconds": '.$live_time.', "action_name": "QR_STR_SCENE", "action_info": {"scene": {"scene_str": '.$scene.'}}}';
        $curl_res = json_decode($this->httpCurl($url, $param), true);
        if (isset($curl_res['ticket'])) {
            return urlencode($curl_res['ticket']);
        } else {
            $message = "QrcodeTicket can't get, because {$curl_res['errmsg']}";
            Log::error($message);
            return 0;
        }
    }

    //获取生成永久二维码ticket
    public function getPermanentQrcodeTicket($scene)
    {
        $url = 'https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token='.$this->getToken();
        $param = '{"action_name": "QR_LIMIT_STR_SCENE", "action_info": {"scene": {"scene_str": '.$scene.'}}}';
        $curl_res = json_decode($this->httpCurl($url, $param), true);
        if (isset($curl_res['ticket'])) {
            return urlencode($curl_res['ticket']);
        } else {
            $message = "QrcodeTicket can't get, because {$curl_res['errmsg']}";
            Log::error($message);
            return 0;
        }
    }

    //网页授权，获取用户信息
    public function getWebAuthUserInfo(string $code)
    {
        //获取token
        $url = "https://api.weixin.qq.com/sns/oauth2/access_token?appid={$this->appid}&secret={$this->appSecret}&code={$code}&grant_type=authorization_code";
        $curl_res = json_decode($this->httpCurl($url, '', 'GET'), true);
        if (isset($curl_res['access_token'])) {
            $argv = $curl_res;
        } else {
            $message = "getWebAuthToken can't get, because {$curl_res['errmsg']}";
            Log::error($message);
            return 0;
        }

        //获取信息
        $url = "https://api.weixin.qq.com/sns/userinfo?access_token={$argv['access_token']}&openid={$argv['openid']}&lang=zh_CN";
        $curl_res = json_decode($this->httpCurl($url, '', 'GET'), true);
        if (isset($curl_res['nickname'])) {
            return $curl_res;
        } else {
            $message = "Can't get userInfo who's openid is {$argv['openid']}, because {$curl_res['errmsg']}";
            Log::error($message);
            return 0;
        }
    }

    //查询用户是否关注接口
    public function isSubscribe($openid)
    {
        $url = "https://api.weixin.qq.com/cgi-bin/user/info?access_token=".$this->getToken()."&openid={$openid}&lang=zh_CN";
        $curl_res = json_decode($this->httpCurl($url, '', 'GET'), true);
        if ($curl_res['subscribe']) {
            return 1;
        } else {
            return 0;
        }
    }

    /**
     * 获取JS证明
     * @param $accessToken
     * @return mixed
     */
    private function _getJsapiTicket($accessToken)
    {
        $key = "jsTicket_{$this->appid}";
        $ticket = Redis::get($key);
        // 缓存不存在-重新创建
        if (!$ticket) {
            // 获取js_ticket
            $url = 'https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token='.$accessToken.'&type=jsapi';
            $curl_res = json_decode($this->httpCurl($url, '', 'GET'), true);
            $ticket = $curl_res['ticket'];
            Redis::setex($key, $curl_res['expires_in'], $ticket);
        }
        return $ticket;
    }

    /**
     * 获取JS-SDK调用权限
     */
    public function wxjsSDKconf($url)
    {
        // 获取accesstoken
        $accessToken = $this->getToken();

        // 获取jsapi_ticket
        $jsapiTicket = $this->_getJsapiTicket($accessToken);
        // -------- 生成签名 --------
        $wxConf = [
            'jsapi_ticket' => $jsapiTicket,
            'noncestr' => '123456',
            'timestamp' => time(),
            'url' => $url,  //这个就是你要自定义分享页面的Url
        ];
        $string1 = sprintf('jsapi_ticket=%s&noncestr=%s&timestamp=%s&url=%s', $wxConf['jsapi_ticket'], $wxConf['noncestr'], $wxConf['timestamp'], $wxConf['url']);
        $wxConf['signature'] = sha1($string1);
        $wxConf['appId'] = $this->appid;
        return $wxConf;
    }

    /**
     * 添加微信标签
     */
    public function addWxlabel($name)
    {
        $url = 'https://api.weixin.qq.com/cgi-bin/tags/create?access_token='.$this->getToken();
        $param = '{"tag":{"name":"'.$name.'"}}';
        $res = json_decode($this->httpCurl($url, $param), true);
        if (isset($res['errcode'])) {
            $err = json_encode($res);
            //加入日志
            $message = "addWxlabel can't get, because {$err}";
            Log::error($message);
        }
        return $res['tag']['id'];
    }

    /**
     * 删除微信标签
     */
    public function delWxlabel($tagid)
    {
        $url = 'https://api.weixin.qq.com/cgi-bin/tags/delete?access_token='.$this->getToken();
        $param = '{"tag":{"id":"'.$tagid.'"}}';
        $res = json_decode($this->httpCurl($url, $param), true);
        return $res;
    }

    /**
     * 给用户打上标签
     */
    public function setLabelToUser($openid, $tagid)
    {
        $url = 'https://api.weixin.qq.com/cgi-bin/tags/members/batchtagging?access_token='.$this->getToken();
        $param = '{"openid_list":["'.$openid.'"],"tagid":'.$tagid.'}';
        $res = json_decode($this->httpCurl($url, $param), true);
        if ($res['errcode']) {
            //加入日志
            $message = "setLabelToUser can't get, because {$res['errmsg']}";
            Log::error($message);
        }
        return $res;
    }

    /**
     * 上传永久素材
     */
    public function uploadPermanentPictrue($type, $filename, $title = 'title', $introduction = 'introduction')
    {
        $url = 'https://api.weixin.qq.com/cgi-bin/material/add_material?type='.$type.'&access_token='.$this->getToken();
        $param = [
            'description' => json_encode(['title' => $title, 'introduction' => $introduction]),
            'media' => new \CURLFile($filename)
        ];
        $res = json_decode($this->httpCurl($url, $param), true);
        return $res;
    }
    /**
     * 删除永久素材
     */
    public function deletePermanentPictrue($id)
    {
        $url = 'https://api.weixin.qq.com/cgi-bin/material/del_material?access_token='.$this->getToken();
        $param = '{"media_id":'.$id.'}';
        $res = json_decode($this->httpCurl($url, $param), true);
        return $res;
    }

    /**
     * 编辑菜单栏
     */
    public function editMenu($menuConf)
    {
        $url = 'https://api.weixin.qq.com/cgi-bin/menu/create?access_token='.$this->getToken();
        $param = $menuConf;
        $res = json_decode($this->httpCurl($url, $param), true);
        return $res;
    }

    /**
     * 查看菜单栏
     */
    public function selectMenu()
    {
        $url = 'https://api.weixin.qq.com/cgi-bin/menu/get?access_token='.$this->getToken();
        $res = json_decode($this->httpCurl($url), true);
        return $res;
    }
}
