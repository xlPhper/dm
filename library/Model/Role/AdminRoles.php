<?php

class Model_Role_AdminRoles extends DM_Model
{
    public static $table_name = "admin_roles";
    protected $_name = "admin_roles";
    protected $_primary = array("AdminID","RoleID");

    public function setRoles($AdminID,$RoleIDs){
        $this->delete("AdminID = {$AdminID}");
        foreach ($RoleIDs as $RoleID) {
            $this->insert([
                "AdminID"=>$AdminID,
                "RoleID"=>$RoleID
            ]);
        }
    }
    public function getRoles($AdminID){
        $select = $this->select()->setIntegrityCheck(false);
        $select->from($this->_name." as ar","RoleID");
        $select->joinLeft("acl_roles as a","ar.RoleID = a.RoleID","Name as RoleName");
        $select->where("ar.AdminID = ?",$AdminID);
        return $select->query()->fetchAll();
    }
    public function getAdminRoles(){
        $select = $this->select()->setIntegrityCheck(false);
        $select->from($this->_name." as ar",["AdminID","RoleID"]);
        $select->joinLeft("acl_roles as a","ar.RoleID = a.RoleID","Name as RoleName");
        $res = $select->query()->fetchAll();
        $data = [];
        foreach ($res as $r){
            $data[$r["AdminID"]][] = $r;
        }
        return $data;
    }
    public function getAdminIDs($RoleID){
        $res = $this->fetchAll("RoleID = $RoleID");
        $ids = [];
        foreach ($res as $r){
            $ids[] = $r["AdminID"];
        }
        return $ids;
    }
}