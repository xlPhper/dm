<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/5/8
 * Time: 19:39
 */
class Model_Group_Url extends DM_Model
{
    public static $table_name = "group_urls";
    protected $_name = "group_urls";
    protected $_primary = "UrlID";

    /**
     * 状态
     */
    const STATUS_NOTUSED = 'NOTUSED';
    const STATUS_USED = 'NOTUSED';

    public function add($Url)
    {
        $data = [
            'Url'  =>  $Url,
            'QRCode'    =>  '',
            'AddDate'    =>  date("Y-m-d H:i:s"),
            'Status'      => self::STATUS_NOTUSED
        ];
        $this->insert($data);
        return $this->_db->lastInsertId();
    }

    public function getNotUsed()
    {
        $select = $this->_db->select();
        $select->from($this->_name)
               ->where("QRCode <> ''")
               ->where("Status = ?", self::STATUS_NOTUSED);
        return $this->_db->fetchAll($select);
    }

    public function setUsed($id)
    {
        $data = [
            'Status'    =>  self::STATUS_USED
        ];
        $where = $this->_db->quoteInto("UrlID = ?", $id);
        $this->update($data, $where);
    }

    public function updateQRCode($UrlID, $QRCode)
    {
        $data = [
            'QRCode'    =>  $QRCode,
        ];
        $where = $this->_db->quoteInto("UrlID = ?", $UrlID);
        $this->update($data, $where);
    }
}