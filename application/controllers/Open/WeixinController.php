<?php

require_once APPLICATION_PATH . '/controllers/Open/OpenBase.php';

class Open_WeixinController extends OpenBase
{

    /**
     * 微信号详情
     */
    public function infoAction()
    {
        $weixinId = $this->_getParam('WeixinID', null);

        if ($weixinId){
            // Model
            $weixinModel = Model_Weixin::getInstance();
            $areaModel = Model_Area::getInstance();

            $weixins = $weixinModel->getDataByWeixinID($weixinId);

            $code = '';
            if (!empty($weixins['Nation'])){
                $code .= $weixins['Nation'];
            }
            if (!empty($weixins['Province'])){
                $code .= '_'.$weixins['Province'];
            }
            if (!empty($weixins['City'])){
                $code .= '_'.$weixins['City'];
            }

            $areaCode = [];

            if ($code){
                $area = $areaModel->findByAreaCode($code);
                if ($area != false){
                    $areaCode = $this->getParent($areaCode,$area['AreaID']);
                }
            }

            $weixins['AreaCodes'] = $areaCode;

            $code = [];

            if (!empty($weixins['Nation'] && isset($weixins['Nation']))){
                $code['Nation'] = $weixins['Nation'];

            }
            if (!empty($weixins['Province'] && isset($weixins['Province']))){
                $code['Province'] = $weixins['Nation'].'_'.$weixins['Province'];
            }
            if (!empty($weixins['City'] && isset($weixins['City']))){
                $code['City'] = $weixins['Nation'].'_'.$weixins['Province'].'_'.$weixins['City'];
            }

            $codeNames = $areaModel->findByCodeNames($code);

            if ($codeNames){
                if (!empty($weixins['Nation']) && isset($weixins['Nation']) && !empty($codeNames[$code['Nation']])){
                    $weixins['Nation'] = $codeNames[$code['Nation']];
                }
                if (!empty($weixins['Province']) && isset($weixins['Province']) && !empty($codeNames[$code['Province']])){
                    $weixins['Province'] = $codeNames[$code['Province']];
                }
                if (!empty($weixins['City']) && isset($weixins['City']) && !empty($codeNames[$code['City']])){
                    $weixins['City'] = $codeNames[$code['City']];
                }
            }


            $this->showJson(1, '微信列表',$weixins);

        }else{
            $this->showJson(1, '无管理微信','');
        }
    }

    public function getParent(&$codes,$areaID)
    {
        $areaModel = Model_Area::getInstance();

        $area = $areaModel->findByID($areaID);

        if ($area){
            array_unshift($codes,$area['AreaCode']);
            $this->getParent($codes,$area['ParentAreaID']);
        }else{
            return $codes;
        }
        return $codes;
    }

    /**
     * 获取管理的微信列表
     */
    public function listAction()
    {
        $weixinIds = $this->adminWxIds;
        $Name = trim($this->_getParam("Name"));
        if ($weixinIds){
            $weixinModel = new Model_Weixin();
            $weixins = $weixinModel->getWeixins($weixinIds,$Name);
            $this->showJson(1, '微信列表',$weixins);

        }else{
            $this->showJson(1, '无管理微信','');
        }
    }

    /**
     * 微信号设置标签
     */
    public function saveCategoryAction()
    {
        $weixinIds = $this->_getParam('WeixinIDs', null);
        $category = $this->_getParam('CategoryIds', null);
        $type = $this->_getParam('Type', 1);  // 1-覆盖 2-叠加 3-移除【默认覆盖】
        if (empty($weixinIds)) {
            $this->showJson(0, '参数不存在');
        }
        if (empty($category)) {
            $this->showJson(0, '填写修改的参数值');
        }
        $weixinIdData = explode(',', $weixinIds);
        $categoryData = explode(',',$category);
        $weixinModel = Model_Weixin::getInstance();
        foreach ($weixinIdData as $wei) {
            $info = $weixinModel->findByID($wei);
            if ($info == false) {
                $this->showJson(0, 'WeixinID中有不存在的微信信息[' . $wei . ']');
            }
            $update = '';
            // 原标签数组
            $originalCategory = explode(',', $info['YyCategoryIds']);
            switch ($type){
                case 1:
                    foreach ($categoryData as $cate){
                        $update .= ',' . $cate;
                    }
                    $update = trim($update,',');
                    $updateData = ['YyCategoryIds'=>$update];
                    break;
                case 2:
                    foreach ($categoryData as $cate){
                        $inCategory = in_array($cate, $originalCategory);
                        if (!$inCategory) {
                            $update .= ',' . $cate;
                        }
                    }
                    $update = trim($info['YyCategoryIds'].$update,',');
                    $updateData = ['YyCategoryIds'=>$update];
                    break;
                case 3:
                    foreach ($categoryData as $cate){
                        $inCategory = in_array($cate,$originalCategory);
                        if ($inCategory) {
                            $key = array_search($cate ,$originalCategory);
                            array_splice($originalCategory,$key,1);
                        }
                    }
                    $update = implode(',',$originalCategory);
                    $updateData = ['YyCategoryIds'=>$update];
                    break;
            }
            if ($updateData){
                $res = $weixinModel->update($updateData, ['WeixinID = ?' => $wei]);
                if (!$res) {
                    $this->showJson(0, '修改失败');
                }
            }
        }
        $this->showJson(1, '修改成功');
    }

