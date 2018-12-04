<?php

require_once APPLICATION_PATH . '/controllers/Admin/AdminBase.php';

class Admin_AdminController extends AdminBase
{

    public function indexAction()
    {
    }

    /**
     *  列表
     */
    public function listAction()
    {
        try{
            $Page = $this->_getParam('Page',1);
            $Pagesize = $this->_getParam('Pagesize',100);
            $Name = $this->_getParam('Name',null);
            $AdminID = (int)$this->_getParam("AdminID",0);
            $RoleID = (int)$this->_getParam("RoleID",0);
            $departmentID = intval($this->_getParam('DepartmentID', 0));

            $AdminModle = new Model_Role_Admin();
            $RoleModel = new Model_Role_AdminRoles();

            $Select = $AdminModle->fromSlaveDB()->select();
            if ($Name){
                $Select->where('Username like ?','%'.$Name.'%');
            }
            if (!empty($AdminID)){
                $Select->where("AdminID = ?",$AdminID);
            }
            if (!empty($RoleID)){
                $AdminIDs = (new Model_Role_AdminRoles())->getAdminIDs($RoleID);
                $Select->where("AdminID in (?)",$AdminIDs);
            }
            if($departmentID){
                $Select->where('DepartmentID = ?', $departmentID);
            }
            $admin = (new Model_Role_Admin())->getInfoByID($this->getLoginUserId());
            $CompanyId = empty($admin["CompanyId"])?0:$admin["CompanyId"];
            if($CompanyId > 0){
                $Select->where("CompanyId = ?",$CompanyId);
            }
            $Select->order('AdminID DESC');
            $Res = $AdminModle->getResult($Select,$Page,$Pagesize);
            if($Res['Results']){
                $roles = $RoleModel->getAdminRoles();
                $departArr = [];
                foreach ($Res['Results'] as &$Val){
                    if(!empty($roles[$Val["AdminID"]])){
                        $Val['Roles'] = $roles[$Val["AdminID"]];
                    }else{
                        $Val['Roles'] = [];
                    }
                    if($Val['DepartmentID']){
                        $data = $this->getDepartmentInfo($departArr, $Val['DepartmentID']);
                        $Val['DepartmentName'] = $data['DepartmentName'];
                        $Val['DepartmentParentName'] = $data['DepartmentParentName'];
                        $Val['DepartmentParentID'] = $data['ParentID'];
                    }else{
                        $Val['DepartmentParentName'] = '';
                        $Val['DepartmentName'] = '';
                        $Val['DepartmentParentID'] = 0;
                    }
                }
            }
            $this->showJson(self::STATUS_OK,'',$Res);
        }catch(Exception $e){
            $this->showJson(self::STATUS_FAIL,'抛出异常'.$e->getMessage());
        }
    }

    /**
     * 编辑时的角色信息
     */
    public function saveInfoAction()
    {
        try{
            $AdminID = $this->_getParam('AdminID','');
            if (!$AdminID){
                $this->showJson(self::STATUS_FAIL,'参数不存在');
            }
            $AdminModel = new Model_Role_Admin();
            $AdminRoleModel = new Model_Role_AdminRoles();

            $info = $AdminModel->getInfoByID($AdminID);
            if(!$info){
                $this->showJson(self::STATUS_FAIL,'信息不存在');
            }
            $info['DepartmentParentID'] = 0;
            if(!empty($info['DepartmentID'])){
                $department = Model_Department::getInstance()->fromSlaveDB()->find($info['DepartmentID'])->current();
                if($department){
                    $info['DepartmentParentID'] = $department->ParentID;
                }
            }
            $Roles = $AdminRoleModel->getRoles($AdminID);
            $info["Roles"] = $Roles;
            $this->showJson(self::STATUS_OK,'角色详情',$info);
        }catch(Exception $e){
            $this->showJson(self::STATUS_FAIL,'抛出异常'.$e->getMessage());
        }
    }

