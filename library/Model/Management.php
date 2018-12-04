<?php
/**
 * Created by PhpStorm.
 * User: Ekko
 * Date: 2018/8/21
 * Ekko: 10:33
 */
class Model_Management extends DM_Model
{
    public static $table_name = "management";
    protected $_name = "management";
    protected $_primary = "AdminID";

    public function findManagementAdminID()
    {
        $all[]=[
            'AdminID' => 'all',
            'Username' => '总汇'
        ];
        $select = $this->_db->select();
        $select->from($this->_name.' as m','AdminID')
               ->join('admins as a','a.AdminID = m.AdminID','a.Username')
               ->group('AdminID');
        $res = $this->_db->fetchAll($select);
        array_splice($res, 0, 0, $all);
        return $res;
    }

    public function findManagementWeixinID($AdminID,$EndDay)
    {
        $select = $this->_db->select();
        $select->from($this->_name,'WeixinID')
            ->where('AddDate < ?',$EndDay);
        if ($AdminID != 'all'){
            $select->where('AdminID = ?',$AdminID);
        }
        $data = $this->_db->fetchAll($select);
        $res = array();
        foreach ($data as $val){
            $res[] = $val['WeixinID'];
        }
        return $res;
    }
}