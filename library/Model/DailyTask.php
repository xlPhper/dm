<?php
/**
 * Created by PhpStorm.
 * User: Ekko
 * Date: 2018/10/24
 * Ekko: 19:06
 */
class Model_DailyTask extends DM_Model
{
    public static $table_name = "task_daily";
    protected $_name = "task_daily";
    protected $_primary = "DailyTaskID";

    /**
     * 获取表名
     *
     * @return string
     */
    public function getTableName()
    {
        return $this->_name;
    }


    /**
     * 获取所有每日任务
     */
    public function findAllDailyTask()
    {
        $select = $this->select();
        $select->where("Status = 'ON'");
        $select->where('NextRunTime > LastRunTime');
        $select->where("CURRENT_TIMESTAMP() >= NextRunTime");
        return $this->_db->fetchAll($select);
    }
}