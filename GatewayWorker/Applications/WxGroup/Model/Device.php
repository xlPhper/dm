<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/24
 * Time: 17:21
 */
use \GatewayWorker\Lib\Db;

class Device
{
    protected static $_name = "devices";
    protected static $_primary = "DeviceID";

    /**
     * 设备ping服务器
     * @param $ClientID
     */
    public static function ping($ClientID, $Message)
    {
         // {"Data":{"DeviceNO":"65c40975-035c-407f-b603-78e9268c8539"},"TaskType":"Ping"}
        $db = Db::instance(Events::getDb());
        if (isset($Message['DeviceNO']) && trim($Message['DeviceNO']) !== '') {
            $deviceNo = trim($Message['DeviceNO']);
            $sql = "select * from " . self::$_name . " where DeviceNO = '{$deviceNo}'";
            $info = $db->row($sql);
            if (isset($info['ClientID']) && $info['ClientID'] != $ClientID) {

                $data = [
                    'ClientID' => '',
                    'OnlineStatus' => 0,
                    'OnlineWeixinID' => 0,
                    'OnlineWeixin' => ''
                ];
                $db->update(self::$_name)->cols($data)->where("DeviceNO = '{$deviceNo}'")->query();

                return $info['ClientID'];
            }
        } else {
            $data = [
                'OnlineTime'    =>  date("Y-m-d H:i:s"),
            ];
            $db->update(self::$_name)->cols($data)->where("ClientID = '{$ClientID}'")->query();
        }

        return 'Pong';
    }

