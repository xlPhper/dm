<?php

require_once APPLICATION_PATH . '/controllers/Admin/AdminBase.php';

class Admin_TaskController extends AdminBase
{
    /**
     * 暂停任务
     */
    public function pauseAction()
    {
        $taskId = (int)$this->_getParam('TaskID', 0);
        if ($taskId <= 0) {
            $this->showJson(self::STATUS_FAIL, '任务id非法');
        }
        $taskIds = explode(',',$taskId);
        foreach ($taskIds as $val){
            $task = (new Model_Task())->fetchRow(['TaskID = ?' => $val]);
            if (!$task) {
                $this->showJson(self::STATUS_FAIL, '任务id非法');
            }
            if ($task->Status != TASK_STATUS_START && $task->Status != TASK_STATUS_NOTSTART) {
                $this->showJson(self::STATUS_FAIL, '未开始或进行中的任务才能暂停');
            }
            //todo: 暂停子任务
            try {
                $task->Status = TASK_STATUS_PAUSE;
                $task->save();
                (new Model_Task_Log())->insert([
                    'TaskID' => $val,
                    'Status' => TASK_STATUS_PAUSE,
                    'Msg' => '暂停任务,操作人id:' . $this->getLoginUserId(),
                    'AddDate' => date('Y-m-d H:i:s')
                ]);
                $this->showJson(self::STATUS_OK, '暂停成功');
            } catch (\Exception $e) {
                $this->showJson(self::STATUS_FAIL, '暂停失败:' . $e->getMessage());
            }
        }

    }

    /**
     * 启动任务
     */
    public function startAction()
    {
        $taskId = (int)$this->_getParam('TaskID', 0);
        $nextRunTime = $this->_getParam('NextRunTime', null);
        if ($taskId <= 0) {
            $this->showJson(self::STATUS_FAIL, '任务id非法');
        }


        $taskIds = explode(',',$taskId);
        foreach ($taskIds as $val){
            $task = (new Model_Task())->fetchRow(['TaskID = ?' => $val]);
            if (!$task) {
                $this->showJson(self::STATUS_FAIL, '任务id非法');
            }
            if ($task->ParentTaskID == TASK_CODE_PHONE_ADD_WX || $task->TaskCode == TASK_CODE_REPORT_WXFRIENDS){
                $this->showJson(self::STATUS_FAIL, '此任务修改需要去对应的任务发布模块编辑');
            }
            if ($task->Status == TASK_STATUS_NOTSTART  && $task->Status == TASK_STATUS_SEND) {
                $this->showJson(self::STATUS_FAIL, '任务未开始或正在发送');
            }
            $time = floor((strtotime(date('Y-m-d H:i:s'))-strtotime($task->LastRunTime))/60);
            if ($time<30){
                $this->showJson(self::STATUS_FAIL, '运行中的任务执行时间少于30分钟不可重新执行');
            }
            //todo: 启动任务
            try {
                $task->MaxRunNums = $task->MaxRunNums+1;
                $task->NextRunTime = $nextRunTime;
                $task->LastRunTime = '0000-00-00 00:00:00';
                $task->Status = TASK_STATUS_NOTSTART;
                $task->save();
                (new Model_Task_Log())->insert([
                    'TaskID' => $val,
                    'Status' => TASK_STATUS_NOTSTART,
                    'Msg' => '重新启动任务,操作人id:' . $this->getLoginUserId(),
                    'AddDate' => date('Y-m-d H:i:s')
                ]);
                $this->showJson(self::STATUS_OK, '启动成功');
            } catch (\Exception $e) {
                $this->showJson(self::STATUS_FAIL, '启动失败:' . $e->getMessage());
            }
        }
    }

    /**
     * 删除任务
     */
    public function deleteAction()
    {
        $taskId = (int)$this->_getParam('TaskID', 0);
        if ($taskId <= 0) {
            $this->showJson(self::STATUS_FAIL, '任务id非法');
        }

        $taskIds = explode(',',$taskId);

        foreach ($taskIds as $val) {

            $task = (new Model_Task())->fetchRow(['TaskID = ?' => $val]);
            if (!$task) {
                $this->showJson(self::STATUS_FAIL, '任务id非法');
            }

            // todo: 删除任务
            try {
                $task->Status = TASK_STATUS_DELETE;
                $task->save();
                (new Model_Task_Log())->insert([
                    'TaskID' => $val,
                    'Status' => TASK_STATUS_DELETE,
                    'Msg' => '删除任务,操作人id:' . $this->getLoginUserId(),
                    'AddDate' => date('Y-m-d H:i:s')
                ]);
                $this->showJson(self::STATUS_OK, '删除成功');
            } catch (\Exception $e) {
                $this->showJson(self::STATUS_FAIL, '删除失败:' . $e->getMessage());
            }
        }

    }

    /**
     * 手机添加微信
     */
    public function phoneAddWxAction()
    {
        $params = $this->getValidPhoneWxParams();

        try {
            // 写入发送任务
            $taskModel = new Model_Task();
            $taskModel->insert([
                'WeixinID' => 0,
                'TaskCode' => TASK_CODE_PHONE_ADD_WX,
                'IsSendClient' => 'N',
                'TaskConfig' => $params['TaskConfig'],
                'AlreadyNums' => 0,
                'MaxRunNums' => $params['MaxRunNums'],
                'TaskRunTime' => $params['TaskRunTime'],
                'NextRunTime' => $params['NextRunTime'],
                'LastRunTime' => '0000-00-00 00:00:00',
                'Status' => TASK_STATUS_NOTSTART,
                'AddDate' => date('Y-m-d H:i:s'),
                'StartTime' => $params['StartTime'],
                'EndTime' => $params['EndTime']
            ]);
            $this->showJson(self::STATUS_OK, '添加成功');
        } catch (\Exception $e) {
            $this->showJson(self::STATUS_FAIL, '添加失败:' . $e->getMessage());
        }
    }


