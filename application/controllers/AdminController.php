<?php
/**
 * Created by PhpStorm.
 * User: Ekko
 * Date: 2018/7/24
 * Ekko: 17:58
 */

class AdminController extends DM_Controller
{
    /**
     * 导入通讯录20个手机号
     */
    public function getPhonesAction()
    {
        $category_id = $this->_getParam('CategoryID',null);

        $weixin = $this->_getParam('Weixin', null);
        $model = new Model_Phones();

        //开启事务
        $model->getAdapter()->beginTransaction();

        $num = 40;
        $data = $model->getPHonesLimit($num,$category_id);
        $phones = [];
        foreach ($data as $v) {
            $phones[] = $v['Phone'];
        }

        $up = $model->update(['FriendsState' => 1, 'SendDate' => date('Y-m-d'), 'SendWeixin' => $weixin], ['Phone in (?)' => $phones]);

        if ($up == false){
            $model->getAdapter()->rollBack();
        }

        $model->getAdapter()->commit();
        $this->showJson(1, '查询成功', $data);
    }

    /**
     * 客户端号码包
     */
    public function phoneListAction()
    {
        $phone_model = new Model_Phones();
        $res = $phone_model->getPhones([88,182,269]);
        $this->showJson(1, '', $res);
    }

    /**
     * 返回客户端 版本信息
     */
    public function versionAction()
    {
        $test_model = $this->_getParam('testMode',0);

        try {
            $version_model = new Model_Version();
            $list = $version_model->findList($test_model);
            foreach ($list as &$val) {
                if ($val['NeedRestart'] == 'Y') {
                    $val['NeedRestart'] = boolval(1);
                } else {
                    $val['NeedRestart'] = boolval(0);
                }
            }
            $this->showJson(1, '', $list);
        } catch (Exception $e) {
            $this->showJson(0, '抛出异常');
        }
    }

    /**
     * 单个手机号添加微信好友接口【运营在使用】
     */
    public function phoneAddWeixinAction()
    {
        $phone = $this->_getParam('Phone', null);
        $wxcateid = $this->_getParam('WxCateId', null);
        $copyWriting = $this->_getParam('CopyWriting', null);

        $weixin_model = new Model_Weixin();

        $weixins = $weixin_model->findIsWeixins($wxcateid);

        foreach ($weixins as $val) {

            $childTaskConfigs = [
                'Phones' => array($phone),
                'Weixin' => $val['Weixin'],
                'AddNum' => 1,
                'CopyWriting' => $copyWriting
            ];
            $task_id = (new Model_Task())->insert([
                'WeixinID' => $val['WeixinID'],
                'TaskCode' => TASK_CODE_FRIEND_JOIN,
                'TaskConfig' => json_encode($childTaskConfigs),
                'MaxRunNums' => 1,
                'AlreadyNums' => 0,
                'TaskRunTime' => '',
                // 当前时间向后推迟 5-30 秒
//							'NextRunTime' => date('Y-m-d H:i:s', (time() + mt_rand(5, 1800))),
                'NextRunTime' => date('Y-m-d H:i:s', strtotime('+2 minute')),
                'LastRunTime' => '0000-00-00 00:00:00',
                'Status' => TASK_STATUS_NOTSTART,
                'ParentTaskID' => 0,
                'IsSendClient' => 'Y'
            ]);

            $taskLog_model = new Model_Task_Log();
            //加入日志
            $taskLog_model->add($task_id, 0, STATUS_NORMAL, "生成单个手机号好友添加任务");
        }

        $this->showJson(1, '成功');

    }

    /**
     * 接收错误信息
     */
    public function exceptionAction()
    {
        $weixin = trim($this->_getParam('Weixin', ''));
        $messageInfo = trim($this->_getParam('MessageInfo', ''));
        $deviceId = trim($this->_getParam('DeviceID', ''));
        $message = trim($this->_getParam('Message', ''));
        $appVersion = trim($this->_getParam('AppVersion', ''));
        $weixinVersion = trim($this->_getParam('WeixinVersion', ''));
        $model = trim($this->_getParam('Mode', ''));

        $exceptionModel = Model_Exception::getInstance();

        $data = [
            'Weixin' => $weixin,
            'DeviceNO' => $deviceId,
            'Message' => $message,
            'MessageInfo' => $messageInfo,
            'AppVersion' => $appVersion,
            'WeixinVersion' => $weixinVersion,
            'Mode' => $model,
            'AddDate' => date('Y-m-d H:i:s'),
        ];

        $int = $exceptionModel->insert($data);

        if ($int) {
            $this->showJson(1, '成功');
        } else {
            $this->showJson(0, '失败');
        }
    }

