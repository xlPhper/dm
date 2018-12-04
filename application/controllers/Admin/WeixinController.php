<?php
require_once APPLICATION_PATH . '/controllers/Admin/AdminBase.php';

class Admin_WeixinController extends AdminBase
{

    /**
     * 微信来源信息
     */
    public function channelAction()
    {
        $phone_model = new Model_Weixin();
        $channel = $phone_model->findWeixinChannel();
        $this->showJson(1, '', $channel);
    }

    // 微信号信息列表
    public function listAction()
    {
        $page = $this->getParam('Page', 1);
        $pagesize = $this->getParam('Pagesize', 100);
        $category_id = $this->getParam('CategoryID', null);
        $name = $this->getParam('Name', null);
        $channel = $this->getParam('Channel', null);
        $group_num_min = $this->getParam('GroupNumMin', null);
        $group_num_max = $this->getParam('GroupNumMax', null);
        $friend_num_min = $this->getParam('FriendNumMin', null);
        $friend_num_max = $this->getParam('FriendNumMax', null);
        $start_date = $this->getParam('StartDate', null);
        $end_date = $this->getParam('EndDate', null);
        $address = $this->getParam('Address', null);
        $online = $this->getParam('Online', null);
        $admin_id = $this->getParam('AdminID', null);
        $serial_num = $this->getParam('SerialNum', null);
        $inputWeixinIds = trim($this->_getParam('WeixinIDs', ''));
        if ($inputWeixinIds) {
            $pagesize = 9999;
        }

        $weixin_model = new Model_Weixin();
        $category_model = new Model_Category();
        $device_model = new Model_Device();

        // 在线微信
        $online_weixinids = $device_model->findOnlineWeixin();

        $select = $weixin_model->fromSlaveDB()->select()->from($weixin_model->getTableName().' as w')->setIntegrityCheck(false);
        $select->joinLeft('devices as d','w.DeviceID = d.DeviceID','d.SerialNum');

        if ($online){
            $online_weixinids_string = implode(',',$online_weixinids);
            if ($online == 1){
                $select->where("w.WeixinID IN ({$online_weixinids_string})");
            }elseif ($online == 2){
                $select->where("w.WeixinID NOT IN ({$online_weixinids_string})");
            }
        }

        // 自定义编码搜索
        if($serial_num){
            $serial_num = str_replace('，',',',$serial_num);
            $serial_num = explode(',',$serial_num);

            $serial_num_where = '';
            foreach ($serial_num as $s){

                $serial_num_where .= "d.SerialNum like '%{$s}%' or ";

            }

            $serial_num_where = rtrim($serial_num_where,'or ');
            $select->where($serial_num_where);

        }
        if (!empty($category_id)) {
            $where_msg ='';
            $category_data = explode(',',$category_id);
            foreach($category_data as $w){
                $where_msg .= "FIND_IN_SET(".$w.",w.CategoryIds) OR ";
            }
            $where_msg = rtrim($where_msg,'OR ');
            $select->where($where_msg);
        }
        if (!empty($name)) {
            $select->where("w.Alias like ?  OR w.Weixin like ?  OR w.Nickname like ? OR d.SerialNum like ?", ["%".$name."%"]);
        }
        if (!empty($channel)) {
            $select->where('w.Channel = ?', $channel);
        }
        if (!empty($group_num_min)) {
            $select->where('w.GroupNum >= ?', $group_num_min);
        }
        if (!empty($group_num_max)) {
            $select->where('w.GroupNum <= ?', $group_num_max);
        }
        if (!empty($friend_num_min)) {
            $select->where('w.FriendNum >= ?', $friend_num_min);
        }
        if (!empty($friend_num_max)) {
            $select->where('w.FriendNum <= ?', $friend_num_max);
        }
        if (!empty($start_date)) {
            $select->where('w.AddDate >= ?', $start_date);
        }
        if (!empty($end_date)) {
            $select->where('w.AddDate <= ?', $end_date);
        }
        if (!empty($address)) {
            $select->where('w.Address like ?', $address);
        }
        if ($inputWeixinIds) {
            $select->where('w.WeixinID IN (?)', explode(',',$inputWeixinIds));
        }
        if ($admin_id) {
            $select->where('w.AdminID = ?', $admin_id);
        }
        $select->order('d.SerialNum Desc');
//        echo $select->__toString();exit();
        $res = $weixin_model->getResult($select, $page, $pagesize);
        $categories = $category_model->getIdToName();
        foreach ($res['Results'] as &$weixin) {
            // 判断是否在线
            if (in_array($weixin['WeixinID'],$online_weixinids)){
                $weixin['Online'] = 1;
            }else{
                $weixin['Online'] = 0;
            }
//            // 添加任务状态
//            $task = $task_model->findByWeiID($weixin['WeixinID']);
//            if ($task) {
//                $msg = '';
//                $task_codo = $taskCodes[$task['TaskCode']];
//
//                if ($task['Status'] >0 && $task['Status']<4){
//                    $msg ='正在执行';
//                }elseif ($task['Status'] == 4){
//                    $msg ='完成';
//                }elseif ($task['Status'] == 5){
//                    $msg ='非正常完成';
//                }elseif ($task['Status'] == 6){
//                    $msg ='暂停';
//                }elseif ($task['Status'] == 9){
//                    $msg ='失败';
//                }elseif ($task['Status'] == 44){
//                    $msg ='被删除';
//                }
//                $info['Task'] = $task_codo.$msg;
//            }else {
//                $info['Task'] = '';
//            }
            // 标签ID转标签名
            if ($weixin['CategoryIds']){
                $arr = explode(",", $weixin['CategoryIds']);
                $label = [];
                foreach ($arr as $id) {
                    $label[] = $categories[$id]??$id;
                }
                $weixin['CategoryIds']= implode(',',$label);
            }
            $weixin['Channel'] = $categories[$weixin['Channel']]??"";
        }
        $weixin_model->getFiled($res['Results'], "AdminID","admins" ,"Username","AdminName" );
        $this->showJson(1, '', $res);
    }

