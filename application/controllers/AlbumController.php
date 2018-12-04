<?php

/**
 * 朋友圈相册
 */
class AlbumController extends DM_Controller
{
    public function lastInfoAction()
    {
        $weixin = trim($this->_getParam('Weixin', ''));
        if ($weixin === '') {
            $this->showJson(0, '微信号不能为空');
        }

        $wxModel = new Model_Weixin();
        $wx = $wxModel->getInfoByWeixin($weixin);
        if (!$wx) {
            $this->showJson(0, '微信非法');
        }


        $lastAlbumId = $wx['LastAlbumID'];
        if ($lastAlbumId == 0) {
            $data = ['createTime' => '', 'AlbumSvrId' => ''];
        } else {
            $album = (new Model_Album())->fetchRow(['AlbumID = ?' => $lastAlbumId]);

            $data = ['createTime' => $album->WxSerTime, 'AlbumSvrId' => $album->WxSerID];
        }

        $this->showJson(1, '操作成功', $data);
    }

    public function lastCommentAction()
    {

    }

    public function tranCommentAction()
    {
        set_time_limit(0);
        ini_set('memory_limit', '1024M');
        // 评论id/朋友圈id/谁发的/回复的/content
        // 1 是点赞, 2是评论
        //[{"WxSerID":"xxx","ComSerID":"xxx","Content":"xxx","Sender":"wx_xxx","Receiver":"wx_xxx","Type":"1/2","SerTime":"xxx"},
        // {"WxSerID":"xxx","ComSerID":"xxx","Content":"xxx","Sender":"wx_xxx","Receiver":"wx_xxx","Type":"1/2","SerTime":"xxx"}]

        $comments = trim($this->_getParam('Comments', ''));
        if ($comments === '') {
            $this->showJson(0, '评论不能为空');
        }

        $comments = json_decode($comments, 1);
        if (json_last_error() != JSON_ERROR_NONE) {
            $this->showJson(0, '评论格式非法');
        }

        $aModel = new Model_Album();
        $arModel = new Model_AlbumReply();
        $validFields = ['WxSerID', 'ComSerID', 'Content', 'Sender', 'Receiver', 'Type', 'SerTime'];
        $insertData = [];
        $wxSerTimes = [];
        $albums = [];
        $addDate = date('Y-m-d H:i:s');
        foreach ($comments as $com) {
            if (!Helper_Until::hasReferFields($com, $validFields)) {
                $this->showJson(0, '缺少指定字段');
            }
            $wxSerId = trim($com['WxSerID']);
            $comSerId = trim($com['ComSerID']);
            $content = trim($com['Content']);
            $sender = trim($com['Sender']);
            $receiver = trim($com['Receiver']);
            $type = trim($com['Type']);
            $serTime = trim($com['SerTime']);
            if ($wxSerId === '') {
                $this->showJson(0, 'WxSerID非法');
            }
            if ($comSerId === '') {
                $this->showJson(0, 'ComSerID非法');
            }

            if (!isset($albums[$wxSerId])) {
                $album = $aModel->fetchRow(['WxSerID = ?' => $wxSerId]);
                if (!$album) {
//                    $this->showJson(0, 'WxSerID:'.$wxSerId.'非法');
                    continue;
                }
                $albums[$wxSerId] = $album;
                $albumId = $album['AlbumID'];
            } else {
                $albumId = $albums[$wxSerId]['AlbumID'];
            }

            $wsTime = $serTime * 1000;
            $wsTime = Helper_Until::getUniqueTime($wsTime, $wxSerTimes);

            $insertData[$wsTime] = [
                'AlbumID' => $albumId,
                'Replier' => $sender,
                'Receiver' => $receiver,
                'Type' => $type == 1 ? 'LIKE' : 'COMMENT',
                'Content' => $content,
                'WxReplySerID' => $comSerId,
                'ReplyTime' => $serTime > 0 ? date('Y-m-d H:i:s', $serTime) : $addDate,
                'SyncTime' => $addDate,
                'FromChannel' => 'CLIENT'
            ];
        }

        ksort($insertData);

        try {
            $aModel->getAdapter()->beginTransaction();

            // todo:清空web端暂存的消息


            $arIds = [];
            foreach ($insertData as $data) {
                $s = $arModel->select()
                    ->where('Replier = ?', $data['Replier'])
                    ->where('WxReplySerID = ?', $data['WxReplySerID'])
                    ->where('AlbumID = ?', $data['AlbumID'])
                    ->where('Type = ?', $data['Type'])
                    ->forUpdate(true);
                $ar = $arModel->fetchRow($s);
                if ($ar) {
                    $arIds[$data['AlbumID']][] = $ar['ReplyID'];
                    continue;
                }
                $id = $arModel->insert($data);
                $arIds[$data['AlbumID']][] = $id;
            }

            // 删除已经没有回复
//            foreach ($arIds as $aid => $ids) {
//                $arModel->update(['DeleteTime' => date('Y-m-d H:i:s')], ['ReplyID not in (?)' => $ids, 'AlbumID = ?' => $aid, 'DeleteTime = ?' => '0000-00-00 00:00:00']);
//            }
            $aModel->getAdapter()->commit();
        } catch (\Exception $e) {
            $aModel->getAdapter()->rollBack();
            $this->showJson(0, '上报失败,err:'.$e->getMessage());
        }

        $this->showJson(1, '上报成功');
    }

