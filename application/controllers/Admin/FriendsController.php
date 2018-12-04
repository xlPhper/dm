<?php
/**
 * User: jakins
 * Date: 2018-09-27
 * 微信好友管理
 */
require_once APPLICATION_PATH . '/controllers/Admin/AdminBase.php';

class Admin_FriendsController extends AdminBase
{

    /**
     * 微信好友列表
     */
    public function listAction()
    {
        $page           = $this->getParam('Page', 1);
        $pagesize       = $this->getParam('Pagesize', 100);
        $weixin_tags    = $this->_getParam('WeixinTags',null); //微信号标签
        $friend_tags    = $this->_getParam('FriendTags',null); //微信好友标签
        $start_date     = $this->_getParam('StartAddDate',null); //添加起始时间
        $end_date       = $this->_getParam('EndAddDate',null); //添加结束时间
        $nick_name      = $this->_getParam('NickName',null);
        $weixin         = $this->_getParam('Weixin',null);
        $Alias          = $this->_getParam('Alias',null);
        $Export         = (int)$this->_getParam("Export", 0); //导出

        // Model
        $fModel         = new Model_Weixin_Friend();
        $weixin_model   = new Model_Weixin();
        $category_model = new Model_Category();


        $select = $fModel->fromSlaveDB()->select()->from($fModel->getTableName().' as f',['f.FriendID','f.WeixinID','f.Account as FriendAccount','f.Alias as FriendAlias','f.NickName as FriendNickName','f.CategoryIDs','f.Avatar as FriendAvatar','f.AddDate'])->setIntegrityCheck(false);
        $select->joinLeft('weixins as w','f.WeixinID = w.WeixinID',['Nickname','Weixin','Alias','AvatarUrl as Avatar']);
        // 微信标签筛选
        if ($weixin_tags){
            $weixins = $weixin_model->findWeixinCategory($weixin_tags);
            $weixin_ids = array();
            if ($weixins){
                foreach ($weixins as $v){
                    $weixin_ids[] = $v['WeixinID'];
                }
            }
            $select->where("f.WeixinID in (?)",empty($weixin_ids)?[0]:array_unique($weixin_ids));
        }

        // 微信好友标签筛选
        if ($friend_tags != '') {
            $tagIds = array_unique(explode(',', $friend_tags));
            $conditions = [];
            foreach ($tagIds as $tagId) {
                if ($tagId > 0) {
                    $conditions[] = 'find_in_set(' . $tagId . ', CategoryIDs)';
                }elseif($tagId == 0){
                    $conditions[] = "f.CategoryIDs = ''";
                }
            }
            if ($conditions) {
                $select->where(implode(' or ', $conditions));
            }
        }
        if($nick_name){
            $select->where('w.Nickname like ?',"%{$nick_name}%");
        }

        if($Alias){
            $select->where('w.Alias like ?',"%{$Alias}%");

        }

        if($weixin){
            $select->where('w.Weixin like ?',"%{$weixin}%");
        }

        // 头-添加时间
        if ($start_date){
            $select->where("f.AddDate >= ?",$start_date. ' 00:00:00');
        }
        // 尾-结束时间
        if ($end_date){
            $select->where("f.AddDate <= ?",$end_date. ' 23:59:59');
        }
        $select->order('AddDate DESC');

        $res = $fModel->getResult($select,$page,$pagesize);

        if ($res['Results']){

//            $weixinArr = [];
            foreach ($res['Results'] as &$friend){
                // 标签转换
                if ($friend['CategoryIDs']){
                    $tags = $category_model->findCategoryName($friend['CategoryIDs']);
                    $friend['CategoryIDsName'] = implode(',',$tags);
                }else{
                    $friend['CategoryIDsName'] = '';
                }

//                //查找微信号信息
//                if(!isset($weixinArr[$friend['WeixinID']])){
//                    $weixin = $weixin_model->findByID($friend['WeixinID']);
//                    if($weixin){
//                        $weixinArr[$friend['WeixinID']] = $weixin;
//                    }else{
//                        $weixinArr[$friend['WeixinID']] = [];
//                    }
//                }
//                $friend['Alias'] = empty($weixinArr[$friend['WeixinID']])?'':$weixinArr[$friend['WeixinID']]['Alias'];
//                $friend['Nickname'] = empty($weixinArr[$friend['WeixinID']])?'':$weixinArr[$friend['WeixinID']]['Nickname'];
//                $friend['Avatar'] = empty($weixinArr[$friend['WeixinID']])?'':$weixinArr[$friend['WeixinID']]['AvatarUrl'];
            }
        }

        if($Export) {
            if (!$weixin) {
                $this->showJson(1, '请选择微信号');
            }
            set_time_limit(0);
            ini_set('memory_limit','512M');

            $arr = [];
            foreach ($res['Results'] as &$value) {
                if ($value['Weixin'] == $weixin) {
                    $arr[] = $value;
                } else {
                    unset($value);
                    continue;
                }
            }

            $data = $arr;
            $excel = new DM_ExcelExport();
            $excel->setTemplateFile(APPLICATION_PATH . "/data/excel/friends.xls")
                ->setData($data)->export();

        }else{
            $this->showJson(1,'好友列表',$res);

        }
    }

