<?php

class DM_Helper
{
    public static function curl($url, $fields = [], $isPost = false, $timeout = 15)
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/36.0.1985.125 Safari/537.36');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); //禁止ssl验证

        if ($isPost) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        }

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new Exception(curl_error($ch),0);
        }

        $httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if (200 !== $httpStatusCode) {
            throw new Exception("http status code exception : ".$httpStatusCode);
        }
        return $response;
    }

    /**
     * 获取逗号分隔的id对应name 关系
     * @param string $ids 1,2,3
     * @param array $idToName [{id:name}]
     * @param bool $isImplode false return array ids[{id:name}]
     * @return array|string name逗号分隔
     */
    public static function explodeToImplode($ids,$idToName,$isImplode = true)
    {
        $targets = [];
        if($ids){
            $sources = explode(",",$ids);
            foreach ($sources as $id) {
                $targets[$id] = empty($idToName[$id])?$id:$idToName[$id];
            }
        }
        if($isImplode){
            return implode(",",array_values($targets));
        }
        return $targets;
    }
}