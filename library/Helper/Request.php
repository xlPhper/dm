<?php
class Helper_Request
{
    public static function curl($url,$fields, $ispost=true){
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        if ($ispost){
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        }else{
            curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        }
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/36.0.1985.125 Safari/537.36');

        //禁止ssl验证
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);


        $response = curl_exec($ch);
        if (curl_errno($ch))
        {
            throw new \Exception(curl_error($ch),0);
        }
        else
        {
            $httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if (200 !== $httpStatusCode)
            {
                return "http status code exception : ".$httpStatusCode;
            }
        }
        curl_close($ch);
        return $response;
    }
}