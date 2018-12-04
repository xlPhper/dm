<?php
class Helper_OpenEncrypt
{
    public static $encryptKey = 'LmMGStGtOpF4xNyvYt54EQ==';

    /**
     * 加密
     * @param $msg string 加密消息体
     * @return string
     */
    public static function encrypt($msg)
    {
        $key = base64_decode(self::$encryptKey);
        $data = strtoupper(substr(md5($msg), 0, 16)) . $msg;
        $desKey = substr($key, 0, 8);
        $iv = substr($key, strlen($key) - 8);
        return openssl_encrypt ($data, 'des-cbc', $desKey, 0, $iv);
    }

    /**
     * 解密
     * @param $encryptData string 加密数据
     * @return string
     */
    public static function decrypt($encryptData)
    {
        $key = base64_decode(self::$encryptKey);
        $desKey = substr($key, 0, 8);
        $iv = substr($key, strlen($key) - 8);
        $msg = openssl_decrypt ($encryptData, 'des-cbc', $desKey, 0, $iv);
        return substr($msg, 16);
    }

    /**
     * 设置加密 key
     * @param $encryptKey string 加密key
     */
    public static function setKey($encryptKey)
    {
        self::$encryptKey = $encryptKey;
    }
}