    /**
     * 编辑手机微信任务
     */
    public function phoneEditWxAction()
    {
        $taskId = $this->_getParam('TaskID', 0);
        if ($taskId <= 0) {
            $this->showJson(self::STATUS_FAIL, '任务id非法');
        }

        $task = (new Model_Task())->fetchRow(['TaskID = ?' => $taskId]);
        if (!$task) {
            $this->showJson(self::STATUS_FAIL, '任务id非法');
        }

        $params = $this->getValidPhoneWxParams();

        try {
            $task->TaskConfig = $params['TaskConfig'];
            $task->MaxRunNums = $params['MaxRunNums'];
            $task->TaskRunTime = $params['TaskRunTime'];
            $task->NextRunTime = $params['NextRunTime'];
            $task->StartTime = $params['StartTime'];
            $task->EndTime = $params['EndTime'];
            $task->save();
            $this->showJson(self::STATUS_OK, '编辑成功');
        } catch (\Exception $e) {
            $this->showJson(self::STATUS_FAIL, '编辑失败:' . $e->getMessage());
        }
    }

    /**
     * 手机微信详情
     */
    public function phoneWxDetailAction()
    {
        $taskId = $this->_getParam('TaskID', 0);
        if ($taskId <= 0) {
            $this->showJson(self::STATUS_FAIL, '任务id非法');
        }

        $task = (new Model_Task())->fetchRow(['TaskID = ?' => $taskId]);
        if (!$task) {
            $this->showJson(self::STATUS_FAIL, '任务id非法');
        }

        $taskData = $task->toArray();
        $taskData['TaskConfig'] = json_decode($taskData['TaskConfig'], 1);

        $this->showJson(self::STATUS_OK, '操作成功', $taskData);
    }

    /**
     * 手机添加微信好友的任务展示
     */
    public function phoneWxListAction()
    {
        $task_model = new Model_Task();
        // 更新已结束的任务状态, 虽然写在这里不合理, 但是不用再写一个脚本来检查
        $taskOvers = $task_model->fetchAll(['EndTime < ?' => date('Y-m-d'), 'EndTime != ?' => '0000-00-00', 'Status = ?' => TASK_STATUS_START]);
        $taskOverIds = [];
        foreach ($taskOvers as $over) {
            $taskOverIds[] = $over->TaskID;
        }
        if ($taskOverIds) {
            $task_model->update(['Status' => TASK_STATUS_FINISHED], ['TaskID in (?)' => $taskOverIds]);
        }

        $page = $this->getParam('Page', 1);
        $pagesize = $this->getParam('Pagesize', 100);
        $phone_cate = $this->getParam('PhoneCate', null);
        $weixin_cate = $this->getParam('WeixinCate', null);

        $task_model = new Model_Task();
        $phones_model = new Model_Phones();
        $weixin_model = new Model_Weixin();

        $select = $task_model->select()->from($task_model->getTableName().' as t',['TaskID','TaskConfig','TaskResult','LastRunTime','Status'])
            ->where('TaskCode = ?', TASK_CODE_PHONE_ADD_WX)
            ->where('Status != ?', TASK_STATUS_DELETE);
        if ($phone_cate){
            $select->where('TaskConfig like ?','%'.'"PhoneCateID":'.$phone_cate.'%');
        }
        if ($weixin_cate){
            $select->where('TaskConfig like ?','%'.'"WxCateID":'.$weixin_cate.'%');
        }
        $select->order('TaskID Desc');
        $res = $task_model->getResult($select, $page, $pagesize);

        // 分类标记微信列表
        $weixin_categorys = $weixin_model->findWeixins();
        $weixin_tags = [];

        foreach ($weixin_categorys as $w){
            $weixin_tags[$w['CategoryID']]['Name'] = $w['Name'];
            $weixin_tags[$w['CategoryID']]['Num'] = $w['Num'];
        }

        $phone_categorys = $phones_model->getPhonesAll();
        $phone_tags = [];

        foreach ($phone_categorys as $p){
            $phone_tags[$p['CategoryID']]['Name'] = $p['Name'];
            $phone_tags[$p['CategoryID']]['Num'] = $p['Num'];
        }
        foreach ($res['Results'] as &$r) {
            $TaskConfig = json_decode($r['TaskConfig'], 1);
            $r['DayNum'] = $TaskConfig['DayNum'];
            $r['FriendNum'] = $TaskConfig['FriendNum'];
            $r['SendTime'] = $TaskConfig['SendTime'];

            $TaskResult = json_decode($r['TaskResult'], 1);

            if ($TaskResult){
                $r['SendSuccess'] = $TaskResult['SendSuccess'];
                $r['Consume'] = $TaskResult['SendNum'];
            }else{
                $r['SendSuccess'] = 0;
                $r['Consume'] = 0;
            }
            // 手机标签
            if ($TaskConfig['PhoneCateID']){
                $r['PhonesCate']['Name'] = empty($phone_tags[$TaskConfig['PhoneCateID']]['Name'])?'':$phone_tags[$TaskConfig['PhoneCateID']]['Name'];
                $r['PhonesCate']['Num'] = empty($phone_tags[$TaskConfig['PhoneCateID']]['Num'])?0:$phone_tags[$TaskConfig['PhoneCateID']]['Num'];
            }else{
                $r['PhonesCate']['Name'] = '';
                $r['PhonesCate']['Num'] = 0;
            }
            if ($TaskConfig['WxCateID']){
                $r['WeixinCate']['Name'] = empty($weixin_tags[$TaskConfig['WxCateID']]['Name'])?'':$weixin_tags[$TaskConfig['WxCateID']]['Name'];
                $r['WeixinCate']['Num'] = empty($weixin_tags[$TaskConfig['WxCateID']]['Num'])?0:$weixin_tags[$TaskConfig['WxCateID']]['Num'];
            }else{
                $r['WeixinCate']['Name'] = '';
                $r['WeixinCate']['Num'] = 0;
            }


        }
        $this->showJson(self::STATUS_OK, '操作成功', $res);
    }

