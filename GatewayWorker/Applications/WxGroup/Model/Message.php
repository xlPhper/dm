<?php

require_once dirname(__FILE__) . "/Base.php";
use \GatewayWorker\Lib\Gateway;

class Message extends Base
{
    public static $table = 'messages';

    /**
     * 左侧微信列表
     */
    public static function openLeftWxList($client_id, $requestData)
    {
        if (!isset($requestData['AdminID'])) {
            return self::returnArray(0, 'adminid require');
        }
        $adminId = Helper_OpenEncrypt::decrypt($requestData['AdminID']);
        if ($adminId === false) {
            return self::returnArray(0, 'adminid require');
        }

        $start = isset($requestData['Start']) ? (int)$requestData['Start'] : 0;
        $start = $start >= 0 ? $start : 0;
        $num = isset($requestData['Num']) ? (int)$requestData['Num'] : 0;
        $num = $num > 0 ? $num : 20;
        $departmentId = isset($requestData['DepartmentID']) ? (int)$requestData['DepartmentID'] : 0;

        $slaveDb = self::getSlaveDb();

        $admin = $slaveDb->from('admins')->select('AdminID,IsSuper,CompanyId,DepartmentID')
            ->where('AdminID = :AdminID')->bindValue('AdminID', $adminId)
            ->row();
        if (!isset($admin['AdminID'])) {
            return self::returnArray(0, 'adminid invalid');
        }

        Gateway::bindUid($client_id, $adminId);

        // 处理超级管理员
        if ($admin['IsSuper'] == 'Y' && $admin['CompanyId'] > 0) {
            $company = $slaveDb->from('company')->select('CompanyID,WeixinIds')
                ->where('CompanyID = :CompanyID')->bindValue('CompanyID', $admin['CompanyId'])
                ->row();
            if (!isset($company['CompanyID'])) {
                return self::returnArray(0, 'super admin no company');
            }
            $weixinIds = trim($company['WeixinIds']);
            if ($weixinIds === '') {
                return self::returnArray(0, 'no allocate weixin');
            }
            $weixinIds = explode(',', $weixinIds);
            $tmpWeixinIds = [];
            foreach ($weixinIds as $wxId) {
                $wxId = (int)$wxId;
                if ($wxId > 0) {
                    $tmpWeixinIds[] = $wxId;
                }
            }
            if (empty($tmpWeixinIds)) {
                return self::returnArray(0, 'no allocate weixin');
            }

            // 获取微信的admins
            if ($departmentId > 0) {
                $tmpAdmins = $slaveDb->from('admins')->select('AdminID')
                    ->where('CompanyID = :CompanyID')->bindValue('CompanyID', $company['CompanyID'])
                    ->where('DepartmentID = :DepartmentID')->bindValue('DepartmentID', $departmentId)
                    ->query();
                $tmpAdminIds = [];
                $adminWhere = [];
                foreach ($tmpAdmins as $tmpAdmin) {
                    $adminWhere[] = "FIND_IN_SET('{$tmpAdmin['AdminID']}', YyAdminID)";
                    $tmpAdminIds[] = $tmpAdmin['AdminID'];
                }
                if (!$tmpAdminIds) {
                    return self::returnArray(1, 'department no admin', [], ['Start' => $start, 'Num' => $num]);
                }
                $conn = $slaveDb->from('weixins')->select('WeixinID,Weixin,Alias,Nickname,AvatarUrl,OnlineWeixinID,WxNotes,FriendNumber,SerialNum')
                    ->leftJoin('devices', 'weixins.DeviceID=devices.DeviceID')
                    ->where("weixins.WeixinID in ({$slaveDb->inWhereArrToStr($tmpWeixinIds)})")
                    ->where('('.implode(' or ', $adminWhere).')')
                    ->orderByDESC(['OnlineWeixinID']);
            }else{
                $conn = $slaveDb->from('weixins')->select('WeixinID,Weixin,Alias,Nickname,AvatarUrl,OnlineWeixinID,WxNotes,FriendNumber,SerialNum')
                    ->leftJoin('devices', 'weixins.DeviceID=devices.DeviceID')
                    ->where("weixins.WeixinID in ({$slaveDb->inWhereArrToStr($tmpWeixinIds)})")
                    ->orderByDESC(['OnlineWeixinID']);
            }
        } else {
            $conn = $slaveDb->from('weixins')->select('WeixinID,Weixin,Alias,Nickname,AvatarUrl,OnlineWeixinID,WxNotes,FriendNumber,SerialNum')
                ->where('find_in_set(:AdminID,YyAdminID)')->bindValue('AdminID', $adminId)
                ->leftJoin('devices', 'weixins.DeviceID=devices.DeviceID')
                ->orderByDESC(['OnlineWeixinID']);
        }

        $wxs = $conn->limit($num)->offset($start)->query();
        $addStartTime = date('Y-m-d H:i:s', strtotime("-3 days"));
        foreach ($wxs as &$wx) {
            $weixinAccount = $wx['Weixin'];
            $wexinId = $wx['WeixinID'];

            $fiendApplyNum = $slaveDb->from('weixin_friend_apply')->select('count(FriendApplyID) as Num')
                ->where("WeixinID = '{$wx['WeixinID']}' and State = 0 and IsNew = 1 and IsDeleted = 0")->row(); //新好友申请数
            $wx['NewFriendApplies'] = isset($fiendApplyNum['Num']) ? $fiendApplyNum['Num'] : '0';

            $unreadMsg = $slaveDb->from('messages')->select('MessageID')
                ->where("((SenderWx = '{$weixinAccount}' and GroupID = 0) or (ReceiverWx = '{$weixinAccount}' and GroupID = 0))")
                ->where("ReadStatus = 'UNREAD'")
                ->where("AddDate > '{$addStartTime}'")
                ->row();
            $wx['HasUnreadMsg'] = isset($unreadMsg['MessageID']) ? 'Y' : 'N';
//            $unreadNum = $slaveDb->from('weixin_friends')->select('sum(UnreadNum) as Num')
//                ->where("WeixinID = {$wexinId}")->row();
//            $wx['HasUnreadMsg'] = isset($unreadNum['Num']) && (int)$unreadNum['Num'] > 0 ? 'Y' : 'N';
            // 在线状态
            $wx['IsOnline'] = intval($wx['OnlineWeixinID']) > 0 ? 'Y' : 'N';
            $wx['Nickname'] = '(' . mb_substr($wx['SerialNum'], -4) . ')' . $wx['Nickname'];
        }

        return self::returnArray(1, 'ok', $wxs, ['Start' => $start, 'Num' => $num]);
    }

