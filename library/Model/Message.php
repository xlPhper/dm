<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/5/4
 * Time: 9:19
 */
class Model_Message extends DM_Model
{
    public static $table_name = "messages";
    protected $_name = "messages";
    protected $_primary = "MessageID";


    /**
     * 获取活跃用户数量
     *
     * @param $WeixinIds 微信Id
     * @param $Date      时间
     */
    public function getActiveFirend($WeixinIds = null,$Date = array())
    {
        $select = $this->fromSlaveDB()->select();
        $select->from($this->_name,["DATE_FORMAT(AddDate,'%Y-%m-%d') as AddDate","COUNT(ReceiverWx) as FriendNum"]);
        $select->where("DATE_FORMAT(AddDate,'%Y-%m-%d') in (?)", $Date);
        if ($WeixinIds){
            $select->where("ReceiverWx in (?)", $WeixinIds);
        }
        $select->group("DATE_FORMAT(AddDate,'%Y-%m-%d')");
        $select->order('AddDate Desc');
        $data = $this->_db->fetchAll($select);
        $res = array();

        foreach ($data as $d){
            $res[$d['AddDate']] = $d;
        }

        return $res;
    }

    /**
     * 获取微信的发送信息数据
     *
     * @param $WeixinIds
     * @param $Date
     */
    public function getMessageData($WeixinIds = null,$startDate = '',$endDate = '')
    {
        $select = $this->fromSlaveDB()->select();
        $select->from($this->_name,["SenderWx"]);
        if ($WeixinIds){
            $select->where("SenderWx in (?)", $WeixinIds);
        }
        if (!empty($startDate) && !empty($endDate)){
            $select->where("AddDate <= ?", $startDate.' 23:59:59');
            $select->where("AddDate >= ?", $endDate.' 00:00:00');
        }
        $select->group('SenderWx');
        $data = $this->_db->fetchAll($select);
        return $data == false?0:count($data);
    }

    /**
     * 回复信息的数据信息
     *
     * @param null $WeixinIds
     * @param array $Date
     */
    public function getAnswerMessageData($WeixinIds = null,$startDate = '',$endDate = '')
    {
        $select = $this->fromSlaveDB()->select();
        $select->from($this->_name,["ReceiverWx"]);
        if ($WeixinIds){
            $select->where("SenderWx in (?)", $WeixinIds);
        }
        if (!empty($startDate) && !empty($endDate)){
            $select->where("AddDate <= ?", $startDate.' 23:59:59');
            $select->where("AddDate >= ?", $endDate.' 00:00:00');
        }
        $select->group('ReceiverWx');
        $data = $this->_db->fetchAll($select);
        return $data == false?0:count($data);
    }

    public function getAllData($lastMessageID, $Num = 1000)
    {
        if($lastMessageID === false){
            $lastMessageID = 0;
        }
        $select = $this->fromSlaveDB()->select();
        $select->from($this->_name, ['MessageID', 'ReceiverWx','SenderWx','Content','AddDate','MsgType'])
               ->where("MessageID > ?", $lastMessageID)
               ->order("MessageID asc")
               ->limit($Num);
        return $this->fetchAll($select);
    }

    public function statMessageNum($Weixin, $startTime, $stopTime)
    {
        $select = $this->fromSlaveDB()->select();
        $select->from($this->_name, ["count(*) as num"])
            ->where("SenderWx = ?", $Weixin)
            ->where("AddDate >= ?", $startTime)
            ->where("AddDate <= ?", $stopTime);
        $info = $this->fromSlaveDB()->fetchRow($select)->toArray();
        return $info['num'];
    }

    /**
     * @param array $weixins 微信账号
     * @param $startTime
     * @param $endTime
     * @param $unit 粒度1:30分钟,2:1小时,3:1天
     * @return array
     * 活跃客户数据统计
     */
    public function getActiveStatData($weixins = [], $startTime, $endTime, $unit){
        if(empty($weixins)){
            return [];
        }
        switch ($unit){
            case '1': //30分钟
                $sql="SELECT TimeString, COUNT(ReceiverWx) AS Num FROM
	(
	SELECT ReceiverWx,
		DATE_FORMAT(
			concat( date( AddDate ), ' ', HOUR ( AddDate ), ':', floor( MINUTE ( AddDate ) / 30 ) * 30 ),
			'%Y-%m-%d %H:%i' 
		) AS TimeString 
	FROM messages
	WHERE ReceiverWx IN ('".implode("','", $weixins)."')  AND AddDate >= '{$startTime}' AND AddDate <= '{$endTime}'
	) a 
GROUP BY TimeString
ORDER BY TimeString Asc";
                $res = $this->fromSlaveDB()->getAdapter()->fetchAll($sql);
                break;
            case '3': //1天
                $res = $this->fromSlaveDB()->select()->from($this->_name, ["DATE_FORMAT(AddDate,'%Y-%m-%d') as TimeString","COUNT(ReceiverWx) as Num"])
                        ->where("ReceiverWx in (?)", $weixins)->where('AddDate >= ?', $startTime)->where('AddDate <= ?', $endTime)
                        ->group("DATE_FORMAT(AddDate,'%Y-%m-%d')")->order('TimeString Asc')->query()->fetchAll();
                break;
            case '2': //1小时
            default:
                $res = $this->fromSlaveDB()->select()->from($this->_name, ["DATE_FORMAT(AddDate,'%Y-%m-%d %H:00') as TimeString","COUNT(ReceiverWx) as Num"])
                ->where("ReceiverWx in (?)", $weixins)->where('AddDate >= ?', $startTime)->where('AddDate <= ?', $endTime)
                ->group("DATE_FORMAT(AddDate,'%Y-%m-%d %H:00')")->order('TimeString Asc')->query()->fetchAll();
                break;
        }
        $data = array();

        foreach ($res as $d){
            $data[$d['TimeString']] = $d['Num'];
        }

        return $data;
    }
}