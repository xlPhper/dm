<?php

class Role_RoleController extends DM_ControllerBase
{


    /**
     * @name 列表
     */
    public function listAction()
    {
    	try{
            $page      = $this->getParam('Page', 1);
            $pagesize  = $this->getParam('Pagesize', 100);
            $aclrs_model = new Model_Role_Aclroles();
            $select    = $aclrs_model->select();
            $select->where("Platform = 'ADMIN'");
            $select->order('RoleID Desc');
            $res = $aclrs_model->getResult($select, $page, $pagesize);
    		$data = array(
    				"pagesize" => $res['Pagesize'],
    				"total" => $res['TotalCount'],
    				"rows" => $res['Results']
    		);
    		$this->showJson(true,'' ,$data);
    	} catch (Exception $e) {
    		$this->showJson(0, "抛出异常：". $e->getMessage());
    	}
    	
    }
    
    /**
     * @name 添加获取数据
     */
    public function addAction()
    {
    	try{
    	    $Aclp = new Model_Role_Aclp();
            $privilegeList = $Aclp->getPrivilegeList();
    		$this->showJson(1,'',['list'=>$privilegeList]);
    	} catch (Exception $e) {
    		$this->showJson(1, $e->getMessage(),['list'=>array()]);
    	}
    }
    
    /**
     * @name 添加
     */
    public function insertAction(){
    	$name = trim($this->_getParam('name', null, ''));
    	if($name == ''){
    		$this->showJson(0,'名称不能为空！');
    	}
    		
    	try {
    		$roleModel = new Model_Role_Aclroles();
//    		$roleModel->getAdapter()->beginTransaction();
            $RoleID = $roleModel->add(array("Name" => $name, "Platform" => 'ADMIN'));
//            lastInsertId
    		//分配权限
            $aclRoles = new Model_Role_Aclp();
    		$allowCheckArray = $this->_getParam('allowCheck', null, array());
    		if(!empty($allowCheckArray)){
    			foreach($allowCheckArray as $key=>$val){
                    $aclRoles->grandRolePrivileges($roleModel->RoleID, $val, 'ALLOW');
    			}
    		}
    			
    		$denyCheckArray = $this->_getParam('denyCheck', null, array());
    		if(!empty($denyCheckArray)){
    			foreach($denyCheckArray as $k=>$v){
                    $aclRoles->grandRolePrivileges($roleModel->RoleID,$v,'DENY');
    			}
    		}
    		$this->db->commit();
    		$this->showJson(1,"添加成功");
    	} catch (Exception $e) {
    		$this->db->rollback();
    		$this->showJson(0, "抛出异常：". $e->getMessage());
    	}
    }
    
    /**
     * @name 删除角色
     */
    public function removeAction()
    {
    	$role_id = intval($this->request->get('rule_id'));
    	if(empty($role_id)){
    		$this->showJson(1,"删除成功");
    	}
    	try {
    		//判断角色是否已经被分配
    		if(AclRoles::isGrantToUser($role_id)){
    			$this->showJson(0,'该角色已经被分配了,暂时不能删除');
    		}
    		$this->db->begin();
    		//删除角色 及 角色与权限的对应关系
    		AclRoles::deleteRoleByID($role_id);
    		$this->db->commit();
    		$this->showJson(1,"删除成功");
    	} catch (Exception $e) {
    		$this->db->rollback();
    		$this->showJson(0, "抛出异常：". $e->getMessage());
    	}
    }
    
    /**
     * @name 编辑角色获取权限列表
     */
    public function editAction()
    {
    	$role_id = intval($this->request->get('role_id'));
    	if(empty($role_id)){
    		$this->showJson(0,'role_id不存在');
    	}
    	$privilegesList = AclRoles::getRolePrivilegesList($role_id);
    	$this->showJson(1,'',['list'=>$privilegesList]);
    }
    
    /**
     * @name 更新
     */
    public function updateAction()
    {
    	$role_id = intval($this->request->get('role_id'));
    	$name = trim($this->request->get('name'));
    	if($name == ''){
    		$this->showJson(0,'名称不能为空!');
    	}
    	$role = AclRoles::findFirst($role_id);
    	if(empty($role)){
    		$this->showJson(0,'角色信息不存在!');
    	}
    	try {
    		$role->save(array("Name" => $name));
    		//分配权限
    		$allowCheckArray = $this->request->get('allowCheck', null, array());
    		$denyCheckArray = $this->request->get('denyCheck', null, array());
    		$willNotDeleteKeys = array_unique(array_merge($allowCheckArray,$denyCheckArray));
    		
    		//先移除多余权限
    		AclRoles::stripRolePrivileges($role_id,$willNotDeleteKeys);
    		
    		if(!empty($allowCheckArray)){
    			foreach($allowCheckArray as $key=>$val){
    				AclRoles::grandRolePrivileges($role_id, $val, AclRoles::STATUS_ALLOW);
    			}
    		}
    		
    		if(!empty($denyCheckArray)){
    			foreach($denyCheckArray as $k=>$v){
    				AclRoles::grandRolePrivileges($role_id,$v,AclRoles::STATUS_DENY);
    			}
    		}
    		$this->db->commit();
    		$this->showJson(1,"更新成功");
    	} catch (Exception $e) {
    		$this->db->rollback();
    		$this->showJson(0, "抛出异常：". $e->getMessage());
    	}
    }
}