    // 好友设置标签
    public function saveCategoryAction()
    {
        $fids = $this->_getParam('FriendIDs', null);
        $cids = $this->_getParam('CategoryIDs', null);
        $type = $this->_getParam('Type', 1);  // 1-覆盖 2-叠加 3-移除【默认覆盖】
        if (empty($fids)) {
            $this->showJson(0, 'FriendIDs不存在');
        }
        if (empty($cids)) {
            $this->showJson(0, '选择标签参数');
        }

        $fids_data = explode(',', $fids);
        $cids_data = explode(',', $cids);

        // Model
        $fmodel = new Model_Weixin_Friend();
        $fmodel->getAdapter()->beginTransaction();
        try {
            foreach ($fids_data as $friendID) {

                $info = $fmodel->find($friendID)->current();
                if (!$info) {
                    $this->showJson(0, 'FriendID[' . $friendID . '],中查询不到数据');
                }
                $update = '';
                // 原标签数组
                $original_tags = explode(',', $info->CategoryIDs);
                switch ($type) {
                    case 1:
                        foreach ($cids_data as $cate) {
                            $update .= ',' . $cate;
                        }
                        $update = trim($update, ',');
                        $update_data = ['CategoryIDs' => $update];
                        break;
                    case 2:
                        foreach ($cids_data as $cate) {
                            $in_grouptags = in_array($cate, $original_tags);
                            if (!$in_grouptags) {
                                $update .= ',' . $cate;
                            }
                        }
                        $update = trim($info->CategoryIDs . $update, ',');
                        $update_data = ['CategoryIDs' => $update];
                        break;
                    case 3:
                        foreach ($cids_data as $cate) {
                            $in_category = in_array($cate, $original_tags);
                            if ($in_category) {
                                $key = array_search($cate, $original_tags);
                                array_splice($original_tags, $key, 1);
                            }
                        }
                        $update = implode(',', $original_tags);
                        $update_data = ['CategoryIDs' => $update];
                        break;
                }
                if ($update_data) {
                    $fmodel->update($update_data, ['FriendID = ?' => $friendID]);
                }
            }
            $fmodel->getAdapter()->commit();
            $this->showJson(1, '修改成功');
        } catch (\Exception $e) {
            $fmodel->getAdapter()->rollBack();
            $this->showJson(self::STATUS_FAIL, '删除失败,err:'.$e->getMessage());
        }
    }

    /**
     * 编辑群发任务
     */
    public function editGroupsendAction(){
        $id = intval($this->_getParam('GroupSendID', 0));
        $data = [];
        $data['AdminID'] = $this->getLoginUserId();
        $data['WeixinTags'] = trim($this->_getParam('WeixinTags', '')); //微信标签串
        $data['FriendTags'] = trim($this->_getParam('FriendTags', '')); //好友标签串
        $data['Content'] = trim($this->_getParam('Content', '')); //消息内容
        $data['SendTime'] = $this->_getParam('SendTime', ''); //发送时间
        if($data['WeixinTags'] == '' && $data['FriendTags'] == ''){
            $this->showJson(0, '标签必须勾选一个');
        }
        if($data['Content'] == ''){
            $this->showJson(0, '消息内容不能为空');
        }
        if(empty($data['SendTime'])){
            $this->showJson(0, '请填写发送时间');
        }
        $content = json_decode($data['Content']);
        if (json_last_error() != JSON_ERROR_NONE || empty($content)) {
            $this->showJson(0, '消息内容格式有误');
        }
        $model = new Model_Weixin_FriendGroupsend();
        if ($id){
            $model->getAdapter()->beginTransaction();
            try{
                $select = $model->select()->where('GroupSendID = ?', $id)->forUpdate(true);
                $res = $model->fetchRow($select);
                if(!$res) $this->showJson(0, '找不到群发任务,ID：'.$id);
                if($res->Status != Model_Weixin_FriendGroupsend::STATUS_PENDING) $this->showJson(0, '此任务不在待执行状态,无法修改');
                $res->WeixinTags = $data['WeixinTags'];
                $res->FriendTags = $data['FriendTags'];
                $res->Content = $data['Content'];
                $res->SendTime = $data['SendTime'];
                $res->save();
                $model->getAdapter()->commit();
            }catch (\Exception $e) {
                $model->getAdapter()->rollBack();
                $this->showJson(self::STATUS_FAIL, '操作出错,err:'.$e->getMessage());
            }
        }else{
            $res = $model->insert($data);
            if(!$res){
                $this->showJson(0, '创建任务失败');
            }
        }
        $this->showJson(1, '操作成功');
    }

