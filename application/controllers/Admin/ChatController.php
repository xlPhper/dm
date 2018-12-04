<?php

require_once APPLICATION_PATH . '/controllers/Admin/AdminBase.php';

class Admin_ChatController extends AdminBase
{
    /**
     * 已读接口
     */
    public function readAction()
    {
        $weixin = trim($this->_getParam('Weixin', ''));
        $friendWeixin = trim($this->_getParam('FriendWx', ''));
        if ($weixin === '') {
            $this->showJson(self::STATUS_FAIL, '微信号必填');
        }
        if ($friendWeixin === '') {
            $this->showJson(self::STATUS_FAIL, '好友微信号必填');
        }

        $wx = (new Model_Weixin())->fetchRow(['Weixin = ?' => $weixin]);
        if (!$wx) {
            $this->showJson(self::STATUS_FAIL, '微信非法');
        }
        $wxFriend = (new Model_Weixin_Friend())->fetchRow(['WeixinID = ?' => $weixin['WeixinID'], 'Account = ?' => $friendWeixin]);
        if (!$wxFriend) {
            $this->showJson(self::STATUS_FAIL, '非微信好友关系');
        }

        try {
            $wxFriend->UnreadNum = 0;
            $wxFriend->save();
        } catch (\Exception $e) {
            $this->showJson(self::STATUS_FAIL, '操作失败,err:'.$e->getMessage());
        }
        
        $this->showJson(self::STATUS_OK, '操作成功');
    }

    /**
     * 最后的消息列表
     */
    public function lastMsgsAction()
    {
        $page = $this->_getParam('Page', 1);
        $pagesize = $this->_getParam('Pagesize', 100);

        // 参数: 微信号/管理员/标签1,标签2/在线状态(离/在线)/消息内容
        // 返回: 微信id/微信号/ 微信昵称/微信头像/好友id/好友微信号/好友头像/好友昵称/消息/时间/online
        $inputWeixins = trim($this->_getParam('Weixins', ''));
        $nickname = trim($this->_getParam('Nickname', ''));
        $adminId = intval($this->_getParam('AdminID'));
        $tagIds = trim($this->_getParam('TagIDs', ''));
        $online = trim($this->_getParam('Online', ''));
        $content = trim($this->_getParam('Content', ''));

        $weixins = [];
        if ($inputWeixins !== '') {
            $inputWeixins = explode(',', $inputWeixins);
            $weixins = array_unique($inputWeixins);
        }

        $mModel = new Model_Message();

        $wfColumns = ['wf.Avatar as FriendAvatar', 'wf.FriendID', 'wf.Account as FriendWx', 'wf.NickName as FriendNickname'];
        $select = $mModel->fromSlaveDB()->select()->from('weixin_friends as wf', $wfColumns)
            ->setIntegrityCheck(false)
            ->joinLeft('weixins as w', 'wf.WeixinID = w.WeixinID', ['w.AvatarUrl as Avatar', 'w.Nickname', 'w.WeixinID', 'w.Weixin'])
            ->joinLeft('messages as m', 'wf.LastMsgID = m.MessageID', ['m.Content', 'm.ReceiverWx', 'm.SenderWx', 'm.AddDate', 'm.MsgType'])
            ->where('wf.LastMsgID > 0');
        if ($weixins) {
            $select->where('w.Weixin in (?) or w.Alias in (?)', $weixins);
        }
        if ($nickname !== '') {
            $select->where('w.NickName like ?', '%'.$nickname.'%');
        }
        if ($adminId > 0) {
            $select->where('w.AdminID = ?', $adminId);
        }
        if ($tagIds !== '') {
            $tagIds = explode(',', $tagIds);
            $tagIds = array_unique($tagIds);
            $conditions = [];
            foreach ($tagIds as $tagId) {
                if ($tagId > 0) {
                    $conditions[] = 'find_in_set(' . $tagId . ', w.CategoryIds)';
                }
            }
            if ($conditions) {
                $select->where(implode(' or ', $conditions));
            }
        }

        $dModel = new Model_Device();
        $ons = $dModel->fromSlaveDB()->select()->from('devices',['OnlineWeixinID', 'SerialNum'])
            ->where("OnlineWeixinID > 0")
            ->where('Status = ?', 'RUNNING');
        $devices = $dModel->fetchAll($ons);
        $onlineWeixinIds = $onlineSerialNums = [];
        foreach ($devices as $device) {
            $dwxId = (int)$device['OnlineWeixinID'];
            $onlineWeixinIds[] = $dwxId;
            if ($dwxId > 0) {
                $onlineSerialNums[$dwxId] = $device['SerialNum'];
            }
        }
        if (in_array($online, ['Y', 'N'])) {
            if ($online == 'Y') {
                $select->where('w.WeixinID in (?)', $onlineWeixinIds);
            } else {
                $select->where('w.WeixinID not in (?)', $onlineWeixinIds);
            }
        }

        if ($content) {
            $select->where('m.Content like ?', '%'.$content.'%');
        }

        $select->order('wf.LastMsgID desc');

        $res = $mModel->getResult($select, $page, $pagesize);

        foreach ($res['Results'] as &$d) {
            if ($online == 'Y' || in_array($d['WeixinID'], $onlineWeixinIds)) {
                $d['Online'] = 'Y';
            } else {
                $d['Online'] = 'N';
            }
            if ($d['MsgType'] == 1 && mb_strlen($d['Content']) > 50) {
                $d['Content'] = mb_substr($d['Content'], 0, 50) . '...';
            }
            if (isset($onlineSerialNums[$d['WeixinID']])) {
                $d['SerialNum'] = $onlineSerialNums[$d['WeixinID']];
            } else {
                $d['SerialNum'] = '';
            }
        }

        $this->showJson(self::STATUS_OK, '操作成功', $res);
    }

