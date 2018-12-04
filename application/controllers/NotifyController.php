<?php
/**
 * Created by PhpStorm.
 * User: Ekko
 * Date: 2018/8/10
 * Ekko: 10:42
 */

class NotifyController extends DM_Controller
{
    // 手机号是否微信号
    public function isWeixinAction()
    {
        $TaskID = $this->_getParam('TaskID', null);
        $data = $this->_getParam('Data', null);

        $task_model = new Model_Task();
        $phone_model = new Model_Phones();

        $task_info = $task_model->findByID($TaskID);

        // 任务状态为 完成时才进行修改
        $send_num = count($data);
        $send_success = 0;
        $send_fail = 0;
        $not_weixin = 0;
        $no_send = 0;
        $friends = [];
        $send_phone = [];
        $task_config = empty($task_info['TaskConfig'])?[]:json_decode($task_info['TaskConfig'],1);
        $task_phone = empty($task_config)?[]:$task_config['Phones'];

        foreach ($data as $key => &$val) {

            $phone_info = $phone_model->findPhone($val['Phone']);
            $val = json_decode($val, 1);
            $updatep = [];

            $updatep['TaskID'] = $TaskID;
            $updatep['ParentTaskID'] = $task_info['ParentTaskID'];
            // 如果发送时手机号是否是微信状态为未知，导入通讯录时获取到V1则修改为微信号，否则其他状态以手机检测为准
            if ($val['TmpWxNum'] != '' && $phone_info['WeixinState'] == 3) {
                $updatep['WeixinState'] = 1;
            }

            $updatep['FriendsState'] = $val['FriendsState'];
            $updatep['SendWeixin'] = $val['SendWeixin'];
            $updatep['V1'] = $val['TmpWxNum'];
            $updatep['CreateDate'] = date('Y-m-d H:i:s');

            if ($val['FriendsState'] == 1) {

                $send_success++;
                $updatep['SendDate'] = date('Y-m-d');
                $updatep['SendError'] = '';

            } elseif ($val['FriendsState'] == 2) {

                $friends[] = $val['Phone'];
                $updatep['SendError'] = '';

            } elseif ($val['FriendsState'] == 3) {

                $updatep['SendError'] = empty($val['Error']) ? '' : $val['Error'];
                $updatep['FriendsState'] = 0;
                $send_fail++;

            } else {
                $no_send++;
            }

            if ($val['TmpWxNum'] == '') {
                $not_weixin++;
            }

            $phone_model->update($updatep, ['Phone = ?' => $val['Phone']]);
            $send_phone[] = $val['Phone'];
        }

        $notsend_phone = array_diff($task_phone,$send_phone);
        if ($notsend_phone){
            $phone_model->update(['FriendsState'=>0], ['Phone = ?' => $notsend_phone]);

        }
        $no_send = $no_send - $not_weixin;

        $task_data = [
            'SendNum' => $send_num,
            'SendSuccess' => $send_success,
            'NotWeixin' => $not_weixin,
            'NoSend' => $no_send,
            'SendFail' => $send_fail,
            'Friends' => $friends,
            'AddFriendNum' => 0
        ];
//        {"FriendsState":0,"Phone":"13269751735","TmpWxNum":"wxid_t4p7nnpuxlug22","WeixinState":1}
        $res = $task_model->update(['TaskResult' => json_encode($task_data), 'UpdateDate' => date('Y-m-d H:i:s'), 'Status' => 4], ['TaskID = ?' => $TaskID]);
        if (!$res) {
            $this->showJson(0, '子任务修改失败');
        }

        // 修改父任务
        if ($task_info['ParentTaskID'] == 0) {
            $this->showJson(1, 'ok');
        }
        $parant_task_info = $task_model->findForTask($task_info['ParentTaskID']);

        if (empty($parant_task_info['TaskResult'])) {
            $parent_task_data = $task_data;
        } else {
            $parent_result = json_decode($parant_task_info['TaskResult'], 1);
            $parent_task_data = [
                'SendNum' => $parent_result['SendNum'] + $send_num,
                'SendSuccess' => $parent_result['SendSuccess'] + $send_success,
                'NotWeixin' => $parent_result['NotWeixin'] + $not_weixin,
                'NoSend' => $parent_result['NoSend'] + $no_send,
                'SendFail' => $parent_result['SendFail'] + $send_fail,
                'Friends' => $parent_result['Friends'] + $friends,
                'AddFriendNum' => 0
            ];
        }
        $parent_res = $task_model->update(['TaskResult' => json_encode($parent_task_data), 'UpdateDate' => date('Y-m-d H:i:s')], ['TaskID = ?' => $task_info['ParentTaskID']]);
        if (!$parent_res) {
            $this->showJson(0, '父任务修改失败');
        }

        $this->showJson(1, 'ok');
    }

    /**
     * 更新微信设备
     */
    public function deviceWeixinAction()
    {
        // DeviceNo OnlineWeixin SwitchWeixin
        $deviceNo = trim($this->_getParam('DeviceNo'));
        if ($deviceNo === '') {
            $this->showJson(0, '设备号为空');
        }
        $deviceModel = new Model_Device();
        $device = $deviceModel->fetchRow(['DeviceNO = ?' => $deviceNo]);
        if (!$device) {
            $this->showJson(0, '设备号不存在');
        }
        $onlineWeixin = trim($this->_getParam('OnlineWeixin'));
        if ($onlineWeixin === '') {
            $this->showJson(0, '在线微信号为空');
        }
        $wxModel = new Model_Weixin();
        $wx = $wxModel->fetchRow(['Weixin = ?' => $onlineWeixin]);
        if (!$wx) {
            $this->showJson(0, '在线微信号不存在');
        }
        $switchWeixin = trim($this->_getParam('SwitchWeixin'));
        if ($switchWeixin) {
            $swx = $wxModel->fetchRow(['Weixin = ?' => $switchWeixin]);
            if (!$swx) {
                $this->showJson(0, '切换的微信号不存在');
            }
        }

        try {
            $deviceModel->getAdapter()->beginTransaction();
            // 更新
            $device->OnlineWeixinID = $wx->WeixinID;
            $device->OnlineWeixin = $onlineWeixin;
            $device->save();

            // 更新其他设备在线微信
            $deviceModel->update(['OnlineWeixinID' => 0, 'OnlineWeixin' => ''], ['OnlineWeixinID = ?' => $wx->WeixinID, 'DeviceID != ?' => $device->DeviceID]);

            $wx->DeviceID = $device->DeviceID;
            $wx->save();
            if ($switchWeixin && $swx) {
                $swx->DeviceID = $device->DeviceID;
                $swx->save();
            }

            if ($switchWeixin && $swx) {
                $wxModel->update(['DeviceID' => 0], ['DeviceID = ?' => $device->DeviceID, 'WeixinID not in (?)' => [$wx->WeixinID, $swx->WeixinID]]);
            } else {
                $wxModel->update(['DeviceID' => 0], ['DeviceID = ?' => $device->DeviceID, 'WeixinID != ?' => [$wx->WeixinID]]);
            }
            $deviceModel->getAdapter()->commit();
        } catch (\Exception $e) {
            $deviceModel->getAdapter()->rollBack();
            $this->showJson(0, '操作失败,err:' . $e->getMessage());
        }

        $this->showJson(1, '操作成功');

    }

