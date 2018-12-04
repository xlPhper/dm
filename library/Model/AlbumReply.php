<?php

class Model_AlbumReply extends DM_Model
{
    public static $table_name = "album_replies";
    protected $_name = "album_replies";
    protected $_primary = "ReplyID";

    /**
     * 获取评论列表
     */
    public function getCommentsByAlbumId($albumId, array $wxFriendAccounts = [])
    {
        $select = $this->fromSlaveDB()->select()
            ->where('AlbumID = ?', $albumId)
            ->where('Type = ?', 'COMMENT')
            ->where('DeleteTime = ?', '0000-00-00 00:00:00');

        if (!empty($wxFriendAccounts)) {
            $select->where('Replier in (?)', $wxFriendAccounts);
        }
        $select->order('ReplyID asc');

        return $this->fetchAll($select)->toArray();
    }

    /**
     * 是否被指定人点赞
     */
    public function isLikeByWeixin($albumId, $weixin)
    {
        $select = $this->fromSlaveDB()->select()
            ->where('AlbumID = ?', $albumId)
            ->where('DeleteTime = ?', '0000-00-00 00:00:00')
            ->where('Replier = ?', $weixin)
            ->where('Type = ?', 'LIKE');

        return $this->fetchRow($select) ? 'Y' : 'N';
    }

    /**
     * 获取朋友圈互动数据
     */
    public function getAlbumReply($AlbumIds)
    {
        $select = $this->fromSlaveDB()->select()
            ->where('AlbumID in (?)', $AlbumIds);
        return $this->fetchAll($select) ;
    }
}