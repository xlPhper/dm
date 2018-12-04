<?php
/**
 * Created by PhpStorm.
 * User: McGrady
 * Date: 2018/11/15
 * Time: 15:29
 */
require_once APPLICATION_PATH . '/controllers/Open/OpenBase.php';

class Open_MemberController extends OpenBase
{

    /**
     * 个人号列表
     */
    public function listAction(){
        $page               = $this->_getParam('Page',1);
        $pagesize           = $this->_getParam('Pagesize',100);
        $Weixin             = trim($this->_getParam('Weixin',null));
        $CategoryIds        = $this->_getParam('CategoryIds',null);
        $FriendsMin         = (int)$this->_getParam('FriendsMin',null);
        $FriendsMax         = (int)$this->_getParam('FriendsMax',null);
        $SerialNumS         = $this->_getParam('SerialNum',null);
        $Status             = $this->_getParam('Status','All');
        $AdminID            = $this->_getParam('AdminID',null);

        $model = new Model_Weixin();

        //判断是否为超级管理员
        $userId = $this->getLoginUserId();
        $wxArr = [];
        if($this->admin['IsSuper']=='N'){
            $arr = $model->fetchAll(['find_in_set(?,YyAdminID)'=>$userId])->toArray();
            foreach ($arr as $value){
                $wxArr[] = $value['WeixinID'];
            }
        }else{
            //第一部分根据标签添加的微信号保存在company表里
            $company = (new Model_Company())->fetchRow(['CompanyID = ?'=>$this->admin['CompanyId']]);
            $wxArr = explode(',',$company['WeixinIds']);
            //第二部分微信表里面直属当前超管账号的数据以及子账号下属的数据
            $userData = (new Model_Role_Admin())->fetchAll(['CompanyId = ?'=> $this->admin['CompanyId']])->toArray();
            $userArr = [];
            foreach ($userData as $value){
                $userArr[] = $value['AdminID'];
            }

            $addInfo = $model->fetchAll(['YyAdminID in (?)'=>$userArr])->toArray();
            $addArr =[];
            foreach ($addInfo as $value){
                $addArr[] = $value['WeixinID'];
            }
            $wxArr = array_unique(array_merge($wxArr,$addArr));
        }

        if(!$wxArr){
            $wxArr[] = -1;
        }

        $select = $model->fromSlaveDB()->select()->setIntegrityCheck(false);
        $select->from('weixins as w',['WeixinID','Weixin','Nickname','WxNotes','YyCategoryIds','FriendNumber','YyAdminID','w.Alias']);
        $select->joinLeft('devices as d','d.DeviceID = w.DeviceID',['d.Status','SerialNum']);

        $select->where('w.WeixinID in (?)',$wxArr);

        if($Weixin){
            $select->where('w.Weixin = ? or w.Alias = ? or w.WeixinID = ?',$Weixin);
        }
        if($CategoryIds){
            $CategoryIds = explode(',', $CategoryIds);
            $cateWheres = [];
            foreach ($CategoryIds as $cId) {
                $cateWheres[] = 'FIND_IN_SET(' . (int)$cId. ',  YyCategoryIds)';
            }
            $select->where(implode(' OR ', $cateWheres));
        }

        if($FriendsMin){
            $select->where('w.FriendNumber >= ?',$FriendsMin);
        }
        if($FriendsMax){
            $select->where('w.FriendNumber <= ?',$FriendsMax);
        }
        if($AdminID){
            $select->where('w.YyAdminID = ?',$AdminID);
        }
        if(!empty($SerialNumS)){
            $SerialNumS = str_replace('，',',',$SerialNumS);
            $SerialNum  = explode(',', $SerialNumS);
            $SerialNum  = array_filter($SerialNum);
            $tmpSerialNum = [];
            foreach ($SerialNum as $s) {
                $s = trim($s);
                if (!empty($s)) {
                    $tmpSerialNum[] = "d.SerialNum like '%" . $s . "'";
                }
            }
            if (!empty($tmpSerialNum)) {
                $select->where(implode(' or ', $tmpSerialNum));
            }
//            if(!empty($SerialNum)){
//                $select->where('d.SerialNum in (?)',$SerialNum);
//            }
        }
        if($Status !='All'){
            if($Status == 1){
                $select->where('d.Status = ?','RUNNING');
            }
            if($Status == 2){
                $select->where('d.Status = ?','PAUSE')->orWhere('d.Status = ?','EXCEPT');
            }
        }
        $res = $model->getResult($select,$page,$pagesize);
        $categories = (new Model_Category())->getIdToName();
        $adminToUsername = (new Model_Role_Admin())->getNames();
        foreach ($res['Results'] as &$value){

            if ($value['Alias']) {
                if ($value['SerialNum']) {
                    $value['Weixin'] = '(' . mb_substr($value['SerialNum'], -4) . ')' . $value['Alias'];
                }
            } else {
                if ($value['SerialNum']) {
                    $value['Weixin'] = '(' . mb_substr($value['SerialNum'], -4) . ')' . $value['Weixin'];
                }
            }
            $value['YyCategoryIds'] = DM_Helper::explodeToImplode($value['YyCategoryIds'],$categories);
            $value['YyAdminID'] = DM_Helper::explodeToImplode($value['YyAdminID'],$adminToUsername);
        }
        $this->showJson(1,'操作成功',$res);
    }