    /**
     * 朋友圈转发上报
     */
    public function tranAction()
    {
        set_time_limit(0);
        ini_set('memory_limit', '1024M');
        // [{"TextContent":"xxx", "SenderWx":"wx_xxx", "Resources":"xxx","WxSerID":"xxx","WxSerTime":"xxx", "Type":1}, {"TextContent":"yyy",  "SenderWx":"wx_xxx", "Resources":"yyy","WxSerID":"yyy", "WxSerTime":"yyy"}]
        $weixin = trim($this->_getParam('Weixin', ''));
        if ($weixin === '') {
            $this->showJson(0, '微信号不能为空');
        }

        $wxModel = new Model_Weixin();
        $wx = $wxModel->getInfoByWeixin($weixin);
        if (!$wx) {
            $this->showJson(0, '微信非法');
        }

        $albums = trim($this->_getParam('Albums', ''));
        if ($albums === '') {
            $this->showJson(0, '朋友圈内容为空');
        }
        $albums = json_decode($albums, 1);
        if (json_last_error() != JSON_ERROR_NONE) {
            $this->showJson(0, '朋友圈内容非法');
        }

        $wxModel = new Model_Weixin();
        $addDate = date('Y-m-d H:i:s');
        $validFields = ['TextContent', 'Resources', 'SenderWx', 'WxSerID', 'WxSerTime'];
        $insertData = [];
        $wxSerTimes = [];
        foreach ($albums as $album) {
            if (!Helper_Until::hasReferFields($album, $validFields)) {
                $this->showJson(0, '内容缺少指定字段');
            }
            if (empty($album['SenderWx'])) {
                $this->showJson(0, '发送微信必填');
            }
            if (empty($album['WxSerID'])) {
                $this->showJson(0, '微信服务器id必填');
            }

            $wsTime = $album['WxSerTime'] * 1000;
            $wsTime = Helper_Until::getUniqueTime($wsTime, $wxSerTimes);

            $insertData[$wsTime] = [
                'Weixin' => $album['SenderWx'],
                'TextContent' => $album['TextContent'],
                'Resources' => json_encode($album['Resources']),
                'WxSerID' => $album['WxSerID'],
                'WxSerTime' => $album['WxSerTime'],
                'AddDate' => $album['WxSerTime'] > 0 ? date('Y-m-d H:i:s', $album['WxSerTime']) : $addDate,
                'SyncTime' => $addDate
            ];
        }

        ksort($insertData);
        $insertData = array_values($insertData);

        $needSyncResWxSerIds = [];
        $aModel = new Model_Album();
        try {
            $wxModel->getAdapter()->beginTransaction();

            $count = count($insertData);
            $lastId = 0;
            foreach ($insertData as $k => $data) {
                $s = $aModel->select()->where('WxSerID = ?', $data['WxSerID'])->forUpdate(true);
                $a = $aModel->fetchRow($s);
                if ($a) {
                    if ($a['Resources'] == 2) {
                        $needSyncResWxSerIds[] = $data['WxSerID'];
                    }
                    if ($k+1 == $count) {
                        $lastId = $a['AlbumID'];
                    }
                } else {
                    $id = $aModel->insert($data);
                    if ($data['Resources'] == 2) {
                        $needSyncResWxSerIds[] = $data['WxSerID'];
                    }
                    if ($k+1 == $count) {
                        $lastId = $id;
                    }
                }
            }
            if ($lastId > 0) {
                $wxModel->update(['LastAlbumID' => $lastId], ['WeixinID = ?' => $wx['WeixinID']]);
            }

            $wxModel->getAdapter()->commit();
        } catch (\Exception $e) {
            $wxModel->getAdapter()->rollBack();
            $this->showJson(0, '上报失败,err:'.$e->getMessage());
        }

        $this->showJson(1, '上报成功', ['NeedSyncResWxSerIds' => $needSyncResWxSerIds]);
    }

