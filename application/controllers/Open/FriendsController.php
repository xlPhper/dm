<?php
/**
 * User: jakins
 * Date: 2018-09-27
 * 微信好友管理
 */
require_once APPLICATION_PATH . '/controllers/Open/OpenBase.php';

class Open_FriendsController extends OpenBase
{

    /**
     * 客户列表（获取当前登录管理员下的个人号客户列表）
     */
    public function listAction()
    {
        $page = $this->getParam('Page', 1);
        $pagesize = $this->getParam('Pagesize', 100);
        $nameType = intval($this->getParam('NameType', 1)); //名称类型,1微信昵称,2客户昵称
        $name = trim($this->getParam('Name', '')); //名称
        $weixin = trim($this->_getParam('Weixin', '')); //个人号微信
        $friend_tags = $this->_getParam('FriendTags',null); //微信好友标签
        $chatRates = $this->_getParam('ChatRates', ''); //互动频率,多个逗号分隔
        $start_date = $this->_getParam('StartAddDate',null); //添加起始时间
        $end_date = $this->_getParam('EndAddDate',null); //添加结束时间
        $orderNumStart = intval($this->_getParam('OrderNumStart', 0)); //订单数起始
        $orderNumEnd = intval($this->_getParam('OrderNumEnd', 0)); //订单数截止
        $order_field = strtolower($this->_getParam('Order_Field', 'desc')); //排序方式
        $sort_field = $this->_getParam('Sort_Field', 'AddDate'); //排序字段
        $order_field = $order_field != 'asc'? 'desc':'asc';


        // Model
        $fModel = new Model_Weixin_Friend();
        $category_model = new Model_Category();


        $select = $fModel->fromSlaveDB()->select()->setIntegrityCheck(false)->from($fModel->getTableName(). ' as wf',['FriendID','WeixinID','Account as FriendAccount','Alias as FriendAlias','NickName as FriendNickName','CategoryIDs','Avatar as FriendAvatar','AddDate','Customer','ChatRate']);

        $select->joinLeft('weixins as wv', 'wv.WeixinID = wf.WeixinID', ['Alias', 'Nickname', 'AvatarUrl as Avatar'])
        ->joinLeft('orders as o', 'o.Seller=wv.Weixin and (o.Buyer=wf.Account or o.Buyer=wf.Alias)', ['count(OrderID) as OrderNum'])
        ->where('wf.IsDeleted = 0')
        ->where('wf.WeixinID IN (?)', empty($this->adminWxIds)?[0]:$this->adminWxIds);
        // 微信好友标签筛选
        if ($friend_tags != '') {
            $tagIds = array_unique(explode(',', $friend_tags));
            $conditions = [];
            foreach ($tagIds as $tagId) {
                if ($tagId > 0) {
                    $conditions[] = 'find_in_set(' . $tagId . ', wf.CategoryIDs)';
                }elseif($tagId == 0){
                    $conditions[] = "wf.CategoryIDs = ''";
                }
            }
            if ($conditions) {
                $select->where(implode(' or ', $conditions));
            }
        }
        // 名称
        if ($name !== ''){
            switch ($nameType){
                case '1': //微信昵称
                    $select->where('wv.NickName like ?', '%'.$name.'%');
                    break;
                case '2': //客户昵称
                    $select->where('wf.NickName like ?', '%'.$name.'%');
                    break;
                default:
                    break;
            }
        }
        // 头-添加时间
        if ($start_date){
            $select->where("wf.AddDate >= ?",$start_date. ' 00:00:00');
        }
        // 尾-结束时间
        if ($end_date){
            $select->where("wf.AddDate <= ?",$end_date. ' 23:59:59');
        }
        // 订单数
        if ($orderNumStart){
            $select->having('OrderNum >= ?', $orderNumStart);
        }
        if ($orderNumEnd){
            $select->having('OrderNum <= ?', $orderNumEnd);
        }
        if ($weixin !== ''){
            $select->where('wv.Weixin = ?', $weixin);
        }
        if ($chatRates !== ''){
            //如果勾选了所有频率则此where无效
            if(count(array_unique(explode(',', $chatRates))) != count(Model_Weixin_Friend::$_chatRateData)){
                $select->where('wf.ChatRate IN (?)', array_unique(explode(',', $chatRates)));
            }
        }
        $select->group('wf.FriendID');
        $select->order("$sort_field $order_field");
        $res = $fModel->getResult($select,$page,$pagesize);

        if ($res['Results']){
            foreach ($res['Results'] as &$friend){
                // 标签转换
                if ($friend['CategoryIDs']){
                    $tags = $category_model->findCategoryName($friend['CategoryIDs']);
                    $friend['CategoryIDsName'] = implode(',',$tags);
                }else{
                    $friend['CategoryIDsName'] = '';
                }
                $friend['ChatRateName'] = Model_Weixin_Friend::$_chatRateData[$friend['ChatRate']]['Name'];
            }
        }

        $this->showJson(1,'客户列表',$res);
    }