    /**
     * 二维码加群-临时生成
     */
    public function qrcodeJoinGroupAction()
    {

        $code = $this->_getParam('Code',null);
        $img = $this->_getParam('Img',null);
        $weixin_id = $this->_getParam('WeixinID',null);

        if ($code == null){
            // $img = 'http://wxgroup-img.duomai.com/6f3c77d3f0b8dbabe1682d151eb1b174';
            $doubanModel = new Model_Gather_Douban();
            $qrcode = $doubanModel->qrcode($img);
        }else{
            $qrcode = $code;
        }

        if ($qrcode == false || $qrcode == null){
            $this->showJson(0, '请上传有效的群二维码信息或图片');
        }

        $childTaskConfigs = [
            'Code' => [$qrcode]
        ];

        (new Model_Task())->insert([
            'WeixinID' => $weixin_id,
            'TaskCode' => TASK_CODE_GROUP_JOIN,
            'TaskConfig' => json_encode($childTaskConfigs),
            'MaxRunNums' => 1,
            'AlreadyNums' => 0,
            'TaskRunTime' => '',
            // 当前时间向后推迟 5-30 秒
            'NextRunTime' => date('Y-m-d H:i:s'),
            'LastRunTime' => '0000-00-00 00:00:00',
            'Status' => TASK_STATUS_NOTSTART,
            'ParentTaskID' => 0,
            'IsSendClient' => 'Y'
        ]);
        var_dump('ok');
        exit;
    }

    /**
     * 初始化任务
     */
    public function resttingAction()
    {
        $weixin_id = $this->_getParam('WeixinID',null);

        (new Model_Task())->insert([
            'WeixinID' =>$weixin_id,
            'TaskCode' => TASK_CODE_RESETTING,
            'TaskConfig' => json_encode([]),
            'MaxRunNums' => 1,
            'AlreadyNums' => 0,
            'TaskRunTime' => '',
            // 当前时间向后推迟 5-30 秒
            'NextRunTime' => date('Y-m-d H:i:s'),
            'LastRunTime' => '0000-00-00 00:00:00',
            'Status' => TASK_STATUS_NOTSTART,
            'ParentTaskID' => 0,
            'IsSendClient' => 'Y'
        ]);
        var_dump('ok');exit;
    }

    /**
     * 下发获取URL页面内容的任务
     * 最好三分钟执行一次
     */
    public function getUrlAction()
    {
        $model = new Model_Linkurl();
        $linkurls = $model->findUrl();

        foreach ($linkurls as $k=>$v){
            $s = $k*3*60;

            $taskconfig = [
                'ID' => $v['LinkurlID'],
                'Url' => $v['Url']
            ];

            (new Model_Task())->insert([
                'WeixinID' =>232,
                'TaskCode' => TASK_CODE_DETECTION_URL,
                'TaskConfig' => json_encode($taskconfig),
                'MaxRunNums' => 1,
                'AlreadyNums' => 0,
                'TaskRunTime' => '',
                // 隔三分钟执行
                'NextRunTime' => date('Y-m-d H:i:s',strtotime('+ '.$s.'second')),
                'LastRunTime' => '0000-00-00 00:00:00',
                'Status' => TASK_STATUS_NOTSTART,
                'ParentTaskID' => 0,
                'IsSendClient' => 'Y'
            ]);
        }

        var_dump('ok');exit;
    }

