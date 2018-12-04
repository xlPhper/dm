<?php

require_once APPLICATION_PATH . '/controllers/Admin/AdminBase.php';

class Admin_MenuController extends AdminBase
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
            $ParentID = (int)$this->_getParam("ParentID",-1);
            $model = new Model_Role_Menu();
            $res = $model->getMenu($ParentID,$Page,$Pagesize);
            $this->showJson(self::STATUS_OK,'',$res);
        }catch(Exception $e){
            $this->showJson(self::STATUS_FAIL,''.$e->getMessage());
        }
    }

    /**
     * 详情
     */
    public function infoAction()
    {
        $MenuID = (int)$this->_getParam('MenuID',0);
        $model = new Model_Role_Menu();
        $row = $model->fetchRow("MenuID = {$MenuID}");
        if(!$row){
            $this->showJson(0,"not find");
        }
        $this->showJson(self::STATUS_OK,'',$row->toArray());
    }

    /**
     * 菜单 添加/修改
     */
    public function addAction()
    {
        try{
            $MenuID = (int)$this->_getParam('MenuID',0);
            $Name = $this->_getParam('Name','');
            $ParentID = (int)$this->_getParam("ParentID",0);
            $IsDisplay = (int)$this->_getParam("IsDisplay",1);
            $Link = trim($this->_getParam("Link"));
            $Sort = (int)$this->_getParam("Sort",0);
            if(empty($Name)){
                $this->showJson(0,"名称不能为空");
            }
            $model = new Model_Role_Menu();
            $Path  = 0;
            if($ParentID > 0){
                $Parent = $model->getByPrimaryId($ParentID);
                if($Parent){
                    $Path  = $Parent['Path']."-".$Parent['MenuID'];
                }else{
                    $this->showJson(0,'父分类不存在');
                }
            }
            $data = [
                "Name"      => $Name,
                "Path"      => $Path,
                "ParentID"  => $ParentID,
                "IsDisplay" => $IsDisplay,
                "Link"      => $Link,
                "Sort"      => $Sort,
            ];
            if ($MenuID){
                $model->update($data,['MenuID = ?'=>$MenuID]);
            }else{
                $model->insert($data);
            }
            $this->showJson(self::STATUS_OK,'保存成功');
        }catch(Exception $e){
            $this->showJson(self::STATUS_FAIL,'保存失败'.$e->getMessage());
        }
    }


    /**
     * 删除菜单
     */
    public function deleteAction()
    {
        $this->showJson(0, "禁止删除");
        try {
            $MenuID = (int)$this->_getParam('MenuID', 0);
            $model  = new Model_Role_Menu();
            $model->delete(['MenuID = ?' => $MenuID]);
            $this->showJson(self::STATUS_OK, '');
        } catch (Exception $e) {
            $this->showJson(self::STATUS_FAIL, '' . $e->getMessage());
        }
    }

    /**
     *  列表
     */
    public function privilegeListAction()
    {
        try{
            $Page = $this->_getParam('Page',1);
            $Pagesize = $this->_getParam('Pagesize',100);
            $MenuID = (int)$this->_getParam('MenuID',0);
            $model = new Model_Role_MenuPrivilege();
            $select = $model->fromMasterDB()->select()->setIntegrityCheck(false);
            if($MenuID > 0){
                $select->where("MenuID = ?",$MenuID);
            }
            $select->order(["MenuID asc","Sort asc"]);
            $Res = $model->getResult($select,$Page,$Pagesize);
            $model->getFiled($Res["Results"],"MenuID","acl_menus","Name","MenuName");
            $model->getFiled($Res["Results"],"Identify","categories","Name","IdentifyName","CategoryID");
            $this->showJson(self::STATUS_OK,'',$Res);
        }catch(Exception $e){
            $this->showJson(self::STATUS_FAIL,''.$e->getMessage());
        }
    }

    /**
     * 详情
     */
    public function privilegeInfoAction()
    {

        $PrivilegeID = (int)$this->_getParam('PrivilegeID',0);
        $model = new Model_Role_MenuPrivilege();
        $row = $model->fetchRow("PrivilegeID = {$PrivilegeID}");
        if(!$row){
            $this->showJson(0,"not find");
        }
        $this->showJson(self::STATUS_OK,'',$row->toArray());
    }

    /**
     * 菜单 添加/修改
     */
    public function privilegeAddAction()
    {
        try{
            $PrivilegeID = (int)$this->_getParam('PrivilegeID',0);
            $MenuID = (int)$this->_getParam('MenuID',0);
            $Name = $this->_getParam('Name','');
            $Identify = (int)$this->_getParam("Identify",0);
            $Controller = trim($this->_getParam("Controller"));
            $Action = trim($this->_getParam("Action"));
            $Sort = (int)$this->_getParam("Sort",0);
            if(empty($Name)){
                $this->showJson(0,"名称不能为空");
            }
            if(empty($Identify)){
                $this->showJson(0,"标识不能为空");
            }
            $model = new Model_Role_MenuPrivilege();
            $data = [
                "Identify"   => $Identify,
                "Name"       => $Name,
                "MenuID"     => $MenuID,
                "Controller" => $Controller,
                "Action"     => $Action,
                "Sort"       => $Sort,
            ];
            if ($PrivilegeID){
                $model->update($data,['PrivilegeID = ?'=>$PrivilegeID]);
            }else{
                $model->insert($data);
            }
            $this->showJson(self::STATUS_OK,'保存成功');
        }catch(Exception $e){
            $this->showJson(self::STATUS_FAIL,'保存失败'.$e->getMessage());
        }
    }

    /**
     * 删除菜单
     */
    public function privilegeDeleteAction()
    {
        $this->showJson(0, "禁止删除");
        try {
            $PrivilegeID = (int)$this->_getParam('PrivilegeID',0);
            $model  = new Model_Role_MenuPrivilege();
            $model->delete(['PrivilegeID = ?' => $PrivilegeID]);
            $this->showJson(self::STATUS_OK, '');
        } catch (Exception $e) {
            $this->showJson(self::STATUS_FAIL, '' . $e->getMessage());
        }
    }
}