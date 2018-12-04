<?php
/**
 * 消息发送服务
 */


class TaskRun_MsgSendSer extends DM_Daemon
{
    const CRON_SLEEP = 1000000;
    const SERVICE='msgSendSer';

    /**
     * 执行Daemon任务
     *
     */
    protected function run()
    {
        $this->done();
    }

    protected function done()
    {
        $msgModel = new Model_Message();
        $deviceModel = new Model_Device();
        $wxfModel = new Model_Weixin_Friend();
        $wxModel = new Model_Weixin();

        // wxid_evw18nbq12hc22 山水美景
        // wxid_vccuh3t6xjp622 杨洋正牌女友
//        $testWxs = [
//            'wxid_vccuh3t6xjp622', 'wxid_evw18nbq12hc22',
//            // bart
//            'wxid_9bwd5pakc9o422', 'wxid_za4cycp6luj022', 'wxid_pahmbtlv7p6922', 'wxid_jjc6f4i9ju7822',
//            // bart
//            'wxid_7vlaq3hq8wgp12', 'wxid_n4pctu1xq9m212', 'wxid_4iln589wf0x822', 'wxid_wb53zd8f2nfd22',
//            // allen
//            'wxid_i8xhfkttxzq222','wxid_t1qnjaqhp67p22','wxid_xtf0yfco4u5n22','wxid_x43c2l20rqcb22','wxid_sl7u48xf1c9122',
//            'wxid_z9wk9k0c8pa022','wxid_v0b8n8fv7md122','wxid_kt94n2x906lg22','wxid_g5trgbeziwxe22','wxid_ad5im0yiaf6g22'
//        ];

        $select = $msgModel->select()
            ->where('SendStatus = ?', 'UNSEND')
            ->where('AudioStatus in (?)', [1, 3])
//            ->where("ReceiverWx in (?) or SenderWx in (?)", $testWxs)
            ->order('MessageID asc')->limit(1000);
        $messages = $msgModel->fetchAll($select);
        if (count($messages->toArray()) == 0) {
            $this->getLog()->add('no unsend messages');
            return;
        }

        // 初始化变量
        $tmpWeixinInfos = [];
        $weixinAccountsNotInGroup = [];
        $wxIds = [];
        $receiverWxAccounts = [];
        $tranWeixinAccounts = [];

        // 取出所有接收/发出的消息
        foreach ($messages as $m) {
            if ($m['GroupID'] == 0) {
                $weixinAccountsNotInGroup[] = $m['ReceiverWx'];
                $weixinAccountsNotInGroup[] = $m['SenderWx'];
                $receiverWxAccounts[] = $m['ReceiverWx'];
            }
        }
        if (empty($weixinAccountsNotInGroup)) {
            return;
        }
        $weixinAccountsNotInGroup = array_unique($weixinAccountsNotInGroup);

        // 微信的分类标签ids, 已找到的微信
        $weixinCategoryIds = $foundWeixins = $weixinAdmins = [];

        $wxAccounts = $wxModel->fetchAll(['Weixin in (?)' => $weixinAccountsNotInGroup]);
        foreach ($wxAccounts as $wxa) {
            $tmpWeixinInfos[$wxa['Weixin']] = [
                'Weixin' => $wxa['Weixin'],
                'Nickname' => $wxa['Nickname'],
                'Avatar' => $wxa['AvatarUrl']
            ];
            $wxIds[$wxa['Weixin']] = $wxa['WeixinID'];
            $weixinCategoryIds[$wxa['Weixin']] = $wxa['CategoryIds'] ? explode(',', $wxa['CategoryIds']) : [];
            $foundWeixins[] = $wxa['Weixin'];
            if ($wxa['YyAdminID']) {
                $weixinAdmins[$wxa['Weixin']] = explode(',', $wxa['YyAdminID']);
            }
        }

        // 查好友
        $friendAccountsNotInGroup = array_diff($weixinAccountsNotInGroup, $foundWeixins);
        if (!empty($friendAccountsNotInGroup)) {
            $s = $wxfModel->select()->where('Account in (?)', $weixinAccountsNotInGroup)->limit(1000);
            $wxFriends = $wxfModel->fetchAll($s)->toArray();
            foreach ($wxFriends as $friend) {
                if (!isset($tmpWeixinInfos[$friend['Account']])) {
                    $tmpWeixinInfos[$friend['Account']] = [
                        'Weixin' => $friend['Account'],
                        'Nickname' => $friend['NickName'],
                        'Avatar' => $friend['Avatar']
                    ];
                }
            }
        }

        // 查询出接收者属于weixins表且转化url不为空的,
        $receiverWxAccountsTranSelect = $wxModel->select()->where("FriendMsgTranUrl = ''")->where('Weixin in (?)', $receiverWxAccounts);
        $receiverWxAccountsTrans = $wxModel->fetchAll($receiverWxAccountsTranSelect);
        foreach ($receiverWxAccountsTrans as $r) {
            $tranWeixinAccounts[] = $r['Weixin'];
        }

        // 自动回复消息模板
        $date = date("Y-m-d");
        $tModel = new Model_MessageTemplate();
        $autoReplySelect = $tModel->select()->where('Type = ?', 'AUTO')
            ->where('IsEnable = ?', 'Y')
            ->where("StartDate <= ?",$date)
            ->where("? <= EndDate",$date);
//        var_dump($autoReplySelect->__toString());exit;
        $autoReplyTemplates = $autoReplySelect->query()->fetchAll();

        // 发送消息
        $clientIds = [];
        foreach ($messages as $msg) {
//            if (!(in_array($msg['ReceiverWx'], $testWxs) || in_array($msg['SenderWx'], $testWxs))) {
//                continue;
//            }

            try {
                if ($msg['GroupID'] == 0) {
                    $receiverAccount = $msg['ReceiverWx'];
                    $senderAccount = $msg['SenderWx'];

                    $data = [
                        'MessageID' => $msg['MessageID'],
                        'GroupID' => 0,
                        'ReceiverWx' => $receiverAccount,
                        'ReceiverNickname' => $tmpWeixinInfos[$msg['ReceiverWx']]['NickName'] ?? '',
                        'ReceiverAvatar' => $tmpWeixinInfos[$msg['ReceiverWx']]['Avatar'] ?? '',
                        'SenderWx' => $senderAccount,
                        'SenderNickname' => $tmpWeixinInfos[$msg['SenderWx']]['NickName'] ?? '',
                        'SenderAvatar' => $tmpWeixinInfos[$msg['SenderWx']]['Avatar'] ?? '',
                        'LastContent' => $msg['Content'],
                        'LastMsgType' => $msg['MsgType'],
                        'LastMsgTime' => $msg['AddDate'],
                        'SyncTime' => $msg['SyncTime'],
                        // 兼容web, 保持两边字段一致
                        'Content' => $msg['Content'],
                        'FromClient' => $msg['FromClient'],
                        'IsBigImg' => $msg['IsBigImg'],
                        'MsgType' => $msg['MsgType'],
                        // 'Nickname' => "沙沙? ? ?",
                        'ReadStatus' =>  $msg['ReadStatus'],
                        'ReadTime' => $msg['ReadTime'],
                        'Remark' => $msg['Remark'],
                        'SendStatus' => $msg['SendStatus'],
                        'SendTime' => $msg['SendTime'],
                        'SendTo' => $msg['SendTo'],
                        'TranStatus' => $msg['TranStatus'],
                        'TranTime' => $msg['TranTime'],
                        'WxCreateTime' => $msg['WxCreateTime'],
                        'WxMsgSvrId' => $msg['WxMsgSvrId'],
                        'MonitorWordIds' => '',
                        'AudioMp3' => $msg['AudioMp3'],
                        'AudioText' => $msg['AudioText']
                    ];
                    // 增加queueId
                    $s = Model_MessageQueue::getInstance()->fromSlaveDB()
                        ->select()->from(Model_MessageQueue::$table_name, ['QueueID'])
                        ->where('MessageID = ?', $msg['MessageID']);
                    $queue = Model_MessageQueue::getInstance()->fromSlaveDB()->fetchRow($s);
                    if ($queue) {
                        $data['QueueID'] = $queue['QueueID'];
                    }
                    $response = json_encode(['TaskType' => TASK_CODE_SEND_CHAT_MSG, 'Result' => $data]);
                    // 发送给 web
                    Helper_Gateway::initConfig()->sendToUid($receiverAccount, $response);
                    Helper_Gateway::initConfig()->sendToUid($senderAccount, $response);

                    // 发送给手机端
                    if (isset($weixinAdmins[$receiverAccount])) {
                        $adminIds = $weixinAdmins[$receiverAccount];
                        $monitorWordIds = $this->existMonitorWordIds($msg['Content'], $adminIds);
                        if (!empty($monitorWordIds)) {
                            $data['MonitorWordIds'] = implode(',', $monitorWordIds);
                        }
                        $response = json_encode(['TaskType' => TASK_CODE_SEND_CHAT_MSG, 'Result' => $data]);
                        foreach ($adminIds as $aId) {
                            Helper_Gateway::initConfig()->sendToUid($aId, $response);
                        }
                    }
                    if (isset($weixinAdmins[$senderAccount])) {
                        $adminIds = $weixinAdmins[$senderAccount];
                        $monitorWordIds = $this->existMonitorWordIds($msg['Content'], $adminIds);
                        if (!empty($monitorWordIds)) {
                            $data['MonitorWordIds'] = implode(',', $monitorWordIds);
                        }
                        $response = json_encode(['TaskType' => TASK_CODE_SEND_CHAT_MSG, 'Result' => $data]);
                        foreach ($adminIds as $aId) {
                            Helper_Gateway::initConfig()->sendToUid($aId, $response);
                        }
                    }

                    $msg->SendStatus = 'WEB';
                    $msg->SendTime = date('Y-m-d H:i:s');

                    // 此处只改写状态,另外一个脚本转发到三方脚本上,
                    // 否则可能会因为三方脚本的不确定性导致消息发送到web端超时或失败,影响消息的发送
                    if (in_array($receiverAccount, $tranWeixinAccounts)) {
                        $msg->TranStatus = 'WAIT';
                    }

                    $msg->save();
                    $this->getLog()->add('send web ok, msgId:'.$msg['MessageID']);

                    // todo: 转发给指定 url

                    // 自动回复
                    $this->autoReply($msg->toArray(), $autoReplyTemplates, $weixinCategoryIds, $clientIds);
                } else {

                }
            } catch (Exception $e) {
                $this->getLog()->add('error:'.$e->__toString());
            }

        }
    }