    public function friendAction()
    {
        set_time_limit(0);   // 设置脚本最大执行时间
        ini_set('memory_limit', '1024M');

        try {
            $taskId = (int)$this->_getParam('TaskID', 0);
            $friend = $this->_getParam('Friend', null);
            $weixin = $this->_getParam('WeixinID', null);

            $friend_data = json_decode($friend, 1);

            $friend_num = count($friend_data);

            if (empty($weixin)) {
                $this->showJson(0, 'WeixinID为空');
            }
            if (!$friend_data) {
                $this->showJson(0, '好友数据为空');
            }
            // Model
            $weixin_friend_model = Model_Weixin_Friend::getInstance();
            $weixin_model = Model_Weixin::getInstance();
            $task_model = Model_Task::getInstance();
            $stat_model = Model_Stat::getInstance();
            $send_weixin_model = Model_Sendweixin::getInstance();
            $phone_model = Model_Phones::getInstance();
            $stathours_model = Model_StatHours::getInstance();

            $weixin_info = $weixin_model->getInfoByWeixin($weixin);
            $weixin_id = $weixin_info['WeixinID'];
            $admin_id = $weixin_info['AdminID'];

            $date = date("Y-m-d");

            $account = array();
            $add_num = 0;
            $add_data = array();
            $wxadd_num = 0;
            $loss_friend_num = 0;

            foreach ($friend_data as $key => &$val) {

                $avatar = !empty($val['Avatar'])?str_replace('http://wx.qlogo.cn','https://wx.qlogo.cn',$val['Avatar']):'';

                $repeat = $weixin_friend_model->fetchRow(['WeixinID = ?' => $weixin_id, 'Account = ?' => $val['Account']]);
                // 除重
                if ($repeat) {
                    if ($repeat->Alias != $val['Alias']){
                        $repeat->Alias = $val['Alias'];
                    }
                    if ($repeat->NickName != $val['NickName']){
                        $repeat->NickName = $val['NickName'];
                    }
                    if ($repeat->Avatar != $avatar){
                        $repeat->Avatar = $avatar;
                    }
                    if ($repeat->V1 != $val['TmpWxNum']){
                        $repeat->V1 = $val['TmpWxNum'];
                    }
                    if ($repeat->IsDeleted > 0){

                        $add_num++;

                        if (!empty($val['Account'])) {
                            $add_data[] = $val['Account'];

                        }
                        if (!empty($val['Alias'])) {
                            $add_data[] = $val['Alias'];

                        }
                    }
                    $repeat->IsDeleted = 0;
                    $repeat->DeletedTime = '0000-00-00 00:00:00';
                    $repeat->save();
                } else {
                    $data = [
                        'WeixinID' => $weixin_id,
                        'Account' => $val['Account'],
                        'Alias' => $val['Alias'],
                        'NickName' => $val['NickName'],
                        'V1' => $val['TmpWxNum'],
                        'Avatar' => $avatar,
                        'AddDate' => date('Y-m-d H:i:s')
                    ];
                    // 设置最后一条id
                    $redis = Helper_Redis::getInstance();
                    $redisKey = Helper_Redis::lastMsgIdKey();
                    $hashKey = $weixin_id . '_' . $val['Account'];
                    $lastMsgId = $redis->hGet($redisKey, $hashKey);
                    if ($lastMsgId > 0) {
                        $redis->hDel($redisKey, $hashKey);
                        $data['LastMsgID'] = $lastMsgId;
                    }

                    $weixin_friend_model->insert($data);

                    $add_num++;

                    if (!empty($val['Account'])) {
                        $add_data[] = $val['Account'];

                    }
                    if (!empty($val['Alias'])) {
                        $add_data[] = $val['Alias'];

                    }
                }
                $account[] = $val['Account'];
            }

            $task_info = $task_model->findByID($taskId);
            $task_config = json_decode($task_info['TaskConfig'], 1);

            // 好友上报任务 TaskConfig的Weixin为空时才是统计所有好友信息
            if ($task_config['Weixin'] == []) {

                $weixin_model->update(['FriendNumber' => $friend_num], ['WeixinID = ?' => $weixin_id]);

                $not_friend = $weixin_friend_model->getNotFriendNum($account,$weixin_id);

                // 不是好友的统计
                if (!empty($not_friend) && $not_friend['Num']>0){
                    $loss_friend_num = $not_friend['Num'];

                    $weixin_friend_model->update(['IsDeleted' => 1, 'DeletedTime' => date('Y-m-d H:i:s')], ['Account not in (?)' => $account, 'WeixinID = ?' => $weixin_id]);
                }

            }else{
                $friend_num = $weixin_info['FriendNumber']+$add_num;
                $weixin_model->update(['FriendNumber' => $friend_num], ['WeixinID = ?' => $weixin_id]);
            }

            // 微信号添加好友
            if ($add_data) {

                $new_weixins = $send_weixin_model->findWeixins($add_data);
                $wxadd_num = $new_weixins['Num'];

                // 对添加成功的标记
                $phone_model->update(['FriendsState'=>4],['Weixin  in (?)'=>$add_data,'FriendsState != 4']);
                $send_weixin_model->update(['Status'=>3],['WxAccount  in (?)'=>$add_data,'Status != 3']);
            }

            // 小时统计
            $stathours_model->updateHourDate($friend_num,$add_num,$loss_friend_num,$weixin_id);
            // 添加好友统计
            $stat_model->updateDayData($weixin_id, $admin_id, $date,$friend_num,$add_num,$wxadd_num);


            $this->showJson(1, '操作成功');
        } catch (Exception $e) {
            $this->showJson(0, '抛出异常' . $e->getMessage());
        }


    }

