<?php

class ChatController extends DM_Controller
{
    /**
     * 客户端上报聊天消息发送结果接口
     */
    public function sendOkAction()
    {
        $queueId = (int)$this->_getParam('QueueID');
        if ($queueId < 1) {
            $this->showJson(0, 'QueueID require');
        }
        $status = (int)$this->_getParam('Status', 0);
        if (!in_array($status, [3, 4])) {
            $this->showJson(0, 'status invalid');
        }
        $serverMsgId = trim($this->_getParam('msgSvrId', ''));
        if ($status == 3 && $serverMsgId === '') {
            $this->showJson(0, 'msgSvrId require');
        }
        $errMsg = trim($this->_getParam('ErrMsg', ''));
        $queueModel = Model_MessageQueue::getInstance();
        try {
            $queueModel->getAdapter()->beginTransaction();
            $queue = $queueModel->fromMasterDB()->getByPrimaryId($queueId);
            if (!$queue) {
                throw new \Exception('QueueID invalid');
            }
            $queue->Status = $status;
            if ($errMsg !== '') {
                $queue->ErrMsg = $errMsg;
            }
            $time = time() * 1000;
            if ($serverMsgId) {
                $queue->WxMsgSvrId = $serverMsgId;
                $queue->WxCreateTime = $time;
            }
            $queue->save();

            if ($status == 3) {
                $select = Model_Message::getInstance()->select()->from(Model_Message::$table_name, ['MessageID'])
                    ->where('WxMsgSvrId = ?', $serverMsgId);
                $message = Model_Message::getInstance()->fetchRow($select);
                if (!$message) {
                    $msgData = [
                        'GroupID' => 0,
                        'ReceiverWx' => $queue->ReceiverWx,
                        'SenderWx' => $queue->SenderWx,
                        'SendTo' => '',
                        // todo: 获取其他消息
                        'MsgType' => $queue->MsgType,
                        'Content' => $queue->Content,
                        'WxMsgSvrId' => $serverMsgId,
                        'WxCreateTime' => $time,
                        'AddDate' => $queue->SendTime,
                        'SyncTime' => date('Y-m-d H:i:s'),
                        'ReadStatus' => 'READ',
                        'ReadTime' => '0000-00-00 00:00:00',
                        'SendStatus' => 'UNSEND',
                        'SendTime' => '0000-00-00 00:00:00',
                        'FromClient' => 'Y',
                        'IsBigImg' => $queue->MsgType == 2 ? 'Y' : 'N',
                        'AudioMp3' => $queue->MsgType == 6 ? $queue->Content : '',
                        'AudioStatus' => $queue->MsgType == 6 ? 3 : 1,
                        'AudioText' => ''
                    ];
                    $messageId = Model_Message::getInstance()->fromMasterDB()->insert($msgData);
                } else {
                    $messageId = $message->MessageID;
                }

                $queue->MessageID = $messageId;
                $queue->save();
            }
            $queueModel->getAdapter()->commit();
        } catch (\Exception $e) {
            $queueModel->getAdapter()->rollBack();
            $this->showJson(0, '上报失败,err:'.$e->getMessage());
        }

        $this->showJson(1, '上报成功');
    }

