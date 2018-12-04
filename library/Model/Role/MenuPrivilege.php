<?php

class Model_Role_MenuPrivilege extends DM_Model
{
    public static $table_name = "acl_menus_privileges";
    protected $_name = "acl_menus_privileges";
    protected $_primary = "PrivilegeID";

    public function getIdentify($MenuID = 0){
        $select = $this->fromMasterDB()->select()->setIntegrityCheck(false);
        $select->from($this->_name." as mp",["MenuID","Identify"]);
        $select->joinLeft("categories as c","c.CategoryID = mp.Identify","ifnull(c.Name,'') as IdentifyName");
        if($MenuID > 0){
            $select->where("mp.MenuID = ?",$MenuID);
        }
        $select->group(["mp.MenuID","mp.Identify"]);
        $res = $select->query()->fetchAll();
        $data = [];
        foreach ($res as $r) {
            $data[$r["MenuID"]][] = $r;
        }
        return $data;
    }
}