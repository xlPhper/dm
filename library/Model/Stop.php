<?php
/**
 * Created by PhpStorm.
 * User: Ekko
 * Date: 2018/9/19
 * Ekko: 17:59
 */
class Model_Stop extends DM_Model
{
    public static $table_name = "stop_date";
    protected $_name = "stop_date";
    protected $_primary = "StopDateID";

    public function getTableName()
    {
        return $this->_name;
    }

    public function findAll()
    {
        $select = $this->select();
        return $this->_db->fetchAll($select);
    }

}