    /**
     * 客户端转发聊天消息到服务端
     */
    public function tranMsgsAction()
    {
        // isSend = 1 代表指定微信发的, isSend = 0 代表好友发给指定微信的
        // [{"ChatroomID":"", "WxAccount":"wxid_1bfirks4dnvp22", "createTime":"12312", "msgSvrId":"asdasd", "content":"aaaa", "type":"1", "isSend":"1"},
        // {"ChatroomID":"", "WxAccount":"wxid_1bfirks4dnvp22", "createTime":"123223312", "msgSvrId":"asdasdsdfsdfsf", "content":"aaaa", "type":"1", "isSend":"0"}]
        $msgs = $this->_getParam('Messages', '');
        $wx = trim($this->_getParam('Weixin', ''));
        if ($wx === '') {
            $this->showJson(0, 'Weixin必填');
        }

        /**
         * {"mgsId":1, "ChatroomID":"","WxAccount":"wxid_4xruxtch2d7s21","content":"123","createTime":1536203468965,"isSend":1,"msgSvrId":"7322882491315525294","type":1},
         * {"mgsId":2, "ChatroomID":"","WxAccount":"wxid_4xruxtch2d7s21","content":"36","createTime":1536203574419,"isSend":1,"msgSvrId":"4690837085442915336","type":1}
         */

        $weixin = (new Model_Weixin())->fetchRow(['Weixin = ?' => $wx]);
        if (!$weixin) {
            $this->showJson(0, 'Weixin非法');
        }
        $wxId = $weixin['WeixinID'];

        if ($msgs === '') {
            $this->showJson(0, 'Messages必填');
        }
        $massages = json_decode($msgs, 1);
        if (json_last_error() != JSON_ERROR_NONE) {
            $this->showJson(0, 'Messages非json格式');
        }

        $validParams = $friendMsgNum = $groupMsgNum = [];
        $msgCreateTimes = [];
        foreach ($massages as $msg) {
            if (!Helper_Until::hasReferFields($msg, ['ChatroomID', 'WxAccount', 'createTime', 'msgSvrId', 'content', 'type', 'isSend'])) {
                $this->showJson(0, 'Messages字段有误');
            }
            if ($msg['WxAccount'] == $weixin) {
                continue;
            }
            // 如果是微信就不写入
            if ($msg['isSend'] == 0 && $msg['WxAccount'] == 'weixin') {
                continue;
            }
            $msg['IsBigImg'] = 'N';
            $msg = $this->assignType($msg);
            // 此处乘 1000 则表示传入数组默认最大只有1000个重复时间(实际不可能有)
            $msgCTime = $msg['createTime'] * 1000;
            $msgCTime = Helper_Until::getUniqueTime($msgCTime, $msgCreateTimes);

            $validParams[$msgCTime] = $msg;
            if ($msg['ChatroomID'] != '') {
                if (isset($groupMsgNum[$msg['ChatroomID']]['Num'])) {
                    $groupMsgNum[$msg['ChatroomID']]['Num'] += 1;
                } else {
                    $groupMsgNum[$msg['ChatroomID']]['Num'] = 1;
                }
            } else {
                if (isset($friendMsgNum[$msg['WxAccount']]['Num'])) {
                    $friendMsgNum[$msg['WxAccount']]['Num'] += 1;
                } else {
                    $friendMsgNum[$msg['WxAccount']]['Num'] = 1;
                }
            }
        }
        ksort($validParams);

        try {
            $m = new Model_Message();

            $config = Zend_Registry::get("config");
            // silk2mp3.server.url
            $silk2mp3Server = $config['silk2mp3']['server']['url'];

            $wxFriends = [];
            foreach ($validParams as $param) {
                $select = $m->select()
                    ->where('WxMsgSvrId = ?', $param['msgSvrId'])
                    ->forUpdate(true);
                $msg = $m->fetchRow($select);
                if ($msg) {
                    $msg->WxCreateTime = $param['createTime'];
                    $msg->AddDate = date('Y-m-d H:i:s', $param['createTime'] / 1000);
                    $msg->FromClient = 'Y';
                    $msg->save();
                    continue;
                }

                if ($param['isSend'] == 1) {
                    $id = $m->insert([
                        // todo: 获取群id
                        'GroupID' => 0,
                        'ReceiverWx' => $param['WxAccount'],
                        'SenderWx' => $wx,
                        'SendTo' => '',
                        // todo: 获取其他消息
                        'MsgType' => $param['type'],
                        'Content' => $param['content'],
                        'WxMsgSvrId' => $param['msgSvrId'],
                        'WxCreateTime' => $param['createTime'],
                        'AddDate' => $param['createTime'] > 0 ? date('Y-m-d H:i:s', $param['createTime'] / 1000) : date('Y-m-d H:i:s'),
                        'SyncTime' => date('Y-m-d H:i:s'),
                        'ReadStatus' => 'READ',
                        'ReadTime' => '0000-00-00 00:00:00',
                        'SendStatus' => 'UNSEND',
                        'SendTime' => '0000-00-00 00:00:00',
                        'FromClient' => 'Y',
                        'IsBigImg' => $param['IsBigImg'],
                        'AudioStatus' => $param['type'] == 6 ? 2 : 1
                    ]);
                } else {
                    if (isset($wxFriends[$param['WxAccount']])) {
                        $isFriend = $wxFriends[$param['WxAccount']];
                    } else {
                        if (Model_Weixin_Friend::getInstance()->fetchRow(['WeixinID = ?' => $wxId, 'Account = ?' => $param['WxAccount']])) {
                            $isFriend = 'Y';
                        } else {
                            $isFriend = 'N';
                        }
                        $wxFriends[$param['WxAccount']] = $isFriend;
                    }
                    $id = $m->insert([
                        // todo: 获取群id
                        'GroupID' => 0,
                        'ReceiverWx' => $wx,
                        'SenderWx' => $param['WxAccount'],
                        'SendTo' => '',
                        'MsgType' => $param['type'],
                        'Content' => $param['content'],
                        'WxMsgSvrId' => $param['msgSvrId'],
                        'WxCreateTime' => $param['createTime'],
                        'AddDate' => $param['createTime'] > 0 ? date('Y-m-d H:i:s', $param['createTime'] / 1000) : date('Y-m-d H:i:s'),
                        'SyncTime' => date('Y-m-d H:i:s'),
                        'ReadStatus' => $isFriend == 'Y' ? 'UNREAD' : 'READ',
                        'ReadTime' => '0000-00-00 00:00:00',
                        'SendStatus' => 'UNSEND',
                        'SendTime' => '0000-00-00 00:00:00',
                        'FromClient' => 'Y',
                        'AudioStatus' => $param['type'] == 6 ? 2 : 1
                    ]);
                }
                // 如果是语音消息
                if ($param['type'] == 6) {
                    Helper_Request::curl($silk2mp3Server, ['Url' => $param['content'], 'MsgId' => $id], true);
                }

                if ($param['ChatroomID'] != '') {
                    $groupMsgNum[$param['ChatroomID']]['LastMsgID'] = $id;
                } else {
                    $friendMsgNum[$param['WxAccount']]['LastMsgID'] = $id;
                }
            }
            $wxfModel = new Model_Weixin_Friend();
            $needSyncWxFriends = [];
            foreach ($friendMsgNum as $wxAccount => $val) {
                $wxf = $wxfModel->fetchRow(['WeixinID = ?' => $wxId, 'Account = ?' => $wxAccount]);
                if ($wxf) {
                    $wxf->UnreadNum += $val['Num'];
                    $wxf->LastMsgID = $val['LastMsgID'];
                    $wxf->save();
                } else {
                    // 设置最后一条id
                    $redis = Helper_Redis::getInstance();
                    $redisKey = Helper_Redis::lastMsgIdKey();
                    $hashKey = $wxId . '_' . $wxAccount;
                    $redis->hSet($redisKey, $hashKey, $val['LastMsgID']);

                    $needSyncWxFriends[] = $wxAccount;
                }
            }
            $needSyncWxFriends = array_unique($needSyncWxFriends);
            if ($needSyncWxFriends) {
                $taskModel = new Model_Task();
                $taskModel->addCommonTask(TASK_CODE_WEIXIN_FRIEND, $wxId, json_encode(['Weixin' => $needSyncWxFriends]));
            }
            foreach ($groupMsgNum as $groupId => $val) {
                // todo:
            }
        } catch (\Exception $e) {
            $this->showJson(0, '同步消息失败,err:'.$e->getMessage());
        }

        $this->showJson(1, '同步成功');
    }

