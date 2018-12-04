<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/8/16
 * Time: 20:05
 */
class Model_Group_Style
{
    public static function buildProduct($originImg, $config)
    {
        $im = imagecreatefrompng($originImg);

        if(isset($config['qrcode'])){

        }

        if(isset($config['title'])){
            //商品标题

            //载入字体
            imageloadfont($config['title']['font']);
        }
    }
}