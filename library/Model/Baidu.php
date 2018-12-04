<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/8/28
 * Time: 15:35
 */

require_once APPLICATION_PATH . '/../library/Baidu/AipOcr.php';

class Model_Baidu
{
    public function text($img_url)
    {
        $appid = '11227838';
        $appkey = 'LpRErAp7OHrnqn2KsZU3QlTT';
        $appsecret = 'yI4oczeMGItMFgAeahSGUQffV9UQCvw1';

        $client = new AipOcr($appid, $appkey, $appsecret);

        return $client->basicGeneralUrl($img_url);
    }
}