<?php

class Model_Group_QrJoin extends DM_Model
{
    public static $table_name = "group_qr_join";
    protected $_name = "group_qr_join";
    protected $_primary = "JoinID";
    /*
     * 获取可执行任务
     */
    public function getData()
    {
        $select = $this->select();
        $select->from($this->_name)
            ->where("StartDate <= ?",date("Y-m-d"))
            ->where("? <= EndDate",date("Y-m-d"))
            ->where("NextRunTime >= LastRunTime")
            ->where("NextRunTime < ?",date("Y-m-d H:i:s"))
            // 未开始/进行中
            ->where('Status = ?', 1)
            ->order("JoinID Asc");
        return $this->_db->fetchAll($select);
    }
}