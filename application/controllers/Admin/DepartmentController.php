<?php
/**
 * Created by PhpStorm.
 * User: jakins
 * Date: 2018/11/1
 * Time: 14:13
 */
require_once APPLICATION_PATH . '/controllers/Admin/AdminBase.php';

class Admin_DepartmentController extends AdminBase
{
    public function listAction(){
        try {
            $page = $this->_getParam('Page', 1);
            $pagesize = $this->_getParam('Pagesize', 100);

            $parentID = intval($this->_getParam("ParentID", 0)); //上级部门ID
            $adminID = intval($this->_getParam("AdminID", 0)); //AdminID
            $name = trim($this->_getParam("Name", '')); //部门名称

            $model = Model_Department::getInstance();
            $select = $model->fromSlaveDB()->select()->from($model->getTableName(), ['DepartmentID', 'Name', 'ParentID', 'AdminID', new Zend_Db_Expr('IF(ParentID, CONCAT(ParentID, "-", DepartmentID), CONCAT(DepartmentID, "-0")) as SortField')]);
            if (!empty($parentID)){
                $select->where("ParentID = ? or DepartmentID = ?", $parentID);
            }
            if (!empty($adminID)){
                $select->where("AdminID = ?", $adminID);
            }
            if ($name !== ''){
                $select->where("Name like ?", '%'.$name.'%');
            }
            $admin = (new Model_Role_Admin())->getInfoByID($this->getLoginUserId());
            $CompanyId = empty($admin["CompanyId"])?-1:$admin["CompanyId"];
            $select->where("CompanyId = ?",$CompanyId);

            $select->order('SortField Asc');
            $res = $model->getResult($select, $page, $pagesize);
            if($res['Results']){
                $depart_model = new Model_Department();
                $model->getFiled($res['Results'], "ParentID","departments" ,"Name","ParentName",'DepartmentID' );
                $model->getFiled($res['Results'], "AdminID","admins" ,"Username","AdminName",'AdminID' );
                foreach ($res['Results'] as &$row){
                    $row['StaffNum'] = $depart_model->getAdminNum($row['DepartmentID']);
                    if(!$row['ParentID']){
                        $row['ParentName'] = $row['Name'];
                        $row['Name'] = '';
                    }
                }
            }

            $this->showJson(1, '部门列表', $res);
        }catch (Exception $e){
            $this->showJson(0, '抛出异常:'.$e->getMessage());
        }
    }

    /*
     * 获取全部部门层级数据
     */
    public function listallAction(){
        try {
            $type = intval($this->_getParam('Type', 0)); //0则返回全部,1返回一级部门
            $getChild = intval($this->_getParam('GetChild', 0)); //0则不获取child,1则返回child
            if($type == 1){
                $parentID = 0;
            }else{
                $parentID = '';
            }
            $admin = (new Model_Role_Admin())->getInfoByID($this->getLoginUserId());
            $CompanyId = empty($admin["CompanyId"])?-1:$admin["CompanyId"];
            $this->showJson(1, '部门层级数据', (new Model_Department())->getAllList($CompanyId, $parentID, $getChild));
        }catch (Exception $e){
            $this->showJson(0, '抛出异常:'.$e->getMessage());
        }
    }

    /**
     * 添加/编辑
     */
    public function editAction(){
        try {
            $parentID = intval($this->_getParam("ParentID", 0)); //上级部门ID
            $name = trim($this->_getParam("Name", ''));
            $departmentID = intval($this->_getParam("DepartmentID", 0)); //部门ID
            if($name == ''){
                $this->showJson(0, '部门名称不能为空');
            }

            $model = new Model_Department();
            if(!empty($departmentID)){
                $department = $model->find($departmentID)->current();
                if(!$department){
                    $this->showJson(0, '部门不存在,ID:'.$departmentID);
                }
                if(!$department->ParentID && $parentID){
                    $this->showJson(0, '一级部门无法归属到其他部门下');
                }
            }else{
                $department = $model->createRow();
            }
            $admin = (new Model_Role_Admin())->getInfoByID($this->getLoginUserId());
            $department->CompanyId = empty($admin["CompanyId"])?0:$admin["CompanyId"];
            $department->ParentID = $parentID;
            $department->Name = $name;
            $department->AdminID = $this->getLoginUserId();
            $department->save();

            $this->showJson(1, '操作成功');
        }catch (Exception $e){
            $this->showJson(0, '抛出异常:'.$e->getMessage());
        }
    }

    /**
     * 删除部门（批量）
     */
    public function delAction(){
        try {
            $ids = trim($this->_getParam('DepartmentIDs', ''));
            $idArr = array_unique(array_filter(explode(',', $ids)));
            if(empty($idArr)){
                $this->showJson(0, '请选择要删除的部门');
            }
            $adminModel = Model_Role_Admin::getInstance();
            $departModel = Model_Department::getInstance();
            $parentDepart = $departModel->fromSlaveDB()->select()->where('DepartmentID IN (?)', $idArr)->where('ParentID = 0')->limit(1)->query()->fetch();
            if($parentDepart){
                $this->showJson(0, '勾选的部门中存在一级部门,无法删除');
            }
            $adminDepart = $adminModel->fromSlaveDB()->select()->where('DepartmentID IN (?)', $idArr)->limit(1)->query()->fetch();
            if($adminDepart){
                $this->showJson(0, '勾选的部门下有人员存在,无法删除');
            }
            $departModel->fromMasterDB()->delete(['DepartmentID IN (?)' => $idArr, 'ParentID != ?' => 0]);
            $this->showJson(1, '操作成功');
        }catch (Exception $e){
            $this->showJson(0, '抛出异常:'.$e->getMessage());
        }
    }
}