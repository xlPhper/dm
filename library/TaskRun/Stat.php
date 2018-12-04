<?php
/**
 * 统计每日好友数据
 *
 * by Tim
 */
class TaskRun_Stat extends DM_Daemon
{
    const CRON_SLEEP = 1000000;
    const SERVICE='stat';

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
        $stat_model = new Model_Stat();
        $phone_model = new Model_Phones();

        $send_weixin_model = new Model_Sendweixin();

        $day = date('Y-m-d',strtotime('-1 day'));

        $data = $stat_model->getAllByDate($day);

        // 手机号添加好友发送数
        foreach($data as $datum){

            if (!empty($datum['Weixin'])){
                $phoneSend = $phone_model->findSendNum($datum['Weixin'],$day);
                $weixinSend = $send_weixin_model->findSendNum($datum['Weixin'],$day);
                $phoneSendNum = count($phoneSend);
                $weixinSendNum = count($weixinSend);
                $weixin_num = 0;
                $unknown_num = 0;
                foreach ($phoneSend as $w){
                    switch ($w['WeixinState']){
                        case 1:
                            $weixin_num++;
                            break;
                        case 3:
                            $unknown_num++;
                            break;
                    }
                }
                $update = [
                    'PhSendWeixinNum' => $weixin_num,
                    'PhSendUnknownNum' => $unknown_num,
                    'PhSendFriendNum' => $phoneSendNum,
                    'WxSendFriendNum' => $weixinSendNum,
                    'SendFriendNum' => $phoneSendNum + $weixinSendNum,
                    'AdminID'   =>  $datum['AdminID'],
                ];
                $where = "StatID = '{$datum['StatID']}'";
                $stat_model->update($update, $where);
            }

        }
        exit();
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