    /**
     * 左侧微信好友消息
     */
    public static function leftWxFriendMessages($client_id, $requestData)
    {
        // 参数:昵称
        // 头像 / 昵称 / 最新消息时间 / 未读条数 / 最后一条消息
        // {"WeixinID":"1", "Nickname":"xxx", "Start":"0", "Num":"20"}
        if (!isset($requestData['Weixin'])) {
            return self::returnArray(0, '微信号必填');
        }
        $weixinAccount = trim($requestData['Weixin']);
        $weixin = Weixin::getInfoByWeixin($weixinAccount);
        if (empty($weixin)) {
            return self::returnArray(0, '微信号非法');
        }
        $wxId = $weixin['WeixinID'];

        Gateway::bindUid($client_id, $weixinAccount);

        $start = isset($requestData['Start']) ? (int)$requestData['Start'] : 0;
        $start = $start >= 0 ? $start : 0;
        $num = isset($requestData['Num']) ? (int)$requestData['Num'] : 0;
        $num = $num > 0 ? $num : 20;
        $nickname = isset($requestData['Nickname']) ? trim($requestData['Nickname']) : '';

        $db = self::getDb();

        $conn = $db->from('weixin_friends')->select('weixin_friends.*,messages.AddDate as LastMsgTime,messages.Content as LastMsgContent,messages.MsgType as LastMsgType')
            ->where('WeixinID = :WeixinID')->bindValue('WeixinID', $wxId)
//            ->where('IsDeleted = 0')
            ->where('Account != :Account')->bindValue('Account', $weixinAccount);
        if ($nickname !== '') {
            $conn->where('Nickname like :Nickname')->bindValue('Nickname', '%'.$nickname.'%');
        }
        $conn->leftJoin('messages', 'messages.MessageID=weixin_friends.LastMsgID');
        $wxFriends = $conn->orderByDESC(['LastMsgTime'])
            ->limit($num)->offset($start)->query();
        foreach ($wxFriends as &$friend) {
            if ($friend['LastMsgID'] == 0) {
                $friend['LastMsgType'] = 0;
                $friend['LastMsgContent'] = '';
                $friend['LastMsgTime'] = '0000-00-00 00:00:00';
            }
        }

        return self::returnArray(1, '获取成功', $wxFriends, ['Start' => $start, 'Num' => $num]);
    }

