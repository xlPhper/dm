<?php

require_once APPLICATION_PATH . '/controllers/Open/OpenBase.php';

class Open_AlbumController extends OpenBase
{


    /**
     * 朋友圈列表（移动端专用整合接口）
     */
    public function albumListAction()
    {
        $actionType = $this->_getParam('ActionType');//请求类型：1：全部，2：我的：3：相册列表
        $timeSpace = $this->_getParam('TimeSpace');//时间区间：1：一小时，2：24小时，3：一天内

        $time = '';
        if($timeSpace ==1 ){
            $time = date('Y-m-d H:i:s', time()-3600);
        }
        if($timeSpace ==2 ){
            $time = date('Y-m-d H:i:s', time()-86400);
        }
        if($timeSpace ==3 ){
            $time = date('Y-m-d H:i:s', time()-3*86400);
        }

        $data = [];
        if($actionType == 1){
            $data = $this->allListAction($time);
        }
        if($actionType == 2){
            $data = $this->myListAction($time);
        }
        if($actionType == 3){
            $data = $this->otherListAction($time);
        }

        $this->responseJson(self::STATUS_OK, '操作成功', $data);
    }

    public function allListAction($date = null)
    {
        $page = $this->_getParam('Page', 1);
        $pagesize = $this->_getParam('Pagesize', 20);

        // 参数: 微信号/管理员/标签1,标签2/在线状态(离/在线)
        $inputWeixins = trim($this->_getParam('Weixins', ''));
        $nickname = trim($this->_getParam('Nickname', ''));
        $tagIds = trim($this->_getParam('TagIDs', ''));
        $type = intval($this->_getParam('Type'));

        $weixins = [];
        if ($inputWeixins !== '') {
            $inputWeixins = explode(',', $inputWeixins);
            $weixins = array_unique($inputWeixins);
        }

        $aModel = new Model_Album();
        $arModel = new Model_AlbumReply();
        $wxfModel = new Model_Weixin_Friend();
        $wxModel = new Model_Weixin();

        $time = date('Y-m-d H:i:s', time()-7*86400);
        if($date){
            $time = $date;
        }
        $select = $aModel->fromSlaveDB()->select()->from('albums as a')
            ->setIntegrityCheck(false)
            ->where('a.DeleteTime = ?', '0000-00-00 00:00:00')
            ->where('a.AddDate > ?', $time);
        // 此处是为了防止不必要的表连接
        $tmpWeixins = [];
        if ($weixins || $nickname !== '' || $tagIds !== '') {
            $select->joinLeft('weixins as w', 'a.Weixin = w.Weixin', ['w.WeixinID', 'w.Weixin as InnerWeixin']);
        } else {
            $s = $wxModel->select()->from('weixins', ['WeixinID', 'Nickname', 'AvatarUrl','Weixin']);
            $weixinsInDb = $wxModel->fetchAll($s)->toArray();
            $tmpWxs = [];
            foreach ($weixinsInDb as $wx) {
                $tmpWxs[] = $wx['Weixin'];
                $wx['NickName'] = $wx['Nickname'];
                $wx['Avatar'] = $wx['AvatarUrl'];
                $tmpWeixins[$wx['Weixin']] = $wx;
            }
            if ($tmpWxs) {
                $select->where('Weixin in (?)', $tmpWxs);
            }
        }
        if ($weixins) {
            $select->where('w.Weixin in (?) or w.Alias in (?)', $weixins);
        }
        if ($nickname !== '') {
            $select->where('w.NickName like ?', '%'.$nickname.'%');
        }
        if ($tagIds !== '') {
            $tagIds = explode(',', $tagIds);
            $tagIds = array_unique($tagIds);
            $conditions = [];
            foreach ($tagIds as $tagId) {
                if ($tagId > 0) {
                    $conditions[] = 'find_in_set(' . $tagId . ', w.YyCategoryIds)';
                }
            }
            if ($conditions) {
                $select->where(implode(' or ', $conditions));
            }
        }
        // 朋友圈类型
        if ($type > 0) {
            // todo:
        }

        $select->order('a.AddDate desc')->order('a.AlbumID desc');

        $res = $aModel->getResult($select, $page, $pagesize);

        $tmpWxFriendAccounts = [];
        foreach ($res['Results'] as &$d) {
            $weixin = $d['Weixin'];
            if (!isset($tmpWeixins[$weixin])) {
                $ss = $wxModel->fromSlaveDB()->select()
                    ->from('weixins', ['WeixinID', 'Nickname', 'AvatarUrl','Weixin'])
                    ->where('Weixin = ?', $weixin);
                $wx = $wxModel->fetchRow($ss)->toArray();
                $wx['NickName'] = $wx['Nickname'];
                $wx['Avatar'] = $wx['AvatarUrl'];
                $tmpWeixins[$weixin] = $wx;
            } else {
                $wx = $tmpWeixins[$weixin];
            }
            $wxId = $wx['WeixinID'];

            $d['NickName'] = $wx['NickName'];
            $d['AvatarUrl'] = $wx['Avatar'];
            // 只显示自己是否已经点赞
//            $d['IsLike'] = $arModel->isLikeByWeixin($d['AlbumID'], $d['Weixin']);
            $d['IsLike'] = 'N';
            $d['Comments'] = $arModel->getCommentsByAlbumId($d['AlbumID'], []);
            foreach ($d['Comments'] as &$c) {
                if (!isset($tmpWeixins[$c['Replier']])) {
                    $tmpWx = $wxfModel->fetchRow(['WeixinID = ?' => $wxId, 'Account = ?' => $c['Replier']]);
                    $tmpWeixins[$c['Replier']] = $tmpWx;
                }
                $c['ReplierNickName'] = $tmpWeixins[$c['Replier']]['NickName'] ?? ($c['Replier'] == $weixin ? $wx['NickName'] : '');
                $c['ReplierAvatar'] = $tmpWeixins[$c['Replier']]['Avatar'] ?? ($c['Replier'] == $weixin ? $wx['Avatar'] : '');

                if (!isset($tmpWeixins[$c['Receiver']])) {
                    $tmpWx = $wxfModel->fetchRow(['WeixinID = ?' => $wxId, 'Account = ?' => $c['Receiver']]);
                    $tmpWeixins[$c['Receiver']] = $tmpWx;
                }
                $c['ReceiverNickName'] = $tmpWeixins[$c['Receiver']]['NickName'] ?? ($c['Receiver'] == $weixin ? $wx['NickName'] : '');
                $c['ReceiverAvatar'] = $tmpWeixins[$c['Receiver']]['Avatar'] ?? ($c['Receiver'] == $weixin ? $wx['Avatar'] : '');
            }
        }

        $this->showJson(self::STATUS_OK, '操作成功', $res);
    }