    /**
     * 筛选分类微信号
     */
    public function findWeixinsAction()
    {
        $weixin_model = new Model_Weixin();
        $res = $weixin_model->findWeixins();
        $this->showJson(1, '', $res);
    }

    // 微信号信息删除
    public function delAction()
    {
        $weixin_id = $this->_getParam('WeixinID', null);
        if (empty($weixin_id)) {
            $this->showJson(0, '参数不存在');
        }
        $weixin_id_data = explode(',', $weixin_id);
        $phone_model = new Model_Weixin();
        foreach ($weixin_id_data as $wei) {
            $res = $phone_model->delete(['WeixinID = ?' => $wei]);
            if (!$res) {
                $this->showJson(0, '删除失败');
            }
        }
        $this->showJson(1, '删除成功');
    }

    // 微信号设置来源
    public function saveChannelAction()
    {
        $weixin_id = $this->_getParam('WeixinID', null);
        $channel = $this->_getParam('Channel', null);
        if (empty($weixin_id)) {
            $this->showJson(0, '参数不存在');
        }
        if (empty($channel)) {
            $this->showJson(0, '填写修改的参数值');
        }
        $weixin_id_data = explode(',', $weixin_id);
        $data['Channel'] = $channel;
        $phone_model = new Model_Weixin();
        foreach ($weixin_id_data as $wei) {
            $res = $phone_model->update($data, ['WeixinID = ?' => $wei]);
            if (!$res) {
                $this->showJson(0, '修改失败');
            }
        }
        $this->showJson(1, '修改成功');
    }

    // 微信号设置标签
    public function saveCategoryAction()
    {
        $weixin_id = $this->_getParam('WeixinID', null);
        $category = $this->_getParam('Category', null);
        $type = $this->_getParam('Type', 1);  // 1-覆盖 2-叠加 3-移除【默认覆盖】
        if (empty($weixin_id)) {
            $this->showJson(0, '参数不存在');
        }
        if (empty($category)) {
            $this->showJson(0, '填写修改的参数值');
        }
        $weixin_id_data = explode(',', $weixin_id);
        $category_data = explode(',',$category);
        $weixin_model = new Model_Weixin();
        foreach ($weixin_id_data as $wei) {
            $info = $weixin_model->findByID($wei);
            if ($info == false) {
                $this->showJson(0, 'WeixinID中有不存在的微信信息[' . $wei . ']');
            }
            $update = '';
            $update_data = [];
            // 原标签数组
            $original_category = explode(',', $info['CategoryIds']);
            switch ($type){
                case 1:
                    foreach ($category_data as $cate){
                            $update .= ',' . $cate;
                    }
                    $update = trim($update,',');
                    $update_data = ['CategoryIds'=>$update];
                    break;
                case 2:
                    foreach ($category_data as $cate){
                        $in_category = in_array($cate, $original_category);
                        if (!$in_category) {
                            $update .= ',' . $cate;
                        }
                    }
                    $update = trim($info['CategoryIds'].$update,',');
                    $update_data = ['CategoryIds'=>$update];
                    break;
                case 3:
                    foreach ($category_data as $cate){
                        $in_category = in_array($cate,$original_category);
                        if ($in_category) {
                            $key = array_search($cate ,$original_category);
                            array_splice($original_category,$key,1);
                        }
                    }
                    $update = implode(',',$original_category);
                    $update_data = ['CategoryIds'=>$update];
                    break;
            }
            if ($update_data){
                try {
                    $weixin_model->update($update_data, ['WeixinID = ?' => $wei]);
                } catch (\Exception $e) {
                    $this->showJson(self::STATUS_FAIL, $e->getMessage());
                }
            }
        }
        $this->showJson(1, '修改成功');
    }


