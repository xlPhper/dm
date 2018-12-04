<?php

require_once APPLICATION_PATH . '/controllers/Admin/AdminBase.php';

class Admin_GroupsController extends AdminBase
{

    /**
     * 微信群列表
     */
    public function listAction()
    {
        $page = $this->getParam('Page', 1);
        $pagesize = $this->getParam('Pagesize', 100);
        $search = $this->_getParam('Search',null);   // 搜索字段
        $is_self = $this->_getParam('IsSelf',null);  // 群类型【1-自有(包含别人转移过来的) 2-他人】
        $using = $this->_getParam('Using',null);     // 可用状态 【1-可用群 2-已用群 】
        $weixin_tags = $this->_getParam('WexinTags',null);
        $group_tags = $this->_getParam('GroupTags',null);
        $min_usernum = $this->_getParam('MinUsernum',null);
        $max_usernum = $this->_getParam('MaxUsernum',null);
        $start_date = $this->_getParam('StartDate',null);
        $end_date = $this->_getParam('EndDate',null);


        // Model
        $group_model = new Model_Group();
        $weixin_model = new Model_Weixin();
        $category_model = new Model_Category();
        $weixin_in_group_model = new Model_Weixin_Groups();


        $select = $group_model->fromSlaveDB()->select()->from($group_model->getTableName(),['GroupID','Name','RealName','UserNum','IsSelf','QRCode','QRCodeImg','GroupTags']);

        // 微信标签筛选
        if ($weixin_tags){
            $weixins = $weixin_model->findWeixinCategory($weixin_tags);
            if ($weixins){
                $weixin_ids = array();
                foreach ($weixins as $v){
                    $weixin_ids[] = $v['WeixinID'];
                }
                $group_ids = $weixin_in_group_model->findWeixinGroup($weixin_ids);
                $group_ids = empty($group_ids)?[0]:$group_ids;
            }else{
                $group_ids = [0];
            }
            $select->where("GroupID in (?)",$group_ids);
        }
        // 搜索字段
        if ($search){
            $select->where("Name like ? AND RealName like ?",'%'.$search.'%');
        }
        // 是否是自用群
        if ($is_self){
            $select->where("IsSelf = ?",$is_self);
        }
        // 可用状态(可用-群成员=0 已用-群成员>0)
        if ($using == 1){
            $select->where("UserNum = 0");
        }elseif($using == 2){
            $select->where("UserNum > 0");
        }
        // 群标签筛选
        if ($group_tags){
            $select->where("FIND_IN_SET(?,GroupTags)",$weixin_tags);
        }
        // 最少人数
        if ($min_usernum){
            $select->where("UserNum >= ?",$min_usernum);
        }
        // 最多人数
        if ($max_usernum){
            $select->where("UserNum <= ?",$max_usernum);
        }
        // 头-创建时间
        if ($start_date){
            $select->where("CreateDate >= ?",$start_date);
        }
        // 尾-创建时间
        if ($end_date){
            $select->where("CreateDate <= ?",$end_date);
        }
        $select->order('GroupID DESC');

        $res = $group_model->getResult($select,$page,$pagesize);

        if ($res['Results']){

            foreach ($res['Results'] as &$group){
                // 群标签转换
                if ($group['GroupTags']){
                    $tags = $category_model->findCategoryName($group['GroupTags']);
                    $weixin['GroupTagsName'] = implode(',',$tags);
                }else{
                    $group['GroupTagsName'] = [];
                }

                // 群的是否可用状态
                if ($group['UserNum'] == 0){
                    $group['Using'] = 1;
                }else{
                    $group['Using'] = 2;
                }

                $group_admin = $weixin_in_group_model->findGroupUser($group['GroupID']);
                if ($group_admin){
                    $group['WeixinID'] = $group_admin['WeixinID'];
                    $group['WeixinNickname'] = $group_admin['Nickname'];
                }else{
                    $group['WeixinID'] = 0;
                    $group['WeixinNickname'] = '';
                }
            }

        }

        $this->showJson(1,'群列表',$res);
    }