    /**
     * 手机添加微信好友的任务详情
     */
    public function phoneWxInfoAction()
    {
        $page = $this->getParam('Page', 1);
        $pagesize = $this->getParam('Pagesize', 100);
        $errorCode = $this->_getParam('ErrorCode',null);
        $sendState = $this->_getParam('SendState',null);
        $friendsState = $this->_getParam('FriendsState',null);
        $taskId = $this->_getParam('TaskID',null);

        if ($taskId == null){
            $this->showJson(self::STATUS_FAIL, '非法的任务ID');
        }

        // Model
        $taskModel = new Model_Task();
        $phoneModel = new Model_Phones();

        $parentTask = $taskModel->findFromSlaveTaskID($taskId);
        $parentTaskConfig = json_decode($parentTask['TaskConfig'],1);
        $phoneNum = $parentTaskConfig['PhoneNum'];
        $chuildPagesize = ceil($pagesize/$phoneNum);

        $childs = $taskModel->findPhoneAddWxChild($taskId,$page,$chuildPagesize);

        $phoneLasrRunTime = [];
        if ($childs){

            try{
                // 查询出任务中手机号
                $phones = $res =  [];
                foreach ($childs as $c){
                    $taskConfig = json_decode($c['TaskConfig'],1);
                    foreach ($taskConfig['Phones'] as $p){
                        $phoneLasrRunTime[$p]=$c['LastRunTime'];
                    }
                    // 去重
                    $phones = array_keys(array_flip($phones)+array_flip($taskConfig['Phones']));
                }

                $phoneData = $phoneModel->getSendWeixin($phones,$page,$pagesize,$errorCode,$sendState,$friendsState);

                foreach ($phoneData['Results'] as &$p){
                    if ($p['SendError']){
                        if (strpos($p['Error'], 'java.lang.String java.lang.Class.getName()') === false) {
                            $p['SendError'] = '未知错误';
                        } else {
                            $p['SendError'] = '当前微信号版本与群控不匹配';
                        }
                    }

                    switch ($p['FriendsState']){
                        case 0:
                            $p['SendMessage'] = '未发送请求';
                            break;
                        case 1:
                            $p['SendMessage'] = '对方未通过好友请求';
                            break;
                        case 2:
                            $p['SendMessage'] = '该手机号已是好友';
                            break;
                        case 3:
                            $p['SendMessage'] = $p['SendError'];
                            break;
                        case 4:
                            $p['SendMessage'] = '好友添加成功';
                            break;
                    }
                    if ($p['Phone']){
                        $p['LastRunTime'] = $phoneLasrRunTime[$p['Phone']];
                    }else{
                        $p['LastRunTime'] = '0000-00-00 00:00:00';
                    }
                }

                $this->showJson(self::STATUS_OK, '操作成功', $phoneData);
            }catch (Exception $e){
                $this->showJson(0,'抛出异常'.$e->getMessage());
            }

        }else{
            $this->showJson(self::STATUS_OK, '操作成功', []);
        }

    }

    public function phoneWxCodeAction()
    {

        $this->showJson(self::STATUS_OK, '', detectionPhoneErrorCode);
    }

