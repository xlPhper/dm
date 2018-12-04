<?php
/**
 * Created by PhpStorm.
 * User: Tim
 * Date: 2018/7/14
 * Time: 23:15
 */
class Model_Gzh extends DM_Model
{
    public static $table_name = "gzhs";
    protected $_name = "gzhs";
    protected $_primary = "GzhID";

    public function getInfoByCode($code)
    {
        $code = trim($code);
        if(empty($code)){
            return false;
        }
        $select = $this->_db->select();
        $select->from($this->_name)
               ->where("Code = ?", $code);
        return $this->_db->fetchRow($select);
    }

}