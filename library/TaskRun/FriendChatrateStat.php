<?php
/**
 * Created by PhpStorm.
 * User: jakins
 * Date: 2018/11/6
 * Time: 17:06
 * 微信好友互动频率统计,每天凌晨执行
 */
class TaskRun_FriendChatrateStat extends DM_Daemon
{
    const CRON_SLEEP = 5000000;
    const SERVICE='friendChatrateStat';

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
        set_time_limit(0);
        try{
            $fmodel = Model_Weixin_Friend::getInstance();
            $mModel = Model_Message::getInstance();
            $pagesize = 2000;
            $page = 1;
            $flag = true;
            self::getLog()->add('----------start stat-----------')->flush();
            do {
                try{
                    $data = $fmodel->fromSlaveDB()->select()->where('WeixinID != 0')->order('FriendID Asc')->limitPage($page, $pagesize)->query()->fetchAll();
                    if(!$data){
                        $flag = false;
                    }else {
                        $fmodel->getFiled($data, 'WeixinID', 'weixins', 'Weixin');
                        foreach ($data as $friend){
                            if($friend['Account'] === '' || $friend['Weixin'] === ''){
                                continue;
                            }
                            if($friend['LastMsgID']){
                                //存在最后一条消息ID,则去查找最近一周每天是否有互动消息,客户发送消息
                                //0-2天无互动->高频,3-5天无互动-中频,6-7天无互动-低频
                                $chatRate = 1;
                                $chatNum = $mModel->fromSlaveDB()->select()->where('SenderWx = ?', $friend['Account'])->where('ReceiverWx = ?', $friend['Weixin'])->where('AddDate >= ?', date('Y-m-d H:i:s', strtotime('-7 days')))->where('AddDate < ?', date('Y-m-d 00:00:00'))->group('Date(AddDate)')->query()->rowCount();
                                if($chatNum >= 5){
                                    $chatRate = Model_Weixin_Friend::CHATRATE_HIGH;
                                }else if($chatNum >= 2){
                                    $chatRate = Model_Weixin_Friend::CHATRATE_MIDDLE;
                                }
                                $fmodel->fromMasterDB()->update(['ChatRate' => $chatRate], ['FriendID = ?' => $friend['FriendID']]);
                            }else{
                                //不存在最后一条消息ID,则从添加好友到现在都无互动
                                if($friend['ChatRate']){
                                    $fmodel->fromMasterDB()->update(['ChatRate' => Model_Weixin_Friend::CHATRATE_NONE], ['FriendID = ?' => $friend['FriendID']]);
                                }
                            }
                        }
                        $page++;
                    }
                } catch (Exception $e){
                    self::getLog()->add('deal error:'.$e->getMessage());
                }
            }while ($flag);
            self::getLog()->add('----------end stat-----------')->flush();
            die();
        } catch (Exception $e){
            self::getLog()->add('error:'.$e->getMessage());
        }
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