    /**
     * 我的朋友圈列表
     */
    public function myListAction($date = null)
    {
        $page = $this->_getParam('Page', 1);
        $pageSize = $this->_getParam('Pagesize', 20);
        $weixin = trim($this->_getParam('Weixin', ''));
        $wxModel = new Model_Weixin();
        $wxIds = [];
        if ($weixin !== '') {
            $wx = $wxModel->getInfoByWeixin($weixin);
            if (!$wx) {
                $this->showJson(self::STATUS_FAIL, '微信非法');
            }
            $wxIds[] = (int)$wx['WeixinID'];
        } else {
            $wxIds = $this->adminWxIds;
        }

        if (!$wxIds) {
            $this->showJson(self::STATUS_FAIL, '没有管理的微信号');
        }
        $wxs = $wxModel->fromSlaveDB()->fetchAll(['WeixinID in (?)' => $wxIds])->toArray();
        $tmpWeixins = [];
        $weixinAccounts = [];
        foreach ($wxs as $wx) {
            $wx['NickName'] = $wx['Nickname'];
            $wx['Avatar'] = $wx['AvatarUrl'];
            $tmpWeixins[$wx['Weixin']] = $wx;
            $weixinAccounts[] = $wx['Weixin'];
        }
        $aModel = new Model_Album();
        $arModel = new Model_AlbumReply();
        $wxfModel = new Model_Weixin_Friend();

        $wxFriends = [];
        foreach ($wxIds as $wxId) {
            $s = $wxfModel->select()->from($wxfModel->getTableName(), ['Account'])->where('WeixinID = ?', $wxId);
            $wxFriends[$wxId] = $wxfModel->fromSlaveDB()->getDb()->fetchCol($s);
        }

        $select = $aModel->fromSlaveDB()->select()
            ->where('Weixin in (?)', $weixinAccounts)
            ->where('DeleteTime = ?', '0000-00-00 00:00:00')
            ->order('AddDate desc')
            ->order('AlbumID desc');

        if($date ){
            $select ->where('AddDate > ?', $date);
        }

        $res = $aModel->getResult($select, $page, $pageSize);

        foreach ($res['Results'] as &$d) {
            $wx = $tmpWeixins[$d['Weixin']];
            $wxId = $wx['WeixinID'];
            $d['NickName'] = $wx['NickName'];
            $d['AvatarUrl'] = $wx['AvatarUrl'];
            // 只显示自己是否已经点赞
            $d['IsLike'] = $arModel->isLikeByWeixin($d['AlbumID'], $weixin);
            if (isset($wxFriends[$wxId])) {
                $d['Comments'] = $arModel->getCommentsByAlbumId($d['AlbumID'], $wxFriends[$wxId]);
            } else {
                $d['Comments'] = [];
            }
            foreach ($d['Comments'] as &$c) {
                if (!isset($tmpWeixins[$c['Replier']])) {
                    $tmpWx = $wxfModel->fetchRow(['WeixinID = ?' => $wxId, 'Account = ?' => $c['Replier']]);
                    $tmpWeixins[$c['Replier']] = $tmpWx;
                }
                $c['ReplierNickName'] = $tmpWeixins[$c['Replier']]['NickName'] ?? ($c['Replier'] == $weixin ? $wx['NickName'] : '');
                $c['ReplierAvatar'] = $tmpWeixins[$c['Replier']]['Avatar'] ?? ($c['Replier'] == $weixin ? $wx['Avatar'] : '');

                if (!isset($tmpWeixins[$c['Receiver']])) {
                    $tmpWx = $wxfModel->fetchRow(['WeixinID = ?' => $wxId, 'Account = ?' => $c['Receiver']]);
                    $tmpWeixins[$c['Receiver']] = $tmpWx;
                }
                $c['ReceiverNickName'] = $tmpWeixins[$c['Receiver']]['NickName'] ?? ($c['Receiver'] == $weixin ? $wx['NickName'] : '');
                $c['ReceiverAvatar'] = $tmpWeixins[$c['Receiver']]['Avatar'] ?? ($c['Receiver'] == $weixin ? $wx['Avatar'] : '');
            }
        }

        $this->showJson(self::STATUS_OK, '操作成功', $res);
    }