    /**
     * 设备登录
     * @param $ClientID
     * @param $Message
     */
    public static function online($ClientID, $Message)
    {
        $db = Db::instance(Events::getDb());
        $sql = "select * from " . self::$_name . " where DeviceNO = '{$Message['DeviceNO']}'";
        $info = $db->row($sql);
        $data = [
            'ClientID' => $ClientID,
            'DeviceNO' => $Message['DeviceNO'],
            'WorkStatus' => DEVICE_REST,
            'OnlineStatus' => DEVICE_ONLINE,
            'RunTaskNum' => 0,
            'OnlineTime' => date("Y-m-d H:i:s"),
            'RunTime' => date("Y-m-d H:i:s"),
            'RestTime' => date("Y-m-d H:i:s"),
            'OnlineWeixinID' => 0,
            'OnlineWeixin' => '',
            'Status' => 'RUNNING',
            'ExceptMessage' => '',
//            'SerialNum' => isset($Message['SerialNum']) ? $Message['SerialNum'] : ''
        ];
        if (isset($Message['Software'])) {
            $data['Software'] = $Message['Software'];
        }
        if (isset($Message['SerialNum'])) {
            $data['SerialNum'] = $Message['SerialNum'];
        }
        try {
            if (isset($info['DeviceID']) && $info['DeviceID'] > 0) {
                //老设备
                $db->update(self::$_name)->cols($data)->where("DeviceID = :DeviceID")
                    ->bindValues(["DeviceID" => $info['DeviceID']])->query();
            } else {
                //新设备
                $db->insert(self::$_name)->cols($data)->query();
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 设备离线
     * @param null $ClientID
     */
    public static function offline($ClientID = null)
    {
        if (null !== $ClientID) {
            $db = Db::instance(Events::getDb());
            $db->update(self::$_name)->cols([
                'ClientID'  =>  '',
                'OnlineStatus'  =>  DEVICE_OFFLINE,
                'OnlineWeixinID'    =>  0,
                'OnlineWeixin'      =>  '',
            ])->where("ClientID = '{$ClientID}'")->query();

            $devices = $db->from(self::$_name)->select()->where("ClientID = '{$ClientID}'")->query();
            $devIds = [];
            foreach ($devices as $dev) {
                // 更新微信表中 deviceId
                $devIds[] = $dev['DeviceID'];

            }
            if ($devIds) {
                $devIds = implode(','. $devIds);
                $db->update('weixins')->cols([
                    'DeviceID' => 0
                ])->where("DeviceID in ({$devIds})")->query();
            }
        }

    }

    public static function changeWorkStatus($ClientID, $data)
    {
        $db = Db::instance(Events::getDb());

        $db->update(self::$_name)->cols([
            'WorkStatus'  =>  $data['WorkStatus'],
        ]);
        $db->where("ClientID = '{$ClientID}'");
        $db->query();
    }

    public static function getInfoByClient($ClientID)
    {
        $db = Db::instance(Events::getDb());
        $sql = "select * from ".self::$_name." where ClientID = '{$ClientID}'";
        return $db->row($sql);
    }

    public static function onlineWeixin($ClientID, $Message)
    {
        $db = Db::instance(Events::getDb());
        $deviceInfo = self::getInfoByClient($ClientID);
        if (!isset($deviceInfo['DeviceID'])) {
            return false;
        }

        $weixinInfo = Weixin::getInfoByWeixin($Message['Weixin']);
        if (!isset($weixinInfo['WeixinID'])) {
            $data = [
                'DeviceID' => $deviceInfo['DeviceID'],
                'Weixin' => $Message['Weixin'],
                'Alias' => isset($Message['Alias']) ? $Message['Alias'] : '',
                'AddDate' => date("Y-m-d H:i:s"),
                'Nickname' => isset($Message['Nickname']) ? $Message['Nickname'] : '',
                'PhoneNumber' => isset($Message['PhoneNumber']) ? $Message['PhoneNumber'] : '',
                'Sex' => isset($Message['Sex']) ? $Message['Sex'] : '',
                'CoverimgUrl' => isset($Message['CoverimgUrl']) ? $Message['CoverimgUrl'] : '',
                'Signature' => isset($Message['Signature']) ? $Message['Signature'] : '',
                'Nation' => isset($Message['Nation']) ? $Message['Nation'] : '',
                'Province' => isset($Message['Province']) ? $Message['Province'] : '',
                'City' => isset($Message['City']) ? $Message['City'] : '',
                'IsXpEnable' => isset($Message['IsXpEnable']) ? $Message['IsXpEnable'] : 'true'
            ];
            $wxId = $db->insert('weixins')->cols($data)->query();
        } else {
            $wxId = $weixinInfo['WeixinID'];
            $db->update('weixins')->cols([
                'DeviceID' => $deviceInfo['DeviceID'],
                'Alias' => isset($Message['Alias']) ? $Message['Alias'] : '',
                'Nickname' => isset($Message['Nickname']) ? $Message['Nickname'] : '',
                'PhoneNumber' => isset($Message['PhoneNumber']) ? $Message['PhoneNumber'] : '',
                'Sex' => isset($Message['Sex']) ? $Message['Sex'] : '',
                'CoverimgUrl' => isset($Message['CoverimgUrl']) ? $Message['CoverimgUrl'] : '',
                'Signature' => isset($Message['Signature']) ? $Message['Signature'] : '',
                'Nation' => isset($Message['Nation']) ? $Message['Nation'] : '',
                'Province' => isset($Message['Province']) ? $Message['Province'] : '',
                'City' => isset($Message['City']) ? $Message['City'] : '',
                'IsXpEnable' => isset($Message['IsXpEnable']) ? $Message['IsXpEnable'] : 'true'
            ])->where("WeixinID = {$wxId}")->query();
        }

        $db->update(self::$_name)->cols([
            'OnlineWeixin' => $weixinInfo['Weixin'],
            'OnlineWeixinID' => $wxId,
            'Status' => 'RUNNING',
            'ExceptMessage' => ''
        ])
            ->where("DeviceID = '{$deviceInfo['DeviceID']}'")
            ->query();

        return ['WeixinID' => $wxId, 'Weixin' => $Message['Weixin'], 'AcceptFriendMsg' => $weixinInfo['AcceptFriendMsg'], 'AcceptGroupMsg' => $weixinInfo['AcceptGroupMsg']];
    }
}