    /**
     * 左侧微信好友消息
     */
    public static function openLeftWxFriendMessages($client_id, $requestData)
    {
        // 参数:昵称  , 好友标签CategoryID
        // 头像 / 昵称 / 最新消息时间 / 未读条数 / 最后一条消息
        // {"TaskType":"OpenWxLeftList","Data":{"AdminID":"1", "Weixin":"wx_xx", "CategoryID":"12", "Nickname":"xxx", "Start":"0", "Num":"20"}}
        if (!isset($requestData['AdminID'])) {
            return self::returnArray(0, 'adminid require');
        }
        $adminId = Helper_OpenEncrypt::decrypt($requestData['AdminID']);
        if ($adminId === false) {
            return self::returnArray(0, 'adminid require');
        }
        $requestWeixin = isset($requestData['Weixin']) && trim($requestData['Weixin']) !== '' ? trim($requestData['Weixin']) : '';

        $db = self::getDb();

        // todo: 判断微信是否为当前管理员的

        $weixinAccounts = $weixinIds = [];
        if (!$requestWeixin) {
            $adminWxs = $db->from('weixins')->select('WeixinID,Weixin,Alias,Nickname,AvatarUrl')
                ->where('find_in_set(:AdminID,YyAdminID)')->bindValue('AdminID', $adminId)->query();
            foreach ($adminWxs as $adminWx) {
                $weixinAccounts[$adminWx['WeixinID']] = $adminWx['Weixin'];
                $weixinIds[] = $adminWx['WeixinID'];
            }
            if (empty($weixinAccounts)) {
                return self::returnArray(0, '此adminid没有管理的微信号');
            }
        } else {
            $weixin = Weixin::getInfoByWeixin($requestWeixin);
            if (empty($weixin)) {
                return self::returnArray(0, '微信号非法');
            }
            $weixinIds[] = $weixin['WeixinID'];
            $weixinAccounts[$weixin['WeixinID']] = $requestWeixin;
        }

        Gateway::bindUid($client_id, $adminId);

        $start = isset($requestData['Start']) ? (int)$requestData['Start'] : 0;
        $start = $start >= 0 ? $start : 0;
        $num = isset($requestData['Num']) ? (int)$requestData['Num'] : 0;
        $num = $num > 0 ? $num : 20;
        $nickname = isset($requestData['Nickname']) ? trim($requestData['Nickname']) : '';
        $categoryID = isset($requestData['CategoryID']) && intval($requestData['CategoryID']) != 0 ? intval($requestData['CategoryID']) : 0;

        $conn = $db->from('weixin_friends')->select('weixin_friends.*,messages.SenderWx,messages.AddDate as LastMsgTime,messages.Content as LastMsgContent,messages.MsgType as LastMsgType,messages.ReadStatus as ReadStatus')
            ->where("WeixinID in ({$db->inWhereArrToStr($weixinIds)})")
//            ->where('IsDeleted = 0')
            ->where("Account not in ({$db->inWhereArrToStr($weixinAccounts)})");
        if ($nickname !== '') {
            $conn->where('Nickname like :Nickname')->bindValue('Nickname', '%'.$nickname.'%');
        }
        if($categoryID){
            $conn->where('FIND_IN_SET(:CategoryID, weixin_friends.CategoryIDs)')->bindValue('CategoryID', $categoryID);
        }
        $conn->leftJoin('messages', 'messages.MessageID=weixin_friends.LastMsgID');
        $wxFriends = $conn->orderByDESC(['DisplayOrder desc'])->orderByDESC(['LastMsgTime'])
            ->limit($num)->offset($start)->query();
        foreach ($wxFriends as &$friend) {
            if ($friend['LastMsgID'] == 0) {
                $friend['LastMsgType'] = 0;
                $friend['LastMsgContent'] = '';
                $friend['LastMsgTime'] = '0000-00-00 00:00:00';
            } else {
                if ($friend['ReadStatus'] == 'UNREAD') {
                    $friend['UnreadNum'] = 1;
                }
            }
            $friend['Weixin'] = $weixinAccounts[$friend['WeixinID']];
        }

        return self::returnArray(1, 'ok', $wxFriends, ['Start' => $start, 'Num' => $num]);
    }

    /**
     * 左侧群消息
     */
    public static function leftWxGroupMessages($client_id, $requestData)
    {
        if (!isset($requestData['WeixinID'])) {
            return self::returnArray(0, '微信id必填');
        }
        $wxId = (int)$requestData['WeixinID'];
        Gateway::bindUid($client_id, $wxId);

        $start = isset($requestData['Start']) ? (int)$requestData['Start'] : 0;
        $start = $start >= 0 ? $start : 0;
        $num = isset($requestData['Num']) ? (int)$requestData['Num'] : 0;
        $num = $num > 0 ? $num : 20;
        $nickname = isset($requestData['Nickname']) ? trim($requestData['Nickname']) : '';

        $db = self::getDb();

        $conn = $db->from('weixin_in_groups')->select()->where('WeixinID = :WeixinID')->bindValue('WeixinID', $wxId);

        // todo:
//        if ($nickname !== '') {
//            $conn->where('Nickname like :Nickname')->bindValue('Nickname', '%'.$nickname.'%');
//        }
        $wxGroups = $conn->orderByDESC(['UnreadNum desc'])->orderByDESC(['LastMsgID'])
            ->limit($num)->offset($start)->query();
        foreach ($wxGroups as &$group) {
            $group['LastMsgType'] = 0;
            $group['LastMsgContent'] = '';
            $group['LastMsgTime'] = '0000-00-00 00:00:00';
            $group['LastMsgSender'] = '';
            if ($group['LastMsgID'] > 0) {
                // 查询出最后一条消息 及 发送时间
                $msg = $db->from('messages')->select()
                    ->where('MessageID = :MessageID')
                    ->bindValues(['MessageID' => $group['LastMsgID']])
                    ->row();
                if (!empty($msg)) {
                    $friend['LastMsgType'] = $msg['MsgType'];
                    $friend['LastMsgContent'] = $msg['Content'];
                    $friend['LastMsgTime'] = $msg['AddDate'];
                    $group['LastMsgSender'] = $msg['SenderWx'];
                }
            }
        }

        return self::returnArray(1, '获取成功', $wxGroups, ['Start' => $start, 'Num' => $num]);
    }

