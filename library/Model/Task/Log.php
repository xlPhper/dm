<?php
/**
 * Created by PhpStorm.
 * User: Tim
 * Date: 2018/4/25
 * Time: 23:00
 */
class Model_Task_Log extends DM_Model
{
    public static $table_name = "task_logs";
    protected $_name = "task_logs";
    protected $_primary = "LogID";

    public function add($TaskID, $ID, $Status, $Msg)
    {
        $data = [
            'TaskID'    =>  $TaskID,
            'ID'  =>  $ID,
            'Status'    =>  $Status,
            'Msg'      =>  $Msg,
            'AddDate'   =>  date("Y-m-d H:i:s"),
        ];
        $this->insert($data);
    }

    /**
     * 查询执行任务日志
     * @param TaskID 任务ID
     */
    public function findTaskLog($TaskID)
    {
        $select = $this->select()->where('TaskID = ?',$TaskID);
        return $this->_db->fetchAll($select);
    }
}