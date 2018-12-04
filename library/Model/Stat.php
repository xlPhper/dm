<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/8/29
 * Time: 20:13
 */
class Model_Stat extends DM_Model
{
    public static $table_name = "stats";
    protected $_name = "stats";
    protected $_primary = "StatID";

    public function updateDayData($weixinID, $adminID, $date,$friendNum,$addNum,$wxAddNum)
    {
        //判断当前微信当天是否有数据
        $select = $this->_db->select();
        $select->from($this->_name)
            ->where("WeixinID = ?", $weixinID)
            ->where("Date = ?", $date);
        $row = $this->_db->fetchRow($select);

        $data['Date'] = $date;
        $data['WeixinID'] = $weixinID;
        $data['AdminID'] = $adminID;
        $data['FriendNum'] = $friendNum;

        //找到前一天的数据
        $select->reset()
            ->from($this->_name)
            ->where("WeixinID = ?", $weixinID)
            ->where("Date < ?", $date)
            ->order("Date desc")
            ->limit(1);
        $findData = $this->_db->fetchRow($select);

        if (isset($findData['StatID'])) {
            $data['NewFriendNum'] = $friendNum - $findData['FriendNum'];
        }else{
            $data['NewFriendNum'] = $friendNum;
        }

        if (isset($row['StatID'])) {
            //存在 减去微信号添加的数量
            $data['PhAddFriendNum'] = $row['PhAddFriendNum'] + $addNum - $wxAddNum;
            $data['WxAddFriendNum'] = $row['WxAddFriendNum'] + $wxAddNum;
            $data['AddFriendNum'] = $row['AddFriendNum'] + $addNum;

            $where = "StatID = '{$row['StatID']}'";
            $this->_db->update($this->_name, $data, $where);
        } else {
            //不存在
            $data['PhAddFriendNum'] = $addNum - $wxAddNum;
            $data['WxAddFriendNum'] = $wxAddNum;
            $data['AddFriendNum'] = $addNum;

            $this->_db->insert($this->_name, $data);
        }
        return true;
    }

    public function getAllByDate($date)
    {
        $select = $this->select()->from($this->_name.' as s')->setIntegrityCheck(false)
            ->joinLeft("weixins as w",'w.WeixinID = s.WeixinID',['w.Weixin'])
            ->where("s.Date = ?", $date);
        return $this->_db->fetchAll($select);
    }

    public function findCount($weixinIds, $day)
    {
        $select = $this->_db->select();
        $select->from($this->_name, ['SUM(SendFriendNum) as num'])
            ->where("Date = ?", $day)
            ->where("WeixinID in (?)", $weixinIds);
        return $this->_db->fetchRow($select);
    }

    /**
     * 获取当前微信下的所有记录
     * @param $weixinId 微信ID
     * @return array
     */
    public function findWeixinID($weixinId)
    {
        $select = $this->select()
            ->where("WeixinID = ?", $weixinId);
        return $this->_db->fetchAll($select);
    }


    /**
     * 根据时间和WeixinID查询
     * @param $StatsID
     * @param $Date
     * @return mixed
     */
    public function findByWeixinID($weixin_id, $date = null)
    {
        $select = $this->select();
        $select->from($this->_name);
        $select->where("WeixinID = ?", $weixin_id);
        if ($date) {
            $select->where("Date = ?", $date);
        }
        return $this->_db->fetchRow($select);
    }

    /**
     * 统计数据
     */
    public function stats($admin_id,$start_date,$end_date)
    {
        // 管理员查询
        $select = $this->fromSlaveDB()->select();
        $select->from($this->_name, ["COUNT(StatID) as WeixinNum","AdminID","Date","SUM(FriendNum) as FriendNum", "SUM(GroupNum) as GroupNum", "SUM(PhSendFriendNum) as PhSendFriendNum", "SUM(PhSendWeixinNum) as PhSendWeixinNum", "SUM(PhSendUnknownNum) as PhSendUnknownNum", "SUM(PhAddFriendNum) as PhAddFriendNum"]);
        $select->where('AdminID in (?)', $admin_id);
        $select->where('Date >= ?', $start_date);
        $select->where('Date <= ?', $end_date);
        $select->group(['AdminID','Date']);
        $data = $this->_db->fetchAll($select);
        $res = [];
        foreach ($data as $v){
            $res[$v['AdminID'].'+'.$v['Date']] = $v;
        }

        // 汇总查询
        $select = $this->fromSlaveDB()->select();
        $select->from($this->_name, ["SUM(FriendNum) as FriendNum", "SUM(GroupNum) as GroupNum", "SUM(PhSendFriendNum) as PhSendFriendNum", "SUM(PhSendWeixinNum) as PhSendWeixinNum", "SUM(PhSendUnknownNum) as PhSendUnknownNum", "SUM(PhAddFriendNum) as PhAddFriendNum","COUNT(StatID) as WeixinNum","Date"]);
        $select->where('Date >= ?', $start_date);
        $select->where('Date <= ?', $end_date);
        $select->group('Date');
        $dataall = $this->_db->fetchAll($select);
        foreach ($dataall as $v){
            $res['all+'.$v['Date']] = $v;
        }

        return $res;
    }