    /**
     * 编辑时的角色信息
     */
    public function roleSaveAction()
    {
        $AdminIDs = $this->_getParam('AdminID',[]);
        $RoleIDs = $this->_getParam("RoleIDs",[]);
        if (!is_array($AdminIDs) || count($AdminIDs) <= 0){
            $this->showJson(self::STATUS_FAIL,'请选择管理员');
        }
        if(!is_array($RoleIDs)){
            $RoleIDs = [];
        }
        $AdminRoleModel = new Model_Role_AdminRoles();
        foreach ($AdminIDs as $AdminID) {
            $AdminRoleModel->setRoles($AdminID,$RoleIDs);
        }
        $this->showJson(self::STATUS_OK,'更新成功');
    }
    /**
     * 添加/编辑
     */
    public function saveAction()
    {
        try{
            $AdminID = (int)$this->_getParam('AdminID',0);
            $Username = trim($this->_getParam('Username',''));
            $Password = trim($this->_getParam('Password',''));
            $Status = $this->_getParam('Status','');
            $departmentID = intval($this->_getParam('DepartmentID', 0)); //部门ID
            $RoleIDs = $this->_getParam('RoleIDs',[]);
            if (!$Username){
                $this->showJson(self::STATUS_FAIL,'请填写用户名');
            }
            if (!$Password){
                $this->showJson(self::STATUS_FAIL,'请输入密码');
            }
            $AdminModel = new Model_Role_Admin();
            $admin = (new Model_Role_Admin())->getInfoByID($this->getLoginUserId());
            $CompanyId = empty($admin["CompanyId"])?0:$admin["CompanyId"];
            $Data = [
                'Username' => $Username,

                'Status' => $Status,
                'DepartmentID' => $departmentID,
                'CompanyId' => $CompanyId,
            ];
            $AdminModel->getAdapter()->beginTransaction();

            // 用是否传AdminID信息来判断是添加还是修改
            if ($AdminID > 0){
                if ($Password != '******' && $Password !== '') {
                    $Data['Password'] = md5($Password);
                }
                if ($AdminModel->fetchRow(['AdminID != ?' => $AdminID, 'Username = ?' => $Username, 'CompanyId = ?' => $CompanyId])) {
                    throw new Exception('用户名已存在');
                }
                $AdminModel->update($Data,['AdminID = ?'=>$AdminID]);
            }else{
                if ($Password === '') {
                    throw new Exception('请输入密码');
                }
                if ($AdminModel->fetchRow(['Username = ?' => $Username])) {
                    throw new Exception('用户名已存在');
                }
                $Data['Password'] = md5($Password);
                $AdminID = $AdminModel->insert($Data);
            }
            if($this->isOpenPlatform()){
                if(!is_array($RoleIDs)){
                    $RoleIDs = [];
                }
                $AdminRoleModel = new Model_Role_AdminRoles();
                $AdminRoleModel->setRoles($AdminID,$RoleIDs);
            }
            $AdminModel->getAdapter()->commit();
            $this->showJson(self::STATUS_OK,'操作成功');
        }catch(Exception $e){
            $AdminModel->getAdapter()->rollBack();
            $this->showJson(self::STATUS_FAIL,'抛出异常'.$e->getMessage());
        }
    }

    /**
     * 批量设置部门
     */
    public function setDepartmentAction(){
        try{
            $adminIDs = array_unique(array_filter(explode(',', trim($this->_getParam('AdminIDs', '')))));
            if(empty($adminIDs)){
                $this->showJson(0,'请勾选成员');
            }
            $data['DepartmentID'] = intval($this->_getParam('DepartmentID', 0)); //部门ID
            Model_Role_Admin::getInstance()->fromMasterDB()->update($data, ['AdminID IN (?)' => $adminIDs]);
            $this->showJson(self::STATUS_OK,'操作成功');
        }catch(Exception $e){
            $this->showJson(self::STATUS_FAIL,'抛出异常:'.$e->getMessage());
        }
    }

    /**
     * 删除
     */
    public function delAction()
    {
        $AdminID = $this->_getParam('AdminID','');

        $admin_model = new Model_Role_Admin();

        $res = $admin_model->delete(['AdminID = ?'=>$AdminID]);

        if ($res){
            $this->showJson(self::STATUS_OK,'删除成功');
        }else{
            $this->showJson(self::STATUS_OK,'删除失败');

        }

    }

