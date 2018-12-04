<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/5/10
 * Time: 10:03
 */
class Model_Group_Tmps extends DM_Model
{
    public static $table_name = "group_qr_tmps";
    protected $_name = "group_qr_tmps";
    protected $_primary = "QrID";

    const CACHE_CHECK_QRCODE_IMG = 'qr_list'; //待检测的二维码图片队列

    const CHANNEL_DOUBAN = 'douban'; //豆瓣来源渠道

    public function getInfoByUrl($channel, $url)
    {
        $select = $this->_db->select();
        $select->from($this->_name)
               ->where("Channel = ?", $channel)
               ->where("Url = ?", $url);
        return $this->_db->fetchRow($select);
    }
    public function getData($Channel, $Type,$Limit = 10,$Status = 0)
    {
        $select = $this->_db->select();
        $select->from($this->_name);
        if(!empty($Channel)){
            $select->where("Channel = ?", $Channel);
        }
        $select->where("Type = ?", $Type)
            ->where("Status = ?",$Status)
            ->where("AddTime > ?",date("Y-m-d H:i:s",strtotime("-7 days")))
            ->limit($Limit);
        return $select->query()->fetchAll();
    }
    public function use($QrID)
    {
        return $this->update(["Status"=>2],"QrID = {$QrID}");
    }
}