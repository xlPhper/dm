<?php
/**
 * Created by PhpStorm.
 * User: tim
 * Date: 2018/11/21
 * Time: 6:00 PM
 */
class Check_TaskController extends DM_Controller
{
    public function indexAction()
    {
        $device_numbers = $this->_getParam('device_number');
        $mFriend = new Model_Weixin_Friend();
        $mWeixin = new Model_Weixin();
        $mTask = new Model_Task();
        $mMessage = new Model_Message();

        //搜索出微信ID
        $exception_devicenum = [];
        $weixinIDs = [];
        $device_arr = explode(',', $device_numbers);
        $weixins = [];
        foreach($device_arr as $device_number){
            $weixinInfo = $mWeixin->getWeixinBySerialnum($device_number);
            if($weixinInfo === false){
                $exception_devicenum[$device_number] = $device_number;
            }else{
                $weixinIDs[$device_number] = $weixinInfo['WeixinID'];
                $weixins[$weixinInfo['WeixinID']] = $weixinInfo['Weixin'];
            }
        }

        //Zend_Debug::dump($exception_devicenum, "异常设备号");
        //Zend_Debug::dump($weixinIDs, "有效");

        //寻找微信的一天、三天、七天动作和加好友情况
        $data = [];
        foreach($weixinIDs as $device_number => $weixinID){
            $data[$device_number]['Task']['oneday'] = $mTask->statTaskNum($weixinID, date("Y-m-d 00:00:00"), date("Y-m-d 23:59:59"));//一天
            $data[$device_number]['Task']['threeday'] = $mTask->statTaskNum($weixinID, date("Y-m-d 00:00:00", time()-3*86400), date("Y-m-d 23:59:59"));//三天
            $data[$device_number]['Task']['sevenday'] = $mTask->statTaskNum($weixinID, date("Y-m-d 00:00:00", time()-7*86400), date("Y-m-d 23:59:59"));//七天

            $data[$device_number]['Friend']['oneday'] = $mFriend->statFriendNum($weixinID, date("Y-m-d 00:00:00"), date("Y-m-d 23:59:59"));//一天
            $data[$device_number]['Friend']['threeday'] = $mFriend->statFriendNum($weixinID, date("Y-m-d 00:00:00", time()-3*86400), date("Y-m-d 23:59:59"));//三天
            $data[$device_number]['Friend']['sevenday'] = $mFriend->statFriendNum($weixinID, date("Y-m-d 00:00:00", time()-7*86400), date("Y-m-d 23:59:59"));//七天

            $data[$device_number]['Message']['oneday'] = $mMessage->statMessageNum($weixins[$weixinID], date("Y-m-d 00:00:00"), date("Y-m-d 23:59:59"));//一天
            $data[$device_number]['Message']['threeday'] = $mMessage->statMessageNum($weixins[$weixinID], date("Y-m-d 00:00:00", time()-3*86400), date("Y-m-d 23:59:59"));//三天
            $data[$device_number]['Message']['sevenday'] = $mMessage->statMessageNum($weixins[$weixinID], date("Y-m-d 00:00:00", time()-7*86400), date("Y-m-d 23:59:59"));//七天
        }
//        Zend_Debug::dump($data);

        $returnData = [];
        $i = 0;
        foreach($data as $s => $datum){
            $returnData[$i]['DeviceNum'] = $s;
            $returnData[$i]['TaskOne'] = $this->joinTask($datum['Task']['oneday']);
            $returnData[$i]['TaskThree'] = $this->joinTask($datum['Task']['threeday']);
            $returnData[$i]['TaskSeven'] = $this->joinTask($datum['Task']['sevenday']);
            $returnData[$i]['FriendOne'] = $datum['Friend']['oneday'];
            $returnData[$i]['FriendThree'] = $datum['Friend']['threeday'];
            $returnData[$i]['FriendSeven'] = $datum['Friend']['sevenday'];
            $returnData[$i]['MessageOne'] = $datum['Message']['oneday'];
            $returnData[$i]['MessageThree'] = $datum['Message']['threeday'];
            $returnData[$i]['MessageSeven'] = $datum['Message']['sevenday'];
            $i++;
        }
        //Zend_Debug::dump($returnData);
        $str = "<table>\n";
        foreach($returnData as $datum){
            $str .= "<tr>\n";
            foreach($datum as $item) {
                $str .= "<td>{$item}</td>\n";
            }
            $str .= "</tr>\n";
        }
        $str .= "<table>";
        if(APPLICATION_ENV == 'production'){
            echo $str;
        }else {
            file_put_contents(APPLICATION_PATH . "/../task.html", $str);
        }

    }

    public $taskname = [
        'WeixinFriend'  =>  '上报好友信息（单个）',
        'ReportWxGroups'  =>  '同步微信群',
        'WxFriendApplyDeal'  =>  '通过微信好友',
        'DetectionPhone' =>  '检测手机号',
        'GetGZHUrlViewNum'  =>  '获取阅读数',
        'WxFriendJoin'  =>  '添加好友',
        'WeixinGroup'   =>  '发送朋友圈',
    ];

    protected function joinTask($task)
    {

        $str = "";
        foreach($task as $t){
            if(isset($this->taskname[$t['TaskCode']])){
                $str .= "<p>{$this->taskname[$t['TaskCode']]} : {$t['num']}次</p>";
            }else {
                $str .= "<p>{$t['TaskCode']} : {$t['num']}次</p>";
            }
        }
        return $str;
    }

    public function sendAction()
    {
        parent::log("qrscan", "APPLICATION_ENV : ". APPLICATION_ENV);
        $params = $this->getAllParams();
        $taskConfig = $params['taskConfig'] ?? [];
        unset($params['controller'], $params['action'], $params['module'], $params['CategoryID'], $params['taskConfig[]']);
        if(!empty($taskConfig)) {
            $params['taskConfig'] = json_encode(json_decode($taskConfig, true));
        }

        $wModel = new Model_Weixin();
        $dModel = new Model_Device();
        $CategoryID = $this->_getParam('CategoryID');
        if(empty($CategoryID)){
            parent::Log("qrscan", "无效的分类ID");
            exit;
        }
        $wData = $wModel->findWeixinCategory($CategoryID);
        foreach ($wData as $wDatum){
            //Zend_Debug::dump($wDatum);exit;
            $dInfo = $dModel->getInfo($wDatum['DeviceID']);
            if(empty($dInfo['ClientID'])){
                parent::Log("qrscan", "微信ID:{$dInfo['WeixinID']}无有效的ClientID");
                continue;
            }
            parent::Log("qrscan", "微信ID:{$wDatum['WeixinID']}发送成功");
            $params['Weixin'] = $wDatum['Weixin'];
            Helper_Gateway::initConfig()->push($dInfo['ClientID'], json_encode($params));
        }

    }
}