    /**
     * 右侧获取消息
     */
    public static function rightChatGetMessages($client_id, $requestData)
    {
        // {"Weixin":"wx_xxxx", "GetFrom":"wx_xxx", "IsGroup":"N", "Start":"0", "Num":"20"}
        if (!isset($requestData['Weixin'])) {
            return self::returnArray(0, '微信必填');
        }
        if (!isset($requestData['GetFrom']) || trim($requestData['GetFrom']) === '') {
            return self::returnArray(0, 'GetFrom必填');
        }
        $weixinAccount = trim($requestData['Weixin']);
        $weixin = Weixin::getInfoByWeixin($weixinAccount);
        if (empty($weixin)) {
            return self::returnArray(0, '微信号非法');
        }
        $wxId = (int)$weixin['WeixinID'];

        Gateway::bindUid($client_id, $weixinAccount);

        $db = self::getDb();

        $getFrom = trim($requestData['GetFrom']);

        $isGroup = isset($requestData['IsGroup']) && in_array($requestData['IsGroup'], ['Y', 'N']) ? $requestData['IsGroup'] : 'N';
        $start = isset($requestData['Start']) ? (int)$requestData['Start'] : 0;
        $start = $start >= 0 ? $start : 0;
        $num = isset($requestData['Num']) ? (int)$requestData['Num'] : 0;
        $num = $num > 0 ? $num : 20;

        if ($isGroup == 'N') {
            $wxFriend = $db->from('weixin_friends')->select()
                ->where('WeixinID = :WeixinID')->bindValue('WeixinID', $wxId)
                ->where('Account = :Account')->bindValue('Account', $getFrom)
//                ->where('IsDeleted = 0')
                ->row();
            if (empty($wxFriend)) {
                return self::returnArray(0, '微信'.$weixinAccount.'与'.$getFrom.'不是好友');
            }

            $friendAccount = $wxFriend['Account'];

            $messages = $db->from('messages')->select()
                ->where("SenderWx in ('{$weixinAccount}', '{$friendAccount}')")
                ->where("ReceiverWx in ('{$weixinAccount}', '{$friendAccount}')")
                ->where('GroupID = 0')
                ->limit($num)->offset($start)
                ->orderByDESC(['MessageID'])->query();
            foreach ($messages as &$msg) {
                if ($msg['ReceiverWx'] != $weixinAccount) {
                    $msg['Nickname'] = $weixin['Nickname'];
                    $msg['AvatarUrl'] = $weixin['AvatarUrl'];
                } else {
                    $msg['Nickname'] = $wxFriend['NickName'];
                    $msg['AvatarUrl'] = $wxFriend['Avatar'];
                }
            }
            $friendId = $wxFriend['FriendID'];
            // 更新好友表的未读数量
            $db->update('weixin_friends')->cols(['UnreadNum' => 0])->where('UnreadNum > 0')->where("FriendID = '{$friendId}'")->query();
            // 更新消息表中的未读状态
            $db->update('messages')->cols(['ReadStatus' => 'READ'])->where("ReadStatus = 'UNREAD'")
                ->where("SenderWx in ('{$weixinAccount}', '{$friendAccount}')")
                ->where("ReceiverWx in ('{$weixinAccount}', '{$friendAccount}')")
                ->where('GroupID = 0')
                ->query();
        } else {
            $wxGroup = $db->from('weixin_in_groups')->select()
                ->where('WeixinID = :WeixinID')->bindValue('WeixinID', $wxId)
                ->where('GroupID = :GroupID')->bindValue('GroupID', $getFrom)
                ->row();
            if (empty($wxGroup)) {
                return self::returnArray(0, '微信id'.$wxId.'不在'.$getFrom.'群中');
            }

            $messages = $db->from('messages')->select()
                ->where("(SenderWx = '{$wxId}') and GroupID = '{$getFrom}'")
                ->orWhere("(ReceiverWx = '{$wxId}' and GroupID = '{$getFrom}')")
                ->limit($num)->offset($start)
                ->orderByDESC(['MessageID'])->query();
            foreach ($messages as &$msg) {
                if ($msg['SenderWx'] == $weixinAccount) {
                    $msg['Nickname'] = $weixin['Nickname'];
                    $msg['AvatarUrl'] = $weixin['AvatarUrl'];
                } else {
                    // todo:
                    $msg['Nickname'] = '';
                    $msg['AvatarUrl'] = '';
                }
            }

            $groupId = $wxGroup['ID'];
            $db->update('weixin_in_groups')->cols(['UnreadNum' => 0])->where('UnreadNum > 0')->where("ID = '{$groupId}'")->query();
        }

        return self::returnArray(1, '获取成功', $messages, ['Start' => $start, 'Num' => $num]);
    }