    private function assignType($msg)
    {
        /**
        1	文字
        3	图片
        49	链接，内部type为5
        49	小程序，内部type为33
        436207665	红包
        43	视频
        419430449	转账
         */
        // 1:文本 2:图片 3:语音 4:链接 5:小程序 6:视频 7:红包
        switch($msg['type']) {
            case 1:
                $msg['type'] = 1;
                break;
            case 3:
                $msg['type'] = 2;
                if (substr($msg['content'], 0, 4) == 'img_') {
                    $msg['content'] = substr($msg['content'], 4);
                } else {
                    if (substr($msg['content'], 0, 4) !== 'http') {
                        $msg['type'] = 1;
                        $msg['content'] = '这是一条图片消息';
                    }
                }
                break;
            case 34:
                $msg['type'] = 6;
                // 需要转换语音
                $msg['AudioStatus'] = 2;
//                $msg['content'] = '语音消息:' . $msg['content'];
                break;
            case 43:
                $msg['type'] = 3;
                $msg['type'] = 1;
                $msg['content'] = '这是一条视频消息';
                break;
            // 自定义表情
            case 47:
                $msg['type'] = 2;
                $msg['IsBigImg'] = 'Y';
                $matchNum = preg_match('/cdnurl=\"(.*?)\"/', $msg['content'], $matches);
                if ($matchNum > 0) {
                    $imgUrl = strtr($matches[1], ['*#*' => ':']);
                    $qiniuUrl = DM_Qiniu::uploadImage($imgUrl, 1);
                    if ($qiniuUrl === '') {
                        $msg['content'] = '这是一条自定义表情消息';
                    } else {
                        $msg['content'] = $qiniuUrl;
                    }
                } else {
                    $msg['type'] = 1;
                    $msg['content'] = '这是一条自定义表情消息';
                }
                break;
            case 49:
                $arr = Helper_Until::xmlToArray($msg['content']);
                if (isset($arr['appmsg']['type'])) {
                    if ($arr['appmsg']['type'] == 5) {
                        $msg['type'] = 4;
                        if (isset($arr['appmsg']['url'])) {
                            $msg['content'] = "<a href='". htmlspecialchars_decode($arr['appmsg']['url']) . "' target='_blank'>" . (isset($arr['appmsg']['title']) ? $arr['appmsg']['title'] : '未知标题') . "</a>";
                        } else {
                            $msg['content'] = '这是一条不完整的链接消息';
                        }
                    } elseif ($arr['appmsg']['type'] == 33) {
                        $msg['type'] = 5;
                        if (isset($arr['appmsg']['sourcedisplayname'])) {
                            $msg['content'] = '小程序名称:' . $arr['appmsg']['sourcedisplayname'];
                        } else {
                            $msg['content'] = '这是一条不完整的小程序消息';
                        }
                    } else {
                        $msg['type'] = 4;
                        $msg['content'] = '这是一条不完整的链接或小程序消息';
                    }
                } else {
                    $msg['type'] = 4;
                    $msg['content'] = '这是一条不完整的链接或小程序消息';
                }
                break;
            case 436207665:
                $msg['type'] = 7;
                $msg['content'] = '这是一条红包消息';
                break;
            case 419430449:
                $msg['type'] = 8;
                $msg['content'] = '这是一条转账消息';
                break;
            default:
                $msg['type'] = 1;
                break;
        }

        return $msg;
    }