    /**
     * 修改地址信息
     */
    public function savePositionAction()
    {
        $weixin_id = $this->_getParam('WeixinID', null);
        $position = $this->_getParam('Position', null);
        $address = $this->_getParam('Address', null);
        $address_id = $this->_getParam('AddressID', null);
        if (empty($weixin_id)) {
            $this->showJson(0, '参数不存在');
        }
        if (empty($position)) {
            $this->showJson(0, '填写修改的参数值');
        }
        $weixin_id_data = explode(',', $weixin_id);
        $weixin_model = new Model_Weixin();
        $failWxIds = [];
        $data = [
            'Position' => $position,
            'Address'=> $address,
            'AddressID'=> $address_id
        ];
        foreach ($weixin_id_data as $wxId) {
            try {
                $weixin_model->getAdapter()->beginTransaction();

                $weixin_model->update($data, ['WeixinID = ?' => $wxId]);

                $taskConfig = json_encode($data);
                Model_Task::addCommonTask(TASK_CODE_UPDATE_WXPOSITION, $wxId, $taskConfig, $this->getLoginUserId());

                $weixin_model->getAdapter()->commit();
            } catch (\Exception $e) {
                $weixin_model->getAdapter()->rollBack();
                $failWxIds[] = $wxId;
            }
        }
        $this->showJson(1, '修改完成,失败数:' . count($failWxIds));
    }

    /**
     * 修改微信个人信息
     */
    public function saveWeixininfoAction()
    {
        $weixinIds = $this->_getParam('WeixinIDs', '');
        $avatarUrl = $this->_getParam('AvatarUrl', '');
        $nickName = $this->_getParam('Nickname', '');
        $sex = $this->_getParam('Sex', null);
        $signature = $this->_getParam('Signature', '');  // 签名
        $coverimgUrl = $this->_getParam('CoverimgUrl', ''); // 朋友圈背景图
        $areaCode = $this->_getParam('AreaCode', '');
//        $wxNotes = $this->_getParam('WxNotes', '');
        $saveType = $this->_getParam('SaveType',0);   //0-修改 1-同步

        if (empty($weixinIds)) {
            $this->showJson(0, '参数不存在');
        }
        // 签名最多是30个中文字(90字节)
        if (strlen($signature) > 90){
            $this->showJson(0, '签名最多可添加30个中文');
        }

        if ($saveType == 1){
            $data =[];
            if (!empty($avatarUrl)){
                $data['AvatarUrl'] = $avatarUrl;
            }
            if (!empty($nickName)){
                $data['Nickname'] = $nickName;
            }
            if (!empty($sex)){
                $data['Sex'] = $sex;
            }
            if (!empty($signature)){
                $data['Signature'] = $signature;
            }
            if (!empty($coverimgUrl)){
                $data['CoverimgUrl'] = $coverimgUrl;
            }

        }else{
            if (empty($avatarUrl)) {
                $this->showJson(0, '头像不能为空');
            }
            if (empty($nickName)) {
                $this->showJson(0, '昵称不能为空');
            }
            if (empty($sex)) {
                $this->showJson(0, '性别不能为空');
            }
            if (empty($coverimgUrl)) {
                $this->showJson(0, '朋友圈背景不能为空');
            }
//            if (empty($areaCode)) {
//                $this->showJson(0, '地区选择不能为空');
//            }

            $data = [
                'AvatarUrl'=>$avatarUrl,
                'Nickname'=>$nickName,
//                'WxNotes'=>$wxNotes,
                'Sex'=>empty($sex)?-1:$sex,
                'Signature'=>$signature,
                'CoverimgUrl'=>$coverimgUrl
            ];
        }
        if (!empty($areaCode)){
            $code = [
                'Nation',
                'Province',
                'City'
            ];

            $areaCode = explode('_',$areaCode);

            foreach ($areaCode as $k=>$v){
                if (!empty($code[$k])){
                    $data[$code[$k]] = $v;
                }
            }
        }

        $weixinIdData = explode(',', $weixinIds);
        $taskModel = Model_Task::getInstance();
//        $weixinModel = Model_Weixin::getInstance();
        $failWxIds = [];

        foreach ($weixinIdData as $wxId) {
            try {
                $taskConfig = json_encode($data);
                $taskModel->addCommonTask(TASK_CODE_UPDATE_WXINFO, $wxId, $taskConfig, $this->getLoginUserId());

                // 修改备注
//                $weixinModel->update(['WxNotes'=>$wxNotes],['WeixinID = ?'=>$wxId]);

            } catch (\Exception $e) {
                $failWxIds[] = $wxId;
            }
        }
        $this->showJson(1, '修改完成,失败数:' . count($failWxIds));
    }


