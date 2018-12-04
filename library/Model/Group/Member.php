<?php

class Model_Group_Member extends DM_Model
{
    public static $table_name = "group_members";
    protected $_name = "group_members";
    protected $_primary = "MemberID";


    /**
     * 查询某个用户在某个群中
     *
     * @param $GroupID 群ID
     * @param $Account 用户账号
     */
    public function groupMember($GroupID,$Account)
    {
        $select = $this->select();
        $select->where('GroupID = ?',$GroupID);
        $select->where('Account = ?',$Account);
        return $this->_db->fetchRow($select);
    }

    /**
     * 根据成员账号查询
     *
     * @param $Account 用户账号
     */
    public function findByAccount($Account)
    {
        $select = $this->select();
        $select->where('Account = ?',$Account);
        return $this->_db->fetchRow($select);
    }
}