    // 微信号设置标签
    public function saveTagsAction()
    {
        $group_ids = $this->_getParam('GroupIds', null);
        $grouptags = $this->_getParam('GroupTags', null);
        $type = $this->_getParam('Type', 1);  // 1-覆盖 2-叠加 3-移除【默认覆盖】
        if (empty($group_ids)) {
            $this->showJson(0, '群ID不存在');
        }
        if (empty($grouptags)) {
            $this->showJson(0, '选择标签参数');
        }

        $group_id_data = explode(',', $group_ids);
        $grouptags_data = explode(',',$grouptags);

        // Model
        $group_model = new Model_Group();

        foreach ($group_id_data as $group) {

            $info = $group_model->findByID($group);
            if ($info == false) {
                $this->showJson(0, 'GroupID[' . $group . '],中查询不到数据');
            }
            $update = '';
            // 原标签数组
            $original_grouptags = explode(',', $info['GroupTags']);
            switch ($type){
                case 1:
                    foreach ($grouptags_data as $cate){
                        $update .= ',' . $cate;
                    }
                    $update = trim($update,',');
                    $update_data = ['GroupTags'=>$update];
                    break;
                case 2:
                    foreach ($grouptags_data as $cate){
                        $in_grouptags = in_array($cate, $original_grouptags);
                        if (!$in_grouptags) {
                            $update .= ',' . $cate;
                        }
                    }
                    $update = trim($info['GroupTags'].$update,',');
                    $update_data = ['GroupTags'=>$update];
                    break;
                case 3:
                    foreach ($grouptags_data as $cate){
                        $in_category = in_array($cate,$original_grouptags);
                        if ($in_category) {
                            $key = array_search($cate ,$original_grouptags);
                            array_splice($original_grouptags,$key,1);
                        }
                    }
                    $update = implode(',',$original_grouptags);
                    $update_data = ['GroupTags'=>$update];
                    break;
            }
            if ($update_data){
                $res = $group_model->update($update_data, ['GroupID = ?' => $group]);
                if (!$res) {
                    $this->showJson(0, '修改失败');
                }
            }
        }
        $this->showJson(1, '修改成功');
    }

    /**
     * 获取编辑页详情
     */
    public function saveInfoAction()
    {
        $group_id = $this->_getParam('GroupID',null);

        // Model
        $group_model = new Model_Group();
        $categroy_model = new Model_Category();


        $group_info = $group_model->findByID($group_id);

        $categroy_name = $categroy_model->findCategoryName($group_info['GroupTags']);

        $group_info['GroupTagsName'] = $categroy_name;

        $this->showJson(1,'详情',$group_info);

    }

    /**
     * 编辑群
     *
     * 内容：群实际名称/群标签/群状态
     */
    public function saveAction()
    {
        $group_id = $this->_getParam('GroupID',null);
        $group_tags = $this->_getParam('GroupTags',null);
        $status = $this->_getParam('Status',null);
        $real_name = $this->_getParam('RealName',null);

        // Model
        $group_model = new Model_Group();
        $categroy_model = new Model_Category();

        $group_tags_data = explode(',',$group_tags);

        // 验证选择的标签
        foreach ($group_tags_data as $tags){
            $categroy_info = $categroy_model->getCategoryByIdType($tags,CATEGORY_TYPE_WXGROUP);
            if (!$categroy_info){
                $this->showJson(0,'标签信息不合法');
            }
        }

        $res = $group_model->update(['GroupTags'=>$group_tags,'Status'=>$status,'RealName'=>$real_name],['GroupID = ?'=>$group_id]);

        if ($res){
            $this->showJson(1,'修改成功');
        }else{
            $this->showJson(0,'修改失败');
        }

    }


    /**
     * 更新二维码
     */
    public function saveQrimgAction()
    {
        $groups = $this->_getParam('Group');

        if ($groups){
            $this->showJson(0,'无参数');

        }
        try{
            $group_model = new Model_Group();
            $task_model = new Model_Task();
            $weixin_model = new Model_Weixin();

            $groups = explode(',',$groups);

            foreach ($groups as $g){

                // 所属群ID
                $group_info = $group_model->findByID($g['GroupID']);

                // 所属群的微信成员WeixinID
                $weixin_info = $weixin_model->findByID($g['WeixinID']);

                $task_config = [
                    'GroupID' => $g['GroupID'],
                    'ChatroomID' => $group_info['ChatroomID'],
                    'Weixin' => $weixin_info['Weixin']
                ];
                $task_config = json_encode($task_config);

                $task_model->addCommonTask(TASK_CODE_GROUP_QRIMG, $g['WeixinID'], $task_config, $this->getLoginUserId());
            }
            $this->showJson(1,'任务添加成功');

        }catch (Exception $e){
            $this->showJson(0,'修改失败'.$e->getMessage());
        }

    }

