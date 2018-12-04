<?php
/**
 * 消息转发服务
 */


class TaskRun_MsgTranSer extends DM_Daemon
{
    const CRON_SLEEP = 1000000;
    const SERVICE='msgTranSer';

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

        // 转发状态:N不转发WAIT待转发TRAN已转发ERR转发失败,
        $select = $msgModel->select()
            ->where('TranStatus = ?', 'WAIT')
            ->order('MessageID asc')->limit(1000);
        $messages = $msgModel->fetchAll($select);

        if (empty($messages)) {
            $this->getLog()->add('no wait tran messages');
            return;
        }

        // 初始化变量
        $receiverWxAccounts = [];
        $tranWeixinFriendUrls = [];

        // 取出所有接收/发出的消息
        foreach ($messages as $m) {
            if ($m['GroupID'] == 0) {
                $receiverWxAccounts[] = $m['ReceiverWx'];
            }
        }
        if (empty($receiverWxAccounts)) {
            return;
        }
        $receiverWxAccounts = array_unique($receiverWxAccounts);
        $s = $wxModel->select()->where('Weixin in (?)', $receiverWxAccounts)->where("FriendMsgTranUrl = ''");
        $wxAccounts = $wxModel->fetchAll($s);
        foreach ($wxAccounts as $wxa) {
            $tranWeixinFriendUrls[$wxa['Weixin']] = $wxa['FriendMsgTranUrl'];
        }
        // 发送消息
        foreach ($messages as $msg) {
            try {
                if ($msg['GroupID'] == 0) {
                    $receiverAccount = $msg['ReceiverWx'];

                    if (!isset($tranWeixinFriendUrls[$receiverAccount])) {
                        $msg->TranStatus = 'NOURL';
                        $this->getLog()->add('tran no url, msgId:'.$msg['MessageID']);
                    } else {
                        $url = $tranWeixinFriendUrls[$receiverAccount];
                        $msg->TranStatus = $this->tranMessage($url, $msg);
                    }
                    $msg->TranTime = date('Y-m-d H:i:s');
                    $msg->save();
                } else {

                }
            } catch (Exception $e) {
                $this->getLog()->add('error:'.$e->__toString());
            }

        }
    }

    /**
     * 转发消息
     * @return TRAN / ERR
     */
    private function tranMessage($url, $msg)
    {
        // todo:
        try {
            $this->getLog()->add('tran msg ok, msgId:'.$msg['MessageID']);

            // todo: 解析 三方url 返回的内容, 发送给客户端
        } catch (\Exception $e) {
            $this->getLog()->add('tran msg err, msgId:'.$msg['MessageID'].';err:'.$e->getMessage());
            return 'ERR';
        }
        return 'TRAN';
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
