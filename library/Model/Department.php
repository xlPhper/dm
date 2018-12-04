<?php
/**
 * Created by PhpStorm.
 * User: jakins
 * Date: 2018/11/1
 * Time: 14:21
 * 部门表
 */
class Model_Department extends DM_Model
{
    public static $table_name = "departments";
    protected $_name = "departments";
    protected $_primary = "DepartmentID";

    /**
     * @param string $parentID ''则返回所有部门,0则返回1级,其余返回具体某个部门的下级
     * @param int $getChild 1则返回下级部门数据
     * @return array
     * 获取所有部门数据
     */
    public function getAllList($CompanyId,$parentID = '', $getChild = 0){
        $select = $this->select();
        if($parentID !== ''){
            $select->where('ParentID = ?', $parentID);
        }
        $select->where("CompanyId = ?",$CompanyId);
        $res = $select->query()->fetchAll();
        if($getChild){
            foreach ($res as $k=>$row){
                $child = $this->getAllList($CompanyId,$row['DepartmentID'], 0);
                if(empty($child)){
                    $res[$k]['Child'] = [];
                }else{
                    $res[$k]['Child'] = $child;
                }
            }
        }
        return $res;
    }

    /**
     * @param $departmentID
     * @return int
     * 查询在此部门下的人员数
     */
    public function getAdminNum($departmentID){
        $admin_model = Model_Role_Admin::getInstance();
        return $admin_model->fromSlaveDB()->select()->where('DepartmentID = ?', $departmentID)->query()->rowCount();
    }
    public function getParentID($CompanyId){
        $select = $this->select()->from($this->_name,["DepartmentID"]);
        $select->where('ParentID = 0');
        $select->where("CompanyId = ?",$CompanyId);
        $res = $select->query()->fetchAll();
        return array_column($res,"DepartmentID");
    }
}