    /**
     * 右侧获取消息
     */
    public static function openRightChatGetMessages($client_id, $requestData)
    {
        // {"AdminID":"1", "Weixin":"wx_xxxx", "GetFrom":"wx_xxx", "IsGroup":"N", "Start":"0", "Num":"20"}
        if (!isset($requestData['AdminID'])) {
            return self::returnArray(0, 'adminid require');
        }
        $adminId = Helper_OpenEncrypt::decrypt($requestData['AdminID']);
        if ($adminId === false) {
            return self::returnArray(0, 'adminid require');
        }
        $word = isset($requestData['Word']) && trim($requestData['Word']) !== '' ? trim($requestData['Word']) : '';

        // todo: 判断微信是否为当前管理员的

        if (!isset($requestData['Weixin'])) {
            return self::returnArray(0, '微信必填');
        }
        if (!isset($requestData['GetFrom']) || trim($requestData['GetFrom']) === '') {
            return self::returnArray(0, 'GetFrom必填');
        }
        $weixinAccount = trim($requestData['Weixin']);
        $weixin = Weixin::getInfoByWeixin($weixinAccount);
        if (empty($weixin)) {
            return self::returnArray(0, '微信号非法');
        }
        $wxId = (int)$weixin['WeixinID'];

        Gateway::bindUid($client_id, $adminId);

        $slaveDb = self::getSlaveDb();
        $db = self::getDb();

        $getFrom = trim($requestData['GetFrom']);

        $isGroup = isset($requestData['IsGroup']) && in_array($requestData['IsGroup'], ['Y', 'N']) ? $requestData['IsGroup'] : 'N';
        $start = isset($requestData['Start']) ? (int)$requestData['Start'] : 0;
        $start = $start >= 0 ? $start : 0;
        $num = isset($requestData['Num']) ? (int)$requestData['Num'] : 0;
        $num = $num > 0 ? $num : 20;

        if ($isGroup == 'N') {
            $wxFriend = $db->from('weixin_friends')->select()
                ->where('WeixinID = :WeixinID')->bindValue('WeixinID', $wxId)
                ->where('Account = :Account')->bindValue('Account', $getFrom)
//                ->where('IsDeleted = 0')
                ->row();
            if (empty($wxFriend)) {
                return self::returnArray(0, '微信'.$weixinAccount.'与'.$getFrom.'不是好友');
            }

            $friendAccount = $wxFriend['Account'];

            $messages = $db->from('messages')->select()
                ->where("SenderWx in ('{$weixinAccount}', '{$friendAccount}')")
                ->where("ReceiverWx in ('{$weixinAccount}', '{$friendAccount}')")
                ->where('GroupID = 0')
                ->limit($num)->offset($start)
                ->orderByDESC(['MessageID'])->query();
            foreach ($messages as &$msg) {
                if ($msg['ReceiverWx'] != $weixinAccount) {
                    $msg['Nickname'] = $weixin['Nickname'];
                    $msg['AvatarUrl'] = $weixin['AvatarUrl'];
                } else {
                    $msg['Nickname'] = $wxFriend['NickName'];
                    $msg['AvatarUrl'] = $wxFriend['Avatar'];
                }
                // 是否有监控词
                $msg['HasMonitorWord'] = 'N';
                if ($msg['MsgType'] == 1 && $word !== '' && false !== strpos($msg['Content'], $word)) {
                    $msg['HasMonitorWord'] = 'Y';
                }
            }
            $friendId = $wxFriend['FriendID'];
            // 更新未读消息数量
            $db->update('weixin_friends')->cols(['UnreadNum' => 0])->where('UnreadNum > 0')->where("FriendID = '{$friendId}'")->query();
            // 更新消息表中的未读状态
            $db->update('messages')->cols(['ReadStatus' => 'READ'])->where("ReadStatus = 'UNREAD'")
                ->where("SenderWx in ('{$weixinAccount}', '{$friendAccount}')")
                ->where("ReceiverWx in ('{$weixinAccount}', '{$friendAccount}')")
                ->where('GroupID = 0')
                ->query();
        } else {
            $wxGroup = $db->from('weixin_in_groups')->select()
                ->where('WeixinID = :WeixinID')->bindValue('WeixinID', $wxId)
                ->where('GroupID = :GroupID')->bindValue('GroupID', $getFrom)
                ->row();
            if (empty($wxGroup)) {
                return self::returnArray(0, '微信id'.$wxId.'不在'.$getFrom.'群中');
            }

            $messages = $db->from('messages')->select()
                ->where("(SenderWx = '{$wxId}') and GroupID = '{$getFrom}'")
                ->orWhere("(ReceiverWx = '{$wxId}' and GroupID = '{$getFrom}')")
                ->limit($num)->offset($start)
                ->orderByDESC(['MessageID'])->query();
            foreach ($messages as &$msg) {
                if ($msg['SenderWx'] == $weixinAccount) {
                    $msg['Nickname'] = $weixin['Nickname'];
                    $msg['AvatarUrl'] = $weixin['AvatarUrl'];
                } else {
                    // todo:
                    $msg['Nickname'] = '';
                    $msg['AvatarUrl'] = '';
                }
            }

            $groupId = $wxGroup['ID'];
            $db->update('weixin_in_groups')->cols(['UnreadNum' => 0])->where('UnreadNum > 0')->where("ID = '{$groupId}'")->query();
        }

        return self::returnArray(1, '获取成功', $messages, ['Start' => $start, 'Num' => $num]);
    }