    /**
     * 获取合法的手机添加微信参数
     */
    private function getValidPhoneWxParams()
    {
        $wxCateId = (int)$this->_getParam('WxCateID', 0);
        if ($wxCateId <= 0) {
            $this->showJson(self::STATUS_FAIL, 'WxCateID非法');
        }
        $phoneCateId = (int)$this->_getParam('PhoneCateID', 0);
        if ($phoneCateId <= 0) {
            $this->showJson(self::STATUS_FAIL, 'PhoneCateID非法');
        }

        if ((new Model_Task())->fetchRow([
            'TaskRelateFlag = ?' => $wxCateId.'_'.$phoneCateId,
            'TaskCode = ?' => TASK_CODE_PHONE_ADD_WX,
            'Status != ?' => TASK_STATUS_DELETE
        ])) {
            $this->showJson(self::STATUS_FAIL, '已存在此分类任务,请先删除');
        }

        $cateMode = new Model_Category();
        $wxCate = $cateMode->getCategoryByIdType($wxCateId, CATEGORY_TYPE_WEIXIN);
        if (!$wxCate) {
            $this->showJson(self::STATUS_FAIL, 'WxCateID不存在');
        }
        $phoneCate = $cateMode->getCategoryByIdType($phoneCateId, CATEGORY_TYPE_PHONE);
        if (!$phoneCate) {
            $this->showJson(self::STATUS_FAIL, 'PhoneCateID不存在');
        }

        $startTime = trim($this->_getParam('StartTime', date('Y-m-d')));
        $endTime = trim($this->_getParam('EndTime', date('Y-m-d')));
        if ($endTime < $startTime) {
            $this->showJson(self::STATUS_FAIL, '结束时间小于开始时间');
        }

        $dayNum = (int)$this->_getParam('DayNum', 0);
        if ($dayNum <= 0) {
            $this->showJson(self::STATUS_FAIL, '每天发送次数须 > 0');
        }
        $phoneNum = (int)$this->_getParam('PhoneNum', 0);
        if ($phoneNum <= 0) {
            $this->showJson(self::STATUS_FAIL, '每次发送手机数量须 > 0');
        }
        $friendNum = (int)$this->_getParam('FriendNum', 0);
        if ($friendNum <= 0) {
            $this->showJson(self::STATUS_FAIL, '每次添加好友数量须 > 0');
        }
        // 5:30,6:30
        $sendTime = $this->_getParam('SendTime', '');
        $sendTimeArr = explode(',', $sendTime);
        $validTime = [];
        foreach ($sendTimeArr as $st) {
            $sta = explode(':', $st);
            if (count($sta) == 2 && $sta[0] >= 0 && $sta[0] <= 23 && $sta[1] >= 0 && $sta[1] <= 59) {
                $validTime[] = $st;
            }
        }
        if (count($validTime) != $dayNum) {
            $this->showJson(self::STATUS_FAIL, '发送时间与发送次数不对应');
        }
        // 文案
        $copyWriting = trim($this->_getParam('CopyWriting', ''));

        $taskConfig = [
            'WxCateID' => $wxCateId,
            'WxCateName' => $wxCate['Name'],
            'PhoneCateName' => $phoneCate['Name'],
            'PhoneCateID' => $phoneCateId,
            'DayNum' => $dayNum,
            'PhoneNum' => $phoneNum,
            'FriendNum' => $friendNum,
            'CopyWriting' => $copyWriting,
            'SendTime' => $validTime,
            'TaskRelateFlag' => $wxCateId.'_'.$phoneCateId,
        ];

        list($runTimeResult, $runTimeConfig) = (new Model_Task())->taskRunTimeConfig('cron', DM_Crontab::getExpressionByHourMin($validTime));
        if (false === $runTimeResult) {
            $this->showJson(self::STATUS_FAIL, $runTimeConfig);
        }

        return [
            'TaskConfig' => json_encode($taskConfig),
            'MaxRunNums' => $runTimeConfig['MaxRunNums'],
            'TaskRunTime' => $runTimeConfig['TaskRunTime'],
            'NextRunTime' => $runTimeConfig['NextRunTime'],
            'StartTime' => $startTime,
            'EndTime' => $endTime
        ];
    }

    /**
     * 微信号添加微信
     */
    public function weixinAddWxAction()
    {
        $params = $this->getValidWeixinWxParams();

        try {
            // 写入发送任务
            $taskModel = new Model_Task();
            $taskModel->insert([
                'WeixinID' => 0,
                'TaskCode' => TASK_CODE_WEIXIN_ADD_WX,
                'IsSendClient' => 'N',
                'TaskConfig' => $params['TaskConfig'],
                'AlreadyNums' => 0,
                'MaxRunNums' => $params['MaxRunNums'],
                'TaskRunTime' => $params['TaskRunTime'],
                'NextRunTime' => $params['NextRunTime'],
                'LastRunTime' => '0000-00-00 00:00:00',
                'Status' => TASK_STATUS_NOTSTART,
                'AddDate' => date('Y-m-d H:i:s'),
                'StartTime' => $params['StartTime'],
                'EndTime' => $params['EndTime']
            ]);
            $this->showJson(self::STATUS_OK, '添加成功');
        } catch (\Exception $e) {
            $this->showJson(self::STATUS_FAIL, '添加失败:' . $e->getMessage());
        }
    }

    /**
     * 编辑微信微信任务
     */
    public function weixinEditWxAction()
    {
        $taskId = $this->_getParam('TaskID', 0);
        if ($taskId <= 0) {
            $this->showJson(self::STATUS_FAIL, '任务id非法');
        }

        $task = (new Model_Task())->fetchRow(['TaskID = ?' => $taskId]);
        if (!$task) {
            $this->showJson(self::STATUS_FAIL, '任务id非法');
        }

        $params = $this->getValidWeixinWxParams();

        try {
            $task->TaskConfig = $params['TaskConfig'];
            $task->MaxRunNums = $params['MaxRunNums'];
            $task->TaskRunTime = $params['TaskRunTime'];
            $task->NextRunTime = $params['NextRunTime'];
            $task->StartTime = $params['StartTime'];
            $task->EndTime = $params['EndTime'];
            $task->save();
            $this->showJson(self::STATUS_OK, '编辑成功');
        } catch (\Exception $e) {
            $this->showJson(self::STATUS_FAIL, '编辑失败:' . $e->getMessage());
        }
    }

    /**
     * 微信微信详情
     */
    public function weixinWxDetailAction()
    {
        $taskId = $this->_getParam('TaskID', 0);
        if ($taskId <= 0) {
            $this->showJson(self::STATUS_FAIL, '任务id非法');
        }

        $task = (new Model_Task())->fetchRow(['TaskID = ?' => $taskId]);
        if (!$task) {
            $this->showJson(self::STATUS_FAIL, '任务id非法');
        }

        $taskData = $task->toArray();
        $taskData['TaskConfig'] = json_decode($taskData['TaskConfig'], 1);

        $this->showJson(self::STATUS_OK, '操作成功', $taskData);
    }

