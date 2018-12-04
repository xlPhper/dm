<?php

require_once dirname(__FILE__) . "/Base.php";
use \GatewayWorker\Lib\Gateway;

class MonitorWord extends Base
{
    public static $table = 'monitor_words';

    /**
     * 监控词列表
     */
    public static function openMonitorWordList($client_id, $requestData)
    {
        if (!isset($requestData['AdminID'])) {
            return self::returnArray(0, 'adminid require');
        }
        $adminId = Helper_OpenEncrypt::decrypt($requestData['AdminID']);
        if ($adminId === false) {
            return self::returnArray(0, 'adminid require');
        }

        Gateway::bindUid($client_id, $adminId);

        $db = self::getDb();
        $slaveDb = self::getSlaveDb();

        $words = $db->from('monitor_words')->select()
            ->where('AdminID = :AdminID')->bindValue('AdminID', $adminId)
            ->query();

        $weixinAccounts = [];
        $adminWxs = $db->from('weixins')->select('Weixin')
            ->where('AdminID = :AdminID')->bindValue('AdminID', $adminId)->query();
        foreach ($adminWxs as $adminWx) {
            $weixinAccounts[] = $adminWx['Weixin'];
        }

        foreach ($words as &$word) {
            $wx['HasUnreadMsg'] = 'N';
            if (!empty($weixinAccounts)) {
                $unreadMsg = $db->from('messages')->select('MessageID')
                    ->where("(SenderWx in ({$db->inWhereArrToStr($weixinAccounts)}) or ReceiverWx in ({$db->inWhereArrToStr($weixinAccounts)}))")
                    ->where("ReadStatus = 'UNREAD'")
                    ->where('GroupID = 0')
                    ->where("Content like '%" . $word['Word'] ."%'")
                    ->row();
                $word['HasUnreadMsg'] = isset($unreadMsg['MessageID']) ? 'Y' : 'N';
            }
        }

        return self::returnArray(1, 'ok', $words);
    }