    /**
     * 查询管理员信息
     */
    public function findUsernameAction()
    {
        $memberAction = $this->_getParam('MemberAction','');
        if($memberAction > 0){
            $AdminID = $this->getLoginUserId();
            $this->showJson(1,'管理员',(new Model_Role_Admin())->getAdminRoleUserName($AdminID));
        }
        $this->showJson(1,'管理员',(new Model_Role_Admin())->getAdminUserName());
    }

    /**
     * 设置管理员
     */
    public function saveAdminAction()
    {
        $admin_id = $this->_getParam('AdminID',null);
        $weixin_ids = $this->_getParam('WeixinIDs',null);

        $weixin_model = new Model_Weixin();

        try{
            $weixin_model->saveAdminID($admin_id,$weixin_ids);
            $this->showJson(1, '修改完成');
        }catch (Exception $e){
            $this->showJson(0,'修改失败:'.$e->getMessage());
        }
    }

    public function reportWxInfoAction()
    {
        $wxIds = trim($this->_getParam('WxIDs', ''));
        if ($wxIds === '') {
            $this->showJson(self::STATUS_FAIL, '微信ids为空');
        }
        $wxIds = explode(',', $wxIds);
        $onlineWxIds = (new Model_Device())->findOnlineWeixin();

        $wxIds = array_intersect($wxIds, $onlineWxIds);
        foreach ($wxIds as $wxId) {
            $taskConfig = json_encode(['Weixin' => []]);
            Model_Task::addCommonTask(TASK_CODE_WEIXIN_FRIEND, $wxId, $taskConfig, $this->getLoginUserId());
        }

        $this->showJson(self::STATUS_OK, '操作成功');
    }

    public function detailAction()
    {
        $wxId = (int)$this->_getParam('WeixinID');
        $weixin = trim($this->_getParam('Weixin', ''));

        $weixin_model = new Model_Weixin();

        $wx = $weixin_model->WxDetail($wxId,$weixin);

        if (!$wx) {
            $this->showJson(self::STATUS_FAIL, '没有找到');
        }

        $this->showJson(self::STATUS_OK, '操作成功', $wx);
    }

    public function friendsListAction()
    {
        $page = $this->_getParam('page', 1);
        $pagesize = $this->_getParam('pagesize', 100);
        $WeixinID = $this->_getParam('WeixinID');
        $NickName = $this->_getParam('NickName');
        $Account = $this->_getParam("Account");

        $StartTime = $this->_getParam("StartTime");
        $EndTime = $this->_getParam("EndTime");

        $model = new Model_Weixin_Friend();
        $select = $model->select()->setIntegrityCheck(false);
        $select->from($model->getTableName());
        if($WeixinID > 0){
            $select->where("WeixinID = ? ",$WeixinID);
        }
        if(!empty($Account)){
            $select->where("Account like ? or Alias like ?", "%".addslashes($Account)."%");
        }
        if(!empty($NickName)){
            $select->where("NickName like ?", "%".addslashes($NickName)."%");
        }
        if(!empty($StartTime)){
            $select->where("AddDate >= ",$StartTime);
        }
        if(!empty($EndTime)){
            $EndTime = date("Y-m-d",strtotime("$EndTime +1 day"));
            $select->where("AddDate < ", $EndTime);
        }
        $select->order("AddDate desc");
//        var_dump($select->__toString());exit;
        $data = $model->getResult($select, $page, $pagesize);

        $this->showJson(true, '',$data);
    }

