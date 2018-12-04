<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/6/5
 * Time: 11:06
 */
class Model_Gather_Number extends DM_Model
{
    public static $table_name = "gather_numbers";
    protected $_name = "gather_numbers";
    protected $_primary = "NumberID";

    public function getNotCheck($UrlID = [], $limit = 20)
    {
        $select = $this->_db->select();
        $select->from($this->_name)
               ->where("IsWeixin = -1");
        if(count($UrlID) > 0){
            $select->where("UrlID in (?)", $UrlID);
        }
        if($limit){
            $select->limit($limit);
        }
        return $this->_db->fetchAll($select);
    }

    public function setWeixin($id, $weixin)
    {
        $data = [
            'IsWeixin' =>  $weixin
        ];
        $where = "NumberID = '{$id}'";
        $this->update($data, $where);
    }

    public function save($UrlID, $Number)
    {
        $select = $this->_db->select();
        $select->from($this->_name)
               ->where("UrlID = ?", $UrlID)
               ->where("Number = ?", $Number);
        $row = $this->_db->fetchRow($select);
        if(!isset($row['NumberID'])){
            $data = [
                'UrlID' =>  $UrlID,
                'Number'    =>  $Number
            ];
            $this->insert($data);
        }
    }
}