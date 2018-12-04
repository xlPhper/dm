<?php

class Model_Role_MenuIdentify extends DM_Model
{
    public static $table_name = "acl_menus_identify";
    protected $_name = "acl_menus_identify";
    protected $_primary = "MenuID";
    public function getIdentify($RoleID){
        $select = $this->select()->setIntegrityCheck(false);
        $select->from($this->_name." as mi",["MenuID","Identify"]);
        $select->where("RoleID = ?",$RoleID);
        $rows = $select->query()->fetchAll();
        $data = [];
        foreach ($rows as $r) {
            $data[$r["MenuID"]][] = $r["Identify"];
        }
        return $data;
    }

    /**
     * 设置权限
     * @param int $RoleID
     * @param array $identify [{MenuID,Identify}]
     */
    public function setIdentify($RoleID,$identify){
        $this->delete("RoleID = $RoleID");
        foreach ($identify as $i) {
            $i["RoleID"] = $RoleID;
            $this->insert($i);
        }
    }
}