    /**
     * 同步资源
     */
    public function syncResAction()
    {
        // [{"WxSerID":"1", "Images":"xxx,yyy,zzz","Videos":"", "Err":""}, {"WxSerID":"2", "Images":"","Videos":"xxx","Err":""}]
        $resources = trim($this->_getParam('Resources', ''));
        if ($resources === '') {
            $this->showJson(0, '资源参数为空');
        }

        $resources = json_decode($resources, 1);
        if (json_last_error() != JSON_ERROR_NONE) {
            $this->showJson(0, '资源参数非法');
        }

        foreach ($resources as $res) {
            if (!Helper_Until::hasReferFields($res, ['WxSerID', 'Images', 'Err'])) {
                $this->showJson(0, '资源参数缺少字段');
            }
        }

        $aModel = new Model_Album();
        try {
            $aModel->getAdapter()->beginTransaction();
            foreach ($resources as $d) {
                if ($d['Err'] !== '') {
                    // 如果有异常: todo:
                    continue;
                }
                $album = $aModel->fetchRow(['WxSerID = ?' => $d['WxSerID']]);
                if (!$album) {
                    continue;
                }
                $album->Photos = isset($d['Images']) ? $d['Images'] : '';
                // thumbImgUrl 视频缩略图 mediaUrl 完整视频
                if (isset($d['Videos']['mediaUrl'])) {
                    $album->Videos = $d['Videos']['mediaUrl'];
                }
                if (isset($d['Videos']['thumbImgUrl'])) {
                    $album->VideoCover = $d['Videos']['thumbImgUrl'];
                }
                if (isset($d['Videos']['mediaId'])) {
                    $album->VideoMediaID = $d['Videos']['mediaId'];
                }
                $album->Resources = 1;
                $album->save();
            }
            $aModel->getAdapter()->commit();
        } catch (\Exception $e) {
            $aModel->getAdapter()->rollBack();
            $this->showJson(0, '同步资源失败,err:'.$e->getMessage());
        }

        $this->showJson(1, '同步资源成功');
    }

    public function reportAction()
    {
        $taskCode = trim($this->_getParam('TaskCode', ''));

        switch ($taskCode) {
            case TASK_CODE_ALBUM_LIKE:
//                $this->likeAlbum();
                break;
            case TASK_CODE_ALBUM_UNLIKE:
//                $this->unlikeAlbum();
                break;
            case TASK_CODE_ALBUM_DELETE:
                $this->deleteAlbum();
                break;
            case TASK_CODE_ALBUM_COMMENT_DEL:
                $this->deleteAlbumComment();
                break;
            default:
                break;
        }

        $this->showJson(0, '非法任务代码');
    }