    /**
     *
     */
    public static function rightChatSendMessage($client_id, $requestData)
    {
        $db = self::getDb();

        // {"Weixin":"wx_xxx", "SendToWx":"wx_yyy", "IsGroup":"N", "Content":"xxx", "MsgType":"1"}
        if (!isset($requestData['Weixin'])) {
            return self::returnArray(0, '微信必填');
        }
        $device = $db->select()->from('devices')
            ->where("OnlineWeixin = '{$requestData['Weixin']}'")
            ->row();
        if (empty($device)) {
            return self::returnArray(0, '请先上线手机端');
        }
        if (!isset($requestData['SendTo']) || trim($requestData['SendTo']) === '') {
            return self::returnArray(0, '发送方必填');
        }
        if (!isset($requestData['Content']) || trim($requestData['Content']) === '') {
            return self::returnArray(0, '发送内容必填');
        }
        if (!isset($requestData['MsgType']) || !in_array((int)$requestData['MsgType'], [1, 2, 3, 4, 5, 6, 7, 8])) {
            return self::returnArray(0, '发送内容类型非法');
        }
        if (trim($requestData['Weixin']) == $requestData['SendTo']) {
            return self::returnArray(0, '不允许给自己发消息');
        }

        $weixinAccount = trim($requestData['Weixin']);
        $weixin = Weixin::getInfoByWeixin($weixinAccount);
        if (empty($weixin)) {
            return self::returnArray(0, '微信号非法');
        }
        $wxId = (int)$weixin['WeixinID'];

        Gateway::bindUid($client_id, $weixinAccount);

        $isGroup = isset($requestData['IsGroup']) && in_array($requestData['IsGroup'], ['Y', 'N']) ? $requestData['IsGroup'] : 'N';
        $sendToWx = trim($requestData['SendTo']);
        $content = trim($requestData['Content']);
        $msgType = (int)$requestData['MsgType'];

        $weixin = $db->from('weixins')->select()
            ->where('WeixinID = :WeixinID')
            ->bindValue('WeixinID', $wxId)
            ->row();
        if (empty($weixin)) {
            return self::returnArray(0, 'WeixinID非法');
        }

        if ($isGroup == 'N') {
            $wxFriend = $db->select()->from('weixin_friends')
                ->where('WeixinID = :WeixinID')
                ->where('Account = :Account')
                ->where('IsDeleted = 0')
                ->bindValues(['Account' => $sendToWx, 'WeixinID' => $wxId])
                ->row();
            if (empty($wxFriend)) {
                return self::returnArray(0, '微信id'.$wxId.'与'.$sendToWx.'不是好友');
            }

            $mobileClientId = $device['ClientID'];
            $data = [
                'MessageID' => "",
                'ChatroomID' => "",
                'WxAccount' => $sendToWx,
                'content' => strtr($content, [
                    '#日期#' => date('Y-m-d'),
                    '#用户昵称#' => $wxFriend['NickName']
                ]),
//                'content' => Helper_Until::replaceMsgContent($content, $weixin, $sendToWx),
                'type' => $requestData['MsgType']
            ];
            if ($data['type'] == 2 && substr($data['content'], 0, 4) == 'http' && false === strpos($data['content'], '?')) {
                $data['content'] .= '?imageView2/2/w/500';
            }
            $response = json_encode(['TaskCode' => TASK_CODE_SEND_CHAT_MSG, 'Data' => $data]);
            $res = Gateway::sendToClient($mobileClientId, $response);
            if (!$res) {
                self::returnArray(0, '发送失败');
            }
            $returnData = [
                'AvatarUrl' => $weixin['AvatarUrl'],
                'Content' => $data['content'],
                'IsBigImg' => $data['type'] == 2 ? 'Y' : 'N',
                'MsgType' => $data['type'],
                'MessageID' => '',
                'Nickname' => $weixin['Nickname'],
                'SenderWx' => $weixinAccount,
                'SendTime' => date('Y-m-d H:i:s'),
                'ReceiverWx' => $sendToWx
            ];
        } else {
            $wxGroup = $db->from('weixin_in_groups')->select()
                ->where('WeixinID = :WeixinID')->bindValue('WeixinID', $wxId)
                ->where('GroupID = :GroupID')->bindValue('GroupID', $sendToWx)
                ->row();
            if (empty($wxGroup)) {
                return self::returnArray(0, '微信id'.$wxId.'不在'.$sendToWx.'群中');
            }

            $data = [
                'GroupID' => $sendToWx,
                'ReceiverWx' => '',
                'SenderWx' => $wxId,
                'MsgType' => $msgType,
                'Content' => $content,
                'WxMsgSvrId' => '',
                // 此处直接写成微秒整数,因为我们服务器时间不一定和微信服务器时间一致,聊天结果根据上下文实际不受影响
                'WxCreateTime' => time().'000',
                'AddDate' => date('Y-m-d H:i:s'),
                'SendStatus' => 'UNSEND',
                'SendTime' => '0000-00-00 00:00:00'
            ];
            $returnData = [];
        }

        return self::returnArray(1, '发送成功', $returnData);
    }