    /**
     * 群发任务详情信息
     */
    public function groupsendDetailAction(){
        $id = $this->_getParam('GroupSendID', 0);

        // Model
        $model = new Model_Weixin_FriendGroupsend();


        $res = $model->fromSlaveDB()->find($id)->current();
        if(!$res){
            $this->showJson(0, '未找到群发任务,ID：'.$id);
        }
        $res['Content'] = empty($res['Content'])?[]:json_decode($res['Content'], true);

        $this->showJson(1,'详情', $res->toArray());
    }

    /**
     * 删除群发任务
     */
    public function delGroupsendAction(){
        $id = intval($this->_getParam('GroupSendID', 0));
        if($id){
            $model = new Model_Weixin_FriendGroupsend();
            $model->update(['Status' => Model_Weixin_FriendGroupsend::STATUS_DELETED], ['GroupSendID = ?' => $id]);
        }
        $this->showJson(1, '删除成功');
    }

    /**
     * 群发任务列表
     */
    public function groupsendListAction(){
        $page = $this->getParam('Page', 1);
        $pagesize = $this->getParam('Pagesize', 100);
        $adminID = intval($this->getParam('AdminID', 0)); //创建人ID
        $friend_tags = $this->_getParam('FriendTags', ''); //微信好友标签
        $status = intval($this->getParam('Status', 0)); //状态
        $start_date = $this->_getParam('StartSendTime',null); //添加起始时间
        $end_date = $this->_getParam('EndSendTime',null); //添加结束时间


        // Model
        $model = new Model_Weixin_FriendGroupsend();
        $weixin_model = new Model_Weixin();
        $category_model = new Model_Category();
        $friendModel = new Model_Weixin_Friend();


        $select = $model->fromSlaveDB()->select()->from($model->getTableName(),['GroupSendID','AdminID','WeixinTags','FriendTags','SendTime','Status','DelWeixinIDs','TaskIDs','CreateTime']);

        // 微信好友标签筛选
        if ($friend_tags != '') {
            $tagIds = array_unique(explode(',', $friend_tags));
            $conditions = [];
            foreach ($tagIds as $tagId) {
                if ($tagId > 0) {
                    $conditions[] = 'find_in_set(' . $tagId . ', FriendTags)';
                }elseif($tagId == 0){
                    $conditions[] = "FriendTags = ''";
                }
            }
            if ($conditions) {
                $select->where(implode(' or ', $conditions));
            }
        }
        //创建管理员筛选
        if($adminID){
            $select->where('AdminID = ?', $adminID);
        }
        //状态
        if($status){
            $select->where('Status = ?', $status);
        }
        // 头-添加时间
        if ($start_date){
            $select->where("SendTime >= ?",$start_date. ' 00:00:00');
        }
        // 尾-结束时间
        if ($end_date){
            $select->where("SendTime <= ?",$end_date. ' 23:59:59');
        }
        $select->where('Status != ?', Model_Weixin_FriendGroupsend::STATUS_DELETED);
        $select->order('GroupSendID DESC');

        $res = $model->getResult($select,$page,$pagesize);

        if ($res['Results']){

            foreach ($res['Results'] as &$row){
                // 标签转换
                if ($row['FriendTags']){
                    $tags = $category_model->findCategoryName($row['FriendTags']);
                    $row['FriendTagsName'] = implode(',',$tags);
                }else{
                    $row['FriendTagsName'] = '';
                }

                if($row['WeixinTags'] != '' || $row['FriendTags'] != ''){
                    $weixin_ids = array();
                    if($row['WeixinTags'] != ''){
                        //根据微信号标签查出微信号
                        $weixins = $weixin_model->findWeixinCategory($row['WeixinTags']);
                        if ($weixins) {
                            foreach ($weixins as $v){
                                $weixin_ids[] = $v['WeixinID'];
                            }
                        }
                    }
                    $num = $friendModel->getNumsByGroupSendInfo(array_unique($weixin_ids), explode(',', $row['FriendTags']), explode(',', $row['DelWeixinIDs']));
                    $row['WeixinNum'] = $num['WeixinNum'];
                    $row['FriendSendNum'] = $num['FriendNum'];
                }else {
                    $row['WeixinNum'] = 0;
                    $row['FriendSendNum'] = 0;
                }
            }
            $model->getFiled($res['Results'], "AdminID","admins" ,"Username","AdminName" );
        }

        $this->showJson(1,'群发任务列表',$res);
    }

