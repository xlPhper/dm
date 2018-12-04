<?php
/**
 * Created by PhpStorm.
 * User: Ekko
 * Date: 2018/11/06
 * Time:
 */
class Model_Weixin_Notes extends DM_Model
{
    public static $table_name = "weixin_friend_notes";
    protected $_name = "weixin_friend_notes";
    protected $_primary = "NoteID";

    public function getNoteNum($FriendID)
    {
        $select = $this->fromSlaveDB()->select()->from($this->_name,['COUNT(NoteID) as Num']);
        $select->where('FriendID = ?',$FriendID);
        return $this->_db->fetchRow($select);
    }

    /**
     * 获取指定客户的备注详情
     * @param $FriendID
     * @return array
     */
    public function findByFriendID($FriendID)
    {
        $select = $this->fromSlaveDB()->select()->from($this->_name.' as wfn',['wfn.NoteID','wfn.Content',"DATE_FORMAT(wfn.RemindTime,'%Y-%m-%d% %H:%i') as RemindTime","CreateTime","Status"])->setIntegrityCheck(false);
        $select->joinLeft('weixin_friends as wf','wf.FriendID = wfn.FriendID',['wf.FriendID','wf.NickName','wf.Customer']);
        $select->where('wfn.FriendID = ?',$FriendID);
        $select->limit(10);
        $select->order('NoteID ASC');
        return $this->_db->fetchAll($select);
    }

    /**
     * 获取 提醒时间内的备注信息
     */
    public function getFriendNotes($FriendIDs,$Date = '')
    {
        $select = $this->fromSlaveDB()->select()->from($this->_name.' as wfn',['wfn.NoteID','wfn.Content',"DATE_FORMAT(wfn.RemindTime,'%Y-%m-%d% %H:%i') as RemindTime","CreateTime","Status"])->setIntegrityCheck(false);
        $select->joinLeft('weixin_friends as wf','wf.FriendID = wfn.FriendID',['wf.FriendID','wf.NickName','wf.Customer']);
        $select->where("wfn.FriendID in (?)",$FriendIDs);
        $select->where("wfn.RemindTime <= ?",$Date);
        $select->where("wfn.RemindTime <> '0000-00-00 00:00:00'");
        $select->where('wfn.Status = 1');
        $select->limit(30);
        $select->order('RemindTime ASC');
        return $this->_db->fetchAll($select);
    }
}