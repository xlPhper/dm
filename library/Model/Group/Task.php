<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/7/18
 * Time: 11:10
 */
class Model_Group_Task extends Model_Group
{
    public function createGroup($WeixinID, $Name, $StartNum, $Type, $Friends)
    {

    }

    /**
     * 发布公告
     * @param $GroupIDs
     * @param $announcement
     */
    public function announcement($ChatroomIDs, $announcement)
    {
        $taskModel = new Model_Task();
        //对群分组
        $groupModel = new Model_Group();
        $weixinModel = new Model_Weixin();

        $weixin = [];
        foreach($ChatroomIDs as $ChatroomID){
            $groupInfo = $groupModel->getInfoByChatroom($ChatroomID);
            $weixin[$groupInfo['WeixinID']][] = $groupInfo['GroupID'];
        }

        foreach($weixin as $WeixinID => $IDS){
            $weixinInfo = $weixinModel->getInfo($WeixinID);
            if($weixinInfo['UserID'] <> Zend_Registry::get('USERID')){
                continue;
            }
            $data = [
                'DeviceID'  =>  $weixinInfo['DeviceID'],
                'WeixinID'  =>  $weixinInfo['WeixinID'],
                'TaskConfig'    =>  [
                    'ChatroomIDs'   =>  $IDS,
                    'Announcement'  =>  $announcement
                ],
                'TaskBody'  =>  TASK_BODY_GROUP,
                'TotalID'    =>  $IDS
            ];
            $taskModel->add("GroupSendAnnouncement", $data);
        }
    }

    /**
     * 面对面建群
     * @param $WeixinIDs
     * @param int $Num
     */
    public function createGroupByFace($WeixinID, $Num = 10)
    {
        $weixinModel = new Model_Weixin();
        $taskModel = new Model_Task();

        $weixinInfo = $weixinModel->getInfo($WeixinID);
        if($weixinInfo['UserID'] <> Zend_Registry::get('USERID')){
            return false;
        }
        $data = [
            'DeviceID'  =>  $weixinInfo['DeviceID'],
            'WeixinID'  =>  $weixinInfo['WeixinID'],
            'TaskConfig'    =>  [
                'Num'   =>  $Num,
            ],
        ];
        $taskModel->add("GroupCreateByFace", $data);
    }

    /**
     * 通过好友建群
     * @param $WeixinID
     * @param $Friends
     * @return bool
     */
    public function createGroupByFriends($WeixinID, $Friends)
    {
        $weixinModel = new Model_Weixin();

        $taskModel = new Model_Task();

        $weixinInfo = $weixinModel->getInfo($WeixinID);
        if($weixinInfo['UserID'] <> Zend_Registry::get('USERID')){
            return false;
        }

        $data = [
            'DeviceID'  =>  $weixinInfo['DeviceID'],
            'WeixinID'  =>  $weixinInfo['WeixinID'],
            'TaskConfig'    =>  [
                'Friends'   =>  $Friends,
            ],
        ];
        $taskModel->add("GroupCreateByFriends", $data);
    }
}