    public function statsAll($start_date,$end_date)
    {
        $select = $this->fromSlaveDB()->select();
        $select->from($this->_name, ["SUM(FriendNum) as FriendNum", "SUM(GroupNum) as GroupNum", "SUM(PhSendFriendNum) as PhSendFriendNum", "SUM(PhSendWeixinNum) as PhSendWeixinNum", "SUM(PhSendUnknownNum) as PhSendUnknownNum", "SUM(PhAddFriendNum) as PhAddFriendNum","COUNT(StatID) as WeixinNum","Date"]);

        // 时间
        $select->where('Date >= ?', $start_date);
        $select->where('Date <= ?', $end_date);
        $select->group('Date');

        return $this->_db->fetchAll($select);
    }

    /**
     * 渠道统计
     */
    public function channel($weixins, $date)
    {
        $select = $this->fromSlaveDB()->select();
        $select->from($this->_name, ["SUM(FriendNum) as FriendNum", "SUM(GroupNum) as GroupNum", "SUM(PhSendFriendNum) as PhSendFriendNum","SUM(PhAddFriendNum) as PhAddFriendNum"]);

        // 管理员ID
        if ($weixins != 'all') {
            $select->where('WeixinID in (?)', $weixins);
        }

        // 时间
        $select->where('Date = ?', $date);


        $res = $this->_db->fetchRow($select);

//      微信号个数
        $weixin_num_select = $this->select()->from($this->_name, 'WeixinID');
        $weixin_num_select->where('Date = ?', $date);

        if ($weixins != 'all') {
            $weixin_num_select->where('WeixinID in (?)', $weixins);
        }
        $weixin_num_select->group('WeixinID');
        $wx_num = $this->_db->fetchAll($weixin_num_select);

        $res['WeixinNum'] = count($wx_num);

        return $res;

    }

    // 微信号的统计
    public function wxStats($admin_id, $weixin_tags, $start_date, $end_date, $nickname, $serial_num, $page, $pagesize)
    {
        $select = $this->fromSlaveDB()->select();
        $select->from($this->_name . ' as s', ["s.StatID", "s.FriendNum", "s.GroupNum", "s.PhSendFriendNum", "s.PhSendWeixinNum", "s.PhSendUnknownNum", "s.PhAddFriendNum","s.WeixinID", "s.Date"])->setIntegrityCheck(false);
        $select->joinLeft('weixins as w', 'w.WeixinID = s.WeixinID', ['w.Nickname as WeixinName', 'w.Weixin']);
        $select->joinLeft('devices as d', 'w.DeviceID = d.DeviceID', 'd.SerialNum');
        $select->where('s.Date >= ?', $start_date);
        $select->where('s.Date <= ?', $end_date);
        if ($nickname) {
            $select->where('w.Nickname like ? or w.Weixin like ?', '%' . $nickname . '%');
        }
        if ($serial_num) {
            $select->where('d.SerialNum like ?', '%' . $serial_num . '%');
        }
        $select->order('d.SerialNum Desc');
        $select->order('s.WeixinID Desc');
        $select->order('s.Date Desc');

        // 管理员
        if ($admin_id) {
            $select->where('s.AdminID = ?', $admin_id);
        }
        // 微信标签
        if ($weixin_tags) {
            $where_msg = '';
            $category_data = explode(',', $weixin_tags);
            foreach ($category_data as $w) {
                $where_msg .= "FIND_IN_SET(" . $w . ",w.CategoryIds) OR ";
            }
            $where_msg = rtrim($where_msg, 'OR ');
            $select->where($where_msg);
        }
        $data = $this->getResult($select, $page, $pagesize);
        $result = [];

        foreach ($data['Results'] as $k=>&$v) {
            if ($v['PhSendFriendNum'] != 0) {
                $v['Pass'] = (number_format($v['PhAddFriendNum'] / $v['PhSendFriendNum'], 2) * 100) . '%';
            } else {
                $v['Pass'] = '00%';
            }
            $result[$v['WeixinID'].':'.$v['Date']] = $v;
        }
        $res = [
            'Page' => $data['Page'],
            'Pagesize' => $data['Pagesize'],
            'TotalCount' => $data['TotalCount'],
            'TotalPage' => $data['TotalPage'],
            'Results' => $result
        ];
        return $res;
    }

    /**
     * 获取微信ID指定时区数据
     */
    public function findWeixinStats($weixin_id, $start_date, $end_date)
    {
        $select = $this->fromSlaveDB()->select();
        // WeixinID
        if ($weixin_id) {
            $select->where('WeixinID = ?', $weixin_id);
        }
        // 开始时区
        if ($start_date) {
            $select->where('Date >= ?', $start_date);
        }
        // 结束时区
        if ($end_date) {
            $select->where('Date <= ?', $end_date);
        }

        return $this->_db->fetchAll($select);

    }

