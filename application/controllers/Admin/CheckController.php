<?php

require_once APPLICATION_PATH . '/controllers/Admin/AdminBase.php';

class Admin_CheckController extends AdminBase
{
    /**
     * 微信添加手机编辑
     */
    public function wxAddPhoneEditAction()
    {
        $phones = trim($this->_getParam('Phones'));
        if ($phones === '') {
            $this->showJson(self::STATUS_FAIL, '检测账号必填');
        }
        $weixins = trim($this->_getParam('Weixins'));
        if ($weixins === '') {
            $this->showJson(self::STATUS_FAIL, '微信号必填');
        }
        //1:微信,2:QQ,3:手机
        $type = (int)$this->_getParam('Type', 0);
        if (!in_array($type, [1, 2, 3])) {
            $this->showJson(self::STATUS_FAIL, '类型非法');
        }

        $tmpPhones = [];
        $phones = explode(',', $phones);
        foreach ($phones as $p) {
            $p = trim($p);
            if ($p !== '') {
                if ($type == 3 && !Helper_Regex::isPhone($p)) {
                    $this->showJson(self::STATUS_FAIL, '手机号非法');
                }
                $tmpPhones[] = $p;
            }
        }

        $tmpWeixins = [];
        $weixins = explode(',', $weixins);
        foreach ($weixins as $weixin) {
            $weixin = trim($weixin);
            if ($weixin !== '') {
                $tmpWeixins[] = $weixin;
            }
        }

        if (count($tmpWeixins) != count($tmpPhones) || !$tmpWeixins || !$tmpPhones) {
            $this->showJson(self::STATUS_FAIL, '手机号/微信号非法');
        }

        if (Model_WeixinCheck::getInstance()->fromSlaveDB()->fetchRow(Model_WeixinCheck::getInstance()->select()->where('Weixin in (?)', $tmpWeixins))) {
            $this->showJson(self::STATUS_FAIL, '存在用过的微信');
        }

        $onlineWeixinIds = Model_Device::getInstance()->fromSlaveDB()->findOnlineWeixin();
        if (empty($onlineWeixinIds)) {
            $this->showJson(self::STATUS_FAIL, '没有找到在线微信');
        }

        $weixinsInDb = Model_Weixin::getInstance()->fromSlaveDB()->fetchAll(['Weixin in (?)' => $tmpWeixins, 'WeixinID in (?)' => $onlineWeixinIds])->toArray();
        if (count($weixinsInDb) != count($tmpPhones)) {
            $this->showJson(self::STATUS_FAIL, '存在非法微信');
        }

        $time = date('Y-m-d H:i:s');
        try {
            Model_WeixinCheck::getInstance()->fromMasterDB()->getAdapter()->beginTransaction();
            foreach ($weixinsInDb as $index => $tmpWeixin) {
                // {"SendWeixins":[{"Wx":"chic2895","V1":null,"V2":null}],"Weixin":"wxid_cl7dqd61novc22","AddNum":1,"CopyWriting":"N"}
                // {"SendWeixins":[{"Wx":"13175057575","V1":null,"V2":null}],"Weixin":"wxid_f5st18424kcf22","AddNum":1,"CopyWriting":""}
                $childTaskConfigs = [
                    'SendWeixins' => [['Wx' => $tmpPhones[$index], 'V1' => null, 'V2' => null, 'Type' => $type]],
                    'Weixin' => $tmpWeixin['Weixin'],
                    'AddNum' => 1,
                    'CopyWriting' => ''
                ];
                $taskId = Model_Task::getInstance()->fromMasterDB()->addCommonTask(TASK_CODE_WXFRIEND_JOIN, $tmpWeixin['WeixinID'], json_encode($childTaskConfigs), $this->getLoginUserId());

                Model_WeixinCheck::getInstance()->fromMasterDB()->insert([
                    'Phone' => $tmpPhones[$index],
                    'Weixin' => $tmpWeixin['Weixin'],
                    'CheckTime' => $time,
                    'TaskId' => $taskId,
                    'Type' => $type,
                ]);
            }
            Model_WeixinCheck::getInstance()->fromMasterDB()->getAdapter()->commit();
        } catch (\Exception $e) {
            Model_WeixinCheck::getInstance()->fromMasterDB()->getAdapter()->rollBack();
            $this->showJson(self::STATUS_FAIL, $e->getMessage());
        }

        $this->showJson(self::STATUS_OK, '操作成功');
    }

    /**
     * 微信添加手机的列表
     */
    public function wxAddPhoneListAction()
    {
        $page = $this->getParam('Page', 1);
        $pagesize = $this->getParam('Pagesize', 20);

        $select = Model_WeixinCheck::getInstance()->fromMasterDB()->select()->setIntegrityCheck(false)
            ->from(Model_WeixinCheck::getInstance()->getTableName() . ' as wc', ['wc.*'])
            ->joinLeft('weixins as w', 'wc.Weixin=w.Weixin', ['w.WeixinID', 'w.Nickname', 'w.AvatarUrl'])
            ->joinLeft('devices as d','w.DeviceID = d.DeviceID','d.SerialNum')
            ->order('TaskID desc');

        $res = Model_WeixinCheck::getInstance()->fromMasterDB()->getResult($select, $page, $pagesize);

        $this->showJson(self::STATUS_OK, 'ok', $res);
    }

    /**
     * 微信添加随机手机号
     */
    public function wxAddPhoneRandAction()
    {
        $num = (int)$this->_getParam('Num');
        if ($num < 1) {
            $this->showJson(self::STATUS_FAIL, '数量须大于0');
        }
        if ($num > 20) {
            $this->showJson(self::STATUS_FAIL, '数量须小于20');
        }

        $wxModel = Model_Weixin::getInstance();

        $onlineWeixinIds = Model_Device::getInstance()->fromSlaveDB()->findOnlineWeixin();
        if (empty($onlineWeixinIds)) {
            $this->showJson(self::STATUS_FAIL, '没有找到在线微信');
        }

        $checkSelect = Model_WeixinCheck::getInstance()->fromSlaveDB()->select()
            ->from(Model_WeixinCheck::getInstance()->getTableName(), ['Weixin']);

        $s = $wxModel->select()->from($wxModel->getTableName(), ['WeixinID', 'Weixin', 'Nickname', 'AvatarUrl'])
            ->where('Weixin not in (?)', $checkSelect)
            ->where('WeixinID in (?)', $onlineWeixinIds)
            ->limit($num);

        $weixins = $wxModel->fromSlaveDB()->fetchAll($s)->toArray();

        $this->showJson(self::STATUS_OK, 'ok', $weixins);
    }

    public function wxAddPhoneMarkAction()
    {

    }
}