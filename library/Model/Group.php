<?php

/**
 * Created by PhpStorm.
 * User: Tim
 * Date: 2018/4/25
 * Time: 0:06
 */
class Model_Group extends DM_Model
{
    public static $table_name = "groups";
    protected $_name = "groups";
    protected $_primary = "GroupID";

    /**
     * 查询指定ID详情
     */
    public function findByID($ID)
    {
        $select = $this->select()->where('GroupID = ?',$ID);
        return $this->_db->fetchRow($select);
    }

    /**
     * 查询指定微信标识查询
     */
    public function findByChatroomID($ID)
    {
        $select = $this->select()->where('ChatroomID = ?',$ID);
        return $this->_db->fetchRow($select);
    }

    /**
     * 初始群信息
     * @param $WeixinID 微信ID
     * @param $QRCode   二维码字符串
     */
    public function add($QRCode, $CategoryID, $Type = 2)
    {
        $UserID = Zend_Registry::get('USERID');
//        $Codes = json_decode($QRCode);
//        $len = count($Codes);
        if ($Type == 1) {
            $QRCodeType = 'QRCode';
            $tasktype = 'QRCodeStr';
        } elseif ($Type == 2) {
            $QRCodeType = 'QRCodeImg';
            $tasktype = 'QRCodeImg';
        }
        $data = [
            'UserID' => $UserID,
            $QRCodeType => $QRCode,
            'CategoryID' => $CategoryID,
            'QRCodeDate' => date('Y-m-d')
        ];
        $flag = $this->insert($data);
        if ($flag) {
            $data['WeixinID'] = 4;
            $data['TaskRunTime'] = '5 * * * *';
            $data['TaskConfig'] = [
                'Type' => $Type,
                $tasktype => $QRCode,
                'Interval' => '5'
            ];
            $task_model = new Model_Task();
            $res = $task_model->add('GroupJoin', $data);
            if (!$res) {
                return false;
            }
        } else {
            return false;
        }
        return true;
    }

    /**
     * 同步微信加入群信息
     * @param $WeixinID  加入的微信ID
     * @param $QRCode    二维码信息
     * @param int $Type 二维码类型 1:二维码字符串 2:二维码图片
     */
    public function inGroup($WeixinID, $QRCode, $Type = 1)
    {
        if ($Type == 1) {
            $BRCodeType = 'QRCode';
        } elseif ($Type == 2) {
            $BRCodeType = 'QRCodeImg';
        }
        $select = $this->_db->select();
        $select->from($this->_name)
            ->where($BRCodeType . " = ?", $QRCode);
        $group = $this->_db->fetchRow($select);
        $weixinData = [
            'WeixinID' => $WeixinID,
            'GroupID' => $group['GroupID'],
            'AddDate' => date('Y-m-d')
        ];
        $weixiningroupModel = new Model_Weixin_Groups();
        $result = $weixiningroupModel->insert($weixinData);
        return $result;
    }

    /**
     * 退出群
     * @param $ChatroomID 微信群ID标识
     * @return int
     */
    public function quit($ChatroomID, $DeviceID)
    {
        $chatroom = json_decode($ChatroomID);
        $len = count($chatroom);
        for ($i = 0; $i < $len; $i++) {
            $result = $this->delete(['ChatroomID = ?' => $ChatroomID[$i]]);
            $json[] = [
                'ChatroomID' => $ChatroomID[$i]
            ];
        }
        if ($result) {
            //增加到任务里
            $taskModel = new Model_Task();
            $RequestData = [
                'TaskConfig' => $json,
                'DeviceID' => $DeviceID,
                'TotalID' => $chatroom
            ];
            $taskModel->add('GroupQuit', $RequestData);
        }
        return $result;
    }

    /**
     * 拉人进群
     * @param $ChatroomID
     * @param $Friends
     */
    public function MemberIn($ChatroomID, $Friends, $DeviceID)
    {
        $FriendsData = json_decode($Friends);
        //增加到任务里
        $json = [
            'ChatroomID' => $ChatroomID,
            'Friends' => $FriendsData
        ];
        $taskModel = new Model_Task();
        $RequestData = [
            'TaskConfig' => $json,
            'DeviceID' => $DeviceID
        ];
        $taskModel->add('GroupMemberIn', $RequestData);

    }