    /**
     *  详情
     */
    public function infoAction()
    {
        $AdminID = intval($this->_getParam("AdminID",0));
        if ($AdminID == 0){
            $Token = $this->getToken();
            if (empty($Token)){
                $this->showJson(self::STATUS_FAIL,'无法获取Token');
            }
            $Jwt = new DM_Jwt();
            $Jwt->parse($Token);
            $AdminID= $Jwt->token->getClaim("AdminID");
        }
        $model = new Model_Role_Admin();
        $data = $model->getInfoByID($AdminID);
        $data['MenuCodes'] = $model->getMenuCodesByAdminId($AdminID, $data['IsSuper'] == 'Y' ? true : false);
        $this->showJson(self::STATUS_OK, '',$data);
    }

    /**
     *  登录
     */
    public function loginAction()
    {
        $Username = $this->_getParam('Username');
        $Password = md5($this->_getParam("Password"));
        if(empty($Username)){
            $this->showJson(self::STATUS_FAIL,"用户名不能为空");
        }
        if(empty($Password)){
            $this->showJson(self::STATUS_FAIL,"密码不能为空");
        }
        //获取用户名是否存在
        $model = new Model_Role_Admin();
        $Admin = $model->getUsername($Username);
        if($Admin){
            if($Admin['Status']!=1){
                $this->showJson(self::STATUS_FAIL,"账号非法");
            }
            if($Password != $Admin['Password']){
                $this->showJson(self::STATUS_FAIL,"密码错误");
            }
            $MaxLifeTime = $this->_config["resources"]["session"]["gc_maxlifetime"];
            $CookieDomain = $this->_config["resources"]["session"]["cookie_domain"];
            $Expire = intval($MaxLifeTime) + time();
            $Jwt = new DM_Jwt();
            $token = $Jwt->create($Admin['AdminID'], [
                'AdminID'=>$Admin['AdminID'],
                'Username'=>$Admin['Username'],
            ],$Expire);

            if ($this->isOpenPlatform()) {
                if ($Admin['CompanyId'] > 0) {
                    setcookie("Token",(string)$token,$Expire,"/",$CookieDomain);
                    setcookie("AdminID",$Admin['AdminID'],$Expire,"/",$CookieDomain);
                    setcookie("Username",$Admin['Username'],$Expire,"/",$CookieDomain);
                } else {
                    setcookie("Token","",$Expire,"/",$CookieDomain);
                    setcookie("AdminID","",$Expire,"/",$CookieDomain);
                    setcookie("Username","",$Expire,"/",$CookieDomain);
                    $this->showJson(self::STATUS_FAIL, '运营平台只能运营人员登录');
                }
            } else {
                // 群控管理后台
                if ($Admin['CompanyId'] > 0) {
                    setcookie("QkToken","",$Expire,"/",$CookieDomain);
                    setcookie("AdminID","",$Expire,"/",$CookieDomain);
                    setcookie("Username","",$Expire,"/",$CookieDomain);
                    $this->showJson(self::STATUS_FAIL, '群控后台禁止运营人员登录');
                } else {
                    setcookie("QkToken",(string)$token,$Expire,"/",$CookieDomain);
                    setcookie("AdminID",$Admin['AdminID'],$Expire,"/",$CookieDomain);
                    setcookie("Username",$Admin['Username'],$Expire,"/",$CookieDomain);
                }
            }
            $model->update(['LastLoginTime'=>date("Y-m-d H:i:s")],['AdminID = ?'=>$Admin['AdminID']]);
            $this->showJson(self::STATUS_OK,"登录成功",["AdminID"=>$Admin['AdminID'],"Username"=>$Admin['Username'],"Token"=>(string)$token, 'AdminToken' => Helper_OpenEncrypt::encrypt($Admin['AdminID']), 'MenuCodes' => $model->getMenuCodesByAdminId($Admin['AdminID'], $Admin['IsSuper'] == 'Y' ? true : false)]);
        }else{
            $this->showJson(self::STATUS_FAIL,"账号不存在");
        }
    }