    /**
     * 朋友的相册列表
     */
    public function otherListAction($date = null)
    {
        $page = $this->_getParam('Page', 1);
        $pageSize = $this->_getParam('Pagesize', 20);
        $weixin = trim($this->_getParam('Weixin', ''));
        $wxModel = Model_Weixin::getInstance();
        $wxIds = [];
        if ($weixin !== '') {
            $wx = $wxModel->getInfoByWeixin($weixin);
            if (!$wx) {
                $this->showJson(self::STATUS_FAIL, '微信非法');
            }
            $wxIds[] = (int)$wx['WeixinID'];
        } else {
            $wxIds = $this->adminWxIds;
        }
        if (!$wxIds) {
            $this->showJson(self::STATUS_FAIL, '没有管理的微信号');
        }
        $wxs = $wxModel->fromSlaveDB()->fetchAll(['WeixinID in (?)' => $wxIds])->toArray();
        $tmpWeixins = [];
        $weixinAccounts = [];
        foreach ($wxs as $wx) {
            $wx['NickName'] = $wx['Nickname'];
            $wx['Avatar'] = $wx['AvatarUrl'];
            $tmpWeixins[$wx['Weixin']] = $wx;
            $weixinAccounts[] = $wx['Weixin'];
        }
        $aModel = new Model_Album();
        $arModel = new Model_AlbumReply();
        $wxfModel = new Model_Weixin_Friend();

        $wxFriends = $wxFriendAccounts = [];
        foreach ($wxIds as $wxId) {
            $s = $wxfModel->select()->from($wxfModel->getTableName(), ['Account'])->where('WeixinID = ?', $wxId);
            $f = $wxfModel->fromSlaveDB()->getDb()->fetchCol($s);
            $wxFriends[$wxId] = $f;
            $wxFriendAccounts = array_merge($wxFriendAccounts, $f);
        }
        $wxFriendAccounts = array_unique($wxFriendAccounts);
        if (!$wxFriendAccounts) {
            $this->showJson(self::STATUS_FAIL, '没有发现微信好友');
        }

        // 头像/昵称/微信号/内容/是否点赞/评论内容/发圈时间
        $select = $aModel->fromSlaveDB()->select()
            ->where('Weixin in (?)', $wxFriendAccounts)
            ->where('DeleteTime = ?', '0000-00-00 00:00:00')
            ->order('AddDate desc')
            ->order('AlbumID desc');
        if($date ){
            $select ->where('AddDate > ?', $date);
        }


        $res = $aModel->getResult($select, $page, $pageSize);

        foreach ($res['Results'] as &$d) {

            if (!isset($tmpWeixins[$d['Weixin']])) {
                $wx = $wxfModel->fetchRow(['WeixinID in (?)' => $wxIds, 'Account = ?' => $d['Weixin']]);
                $tmpWeixins[$d['Weixin']] = $wx;
                $wx = $tmpWeixins[$d['Weixin']];
                $wxId = $wx['WeixinID'];
            } else {
                $wx = $tmpWeixins[$d['Weixin']];
                $wxId = $wx['WeixinID'];
            }

            $d['NickName'] = $tmpWeixins[$d['Weixin']]['NickName'];
            $d['AvatarUrl'] = $tmpWeixins[$d['Weixin']]['Avatar'];
            //
            $d['IsLike'] = $arModel->isLikeByWeixin($d['AlbumID'], $weixin);
            $d['Comments'] = $arModel->getCommentsByAlbumId($d['AlbumID'], $wxFriendAccounts);
            foreach ($d['Comments'] as &$c) {
                if (!isset($tmpWeixins[$c['Replier']])) {
                    $tmpWx = $wxfModel->fetchRow(['WeixinID = ?' => $wxId, 'Account = ?' => $c['Replier']]);
                    $tmpWeixins[$c['Replier']] = $tmpWx;
                }
                $c['ReplierNickName'] = $tmpWeixins[$c['Replier']]['NickName'] ?? ($c['Replier'] == $weixin ? $wx['NickName'] : '');
                $c['ReplierAvatar'] = $tmpWeixins[$c['Replier']]['Avatar'] ?? ($c['Replier'] == $weixin ? $wx['Avatar'] : '');

                if (!isset($tmpWeixins[$c['Receiver']])) {
                    $tmpWx = $wxfModel->fetchRow(['WeixinID = ?' => $wxId, 'Account = ?' => $c['Receiver']]);
                    $tmpWeixins[$c['Receiver']] = $tmpWx;
                }
                $c['ReceiverNickName'] = $tmpWeixins[$c['Receiver']]['NickName'] ?? ($c['Receiver'] == $weixin ? $wx['NickName'] : '');
                $c['ReceiverAvatar'] = $tmpWeixins[$c['Receiver']]['Avatar'] ?? ($c['Receiver'] == $weixin ? $wx['Avatar'] : '');
            }
        }

        $this->showJson(self::STATUS_OK, '操作成功', $res);
    }

