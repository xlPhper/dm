<?php
/**
 * Created by PhpStorm.
 * User: Ekko
 * Date: 2018/8/14
 * Ekko: 16:29
 */
require_once APPLICATION_PATH . "/../library/phpqrcode/phpqrcode.php";

class Model_Styles extends DM_Model
{
    public static $table_name = "group_styles";
    protected $_name = "group_styles";
    protected $_primary = "StyleID";

    protected $configs = array();
    protected $price = 0;

    /**
     * 初始化
     */
    public static function initStyle(array $configs, array $titles)
    {
        $s = new Model_Styles();

        foreach ($configs as $key => $item) {
            if (isset($titles[$key]) && in_array($item['type'], ['text','price', 'qrcode'])) {
                $configs[$key]['text'] = $titles[$key];
            }
        }

        $s->configs = $configs;
        $s->price = $titles['new-price'];

        return $s;
    }

    public function buildImg($originImg,$background = null)
    {
        $configs = $this->configs;

        if (substr($originImg, 0, 4) == 'http') {
            $originImg .= '?imageView2/2/format/png';
        }

        if ($background){
            $background_info = getimagesize($background);
            $origin_info = getimagesize($originImg);

            $im = $this->imageCreateFrom($background_info['mime'],$background);
            $origin_im = $this->imageCreateFrom($origin_info['mime'],$originImg);

            // 商品图横向居中的x坐标
            $origin_x = number_format(($background_info[0]-$origin_info[0])/2, 2);
            $origin_y = 0;

            // 产品图合成
            imagecopyresampled($im, $origin_im,$origin_x,$origin_y, 0, 0, $origin_info[0], $origin_info[1], $origin_info[0], $origin_info[1]);

        }else{
            // 没有背景图就默认产品图背景
            $origin_info = getimagesize($originImg);
            $im = $this->imageCreateFrom($origin_info['mime'],$originImg);
        }

        foreach ($configs as $item){
            switch($item['type']){
                case 'origin':   //产品图，
                    break;
                case 'backgroud':   //背景图，
                    break;
                case 'front':   //前景图，将原图导入到前景图中
                    if (preg_match("/wxgroup-img.duomai.com/", $item['img'])){
                        $img_url = $item['img'];
                    }else{
                        $img_url = APPLICATION_PATH . $item['img'];
                    }
                    $img_info = getimagesize($img_url);
                    $src_im = imagecreatefrompng($img_url);

                    imagecopy($im, $src_im, 0, 0, 0, 0, $img_info[0], $img_info[1]);

                    unset($src_im);
                    break;
                case 'qrcode':
                    $back_color = hexdec($item['back_color']);
                    $front_color = hexdec($item['front_color']);
                    $tmp_path = APPLICATION_PATH . "/data/img/qrcode_".time().".png";
                    //二维码尺寸与像素换算公式
                    $size = floor($item['height']/35*100)/100 + 0.01;
                    QRcode::png($item['text'], $tmp_path, 'h', $size, 1, false, $back_color, $front_color);
                    $src_im = imagecreatefrompng($tmp_path);
                    $img_info = getimagesize($tmp_path);
                    imagecopyresampled($im, $src_im, $item['coordinate']['x'], $item['coordinate']['y'], 0, 0, $item['height'], $item['height'], $img_info[0], $img_info[1]);
                    unset($src_im);
                    break;
                case 'price':
                    //判断价格是否有小数点
                    $len = strlen($this->price);
                    if(preg_match("/\./", $this->price)){
                        $img_config = $item['coordinate']["d".$len];
                    }else{
                        $img_config = $item['coordinate']["nd".$len];
                    }
                    $item['size'] = $img_config['size'];
                    $item['x'] = $img_config['x'];
                    $item['y'] = $img_config['y'];

                    $font_path = APPLICATION_PATH . "/data/font/" . $item['font'];

                    //字体颜色
                    $c = $this->hex2rgb($item['color']);
                    $color = imagecolorallocate($im, $c[0], $c[1], $c[2]);

                    imagettftext($im, $item['size'], 0, $item['x'], $item['y'], $color, $font_path ,$item['text']);
                    unset($font_path, $color);
                    break;
                case 'text':
                    // 是否是多个文字设置
                    if(count($item) == count($item,1)){
                        $font_path = APPLICATION_PATH . "/data/font/" . $item['font'];

                        //字体颜色
                        $c = $this->hex2rgb($item['color']);
                        $color = imagecolorallocate($im, $c[0], $c[1], $c[2]);

                        imagettftext($im, $item['size'], 0, $item['coordinate']['x'], $item['coordinate']['y'], $color, $font_path ,$item['text']);
                        unset($font_path, $color);
                        break;

                    }else{
                        foreach ($item as $txt){
                            $font_path = APPLICATION_PATH . "/data/font/" . $txt['font'];

                            //字体颜色
                            $c = $this->hex2rgb($txt['color']);
                            $color = imagecolorallocate($im, $c[0], $c[1], $c[2]);

                            imagettftext($im, $txt['size'], 0, $txt['coordinate']['x'], $txt['coordinate']['y'], $color, $font_path ,$txt['text']);
                            unset($font_path, $color);
                        }
                        break;
                    }

            }

        }

        return $im;
    }

    public function hex2rgb($hex)
    {
        $hex = str_replace("#", "", $hex);

        if(strlen($hex) == 3) {
            $r = hexdec(substr($hex,0,1).substr($hex,0,1));
            $g = hexdec(substr($hex,1,1).substr($hex,1,1));
            $b = hexdec(substr($hex,2,1).substr($hex,2,1));
        } else {
            $r = hexdec(substr($hex,0,2));
            $g = hexdec(substr($hex,2,2));
            $b = hexdec(substr($hex,4,2));
        }

        return array($r, $g, $b);
    }

    public function rgb2hex($rgb)
    {
        $hex = "#";
        $hex .= str_pad(dechex($rgb[0]), 2, "0", STR_PAD_LEFT);
        $hex .= str_pad(dechex($rgb[1]), 2, "0", STR_PAD_LEFT);
        $hex .= str_pad(dechex($rgb[2]), 2, "0", STR_PAD_LEFT);

        return $hex;
    }

    /**
     * 根据图片格式来创建画布
     */
    public function imageCreateFrom($mime,$img)
    {
        $mime_info = explode('/',$mime);

        switch ($mime_info[1]){
            case 'gif':
                $im = imagecreatefromgif($img);
                break;
            case 'jpeg':
                $im = imagecreatefromjpeg($img);
                break;
            case 'png':
                $im = imagecreatefrompng($img);
                break;
        }
        return $im;

    }

    /**
     * 查询所有样式
     * @return array
     */
    public function findAll()
    {
        $select = $this->fromSlaveDB()->select()->from($this->_name,['StyleID','ExampleImg','Status']);
        return $this->_db->fetchAll($select);
    }

    /**
     * 查询指定样式
     * @param $ID 样式ID
     * @return array
     */
    public function findByID($ID)
    {
        $select = $this->select()->where('StyleID = ?',$ID);
        return $this->_db->fetchRow($select);
    }

}