    /**
     * 最后一条消息
     */
    public function lastMsgAction()
    {
        $wx = trim($this->_getParam('Weixin', ''));
        if ($wx === '') {
            $this->showJson(0, 'Weixin必填');
        }

        $weixin = (new Model_Weixin())->fetchRow(['Weixin = ?' => $wx]);
        if (!$weixin) {
            $this->showJson(0, 'Weixin非法');
        }

        $m = new Model_Message();
        $select = $m->select()
            ->where("ReceiverWx = ? or SenderWx = ?", $wx)
            ->where("WxMsgSvrId != ''")
            ->order('WxCreateTime desc')->limit(1);
        $row = $m->fetchRow($select);
        if (!$row) {
            $data = ['createTime' => '', 'msgSvrId' => ''];
        } else {
            $data = ['createTime' => $row->WxCreateTime, 'msgSvrId' => $row->WxMsgSvrId];
        }

        $this->showJson(1, '操作成功', $data);
    }

    /**
     * 客户端上报大图
     */
    public function bigImgAction()
    {
        $weixin = trim($this->_getParam('Weixin', ''));
        $wxMsgSvrId = trim($this->_getParam('SvrId', ''));
        $bigImg = trim($this->_getParam('BigImg', ''));
        if ($wxMsgSvrId === '') {
            $this->showJson(0, '消息serid非法');
        }
        if ($weixin === '') {
            $this->showJson(0, 'weixin非法');
        }
        if ($bigImg === '') {
            $this->showJson(0, '图片非法');
        }

        $model = new Model_Message();
        $s = $model->select()->where('WxMsgSvrId = ?', $wxMsgSvrId)->forUpdate(true);
        $message = $model->fetchRow($s);
        if (!$message) {
            $this->showJson(0, '消息不存在');
        }
        if ($message['MsgType'] != 2) {
            $this->showJson(0, '非图片消息');
        }

        try {
            $message->Content = $bigImg;
            $message->IsBigImg = 'Y';
            $message->save();
        } catch (\Exception $e) {
            $this->showJson(0, '操作失败,err:'.$e->getMessage());
        }

        try {
            // {"TaskType":"MsgBigImg", "Result":{"MessageID":"1","BigImg":"http://xxx"}}
            $data = [
                'MessageID' => $message['MessageID'],
                'BigImg' => $bigImg
            ];
            $response = json_encode(['TaskType' => TASK_CODE_MSG_BIG_IMG, 'Result' => $data]);
            // 发送给web端
            Helper_Gateway::initConfig()->sendToUid($weixin, $response);

            // 发送给手机端
            $wx = Model_Weixin::getInstance()->fromSlaveDB()->fetchRow(['Weixin = ?' => $weixin]);
            $adminIds = explode(',', $wx['YyAdminID']);
            foreach ($adminIds as $aId) {
                Helper_Gateway::initConfig()->sendToUid($aId, $response);
            }
        } catch (\Exception $e) {
            $this->showJson(1, '更新成功,下发socket失败,err:'.$e->getMessage());
        }

        $this->showJson(1, '操作成功');
    }

