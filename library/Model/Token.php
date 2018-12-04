<?php
/**
 * Created by PhpStorm.
 * User: Ekko
 * Date: 2018/7/25
 * Ekko: 15:04
 */
class Model_Token extends DM_Model
{
    public static $table_name = "token";
    protected $_name = "token";
    protected $_primary = "TokenID";

    public function getByAppID($AppID)
    {
        if(empty($AppID)){
            return false;
        }
        $select = $this->_db->select();
        $select->from($this->_name)
            ->where("AppID = ?", $AppID);
        return $this->_db->fetchRow($select);
    }
}