    /**
     * 发送聊天消息
     */
    public static function openRightChatSendMessage($client_id, $requestData)
    {
        if (!isset($requestData['AdminID'])) {
            return self::returnArray(0, 'adminid require');
        }
        $adminId = Helper_OpenEncrypt::decrypt($requestData['AdminID']);
        if ($adminId === false) {
            return self::returnArray(0, 'adminid require');
        }

        $db = self::getDb();

        // {"Weixin":"wx_xxx", "SendToWx":"wx_yyy", "IsGroup":"N", "Content":"xxx", "MsgType":"1", "QueueID":"1"}
        if (!isset($requestData['Weixin'])) {
            return self::returnArray(0, '微信必填');
        }
        $device = $db->select()->from('devices')
            ->where("OnlineWeixin = '{$requestData['Weixin']}'")
            ->where("ClientID != ''")
            ->row();
        if (empty($device)) {
            return self::returnArray(0, '请先上线手机端');
        }
        if (!isset($requestData['SendTo']) || trim($requestData['SendTo']) === '') {
            return self::returnArray(0, '发送方必填');
        }
        if (!isset($requestData['Content']) || trim($requestData['Content']) === '') {
            return self::returnArray(0, '发送内容必填');
        }
        if (!isset($requestData['MsgType']) || !in_array((int)$requestData['MsgType'], [1, 2, 3, 4, 5, 6, 7, 8])) {
            return self::returnArray(0, '发送内容类型非法');
        }
        if (trim($requestData['Weixin']) == $requestData['SendTo']) {
            return self::returnArray(0, '不允许给自己发消息');
        }

        $weixinAccount = trim($requestData['Weixin']);
        $weixin = Weixin::getInfoByWeixin($weixinAccount);
        if (empty($weixin)) {
            return self::returnArray(0, '微信号非法');
        }
        $wxId = (int)$weixin['WeixinID'];

        $isGroup = isset($requestData['IsGroup']) && in_array($requestData['IsGroup'], ['Y', 'N']) ? $requestData['IsGroup'] : 'N';
        $sendToWx = trim($requestData['SendTo']);
        $content = trim($requestData['Content']);
        $msgType = (int)$requestData['MsgType'];
        $queueId = isset($requestData['QueueID']) ? (int)$requestData['QueueID'] : 0;

        $weixin = $db->from('weixins')->select()
            ->where('WeixinID = :WeixinID')
            ->bindValue('WeixinID', $wxId)
            ->row();
        if (empty($weixin)) {
            return self::returnArray(0, 'WeixinID非法');
        }

        Gateway::bindUid($client_id, $adminId);

        if ($isGroup == 'N') {
            $wxFriend = $db->select()->from('weixin_friends')
                ->where('WeixinID = :WeixinID')
                ->where('Account = :Account')
                ->where('IsDeleted = 0')
                ->bindValues(['Account' => $sendToWx, 'WeixinID' => $wxId])
                ->row();
            if (empty($wxFriend)) {
                return self::returnArray(0, '微信id'.$wxId.'与'.$sendToWx.'不是好友');
            }

            $content = strtr($content, [
                '#日期#' => date('Y-m-d'),
                '#用户昵称#' => $wxFriend['NickName']
            ]);
            $returnData = [
                'AvatarUrl' => $weixin['AvatarUrl'],
                'Content' => $content,
                'IsBigImg' => $requestData['MsgType'] == 2 ? 'Y' : 'N',
                'MsgType' => $requestData['MsgType'],
                'MessageID' => '',
                'Nickname' => $weixin['Nickname'],
                'SenderWx' => $weixinAccount,
                'SendTime' => date('Y-m-d H:i:s'),
                'ReceiverWx' => $sendToWx,
                'Status' => 1,
                'AudioMp3' => $requestData['MsgType'] == 6 ? $content : '',
                'AudioText' => ''
            ];

            $needSend = false;
            if ($queueId > 0) {
                $queue = $db->select()->from('message_queues')->where('QueueID = :QueueID')->bindValue('QueueID', $queueId)->row();
                if (!isset($queue['QueueID'])) {
                    return self::returnArray(0, 'queueId invalid');
                }
                $returnData['Status'] = $queue['Status'];
                $returnData['QueueID'] = $queueId;
                if ($queue['Status'] == 3) {
                    // 如果是成功状态, 则直接成功
                    return self::returnArray(1, '发送成功,请不要重复发送', $returnData);
                } elseif ($queue['Status'] == 4) {
//                    return self::returnArray(0, '手机异常,请检查手机', $returnData);
                    $needSend = true;
                } elseif ($queue['Status'] == 2) {
                    // 已经发送过,再次下发
                    $needSend = true;
                } else {
                    $needSend = true;
                }
            } else {
                $queueData = [
                    'GroupID' => 0,
                    'ReceiverWx' => $sendToWx,
                    'SenderWx' => $weixinAccount,
                    'SenderWxId' => $wxId,
                    'MsgType' => $requestData['MsgType'],
                    'Content' => $content,
                    'WxMsgSvrId' => '',
                    'WxCreateTime' => '',
                    'SendTime' => date('Y-m-d H:i:s'),
                    'Status' => 1,
                    'ErrMsg' => ''
                ];
                $queueId = $db->insert('message_queues')->cols($queueData)->query();
                $returnData['QueueID'] = $queueId;
                $needSend = true;
            }

            if ($needSend === true) {
                $mobileClientId = $device['ClientID'];
                $data = [
                    'MessageID' => "",
                    'ChatroomID' => "",
                    'WxAccount' => $sendToWx,
                    'content' => $content,
                    'type' => $requestData['MsgType'],
                    'QueueID' => $queueId
                ];
                if ($data['type'] == 2 && substr($data['content'], 0, 4) == 'http' && false === strpos($data['content'], '?')) {
                    $data['content'] .= '?imageView2/2/w/500';
                }
                $response = json_encode(['TaskCode' => TASK_CODE_SEND_CHAT_MSG, 'Data' => $data]);
                $res = Gateway::sendToClient($mobileClientId, $response);
                if (!$res) {
                    self::returnArray(0, '发送失败');
                } else {
                    if ($returnData['Status'] == 1) {
                        $db->update('message_queues')->cols(['Status' => 2, 'SendTime' => date('Y-m-d H:i:s')])->where("QueueID = :QueueID")
                            ->bindValues(["QueueID" => $queueId])->query();
                    }
                    return self::returnArray(1, '发送成功', $returnData);
                }
            }
        } else {
            $wxGroup = $db->from('weixin_in_groups')->select()
                ->where('WeixinID = :WeixinID')->bindValue('WeixinID', $wxId)
                ->where('GroupID = :GroupID')->bindValue('GroupID', $sendToWx)
                ->row();
            if (empty($wxGroup)) {
                return self::returnArray(0, '微信id'.$wxId.'不在'.$sendToWx.'群中');
            }

            $data = [
                'GroupID' => $sendToWx,
                'ReceiverWx' => '',
                'SenderWx' => $wxId,
                'MsgType' => $msgType,
                'Content' => $content,
                'WxMsgSvrId' => '',
                // 此处直接写成微秒整数,因为我们服务器时间不一定和微信服务器时间一致,聊天结果根据上下文实际不受影响
                'WxCreateTime' => time().'000',
                'AddDate' => date('Y-m-d H:i:s'),
                'SendStatus' => 'UNSEND',
                'SendTime' => '0000-00-00 00:00:00'
            ];
            $returnData = [];
            return self::returnArray(1, '发送成功', $returnData);
        }
    }