    /**
     * 欢迎语
     */
    public function welcomeAction()
    {
        $weixin = trim($this->_getParam('Weixin', ''));
        if ($weixin === '') {
            $this->showJson(0, 'weixin非法');
        }

        $this->showJson(1, '你好');
    }

    /**
     * 音频转换后通知
     */
    public function audioNotifyAction()
    {
        $notifyData = file_get_contents("php://input");
//        $notifyData = '{"f":1,"m":"音频转换成功","d":{"AudioName":"http://wxgroup-img.duomai.com/54def51e44219531a45446fcb41e460e.mp3","MsgId":"6"},"e":null}';
        $data = json_decode($notifyData, 1);
        if (json_last_error() == JSON_ERROR_NONE && isset($data['f']) && $data['f'] == 1
            && isset($data['d']) && isset($data['d']['MsgId']) && isset($data['d']['AudioName']))
        {
            $msgId = (int)$data['d']['MsgId'];
            $audioMp3 = trim($data['d']['AudioName']);
            $audioText = trim(isset($data['d']['AudioText']) ? $data['d']['AudioText'] : '');
            if ($msgId > 0 && ($audioMp3 !== '' || $audioText !== '')) {
                $msg = Model_Message::getInstance()->getByPrimaryId($msgId);

                if ($msg && $msg->MsgType == 6 && $msg->AudioStatus == 2) {
                    $msg->AudioStatus = 3;
                    $msg->AudioMp3 = $audioMp3;
                    $msg->AudioText = $audioText;
                    $msg->save();
                }
            }
        }
    }
}