    /**
     * 微信号添加微信好友的任务展示
     */
    public function weixinAddWxListAction()
    {
        $page = $this->getParam('Page', 1);
        $pagesize = $this->getParam('Pagesize', 100);
        $sendwx_cate = $this->getParam('SendWxCate', null);
        $weixin_cate = $this->getParam('WeixinCate', null);

        $task_model = new Model_Task();
        $sendwx_model = new Model_Sendweixin();
        $weixin_model = new Model_Weixin();

        $select = $task_model->select()->from($task_model->getTableName().' as t',['TaskID','TaskConfig','TaskResult','LastRunTime','Status'])
            ->where('TaskCode = ?', TASK_CODE_WEIXIN_ADD_WX)
            ->where('Status != ?', TASK_STATUS_DELETE);
        if ($sendwx_cate){
            $select->where('TaskConfig like ?','%'.'"SendWxCateID":'.$sendwx_cate.'%');
        }
        if ($weixin_cate){
            $select->where('TaskConfig like ?','%'.'"WxCateID":'.$weixin_cate.'%');
        }
        $select->order('TaskID Desc');
        $res = $task_model->getResult($select, $page, $pagesize);

        // 分类标记微信列表
        $weixin_categorys = $weixin_model->findWeixins();
        $weixin_tags = [];

        foreach ($weixin_categorys as $w){
            $weixin_tags[$w['CategoryID']]['Name'] = $w['Name'];
            $weixin_tags[$w['CategoryID']]['Num'] = $w['Num'];
        }

        $sendwx_categorys = $sendwx_model->getSendWeixinsAll();
        $sendwx_tags = [];


        foreach ($sendwx_categorys as $s){
            $sendwx_tags[$s['CategoryID']]['Name'] = $s['Name'];
            $sendwx_tags[$s['CategoryID']]['Num'] = $s['Num'];
        }


        foreach ($res['Results'] as &$r) {
            $TaskConfig = json_decode($r['TaskConfig'], 1);
            $TaskResult = json_decode($r['TaskResult'], 1);
            if ($TaskResult){
                $r['SendSuccess'] = $TaskResult['SendSuccess'];
                $r['Consume'] = $TaskResult['SendSuccess'];
            }else{
                $r['SendSuccess'] = 0;
                $r['Consume'] = 0;
            }


            $r['DayNum'] = $TaskConfig['DayNum'];
            $r['FriendNum'] = $TaskConfig['FriendNum'];
            $r['SendTime'] = $TaskConfig['SendTime'];

            if ($TaskResult){
                $r['SendSuccess'] = $TaskResult['SendSuccess'];
                $r['Consume'] = $TaskResult['SendNum'];
            }else{
                $r['SendSuccess'] = 0;
                $r['Consume'] = 0;
            }

            // 标签
            if ($TaskConfig['SendWxCateID']){
                $r['SendCate']['Name'] = empty($sendwx_tags[$TaskConfig['SendWxCateID']]['Name'])?'':$sendwx_tags[$TaskConfig['SendWxCateID']]['Name'];
                $r['SendCate']['Num'] = empty($sendwx_tags[$TaskConfig['SendWxCateID']]['Num'])?0:$sendwx_tags[$TaskConfig['SendWxCateID']]['Num'];
            }else{
                $r['SendCate']['Name'] = '';
                $r['SendCate']['Num'] = 0;
            }
            if ($TaskConfig['WxCateID']){
                $r['WeixinCate']['Name'] = empty($weixin_tags[$TaskConfig['WxCateID']]['Name'])?'':$weixin_tags[$TaskConfig['WxCateID']]['Name'];
                $r['WeixinCate']['Num'] = empty($weixin_tags[$TaskConfig['WxCateID']]['Num'])?0:$weixin_tags[$TaskConfig['WxCateID']]['Num'];
            }else{
                $r['WeixinCate']['Name'] = '';
                $r['WeixinCate']['Num'] = 0;
            }

        }
        $this->showJson(self::STATUS_OK, '操作成功', $res);
    }

