<?php
/**
 * Created by PhpStorm.
 * User: Ekko
 * Date: 2018/7/30
 * Ekko: 09:10
 */
class Model_Role_Admin extends DM_Model
{
    public static $table_name = "admins";
    protected $_name = "admins";
    protected $_primary = "AdminID";

    public function getInfoByID($AdminID)
    {
        $select = $this->select();
        $select->from($this->_name)
            ->where("AdminID = ?", $AdminID);
        $admin = $this->_db->fetchRow($select);
        if($admin){
            $admin['Password'] = '******';
            $admin['AdminToken'] = Helper_OpenEncrypt::encrypt($admin['AdminID']);
        }
        return $admin;
    }

    public function getUsername($Username)
    {
        $select = $this->select();
        $select->from($this->_name)
            ->where("Username = ?", $Username);
        return $this->_db->fetchRow($select);
    }

    /**
     * 获取所有有效用户
     */
    public function getAdminUserName()
    {
        $select = $this->select();
        $select->from($this->_name,['AdminID','Username'])->where('Status = 1');
        return $this->_db->fetchAll($select);
    }

    /**
     * 带有权限的用户列表
     */
    public function getAdminRoleUserName($AdminID)
    {
        $data = $this->select()->where('AdminID = ?',$AdminID)->query()->fetch();

        $arr = [];
        if($data['IsSuper'] =='Y'){
            $res = $this->select()->where('CompanyId = ?',$data['CompanyId'])->query()->fetchAll();
            foreach ($res as $value){
                $arr[] = $value['AdminID'];
            }
        }
        if(!$arr){
            $arr[] = -1;
        }

        $select = $this->select();
        $select->from($this->_name,['AdminID','Username'])->where('AdminID in (?)',$arr)->where('Status = 1');
        return $this->_db->fetchAll($select);
    }

    /**
     * 获取同部门成员
     */
    public function getDepartmentAdminIds($AdminID)
    {
        $select = $this->select();
        $select->from($this->_name,['DepartmentID','AdminID'])->where('AdminID = ?',$AdminID);
        $res = [];
        $data = $this->_db->fetchRow($select);
        if ($data && $data['DepartmentID']!=0){
            $select = $this->select();
            $select->from($this->_name,['AdminID'])->where('DepartmentID = ?',$data['DepartmentID']);
            $admin = $this->_db->fetchAll($select);
            foreach ($admin as $a){
                $res[] = $a['AdminID'];
            }
        }
        return $res;
    }
    /**
     * 获取同公司成员
     */
    public function getCompanyAdminIds($AdminID)
    {
        $select = $this->select();
        $select->from($this->_name,['CompanyId','AdminID'])->where('AdminID = ?',$AdminID);
        $data = $this->_db->fetchRow($select);
        $res = [];
        if ($data && $data['CompanyId']!=0){
            $select = $this->select();
            $select->from($this->_name,['AdminID'])->where('CompanyId = ?',$data['CompanyId']);
            $admin = $this->_db->fetchAll($select);
            foreach ($admin as $a){
                $res[] = $a['AdminID'];
            }
        }
        return $res;
    }

    public function getDepartmentWx($DepartmentID)
    {
        $select = $this->select()->setIntegrityCheck(false)->from($this->_name." as a","");
        $select->joinLeft("weixins as w","w.AdminID = a.AdminID",["WeixinID","Nickname"]);
        $select->joinLeft("departments as d","d.DepartmentID = a.DepartmentID","");
        $select->where("d.DepartmentID = ? or d.ParentID = ?",intval($DepartmentID));
        return $select->query()->fetchAll();
    }

    /**
     * 获取管理员的 一级部门 仅两级 不支持管理员
     * @param $AdminID
     * @return string
     */
    public function getDependentParentID($AdminID)
    {
        $select = $this->select()->setIntegrityCheck(false)->from($this->_name." as a","");
        $select->joinLeft("departments as d","d.DepartmentID = a.DepartmentID",
            new Zend_Db_Expr("if(d.ParentID > 0,d.ParentID,d.DepartmentID) as DepartmentID"));
        $select->where("a.AdminID = ?",intval($AdminID));
        return $select->query()->fetchColumn();
    }

    /**
     * 根据企业查询部门ID
     *
     * @param $CompanyId
     * @return array
     */
    public function getDepartmentIDs($CompanyId)
    {
        $select = $this->select()->from($this->_name,['DepartmentID']);
        $select->where("CompanyId = ?",$CompanyId);
        $data = $select->query()->fetchAll();

        $res = [];
        foreach ($data as $d){
            $res[] = $d['DepartmentID'];
        }

        return $res;
    }

    /**
     * 获取id和名字的对应关系
     */
    public function getNames(){
        $select = $this->fromSlaveDB()->select()->from($this->_name,["AdminID","Username"]);
        $res = $select->query()->fetchAll();
        $data = [];
        foreach ($res as $r) {
            $data[$r["AdminID"]]= $r["Username"];
        }
        return $data;
    }

    /**
     * 通过管理员id获取菜单
     */
    public function getMenuCodesByAdminId($adminId, $isSuper = false)
    {
        if ($isSuper) {
            $tmpMenuCodes = Model_Role_Menu::getInstance()->fromSlaveDB()->getAdapter()->fetchCol(
                Model_Role_Menu::getInstance()->select()->from('acl_menus', ['Link'])
                    ->where('Link != ?', '')
            );

            return $tmpMenuCodes ? implode(',', $tmpMenuCodes) : '';
        }
        
        $roles = Model_Role_AdminRoles::getInstance()->fromSlaveDB()->fetchAll(['AdminID = ?' => $adminId])->toArray();
        if (!$roles) {
            return '';
        }

        $roleIds = [];
        foreach ($roles as $role) {
            $roleIds[] = $role['RoleID'];
        }
        $aclRoles = Model_Role_Roles::getInstance()->fromSlaveDB()->fetchAll(['RoleID in (?)' => $roleIds])->toArray();
        $tmpMenuIds = [];
        foreach ($aclRoles as $aclRole) {
            $menus = json_decode($aclRole['Menu'], 1);
            if (json_last_error() == JSON_ERROR_NONE) {
                foreach ($menus as $menuId => $menuStatus) {
                    if ($menuStatus == true) {
                        $tmpMenuIds[] = $menuId;
                    }
                }
            }
        }

        if (!$tmpMenuIds) {
            return '';
        }

        $tmpMenuCodes = Model_Role_Menu::getInstance()->fromSlaveDB()->getAdapter()->fetchCol(
            Model_Role_Menu::getInstance()->select()->from('acl_menus', ['Link'])
                ->where('MenuID in (?)', $tmpMenuIds)->where('Link != ?', '')
        );

        return $tmpMenuCodes ? implode(',', $tmpMenuCodes) : '';
    }
}