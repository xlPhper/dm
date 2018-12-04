<?php
/**
 * Created by PhpStorm.
 * User: Ekko
 * Date: 2018/10/25
 * Ekko: 16:45
 */
class Model_PositionWeixin extends DM_Model
{
    public static $table_name = "position_weixin";
    protected $_name = "position_weixin";
    protected $_primary = "PositionWxID";

    public function getTableName()
    {
        return $this->_name;
    }

    public function findByID($ID)
    {
        $select = $this->select();
        $select->where('PositionWxID = ?',$ID);
        return $this->_db->fetchRow($select);
    }

    /**
     * 查询随机定位任务
     */
    public function findPositionTask($tagId,$weixins,$positionWxID = null)
    {
        $select = $this->select();
        $select->where("CURRENT_DATE() > StartDate AND CURRENT_DATE() < EndDate");
        $select->where('PositionTagID = ?',$tagId);
        $where_msg ='';
        $weixinData = explode(',',$weixins);
        foreach($weixinData as $w){
            $where_msg .= "FIND_IN_SET(".$w.",Weixins) OR ";
        }
        $where_msg = rtrim($where_msg,'OR ');
        $select->where($where_msg);
        if ($positionWxID){
            $select->where('PositionWxID <> ?',$positionWxID);
        }
        return $this->_db->fetchAll($select);
    }

    /**
     * 查询可执行的任务信息
     */
    public function getRunTask()
    {
        $select = $this->select()->from($this->_name)
            ->where("NextRunTime > LastRunTime")
            ->where("CURRENT_TIMESTAMP() >= NextRunTime")
            ->where("CURRENT_DATE() >= StartDate")
            ->where("CURRENT_DATE() <= EndDate")
            ->order("NextRunTime Asc");
        return $this->_db->fetchAll($select);
    }

    /**
     * 获取随机定位任务原型
     */
    public function findTaskInfo($positionWxId)
    {
        $select = $this->select()->from($this->_name);
        $select->where('PositionWxID = ?',$positionWxId);
        return $this->_db->fetchRow($select);
    }

}