<?php
/**
 * 手机添加微信
 */
class TaskRun_PhoneAddWx extends DM_Daemon
{   
    const CRON_SLEEP = 1000000;
	const SERVICE='phoneAddWx';
	
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
        // {"WxCateID\":6,\"WxCateName\":\"\后\台\",\"PhoneCateName\":\"test\",\"PhoneCateID\":7,\"DayNum\":2,\"PhoneNum\":100,\"FriendNum\":15,\"CopyWriting\":\"\",\"SendTime\":[\"00:15\",\"01:00\"]}
        $taskModel = new Model_Task();
        $phoneModel = new Model_Phones();
        $sendWxModel = new Model_Sendweixin();
        $weixinFriendModel = new Model_Weixin_Friend();
        $phoneAddWxs = $taskModel->getPhoneAddWxTask();

        foreach ($phoneAddWxs as $paw) {

            try {
                $taskModel->getAdapter()->beginTransaction();

                $taskConfig = json_decode($paw['TaskConfig'], 1);
                $wxCateId = $taskConfig['WxCateID'];

                // 根据微信 cateId 获取微信
                $onlineWxIds = (new Model_Device())->findOnlineWeixin();
                if (empty($onlineWxIds)) {
                    continue;
                }
                $weixins = (new Model_Weixin())->fetchAll(['find_in_set(?, CategoryIds)' => $wxCateId, 'WeixinID in (?)' => $onlineWxIds]);
                // 引入 maxPhoneId 主要是因为在循环中查询不锁表,
                // 因为在没commit之前, 循环中不锁表读出来的 phones 可能都是相同的.
                // 显然, phones 表很大的时候, 循环锁表的性能开销大
                $maxId = 0;

                foreach ($weixins as $weixin) {

                    // 区分手机添加好友和微信添加好友
                    switch ($paw['TaskCode']){

                        // 手机号添加好友
                        case TASK_CODE_PHONE_ADD_WX:
                            $Nums = $Ids = [];

                            $sendSelect = $phoneModel->select()->where('CategoryID = ?', $taskConfig['PhoneCateID'])
                                ->where('PhoneID > ?', $maxId)
                                ->where('FriendsState = ?', 0)
                                ->where('Detection = 1')
                                ->where('WeixinState in (?)', [1,3])
                                ->order('WeixinState ASC')
                                ->order('PhoneID ASC')
                                ->limit($taskConfig['PhoneNum']);
                            $sends = $phoneModel->fetchAll($sendSelect);

                            // 获取手机号
                            foreach ($sends as $s) {
                                $Nums[] = $s['Phone'];
                                $Ids[] = $s['PhoneID'];
                                $maxId = $s['PhoneID'];
                            }

                            if (!empty($Nums)) {
                                $childTaskConfigs = [
                                    'Phones' => $Nums,
                                    'Weixin' =>$weixin['Weixin'],
                                    'AddNum' =>$taskConfig['FriendNum'],
                                    'CopyWriting' =>$taskConfig['CopyWriting']
                                ];
                                (new Model_Task())->insert([
                                    'WeixinID' => $weixin['WeixinID'],
                                    'TaskCode' => TASK_CODE_FRIEND_JOIN,
                                    'TaskConfig' => json_encode($childTaskConfigs),
                                    'MaxRunNums' => 1,
                                    'AlreadyNums' => 0,
                                    'TaskRunTime' => '',
                                    'NextRunTime' => date('Y-m-d H:i:s'),
                                    'LastRunTime' => '0000-00-00 00:00:00',
                                    'Status' => TASK_STATUS_NOTSTART,
                                    'ParentTaskID' => $paw['TaskID'],
                                    'IsSendClient' => 'Y'
                                ]);
                                // 更新手机号状态
                                $phoneModel->update(['FriendsState' => 1], ['PhoneID in (?)' => $Ids]);
                            }

                        break;

                        // 微信号添加好友
                        case TASK_CODE_WEIXIN_ADD_WX:

                            $sendSelect = $sendWxModel->select()->where('CategoryID = ?', $taskConfig['SendWxCateID'])
                                ->where('SendWeixinID > 0')
                                ->where('Status = ?', 0)
                                ->order('SendWeixinID ASC')
                                ->limit($taskConfig['SendWxNum']);
                                $sends = $sendWxModel->fetchAll($sendSelect);

                            // 可发送的微信号 逐条发送
                            foreach ($sends as $k=>$s) {
                                $Nums = $Ids = [];
                                $WeixinData['Wx'] = $s['Weixin'];
                                $WeixinData['Type'] = (int)$s['Type'];

                                // 判断数据库中是否有v1,v2可以发送
                                $tmpWxNum = $sendWxModel->findWeixin('',$s['Weixin']);
                                if ($tmpWxNum == false || $tmpWxNum['V2'] == null){
                                    $tmpWxNum = $phoneModel->findWeixin($s['Weixin']);
                                }
                                if ($tmpWxNum == false || $tmpWxNum['V2'] == null){
                                    $tmpWxNum = $weixinFriendModel->findAccount($s['Weixin']);
                                }
                                $WeixinData['V1'] = empty($tmpWxNum['V1'])?null:$tmpWxNum['V2'];
                                $WeixinData['V2'] = empty($tmpWxNum['V2'])?null:$tmpWxNum['V2'];

                                $Nums[] = $WeixinData;

                                $Ids[] = $s['SendWeixinID'];

                                if (!empty($Nums)) {
                                    $childTaskConfigs = [
                                        'SendWeixins' => $Nums,
                                        'Weixin' =>$weixin['Weixin'],
                                        'AddNum' =>$taskConfig['FriendNum'],
                                        'CopyWriting' =>$taskConfig['CopyWriting']
                                    ];
                                    (new Model_Task())->insert([
                                        'WeixinID' => $weixin['WeixinID'],
                                        'TaskCode' => TASK_CODE_WXFRIEND_JOIN,
                                        'TaskConfig' => json_encode($childTaskConfigs),
                                        'MaxRunNums' => 1,
                                        'AlreadyNums' => 0,
                                        'TaskRunTime' => '',
                                        'NextRunTime' => date('Y-m-d H:i:s', strtotime('+ '.($k*6*60).' seconds')),
                                        'LastRunTime' => '0000-00-00 00:00:00',
                                        'Status' => TASK_STATUS_NOTSTART,
                                        'ParentTaskID' => $paw['TaskID'],
                                        'IsSendClient' => 'Y'
                                    ]);
                                    // 更新手机号状态
                                    $sendWxModel->update(['Status' => 1], ['SendWeixinID in (?)' => $Ids]);
                                }
                            }
                        break;
                    }

                }


                // 更新任务状态
                $task = $taskModel->fetchRow(['TaskID = ?' => $paw['TaskID']]);
                $task->Status = TASK_STATUS_START;
                $task->LastRunTime = date('Y-m-d H:i:s');
                $nextRunTime = $taskModel::getNextRunTime(json_decode($task->TaskRunTime, 1), $task->LastRunTime);
                if ($nextRunTime > ($task->EndTime . ' 23:59:59')) {
                    $nextRunTime = '0000-00-00 00:00:00';
                    $task->Status = TASK_STATUS_FINISHED;
                }
                $task->NextRunTime = $nextRunTime;
                $task->AlreadyNums += 1;
                $task->save();

                $taskLogModel = new Model_Task_Log();
                //加入日志
                $taskLogModel->add($task->TaskID, 0, STATUS_NORMAL, "任务已派生子任务");

                $taskModel->getAdapter()->commit();
            } catch (\Exception $e) {
                $taskModel->getAdapter()->rollBack();
                self::getLog()->add('cron generate child task err:' . $e->__toString());
                self::getLog()->flush();
            }
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
