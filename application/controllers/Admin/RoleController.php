<?php

require_once APPLICATION_PATH . '/controllers/Admin/AdminBase.php';

class Admin_RoleController extends AdminBase
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
            $AdminID = (int)$this->_getParam("AdminID",0);
            $model = new Model_Role_Roles();
            $select = $model->fromMasterDB()->select()->setIntegrityCheck(false);
            $select->from($model->getTableName()." as r");
            $select->columns(new Zend_Db_Expr(
                "(select count(*) as AdminNum from admin_roles where RoleID = r.RoleID) as AdminNum"));
            $select->order('RoleID DESC');
            if (!empty($AdminID)){
                $select->where("r.AdminID = ?",$AdminID);
            }
            $CompanyId = $this->admin["CompanyId"];
            $select->where("CompanyId = ?",$CompanyId);
//            echo $select->__toString();exit;
            $Res = $model->getResult($select,$Page,$Pagesize);
            $model->getFiled($Res["Results"],"AdminID","admins","Username","AdminName");
            $this->showJson(self::STATUS_OK,'',$Res);
        }catch(Exception $e){
            $this->showJson(self::STATUS_FAIL,''.$e->getMessage());
        }
    }

    /**
     * 详情
     */
    public function infoAction()
    {
        $RoleID = (int)$this->_getParam('RoleID',0);
        $model = new Model_Role_Roles();
        $row = $model->fetchRow("RoleID = {$RoleID}");
        if(!$row){
            $this->showJson(0,"not find");
        }
        //获取菜单权限 和 子项
        $this->showJson(self::STATUS_OK,'',$row->toArray());
    }

    /**
     * 角色 添加/修改
     */
    public function addAction()
    {
        $RoleID = (int)$this->_getParam('RoleID',0);
        $Name = $this->_getParam('Name','');
        $IdentifyJson = trim($this->_getParam("Identify"));
        $model = new Model_Role_Roles();
        $json = json_decode($IdentifyJson,true);
        $menu = [];
        $identify = [];
        foreach ($json as $j){
            $MenuID = $j["MenuID"];
            $menu[$MenuID] = empty($j["Check"])?false:true;
            if(empty($j["List"]) || !is_array($j["List"])) {
                continue;
            }
            foreach ($j["List"] as $m) {
                if($m["Check"]){
                    $identify[] = [
                        "MenuID"=> $m["MenuID"],
                        "Identify"=> $m["Identify"]
                    ];
                }
            }
        }
        $data = [
            "Name"    => $Name,
            "Menu"    => json_encode($menu),
            "AdminID" => $this->getLoginUserId(),
            "CompanyId" => $this->admin["CompanyId"],
        ];

        $db = $model->getAdapter();
        try {
            $db->beginTransaction();
            if ($RoleID){
                $model->update($data,['RoleID = ?'=>$RoleID]);
            }else{
                $RoleID = $model->insert($data);
            }
            (new Model_Role_MenuIdentify())->setIdentify($RoleID,$identify);

            $db->commit();
            $this->showJson(1,"更新成功");
        } catch (Exception $e) {
            $db->rollBack();
            $this->showJson(0,"更新失败：".$e->getMessage());
        }
        $this->showJson(self::STATUS_OK,'保存成功');

    }

    /**
     * 删除角色
     */
    public function deleteAction()
    {
        $this->showJson(0, "禁止删除");
        try {
            $RoleID = (int)$this->_getParam('RoleID', 0);
            $model  = new Model_Role_Roles();
            $model->delete(['RoleID = ?' => $RoleID]);
            $this->showJson(self::STATUS_OK, '');
        } catch (Exception $e) {
            $this->showJson(self::STATUS_FAIL, '' . $e->getMessage());
        }
    }
    /**
     * 获取权限
     */
    public function getIdentifyAction()
    {
        $RoleID = (int)$this->_getParam("RoleID",0);
        $roleModel = new Model_Role_Roles();
        $role = $roleModel->fetchRow("RoleID = {$RoleID}");
        if(!$role){
            $role["Menu"] = "";
        }
        //存储menu id > check
        $checkMenu = json_decode($role["Menu"],true);
        $model = new Model_Role_MenuPrivilege();
        $data = $model->getIdentify();
        $checkIdentify = (new Model_Role_MenuIdentify())->getIdentify($RoleID);
        foreach ($data as $MenuID=>$item){
            foreach ($item as &$d) {
                $d["Check"] = false;
                $ci = empty($checkIdentify[$d["MenuID"]])?[]:$checkIdentify[$d["MenuID"]];
                if(in_array($d["Identify"],$ci)){
                    $d["Check"] = true;
                }
            }
            $data[$MenuID] = $item;
        }
        $res = (new Model_Role_Menu())->getMenu();
        $menus = $res["Results"];
        foreach ($menus as &$menu) {
            $MenuID = $menu["MenuID"];
            if(!empty($data[$MenuID])){
                $menu["List"] = $data[$MenuID];
            }else{
                $menu["List"] = [];
            }
            $menu["Check"] = empty($checkMenu[$MenuID])||!$checkMenu[$MenuID]?false:true;
        }
        $this->showJson(1,"",$menus);
    }
}