    /**
     * 监控词好友
     */
    public static function openMonitorWordFriends($client_id, $requestData)
    {
        if (!isset($requestData['AdminID'])) {
            return self::returnArray(0, 'adminid require');
        }
        $adminId = Helper_OpenEncrypt::decrypt($requestData['AdminID']);
        if ($adminId === false) {
            return self::returnArray(0, 'adminid require');
        }
        $word = isset($requestData['Word']) && trim($requestData['Word']) !== '' ? trim($requestData['Word']) : '';
        if ($word === '') {
            return self::returnArray(0, 'word require');
        }
        $nickname = isset($requestData['Nickname']) && trim($requestData['Nickname']) !== '' ? trim($requestData['Nickname']) : '';
        $requestWeixin = isset($requestData['Weixin']) && trim($requestData['Weixin']) !== '' ? trim($requestData['Weixin']) : '';

        $start = isset($requestData['Start']) ? (int)$requestData['Start'] : 0;
        $start = $start >= 0 ? $start : 0;
        $num = isset($requestData['Num']) ? (int)$requestData['Num'] : 0;
        $num = $num > 0 ? $num : 20;

        $db = self::getDb();

        $weixinAccounts = $weixinIds = $wxIdAccounts = [];
        if ($requestWeixin) {
            $adminWx = $db->from('weixins')->select('WeixinID,Weixin,Alias,Nickname,AvatarUrl')
                ->where('Weixin = :Weixin')->bindValue('Weixin', $requestWeixin)->row();
            if (!isset($adminWx['WeixinID'])) {
                return self::returnArray(0, '微信号非法');
            }
            $weixinAccounts[] = $adminWx['Weixin'];
            $weixinIds[] = $adminWx['WeixinID'];
            $wxIdAccounts[$adminWx['WeixinID']] = $adminWx['Weixin'];
        } else {
            $adminWxs = $db->from('weixins')->select('WeixinID,Weixin,Alias,Nickname,AvatarUrl')
                ->where('AdminID = :AdminID')->bindValue('AdminID', $adminId)->query();
            foreach ($adminWxs as $adminWx) {
                $weixinAccounts[] = $adminWx['Weixin'];
                $weixinIds[] = $adminWx['WeixinID'];
                $wxIdAccounts[$adminWx['WeixinID']] = $adminWx['Weixin'];
            }
            if (empty($weixinAccounts)) {
                return self::returnArray(0, '此adminid没有管理的微信号');
            }
        }

        Gateway::bindUid($client_id, $adminId);

        $time = date('Y-m-d H:i:s', strtotime('-3 days'));
        $time = date('Y-m-d H:i:s', strtotime('-30 days'));
        $categoryID = isset($requestData['CategoryID']) && intval($requestData['CategoryID']) != 0 ? intval($requestData['CategoryID']) : 0;

        $messageSql = "select distinct(SenderWx) from messages where ReceiverWx in ({$db->inWhereArrToStr($weixinAccounts)})
 and MsgType = 1 and Content like '%{$word}%' and AddDate >= '{$time}'";
        $conn = $db->from('weixin_friends')->select()
            ->where("WeixinID in ({$db->inWhereArrToStr($weixinIds)})")
            ->where('IsDeleted = 0')
            ->where("Account not in ({$db->inWhereArrToStr($weixinAccounts)})")
            ->where("Account in ({$messageSql})");

        if ($nickname !== '') {
            $conn->where('NickName like :Nickname')->bindValue('Nickname', '%'.$nickname.'%');
        }
        if($categoryID){
            $conn->where('FIND_IN_SET(:CategoryID, CategoryIDs)')->bindValue('CategoryID', $categoryID);
        }
        $friends = $conn->orderByDESC(['DisplayOrder desc'])->orderByDESC(['LastMsgID'])
            ->limit($num)->offset($start)->query();

        foreach ($friends as &$friend) {
            $msg = $db->from('messages')->select()
                ->where("((ReceiverWx = '{$wxIdAccounts[$friend['WeixinID']]}' and SenderWx = '{$friend['Account']}') or (SenderWx = '{$wxIdAccounts[$friend['WeixinID']]}' and ReceiverWx = '{$friend['Account']}'))")
//                ->where('ReceiverWx = :ReceiverWx')->bindValue('ReceiverWx', $wxIdAccounts[$friend['WeixinID']])
//                ->where('SenderWx = :SenderWx')->bindValue('SenderWx', $friend['Account'])
                ->where('AddDate >= :AddDate')->bindValue('AddDate', $time)
                ->where('GroupID = 0')
                ->orderByDESC(['MessageID'])->row();

            // todo: 监控词未读标识
            $friend['LastMsgType'] = 1;
            $friend['LastMsgContent'] = $msg['Content'];
            $friend['LastMsgTime'] = $msg['AddDate'];

            $friend['Weixin'] = $wxIdAccounts[$friend['WeixinID']];
        }

        return self::returnArray(1, '获取成功', $friends, ['Start' => $start, 'Num' => $num]);
    }