    /**
     * 设置设备的网络信息
     */
    public function setNetworkAction()
    {
        $wxIds = trim($this->_getParam('WeixinIds', ''));
        $fly_model = $this->_getParam('FlyModel',0);
        $net = $this->_getParam('Net',0);  // 0-4G 1-WIFI
        $wifiname = trim($this->_getParam('WIFIname',''));
        $wifipass = trim($this->_getParam('WIFIpassword',''));

        if ($wxIds === '') {
            $this->showJson(self::STATUS_FAIL, '微信ids为空');
        }
        $wxIds = explode(',', $wxIds);
        $onlineWxIds = (new Model_Device())->findOnlineWeixin();

        $onlineWxIds = array_intersect($wxIds, $onlineWxIds);
        if (count($onlineWxIds) != count($wxIds)){
            $this->showJson(self::STATUS_FAIL, '所选微信号有不在线情况');
        }
        foreach ($onlineWxIds as $wxId) {
            $taskConfig = [
                'FlyModel' => $fly_model,
                'Net' => $net,
                'WIFI'=>[
                    'Name'=>$wifiname,
                    'Password'=>$wifipass
                ],
                'VPN' => [
                    'Account'=>'',
                    'Port'=>'',
                    'Username'=>'',
                    'Password'=>''
                ]
            ];
            Model_Task::addCommonTask(TASK_CODE_DEVICE_NETWORK, $wxId, json_encode($taskConfig), $this->getLoginUserId());
        }

        $this->showJson(self::STATUS_OK, '操作成功');
    }

