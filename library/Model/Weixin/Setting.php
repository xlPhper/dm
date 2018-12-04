<?php
/**
 * Created by PhpStorm.
 * User: Ekko
 * Date: 2018/11/06
 * Time: 12:39
 */
class Model_Weixin_Setting extends DM_Model
{
    public static $table_name = "weixin_setting";
    protected $_name = "weixin_setting";
    protected $_primary = "SettingID";

    /**
     * 根据ID查询
     *
     * @param $id
     */
    public function findByID($settingID)
    {
        $select = $this->select()->from($this->_name);
        $select->where('SettingID = ?',$settingID);
        return $this->_db->fetchRow($select);
    }


    /**
     * 根据WeixinID查询
     *
     * @param $id
     */
    public function findByWeixnID($weixinID)
    {
        $select = $this->select()->from($this->_name);
        $select->where('WeixinID = ?',$weixinID);
        return $this->_db->fetchRow($select);
    }

    /**
     * 获取微信的配置信息
     */
    public function getWxSettings($weixinIDs)
    {
        $select = $this->select()->from($this->_name,['WeixinID','FriendsValidation','AddressBookFriends','AddMeWay','ViewTenPictures','ViewRange']);
        $select->where('WeixinID in (?)',$weixinIDs);
        $data = $this->_db->fetchAll($select);

        $res = [];

        foreach ($data as $d){
            $res[$d['WeixinID']]['FriendsValidation'] = $d['FriendsValidation'];
            $res[$d['WeixinID']]['AddressBookFriends'] = $d['AddressBookFriends'];
            $res[$d['WeixinID']]['ViewTenPictures'] = $d['ViewTenPictures'];
            $res[$d['WeixinID']]['ViewRange'] = $d['ViewRange'];

            if ($d['AddMeWay']){
                $addMeWay = json_decode($d['AddMeWay'],1);
                $res[$d['WeixinID']]['Weixin'] = $addMeWay['Weixin'];
                $res[$d['WeixinID']]['Phone'] = $addMeWay['Phone'];
                $res[$d['WeixinID']]['QQ'] = $addMeWay['QQ'];
                $res[$d['WeixinID']]['Group'] = $addMeWay['Group'];
                $res[$d['WeixinID']]['QRcode'] = $addMeWay['QRcode'];
                $res[$d['WeixinID']]['NameCard'] = $addMeWay['NameCard'];
            }

        }

        return $res;

    }
}