    public function getInfoByChatroom($chatroom)
    {
        $select = $this->_db->select();
        $select->from($this->_name)
            ->where("ChatroomID = ?", $chatroom);
        return $this->_db->fetchRow($select);
    }

    public function updateInfo($data)
    {
        //查询是否有这个群
        $info = $this->getInfoByChatroom($data['ChatroomID']);
        if (!isset($info['GroupID'])) {
            $data = [
                'ChatroomID' => $data['ChatroomID'],
                'Name' => $data['Name'],
                'UserNum' => $data['UserNum'],
                'QRCode' => $data['QRCode'],
                'QRCodeDate' => date("Y-m-d H:i:s"),
                'CreateDate' => date("Y-m-d H:i:s"),
                'IsSelf' => $data['IsSelf']
            ];
            $this->insert($data);
            $GroupID = $this->_db->lastInsertId();
        } else {
            $data = [
                'UserNum' => $data['UserNum'],
                'QRCode' => $data['QRCode'],
                'IsSelf' => $data['IsSelf'],
            ];
            if ($data['Name'] <> $info['Name']) {
                $data['RealName'] = $data['Name'];
                unset($data['Name']);
            }
            $where = $this->_db->quoteInto("GroupID = ?", $data['GroupID']);
            $this->update($data, $where);
            $GroupID = $info['GroupID'];
        }
        return $GroupID;
    }

    /**
     * 更新二维码
     * @param $GroupID
     * @param $qrcode
     * @return int
     */
    public function updateQrcode($GroupID, $qrcode)
    {
        $data = [
            'QRCode' => $qrcode,
            'QRCodeDate' => date("Y-m-d H:i:s")
        ];
        $where = $this->_db->quoteInto("GroupID = ?", $GroupID);
        return $this->update($data, $where);
    }

    public function checkGroupName($groupInfo)
    {
        $groupConfigModel = new Model_Group_Config();
        $configInfo = $groupConfigModel->getInfo($groupInfo['ConfigID']);
        if ($configInfo['HoldName']) {
            //需要保持名称,下发改名任务
            $taskModel = new Model_Task();
            $taskModel->add($groupInfo['DeviceID'], $groupInfo['']);
        }
    }

    /**
     * 获取群ID
     * @param $WeixinID 微信号
     * @param int $IsSelf 是否为管理员
     * @return array
     */
    public function getGroupIDByWeixinID($WeixinID, $IsSelf = null)
    {
        $select = $this->_db->select();
        $select->from($this->_name, ['GroupID'])
            ->where("WeixinID = ?", $WeixinID);
        if (null !== $IsSelf) {
            $select->where("IsSelf = ?", $IsSelf);
        }
        return $this->_db->fetchCol($select);
    }

    /**
     * 判断该分类下是否有微信群
     */
    public function findIsCategory($TagID)
    {
        $select = $this->select()->from($this->_name,'COUNT(*) as Num');
        $select->where('FIND_IN_SET(?,GroupTags)',$TagID);
        return $this->_db->fetchRow($select);
    }


    /**
     * ID的in查询
     * @param $GroupIds 群的Ids
     */
    public function findGroups($GroupIds)
    {
        $select = $this->select()->where('GroupID in (?)',explode(',',$GroupIds));

        return $this->_db->fetchAll($select);
    }

    /**
     * 获取自用群
     * @return mixed
     */
    public function findGroupAdmin($GroupIds)
    {
        $select = $this->select()->from($this->_name.' as g',['GroupID','ChatroomID'])->setIntegrityCheck(false)
            ->join('weixin_in_groups as wg','wg.GroupID = g.GroupID AND wg.IsAdmin=1',['ID','WeixinID'])
            ->where('g.IsSelf = 1')
            ->where('g.GroupID in (?)',$GroupIds);

        return $this->_db->fetchAll($select);
    }

}