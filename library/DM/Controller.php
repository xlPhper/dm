<?php
/**
 * Created by PhpStorm.
 * User: Tim
 * Date: 2018/4/24
 * Time: 23:31
 */
class DM_Controller extends Zend_Controller_Action
{
    protected $_config = null;

    public function init()
    {
        parent::init();

        $this->_config = Zend_Registry::get("config");
        Helper_Redis::init($this->_config['redis']);

        //TODO 登录
        Zend_Registry::set("USERID", 1);

        $this->disableView();
    }

    protected function enableView()
    {
        $this->_helper->viewRenderer->setNoRender(false);
    }

    protected function disableView()
    {
        $this->_helper->viewRenderer->setNoRender();
    }

    /**
     * 移动端专用返回函数
     */
    protected  function responseJson($flag, $msg = '', $data = '', $ext = '')
    {
        $flag = $flag === true ?1:$flag;
        $d = array('f' => $flag,'m'=>$msg ,'d' => $data,'e'=>$ext);
        echo json_encode($d);

    }

    public static function returnJson($flag, $msg = '', $data = null, $ext = null)
    {
        $flag = $flag === true ?1:$flag;
        $d = array('f' => $flag,'m'=>$msg ,'d' => $data,'e'=>$ext);
        return json_encode($d);

    }

    protected function showJson($flag, $msg = '', $data = null, $ext = null)
    {
        $d = self::returnJson($flag, $msg, $data, $ext);
        $jsonp = $this->_getParam("callback");
        if(empty($jsonp)) {
            echo $d;exit;
        }else{
            echo $jsonp . "(" . $d . ")";exit;
        }
    }

    protected function showJsonNotExit($flag, $msg = '', $data = null, $ext = null)
    {
        $d = self::returnJson($flag, $msg, $data, $ext);
        $jsonp = $this->_getParam("callback");
        if(empty($jsonp)) {
            echo $d;
        }else{
            echo $jsonp . "(" . $d . ")";
        }
    }

    public static function Log($prefix, $msg, $writeType = 'file')
    {
        $classname = "";
        switch($writeType){
            case 'file':
                $classname = "logclass_{$prefix}_{$writeType}";
                break;
        }
        if(Zend_Registry::isRegistered($classname)){
            $logger = Zend_Registry::get($classname);
        }else{
            switch($writeType){
                case 'file':
                    $logfile_path = APPLICATION_PATH . "/data/log/{$prefix}-".date("Y-m-d").".log";
                    $writer = new Zend_Log_Writer_Stream( $logfile_path );
                    $logger = new Zend_Log($writer);
                    Zend_Registry::set($classname, $logger);
                    break;
            }
        }
        $logger->info($msg);
    }

    /**
     * 检测设备与微信号是否在一个账号下
     *
     * @param $DeviceNO
     * @param $Weixin
     * @return array|bool
     */
    public function checkDeviceAndWeixin($DeviceNO, $Weixin)
    {
        $deviceModel = new Model_Device();
        $weixinModel = new Model_Weixin();
        $deviceInfo = $deviceModel->getInfoByNO($DeviceNO);
        $weixinInfo = $weixinModel->getInfoByWeixin($Weixin);

        if(!isset($deviceInfo['DeviceID']) || !isset($weixinInfo['WeixinID'])){
            return false;
        }
        if($weixinInfo['DeviceID'] <> $deviceInfo['DeviceID']){
            return false;
        }
        return [
            'deviceInfo'    =>  $deviceInfo,
            'weixinInfo'    =>  $weixinInfo
        ];
    }

    public static function curl($url,$fields = null, $ispost=true, $curlConfig = []){
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        if(isset($fields['cookie'])){
            curl_setopt($ch,CURLOPT_COOKIE, $fields['cookie']);
            echo 'cookie';
            unset($fields['cookie']);
        }
        if ($ispost){
            curl_setopt($ch, CURLOPT_POST, true);
        }
        if($fields) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        }
        if(isset($curlConfig['proxy_type'])){
            curl_setopt($ch, CURLOPT_PROXYTYPE, $curlConfig['proxy_type']);
        }
        if(isset($curlConfig['proxy'])){
            curl_setopt($ch, CURLOPT_PROXY, $curlConfig['proxy']);
        }
        if(isset($curlConfig['proxy_port'])){
            curl_setopt($ch, CURLOPT_PROXYPORT, $curlConfig['proxy_port']);
        }
        if(isset($curlConfig['proxy_userpwd'])){
            curl_setopt($ch,CURLOPT_PROXYUSERPWD, $curlConfig['proxy_userpwd']);
        }
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch,  CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/36.0.1985.125 Safari/537.36');

        //禁止ssl验证
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);


        $response = curl_exec($ch);
        if (curl_errno($ch))
        {
            throw new Zend_Exception(curl_error($ch),0);
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

    /**
     * 获取远程图片的heade content-type
     * @param unknown $url
     * @throws Exception
     * @return mixed
     */
    public static function curl_getType($url){
        try{
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
            curl_setopt($ch, CURLOPT_HEADER, true); //取得返回头信息
            curl_setopt($ch, CURLOPT_NOBODY, true); //不返回结果
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/36.0.1985.125 Safari/537.36');
            curl_setopt($ch, CURLOPT_URL, $url);
            $response = curl_exec($ch);
            if (curl_errno($ch))
            {
                throw new Exception(curl_error($ch),0);
            }
            else
            {
                $httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                if (200 !== $httpStatusCode)
                {
                    throw new Exception("http status code exception : ".$httpStatusCode);
                }
            }
            $mime = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            curl_close($ch);
            return $mime;
        } catch (Exception $e) {
            return '';
        }

    }
}