    /**
     * 点赞与取消点赞接口
     */
    public function likeAction()
    {
        $albumId = (int)$this->_getParam('AlbumID', 0);
        if ($albumId < 1) {
            $this->showJson(self::STATUS_FAIL, '朋友圈id非法');
        }
        $weixin = trim($this->_getParam('Weixin', ''));
        if ($weixin === '') {
            $this->showJson(self::STATUS_FAIL, '点赞人微信必填');
        }
        $onlineWeixinDevice = (new Model_Device())->getDeviceByWeixin($weixin);
        if (!$onlineWeixinDevice) {
            $this->showJson(self::STATUS_FAIL, '请先上线微信');
        }
        $like = $this->_getParam('Like');
        if ($like != 'Y' && $like != 'N') {
            $this->showJson(self::STATUS_FAIL, '点赞参数非法');
        }

        try {
            $aModel = new Model_Album();
            $wxfModel = new Model_Weixin_Friend();
            $arModel = new Model_AlbumReply();
            $wxModel = new Model_Weixin();
            $album = $aModel->fetchRow(['AlbumID = ?' => $albumId]);
            if (!$album) {
                $this->showJson(self::STATUS_FAIL, '朋友圈id不存在');
            }
            // 如果不是自己, 则看看是不是朋友关系
            if ($album['Weixin'] != $weixin) {
                $wx = $wxModel->getInfoByWeixin($weixin);
                if (!$wx) {
                    $this->showJson(self::STATUS_FAIL, '微信不存在');
                }
                $wxf = $wxfModel->getUser($wx['WeixinID'], $weixin);
                if (!$wxf) {
                    $this->showJson(self::STATUS_FAIL, '非朋友关系');
                }
            }

            // {"TaskCode":"MsgBigImg","Data":{"SvrId":"xxx","Weixin":"xxx"}"}
            if ($like == 'Y') {
                $data = [
                    'TaskCode' => TASK_CODE_ALBUM_LIKE,
                    'Data' => [
                        'ToWxSerID' => $album['WxSerID'],
                        'NeedReport' => 'Y',
                        'ReportApi' => 'album/tran-comment',
                    ],
                ];
            } else {
                $data = [
                    'TaskCode' => TASK_CODE_ALBUM_UNLIKE,
                    'Data' => [
                        'ToWxSerID' => $album['WxSerID'],
                        'NeedReport' => 'Y',
                        'ReportApi' => 'album/tran-comment',
                    ],
                ];
            }

            $res = Helper_Gateway::initConfig()->sendToClient($onlineWeixinDevice['ClientID'], json_encode($data));
            if (!$res) {
                $this->showJson(self::STATUS_FAIL, '操作失败');
            }
        } catch (\Exception $e) {
            $this->showJson(self::STATUS_FAIL, '操作失败,err:'.$e->getMessage());
        }

        $this->showJson(self::STATUS_OK, '操作成功,请等待手机端同步后刷新');
    }

