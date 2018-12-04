<?php

require_once APPLICATION_PATH . '/controllers/Open/OpenBase.php';

class Open_ChatController extends OpenBase
{

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
            $this->showJson(self::STATUS_FAIL, '已经是大图,不用再次获取');
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

    /**
     * 聊天置顶
     */
    public function topAction()
    {
        $weixin = trim($this->_getParam('Weixin'));
        $friendAccount = trim($this->_getParam('FriendAccount'));
        $top = trim($this->_getParam('Top'));

        if (!in_array($top, ['Y', 'N'])) {
            $this->showJson(self::STATUS_FAIL, 'top非法');
        }
        if ($weixin === '') {
            $this->showJson(self::STATUS_FAIL, 'Weixin必填');
        }
        if ($friendAccount === '') {
            $this->showJson(self::STATUS_FAIL, 'FriendAccount必填');
        }

        $wx = Model_Weixin::getInstance()->fromSlaveDB()->fetchRow(['Weixin = ?' => $weixin]);
        $wxfModel = Model_Weixin_Friend::getInstance();
        if ($top == 'N') {
            try {
                $wxfModel->fromMasterDB()->update(['DisplayOrder' => 0], ['WeixinID = ?' => $wx['WeixinID'], 'Account = ?' => $friendAccount]);
                $this->showJson(self::STATUS_OK, '操作成功');
            } catch (\Exception $e) {
                $this->showJson(self::STATUS_FAIL, $e->getMessage());
            }
        } else {
            // 获取最大值
            $select = $wxfModel->fromSlaveDB()->select()
                ->from($wxfModel->getTableName(), ['max(DisplayOrder) as MaxDisplayOrder'])
                ->where('WeixinID = ?', $wx['WeixinID'])
                ->where('Account != ?', $weixin)
                ->where('Account != ?', $friendAccount);
            $wxf = $wxfModel->fetchRow($select);

            $maxDisplayOrder = $wxf ? (int)$wxf['MaxDisplayOrder'] : 0;
            $maxDisplayOrder += 1;

            try {
                $wxfModel->fromMasterDB()->update(['DisplayOrder' => $maxDisplayOrder], ['WeixinID = ?' => $wx['WeixinID'], 'Account = ?' => $friendAccount]);
                $this->showJson(self::STATUS_OK, '操作成功');
            } catch (\Exception $e) {
                $this->showJson(self::STATUS_FAIL, $e->getMessage());
            }
        }
    }
}