<?php
/**
 * Created by PhpStorm.
 * User: Ekko
 * Date: 2018/08/29
 * Ekko: 16:00
 */
class Model_Group_Create extends DM_Model
{
    public static $table_name = "group_create_tasks";
    protected $_name = "group_create_tasks";
    protected $_primary = "CreateID";


   /*
    * 获取可执行任务
    * @return
    */
    public function getCreateGroup()
    {
        $select = $this->select();
        $select->from($this->_name)
            // 下次执行时间大于上次执行时间 并且 当前时间大于下次执行时间
            ->where("CURRENT_DATE() >= StartDate")
            ->where("CURRENT_DATE() <= EndDate")
            ->where("NextRunTime > LastRunTime")
            ->where("CURRENT_TIMESTAMP() > NextRunTime")
            // 未开始/进行中
            ->where('Status in (?)', [TASK_STATUS_NOTSTART])
            ->order("CreateID Asc");
        return $this->_db->fetchAll($select);
    }
}