    public function batchLikeAction()
    {
        $albumIds = trim($this->_getParam('AlbumIDs', ''));
        if ($albumIds === '') {
            $this->showJson(self::STATUS_FAIL, '朋友圈ids非法');
        }
        $albumIds = explode(',', $albumIds);
        $tmpAlbumIds = [];
        foreach ($albumIds as $albumId) {
            $albumId = (int)$albumId;
            if ($albumId > 0) {
                $tmpAlbumIds[] = $albumId;
            }
        }
        if (empty($tmpAlbumIds)) {
            $this->showJson(self::STATUS_FAIL, '朋友圈ids非法');
        }

        $weixin = trim($this->_getParam('Weixin', ''));
        if ($weixin === '') {
            $this->showJson(self::STATUS_FAIL, '点赞人微信必填');
        }
        $onlineWeixinDevice = (new Model_Device())->getDeviceByWeixin($weixin);
        if (!$onlineWeixinDevice) {
            $this->showJson(self::STATUS_FAIL, '请先上线微信');
        }
        $like = $this->_getParam('Like');
        if ($like != 'Y' && $like != 'N') {
            $this->showJson(self::STATUS_FAIL, '点赞参数非法');
        }

        $sendOkAlbumIds = $sendErrAlbumIds = [];

        $aModel = new Model_Album();
        $wxfModel = new Model_Weixin_Friend();
        $wxModel = new Model_Weixin();
        try {
            $sendDatas = [];
            foreach ($tmpAlbumIds as $albumId) {
                $album = $aModel->fetchRow(['AlbumID = ?' => $albumId]);
                if (!$album) {
                    $this->showJson(self::STATUS_FAIL, '朋友圈id不存在');
                }
                // 如果不是自己, 则看看是不是朋友关系
                if ($album['Weixin'] != $weixin) {
                    $wx = $wxModel->getInfoByWeixin($weixin);
                    if (!$wx) {
                        $this->showJson(self::STATUS_FAIL, '微信不存在');
                    }
                    $wxf = $wxfModel->getUser($wx['WeixinID'], $weixin);
                    if (!$wxf) {
                        $this->showJson(self::STATUS_FAIL, '非朋友关系');
                    }
                }

                // {"TaskCode":"MsgBigImg","Data":{"SvrId":"xxx","Weixin":"xxx"}"}
                if ($like == 'Y') {
                    $data = [
                        'TaskCode' => TASK_CODE_ALBUM_LIKE,
                        'Data' => [
                            'ToWxSerID' => $album['WxSerID'],
                            'NeedReport' => 'Y',
                            'ReportApi' => 'album/tran-comment',
                        ],
                    ];
                } else {
                    $data = [
                        'TaskCode' => TASK_CODE_ALBUM_UNLIKE,
                        'Data' => [
                            'ToWxSerID' => $album['WxSerID'],
                            'NeedReport' => 'Y',
                            'ReportApi' => 'album/tran-comment',
                        ],
                    ];
                }
                $sendDatas[$albumId] = $data;
            }

            foreach ($sendDatas as $albumId => $data) {
                $res = Helper_Gateway::initConfig()->sendToClient($onlineWeixinDevice['ClientID'], json_encode($data));
                if (!$res) {
                    $sendErrAlbumIds[] = $albumId;
                } else {
                    $sendOkAlbumIds[] = $albumId;
                }
            }
        } catch (\Exception $e) {
            $this->showJson(self::STATUS_FAIL, '操作失败,err:'.$e->getMessage());
        }

        if (empty($sendErrAlbumIds)) {
            $this->showJson(self::STATUS_OK, '操作成功,请等待手机端同步后刷新');
        } elseif (empty($sendOkAlbumIds)) {
            $this->showJson(self::STATUS_OK, '操作失败,没有发送成功');
        } else {
            $this->showJson(self::STATUS_OK, '朋友圈' . implode(',', $sendOkAlbumIds).'发送成功,'.implode(',', $sendErrAlbumIds).'发送失败');
        }
    }