    /**
     * 监控词消息
     */
    public static function openMonitorWordMsgs($client_id, $requestData)
    {
        if (!isset($requestData['AdminID'])) {
            return self::returnArray(0, 'adminid require');
        }
        $adminId = Helper_OpenEncrypt::decrypt($requestData['AdminID']);
        if ($adminId === false) {
            return self::returnArray(0, 'adminid require');
        }

        $word = isset($requestData['Word']) && trim($requestData['Word']) !== '' ? trim($requestData['Word']) : '';
        if ($word === '') {
            return self::returnArray(0, 'word require');
        }

        $start = isset($requestData['Start']) ? (int)$requestData['Start'] : 0;
        $start = $start >= 0 ? $start : 0;
        $num = isset($requestData['Num']) ? (int)$requestData['Num'] : 0;
        $num = $num > 0 ? $num : 20;

        $db = self::getDb();

        // todo: 判断微信是否为当前管理员的

        $weixinAccounts = $weixinIds = [];
        $adminWxs = $db->from('weixins')->select('WeixinID,Weixin,Alias,Nickname,AvatarUrl')
            ->where('AdminID = :AdminID')->bindValue('AdminID', $adminId)->query();
        foreach ($adminWxs as $adminWx) {
            $weixinAccounts[] = $adminWx['Weixin'];
            $weixinIds[] = $adminWx['WeixinID'];
        }
        if (empty($weixinAccounts)) {
            return self::returnArray(0, '此adminid没有管理的微信号');
        }

        Gateway::bindUid($client_id, $adminId);

        $messages = $db->from('messages')->select()
            ->where("ReceiverWx in ({$db->inWhereArrToStr($weixinAccounts)})")
            ->where('MsgType = :MsgType')->bindValue('MsgType', 1)
            ->where('Content like :Content')->bindValue('Content', '%'.$word.'%')
            ->where('AddDate >= :AddDate')->bindValue('AddDate', date('Y-m-d H:i:s', strtotime('-3 days')))
            ->limit($num)->offset($start)
            ->orderByDESC(['MessageID'])->query();
        foreach ($messages as &$msg) {
//            if ($msg['ReceiverWx'] != $weixinAccount) {
//                $msg['Nickname'] = $weixin['Nickname'];
//                $msg['AvatarUrl'] = $weixin['AvatarUrl'];
//            } else {
//                $msg['Nickname'] = $wxFriend['NickName'];
//                $msg['AvatarUrl'] = $wxFriend['Avatar'];
//            }
        }

        return self::returnArray(1, '获取成功', $messages, ['Start' => $start, 'Num' => $num]);
    }

    /**
     * 添加监控词
     */
    public static function openMonitorWordAdd($client_id, $requestData)
    {
        if (!isset($requestData['AdminID'])) {
            return self::returnArray(0, 'adminid require');
        }
        $adminId = Helper_OpenEncrypt::decrypt($requestData['AdminID']);
        if ($adminId === false) {
            return self::returnArray(0, 'adminid require');
        }

        $word = isset($requestData['Word']) && trim($requestData['Word']) !== '' ? trim($requestData['Word']) : '';
        if ($word === '') {
            return self::returnArray(0, 'word require');
        }

        Gateway::bindUid($client_id, $adminId);

        $db = self::getDb();

        $count = $db->select('count(1) as total')->from('monitor_words')
            ->where('Word = :Word')->bindValue('Word', $word)
            ->where('AdminID = :AdminID')->bindValue('AdminID', $adminId)
            ->row();
        if ($count['total'] > 8) {
            return self::returnArray(0, 'max num is 9');
        }

        $wordInDb = $db->select()->from('monitor_words')
            ->where('Word = :Word')->bindValue('Word', $word)
            ->where('AdminID = :AdminID')->bindValue('AdminID', $adminId)
            ->row();
        if (!isset($wordInDb['WordID'])) {
            $insertData = [
                'AdminID' => $adminId,
                'Word' => $word,
                'CreateTime' => date('Y-m-d H:i:s')
            ];
            $insertId = $db->insert('monitor_words')->cols($insertData)->query();
        } else {
            $insertId = (int)$wordInDb['WordID'];
        }

        if ($insertId > 0) {
            return self::returnArray(1, 'add ok');
        } else {
            return self::returnArray(1, 'add err');
        }
    }

    /**
     * 删除监控词
     */
    public static function openMonitorWordDel($client_id, $requestData)
    {
        if (!isset($requestData['AdminID'])) {
            return self::returnArray(0, 'adminid require');
        }
        $adminId = Helper_OpenEncrypt::decrypt($requestData['AdminID']);
        if ($adminId === false) {
            return self::returnArray(0, 'adminid require');
        }

        $wordId = isset($requestData['WordID']) && intval($requestData['WordID']) > 0 ? intval($requestData['WordID']) : 0;
        if ($wordId < 1) {
            return self::returnArray(0, 'wordid require');
        }

        Gateway::bindUid($client_id, $adminId);

        $db = self::getDb();
        $count = $db->delete('monitor_words')
            ->where('WordID = :WordID')->bindValue('WordID', $wordId)
            ->where('AdminID = :AdminID')->bindValue('AdminID', $adminId)
            ->limit(1)
            ->query();
        if ($count > 0) {
            return self::returnArray(1, 'del ok');
        } else {
            return self::returnArray(0, 'del err');
        }
    }
}