    /**
     * 更新群信息
     */
    public function weixinGroupAction()
    {
        set_time_limit(0);   // 设置脚本最大执行时间
        ini_set('memory_limit', '1024MB');

        $group_data = $this->_getParam('Groups', null);
        $weixin = $this->_getParam('Weixin', null);

        $group_data = json_decode($group_data, 1);

        // Model
        $weixin_model = Model_Weixin::getInstance();
        $group_model = Model_Group::getInstance();
        $weixin_group_model = Model_Weixin_Groups::getInstance();

        $weixin_info = $weixin_model->getInfoByWeixin($weixin);

        $groupIds = [];
        try {
            foreach ($group_data as $g) {

                $group = $group_model->findByChatroomID($g['ChatroomID']);
                $self = $weixin_model->getInfoByWeixin($g['Roomowner']);

                $users = explode(';', $g['Memberlist']);

                if (empty($group)) {

                    // 创建群
                    $group_data = [
                        'Name' => $g['GroupName'],
                        'RealName' => $g['GroupName'],
                        'ChatroomID' => $g['ChatroomID'],
                        'UserNum' => count($users),
                        'Memberlist' => $g['Memberlist'],
                        'IsSelf' => !empty($self)?1:2,
                        'CreateDate' => date('Y-m-d H:i:s'),
                        'Roomowner' => $g['Roomowner']
                    ];
                    $group_id = $group_model->insert($group_data);

                } else {

                    // 更新群
                    $group_data = [
                        'RealName' => $g['GroupName'],
                        'UserNum' => count($users),
                        'Memberlist' => $g['Memberlist'],
                        'IsSelf' => !empty($self)?1:2,
                        'Roomowner' => $g['Roomowner']
                    ];
                    $group_model->update($group_data,['GroupID = ?'=>$group['GroupID']]);

                    $group_id = $group['GroupID'];
                }

                $weixin_in_group = $weixin_group_model->findWeixinInGroup($weixin_info['WeixinID'], $group_id);

                if (empty($weixin_in_group)) {

                    // 创建微信与群的关联
                    $weixin_in_group_data = [
                        'WeixinID' => $weixin_info['WeixinID'],
                        'GroupID' => $group_id,
                        'AddDate' => date('Y-m-d H:i:s'),
                        'IsAdmin' => $weixin == $g['Roomowner']?1:0
                    ];
                    $weixin_group_model->insert($weixin_in_group_data);
                } else {
                    $weixin_group_model->update(['IsAdmin' => $weixin == $g['Roomowner']?1:0], ['ID = ?' => $weixin_in_group['ID']]);
                }

                $groupIds[] = $group_id;
            }
            $weixin_group_model->update(['Status'=>2],['WeixinID = ?'=>$weixin_info['WeixinID'],'GroupID in (?)'=>$groupIds]);

            $this->showJson(1, '更新成功');
        } catch (Exception $e) {
            $this->showJson(0, '抛出异常' . $e->getMessage());
        }


    }

    /**
     * 创建群回调
     */
    public function creteGroupAction()
    {
        try {
            $chatroom_id = $this->_getParam('ChatroomID', null);
            $weixin = $this->_getParam('Weixin', null);

            $group_model = new Model_Group();
            $weixin_model = new Model_Weixin();
            $weixin_group_model = new Model_Weixin_Groups();

            $weixin_info = $weixin_model->getInfoByWeixin($weixin);


            // 创建群
            $group_data = [
                'ChatroomID' => $chatroom_id,
                'IsSelf' => 1,
                'CreateDate' => date('Y-m-d H:i:s')
            ];
            $group_id = $group_model->insert($group_data);

            // 创建微信与群的关联
            $weixin_in_group_data = [
                'WeixinID' => $weixin_info['WeixinID'],
                'GroupID' => $group_id,
                'AddDate' => date('Y-m-d H:i:s'),
                'IsAdmin' => 1
            ];
            $weixin_group_model->insert($weixin_in_group_data);

            $this->showJson(1, '更新成功');
        } catch (Exception $e) {
            $this->showJson(0, '更新失败,抛出异常' . $e->getMessage());
        }

    }

    /**
     * 二维码更新回调
     */
    public function saveQrimgAction()
    {

        try {
            $task_id = $this->_getParam('TaskID', null);
            $qrimg = $this->_getParam('Qrimg', null);

            // Model
            $task_model = new Model_Task();
            $group_model = new Model_Group();

            // 获取任务信息
            $task_info = $task_model->findByID($task_id);
            $taskconfig = json_decode($task_info['TaskConfig'], 1);

            $res = $group_model->update(['QRCodeImg' => $qrimg], ['GroupID = ?' => $taskconfig['GroupID']]);

            if ($res) {
                $this->showJson(1, '更新成功');
            } else {
                $this->showJson(0, '更新失败');
            }

        } catch (Exception $e) {
            $this->showJson(0, '更新失败,抛出异常' . $e->getMessage());
        }
    }

    /**
     * 退群任务回调
     */
    public function quitGroupAction()
    {
        $weixin = $this->_getParam('Weixin', null);
        $chatroomId = $this->_getParam('ChatroomID ', null);

        if (empty($weixin)){
            $this->showJson(0,'微信帐号不存在');
        }

        if (empty($chatroomId)){
            $this->showJson(0,'群标识不存在');
        }

        try{
            // Model
            $groupModel = Model_Group::getInstance();
            $weixinGroupModel = Model_Weixin_Groups::getInstance();
            $groupMemberModel = Model_Group_Member::getInstance();
            $weixinModel = Model_Weixin::getInstance();

            // 获取weixinID
            $weixinInfo = $weixinModel->getInfoByWeixin($weixin);
            $groupInfo = $groupModel->findByChatroomID($chatroomId);

            // 修改数据表
            $weixinGroupModel->update(['Status'=>2],['WeixinID = ?' => $weixinInfo['WeixinID'], 'GroupID = ?' => $groupInfo['GroupID']]);
            $groupMemberModel->update(['Status'=>2],['Account = ?' => $weixin, 'GroupID = ?' => $groupInfo['GroupID']]);

            $this->showJson(1, '更新成功');

        }catch (Exception $e){
            $this->showJson(0, '更新失败,抛出异常'.$e->getMessage());
        }

    }