    /**
     * 手机号添加好友详情
     */
    public function weixinDetailAction()
    {
        $page = $this->_getParam('Page', 1);
        $pagesize = $this->_getParam('Pagesize', 100);
        $ParentTaskID   = $this->_getParam('ParentTaskID');
        if(!$ParentTaskID)
        {
            $this->showJson(0,'非法的任务ID');
        }
        $taskModel          = new Model_Task();
        $sendWeixinModel    = new Model_Sendweixin();
        $weixinModel        = new Model_Weixin();

        $select = $taskModel->fromSlaveDB()->select()->from('tasks',['TaskID','TaskCode','TaskConfig','UpdateDate']);
        $select->where('ParentTaskID = ?',$ParentTaskID)->order('AddDate DESC');
        $res = $taskModel->getResult($select,$page,$pagesize);
        //找到父任务的分类id
        $cateSelect = $taskModel->fromSlaveDB()->select()->from('tasks',['TaskConfig']);
        $cateResult = $cateSelect->where('TaskID =?',$ParentTaskID)->query()->fetch();
        $cateConfig = (array)json_decode($cateResult['TaskConfig']);
        $cateId = $cateConfig['SendWxCateID'];
        $arr = $data = [];

        foreach ($res['Results'] as &$value)
        {
            $config = (array)json_decode($value['TaskConfig']);//组合配置信息

            if(isset($config['Weixin']) && in_array($config['Weixin'],$arr)){
                $value = $data[$config['Weixin']];
                continue;
            }else{
                $select = $sendWeixinModel->fromSlaveDB()->select();
                $select->where('Weixin = ?',$config['SendWeixins'][0]->Wx)
                        ->where('CategoryID = ?',$cateId);
                $result = $select->query()->fetch();

                switch ($result['Status']){
                    case 0 :
                        $errMsg  = '未发送请求';
                        break;
                    case 1 :
                        $errMsg  = '对方未通过好友请求';
                        break;
                    case 2 :
                        $message = strip_tags($result['Message']);
                        $message = str_replace(['State','{','}',' '],'',$message);
                        $message = str_replace('：',',',$message);
                        $message = explode(',',$message);
                        $Code = $message[0];
                        $Code= str_replace(['err_code=','\''],'',$Code);
                        switch ($Code){
                            case -1:
                                $errMsg = '微信搜索接口回调错误';
                                break;
                            case -24:
                                $errMsg = '操作频繁';
                                break;
                        }
                        break;
                    case 3:
                        $errMsg  = '好友添加成功';
                        break;
                }

                $selectWx = $weixinModel->fromSlaveDB()->select()->from('weixins',['WeixinID','Nickname','AvatarUrl']);
                $selectWx->where('Weixin = ?',$config['Weixin']);
                $wxResult = $selectWx->query()->fetch();
                $WxID = $wxResult['WeixinID'];

                $FriendModel = new Model_Weixin_Friend();
                $query = $FriendModel->fromSlaveDB()->select()->from('weixin_friends',['Avatar','NickName']);
                $query->where('WeixinID = ?',$WxID);
                $query->where('Account = ?',$result['Weixin']);
                $FriendResult = $query->query()->fetch();

                $value['SendWeixinID']  = $result['SendWeixinID'];//
                $value['WxId']          = $config['Weixin'];//添加的微信id
                $value['WxNickName']    = $wxResult['Nickname'];//微信昵称
                $value['AvatarUrl']     = $wxResult['AvatarUrl'];//头像
                $value['Phone']         = $config['SendWeixins'][0]->Wx??'';//导入的手机号
                $value['Message']       = $errMsg;//错误信息
                $value['AddAvatarUrl']  = $FriendResult?$FriendResult['Avatar']:'';
                $value['AddNickName']   = $FriendResult?$FriendResult['NickName']:'';
                $value['LastRunTime']   = $value['UpdateDate'];
                $value['Status']        = $result['Status'];//状态
                unset($value['TaskConfig']);
                unset($value['TaskCode']);
                unset($value['UpdateDate']);
                array_push($arr,$config['Weixin']);
                $data[$config['Weixin']] = $value;
            }

        }

        $this->showJson(self::STATUS_OK, '操作成功', $res);
    }


    /**
     * 获取合法的微信号添加微信参数
     */
    private function getValidWeixinWxParams()
    {
        $wxCateId = (int)$this->_getParam('WxCateID', 0);
        if ($wxCateId <= 0) {
            $this->showJson(self::STATUS_FAIL, 'WxCateID非法');
        }
        $sendWxCateId = (int)$this->_getParam('SendWxCateID', 0);
        if ($sendWxCateId <= 0) {
            $this->showJson(self::STATUS_FAIL, 'SendWxCateID非法');
        }

        if ((new Model_Task())->fetchRow([
            'TaskRelateFlag = ?' => $wxCateId.'_'.$sendWxCateId,
            'TaskCode = ?' => TASK_CODE_WEIXIN_ADD_WX,
            'Status != ?' => TASK_STATUS_DELETE
        ])) {
            $this->showJson(self::STATUS_FAIL, '已存在此分类任务,请先删除');
        }

        $cateMode = new Model_Category();
        $wxCate = $cateMode->getCategoryByIdType($wxCateId, CATEGORY_TYPE_WEIXIN);
        if (!$wxCate) {
            $this->showJson(self::STATUS_FAIL, 'WxCateID不存在');
        }

        $sendWexCate = $cateMode->getCategoryByIdType($sendWxCateId, CATEGORY_TYPE_SENDWEIXIN);
        if (!$sendWexCate) {
            $this->showJson(self::STATUS_FAIL, 'SendWxCateID不存在');
        }

        $startTime = trim($this->_getParam('StartTime', date('Y-m-d')));
        $endTime = trim($this->_getParam('EndTime', date('Y-m-d')));
        if ($endTime < $startTime) {
            $this->showJson(self::STATUS_FAIL, '结束时间小于开始时间');
        }

        $dayNum = (int)$this->_getParam('DayNum', 0);
        if ($dayNum <= 0) {
            $this->showJson(self::STATUS_FAIL, '每天发送次数须 > 0');
        }
        $sendWxNum = (int)$this->_getParam('SendWxNum', 0);
        if ($sendWxNum <= 0) {
            $this->showJson(self::STATUS_FAIL, '每次发送微信号数量须 > 0');
        }
        $friendNum = (int)$this->_getParam('FriendNum', 0);
        if ($friendNum <= 0) {
            $this->showJson(self::STATUS_FAIL, '每次添加好友数量须 > 0');
        }
        // 5:30,6:30
        $sendTime = $this->_getParam('SendTime', '');
        $sendTimeArr = explode(',', $sendTime);
        $validTime = [];
        foreach ($sendTimeArr as $st) {
            $sta = explode(':', $st);
            if (count($sta) == 2 && $sta[0] >= 0 && $sta[0] <= 23 && $sta[1] >= 0 && $sta[1] <= 59) {
                $validTime[] = $st;
            }
        }
        if (count($validTime) != $dayNum) {
            $this->showJson(self::STATUS_FAIL, '发送时间与发送次数不对应');
        }
        // 文案
        $copyWriting = trim($this->_getParam('CopyWriting', ''));

        $taskConfig = [
            'WxCateID' => $wxCateId,
            'WxCateName' => $wxCate['Name'],
            'SendWxCateName' => $sendWexCate['Name'],
            'SendWxCateID' => $sendWxCateId,
            'DayNum' => $dayNum,
            'SendWxNum' => $sendWxNum,
            'FriendNum' => $friendNum,
            'CopyWriting' => $copyWriting,
            'SendTime' => $validTime,
            'TaskRelateFlag' => $wxCateId.'_'.$sendWxCateId,
        ];

        list($runTimeResult, $runTimeConfig) = (new Model_Task())->taskRunTimeConfig('cron', DM_Crontab::getExpressionByHourMin($validTime));
        if (false === $runTimeResult) {
            $this->showJson(self::STATUS_FAIL, $runTimeConfig);
        }

        return [
            'TaskConfig' => json_encode($taskConfig),
            'MaxRunNums' => $runTimeConfig['MaxRunNums'],
            'TaskRunTime' => $runTimeConfig['TaskRunTime'],
            'NextRunTime' => $runTimeConfig['NextRunTime'],
            'StartTime' => $startTime,
            'EndTime' => $endTime
        ];
    }

