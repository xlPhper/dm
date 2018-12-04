<?php
/**
 * Created by PhpStorm.
 * User: Ekko
 * Date: 2018/9/17
 * Ekko: 17:22
 */
class Model_Exception extends DM_Model
{
    public static $table_name = "exception";
    protected $_name = "exception";
    protected $_primary = "ExceptionID";

    public function getTableName()
    {
        return $this->_name;
    }

    public function findByID($id)
    {
        $select = $this->fromSlaveDB()->select()->where('ExceptionID = ?',$id);

        return $this->_db->fetchRow($select);
    }

}