    /**
     * 转移群任务回调
     */
    public function transferGroupAction()
    {
        try {
            $task_id = $this->_getParam('TaskID', null);

            // Model
            $weixin_group_model = new Model_Weixin_Groups();
            $task_model = new Model_Task();
            $weixin_model = new Model_Weixin();

            // 获取任务信息
            $task_info = $task_model->findByID($task_id);
            $taskconfig = json_decode($task_info['TaskConfig'], 1);

            // 获取weixinID
            $transfer_weixin = $weixin_model->getInfoByWeixin($taskconfig['TransferWeixin']);
            $weixin = $weixin_model->getInfoByWeixin($taskconfig['Weixin']);


            // 修改数据表
            $weixin_group_model->update(['IsAdmin' => 1], ['WeixinID = ?' => $transfer_weixin['WeixinID']]);
            $weixin_group_model->update(['IsAdmin' => 0], ['WeixinID = ?' => $weixin['WeixinID']]);

            $this->showJson(1, '更新成功');
        } catch (Exception $e) {
            $this->showJson(0, '更新失败');
        }

    }


    /**
     * 手机微信检测回调
     */

    public function detectionPhoneAction()
    {

        $phones = $this->_getParam('Phones', null);
        $phones = json_decode($phones, 1);

        $model = new Model_Phones();

        foreach ($phones as $key => &$val) {

            try {
                $updatep['Avatar'] = empty($val['Avatar']) ? '' : str_replace('http://wx.qlogo.cn','https://wx.qlogo.cn',$val['Avatar']);
                $updatep['Nickname'] = empty($val['Nickname']) ? '' : $val['Nickname'];
                $updatep['Province'] = empty($val['Province']) ? '' : $val['Province'];
                $updatep['City'] = empty($val['City']) ? '' : $val['City'];

                // 帐号状态异常判断微信号为 无用号码
                if (strpos($val['Error'], '<Content><![CDATA[被搜帐号状态异常，无法显示]]></Content>') === false) {
                    $updatep['WeixinState'] = $val['IsWeixin'] == 0 ? 3 : $val['IsWeixin'];
                } else {
                    $updatep['WeixinState'] = 2;
                }
                $updatep['DetectionError'] = empty($val['Error']) ? '' : $val['Error'];
                $updatep['V1'] = empty($val['TmpWxNum']) ? '' : $val['TmpWxNum'];
                $updatep['V2'] = empty($val['V2']) ? '' : $val['V2'];
                $updatep['Weixin'] = empty($val['Weixin']) ? '' : $val['Weixin'];
                $updatep['DetectionDate'] = date('Y-m-d H:i:s');
                $model->update($updatep, ['Phone = ?' => $val['Phone']]);
            } catch (Exception $e) {
                $this->showJson(0, '抛出异常' . $e->getMessage());
            }
        }
        $this->showJson(1, '完成');

    }


    /**
     * 微信添加微信号好友的任务回调
     */
    public function weixinAddFriendAction()
    {
        $task_id = $this->_getParam('TaskID', null);
        $status = $this->_getParam('Status', null);
        $weixin = $this->_getParam('Weixin', ''); // 好友资源的微信号
        $wx_account = $this->_getParam('WxAccount', '');
        $nickname = $this->_getParam('Nickname', '');
        $avatar = $this->_getParam('Avatar', '');
        $province = $this->_getParam('Province', '');
        $city = $this->_getParam('City', '');
        $msg = $this->_getParam('Msg', '');
        $v1 = $this->_getParam('V1', '');
        $v2 = $this->_getParam('V2', '');

        // Model
        $snedweixin_model = Model_Sendweixin::getInstance();
        $task_model = Model_Task::getInstance();
        $weixin_model = Model_Weixin::getInstance();
        $weixin_friend_model = Model_Weixin_Friend::getInstance();

        // 任务
        $task_info = $task_model->findByID($task_id);
        $weixin_info = $weixin_model->findByID($task_info['WeixinID']);

        $send_weixin = empty($weixin_info['Weixin'])?'':$weixin_info['Weixin'];
        $send_date = date('Y-m-d');

        $success = 0;
        $fail = 1;

        switch ($status){
            case 1:

                $success = 1;
                $fail = 0;
                break;

            case 2:

                // 如果是操作过于频繁 好友资源可再次使用
                if (!empty($msg) && strpos($msg, '<Content><![CDATA[操作过于频繁，请稍后再试]]></Content>') !== false) {
                    $status = 0;
                    $send_weixin = '';
                    $send_date = '0000-00-00';
                }
                // 查看是否已经是好友
                if (!empty($v1)){
                    $wx_friend = $weixin_friend_model->findWxIsFriend($task_info['WeixinID'],$v1);
                    if ($wx_friend){
                        $status = 4;
                        $msg = '已经是好友了';
                        $v1 = $v2 ='';
                    }
                }
                break;

            case 4:

                $v1 = $v2 ='';
                break;
        }


        // 微信帐号问题(例:wxidbqhu304mx3a711 wxid后面没有下划线)

        if (strpos($wx_account, 'wxid') !== false && strpos($wx_account, 'wxid') === 0){
            // 帐号存在wxid 但不是wxid_ 且在字符串首位

            if (strpos($wx_account, 'wxid_') === false){
                $wx_account = str_replace('wxid','wxid_',$wx_account);
            }
        }

        $sendweixin_data = [
            'Status' => $status,
            'UpdateDate' => date('Y-m-d H:i:s'),
            'Message' => $msg,
            'V1' => $v1,
            'V2' => $v2,
            'TaskID' => $task_id,
            'WxAccount' => $wx_account,
            'Nickname' => $nickname,
            'Avatar' => !empty($avatar)?str_replace('http://wx.qlogo.cn','https://wx.qlogo.cn',$avatar):'',
            'Province' => $province,
            'City' => $city,
            'ParentTaskID' => $task_info['ParentTaskID'],
            'SendWeixin' => $send_weixin,
            'SendDate' => $send_date
        ];
        $res = $snedweixin_model->update($sendweixin_data, ['Weixin = ?' => $weixin]);

        if (!$res) {
            $this->showJson(0, '修改失败');
        }

        try {

            // 开启事务
            $task_model->getAdapter()->beginTransaction();

            if ($task_info['TaskResult']) {
                $task_result = json_decode($task_info['TaskResult'], 1);
                $task_data = [
                    'SendNum' => $task_result['SendNum'] + 1,
                    'SendSuccess' => $task_result['SendSuccess'] + $success,
                    'SendFail' => $task_result['SendFail'] + $fail,
                ];
                $task_model->update(['TaskResult' => json_encode($task_data), 'UpdateDate' => date('Y-m-d H:i:s')], ['TaskID = ?' => $task_id]);
            } else {
                $task_data = [
                    'SendNum' => 1,
                    'SendSuccess' => $success,
                    'SendFail' => $fail,
                ];
                $task_model->update(['TaskResult' => json_encode($task_data), 'UpdateDate' => date('Y-m-d H:i:s')], ['TaskID = ?' => $task_id]);

            }

            if ($task_info['ParentTaskID'] > 0) {
                // 父任务
                $task_parent_info = $task_model->findForTask($task_info['ParentTaskID']);

                if ($task_parent_info['TaskResult']) {
                    $task_parent_result = json_decode($task_parent_info['TaskResult'], 1);
                    $task_parent_data = [
                        'SendNum' => $task_parent_result['SendNum'] + 1,
                        'SendSuccess' => $task_parent_result['SendSuccess'] + $success,
                        'SendFail' => $task_parent_result['SendFail'] + $fail,
                    ];
                    $task_model->update(['TaskResult' => json_encode($task_parent_data), 'UpdateDate' => date('Y-m-d H:i:s')], ['TaskID = ?' => $task_info['ParentTaskID']]);
                } else {
                    $task_parent_data = [
                        'SendNum' => 1,
                        'SendSuccess' => $success,
                        'SendFail' => $fail,
                    ];
                    $task_model->update(['TaskResult' => json_encode($task_parent_data), 'UpdateDate' => date('Y-m-d H:i:s')], ['TaskID = ?' => $task_info['ParentTaskID']]);

                }
            }

            $task_model->getAdapter()->commit();

            $this->showJson(1, '更新成功');

        } catch (Exception $e) {
            $task_model->getAdapter()->rollBack();
            $this->showJson(0, '抛出异常' . $e->getMessage());
        }

    }