    /**
     * 任务名称
     */
    public function taskNameAction()
    {
        $res = array();
        $taskCodes = TASK_CODE;
        foreach ($taskCodes as $k=>$v){
            if ($k != TASK_CODE_PHONE_ADD_WX && $k !=TASK_CODE_REPORT_WXFRIENDS && $k !=TASK_CODE_WEIXIN_ADD_WX){
                $res[] = [
                    'TaskName'=>$v,
                    'TaskCode'=>$k
                ];
            }
        }
        $this->showJson(1,'任务信息',$res);
    }

    /**
     * 任务状态
     */
    public function taskStatusAction()
    {
        $res = [
            array('Status'=>TASK_STATUS_NOTSTART,'StatusName'=>'未开始'),
            array('Status'=>TASK_STATUS_SEND,'StatusName'=>'已发送-未执行'),
            array('Status'=>TASK_STATUS_START,'StatusName'=>'运行中'),
            array('Status'=>TASK_STATUS_FINISHED,'StatusName'=>'任务完成'),
            array('Status'=>TASK_STATUS_UNUSUAL,'StatusName'=>'非正常完成'),
            array('Status'=>TASK_STATUS_PAUSE,'StatusName'=>'任务暂停'),
            array('Status'=>TASK_STATUS_FAILURE,'StatusName'=>'任务失败'),
            array('Status'=>TASK_STATUS_DELETE,'StatusName'=>'任务删除')
        ];
        $this->showJson(1,'任务信息',$res);
    }