    /**
     * 评论接口
     */
    public function commentAction()
    {
        $albumId = (int)$this->_getParam('AlbumID', 0);
        if ($albumId < 1) {
            $this->showJson(self::STATUS_FAIL, '朋友圈id非法');
        }
        $weixin = trim($this->_getParam('Weixin', ''));
        if ($weixin === '') {
            $this->showJson(self::STATUS_FAIL, '评论人微信必填');
        }
        $onlineWeixinDevice = (new Model_Device())->getDeviceByWeixin($weixin);
        if (!$onlineWeixinDevice) {
            $this->showJson(self::STATUS_FAIL, '请先上线微信');
        }
        $content = trim($this->_getParam('Content', ''));
        if ($content === '') {
            $this->showJson(self::STATUS_FAIL, '评论内容不能为空');
        }
        $receiver = trim($this->_getParam('Receiver', ''));
        $replyId = (int)$this->_getParam('ReplyID');

        $aModel = new Model_Album();
        $arModel = new Model_AlbumReply();
        $wxModel = new Model_Weixin();
        $wxfModel = new Model_Weixin_Friend();

        if ($replyId > 0) {
            $reply = $arModel->fetchRow(['ReplyID = ?' => $replyId]);
            if (!$reply) {
                $this->showJson(self::STATUS_FAIL, '评论id非法');
            }
            if ($reply['WxReplySerID'] <= 0) {
                $this->showJson(self::STATUS_FAIL, '此评论未被同步过');
            }
        }

        $album = $aModel->fetchRow(['AlbumID = ?' => $albumId]);
        if (!$album) {
            $this->showJson(self::STATUS_FAIL, '朋友圈id不存在');
        }

        $wx = $wxModel->getInfoByWeixin($weixin);
        if (!$wx) {
            $this->showJson(self::STATUS_FAIL, '评论人微信不存在');
        }
        // 如果 receiver 是空, 则看评论人是否和主人是朋友关系
        // 如果不为空, 则看评论人是否是 receiver 是朋友关系
        if (empty($receiver)) {
            if ($weixin != $album['Weixin']) {
                $wxf = $wxfModel->getUser($wx['WeixinID'], $weixin);
                if (!$wxf) {
                    $this->showJson(self::STATUS_FAIL, '非朋友关系');
                }
            }
        } else {
            if ($receiver != $weixin) {
                $wxf = $wxfModel->getUser($wx['WeixinID'], $receiver);
                if (!$wxf) {
                    $this->showJson(self::STATUS_FAIL, '非朋友关系');
                }
            } else {
                $this->showJson(self::STATUS_FAIL, '自己不能回复自己');
            }
        }

        $data = [
            'TaskCode' => TASK_CODE_ALBUM_COMMENT,
            'Data' => [
                'ToWxSerID' => $album['WxSerID'],
                'ToComSerID' => $replyId > 0 ? $reply['WxReplySerID'] : '',
                'NeedReport' => 'Y',
                'Content' => $content,
                'ReportApi' => 'album/tran-comment',
            ],
        ];

        $res = Helper_Gateway::initConfig()->sendToClient($onlineWeixinDevice['ClientID'], json_encode($data));
        if (!$res) {
            $this->showJson(self::STATUS_FAIL, '操作失败');
        }

        $this->showJson(self::STATUS_OK, '操作成功,请等待手机端同步后刷新');

    }