    /**
     * 群发任务关联微信号列表
     */
    public function groupsendWeixinListAction(){
        $page = $this->getParam('Page', 1);
        $pagesize = $this->getParam('Pagesize', 100);
        $id = intval($this->getParam('GroupSendID', 0)); //群发任务ID
        $group_model = new Model_Weixin_FriendGroupsend();
        $group = $group_model->fromSlaveDB()->find($id)->current();
        if(!$group || $group->Status == Model_Weixin_FriendGroupsend::STATUS_DELETED){
            $this->showJson(1,'群发任务关联微信号列表',array(
                'Page'  =>  $page,
                'Pagesize'  =>  $pagesize,
                'TotalCount' => 0,
                'TotalPage' => 0,
                'Results'  => []
            ));
        }
        $friend_model = new Model_Weixin_Friend();
        $weixin_model = new Model_Weixin();
        $select = $friend_model->fromSlaveDB()->select()->from($friend_model->getTableName(),['WeixinID','count(Account) as FriendSendNum']);
        if($group->WeixinTags != ''){
            $weixins = $weixin_model->findWeixinCategory($group->WeixinTags);
            if ($weixins) {
                foreach ($weixins as $v){
                    $weixin_ids[] = $v['WeixinID'];
                }
            }
        }
        if(!empty($weixin_ids)){
            $select->where('WeixinID IN (?)', array_unique($weixin_ids));
        }
        if($group->DelWeixinIDs != ''){
            $select->where('WeixinID not in (?)', array_unique(explode(',', $group->DelWeixinIDs)));
        }
        if($group->FriendTags != ''){
            $tagIds = array_unique(explode(',', $group->FriendTags));
            $conditions = [];
            foreach ($tagIds as $tagId) {
                if ($tagId > 0) {
                    $conditions[] = 'find_in_set(' . $tagId . ', CategoryIDs)';
                }
            }
            if ($conditions) {
                $select->where(implode(' or ', $conditions));
            }
        }
        $select->group('WeixinID');
        $res = $friend_model->getResult($select,$page,$pagesize);
        if ($res['Results']){
            $category_model = new Model_Category();
            $tagsName = '';
            if($group->WeixinTags != ''){
                $tags = $category_model->findCategoryName($group->WeixinTags);
                $tagsName = implode(',',$tags);
            }
            $weixinArr = [];
            foreach ($res['Results'] as &$row){
                $row['TagsName'] = $tagsName;
                //查找微信号信息
                if(!isset($weixinArr[$row['WeixinID']])){
                    $weixin = $weixin_model->findByID($row['WeixinID']);
                    if($weixin){
                        $weixinArr[$row['WeixinID']] = $weixin;
                    }else{
                        $weixinArr[$row['WeixinID']] = [];
                    }
                }
                $row['Alias'] = empty($weixinArr[$row['WeixinID']])?'':$weixinArr[$row['WeixinID']]['Alias'];
                $row['Nickname'] = empty($weixinArr[$row['WeixinID']])?'':$weixinArr[$row['WeixinID']]['Nickname'];
                $row['Avatar'] = empty($weixinArr[$row['WeixinID']])?'':$weixinArr[$row['WeixinID']]['AvatarUrl'];
                $row['Weixin'] = empty($weixinArr[$row['WeixinID']])?'':$weixinArr[$row['WeixinID']]['Weixin'];
                $row['AdminID'] = empty($weixinArr[$row['WeixinID']])?'':$weixinArr[$row['WeixinID']]['AdminID'];
            }
            $friend_model->getFiled($res['Results'], "AdminID","admins" ,"Username","AdminName" );
        }

        $this->showJson(1,'群发任务关联微信号列表',$res);
    }

    /**
     * 删除某次群发某个微信号
     */
    public function delSendWeixinAction(){
        $id = intval($this->_getParam('GroupSendID', 0));
        $wxIDs = trim($this->_getParam('WeixinIDs', ''));
        if($wxIDs == ''){
            $this->showJson(0, '待删除微信ID串不能为空');
        }
        $model = new Model_Weixin_FriendGroupsend();
        $res = $model->find($id)->current();
        if(!$res){
            $this->showJson(0, '此群发任务不存在,GroupSendID：'.$id);
        }
        if($res->Status != Model_Weixin_FriendGroupsend::STATUS_PENDING){
            $this->showJson(0, '此群发任务不在待执行状态,无法修改');
        }
        $delWeixinIDs = $res->DelWeixinIDs?explode(',', $res->DelWeixinIDs):[];
        $wxIDArr = explode(',', $wxIDs);
        $res->DelWeixinIDs = implode(',', array_unique(array_merge($wxIDArr, $delWeixinIDs)));
        $res->save();
        $this->showJson(1, '删除成功');
    }

}