<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/5/4
 * Time: 9:19
 */
class Model_MessageQueue extends DM_Model
{
    public static $table_name = "message_queues";
    protected $_name = "message_queues";
    protected $_primary = "QueueID";


    public function getTableName()
    {
        return $this->_name;
    }
}