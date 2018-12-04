<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/6/5
 * Time: 13:32
 */
class Model_Gather_Url extends DM_Model
{
    public static $table_name = "gather_number_urls";
    protected $_name = "gather_number_urls";
    protected $_primary = "UrlID";

    public function getNotGather($UrlIDs = [])
    {
        $select = $this->_db->select();
        $select->from($this->_name)
               ->where("Status = 0");
        if(count($UrlIDs) > 0){
            $select->where("UrlID in (?)", $UrlIDs);
        }
        return $this->_db->fetchAll($select);
    }

    public function reset()
    {
        $data = [
            'Status'    =>  0
        ];
        $where = "1 = 1";
        $this->update($data, $where);
    }

    public function setStatus($id, $status)
    {
        $data = [
            'Status'    =>  $status
        ];
        $where = "UrlID = '{$id}'";
        $this->update($data, $where);
    }
}