    /**
     * 任务列表
     */
    public function listAction()
    {
        $task_model = new Model_Task();
        // 更新已结束的任务状态, 虽然写在这里不合理, 但是不用再写一个脚本来检查
        $taskOvers = $task_model->fetchAll(['EndTime < ?' => date('Y-m-d'), 'EndTime != ?' => '0000-00-00', 'Status = ?' => TASK_STATUS_START]);
        $taskOverIds = [];
        foreach ($taskOvers as $over) {
            $taskOverIds[] = $over->TaskID;
        }
        if ($taskOverIds) {
            $task_model->update(['Status' => TASK_STATUS_FINISHED], ['TaskID in (?)' => $taskOverIds]);
        }

        $page = $this->_getParam('Page',1);
        $pagesize = $this->_getParam('Pagesize',100);
        $category_id = $this->_getParam('CategoryID',null);
        $task_code = $this->_getParam('TaskCode',null);
        $weixin_name = $this->_getParam('WeixinName',null);
        $start_add_date = $this->_getParam('StartAddDate',null);
        $end_add_date = $this->_getParam('EndAddDate',null);
        $start_date = $this->_getParam('StartDate',null);
        $end_date = $this->_getParam('EndDate',null);
        $status = $this->_getParam('Status',null);
        $admin_id = $this->_getParam('AdminID',null);
        $parent_task_id = $this->_getParam('ParentTaskID',null);
        $taskIDs = $this->_getParam('TaskIDs', null);

        $admin_model = new Model_Role_Admin();
        $wModel = new Model_Weixin();

        $select = $task_model->fromSlaveDB()
            ->select()
            ->setIntegrityCheck(false)
            ->from($task_model->getTableName().' as t',['TaskID','WeixinID','TaskCode','Status','AddDate','StartTime','EndTime','Level','NextRunTime','LastRunTime']);
//        $select->join('weixins as w','t.WeixinID = w.WeixinID',['w.Nickname','w.AdminID','w.Weixin']);
//        $select->where('t.ParentTaskID != 0');
        if ($category_id){
            $where_msg ='';
            $category_data = explode(',',$category_id);
            foreach($category_data as $w){
                $where_msg .= "FIND_IN_SET(".$w.",CategoryIds) OR ";
            }
            $where_msg = rtrim($where_msg,'OR ');
            $s = $wModel->fromSlaveDB()->select()->from($wModel->getTableName(), 'WeixinID')->where($where_msg);
            $select->where('t.WeixinID in (?)', $s);
        }

        if ($task_code && !($task_code == TASK_CODE_PHONE_ADD_WX || $task_code == TASK_CODE_REPORT_WXFRIENDS)){
//            $select->where('t.TaskCode in (?)',[TASK_CODE_FRIEND_JOIN,TASK_CODE_WEIXIN_FRIEND]);
            $select->where('t.TaskCode = ?',$task_code);

        }

        if ($weixin_name){
            $s = $wModel->fromSlaveDB()->select()->from($wModel->getTableName(), 'WeixinID')->where('Alias like ?  OR Weixin like ?  OR Nickname like ?','%'.$weixin_name.'%');
            $select->where('t.WeixinID in (?)', $s);
        }
        if ($start_add_date){
            $select->where('t.AddDate >= ?',$start_add_date);
        }
        if ($end_add_date){
            $select->where('t.AddDate < ?',$end_add_date);
        }
        if ($start_date && $end_date){
            $select->where("t.StartTime >= {$start_date} or t.EndTime <= {$end_date}");
        }elseif ($start_date){
            $select->where('t.StartTime >= ?',$start_date);
        }elseif($end_date) {
            $select->where('t.EndTime <= ?',$end_date);
        }
        if ($status){
            $select->where('t.Status = ?',$status);
        }
        if ($admin_id){
            $s = $wModel->fromSlaveDB()->select()->from($wModel->getTableName(), 'WeixinID')->where('AdminID = ?',$admin_id);
            $select->where('t.WeixinID in (?)', $s);
        }
        if ($parent_task_id){
            $select->where('t.ParentTaskID = ?',$parent_task_id);
        }
        if($taskIDs){
            $select->where('t.TaskID IN (?)', explode(',', $taskIDs));
        }
        $select->order('TaskID DESC');

        try {

            $res = $task_model->getResult($select,$page,$pagesize);



            $adminInfos = $tmpWeixins = [];
            foreach ($res['Results'] as &$val){
                if (!empty($val['WeixinID'])) {
                    if (!isset($tmpWeixins[$val['WeixinID']])) {
                        $wx = $wModel->fromSlaveDB()->getByPrimaryId($val['WeixinID']);
                        $tmpWeixins[$val['WeixinID']] = $wx;
                    } else {
                        $wx = $tmpWeixins[$val['WeixinID']];
                    }
                    $val['Nickname'] = $wx['Nickname'];
                    $val['AdminID'] = $wx['AdminID'];
                    $val['Weixin'] = $wx['Weixin'];
                } else {
                    $val['Nickname'] = '';
                    $val['AdminID'] = 0;
                    $val['Weixin'] = '';
                }
                if ($val['AdminID'] != 0){
                    if (!isset($adminInfos[$val['AdminID']])) {
                        $admin_info =  $admin_model->fromSlaveDB()->getInfoByID($val['AdminID']);
                        $adminInfos[$val['AdminID']] = $admin_info;
                    } else {
                        $admin_info = $adminInfos[$val['AdminID']];
                    }
                    $val['AdminName'] = $admin_info['Username'];
                }else{
                    $val['AdminName'] = '';
                }
                if ($val['TaskCode'] == TASK_CODE_PHONE_ADD_WX || $val['TaskCode'] == TASK_CODE_REPORT_WXFRIENDS){
                    $val['TimeStatus'] = 1;
                }else{
                    $val['TimeStatus'] = 0;
                }
            }
            $this->showJson(1,'列表',$res);
        } catch (\Exception $e) {
            $this->showJson(0, 'err:'.$e->__toString());
        }
    }


    /**
     * 查看日志
     */
    public function taskLogAction()
    {
        $task_id = $this->_getParam('TaskID',null);

        $task_log = (new Model_Task_Log)->findTaskLog($task_id);
        $this->showJson(1,'日志信息',$task_log);
    }

    /**
     * 任务详情
     */
    public function infoAction()
    {
        $task_id = $this->_getParam('TaskID',null);

        $task_model = new Model_Task;

        $task_info = $task_model->findTaskInfo($task_id);
        $task_info['RunNum'] = count($task_model->findChild($task_id));
        $taskCodes = TASK_CODE;
        $task_info['TaskName'] = $taskCodes[$task_info['TaskCode']];
        $this->showJson(1,'日志详情',$task_info);
    }

    /**
     * 设置优先级
     */
    public function saveLevelAction()
    {
        $taskId = (int)$this->_getParam('TaskID', 0);
        $level = (int)$this->_getParam('Level', 0);
        if ($taskId <= 0) {
            $this->showJson(self::STATUS_FAIL, '任务id非法');
        }

        $taskIds = explode(',',$taskId);

        foreach ($taskIds as $val) {

            $task = (new Model_Task())->fetchRow(['TaskID = ?' => $val]);
            if (!$task) {
                $this->showJson(self::STATUS_FAIL, '任务id非法');
            }

            try {
                $task->Level = $level;
                $task->save();
                $this->showJson(self::STATUS_OK, '设置成功');
            } catch (\Exception $e) {
                $this->showJson(self::STATUS_FAIL, '设置失败:' . $e->getMessage());
            }
        }
    }

}