    /**
     * 设置管理员
     * 解决代码不合并
     */
    public function setMemberAction(){
        $wxIds          = $this->_getParam('WeixinIDs');
        $adminIds        = $this->_getParam('AdminID');

        if(!$wxIds){
            $this->showJson(0,'参数微信id不能为空');
        }
        if(!$adminIds){
            $this->showJson(0,'请选择管理员');
        }
        $wxArr = explode(',',$wxIds);
        $model = new Model_Weixin();
        $cModel = new Model_Company();

        if($this->admin['IsSuper']=='N'){
            $this->showJson(0,'您没有权限操作');
        }

        //查看当前超管下面的微信
        $wxInfo = $cModel->fetchRow(['CompanyID = ?'=>$this->admin['CompanyId']]);
        $superWxArr = explode(',',$wxInfo['WeixinIds']);

        $userData = (new Model_Role_Admin())->fetchAll(['CompanyId = ?'=> $this->admin['CompanyId']])->toArray();
        $userArr = [];
        foreach ($userData as $value){
            $userArr[] = $value['AdminID'];
        }
        $addInfo = $model->fetchAll(['YyAdminID in (?)'=>$userArr])->toArray();
        $addArr =[];
        foreach ($addInfo as $value){
            $addArr[] = $value['WeixinID'];
        }

        $superWxArr = array_unique(array_merge($superWxArr,$addArr));
        $diffArr = array_diff($wxArr,$superWxArr);

        if($diffArr){
            $this->showJson(0,'操作异常数据');
        }

        try{
            //$model->update(['YyAdminID'=> $this->admin['AdminID']],['WeixinID in (?)'=>$wxArr]);
            $model->update(['YyAdminID'=> $adminIds],['WeixinID in (?)'=>$wxArr]);
            $this->showJson(1, '操作成功');
        }catch (\Exception $e){
            $this->showJson(0, $e->getMessage());
        }
    }

    /**
     * 设置标签
     */
    public function setTagAction(){
        $categoryIds     = $this->_getParam('CategoryID');
        $wxId            = $this->_getParam('WeixinID');
        $type            = $this->_getParam('Type');

        $categoryArr    = explode(',',$categoryIds);
        $wxIds          = explode(',',$wxId);

        if(!$categoryIds){
            $this->showJson(0,'标签id不能为空');
        }
        if(!$wxId){
            $this->showJson(0,'微信id不能为空');
        }
        $wModel = new Model_Weixin();
        //判断是否为超级管理员
        $userId = $this->getLoginUserId();
        //如果不是超级管理员，判断是否为归属个号
        if($this->admin['IsSuper']=='N'){
            $checkArr = [];
            $checkResult = $wModel->select()->where('AdminID = ?',$userId)->query()->fetchAll();
            foreach ($checkResult as $value){
                $checkArr[] = $value['WeixinID'];
            }
            $diffArr = array_diff($wxIds,$checkArr);
            if($diffArr){
                $this->showJson(0,'操作异常数据');
            }
        }

        //新增标签
        if($type==1){
            foreach ($wxIds as $value){
                $cateResult = $wModel->select()->where('WeixinID = ?',$value)->query()->fetch();
                $cateIds = $cateResult['YyCategoryIds'].','.$categoryIds;
                $wModel->update(['YyCategoryIds' => $cateIds],['WeixinID = ?'=>$value]);
            }
            $this->showJson(1, '操作成功');
        }
        //覆盖标签
        if($type ==2){
            $wModel->update(['YyCategoryIds' => $categoryIds],['WeixinID in (?)'=>$wxIds]);
            $this->showJson(1, '操作成功');
        }
        //删除标签
        if($type ==3){
            foreach ($wxIds as $value){
                $cateResult = $wModel->select()->where('WeixinID = ?',$value)->query()->fetch();
                $cateArr = explode(',',$cateResult['YyCategoryIds']);
                foreach ($categoryArr as $key => $id){
                    $cateArr = array_diff($cateArr,[$id]);
                }
                $cateStr = implode(',',$cateArr);
                $wModel->update(['YyCategoryIds' => $cateStr],['WeixinID = ?'=>$value]);
            }
            $this->showJson(1, '操作成功');
        }
    }
}