function http_Curl($url,$paramArray = array(),$method = 'POST'){
	        $ch = curl_init();
	        if ($method == 'POST')
	        {
	            curl_setopt($ch, CURLOPT_POST, 1);
	            curl_setopt($ch, CURLOPT_POSTFIELDS, $paramArray);
	        }
	        else if (!empty($paramArray))
	        {
	            $url .= '?' . http_build_query($paramArray);
	        }
	        curl_setopt($ch, CURLOPT_URL, $url);
	        curl_setopt($ch, CURLOPT_TIMEOUT,10);

	        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

	        if (false !== strpos($url, "https")) {
	            // 证书
	            // curl_setopt($ch,CURLOPT_CAINFO,"ca.crt");
	            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,  false);
	            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,  false);
	        }
	        $resultStr = curl_exec($ch);
	        curl_close($ch);

	        return $resultStr;
    	}