    /**
     * 删除朋友圈评论
     */
    private function deleteAlbumComment()
    {
        $wxSerId = trim($this->_getParam('WxSerID', ''));
        if ($wxSerId === '') {
            $this->showJson(0, 'WxSerID非法');
        }
        $aModel = new Model_Album();
        $album = $aModel->fetchRow(['WxSerID = ?' => $wxSerId]);
        if (!$album) {
            $this->showJson(0, '非法的WxSerID');
        }
        $wxSerId = trim($this->_getParam('ComSerID', ''));
        if ($wxSerId === '') {
            $this->showJson(0, 'ComSerID非法');
        }
        $arModel = new Model_AlbumReply();
        $ar = $arModel->fetchRow(['AlbumID = ?' => $album['AlbumID'], 'WxReplySerID = ?' => $wxSerId]);
        if (!$ar) {
            $this->showJson(0, '非法的ComSerID');
        }

        try {
            $ar->delete();
        } catch (\Exception $e) {
            $this->showJson(0, '操作失败,err:'.$e->getMessage());
        }

        $this->showJson(1, '操作成功');
    }

    /**
     * 删除朋友圈
     */
    private function deleteAlbum()
    {
        $wxSerId = trim($this->_getParam('WxSerID', ''));
        if ($wxSerId === '') {
            $this->showJson(0, 'WxSerID非法');
        }
        $aModel = new Model_Album();
        $arModel = new Model_AlbumReply();
        $album = $aModel->fetchRow(['WxSerID = ?' => $wxSerId]);
        if (!$album) {
            $this->showJson(0, '非法的WxSerID');
        }

        try {
            $aModel->getAdapter()->beginTransaction();
            $album->DeleteTime = date('Y-m-d H:i:s');
            $album->save();

            $arModel->delete(['AlbumID = ?' => $album['AlbumID']]);
            $aModel->getAdapter()->commit();
        } catch (\Exception $e) {
            $aModel->getAdapter()->rollBack();
            $this->showJson(0, '操作失败,err:'.$e->getMessage());
        }

        $this->showJson(1, '操作成功');
    }

    /**
     * 点赞朋友圈
     */
    private function likeAlbum()
    {
        $params = trim($this->_getParam('Params', ''));
        $params = json_decode($params, 1);
        if (json_last_error() != JSON_ERROR_NONE) {
            $this->showJson(0, '参数非法');
        }
        if (!Helper_Until::hasReferFields($params, ['WxSerID', 'Weixin', 'ComSerID', 'SerTime'])) {
            $this->showJson(0, '参数非法');
        }

        $wxSerId = trim($params['WxSerID']);
        $weixin = trim($params['Weixin']);
        if ($wxSerId === '' || $weixin === '') {
            $this->showJson(0, '参数非法');
        }

        $aModel = new Model_Album();
        $arModel = new Model_AlbumReply();
        $album = $aModel->fetchRow(['WxSerID = ?' => $wxSerId]);
        if (!$album) {
            $this->showJson(0, '非法的WxSerID');
        }
        $albumId = $album['AlbumID'];

        try {
            $arModel->insert([
                'AlbumID' => $albumId,
                'Replier' => $weixin,
                'Receiver' => '',
                'Type' => 'LIKE',
                'Content' => '',
                // todo:
                'WxReplySerID' => '',
                'ReplyTime' => date('Y-m-d H:i:s'),
                'FromChannel' => 'CLIENT'
            ]);
        } catch (Exception $e) {
            $this->showJson(0, '点赞失败,err:' . $e->getMessage());
        }

        $this->showJson(1, '点赞成功');

    }

    private function unlikeAlbum()
    {

    }


}