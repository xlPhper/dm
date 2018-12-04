<?php
/**
 * Created by PhpStorm.
 * User: Tim
 * Date: 2018/4/24
 * Time: 23:30
 */
class WeixinController extends DM_Controller
{
    public function getDeviceConfigAction()
    {
        $this->showJson(1, "", Model_Device::getDeviceConfig());
    }

    public function changeAction()
    {
        $DeviceID = $this->_getParam("DeviceID");
        $WeixinID = $this->_getParam("WeixinID");
        $Level = $this->_getParam('Level', TASK_LEVAL_MEDIUM);

        $weixinModel = new Model_Weixin();
        $flag = $weixinModel->changeWeixin($DeviceID, $WeixinID, $Level);

        $this->showJson($flag);
    }

    public function newAction()
    {
        $DeviceNO = $this->_getParam('DeviceNO');
        $Weixin = $this->_getParam('Weixin');

        $deviceModel = new Model_Device();
        $deviceInfo = $deviceModel->getInfoByNO($DeviceNO);

        if(empty($Weixin) || empty($deviceInfo)){
            exit("无效的请求参数");
        }

        $config = [];
        $config['SN'] = $this->_getParam('SN');
        $config['AndroidID'] = $this->_getParam('AndroidID');
        $config['IMEI'] = $this->_getParam('IMEI');
        $config['MAC'] = $this->_getParam('MAC');
        $config['SSID'] = $this->_getParam('SSID');
        $config['Model'] = $this->_getParam('Model');
        $config['Vendor'] = $this->_getParam('Vendor');
        $config['Brand'] = $this->_getParam('Brand');

        $data = [
            'DeviceID'  =>  $deviceInfo['DeviceID'],
            'Weixin'    =>  $Weixin,
            'Config'    =>  json_encode($config),
            'AddDate'   =>  date("Y-m-d H:i:s")
        ];

        $weixinModel = new Model_Weixin();
        $weixinInfo = $weixinModel->getInfo($Weixin);
        if(!isset($weixinInfo['WeixinID'])) {
            $flag = $weixinModel->insert($data);
            $this->showJson($flag);
        }else{
            $flag =  false;
            $this->showJson($flag, '重复的微信号');
        }
    }
}