    /**
     * 微信隐私设置配置
     * [通讯录,朋友圈]
     */
    public function setPrivacyAction()
    {
        $wxIds = trim($this->_getParam('WeixinIds', ''));
        $friendsValidation = $this->_getParam('FriendsValidation',1);    // 添加好友验证
        $addressBookFriends = $this->_getParam('AddressBookFriends',1);  // 向我推荐通讯录好友
        $addMeWay = $this->_getParam('AddMeWay',[]);                     // 添加我的方法 Weixin Phone QQ Group QRcode NameCard
        $viewTenPictures = $this->_getParam('ViewTenPictures',1);        // 允许陌生人查看十张照片
        $viewRange = $this->_getParam('ViewRange',1);                    // 允许朋友查看朋友圈范围 0-全部 1-三天 2-半年

        if ($wxIds === '') {
            $this->showJson(self::STATUS_FAIL, '微信ids为空');
        }
        $wxIds = explode(',', $wxIds);
        $onlineWxIds = (new Model_Device())->findOnlineWeixin();

        // 在线微信
        $onlineWxIds = array_intersect($wxIds, $onlineWxIds);

        if (count($onlineWxIds) != count($wxIds)){
            $this->showJson(self::STATUS_FAIL, '所选微信号有不在线情况');
        }

        $weixinSettingModel = Model_Weixin_Setting::getInstance();

        $weixinSettings = $weixinSettingModel->getWxSettings($onlineWxIds);

        foreach ($onlineWxIds as $wxId) {
            $taskConfig = [];

            if ($weixinSettings[$wxId]['FriendsValidation'] != $friendsValidation){
                $taskConfig['FriendsValidation'] = $friendsValidation;
            }

            if ($weixinSettings[$wxId]['AddressBookFriends'] != $addressBookFriends){
                $taskConfig['AddressBookFriends'] = $addressBookFriends;
            }

            if (!empty($addMeWay['Weixin']) && $weixinSettings[$wxId]['Weixin'] != $addMeWay['Weixin']){
                $taskConfig['Weixin'] = $addMeWay['Weixin'];
            }
            if (!empty($addMeWay['Phone']) && $weixinSettings[$wxId]['Phone'] != $addMeWay['Phone']){
                $taskConfig['Phone'] = $addMeWay['Phone'];
            }
            if (!empty($addMeWay['QQ']) && $weixinSettings[$wxId]['QQ'] != $addMeWay['QQ']){
                $taskConfig['QQ'] = $addMeWay['QQ'];
            }
            if (empty($addMeWay['Group']) && $weixinSettings[$wxId]['Group'] != $addMeWay['Group']){
                $taskConfig['Group'] = $addMeWay['Group'];
            }
            if (!empty($addMeWay['QRcode']) && $weixinSettings[$wxId]['QRcode'] != $addMeWay['QRcode']){
                $taskConfig['QRcode'] = $addMeWay['QRcode'];
            }
            if (!empty($addMeWay['NameCard']) && $weixinSettings[$wxId]['NameCard'] != $addMeWay['NameCard']){
                $taskConfig['NameCard'] = $addMeWay['NameCard'];
            }

            if ($weixinSettings[$wxId]['ViewTenPictures'] != $viewTenPictures){
                $taskConfig['ViewTenPictures'] = $viewTenPictures;
            }

            if ($weixinSettings[$wxId]['ViewRange'] != $viewRange){
                $taskConfig['ViewRange'] = $viewRange;
            }

            Model_Task::addCommonTask(TASK_CODE_PRIVACY_SETTINGS, $wxId, json_encode($taskConfig), $this->getLoginUserId());

            $setting = $weixinSettingModel->findByWeixnID($wxId);

            // 数据库存在微信客户端配置
            if ($setting){
                $update = [
                    'FriendsValidation' => $friendsValidation,
                    'AddressBookFriends' => $addressBookFriends,
                    'AddMeWay' => json_encode([
                        'Weixin' => $addMeWay['Weixin'],
                        'Phone' => $addMeWay['Phone'],
                        'QQ' => $addMeWay['QQ'],
                        'Group' => $addMeWay['Group'],
                        'QRcode' => $addMeWay['QRcode'],
                        'NameCard' => $addMeWay['NameCard']
                    ]),
                    'ViewTenPictures' => $viewTenPictures,
                    'ViewRange' => $viewRange,
                    'UpdateTime' => date('Y-m-d H:i:s')

                ];

                $weixinSettingModel->update($update,['SettingID = ?'=>$setting['SettingID']]);
            }else{
                $insert = [
                    'WeixinID' => $wxId,
                    'FriendsValidation' => $friendsValidation,
                    'AddressBookFriends' => $addressBookFriends,
                    'AddMeWay' => json_encode([
                        'Weixin' => $addMeWay['Weixin'],
                        'Phone' => $addMeWay['Phone'],
                        'QQ' => $addMeWay['QQ'],
                        'Group' => $addMeWay['Group'],
                        'QRcode' => $addMeWay['QRcode'],
                        'NameCard' => $addMeWay['NameCard']
                    ]),
                    'ViewTenPictures' => $viewTenPictures,
                    'ViewRange' => $viewRange
                ];
                $weixinSettingModel->insert($insert);
            }
        }

        $this->showJson(self::STATUS_OK, '设置成功');

    }

    /**
     * 微信设置附近的人
     */
    public function nearPeopleAction()
    {
        $wxIds = trim($this->_getParam('WeixinIds', ''));
        $sex = $this->_getParam('Sex',1);
        $auto = $this->_getParam('AutoPass',1);
        $quit = $this->_getParam('Quit',1);

        if ($wxIds === '') {
            $this->showJson(self::STATUS_FAIL, '微信ids为空');
        }
        $wxIds = explode(',', $wxIds);
        $onlineWxIds = (new Model_Device())->findOnlineWeixin();

        // 在线微信
        $onlineWxIds = array_intersect($wxIds, $onlineWxIds);
        if (count($onlineWxIds) != count($wxIds)){
            $this->showJson(self::STATUS_FAIL, '所选微信号有不在线情况');
        }

        foreach ($onlineWxIds as $wxId) {
            $taskConfig = [
                'Sex' => $sex,
                'AutoPass' => $auto,
                'Quit' => $quit,
            ];
            Model_Task::addCommonTask(TASK_CODE_NEAR_PEOPLE, $wxId, json_encode($taskConfig), $this->getLoginUserId());
        }

        $this->showJson(self::STATUS_OK, '设置成功');

    }

