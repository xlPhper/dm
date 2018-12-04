<?php

require_once APPLICATION_PATH . '/controllers/Admin/AdminBase.php';

class Admin_AlbumController extends AdminBase
{
    public function allListAction()
    {
        $page = $this->_getParam('Page', 1);
        $pagesize = $this->_getParam('Pagesize', 20);

        // 参数: 微信号/管理员/标签1,标签2/在线状态(离/在线)
        $inputWeixins = trim($this->_getParam('Weixins', ''));
        $nickname = trim($this->_getParam('Nickname', ''));
        $adminId = intval($this->_getParam('AdminID'));
        $tagIds = trim($this->_getParam('TagIDs', ''));
        $online = trim($this->_getParam('Online', ''));

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
        $select = $aModel->fromSlaveDB()->select()->from('albums as a')
            ->setIntegrityCheck(false)
            ->where('a.DeleteTime = ?', '0000-00-00 00:00:00')
            ->where('a.AddDate > ?', $time);
        // 此处是为了防止不必要的表连接
        $tmpWeixins = [];
        if ($weixins || $nickname !== '' || $adminId > 0 || $tagIds !== '' || in_array($online, ['Y', 'N'])) {
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
        if ($adminId > 0) {
            $select->where('w.AdminID = ?', $adminId);
        }
        if ($tagIds !== '') {
            $tagIds = explode(',', $tagIds);
            $tagIds = array_unique($tagIds);
            $conditions = [];
            foreach ($tagIds as $tagId) {
                if ($tagId > 0) {
                    $conditions[] = 'find_in_set(' . $tagId . ', w.CategoryIds)';
                }
            }
            if ($conditions) {
                $select->where(implode(' or ', $conditions));
            }
        }

        $dModel = new Model_Device();
        $onlineWeixinIds = $dModel->findOnlineWeixin();
        if (in_array($online, ['Y', 'N'])) {
//            $ons = $dModel->select()->from('devices','OnlineWeixinID')
//                ->where("OnlineWeixinID > 0")
//                ->where('Status = ?', 'RUNNING');
            if ($online == 'Y') {
                $select->where('w.WeixinID in (?)', $onlineWeixinIds);
            } else {
                $select->where('w.WeixinID not in (?)', $onlineWeixinIds);
            }
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
//            if (!isset($tmpWxFriendAccounts[$weixin])) {
//                $wxFriends = $wxfModel->fetchAll(['WeixinID = ?' => $wxId]);
//                $wxFriendAccounts = [$weixin];
//                foreach ($wxFriends as $wxFriend) {
//                    $wxFriendAccounts[] = $wxFriend['Account'];
//                }
//                $wxFriendAccounts = array_unique($wxFriendAccounts);
//                $tmpWxFriendAccounts[$weixin] = $wxFriendAccounts;
//            } else {
//                $wxFriendAccounts = $tmpWxFriendAccounts[$weixin];
//            }

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
            if ($online == 'Y' || in_array($wxId, $onlineWeixinIds)) {
                $d['Online'] = 'Y';
            } else {
                $d['Online'] = 'N';
            }
        }

        $this->showJson(self::STATUS_OK, '操作成功', $res);
    }

    public function allOtherListAction()
    {
        $page = $this->_getParam('Page', 1);
        $pagesize = $this->_getParam('Pagesize', 20);

        // 参数: 微信号/管理员/标签1,标签2/在线状态(离/在线)
        $inputWeixins = trim($this->_getParam('Weixins', ''));
        $nickname = trim($this->_getParam('Nickname', ''));
        $adminId = intval($this->_getParam('AdminID'));
        $tagIds = trim($this->_getParam('TagIDs', ''));
        $online = trim($this->_getParam('Online', ''));

        $weixins = [];
        if ($inputWeixins !== '') {
            $inputWeixins = explode(',', $inputWeixins);
            $weixins = array_unique($inputWeixins);
        }

        $aModel = new Model_Album();
        $arModel = new Model_AlbumReply();
        $wxfModel = new Model_Weixin_Friend();
        $wxModel = new Model_Weixin();

        $select = $aModel->select()->from('albums as a')
            ->setIntegrityCheck(false)
            ->where('a.DeleteTime = ?', '0000-00-00 00:00:00');
        // 此处是为了防止不必要的表连接
        if ($weixins || $nickname !== '' || $adminId > 0 || $tagIds !== '' || in_array($online, ['Y', 'N'])) {
            $select->joinLeft('weixins as w', 'a.Weixin = w.Weixin', ['w.WeixinID']);
        } else {
//            $s = $wxfModel->select()->from($wxfModel->getTableName(), ['Account']);
//            $select->where('Weixin in (?)', $s);
        }
        if ($weixins) {
            $select->where('w.Weixin in (?) or w.Alias in (?)', $weixins);
        }
        if ($nickname !== '') {
            $select->where('w.NickName like ?', '%'.$nickname.'%');
        }
        if ($adminId > 0) {
            $select->where('w.AdminID = ?', $adminId);
        }
        if ($tagIds !== '') {
            $tagIds = explode(',', $tagIds);
            $tagIds = array_unique($tagIds);
            $conditions = [];
            foreach ($tagIds as $tagId) {
                if ($tagId > 0) {
                    $conditions[] = 'find_in_set(' . $tagId . ', w.CategoryIds)';
                }
            }
            if ($conditions) {
                $select->where(implode(' or ', $conditions));
            }
        }

        if (in_array($online, ['Y', 'N'])) {
            $ons = (new Model_Device())->select()->from('devices','OnlineWeixinID')
                ->where("OnlineWeixinID > 0")
                ->where('Status = ?', 'RUNNING');
            if ($online == 'Y') {
                $select->where('w.WeixinID in (?)', $ons);
            } else {
                $select->where('w.WeixinID not in (?)', $ons);
            }
        }

        $select->order('a.AddDate desc')->order('a.AlbumID desc');

        $res = $aModel->getResult($select, $page, $pagesize);

        $tmpWxFriendAccounts = [];
        $tmpWeixins = [];
//        foreach ($res['Results'] as &$d) {
//
//        }

        $this->showJson(self::STATUS_OK, '操作成功', $res);
    }

    /**
     * 我的朋友圈列表
     */
    public function myListAction()
    {
        $page = $this->_getParam('Page', 1);
        $pageSize = $this->_getParam('Pagesize', 20);
        $weixin = trim($this->_getParam('Weixin', ''));
        if ($weixin === '') {
            $this->showJson(self::STATUS_FAIL, '微信必填');
        }
        $wxModel = new Model_Weixin();
        $wx = $wxModel->getInfoByWeixin($weixin);
        if (!$wx) {
            $this->showJson(self::STATUS_FAIL, '微信非法');
        }
        $wx['NickName'] = $wx['Nickname'];
        $wx['Avatar'] = $wx['AvatarUrl'];
        $wxId = (int)$wx['WeixinID'];

        $aModel = new Model_Album();
        $arModel = new Model_AlbumReply();
        $wxfModel = new Model_Weixin_Friend();

        $wxFriends = $wxfModel->fetchAll(['WeixinID = ?' => $wx['WeixinID']]);
        $wxFriendAccounts = [$weixin];
        foreach ($wxFriends as $wxFriend) {
            $wxFriendAccounts[] = $wxFriend['Account'];
        }
        $wxFriendAccounts = array_unique($wxFriendAccounts);

        $select = $aModel->fromSlaveDB()->select()
            ->where('Weixin = ?', $weixin)
            ->where('DeleteTime = ?', '0000-00-00 00:00:00')
            ->order('AddDate desc')
            ->order('AlbumID desc');


        $res = $aModel->getResult($select, $page, $pageSize);

        $tmpWeixins = [];
        $tmpWeixins[$weixin] = $wx;
        foreach ($res['Results'] as &$d) {
            $d['NickName'] = $wx['NickName'];
            $d['AvatarUrl'] = $wx['AvatarUrl'];
            // 只显示自己是否已经点赞
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
     * 朋友的相册列表
     */
    public function otherListAction()
    {
        $page = $this->_getParam('Page', 1);
        $pageSize = $this->_getParam('Pagesize', 20);

        $weixin = trim($this->_getParam('Weixin', ''));
        if ($weixin === '') {
            $this->showJson(self::STATUS_FAIL, '微信必填');
        }

        $wxModel = new Model_Weixin();
        $wxfModel = new Model_Weixin_Friend();
        $aModel = new Model_Album();
        $arModel = new Model_AlbumReply();
        $wx = $wxModel->getInfoByWeixin($weixin);
        if (!$wx) {
            $this->showJson(self::STATUS_FAIL, '微信非法');
        }
        $wx['NickName'] = $wx['Nickname'];
        $wx['Avatar'] = $wx['AvatarUrl'];
        $wx['Account'] = $wx['Weixin'];
        $wxId = (int)$wx['WeixinID'];

        $wxFriends = $wxfModel->fetchAll(['WeixinID = ?' => $wx['WeixinID']]);
        $wxFriendAccounts = [$weixin];
        foreach ($wxFriends as $wxFriend) {
            $wxFriendAccounts[] = $wxFriend['Account'];
        }
        $wxFriendAccounts = array_unique($wxFriendAccounts);

        // 头像/昵称/微信号/内容/是否点赞/评论内容/发圈时间
        $select = $aModel->fromSlaveDB()->select()
            ->where('Weixin in (?)', $wxFriendAccounts)
            ->where('DeleteTime = ?', '0000-00-00 00:00:00')
            ->order('AddDate desc')
            ->order('AlbumID desc');

        $res = $aModel->getResult($select, $page, $pageSize);

        $tmpWeixins[$weixin] = $wx;
        foreach ($res['Results'] as &$d) {
            if (!isset($tmpWeixins[$d['Weixin']])) {
                $tmpWx = $wxfModel->fetchRow(['WeixinID = ?' => $wxId, 'Account = ?' => $d['Weixin']]);
                $tmpWeixins[$d['Weixin']] = $tmpWx;
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
        $albumId = (int)$this->_getParam('AlbumID', 0);
        if ($albumId < 1) {
            $this->showJson(self::STATUS_FAIL, '朋友圈id非法');
        }

        $aModel = new Model_Album();
        $arModel = new Model_AlbumReply();
        $album = $aModel->fetchRow(['AlbumID = ?' => $albumId]);
        if (!$album) {
            $this->showJson(self::STATUS_FAIL, '朋友圈id不存在');
        }

        $weixin = $album['Weixin'];
        $onlineWeixinDevice = (new Model_Device())->getDeviceByWeixin($weixin);
        if (!$onlineWeixinDevice) {
            $this->showJson(self::STATUS_FAIL, '请先上线微信');
        }

        $data = [
            'TaskCode' => TASK_CODE_ALBUM_DELETE,
            'Data' => [
                'ToWxSerID' => $album['WxSerID'],
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