    /**
     * 评论接口
     */
    public function batchCommentAction()
    {
        $albumIds = trim($this->_getParam('AlbumIDs', ''));
        if ($albumIds === '') {
            $this->showJson(self::STATUS_FAIL, '朋友圈ids非法');
        }
        $albumIds = explode(',', $albumIds);
        $tmpAlbumIds = [];
        foreach ($albumIds as $albumId) {
            $albumId = (int)$albumId;
            if ($albumId > 0) {
                $tmpAlbumIds[] = $albumId;
            }
        }
        if (empty($tmpAlbumIds)) {
            $this->showJson(self::STATUS_FAIL, '朋友圈ids非法');
        }
        $weixin = trim($this->_getParam('Weixin', ''));
        if ($weixin === '') {
            $this->showJson(self::STATUS_FAIL, '评论人微信必填');
        }
        $onlineWeixinDevice = (new Model_Device())->getDeviceByWeixin($weixin);
        if (!$onlineWeixinDevice) {
            $this->showJson(self::STATUS_FAIL, '请先上线微信');
        }
        $content = trim($this->_getParam('Content', ''));
        if ($content === '') {
            $this->showJson(self::STATUS_FAIL, '评论内容不能为空');
        }

        $sendOkAlbumIds = $sendErrAlbumIds = [];

        $aModel = new Model_Album();
        $wxfModel = new Model_Weixin_Friend();
        $wxModel = new Model_Weixin();
        try {
            $sendDatas = [];
            foreach ($tmpAlbumIds as $albumId) {
                $album = $aModel->fetchRow(['AlbumID = ?' => $albumId]);
                if (!$album) {
                    $this->showJson(self::STATUS_FAIL, '朋友圈id不存在');
                }
                // 如果不是自己, 则看看是不是朋友关系
                if ($album['Weixin'] != $weixin) {
                    $wx = $wxModel->getInfoByWeixin($weixin);
                    if (!$wx) {
                        $this->showJson(self::STATUS_FAIL, '微信不存在');
                    }
                    $wxf = $wxfModel->getUser($wx['WeixinID'], $weixin);
                    if (!$wxf) {
                        $this->showJson(self::STATUS_FAIL, '非朋友关系');
                    }
                }

                $data = [
                    'TaskCode' => TASK_CODE_ALBUM_COMMENT,
                    'Data' => [
                        'ToWxSerID' => $album['WxSerID'],
                        'ToComSerID' => '',
                        'NeedReport' => 'Y',
                        'Content' => $content,
                        'ReportApi' => 'album/tran-comment',
                    ],
                ];

                $sendDatas[$albumId] = $data;
            }

            foreach ($sendDatas as $albumId => $data) {
                $res = Helper_Gateway::initConfig()->sendToClient($onlineWeixinDevice['ClientID'], json_encode($data));
                if (!$res) {
                    $sendErrAlbumIds[] = $albumId;
                } else {
                    $sendOkAlbumIds[] = $albumId;
                }
            }
        } catch (\Exception $e) {
            $this->showJson(self::STATUS_FAIL, '操作失败,err:'.$e->getMessage());
        }

        if (empty($sendErrAlbumIds)) {
            $this->showJson(self::STATUS_OK, '操作成功,请等待手机端同步后刷新');
        } elseif (empty($sendOkAlbumIds)) {
            $this->showJson(self::STATUS_OK, '操作失败,没有发送成功');
        } else {
            $this->showJson(self::STATUS_OK, '朋友圈' . implode(',', $sendOkAlbumIds).'发送成功,'.implode(',', $sendErrAlbumIds).'发送失败');
        }
    }

    /**
     * 删除评论接口
     */
    public function comDelAction()
    {
        $replyId = (int)$this->_getParam('ReplyID');
        if ($replyId < 1) {
            $this->showJson(self::STATUS_FAIL, '评论id必填');
        }

        $arModel = new Model_AlbumReply();
        $ar = $arModel->fetchRow(['ReplyID = ?' => $replyId]);
        if (!$ar) {
            $this->showJson(self::STATUS_FAIL, '评论id非法');
        }

        $weixin = $ar['Replier'];
        $onlineWeixinDevice = (new Model_Device())->getDeviceByWeixin($weixin);
        if (!$onlineWeixinDevice) {
            $this->showJson(self::STATUS_FAIL, '请先上线评论人微信');
        }

        $album = (new Model_Album())->fetchRow(['AlbumID = ?' => $ar['AlbumID']]);

        $data = [
            'TaskCode' => TASK_CODE_ALBUM_COMMENT_DEL,
            'Data' => [
                'ToWxSerID' => $album['WxSerID'],
                'ToComSerID' => $ar['WxReplySerID'],
                'NeedReport' => 'Y',
                'ReportApi' => 'album/report',
            ],
        ];

        $res = Helper_Gateway::initConfig()->sendToClient($onlineWeixinDevice['ClientID'], json_encode($data));
        if (!$res) {
            $this->showJson(self::STATUS_FAIL, '操作失败');
        }

        $this->showJson(self::STATUS_OK, '操作成功,请等待手机端同步后刷新');
    }