    /**
     * 创建群设置
     */
    public function createGroupAction()
    {
        $weixin_tags = $this->_getParam('WeixinTags',null);
        $group_tags = $this->_getParam('GroupTags',null);
        $type = $this->_getParam('Type',1);
        $group_name = $this->_getParam('GroupName',null);
        $start_date = $this->_getParam('StartDate',date('Y-m-d'));
        $end_date = $this->_getParam('EndDate',date('Y-m-d',strtotime('+1 day')));
        $create_num = $this->_getParam('CreateNum',null);
        $create_time = $this->_getParam('CreateTime',null);

        if (!$weixin_tags){
            $this->showJson(0,'请选择微信标签');
        }
        if (!$group_tags){
            $this->showJson(0,'请选择群标签');
        }
        if (!$group_name){
            $this->showJson(0,'请填写群名称');
        }
        if (!$create_num){
            $this->showJson(0,'请选择每次创建个数');
        }
        if ($start_date < date('Y-m-d')){
            $this->showJson(0,'请选择有效的执行时间');
        }
        if ($end_date < date('Y-m-d',strtotime('+1 day'))){
            $this->showJson(0,'执行的有效时间至少一天');
        }
        if (!$create_time){
            $create_time = date('H:i',strtotime('+10 minute'));
        }

        // 匹配下次执行时间
        $create_time_data = explode(',',$create_time);
        $next_run_time = '';
        foreach ($create_time_data as $tim){
            if ($create_time.' '.$tim > date('Y-m-d H:i')){
                $next_run_time = $create_time.' '.$tim;
            }
        }

        $data = [
            'WeixinTags' => $weixin_tags,
            'GroupTags' => $group_tags,
            'Type' => $type,
            'Name' => $group_name,
            'StartDate' => $start_date,
            'EndDate' => $end_date,
            'CreateNum' => $create_num,
            'CreateTime' => $create_time,
            'NextRunTime' => $next_run_time,
            'AdminID' => $this->getLoginUserId()
        ];

        try{
            $group_create_model = new Model_Group_Create();
            $group_create_model->insert($data);
            $this->showJson(1,'设置成功');

        }catch (Exception $e){
            $this->showJson(0,'设置失败'.$e->getMessage());
        }

    }

    /**
     * 加群设置
     */
    public function joinGroupAction()
    {
        $weixin_tags = $this->_getParam('WeixinTags',null);
        $group_tags = $this->_getParam('GroupTags',null);
        $start_date = $this->_getParam('StartDate',date('Y-m-d'));
        $end_date = $this->_getParam('EndDate',date('Y-m-d',strtotime('+1 day')));
        $join_num = $this->_getParam('JoinNum',null);
        $join_time = $this->_getParam('JoinTime',null);

        if (!$weixin_tags){
            $this->showJson(0,'请选择微信标签');
        }
        if (!$group_tags){
            $this->showJson(0,'请选择群标签');
        }
        if (!$join_num){
            $this->showJson(0,'请选择每次添加个数');
        }

        // 匹配下次执行时间
        $join_time_data = explode(',',$join_time);
        $next_run_time = '';
        foreach ($join_time_data as $tim){
            if ($start_date.' '.$tim > date('Y-m-d H:i')){
                $next_run_time = $start_date.' '.$tim;
            }
        }

        $data = [
            'WeixinTags' => $weixin_tags,
            'GroupTags' => $group_tags,
            'StartDate' => $start_date,
            'EndDate' => $end_date,
            'JoinNum' => $join_num,
            'JoinTime' => $join_time,
            'NextRunTime' => $next_run_time,
            'AdminID' => $this->getLoginUserId()
        ];
        try{
            $group_join_model = new Model_Group_Join();
            $group_join_model->insert($data);
            $this->showJson(1,'设置成功');

        }catch (Exception $e){
            $this->showJson(0,'设置失败'.$e->getMessage());
        }
    }