    /**
     * 总汇获取所有统计数据
     */
    public function findAllData($date, $weixinids)
    {
        $select = $this->fromSlaveDB()->select();
        $select->from($this->_name, ["SUM(GroupNum) as GroupNum", "SUM(PhSendFriendNum) as PhSendFriendNum", "SUM(PhSendWeixinNum) as PhSendWeixinNum", "SUM(PhSendUnknownNum) as PhSendUnknownNum", "SUM(PhAddFriendNum) as PhAddFriendNum", "SUM(FriendNum) as FriendNum"])->setIntegrityCheck(false);
        $select->where('Date = ?', $date);
        // 微信标签
        if ($weixinids) {
            $select->where('WeixinID in (?)', $weixinids);
        }
        return $this->_db->fetchRow($select);
    }


    public function findWeixinIds($start_date, $end_date, $weixin_tags)
    {
        $select = $this->fromSlaveDB()->select()->from($this->_name . ' as s', ['WeixinID'])->setIntegrityCheck(false);
        $select->joinLeft('weixins as w', 'w.WeixinID = s.WeixinID', []);
        $select->where('s.Date >= ?', $start_date);
        $select->where('s.Date <= ?', $end_date);
        $where_msg = '';
        $category_data = explode(',', $weixin_tags);
        foreach ($category_data as $w) {
            $where_msg .= "FIND_IN_SET(" . $w . ",w.CategoryIds) OR ";
        }
        $where_msg = rtrim($where_msg, 'OR ');
        $select->where($where_msg);
        $select->group('s.WeixinID');
        $data = $this->_db->fetchAll($select);

        $res = [];
        foreach ($data as $k => $v) {
            $res[] = $v['WeixinID'];
        }
        return $res;
    }

    /**
     * 微信号添加好友数据统计 筛选
     */
    public function weixinStat($weixinIds,$start_date,$end_date)
    {
        $select = $this->_db->select();
        $select->from($this->_name)
            ->where("WeixinID in (?)",$weixinIds)
            ->where("Date >= ?",$start_date)
            ->where("Date <= ?",$end_date)
            ->order("Date DESC");
        $data = $this->_db->fetchAll($select);
        $res = array();

        foreach ($data as $s){
            $res[$s['WeixinID'].':'.$s['Date']] = $s;
        }

        return $res;
    }


    /**
     * 获取所有统计数据
     *
     * @param $start_date
     * @param $end_date
     */
    public function findAllStat($weixin_tag,$admin_id,$search,$serial_num,$start_date,$end_date)
    {
        $select = $this->select()->from($this->_name.' as ws',['ws.Date','SUM(ws.FriendNum) as FriendNum','SUM(ws.WxSendFriendNum) as WxSendFriendNum','SUM(ws.WxAddFriendNum) as WxAddFriendNum'])->setIntegrityCheck(false);
        $select->joinLeft('weixins as w','w.WeixinID = ws.WeixinID',[]);
        $select->joinLeft('devices as d','d.DeviceID = w.DeviceID',[]);
        if ($weixin_tag){
            $select->where('FIND_IN_SET(?,w.CategoryIds)',$weixin_tag);
        }
        if ($admin_id){
            $select->where('w.AdminID = ?',$admin_id);
        }
        if ($search){
            $select->where('w.Weixin like ? OR w.Nickname ?','%'.$search.'%');
        }
        if ($serial_num){
            $select->where('d.SerialNum like ?','%'.$serial_num.'%');
        }
        $select->where("ws.Date >= ?",$start_date);
        $select->where("ws.Date <= ?",$end_date);
        $select->order("ws.Date DESC");
        $select->group("ws.Date");
        return $this->_db->fetchAll($select);
    }

    /**
     * 添加统计数据
     *
     * @param $num
     */
    public function saveStats($WeixinID,$AdminID,$FriendNum,$Num)
    {
        $date = date('Y-m-d');
        $select = $this->_db->select();
        $select->from($this->_name)
            ->where("WeixinID = ?", $WeixinID)
            ->where("Date = ?", $date);
        $row = $this->_db->fetchRow($select);

        $data['Date'] = $date;
        $data['WeixinID'] = $WeixinID;
        $data['AdminID'] = $AdminID;
        $data['FriendNum'] = $FriendNum;

        if (isset($row['StatID'])) {
            //存在 减去微信号添加的数量
            $data['AddFriendNum'] = $row['AddFriendNum'] + $Num;

            $where = "StatID = '{$row['StatID']}'";
            $this->_db->update($this->_name, $data, $where);
        } else {
            //不存在
            $data['AddFriendNum'] = $Num;

            $this->_db->insert($this->_name, $data);
        }
    }
}