    /**
     * 修改微信个人信息
     */
    public function saveWxInfoAction()
    {
        $weixinIds = $this->_getParam('WeixinIDs', '');
        $avatarUrl = $this->_getParam('AvatarUrl', '');
        $nickName = $this->_getParam('Nickname', '');
        $sex = $this->_getParam('Sex', null);
        $signature = $this->_getParam('Signature', '');  // 签名
        $coverimgUrl = $this->_getParam('CoverimgUrl', ''); // 朋友圈背景图
        $areaCode = $this->_getParam('AreaCode', '');
        $wxNotes = $this->_getParam('WxNotes', '');
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
            $data = [
                'AvatarUrl'=>$avatarUrl,
                'Nickname'=>$nickName,
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
        $weixinModel = Model_Weixin::getInstance();

        foreach ($weixinIdData as $wxId) {
            try {
                $taskConfig = json_encode($data);
                $taskModel->addCommonTask(TASK_CODE_UPDATE_WXINFO, $wxId, $taskConfig, $this->getLoginUserId());

                // 修改备注
                $weixinModel->update(['WxNotes'=>$wxNotes],['WeixinID = ?'=>$wxId]);

            } catch (Exception $e) {
                $this->showJson(0, '抛出异常'.$e->getMessage());
            }
        }
        $this->showJson(1, '修改完成');
    }

    /**
     * 微信隐私设置配置
     * [通讯录,朋友圈]
     */
    public function setPrivacyAction()
    {
        $wxIds = trim($this->_getParam('WeixinIDs', ''));
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
     * 功能设置
     */
    public function wxSwitchesAction()
    {
        $wxIds = trim($this->_getParam('WeixinIDs', ''));
        $taskCode = trim($this->_getParam('TaskCode',''),'');  // 功能Code
        $status = $this->_getParam('Status',1);

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

        $weixinSettingModel = Model_Weixin_Setting::getInstance();

        foreach ($onlineWxIds as $wxId) {
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
                $weixinSettingModel->update($update,['SettingID = ?'=>$setting['SettingID']]);
            }else{
                $insert = [
                    'WxFriendPass'=>$status,
                    'WeixinID'=>$wxId,
                    'AddMeWay' => json_encode([
                        'Weixin' => 1,
                        'Phone' => 1,
                        'QQ' => 1,
                        'Group' => 1,
                        'QRcode' => 1,
                        'NameCard' => 1
                    ]),
                    'UpdateTime'=>date('Y-m-d H:i:s')
                ];
                $weixinSettingModel->insert($insert);
            }

        }

        $this->showJson(self::STATUS_OK, '设置成功');

    }

    /**
     * 微信-地区列表
     */
    public function areaListAction()
    {
        set_time_limit(0);
        try{
            // Model
            $areaModel = Model_Area::getInstance();

            // Redis
            $redis = Helper_Redis::getInstance();
            $redisKey = Helper_Redis::wxAreaKey();
            $areaList = $redis->hGet($redisKey,'WeixinAreaList');

            if ($areaList) {
                $res = json_decode($areaList,1);
            }else{
                $areaList = $areaModel->getChild();

                $areaList = $this->Child($areaList);

                $redis->hSet($redisKey,'WeixinAreaList',json_encode($areaList));
                $res = $areaList;
            }

            $this->showJson(self::STATUS_OK, '列表',$res);

        }catch (Exception $e){
            $this->showJson(self::STATUS_FAIL, '抛出异常'.$e->getMessage());
        }

    }

    /**
     * 微信地区-获取子区
     */
    private function Child($data)
    {
        $areaModel = Model_Area::getInstance();
        foreach ($data as &$d){
            $child = $areaModel->getChild($d['AreaID']);
            if ($child){
                $child = $this->Child($child);
                $d['Child'] = $child;
            }
        }
        return $data;
    }

    /**
     * 获取微信客户端配置
     */
    public function wxSettingAction()
    {
        $weixinId = $this->_getParam('WeixinID',null);

        // Model
        $weixinSettingModel = Model_Weixin_Setting::getInstance();
        $weixinModel = Model_Weixin::getInstance();


        try{
            $setting = $weixinSettingModel->findByWeixnID($weixinId);

            if ($setting){
                if (empty($setting['AddMeWay'])){
                    $setting['AddMeWay'] = '';
                }else{
                    $setting['AddMeWay'] = json_decode($setting['AddMeWay'],1);
                }

                $weixinInfo = $weixinModel->getDataByWeixinID($weixinId);

                if ($weixinInfo){
                    $setting['CategoryIds'] = $weixinInfo['YyCategoryIds'];
                }

                $this->showJson(self::STATUS_OK, '配置详情',$setting);
            }else{
                $this->showJson(self::STATUS_OK, '配置详情','');
            }

        }catch (Exception $e){
            $this->showJson(self::STATUS_FAIL, '抛出异常'.$e->getMessage());
        }

    }

    /**
     * 更新微信二维码
     */
    public function qrcodeImgAction()
    {
        $weixinIds = $this->_getParam('WeixinIDs','');

        try{
            $weixinIds = explode(',',$weixinIds);

            $taskModel = Model_Task::getInstance();

            $runTask = $taskModel->todayRunTasks($weixinIds,TASK_CODE_WEIXIN_QRCODE,TASK_STATUS_FINISHED);

            if ($runTask){
                $this->showJson(self::STATUS_FAIL, '微信ID'.implode(',',$runTask).' 今日已经执行过任务');
            }

            foreach ($weixinIds as $wxId){

                Model_Task::addCommonTask(TASK_CODE_WEIXIN_QRCODE, $wxId,'', $this->getLoginUserId());

            }

        }catch (Exception $e){
            $this->showJson(self::STATUS_FAIL, '抛出异常'.$e->getMessage());
        }

    }
}