    /**
     * 功能设置
     */
    public function wxSwitchesAction()
    {
        $wxIds = trim($this->_getParam('WeixinIds', ''));
        $taskCode = trim($this->_getParam('TaskCode',''),'');  // 功能Code
        $status = $this->_getParam('Status',0);

        if (!array_key_exists($taskCode,TASK_CODE)){
            $this->showJson(0, '功能Code不合法');
        }

        if ($taskCode === ''){
            $this->showJson(self::STATUS_FAIL, '功能信息为空');
        }

        if ($wxIds === '') {
            $this->showJson(self::STATUS_FAIL, '微信ids为空');
        }
        $wxIds = explode(',', $wxIds);
        $onlineWxIds = (new Model_Device())->findOnlineWeixin();

        // 在线微信
        $onlineWxIds = array_intersect($wxIds, $onlineWxIds);
        if (count($onlineWxIds) != count($wxIds)){
            $this->showJson(self::STATUS_FAIL, '所选微信号有不在线情况');
        }

        // Model
        $weixinSettingModel = Model_Weixin_Setting::getInstance();


        foreach ($onlineWxIds as $wxId) {
            try{
                $taskConfig = [
                    'TaskCode' => $taskCode,
                    'Status' => $status == 1?true:false,
                ];

                Model_Task::addCommonTask(TASK_CODE_SWITCHES, $wxId, json_encode($taskConfig), $this->getLoginUserId());

                $setting = $weixinSettingModel->findByWeixnID($wxId);

                if ($setting){
                    $update = [
                        'WxFriendPass'=>$status
                    ];
                    $weixinSettingModel->update($update,['SettingID = ?',$setting['SettingID']]);
                }else{
                    $insert = [
                        'WxFriendPass'=>$status,
                        'WeixinID'=>$wxId,
                        'UpdateTime'=>date('Y-m-d H:i:s')
                    ];
                    $weixinSettingModel->update($insert);
                }

            }catch (Exception $e){
                $this->showJson(self::STATUS_FAIL, '修改错误抛出异常'.$e->getMessage());

            }
        }

        $this->showJson(self::STATUS_OK, '设置成功');

    }

    /**
     * 设置微信号的每日任务
     */
    public function setDailyTaskAction()
    {
        $wxIds = trim($this->_getParam('WeixinIds', ''));
        $taskCode = trim($this->_getParam('TaskCode',''),'');  // 功能Code
        $status = $this->_getParam('Status',null);

        if (!array_key_exists($taskCode,TASK_CODE)){
            $this->showJson(0, '功能Code不合法');
        }

        if ($taskCode === ''){
            $this->showJson(self::STATUS_FAIL, '功能信息为空');
        }

        if ($wxIds === '') {
            $this->showJson(self::STATUS_FAIL, '微信ids为空');
        }
        $wxIds = explode(',', $wxIds);


        $dailyTaskModel = new Model_DailyTask();

        $hour = date('H');

        foreach ($wxIds as $wxId) {
            try{
                $dailyTask = $dailyTaskModel->fetchRow(['WeixinID = ?'=>$wxId,'TaskCode = ?'=>$taskCode]);

                $h = (string)rand($hour,24);
                $i = (string)rand(0,59);
                $s = (string)rand(0,59);
                if ((int)$h<10){
                    $h = '0'.$h;
                }
                if ((int)$i<10){
                    $i = '0'.$i;
                }
                if ((int)$s<10){
                    $s = '0'.$s;
                }

                if ($dailyTask){
                    $dailyTask->Status = $status==1?'ON':'OFF';
                    $dailyTask->UpdateDate = date('Y-m-d H:i:s');
                    $dailyTask->AdminID = $this->getLoginUserId();
                    $dailyTask->NextRunTime = date('Y-m-d ').$h.':'.$i.':'.$s;
                    $dailyTask->save();
                }else{
                    $data = [
                        'WeixinID' => $wxId,
                        'TaskCode' => $taskCode,
                        'Status' => $status==1?'ON':'OFF',
                        'CreateDate' => date('Y-m-d H:i:s'),
                        'AdminID' =>$this->getLoginUserId(),
                        'NextRunTime' => date('Y-m-d ').$h.':'.$i.':'.$s,
                    ];
                    $dailyTaskModel->insert($data);
                }
            }catch (Exception $e){
                $this->showJson(self::STATUS_FAIL, '抛出异常'.$e->getMessage());
            }

        }
        $this->showJson(self::STATUS_OK, '设置成功');
    }

}