    /**
     * 删除朋友圈
     */
    public function deleteAction()
    {
        $albumIds = trim($this->_getParam('AlbumID', ''));
        if ($albumIds === '') {
            $this->showJson(self::STATUS_FAIL, '朋友圈ids非法');
        }

        $albumIds = explode(',', $albumIds);
        $tmpAlbumIds = [];
        foreach ($albumIds as $albumId) {
            $albumId = (int)$albumId;
            if ($albumId > 0) {
                $tmpAlbumIds[] = $albumId;
            }
        }
        if (empty($tmpAlbumIds)) {
            $this->showJson(self::STATUS_FAIL, '朋友圈ids非法');
        }

        $aModel = new Model_Album();
        $arModel = new Model_AlbumReply();

        try{
            foreach ($albumIds as $value){
                $albumResult = $aModel->fetchRow(['AlbumID = ?' => $value]);
                $weixin = $albumResult['Weixin'];
                $onlineWeixinDevice = (new Model_Device())->getDeviceByWeixin($weixin);
                if (!$onlineWeixinDevice) {
                    $this->showJson(self::STATUS_FAIL, '请先上线微信');
                }

                $data = [
                    'TaskCode' => TASK_CODE_ALBUM_DELETE,
                    'Data' => [
                        'ToWxSerID' => $albumResult['WxSerID'],
                        'NeedReport' => 'Y',
                        'ReportApi' => 'album/report',
                    ],
                ];

                $res = Helper_Gateway::initConfig()->sendToClient($onlineWeixinDevice['ClientID'], json_encode($data));
                if (!$res) {
                    $this->showJson(self::STATUS_FAIL, '操作失败');
                }
            }

            $this->showJson(self::STATUS_OK, '操作成功,请等待手机端同步后刷新');
        }catch (\Exception $e){
            $this->showJson(self::STATUS_FAIL, '操作失败,err:'.$e->getMessage());
        }

    }

    /**
     * 同步朋友圈
     */
    public function syncAction()
    {
        $type = $this->_getParam('Type', '');
        if (!in_array($type, ['MY', 'OTHER'])) {
            $this->showJson(self::STATUS_FAIL, '类型非法');
        }
        $weixin = trim($this->_getParam('Weixin', ''));
        if ($weixin === '') {
            $this->showJson(self::STATUS_FAIL, '微信必填');
        }
        $onlineWeixinDevice = (new Model_Device())->getDeviceByWeixin($weixin);
        if (!$onlineWeixinDevice) {
            $this->showJson(self::STATUS_FAIL, '请先上线微信');
        }

        /**
         * CREATE TABLE `albums` (
         * `AlbumID` int(10) unsigned NOT NULL AUTO_INCREMENT,
         * `Weixin` varchar(255) NOT NULL DEFAULT '' COMMENT '微信号(wx_123adfa)',
         * `TextContent` varchar(5000) NOT NULL DEFAULT '' COMMENT '文字内容',
         * `Photos` varchar(2000) NOT NULL DEFAULT '' COMMENT '照片,逗号分割',
         * `AddDate` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' COMMENT '发圈时间',
         * `WxSerID` varchar(0) NOT NULL DEFAULT '' COMMENT '微信服务器上的朋友圈id',
         * PRIMARY KEY (`AlbumID`),
         * KEY `Weixin` (`Weixin`) USING BTREE,
         * KEY `AddDate` (`AddDate`) USING BTREE
         * ) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8 COMMENT='朋友圈';
         */
        // todo: notify to client
        // 通知客户端上报七天内的朋友圈
        $taskConfig = json_encode([
            ''
        ]);
//        Model_Task::addCommonTask(TASK_CODE_ALBUM_SYNC, $onlineWeixinDevice['OnlineWeixinID'], $taskConfig, $this->getLoginUserId());

        $this->showJson(self::STATUS_OK, '操作成功');
    }
}