    public function chatratesAction(){
        try{
            $weixin = trim($this->_getParam('Weixin', '')); //个人号微信

            // Model
            $fModel = new Model_Weixin_Friend();
            $select = $fModel->fromSlaveDB()->select()->from($fModel->getTableName().' as wf', ['count(*) as Num', 'ChatRate'])
                    ->where('wf.WeixinID IN (?)', empty($this->adminWxIds)?[0]:$this->adminWxIds);
            if($weixin !== ''){
                $wx = Model_weixin::getInstance()->getInfoByWeixin($weixin);
                if($wx){
                    $select->where('wf.WeixinID = ?', $wx['WeixinID']);
                }else{
                    $select->where('wf.WeixinID = ?', 0);
                }
            }
            $select->group('wf.ChatRate')->order('wf.ChatRate asc');
            $res = $select->query()->fetchAll();
            $data = Model_Weixin_Friend::$_chatRateData;
            foreach ($data as &$row){
                foreach ($res as $chatrate){
                    if($row['ChatRate'] == $chatrate['ChatRate']){
                        $row['FriendNum'] = $chatrate['Num'];
                    }
                }
            }
            $this->showJson(1,'互动频率数据',$data);
        } catch (Exception $e) {
            $this->showJson(self::STATUS_FAIL, '抛出异常,err:'.$e->getMessage());
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

        $fids_data = array_unique(explode(',', $fids));
        $cids_data = array_unique(explode(',', $cids));
        if($type == 1 && count($cids_data) > 10){
            $this->showJson(0, '标签最多可设置10个');
        }

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
                        if(count(array_unique(explode(',', $update))) > 10){
                            $this->showJson(0, 'FriendID[' . $friendID . ']Weixin['.$info->Account.'],标签最多设置10个');
                        }
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
     * 批量发送消息
     */
    public function sendMsgAction(){
        try{
            $fids = trim($this->_getParam('FriendIDs', ''));
            $fid_data = array_unique(array_filter(explode(',', $fids)));
            $content = trim($this->_getParam('Content', '')); //消息内容,json格式
            $sendTime = trim($this->_getParam('SendTime', '')); //1为立即发送,否则填写日期时间
            if(empty($fid_data)){
                $this->showJson(0, '请勾选要批量的好友');
            }
            $content = json_decode($content, true);
            if (json_last_error() != JSON_ERROR_NONE || empty($content)) {
                $this->showJson(0, '消息内容格式有误');
            }
            if($sendTime === '' || ($sendTime != 1 && !strtotime($sendTime))){
                $this->showJson(0, '发送时间有误,SendTime:'.$sendTime);
            }
            $friendModel = Model_Weixin_Friend::getInstance();
            $friends = $friendModel->fromSlaveDB()->select()->where('FriendID IN (?)', $fid_data)->query()->fetchAll();
            foreach($friends as $wx){
                if($wx['Account'] == ''){
                    continue;
                }
                $task_config = [
                    'Weixins' => [$wx['Account']],
                    'Content' => $content,
                ];
                if($sendTime == 1){
                    //当前时间
                    $runTime = date('Y-m-d H:i:s');
                }else{
                    //1个小时内随机时间
                    $hourStart = date('Y-m-d H:00:00', strtotime($sendTime));
                    $hourEnd = date('Y-m-d H:59:59', strtotime($sendTime));
                    $runTime = Helper_Until::getRandTime($hourStart, $hourEnd);
                }
                if($runTime == '0000-00-00 00:00:00'){
                    continue;
                }
                Model_Task::addCommonTask(TASK_CODE_FRIEND_GROUP_SEND, $wx['WeixinID'], json_encode($task_config), $this->getLoginUserId(), $runTime);
            }
            $this->showJson(self::STATUS_OK, '发送成功');
        } catch (Exception $e) {
            $this->showJson(self::STATUS_FAIL, '发送失败,err:'.$e->getMessage());
        }
    }

    /**
     * 好友申请列表（获取当前登录管理员下的个人号好友申请列表）
     */
    public function applyListAction(){
        try{
            $page = $this->getParam('Page', 1);
            $pagesize = $this->getParam('Pagesize', 100);
            $weixin = trim($this->_getParam('Weixin', '')); //个人号微信

            // Model
            $fModel = new Model_Weixin_FriendApply();

            $select = $fModel->fromSlaveDB()->select()->setIntegrityCheck(false)->from($fModel->getTableName(). ' as fa', ['FriendApplyID','WeixinID','Talker','DisplayName','ContentVerifyContent','State','UpdateTime', 'Avatar'])
                ->where('fa.WeixinID IN (?)', empty($this->adminWxIds)?[0]:$this->adminWxIds)
                ->where('fa.State = ?', Model_Weixin_FriendApply::STATE_UNADD)->where('fa.IsDeleted = ?', Model_Weixin_FriendApply::IS_NOT_DELETED);
            if($weixin !== ''){
                $wx = Model_weixin::getInstance()->getInfoByWeixin($weixin);
                if($wx){
                    $select->where('fa.WeixinID = ?', $wx['WeixinID']);
                }else{
                    $select->where('fa.WeixinID = ?', 0);
                }
            }
            $select->order("LastModifiedTime Desc");
            $res = $fModel->getResult($select,$page,$pagesize);

            $this->showJson(1,'好友申请列表',$res);
        } catch (Exception $e) {
            $this->showJson(self::STATUS_FAIL, '抛出异常,err:'.$e->getMessage());
        }
    }

    /**
     * 同意申请
     */
    public function agreeApplyAction(){
        try{
            $applyIDs = array_unique(array_filter(explode(',', trim($this->_getParam('FriendApplyIDs', '')))));
            if(empty($applyIDs)){
                $this->showJson(0,'请勾选好友申请');
            }
            $model = Model_Weixin_FriendApply::getInstance();
            $friends = $model->fromMasterDB()->select()->where('FriendApplyID IN (?)', $applyIDs)->where('State = ?', Model_Weixin_FriendApply::STATE_UNADD)->query()->fetchAll();
            $sendWeixin = []; //待发送的微信号以及好友信息
            foreach($friends as $wx){
                if($wx['Talker'] == ''){
                    continue;
                }
                $sendWeixin[$wx['WeixinID']][] = $wx['Talker'];
            }
            foreach ($sendWeixin as $key => $val){
                $task_config = [
                    'Talkers' => $val,
                    'OpType' => Model_Weixin_FriendApply::APPLY_DEAL_TYPE_AGREE,
                ];
                Model_Task::addCommonTask(TASK_CODE_FRIEND_APPLY_DEAL, $key, json_encode($task_config), $this->getLoginUserId());
            }
            if(!empty($friends)){
                //更新申请表状态
                $model->fromMasterDB()->update(['IsDeleted' => Model_Weixin_FriendApply::IS_AGREE_DELETED], ['FriendApplyID IN (?)' => $applyIDs]);
            }
            $this->showJson(self::STATUS_OK, '操作成功');
        } catch (Exception $e) {
            $this->showJson(self::STATUS_FAIL, '抛出异常,err:'.$e->getMessage());
        }
    }

    /**
     * 删除申请
     */
    public function delApplyAction(){
        try{
            $applyIDs = array_unique(array_filter(explode(',', trim($this->_getParam('FriendApplyIDs', '')))));
            if(empty($applyIDs)){
                $this->showJson(0,'请勾选好友申请');
            }
            $model = Model_Weixin_FriendApply::getInstance();
            $friends = $model->fromMasterDB()->select()->where('FriendApplyID IN (?)', $applyIDs)->where('IsDeleted = ?', Model_Weixin_FriendApply::IS_NOT_DELETED)->query()->fetchAll();
            $sendWeixin = []; //待发送的微信号以及好友信息
            foreach($friends as $wx){
                if($wx['Talker'] == ''){
                    continue;
                }
                $sendWeixin[$wx['WeixinID']][] = $wx['Talker'];
            }
            foreach ($sendWeixin as $key => $val){
                $task_config = [
                    'Talkers' => $val,
                    'OpType' => Model_Weixin_FriendApply::APPLY_DEAL_TYPE_DELETE,
                ];
                Model_Task::addCommonTask(TASK_CODE_FRIEND_APPLY_DEAL, $key, json_encode($task_config), $this->getLoginUserId());
            }
            if(!empty($friends)){
                //更新申请表状态
                $model->fromMasterDB()->update(['IsDeleted' => Model_Weixin_FriendApply::IS_DELETED], ['FriendApplyID IN (?)' => $applyIDs]);
            }
            $this->showJson(self::STATUS_OK, '操作成功');
        } catch (Exception $e) {
            $this->showJson(self::STATUS_FAIL, '抛出异常,err:'.$e->getMessage());
        }
    }

    /**
     * 获取新申请数
     */
    public function newApplyAction(){
        try{
            $weixin = trim($this->_getParam('Weixin', '')); //个人号微信
            $select = Model_Weixin_FriendApply::getInstance()->fromSlaveDB()->select()->setIntegrityCheck(false)->from('weixin_friend_apply as fa', ['FriendApplyID'])
                ->where('fa.WeixinID IN (?)', empty($this->adminWxIds)?[0]:$this->adminWxIds)
                ->where('fa.State = ?', Model_Weixin_FriendApply::STATE_UNADD)->where('fa.IsNew = ?', Model_Weixin_FriendApply::IS_NEW)->where('fa.IsDeleted = ?', Model_Weixin_FriendApply::IS_NOT_DELETED);
            if($weixin !== ''){
                $wx = Model_weixin::getInstance()->getInfoByWeixin($weixin);
                if($wx){
                    $select->where('fa.WeixinID = ?', $wx['WeixinID']);
                }else{
                    $select->where('fa.WeixinID = ?', 0);
                }
            }
            $news = $select->query()->rowCount();
            $this->showJson(self::STATUS_OK, '新申请数量', $news);
        }catch(Exception $e){
            $this->showJson(0, '抛出异常:'.$e->getMessage());
        }
    }

    /**
     * 清除新申请状态
     */
    public function clearNewApplyAction(){
        try{
            $weixin = trim($this->_getParam('Weixin', '')); //个人号微信
            $where = [
                'WeixinID IN (?)' => empty($this->adminWxIds)?[0]:$this->adminWxIds,
                'IsNew = ?' => Model_Weixin_FriendApply::IS_NEW,
                'State = ?' => Model_Weixin_FriendApply::STATE_UNADD,
                'IsDeleted = ?' => Model_Weixin_FriendApply::IS_NOT_DELETED
            ];
            if($weixin !== ''){
                $wx = Model_weixin::getInstance()->getInfoByWeixin($weixin);
                if($wx){
                    $where['WeixinID = ?'] = $wx['WeixinID'];
                }else{
                    $where['WeixinID = ?'] = 0;
                }
            }
            Model_Weixin_FriendApply::getInstance()->fromMasterDB()->update(['IsNew' => Model_Weixin_FriendApply::IS_NOT_NEW], $where);
            $this->showJson(self::STATUS_OK, '操作成功');
        }catch(Exception $e){
            $this->showJson(0, '抛出异常:'.$e->getMessage());
        }
    }


    /**
     * 客户详情
     */
    public function friendInfoAction()
    {
        $friendId = $this->_getParam('FriendID',null);

        // Model
        $friendModel = Model_Weixin_Friend::getInstance();
        $categoryModel = Model_Category::getInstance();
        $adminModel = Model_Role_Admin::getInstance();

        $res = $friendModel->getCustomerInfo($friendId);

        if ($res){
            $friendCategoryIds = [];

            if (isset($res['CategoryIds']) && !empty($res['CategoryIds'])){
                $categoryIds = explode(',',$res['CategoryIds']);
                foreach ($categoryIds as $id){
                    $friendCategoryIds[] = $id;

                }
            }

            $adminId = $this->getLoginUserId();

            $adminInfo = $adminModel->getInfoByID($adminId);

            if ($adminInfo == false){
                $this->showJson(self::STATUS_FAIL, '管理员不存在');
            }

            $departmentID = [];

            if ($adminInfo['IsSuper'] == 'Y'){
                $departmentID = $adminModel->getDepartmentIDs($adminInfo['CompanyId']);
            }else{
                $departmentID[] = $adminInfo['DepartmentID'];
            }

            $categorys = $categoryModel->findByDepartmentID('',$adminInfo['CompanyId'],$departmentID,CATEGORY_TYPE_WEIXINFRIEND,'',PLATFORM_OPEN);

            $categoryData = [];

            foreach ($categorys as $g){
                if (in_array($g['CategoryID'],$friendCategoryIds)){
                    $categoryData[] = [
                        'CategoryID'=>$g['CategoryID'],
                        'Name'=>$g['Name'],
                        'State'=>1
                    ];
                }else{
                    $categoryData[] = [
                        'CategoryID'=>$g['CategoryID'],
                        'Name'=>$g['Name'],
                        'State'=>0
                    ];
                }
            }


            $res['CategoryList'] = $categoryData;
            $res['AddDate'] = date('Y-m-d',strtotime($res['AddDate']));
            $res['Nickname'] = '(' . mb_substr($res['SerialNum'], -4) . ')' . $res['Nickname'];
        }

        $this->showJson(self::STATUS_OK,'客户详情',$res);

    }

    /**
     * 获取客户备注列表
     */
    public function noteListAction()
    {
        $type = $this->_getParam('Type',1);
        $friendId = $this->_getParam('FriendID',null);

        // Model
        $noteModel = Model_Weixin_Notes::getInstance();

        switch ($type){
            case 1:
                if (empty($friendId)){
                    $this->showJson(self::STATUS_FAIL,'客户ID不能为空');
                }
                $notes = $noteModel->findByFriendID($friendId);

                break;
            case 2:
                $friendModel = Model_Weixin_Friend::getInstance();

                $weixinIds = $this->adminWxIds;

                if ($weixinIds){
                    $friendIds = $friendModel->getWeixnFriendIDs($weixinIds);
                    $date = date('Y-m-d H:i').':00';

                    $notes = $noteModel->getFriendNotes($friendIds,$date);
                }else{
                    $notes = [];
                }

                break;
        }

        if ($notes){
            $this->showJson(self::STATUS_OK,'客户备注',$notes);
        }else{
            $this->showJson(self::STATUS_OK,'客户备注',[]);
        }


    }

    /**
     * 修改客户昵称
     */
    public function saveCustomerAction()
    {
        $FriendID = $this->_getParam('FriendID',null);
        $Customer = $this->_getParam('Customer',null);

        if (empty($FriendID)){
            $this->showJson(self::STATUS_FAIL,'客户ID不能为空');
        }

        $friendModel = Model_Weixin_Friend::getInstance();

        $up = $friendModel->update(['Customer'=>$Customer],['FriendID = ?'=>$FriendID]);

        if ($up){
            $this->showJson(self::STATUS_OK,'操作成功');
        }else{
            $this->showJson(self::STATUS_FAIL,'操作失败');
        }

    }


    /**
     *  [添加/编辑]客户备注
     */
    public function saveNoteAction()
    {
        $noteId = (int)$this->_getParam('NoteID',0);
        $friendId = (int)$this->_getParam('FriendID',0);
        $content = (string)$this->_getParam('Content','');
        $status = (int)$this->_getParam('Status',1);
        $remindTime = (string)$this->_getParam('RemindTime','');

        // Model
        $noteModel = Model_Weixin_Notes::getInstance();

        try{
            if (!empty($noteId)){
                if (!empty($content) && isset($content)){
                    $data['Content'] = $content;
                }

                if (!empty($status) && isset($status)){
                    $data['Status'] = $status;
                }

                if (!empty($remindTime) && isset($remindTime)){
                    $data['RemindTime'] = $remindTime;
                }

                if (empty($data)){
                    $this->showJson(self::STATUS_FAIL, '请进行有效的编辑');
                }
                $data['UpdateTime'] = date('Y-m-d H:i:s');

                $noteModel->update($data,['NoteID = ?'=>$noteId]);
                $id = $friendId;
            }else{
                if (empty($friendId)){
                    $this->showJson(self::STATUS_FAIL, '传客户ID');
                }

                if (empty($content)){
                    $this->showJson(self::STATUS_FAIL, '请进行有效的编辑');
                }

                $note = $noteModel->getNoteNum($friendId);

                if (!empty($note) && $note['Num'] >=10){
                    $this->showJson(self::STATUS_FAIL, '客户备注已经超过10条,清理一些完成备注再添加');
                }

                $data = [
                    'FriendID'=>$friendId,
                    'CreateTime'=>date('Y-m-d H:i:s'),
                    'Content'=>$content,
                ];

                if (!empty($remindTime) && isset($remindTime)){
                    $data['RemindTime'] = $remindTime;
                }

                $id = $noteModel->fromMasterDB()->insert($data);
            }
            $this->showJson(self::STATUS_OK,'操作成功',$id);

        }catch (Exception $e){
            $this->showJson(self::STATUS_FAIL,'抛出异常'.$e->getMessage());
        }
    }

    /**
     * 删除备注
     */
    public function delNoteAction()
    {
        $noteId = $this->_getParam('NoteID',null);

        if (empty($noteId)){
            $this->showJson(self::STATUS_FAIL,'ID非法');
        }

        $noteModel = Model_Weixin_Notes::getInstance();

        $del = $noteModel->delete(['NoteID = ?'=>$noteId]);

        if ($del){
            $this->showJson(self::STATUS_OK,'删除成功');
        }else{
            $this->showJson(self::STATUS_FAIL,'删除失败');
        }
    }

}