    /**
     * 初始化回调
     */
    public function resettingAction()
    {
        $device_no = $this->_getParam('DeviceNo', null);
        $message = $this->_getParam('Message', null);
        $status = $this->_getParam('Status', null);

        $device_model = new Model_Device();

        $data = [
            'Resetting' => $status,
            'ExceptMessage' => $message
        ];

        $up = $device_model->update($data, $device_no);

        if ($up) {
            $this->showJson(1, '更新成功');
        } else {
            $this->showJson(0, '更新失败');
        }

    }

    /**
     * 设备返回SerialNum
     */
    public function getSerialnumAction()
    {
        $device_no = $this->_getParam('DeviceNO', null);

        $device_model = new Model_Device();
        $device_info = $device_model->getInfoByNO($device_no);

        if ($device_info) {
            $this->showJson(1, '', $device_info['SerialNum']);
        } else {
            $this->showJson(0, '查询失败');
        }

    }

    /**
     * 每个小时回传好友数量
     */
    public function weixinFriendAction()
    {
        $friend_num = $this->_getParam('FriendNum');
        $time = $this->_getParam('Time');
        $weixin = $this->_getParam('Weixin');

        try {
            // Model
            $weixin_model = new Model_Weixin();
            $stat_hours_model = new Model_StatHours();

            $weixin_info = $weixin_model->getInfoByWeixin($weixin);

            // JAVA时间戳是13位  PHP时间戳为10位
            $hour = date('H', substr($time, 0, 10));
            $date = date('Y-m-d');

            // 查询是否已经添加了这个小时的记录
            $hour_info = $stat_hours_model->findFriendNum($weixin_info['WeixinID'], $date, $hour);

            $data['FriendNum'] = $friend_num;
            $data['Date'] = $date;
            $data['Time'] = $time;
            $data['Hour'] = $hour;
            $data['DateTime'] = date('Y-m-d H:i:s', substr($time, 0, 10));
            $data['WeixinID'] = $weixin_info['WeixinID'];

            // 查询最近更新的记录
            $last_num = $stat_hours_model->findLast($weixin_info['WeixinID'], $hour_info ? $hour_info['HourID'] : null);

            if ($last_num == false) {
                $new_friend_num = $friend_num;
            } else {
                $new_friend_num = $friend_num - $last_num['FriendNum'];
            }

            $data['NewFriendNum'] = $new_friend_num < 0?0:$new_friend_num;

            if ($hour_info) {
                $stat_hours_model->update($data, ['HourID = ?' => $hour_info['HourID']]);
            } else {
                $stat_hours_model->insert($data);
            }

            // 同时同步微信列表中的好友数量
            $weixin_model->update(['FriendNumber' => $friend_num], ['WeixinID = ?' => $weixin_info['WeixinID']]);

            $this->showJson(1, '成功');

        } catch (Exception $e) {
            $this->showJson(0, '抛出异常' . $e->getMessage());
        }

    }

