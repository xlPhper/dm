<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/24
 * Time: 14:41
 */
class Model_Device extends DM_Model
{
    public static $table_name = "devices";
    protected $_name = "devices";
    protected $_primary = "DeviceID";

    public function getInfoByNO($DeviceNO)
    {
        $select = $this->_db->select();
        $select->from($this->_name)
               ->where("DeviceNO = ?", $DeviceNO);
        return $this->_db->fetchRow($select);
    }


    /**
     * 获得空闲设备
     */
    public function getFree()
    {
        $select = $this->_db->select();
        $select->from($this->_name)
               ->where("OnlineStatus = ?", DEVICE_ONLINE)
               ->where("WorkStatus = ?", DEVICE_REST)
               ->where('Status = ?', 'RUNNING');
        return $this->_db->fetchAll($select);
    }

    public static function getDeviceConfig()
    {
        $data = [
            'SN'    =>  self::getRandChar('num', 15),
            'AndroidID' =>  self::getRandChar('mix', 16),
            'IMEI'  =>  self::getRandChar('num', 15),
            'MAC'   =>  self::getRandChar('mac', 2) . ":" . self::getRandChar('mac', 2) . ":" . self::getRandChar('mac', 2) . ":" . self::getRandChar('mac', 2) . ":" . self::getRandChar('mac', 2) . ":" . self::getRandChar('mac', 2),
            'SSID'  =>  self::getRandChar('word', rand(4, 8)),
        ];
        $data = array_merge($data, self::getProductModel());
        return $data;
    }

    protected static function getRandChar($type, $length){
        switch($type){
            case 'num':
                $strPol = "0123456789";
                break;
            case 'word':
                $strPol = "abcdefghijklmnopqrstuvwxyz";
                break;
            case 'mix':
                $strPol = "0123456789abcdefghijklmnopqrstuvwxyz";
                break;
            case 'mac':
                $strPol = "0123456789abcdef";
                break;
        }
        $str = "";
        $max = strlen($strPol)-1;

        for($i=0;$i<$length;$i++){
            $str.=$strPol[rand(0,$max)];
        }
        return $str;
    }

    protected static function getProductModel()
    {
        return ['Model'   =>  'Lenovo A7600-m', 'Vendor'    =>  'LENOVO', 'Brand'   =>  'Lenovo'];
    }

    /**
     * 查询微信信息
     * @return array
     */
    public function findOnlineWeixin()
    {
        $select = $this->select();
        $select->from($this->_name,'OnlineWeixinID')
            ->where("OnlineWeixinID > 0")
            ->where('Status = ?', 'RUNNING');
        $data = $this->_db->fetchAll($select);
        $res = array();
        foreach ($data as $val){
            $res[] = (int)$val['OnlineWeixinID'];
        }
        return $res;
    }


    /**
     * 微信是否在线
     */
    public function weixinIsOnline($weixin)
    {
        return $this->fetchRow($this->select()->where('OnlineWeixin = ?', $weixin)->where('Status = ?', 'RUNNING')) ? true : false;
    }

    /**
     * 获取 client_id
     */
    public function getClientIdByWeixin($weixin)
    {
        $device = $this->fetchRow($this->select()->where('OnlineWeixin = ?', $weixin)->where('Status = ?', 'RUNNING'));

        return $device ? $device['ClientID'] : '';
    }

    /**
     * 获取在线微信设备
     */
    public function getDeviceByWeixin($weixin)
    {
        return $this->fetchRow($this->select()->where('OnlineWeixin = ?', $weixin)->where('Status = ?', 'RUNNING'));
    }

    /**
     * 获取指定微信号的设备状态
     *
     * @param $CategoryId  排除掉的微信号
     * @return array
     */
    public function getDetectionPhoneWx($weixinIds = array())
    {
        $select = $this->select()->from($this->_name,['OnlineWeixinID']);
        $select->where("OnlineStatus = ?", DEVICE_ONLINE);
        $select->where("WorkStatus = ?", DEVICE_REST);
        $select->where('Status = ?', 'RUNNING');
        if ($weixinIds){
            $select->where('OnlineWeixinID  not in (?)',$weixinIds);
        }
        return $this->_db->fetchAll($select);
    }
    /**
     * 获取上线的设备ids
     */
    public function getOnlineDeviceIds()
    {
        $select = $this->_db->select();
        $select->from($this->_name)
            ->where("OnlineStatus = ?", DEVICE_ONLINE)
            ->where("WorkStatus = ?", DEVICE_REST)
            ->where('Status = ?', 'RUNNING');
        $devices = $this->_db->fetchAll($select);
        $onlineDeviceIds = [];
        foreach ($devices as $device) {
            $onlineDeviceIds[] = (int)$device['DeviceID'];
        }
        return $onlineDeviceIds;
    }

    /**
     * 根据设备编号获取在线微信ID和不在线设备
     * @param $serialNums
     * @return array
     */
    public function getOnlineWxIDBySerialNums($serialNums)
    {
        $res = ['WeixinIDs' =>[], 'UnOnlineSerialNums' => []];
        foreach ($serialNums as $serialNum){
            $onlineWeixinID = $this->getExactOnlineWxIDBySerialNum($serialNum);
            if($onlineWeixinID){
                $res['WeixinIDs'][] = $onlineWeixinID;
            }else{
                $res['UnOnlineSerialNums'][] = $serialNum;
            }
        }
        return $res;
    }

    /**
     * @param $serialNum
     * @return int
     * 根据设备准确查找到设备在线WeixinID
     */
    public function getExactOnlineWxIDBySerialNum($serialNum){
        $data = $this->fromSlaveDB()->select()->from($this->_name, ['OnlineWeixinID','SerialNum'])
            ->where("OnlineWeixinID > 0")->where('Status = ?', 'RUNNING')->where("OnlineStatus = ?", DEVICE_ONLINE)
            ->where("SerialNum Like ?", "%{$serialNum}")->query()->fetchAll();
        if(count($data) == 1){
            return $data[0]['OnlineWeixinID'];
        }else{
            foreach($data as $datum){
                $sarr = explode("-", $datum['SerialNum']);
                $end = array_pop($sarr);
                if($end == $serialNum){
                    return $datum['OnlineWeixinID'];
                }
            }
        }
        return 0;
    }

    /**
     * 随机获取一个在线微信设备
     * @return mixed
     */
    public function getOneOnlineWeixin()
    {
        $select = $this->_db->select();
        $select->from($this->_name)
            ->where("OnlineStatus = ?", DEVICE_ONLINE)
            ->where("OnlineWeixinID > 0")
            ->where('Status = ?', 'RUNNING')->order('RAND()')->limit(1);
        return $this->_db->fetchRow($select);
    }
}