    protected function existMonitorWordIds($content, $adminIds)
    {
        $existIds = [];
        $words = Model_MonitorWord::getInstance()->fetchAll(['AdminID in (?)' => $adminIds]);
        foreach ($words as $word) {
            if (false !== strpos($content, $word['Word'])) {
                $existIds[] = (int)$word['WordID'];
            }
        }
        return $existIds;
    }

    protected function autoReply($msg, $autoReplyTemplates = [], $weixinCategoryIds = [], &$clientIds = [])
    {
        $receiverAccount = $msg['ReceiverWx'];
        $senderAccount = $msg['SenderWx'];

        // wxid_ad5im0yiaf6g22	wxid_v0b8n8fv7md122 wxid_cl7dqd61novc22
//        $canReply = false;
//        if ((($receiverAccount == 'wxid_ad5im0yiaf6g22' && $senderAccount == 'wxid_v0b8n8fv7md122') || ($senderAccount == 'wxid_ad5im0yiaf6g22' && $receiverAccount == 'wxid_v0b8n8fv7md122'))) {
//            $canReply = true;
//        } else
//        if ((($receiverAccount == 'wxid_ad5im0yiaf6g22' && $senderAccount == 'wxid_cl7dqd61novc22') || ($senderAccount == 'wxid_ad5im0yiaf6g22' && $receiverAccount == 'wxid_cl7dqd61novc22'))) {
//            $canReply = true;
//        } else
//        if ((($receiverAccount == 'wxid_cl7dqd61novc22' && $senderAccount == 'wxid_v0b8n8fv7md122') || ($senderAccount == 'wxid_cl7dqd61novc22' && $receiverAccount == 'wxid_v0b8n8fv7md122'))) {
//            $canReply = true;
//        }
//        if ($canReply === false) {
//            return;
//        }

        // 如果不是文本, 直接跳过, 不进行自动回复
        if ($msg['MsgType'] != 1) {
            return;
        }

        // 自动回复
        $hasAutoReplyTagId = false;
        $hasAutoReplyKeyword = false;
        $autoReplyContents = [];

        $time = date("H:i");
        foreach ($autoReplyTemplates as $art) {
            $times = json_decode($art["TimeQuantum"],true);
            if(! (is_array($times) && count($times)) ){
                continue;
            }
            $isMatch = false;
            foreach ($times as $t) {
                //跨天
                if($t["End"] < $t["Start"]){
                    if($t["End"] <= $time && $time <= "23:59"){
                        $isMatch = true;
                        break;
                    }
                    if("00:00" <= $time && $time <= $t["Start"]){
                        $isMatch = true;
                        break;
                    }
                }
                if($t["Start"] <= $time && $time <= $t["End"]){
                    $isMatch = true;
                    break;
                }
            }
            if(!$isMatch){
                continue;
            }
            $wxTagIds = trim($art['WxTagIDs']);
            if ($wxTagIds === '') {
                // 表示所有
                $hasAutoReplyTagId = true;
            } else {
                $wxTagIds = explode(',', $wxTagIds);
                $referCids = isset($weixinCategoryIds[$receiverAccount]) ? $weixinCategoryIds[$receiverAccount] : [];
                if ($referCids && count(array_intersect($wxTagIds, $referCids)) > 0) {
                    $hasAutoReplyTagId = true;
                }
            }
            if ($hasAutoReplyTagId === false) {
                continue;
            }
            // [{"Type":"REFER","Keyword":"aaa"},{"Type":"REG", "Keyword":"bbb"}]
            $keywords = json_decode($art['Keywords'], 1);
            foreach ($keywords as $word) {
                if ($word['Type'] == 'REFER') {
                    if (trim($word['Keyword']) === trim($msg['Content'])) {
                        $hasAutoReplyKeyword = true;
                        continue;
                    }
                } else {
                    if (false !== strpos($msg['Content'], $word['Keyword'])) {
                        $hasAutoReplyKeyword = true;
                        continue;
                    }
                }
            }

            if ($hasAutoReplyKeyword === false) {
                // 如果没有自动回复关键词, 则将满足自动标签也置为false, 下一次查看新的记录
                $hasAutoReplyTagId = false;
                continue;
            }

            // [{"Type":"TEXT","Content":"xxx"}, {"Type":"IMG","Content":"http://img..."},{"Type":"LINK","Content":"http://..."},{"Type":"VIDEO","Content":"http://..."}]
            $replyContents = json_decode($art['ReplyContents'], 1);
            if ($art['ReplyType'] == 'ALL') {
                $autoReplyContents = $replyContents;
            } else {
                $autoReplyContents = [$replyContents[array_rand($replyContents)]];
            }
            // 找到一条满足的, 不继续向下找
            if (!empty($autoReplyContents)) {
                break;
            } else {
                $hasAutoReplyTagId = $hasAutoReplyKeyword = false;
            }
        }

        $deviceModel = new Model_Device();
        if ($hasAutoReplyTagId === true && $hasAutoReplyKeyword === true && !empty($autoReplyContents)) {

            if (!isset($clientIds[$receiverAccount])) {
                $device = $deviceModel->fetchRow(['OnlineWeixin = ?' => $receiverAccount]);
                if (!$device) {
                    $this->getLog()->add('auto reply client fail, msgId:'.$msg['MessageID'] .';reason:客户端不在线');
                    return;
                }
                $client_id = $device['ClientID'];
                $clientIds[$receiverAccount] = $client_id;
            } else {
                $client_id = $clientIds[$receiverAccount];
            }
            foreach ($autoReplyContents as $autoReplyContent) {
                // '1:文本 2:图片 3:视频 4:链接 5:小程序 6:语音 7:红包 8:转账',
                switch ($autoReplyContent['Type']) {
                    case 'TEXT':
                        $type = 1;
                        break;
                    case 'IMG':
                        $type = 2;
                        break;
                    case 'Video':
                        $type = 3;
                        break;
                    case 'Audio':
                        $type = 6;
                        break;
                    default:
                        $type = 1;
                        break;
                }
                $data = [
                    'MessageID' => '',
                    'ChatroomID' => "",
                    'WxAccount' => $senderAccount,
                    'content' => Helper_Until::replaceMsgContent($autoReplyContent['Content'], $receiverAccount, $senderAccount),
                    'type' => $type
                ];

                if($art["Delay"] > 0){
//                    $wx = (new Model_Weixin())->getWx($msg["ReceiverWx"]);
//                    if(!$wx){
//                        $this->getLog()->add("not find wx:{$msg["ReceiverWx"]}");
//                    }else{
//                        Helper_DisQueue::getInstance()->inQueue(Helper_DisQueue::job_name_msgSend, ['ReceiverWxId' => $wx['WeixinID'], 'Data' => $data], $art['Delay'] * 60);
//                        $this->getLog()->add('auto reply delay in queue ok, msgId:'.$msg['MessageID'] .';content:'.$autoReplyContent['Content']);
//                    }
                }else{
                    $response = json_encode(['TaskCode' => TASK_CODE_SEND_CHAT_MSG, 'Data' => $data]);
                    $res = Helper_Gateway::initConfig()->sendToClient($client_id, $response);
                    if ($res) {
                        $this->getLog()->add('auto reply client ok, msgId:'.$msg['MessageID'] .';content:'.$autoReplyContent['Content']);
                    } else {
                        $this->getLog()->add('auto reply client fail, msgId:'.$msg['MessageID'] .';content:'.$autoReplyContent['Content']);
                    }
                }
            }
        }
        $this->getLog()->flush();
    }

    protected function init()
    {
        parent::init();
        self::getLog()->add("\n\n**********************定时更新**************************");
        self::getLog()->flush();
    }

    /**
     * 发现新版本的事件
     */
    protected function onNewReleaseFind()
    {
        self::getLog()->add('Found new release: '.$this->getReleaseCheck()->getRelease().', will quit for update.');
        die();
    }

    /**
     * 系统运行过程检测到内存不够的事件
     */
    protected function onOutOfMemory()
    {
        self::getLog()->add('System find that daemon will be out of memory, will quit for restart.');
        die();
    }

}