    /**
     * 被动加好友的好友数据
     */
    public function passiveFriendAction()
    {
        $weixin = $this->_getParam('Weixin', null);
        $account = $this->_getParam('Account', null);
        $alias = $this->_getParam('Alias', null);
        $nickname = $this->_getParam('Nickname', null);
        $avatar = $this->_getParam('Avatar', null);
        $v1 = $this->_getParam('V1', null);
        $v2 = $this->_getParam('V2', null);
        $source = $this->_getParam('Source', null);
        $chatroom_id = $this->_getParam('ChatroomID', null);
        $status = $this->_getParam('Status', null); // 1-通过 2-未通过(删除)

        try {
            // Model
            $weixin_friend_model = new Model_Weixin_Friend();
            $weixin_model = new Model_Weixin();
            $message_model = new Model_MessageTemplate();
            $stat_model = new Model_Stat();
            $stat_hour_model = new Model_StatHours();

            $weixin_info = $weixin_model->getInfoByWeixin($weixin);
            if (!$weixin_info) {
                $this->showJson(0, '微信非法');
            }
            if($status == 1){
                $dataApply['State'] = Model_Weixin_FriendApply::STATE_ADD;
                $dataApply['IsDeleted'] = Model_Weixin_FriendApply::IS_NOT_DELETED;
            }else{
                $dataApply['IsDeleted'] = Model_Weixin_FriendApply::IS_DELETED;
            }
            $dataApply['UpdateTime'] = date('Y-m-d H:i:s');
            (new Model_Weixin_FriendApply())->update($dataApply, ['WeixinID = ?' => $weixin_info['WeixinID'], 'Talker = ?' => $account]);
            if($status == 1){
                $repeat = $weixin_friend_model->fetchRow(['WeixinID = ?' => $weixin_info['WeixinID'], 'Account = ?' => $account]);

            $num = 0;
            if ($repeat) {
                $repeat->Alias = $alias;
                $repeat->NickName = $nickname;
                $repeat->Avatar = !empty($avatar)?str_replace('http://wx.qlogo.cn','https://wx.qlogo.cn',$avatar):'';
                $repeat->V1 = $v1;
                $repeat->V2 = $v2;
                $repeat->IsDeleted = $status == 2 ? 2 : 0;
                $repeat->Source = $source;
                $repeat->ChatroomID = $chatroom_id;
                $repeat->AddDate = date('Y-m-d H:i:s');
                $repeat->save();

                if ($status != 2){
                    $num = 1;
                }

            } else {
                $data = [
                    'WeixinID' => $weixin_info['WeixinID'],
                    'Account' => $account,
                    'Alias' => $alias,
                    'Avatar' => !empty($avatar)?str_replace('http://wx.qlogo.cn','https://wx.qlogo.cn',$avatar):'',
                    'NickName' => $nickname,
                    'V1' => $v1,
                    'V2' => $v2,
                    'IsDeleted' => $status == 2 ? 2 : 0,
                    'Source' => $source,
                    'ChatroomID' => $chatroom_id,
                    'AddDate' =>date('Y-m-d H:i:s')
                ];
                $weixin_friend_model->insert($data);
                $message = $message_model->isWxTagId($weixin_info['CategoryIds']);
                // 新增好友给与回复
                if ($message) {
                    $device_model = new Model_Device();
                    $client = $device_model->getDeviceByWeixin($weixin);

                        $contents = json_decode($message['ReplyContents'], 1);
                        $num = count($contents);

                        switch ($message['ReplyType']) {
                            // 回复所有
                            case 'ALL':
                                foreach ($contents as $c) {
                                    $data = [
                                        'MessageID' => '',
                                        'ChatroomID' => "",
                                        'WxAccount' => $account,
                                        'content' => $c['Content'],
                                        'type' => 1
                                    ];
                                    $response = json_encode(['TaskCode' => TASK_CODE_SEND_CHAT_MSG, 'Data' => $data]);
                                    Helper_Gateway::initConfig()->sendToClient($client['ClientID'], $response);
                                }
                                break;
                            // 回复随机一条
                            case 'RAND':
                                $n = rand(0, $num - 1);
                                $data = [
                                    'MessageID' => '',
                                    'ChatroomID' => "",
                                    'WxAccount' => $account,
                                    'content' => $contents[$n]['Content'],
                                    'type' => 1
                                ];
                                $response = json_encode(['TaskCode' => TASK_CODE_SEND_CHAT_MSG, 'Data' => $data]);
                                Helper_Gateway::initConfig()->sendToClient($client['ClientID'], $response);
                                break;
                        }

                    }

                $num = 1;
                }
            }

            if ($num == 1){
                $friend_num = $weixin_info['FriendNumber']+$num;
                $weixin_model->update(['FriendNumber'=>$friend_num],['WeixinID = ?'=>$weixin_info['WeixinID']]);
                $stat_model->saveStats($weixin_info['WeixinID'],$weixin_info['AdminID'],$friend_num,$num);
                $stat_hour_model->updateHourDate($friend_num,$num,0,$weixin_info['WeixinID']);
            }

            $this->showJson(1, 'ok');

        } catch (Exception $e) {
            $this->showJson(0, '抛出异常' . $e->getMessage());
        }


    }

    /**
     * 获取公众号文章阅读数
     */
    public function getGzhurlViewnumAction()
    {
        $url = trim($this->_getParam('Url')); //gzh文章链接
        $num = intval($this->_getParam('ViewNum', 0)); //阅读数
        if ($url == '') {
            $this->showJson(0, '文章链接为空');
        }
        try {
            // Model
            $model = new Model_Distribution();
            $time = date('Y-m-d H:i:s', strtotime('-1 hour')); //更新一个小时前此公众号链接阅读数
            $data = [];
            $data['ViewNumGetTime'] = date('Y-m-d H:i:s');
            if ($num) {
                $data['ArticleViewNum'] = $num;
            }
            $model->update($data, ['ArticleUrl = ?' => $url, 'ViewNumGetTime <= ?' => $time]);
            $this->showJson(1, '成功');
        } catch (Exception $e) {
            $this->showJson(0, '抛出异常' . $e->getMessage());
        }
    }


    /**
     * 获取URL返回的HTML回调
     */
    public function getHtmlAction()
    {
        $id = $this->_getParam('ID', null);
        $html = $this->_getParam('Html', null);

        try {

            $model = new Model_Linkurl();

            $data = [
                'Html' => $html,
                'Status' => Model_Linkurl::STATUS_GATHERED,
                'UpdateDate' => date('Y-m-d H:i:s')
            ];
            $model->update($data, ['LinkurlID = ?' => $id]);

            $this->showJson(1, 'ok');
        } catch (Exception $e) {
            $this->showJson(0, '抛出异常' . $e->getMessage());
        }

    }