    /**
     * 设置已读
     */
    public static function openSetRead($client_id, $requestData)
    {
        if (!isset($requestData['AdminID'])) {
            return self::returnArray(0, 'adminid require');
        }
        $adminId = Helper_OpenEncrypt::decrypt($requestData['AdminID']);
        if ($adminId === false) {
            return self::returnArray(0, 'adminid require');
        }

        $weixinAccount = isset($requestData['Weixin']) ? trim($requestData['Weixin']) : '';
        if (empty($weixinAccount)) {
            return self::returnArray(0, 'weixin require');
        }
        $friendWx = isset($requestData['FriendWx']) ? trim($requestData['FriendWx']) : '';
        if (empty($friendWx)) {
            return self::returnArray(0, 'friend require');
        }
        $isGroup = isset($requestData['IsGroup']) && in_array($requestData['IsGroup'], ['Y', 'N']) ? $requestData['IsGroup'] : 'N';

        $weixin = Weixin::getInfoByWeixin($weixinAccount);
        if (empty($weixin)) {
            return self::returnArray(0, '微信号非法');
        }

        Gateway::bindUid($client_id, $adminId);

        $db = self::getDb();

        if ($isGroup == 'N') {
            $wxFriend = $db->from('weixin_friends')->select()
                ->where('WeixinID = :WeixinID')->bindValue('WeixinID', $weixin['WeixinID'])
                ->where('Account = :Account')->bindValue('Account', $friendWx)
                ->where('IsDeleted = 0')
                ->row();
            if (empty($wxFriend)) {
                return self::returnArray(0, '微信'.$weixin.'与'.$friendWx.'不是好友');
            }

            // 更新未读消息数量
            $db->update('weixin_friends')->cols(['UnreadNum' => 0])->where('UnreadNum > 0')->where("FriendID = '{$wxFriend['FriendID']}'")->query();
            // 更新消息表中的未读状态
            $db->update('messages')->cols(['ReadStatus' => 'READ'])->where("ReadStatus = 'UNREAD'")
                ->where("SenderWx in ('{$weixinAccount}', '{$friendWx}')")
                ->where("ReceiverWx in ('{$weixinAccount}', '{$friendWx}')")
                ->where('GroupID = 0')
                ->query();
        } else {

        }

        return self::returnArray(1, 'ok');
    }

    /**
     * @param $client_id
     * @param $requestData
     * @return array
     * WEB 左侧好友列表上方 好友所拥有的标签列表
     */
    public static function openFriendCategoryList($client_id, $requestData){
        // {"TaskType":"OpenWxLeftCategoryList","Data":{"AdminID":"1", "Weixin":"wx_xx"}}
        if (!isset($requestData['AdminID'])) {
            return self::returnArray(0, 'adminid require');
        }
        $adminId = Helper_OpenEncrypt::decrypt($requestData['AdminID']);
        if ($adminId === false) {
            return self::returnArray(0, 'adminid require');
        }
        $db = self::getDb();
        $admin = $db->from('admins')->select('AdminID,IsSuper,CompanyId,DepartmentID')
            ->where('AdminID = :AdminID')->bindValue('AdminID', $adminId)
            ->row();
        if(empty($admin)){
            return self::returnArray(0, '未找到管理员信息');
        }
        $requestWeixin = isset($requestData['Weixin']) && trim($requestData['Weixin']) !== '' ? trim($requestData['Weixin']) : '';


        $weixinIds = [];
        if (!$requestWeixin) {
            //获取当前登录管理员所有的个号微信
            $adminWxs = $db->from('weixins')->select('WeixinID,Weixin,Alias,Nickname,AvatarUrl')
                ->where('find_in_set(:AdminID,YyAdminID)')->bindValue('AdminID', $adminId)->query();
            foreach ($adminWxs as $adminWx) {
                $weixinIds[] = $adminWx['WeixinID'];
            }
            if (empty($weixinIds)) {
                return self::returnArray(0, '此adminid没有管理的微信号');
            }
        } else {
            $weixin = Weixin::getInfoByWeixin($requestWeixin);
            if (empty($weixin)) {
                return self::returnArray(0, '微信号非法');
            }
            $weixinIds[] = $weixin['WeixinID'];
        }

        Gateway::bindUid($client_id, $adminId);

        // todo 先查出当前管理员所在部门/公司的所有好友标签
        $cSelect = $db->from('categories')->select('CategoryID,Name')
            //->join('weixin_friends as wf', 'FIND_IN_SET(c.CategoryID, wf.CategoryIDs)')
            ->where('Type = :Type')->bindValue('Type', 'WX_FRIEND')
            ->where('Platform = :Platform')->bindValue('Platform', 'OPEN');
        if($admin['DepartmentID']){
            $cSelect->where('DepartmentID = :DepartmentID')->bindValue('DepartmentID', $admin['DepartmentID']);
        }else{
            $cSelect->where('CompanyId = :CompanyId')->bindValue('CompanyId', $admin['CompanyId']);
        }
        $categories = $cSelect->query();

        //->where("wf.WeixinID in ({$db->inWhereArrToStr($weixinIds)})")->where("wf.CategoryIDs != ''")

        return self::returnArray(1, 'ok', $categories);
    }
}