<?php

class Model_Album extends DM_Model
{
    public static $table_name = "albums";
    protected $_name = "albums";
    protected $_primary = "AlbumID";


    public function getAlbumData($weixinIds = null,$startDate = '',$endDate = '')
    {
        $select = $this->fromSlaveDB()->select()->from($this->_name);
        if (!empty($startDate) && !empty($endDate)){
            $select->where("AddDate <= ?", $startDate.' 23:59:59');
            $select->where("AddDate >= ?", $endDate.' 00:00:00');
        }
        if ($weixinIds){
            $select->where("Weixin in (?)", $weixinIds);
        }
        return $this->_db->fetchAll($select);
    }

}