    public function testProxyAction(){
        $proxy = DM_Controller::curl('https://aphp.duomai.com/proxy_index/get-rand', false);
        $proxyInfo = json_decode($proxy, true);
        if(isset($proxyInfo['d']) && isset($proxyInfo['d']['Ip']) && isset($proxyInfo['d']['Port'])){
            $proxycurl = $proxyInfo['d']['Ip'].':'.$proxyInfo['d']['Port'];
        }else{
            echo 'get proxy error:'.$proxy;
            exit();
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://ip.cn');
            //curl_setopt($ch, CURLOPT_PROXYAUTH, CURLAUTH_BASIC); //代理认证模式
            curl_setopt($ch, CURLOPT_PROXY, $proxyInfo['d']['Ip']); //代理服务器地址
            curl_setopt($ch, CURLOPT_PROXYPORT,$proxyInfo['d']['Port']); //代理服务器端口

            //curl_setopt($ch, CURLOPT_PROXY, "182.240.246.162:26341"); //代理服务器地址
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
        print_r($response);exit();
    }

    /**
     * 导入微信地区信息
     */
    public function setAreaAction()
    {
        set_time_limit(0);
        $file = file_get_contents('D:/mmregioncode_zh_CN.txt');
        $data = explode(',',$file);

        $model = new Model_Area;

        foreach ($data as $a){
            try{
                $b = explode('|',$a);
                $c = explode(' ',$b[0]);
                $d = explode('_',$c[0]);
                $n = count($d);

                if ($n <= 1){
                    $parentAreaID = 0;
                }else{
                    $parent = '';
                    for($i=0;$i<$n-1;$i++)
                    {

                        $parent .= $d[$i].'_';
                    }
                    $parent = rtrim($parent,'_');
                    if ($parent){
                        $parentArea = $model->findByAreaCode($parent);
                        $parentAreaID = $parentArea['AreaID'];
                    }else{
                        $parentAreaID = 0;
                    }
                }

                $data =[
                    'AreaCode'=>$b[0],
                    'AreaName'=>$b[1],
                    'ParentAreaID'=>$parentAreaID
                ];
                $model->insert($data);
            }catch (Exception $e){
                var_dump('抛出异常'.$e->getMessage());exit;
            }

        }
        var_dump('ok');exit;

    }


    /**
     * 拉人进群
     */
    public function joinGroupAction()
    {
        // Model
        $taskModel = new Model_Task();

        $weixinId = 0;

        // 需要拉进来的好友 必须是微信帐号不能是Alias
        $weixins = [
            'wxid_1',
            'wxid_2',
            'wxid_3',
        ];

        $taskConfig = [
            'ChatroomID' => '',
            'Friend' =>$weixins
        ];
        $taskConfig = json_encode($taskConfig);

        $taskModel->addCommonTask(TASK_CODE_GROUP_ADD_MEMBER, $weixinId, $taskConfig, 0);

        $this->showJson(1,'成功');
    }

    /**
     * 创建群
     */
    public function createGroupAction()
    {
        // Model
        $taskModel = new Model_Task();

        $weixinId = 0;

        // 需要拉进来的好友 必须是微信帐号不能是Alias
        $weixins = [
            'wxid_1',
            'wxid_2',
            'wxid_3',
        ];

        $taskConfig = [
            'Friend' => $weixins,
            'Message' => '创建群后发送的第一条消息,为了弹出这个群',
        ];
        $taskConfig = json_encode($taskConfig);

        $taskModel->addCommonTask(TASK_CODE_GROUP_CREATE, $weixinId, $taskConfig, 0);

        $this->showJson(1,'成功');
    }

    /**
     * 给微信群发送信息
     */
    public function sendGroupChatAction()
    {
        $weixin = $this->_getParam('Weixin','');
        $chatroomId = $this->_getParam('ChatroomID','');
        $content = $this->_getParam('Content','');

        if (empty($weixin)){
            $this->showJson(0,'微信帐号不存在');
        }

        if (empty($chatroomId)){
            $this->showJson(0,'群标识不存在');
        }

        $deviceModel = Model_Device::getInstance();

        // 测试账号山水美景
        $weixinInfo = $deviceModel->getDeviceByWeixin($weixin);

        $data = [
            'MessageID' => '',
            'ChatroomID' => $chatroomId,
            'WxAccount' => "",
            'content' => $content,
            'type' => 1
        ];

        $response = json_encode(['TaskCode' => TASK_CODE_SEND_CHAT_MSG, 'Data' => $data]);
        Helper_Gateway::initConfig()->sendToClient($weixinInfo['ClientID'], $response);
        $this->showJson(1,'发送成功');
    }

    /**
     * 退群任务
     */
    public function groupQuitAction()
    {

        $weixinId = $this->_getParam('WeixinID','');
        $chatroomId = $this->_getParam('ChatroomID','');

        if (empty($weixinId)){
            $this->showJson(0,'微信帐号不存在');
        }

        if (empty($chatroomId)){
            $this->showJson(0,'群标识不存在');
        }

        $taskModel = new Model_Task();

        $taskConfig = [
            'ChatroomID' => $chatroomId,
        ];
        $taskConfig = json_encode($taskConfig);

        $taskModel->addCommonTask(TASK_CODE_GROUP_QUIT, $weixinId, $taskConfig, 0);

        $this->showJson(1,'成功');
    }
}