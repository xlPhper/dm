<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/8/9
 * Time: 14:36
 */

require APPLICATION_PATH . '/../library/QrCode/autoload.php';

use Zxing\QrReader;

class Model_Gather
{

    public $sleep = 5;

    /**
     * 获取302跳转后的真实地址
     * @param $url
     */
    public function getRealUrl($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_AUTOREFERER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
// 下面两行为不验证证书和 HOST，建议在此前判断 URL 是否是 HTTPS
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/36.0.1985.125 Safari/537.36');
// $ret 返回跳转信息
        curl_exec($ch);
// $info 以 array 形式返回跳转信息
        $info = curl_getinfo($ch);
// 跳转后的 URL 信息
        $retURL = $info['url'];
// 记得关闭curl
        curl_close($ch);
        return $retURL;
    }

    /**
     * 检测时间是否超过天数
     * 如果超过则返回真，否则返回假
     *
     * @param $check_time
     * @param int $expire_time
     */
    public function checkExpireTime($check_date, $expire_time = 7)
    {
        $check_time = strtotime($check_date);
        $now_time = time();
        if($check_time + $expire_time * 86400 < $now_time){
            return true;
        }else{
            return false;
        }
    }

    public function webp2jpg($imgpath)
    {
        $im = imagecreatefromwebp($imgpath);
        $new_path = APPLICATION_PATH . "/data/img/".time().".jpg";
        imagejpeg($im, $new_path, 100);
        imagedestroy($im);
        return $new_path;
    }

    public function saveTmpImg($imgpath)
    {
        $new_path = APPLICATION_PATH . "/data/img/".time().".jpg";
        file_put_contents($new_path, file_get_contents($imgpath));
        return $new_path;
    }

    public function qrcode($img)
    {
        $qrcode = new QrReader($img);

        return $qrcode->text();
    }

    public function findGroupQrcode($imgs, $convert = false)
    {
        $data = [];
        foreach($imgs as $img){
            if($convert){
                $img = $this->webp2jpg($img);
            }
            $qrcode = $this->qrcode($img);

            if(preg_match("/\/g\//", $qrcode)){
                $data[] = $qrcode;
            }
        }
        return $data;
    }
}