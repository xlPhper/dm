<?php
/**
 * @name 权限管理
 */
class Role_PrivilegeController extends DM_ControllerBase
{


    
    /**
     * @name 权限列表
     */
    public function listAction()
    {
    	try{
            $page      = $this->getParam('Page', 1);
            $pagesize  = $this->getParam('Pagesize', 100);
            $describe = trim($this->_getParam('Describe'));
    		$router = trim($this->_getParam('Router'));
    		$platform = $this->_getParam('Platform', null, '');
    		$params = array(
	    			'Describe' => $describe,
	    			'Router' => $router,
	    			'Platform' => $platform
	    	);
    		$AclPrivileges = new Model_Role_Aclp();
            $select = $AclPrivileges->select()->setIntegrityCheck(false);
            $select = $select->from("acl_privileges as p",'');
            if(isset($platform) && $platform != ""){
                $select->where("Platform = '{$params['Platform']}'");
            }
            if (isset($describe) && $describe != "") {
                $description = '%' . $describe . '%';

                $select->where("Describe like '{$description}'");
            }

            if (isset($router) && $router != "") {
                $router = '%' . $router . '%';

                $select->where("Router like '{$router}'");
            }

            $select->order(' PrivilegeID desc ');
            $res = $AclPrivileges->getResult($select, $page, $pagesize);
            $this->showJson(1,'',array('total'=> $res['TotalCount'],'pagesize'=> $res['Pagesize'],'rows'=> $res['Results']));
    	} catch (Exception $e) {
    		$this->showJson(0, '抛出异常：'.$e->getMessage());
    	}
    }
    
    /**
     * @name 添加
     */
    public function insertAction()
    {
    	try{
    		$describe = trim($this->_getParam('describe'));
    		$platform = $this->_getParam('platform', null, ''); //增加前端和后端接口的权限标识，FRONT前端，ADMIN后端
    		if($describe == ''){
    			$this->showJson(0,'描述不能为空');
    		}
    		$router = trim($this->_getParam('router')); //路由
    		if($router == ""){
    			$this->showJson(0,'路由标识不能为空');
    		}
    		if($platform == "" || !in_array($platform, array('ADMIN', 'FRONT'))){
    			$this->showJson(0,'请选择模块标识');
    		}
            $AclPrivileges = new Model_Role_Aclp();
            if($AclPrivileges->checkHasExits($router, $platform)){
    			$this->showJson(0,'已存在对应的标识,请使用其他标识!');
    		}

            $AclPrivileges->insert(array(
    				'Describe'=>$describe,
    				'Platform'=>$platform,
    				'Router'=>$router
    		));
    		$this->showJson(1,"添加成功");
    	} catch (Exception $e) {
    		$this->showJson(0, '抛出异常：'.$e->getMessage());
    	}
    }

    /**
     * @name 删除
     */
    public function removeAction()
    {
    	try{
    		$privilege_id = intval($this->_getParam('privilege_id'));
    		if(empty($privilege_id)){
    			$this->showJson(0,'参数id错误');
    		}
    		//判断该权限是否已经分配给对应的角色
            $AclRolep = new Model_Role_Aclrolep();
    		if($AclRolep->findPriID($privilege_id)){
    			$this->showJson(0,'该权限已经被分配了,暂时不能删除！');
    		}
            $AclPrivileges = new Model_Role_Aclp();
            $AclPrivileges->delete(['PrivilegeID = ?'=>$privilege_id]);
    		$this->showJson(1,"删除成功");
    	} catch (Exception $e) {
    		$this->showJson(0, '抛出异常：'.$e->getMessage());
    	}
    }
    
    /**
     * @name 编辑获取数据
     */
    public function editAction()
    {
    	$privilege_id = intval($this->_getParam('privilege_id'));
    	$Aclp = new Model_Role_Aclp();
    	$privilege = $Aclp->findByID($privilege_id);
    	if($privilege) {
    		$this->showJson(1,'',$privilege);
    	}else{
    		$this->showJson(0,"不存在的权限");
    	}
    }
    /**
     * @name 编辑
     */
    public function updateAction()
    {
    	try{
    		$privilege_id = intval($this->_getParam('privilege_id'));
    		$describe = trim($this->_getParam('describe'));
    		if($describe == ''){
    			$this->showJson(0,'描述不能为空');
    		}
    		
    		$router = trim($this->_getParam('router'));
    		if($router == ""){
    			$this->showJson(0,'路由标识不能为空');
    		}
    		$Aclp = new Model_Role_Aclp();
    		$privilege = $Aclp->findByID($privilege_id);
            if($Aclp->checkHasExits($router, $privilege['Platform'],$privilege_id)){
                $this->showJson(0,'已存在对应的标识,请使用其他标识!');
            }
            $Aclp->update(array('Describe'=>$describe,'Router'=>$router),array('PrivilegeID =?'=>$privilege_id));
    		$this->showJson(1,"更新成功");
    	} catch (Exception $e) {
    		$this->showJson(0, '抛出异常：'.$e->getMessage());
    	}
    }
}