    /**
     * 广告点击
     */
    public function adClickAction()
    {
        $adId = (int)$this->_getParam('AdID');
        if ($adId < 1) {
            $this->showJson(0, 'adid非法');
        }
        $adModel = new Model_Ad();
        $ad = $adModel->getByPrimaryId($adId);
        $hotWordClickTimes = (int)$this->_getParam('HotWordClickTimes', 0);
        $adClickTimes = (int)$this->_getParam('AdClickTimes', 0);
        $likeTimes = (int)$this->_getParam('LikeTimes', 0);
        $urlClickTimes = (int)$this->_getParam('UrlClickTimes', 0);

        try {
            $ad->HotWordClickTimes += $hotWordClickTimes;
            $ad->AdClickTimes += $adClickTimes;
            $ad->LikeTimes += $likeTimes;
            $ad->UrlClickTimes += $urlClickTimes;
            $ad->save();
        } catch (\Exception $e) {
            $this->showJson(0, 'err:' . $e->getMessage());
        }

        $this->showJson(1, '操作成功');
    }

    /**
     * 微信设置附近人回调
     */
    public function wxNearPeopleAction()
    {
        $weixin = $this->_getParam('Weixin', null);
        $config = $this->_getParam('Config', null);

        if (!isset($weixin) || empty($weixin)) {
            $this->showJson(0, '微信号参数不存在');
        }

        if (!isset($config) || empty($config)) {
            $this->showJson(0, '配置信息为空');
        }

        $weixinModel = new Model_Weixin();

        try {
            $weixinInfo = $weixinModel->fetchRow(['Weixin = ?' => $weixin]);

            if ($weixinInfo) {
                $weixinInfo->NearPeople = $config;
                $weixinInfo->save();
                $this->showJson(1, '存储成功');
            } else {
                $this->showJson(0, '存储失败微信号不存在');
            }
        } catch (Exception $e) {
            $this->showJson(0, '存储失败,抛出异常' . $e->getMessage());
        }

    }

    /**
     * 修改微信详情任务回调
     */
    public function saveWeixinInfoAction()
    {
        $taskID = $this->_getParam('TaskID', null);
        $weixinInfo = $this->_getParam('WeixinInfo', null);
        $weixinInfo = json_decode($weixinInfo,1);

        if (empty($taskID)){
            $this->showJson(0, '无任务ID');
        }

        if (empty($weixinInfo)){
            $this->showJson(0, '没有微信修改后的数据');
        }

        $info = [
            'Nickname',
            'AvatarUrl',
            'Sex',
            'Signature',
            'CoverimgUrl',
            'Nation',
            'Province',
            'City'
        ];

        $weixinModel = Model_Weixin::getInstance();
        $taskModel = Model_Task::getInstance();

        $taskInfo = $taskModel->findByID($taskID);

        $data = [];
        foreach ($weixinInfo as $k=>$v){

            if (in_array($k,$info)){
                $data[$k]=$v;
            }else{
                $this->showJson(0, '参数{'.$k.'}非法');
            }
        }

        $weixinModel->update($data,['WeixinID = ?'=>$taskInfo['WeixinID']]);

        $this->showJson(1, '修改成功');

    }

    /**
     * 上报好友申请数据
     */
    public function friendApplyAction(){
        try {
            set_time_limit(0);
            ini_set('memory_limit', '1024M');
            $weixin = trim($this->_getParam('Weixin', ''));
            if ($weixin === '') {
                $this->showJson(0, '微信号不能为空');
            }
            $applys = trim($this->_getParam('ApplyInfo', ''));
            if ($applys === '') {
                $this->showJson(0, '好友申请内容为空');
            }
            $applys = json_decode($applys, true);
            if (json_last_error() != JSON_ERROR_NONE) {
                $this->showJson(0, '好友申请内容非法');
            }
            $wxModel = new Model_Weixin();
            $applyModel = new Model_Weixin_FriendApply();
            $wx = $wxModel->getInfoByWeixin($weixin);
            if (!$wx) {
                $this->showJson(0, '微信非法');
            }

            $validFields = ['Talker', 'DisplayName', 'ContentVerifyContent', 'IsNew','State', 'LastModifiedTime', 'FmsgContent', 'Avatar'];
            foreach ($applys as $row) {
                if (!Helper_Until::hasReferFields($row, $validFields)) {
                    continue;
                }
                if ($row['Talker'] === '') {
                    continue;
                }
                $data = [
                    'DisplayName' => $row['DisplayName'],
                    'Avatar' => !empty($row['Avatar'])?str_replace('http://wx.qlogo.cn','https://wx.qlogo.cn',$row['Avatar']):'',
                    'IsNew' => $row['IsNew'],
                    'State' => $row['State'],
                    'LastModifiedTime' => $row['LastModifiedTime'],
                    'ContentVerifyContent' => $row['ContentVerifyContent'],
                    'UpdateTime' => date('Y-m-d H:i:s', ($row['LastModifiedTime']?substr($row['LastModifiedTime'], 0, 10):time())),
                    'IsDeleted' => Model_Weixin_FriendApply::IS_NOT_DELETED,
                ];
                if(!$row['LastModifiedTime']){
                    DM_Controller::Log('friendApply', 'LastModifiedTime is null,'.json_encode($row));
                }
                $apply = $applyModel->getByWeixinAndFriend($wx['WeixinID'], $row['Talker'], false);
                if($apply){
                    if($apply->LastModifiedTime < $row['LastModifiedTime']){
                        if($apply->IsDeleted == Model_Weixin_FriendApply::IS_DELETED ||
                            ($apply->State == Model_Weixin_FriendApply::STATE_ADD && $row['State'] == Model_Weixin_FriendApply::STATE_UNADD)){
                            $data['ApplyTime'] = date('Y-m-d H:i:s', ($row['LastModifiedTime']?substr($row['LastModifiedTime'], 0, 10):time()));
                            if($apply->IsDeleted == Model_Weixin_FriendApply::IS_DELETED){
                                //如果存在的记录是删除状态,则表示再次加好友,更新FmsgContent
                                $data['FmsgContent'] = $row['FmsgContent'];
                            }
                        }
                        $applyModel->fromMasterDB()->update($data, ['FriendApplyID = ?' => $apply->FriendApplyID]);
                    }
                }else{
                    $data['Talker'] = $row['Talker'];
                    $data['WeixinID'] = $wx['WeixinID'];
                    $data['FmsgContent'] = $row['FmsgContent'];
                    $data['ApplyTime'] = date('Y-m-d H:i:s', ($row['LastModifiedTime']?substr($row['LastModifiedTime'], 0, 10):time()));
                    $applyModel->fromMasterDB()->insert($data);
                }
            }
            $this->showJson(1, 'ok');
        } catch (Exception $e) {
            $this->showJson(0, '抛出异常,error:' . $e->getMessage());
        }
    }

