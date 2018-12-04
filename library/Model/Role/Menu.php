<?php

class Model_Role_Menu extends DM_Model
{
    public static $table_name = "acl_menus";
    protected $_name = "acl_menus";
    protected $_primary = "MenuID";

    public function getMenu($ParentID = -1,$Page = 1,$Pagesize = 1000)
    {
        $select = $this->fromMasterDB()->select()->setIntegrityCheck(false);
        if($ParentID != -1){
            $select->where("ParentID = ?",$ParentID);
        }
        $select->order([new Zend_Db_Expr("concat(Path,'-',MenuID,' Asc')"),"Sort Desc"]);
//            echo $select->__toString();exit;
        $res = $this->getResult($select,$Page,$Pagesize);
        $this->getFiled($res["Results"],"ParentID","acl_menus","MenuID","Name as ParentName");
        foreach ($res["Results"] as &$r) {
            $level = intval(mb_substr_count($r['Path'],"-")) + 1;
            $r["Name"] = implode("----",array_fill(0,$level,"")).$r["Name"];
        }
        return $res;
    }
}