    /**
     * 好友消息列表
     */
    public function friendMsgsAction()
    {
        $page = $this->_getParam('Page', 1);
        $pagesize = $this->_getParam('Pagesize', 20);

        $weixin = $this->_getParam('Weixin');
        $wx = (new Model_Weixin())->fetchRow(['Weixin = ?' => $weixin]);
        if (!$wx) {
            $this->showJson(self::STATUS_FAIL, '微信非法');
        }

        $friendWx = $this->_getParam('FriendWx');

        $wxFriend = (new Model_Weixin_Friend())->fetchRow(['WeixinID = ?' => $wx['WeixinID'], 'Account = ?' => $friendWx]);
        if (empty($wxFriend)) {
            $this->showJson(self::STATUS_FAIL, '微信'.$weixin.'与'.$friendWx.'不是好友');
        }

        $mModel = new Model_Message();
        $select = $mModel->fromSlaveDB()->select()
            ->where("(SenderWx = '{$weixin}' and ReceiverWx = '{$friendWx}' and GroupID = 0)")
            ->orWhere("(SenderWx = '{$friendWx}' and ReceiverWx = '{$weixin}' and GroupID = 0)")
            ->order('MessageID desc');

        $res = $mModel->getResult($select, $page, $pagesize);

        foreach ($res['Results'] as &$d) {
            if ($d['SenderWx'] != $friendWx) {
                $d['Nickname'] = $wx['Nickname'];
                $d['AvatarUrl'] = $wx['AvatarUrl'];
            } else {
                $d['Nickname'] = $wxFriend['NickName'];
                $d['AvatarUrl'] = $wxFriend['Avatar'];
            }
        }

        $this->showJson(self::STATUS_OK, '操作成功', $res);
    }

    /**
     * 回复消息
     */
    public function replyMsgAction()
    {
        $weixin = $this->_getParam('Weixin');
        $sendTo = $this->_getParam('SendTo');
        $content = trim($this->_getParam('Content'));
        $type = (int)$this->_getParam('MsgType');

        $device = (new Model_Device())->fetchRow(['OnlineWeixin = ?' => $weixin]);
        if (!$device) {
            $this->showJson(0, '请先上线手机端');
        }

        $mobileClientId = $device['ClientID'];
        $data = [
            'MessageID' => "",
            'ChatroomID' => "",
            'WxAccount' => $sendTo,
            'content' => $content,
            'type' => $type
        ];
        $response = json_encode(['TaskCode' => TASK_CODE_SEND_CHAT_MSG, 'Data' => $data]);
        $res = Helper_Gateway::initConfig()->sendToClient($mobileClientId, $response);
        if (!$res) {
            $this->showJson(0, '发送失败');
        }

        $this->showJson(1, '发送成功');
    }

    /**
     * 获取大图
     */
    public function bigImgAction()
    {
        $messageId = (int)$this->_getParam('MessageID');
        if ($messageId < 1) {
            $this->showJson(self::STATUS_FAIL, '消息id非法');
        }

        $model = new Model_Message();
        $message = $model->fetchRow(['MessageID = ?' => $messageId]);
        if (!$message) {
            $this->showJson(self::STATUS_FAIL, '消息不存在');
        }
        if ($message['MsgType'] != 2) {
            $this->showJson(self::STATUS_FAIL, '非图片消息');
        }
        if ($message['IsBigImg'] == 'Y') {
            $this->showJson(self::STATUS_OK, '已是大图,不用再次上传');
        }

        $weixin = $this->_getParam('Weixin');

        // 下发查看大图任务
        $device = (new Model_Device())->fetchRow(['OnlineWeixin = ?' => $weixin]);
        if (!$device) {
            $this->showJson(0, '请先上线手机端');
        }

        $mobileClientId = $device['ClientID'];
        $data = [
            'SvrId' => $message['WxMsgSvrId'],
            'Weixin' => $weixin
        ];
        $response = json_encode(['TaskCode' => TASK_CODE_MSG_BIG_IMG, 'Data' => $data]);
        $res = Helper_Gateway::initConfig()->sendToClient($mobileClientId, $response);
        if (!$res) {
            $this->showJson(0, '发送失败');
        }

        $this->showJson(1, '发送成功');

    }
}