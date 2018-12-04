<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/5/4
 * Time: 9:19
 */
class Model_MessageTemplate extends DM_Model
{
    public static $table_name = "message_templates";
    protected $_name = "message_templates";
    protected $_primary = "TemplateID";


    public function getTableName()
    {
        return $this->_name;
    }

    public function isWxTagId($Tagids)
    {
        $select = $this->select();
        $select->where('Type = WELCOME');
        $where_msg ='';
        $category_data = explode(',',$Tagids);
        foreach($category_data as $w){
            $where_msg .= "FIND_IN_SET(".$w.",WxTagIDs) OR ";
        }
        $where_msg = rtrim($where_msg,'OR ');
        $select->where($where_msg);

        return $this->_db->fetchRow($select);
    }
}