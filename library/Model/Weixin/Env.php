<?php
/**
 * Created by PhpStorm.
 * User: Tim
 * Date: 2018/4/24
 * Time: 23:33
 */
class Model_Weixin_Env extends DM_Model
{
    public static $table_name = "weixin_env";
    protected $_name = "weixin_env";
    protected $_primary = "EnvID";

    public function getEnv($WeixinID)
    {
        $select = $this->_db->select();
        $select->from($this->_name)
               ->where("WeixinID = ?", $WeixinID);
        return $this->_db->fetchRow($select);
    }

}