    /**
     * 拉人进群
     */
    public function weixinJoinGroupAction()
    {
        $weixins = $this->_getParam('Weixins',null);
        $group = $this->_getParam('Group',null);

        // 获取数据
        $group_data = json_decode($group,1);
        $weixin_data = explode(',',$weixins);

        // Model
        $group_model = new Model_Group();
        $task_model = new Model_Task();
        $weixin_model = new Model_Weixin();

        foreach ($group_data as $v){

            // 所属群ID
            $group_info = $group_model->findByID($v['GroupID']);

            // 所属群的微信成员WeixinID
            $weixin_info = $weixin_model->findByID($v['WeixinID']);

            // 需要加入群的微信号
            $weixins = $weixin_model->findWeixsCode($weixin_data);

            if (!$group_info){
                $this->showJson(0,'群ID'.$v['GroupID'].'无效');
            }
            if (!$weixin_info){
                $this->showJson(0,'微信ID无效');
            }

            $task_config = [
                'GroupID' => $v['GroupID'],
                'ChatroomID' => $group_info['ChatroomID'],
                'Weixin' => $weixin_info['Weixin'],
                'WeixinData' =>$weixins
            ];
            $task_config = json_encode($task_config);

            $task_model->addCommonTask(TASK_CODE_GROUP_ADD_MEMBER, $v['WeixinID'], $task_config, $this->getLoginUserId());

        }

        $this->showJson(1,'成功');
    }

    /**
     * 退群
     */
    public function quitGroupAction()
    {
        try{

            $group = $this->_getParam('Group',null);
            $group_data = json_decode($group,1);

            // Model
            $task_model = new Model_Task();
            $group_model = new Model_Group();
            $weixin_model = new Model_Weixin();

            foreach ($group_data as $g){

                // 所属群ID
                $group_info = $group_model->findByID($g['GroupID']);

                // 所属群的微信成员WeixinID
                $weixin_info = $weixin_model->findByID($g['WeixinID']);

                // 添加退群任务
                $task_config = [
                    'GroupID' => $g['GroupID'],
                    'ChatroomID' => $group_info['ChatroomID'],
                    'Weixin' =>$weixin_info['Weixin']
                ];
                $task_config = json_encode($task_config);
                $task_model->addCommonTask(TASK_CODE_GROUP_QUIT, $g['WeixinID'], $task_config, $this->getLoginUserId());
                $this->showJson(1,'添加退群任务');

            }

        }catch (Exception $e){
            $this->showJson(0,'退出失败'.$e->getMessage());
        }
    }


    /**
     * 转移群
     */
    public function transferAction()
    {
        $weixin_id = $this->_getParam('WeixinID',null);
        $group_ids = $this->_getParam('GroupIds',null);

        // Model
        $weixin_group_model = new Model_Weixin_Groups();
        $weixin_model = new Model_Weixin();
        $task_model = new Model_Task();
        $group_model = new Model_Group();

        $group_data = $group_model->findGroupAdmin(explode(',',$group_ids));

        $transfer_weixin = $weixin_model->findByID($weixin_id);

        foreach ($group_data as $g){
            try{
                // 转移的微信号是否在微信群里
                $isInGroup = $weixin_group_model->findWeixinIsGroup($weixin_id,$g['GroupID']);

                // 获取群主的微信号
                $group_weixin = $weixin_model->findByID($g['WeixinID']);

                if ($group_weixin && $isInGroup){

                    // 添加转群任务
                    $task_config = [
                        'GroupID' => $g['GroupID'],
                        'TransferWeixin' => $transfer_weixin['Weixin'],
                        'ChatroomID' => $g['ChatroomID'],
                        'Weixin' =>$group_weixin['Weixin']
                    ];
                    $task_config = json_encode($task_config);
                    $task_model->addCommonTask(TASK_CODE_GROUP_TRANSFER, $g['WeixinID'], $task_config, $this->getLoginUserId());


                    $this->showJson(1,'添加转移群任务');

                }
            }catch (Exception $e){
                $this->showJson(0,'转移失败'.$e->getMessage());
            }

        }

    }

    /**
     * 修改群名称
     */
    public function saveGroupNameAction()
    {

    }