    /**
     * 返回个号最近的一次时间
     */
    public function lastFriendApplyAction(){
        try {
            $weixin = trim($this->_getParam('Weixin', ''));
            if ($weixin === '') {
                $this->showJson(0, '微信号不能为空');
            }

            $wxModel = new Model_Weixin();
            $wx = $wxModel->getInfoByWeixin($weixin);
            if (!$wx) {
                $this->showJson(0, '微信非法');
            }
            $aModel = new Model_Weixin_FriendApply();
            $apply = $aModel->select()->where('WeixinID = ?',$wx['WeixinID'])->order('LastModifiedTime Desc')->limit(1)->query()->fetch();
            $time = 0;
            if($apply){
                $time = $apply['LastModifiedTime'];
            }
            $this->showJson(1, '操作成功', $time);
        } catch (Exception $e) {
            $this->showJson(0, '存储失败,抛出异常' . $e->getMessage());
        }
    }


    /**
     * 头像信息回传
     */
    public function saveWxAvatarAction()
    {
        $wxAccount = $this->_getParam('Weixin','');
        $avatarUrl = $this->_getParam('AvatarUrl','');

        try{

            if (empty($wxAccount)){
                $this->showJson(0, '无账号');
            }

            if (empty($avatarUrl)){
                $this->showJson(0, '无头像');
            }

            $wxModel = Model_Weixin::getInstance();
            $res = $wxModel->update(['AvatarUrl'=>str_replace('http://wx.qlogo.cn','https://wx.qlogo.cn',$avatarUrl)],['Weixin = ?'=>$wxAccount]);

            if ($res == false){
                $this->showJson(0, '修改失败');
            }else{
                $this->showJson(1, '修改成功');
            }

        }catch (Exception $e){
            $this->showJson(0, '抛出异常'.$e->getMessage());
        }
    }

    /**
     * 个号微信二维码回调接口
     */
    public function wxQrcodeImgAction()
    {
        $wxAccount = $this->_getParam('Weixin','');
        $qrcodeImg = $this->_getParam('QrcodeImg','');

        try{

            if (empty($wxAccount)){
                $this->showJson(0, '无账号');
            }

            if (empty($qrcodeImg)){
                $this->showJson(0, '无二维码地址');
            }

            $wxModel = Model_Weixin::getInstance();
            $res = $wxModel->update(['WxQrcode'=>$qrcodeImg],['Weixin = ?'=>$wxAccount]);

            if ($res == false){
                $this->showJson(0, '修改失败');
            }else{
                $this->showJson(1, '修改成功');
            }

        }catch (Exception $e){
            $this->showJson(0, '抛出异常'.$e->getMessage());
        }
    }

    /**
     * 微信群成员回调接口
     */
    public function wxGroupMembersAction()
    {
        $chatroomId = $this->_getParam('ChatroomID',null);

        if (empty($chatroomId)){
            $this->showJson(0, '请上传有效的群标识字段');
        }

        $memberList = $this->_getParam('Memberlist',null);

        if (empty($memberList)){
            $this->showJson(0, '没有成员列表数据');
        }

        $memberList = json_decode($memberList, 1);

        $groupModel = Model_Group::getInstance();
        $groupMembersModel = Model_Group_Member::getInstance();

        $groupinfo = $groupModel->findByChatroomID($chatroomId);
        if (empty($groupinfo)){
            $this->showJson(0, '群不存表中,请先同步群');
        }

        $account = [];
        foreach ($memberList as $member){

            try{

                $data = [
                    'GroupID' => $groupinfo['GroupID'],
                    'Account' => $member['Account'],
                    'NickName' => $member['NickName'],
                    'Source' => $member['Source'],
                    'Avatar' => $member['Avatar'],
                    'UpdateDate' => date('Y-m-d H:i:s')
                ];

                $groupMember = $groupMembersModel->groupMember($groupinfo['GroupID'],$member['Account']);

                if (isset($groupMember['MemberID'])){

                    $groupMembersModel->update($data,['MemberID = ?'=>$groupMember]);

                }else{
                    $data['AddDate'] = date('Y-m-d H:i:s');
                    $groupMembersModel->insert($data);
                }

                $account[] = $member['Account'];
            }catch (Exception $e){
                $this->showJson(0, '更新群成员列表时候出现错误,抛出异常'.$e->getMessage());
            }

        }

        // 不在群里的删除
        $groupMembersModel->update(['Status'=>2],['GroupID = ?'=>$groupinfo['GroupID'],'Account not in (?)'=>$account]);

        $this->showJson(1, '更新成功');
    }

    /**
     * 拉人进群的回调接口
     */
    public function groupAddMembersAction()
    {
        $chatroomId = $this->_getParam('ChatroomID',null);

        if (empty($chatroomId)){
            $this->showJson(0, '请上传有效的群标识字段');
        }

        $friend = $this->_getParam('Friend',null);

        if (empty($friend)){
            $this->showJson(0, '没有成员列表数据');
        }

        $weixin = $this->_getParam('Weixin','');


        $friend = json_decode($friend, 1);

        $groupModel = Model_Group::getInstance();
        $groupMembersModel = Model_Group_Member::getInstance();
        $weixinFriendModel = Model_Weixin_Friend::getInstance();

        $memberList = $weixinFriendModel->getUserByAccount($friend);
        $groupinfo = $groupModel->findByChatroomID($chatroomId);

        foreach ($memberList as $member){

            try{
                $data = [
                    'GroupID' => $groupinfo['GroupID'],
                    'Account' => $member['Account'],
                    'NickName' => $member['NickName'],
                    'Source' => $weixin,
                    'Avatar' => $member['Avatar'],
                    'UpdateDate' => date('Y-m-d H:i:s')
                ];

                $groupMember = $groupMembersModel->groupMember($groupinfo['GroupID'],$member['Account']);

                if (isset($groupMember['MemberID'])){

                    $groupMembersModel->update($data,['MemberID = ?'=>$groupMember]);

                }else{
                    $data['AddDate'] = date('Y-m-d H:i:s');
                    $groupMembersModel->insert($data);
                }

            }catch (Exception $e){
                $this->showJson(0, '更新群成员列表时候出现错误,抛出异常'.$e->getMessage());
            }

        }

        $this->showJson(1, '更新成功');
    }

}