    /**
     * 通过运营admin id登录
     */
    public function logYyidAction()
    {
        $adminId = Helper_OpenEncrypt::decrypt($this->_getParam('YyAdminID'));
        if (!$adminId) {
            $this->showJson(self::STATUS_FAIL, '参数非法');
        }
        $model = new Model_Role_Admin();
        $Admin = $model->fromSlaveDB()->getByPrimaryId($adminId);
        if (!$Admin) {
            $this->showJson(self::STATUS_FAIL, '参数非法');
        }
        $MaxLifeTime = $this->_config["resources"]["session"]["gc_maxlifetime"];
        $CookieDomain = $this->_config["resources"]["session"]["cookie_domain"];
        $Expire = intval($MaxLifeTime) + time();
        $Jwt = new DM_Jwt();
        $token = $Jwt->create($Admin['AdminID'], [
            'AdminID'=>$Admin['AdminID'],
            'Username'=>$Admin['Username'],
        ],$Expire);
        if ($Admin['CompanyId'] > 0) {
            setcookie("Token",(string)$token,$Expire,"/",$CookieDomain);
            setcookie("AdminID",$Admin['AdminID'],$Expire,"/",$CookieDomain);
            setcookie("Username",$Admin['Username'],$Expire,"/",$CookieDomain);
        } else {
            setcookie("Token","",$Expire,"/",$CookieDomain);
            setcookie("AdminID","",$Expire,"/",$CookieDomain);
            setcookie("Username","",$Expire,"/",$CookieDomain);
            $this->showJson(self::STATUS_FAIL, '运营平台只能运营人员登录');
        }
        $model->fromMasterDB()->update(['LastLoginTime'=>date("Y-m-d H:i:s")],['AdminID = ?'=>$Admin['AdminID']]);
        $this->showJson(self::STATUS_OK,"登录成功",["AdminID"=>$Admin['AdminID'],"Username"=>$Admin['Username'],"Token"=>(string)$token, 'AdminToken' => Helper_OpenEncrypt::encrypt($Admin['AdminID'])]);
    }

    /**
     *  退出登录
     */
    public function logoutAction(){
        $this->logout('退出成功', true);
    }

    /**
     * @param $departArr
     * @param $departmentID
     * @return array
     * @throws Zend_Db_Table_Exception
     * 查询此部门ID所对应的一级部门名称和二级部门名称
     */
    private function getDepartmentInfo(&$departArr, $departmentID){
        $departModel = Model_Department::getInstance();
        $data = ['DepartmentName' => '', 'DepartmentParentName' => '', 'ParentID' => 0];
        //查出部门Name
        if(isset($departArr[$departmentID])){
            $data['DepartmentName'] = $departArr[$departmentID]['Name'];
        }else{
            $depart = $departModel->fromSlaveDB()->find($departmentID)->current();
            if($depart){
                $data['DepartmentName'] = $depart->Name;
                $data['ParentID'] = $depart->ParentID;
                $departArr[$departmentID] = ['Name' => $depart->Name, 'ParentID' => $depart->ParentID];
            }else{
                $data['DepartmentName'] = '';
                $departArr[$departmentID] = ['Name' => '', 'ParentID' => 0];
            }
        }
        if($departArr[$departmentID]['Name'] === ''){
            //如果部门都查不到
            $data['DepartmentParentName'] = '';
        }else{
            if($departArr[$departmentID]['ParentID']){
                //如果查到部门并且有上级部门ID
                if(isset($departArr[$departArr[$departmentID]['ParentID']])){
                    $data['DepartmentParentName'] = $departArr[$departArr[$departmentID]['ParentID']]['Name'];
                }else{
                    $departParent = $departModel->fromSlaveDB()->find($departArr[$departmentID]['ParentID'])->current();
                    if($departParent){
                        $data['DepartmentParentName'] = $departParent->Name;
                        $departArr[$departParent->DepartmentID] = ['Name' => $departParent->Name, 'ParentID' => $departParent->ParentID];
                    }else{
                        $data['DepartmentParentName'] = '';
                        $departArr[$departParent->DepartmentID] = ['Name' => '', 'ParentID' => 0];
                    }
                }
            }else{
                //如果部门没有上级部门
                $data['DepartmentParentName'] = $data['DepartmentName'];
                $data['DepartmentName'] = '';
            }
        }
        return $data;
    }

}