    /**
     * 微信号加群 列表
     */
    public function qrJoinListAction()
    {
        $page = $this->getParam('Page', 1);
        $pagesize = $this->getParam('Pagesize', 100);
        $AdminID = (int)$this->_getParam("AdminID",0);
        $Channel = $this->_getParam('Channel');
        $GroupTagID = $this->_getParam('GroupTagID');
        $StartDate = $this->_getParam('StartDate');
        $EndDate = $this->_getParam('EndDate');

        $model = new Model_Group_QrJoin();
        $select = $model->select();
        if (!empty($AdminID)){
            $select->where("AdminID = ?",$AdminID);
        }
        if (!empty($Channel)){
            $select->where("Channel = ?",$Channel);
        }
        if (!empty($GroupTagID)){
            $select->where("FIND_IN_SET(?,GroupTags)",$GroupTagID);
        }
        if (!empty($StartDate)){
            $select->where("StartDate >= ?",$StartDate);
        }
        if (!empty($EndDate)){
            $select->where("EndDate <= ?",$EndDate);
        }
        $select->where('Status != ?', 3);
        $select->order('JoinID desc');
        $res = $model->getResult($select,$page,$pagesize);
        $model->getFiled($res['Results'],"AdminID" ,"admins" ,"Username","AdminName");
        $categories = (new Model_Category())->getIdToName();
        $wx = new Model_Weixin();
        foreach ($res["Results"] as &$r) {
            if(!empty($r["GroupTags"])){
                $ids = explode(",",$r["GroupTags"]);
                $label = [];
                foreach ($ids as $id) {
                    $label[] = $categories[$id]??$id;
                }
                $r['GroupTags'] = implode(',',$label);
            }
            $r["WeixinNum"] = $wx->findWeixinNum($r["WeixinTags"]);
        }
        $this->showJson(1, '', $res);
    }
    /**
     * 微信号加群 添加
     */
    public function qrJoinAddAction()
    {
        $JoinID = (int)$this->_getParam("JoinID",0);
        $WeixinTags = $this->_getParam('WeixinTags');
        $Channel = trim($this->_getParam('Channel',""));
        $GroupTags = trim($this->_getParam('GroupTags'));
        $StartDate = $this->_getParam('StartDate',date('Y-m-d'));
        $EndDate = $this->_getParam('EndDate',date('Y-m-d',strtotime('+1 day')));
        $JoinNum = $this->_getParam('JoinNum',1);
        $ExecTime = $this->_getParam('ExecTime',null);

        if (!$WeixinTags){
            $this->showJson(0,'请选择微信');
        }

        $validExecTime = Helper_Timer::getValidOptions(json_decode($ExecTime,true));
        if ($validExecTime === false) {
            $this->showJson(self::STATUS_FAIL, '执行时间非法');
        }
        list($nextRunTime, $nextRunType) = Helper_Timer::getNextRunTime($StartDate, $EndDate, json_decode($ExecTime,true));
        $now = date("Y-m-d H:i:s");
        $data = [
            'WeixinTags'  => $WeixinTags,
            'Channel'     => $Channel,
            'GroupTags'   => $GroupTags,
            'StartDate'   => $StartDate,
            'EndDate'     => $EndDate,
            'JoinNum'     => $JoinNum,
            'NextRunTime' => $nextRunTime,
            'NextRunType' => $nextRunType,
            'ExecTime'    => json_encode($validExecTime),
            'AdminID'     => $this->getLoginUserId(),
            'UpdateTime'  => $now
        ];
        if ($nextRunTime == '0000-00-00 00:00:00') {
            $data["Status"] = 2;
        }else{
            $data["Status"] = 1;
        }
        try{
            $model = new Model_Group_QrJoin();
            if($JoinID > 0){
                $model->update($data,"JoinID = {$JoinID}");
            }else{
                $data["CreateTime"] = $now;
                $model->insert($data);
            }
            $this->showJson(1,'保存成功');

        }catch (Exception $e){
            $this->showJson(0,'保存失败'.$e->getMessage());
        }
    }
    /**
     * 微信号加群 列表
     */
    public function qrJoinInfoAction()
    {
        $JoinID = (int)$this->_getParam("JoinID",0);
        $model = new Model_Group_QrJoin();
        $row = $model->fetchRow("JoinID = {$JoinID}")->toArray();
        $this->showJson(1,'',$row);
    }
    /**
     * 微信号加群 列表
     */
    public function qrJoinDeleteAction()
    {
        $this->showJson(0,"禁止删除");
        $JoinID = (int)$this->_getParam("JoinID",0);
        $model = new Model_Group_QrJoin();
        $model->delete("JoinID = {$JoinID}");
        $this->showJson(1,"删除成功");
    }
    /**
     * 微信号加群 列表
     */
    public function qrJoinStatusAction()
    {
        $JoinID = (int)$this->_getParam("JoinID",0);
        $Status = (int)$this->_getParam("Status",0);
        if(!in_array($Status,[1,2,3])){
            $this->showJson(1,"参数错误");
        }
        $model = new Model_Group_QrJoin();
        $model->update(["Status"=>$Status],"JoinID = {$JoinID}");
        $this->showJson(1,"更新成功");
    }
}