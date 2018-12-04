<?php


class DM_ControllerBase extends Zend_Controller_Action
{
    protected $_config = null;

    public function init()
    {
        parent::init();
        $this->_config = Zend_Registry::get("config");
    }


    public static function returnJson($flag, $msg = '', $data = null, $ext = null)
    {
        $flag = $flag === true ?1:$flag;
        $d = array('f' => $flag,'m'=>$msg ,'d' => $data,'e'=>$ext);
        return json_encode($d);

    }

    protected function showJson($flag, $msg = '', $data = null, $ext = null)
    {
        $d = self::returnJson($flag, $msg, $data, $ext);
        $jsonp = $this->_getParam("callback");
        if(empty($jsonp)) {
            echo $d;exit;
        }else{
            echo $jsonp . "(" . $d . ")";exit;
        }
    }

	/**
	 * 初始化
	 */
	public function initialize() {
	    $paramAll = $this->getAllParams();
		$controllerName = $paramAll['controller'];
		$actionName = $paramAll['Action'];
		$notCheckArray = array('admin/login', 'admin/logout');
		if (!in_array("{$controllerName}/{$actionName}", $notCheckArray)) {
			$this->isLogin();
			$appName = 'admin'; //后台管理模块
			if($controllerName == 'index' && $actionName == 'gateway'){
				$appName = $this->dispatcher->getParam('appName'); // 小程序模块
				$controllerName = $this->dispatcher->getParam('appController');
				$actionName = $this->dispatcher->getParam('appAction');
			}
			if (!$this->checkPrivilege($appName,$controllerName,$actionName)) {
				$this->showJson(0, '您无权操作');
			}
		}
	}
	
	protected function checkPrivilege($appName,$controllerName,$actionName)
	{
		$rolesArray = $this->session->get('Roles');
		if(empty($rolesArray) || empty($appName) || empty($controllerName) || empty($actionName)){
			return false;
		}else{
			// 管理员默认拥有所有权限
			if(in_array(1, $rolesArray)){
				return true;
			}
			// 根据角色获取权限列表
            // $privileges = AclRoles::getRolePrivliegesByRoleIDs($rolesArray);
			return true;
		}
	
	}

	
	/**
	 * 判断是否登录
	 */
	protected function isLogin()
	{
		if(empty($_COOKIE['AdminID'])){
			$this->logout("请登录");
		}
        $token = $this->_getParam('Token',0);
        if(!$token && empty($_COOKIE['Token'])){
            $this->logout("请登录");
        }elseif (!$token){
            $token = $_COOKIE['Token'];
        }
        $Jwt = new DM_Jwt();
        $Jwt->parse($token);
        $Time = $Jwt->token->getClaim("exp");
        $MaxLifeTime = $this->_config["resources"]["session"]["gc_maxlifetime"];
        if ($Time<time()+$MaxLifeTime){
            $this->logout("请重新登录");
        }
		$Admins = new Model_Role_Admin();
		$loginAdmin = $Admins->getInfoByID($_COOKIE['AdminID']);
		if ($loginAdmin && $loginAdmin['Status']){
			return true;
		}
		$this->logout("用户禁用");
	}
	
	/**
	 * @name 退出登录
	 */
	public function logout($msg = "退出成功"){
		$CookieDomain = $this->_config["resources"]["session"]["cookie_domain"];
		$Expire = time() - 1209600;
		setcookie("AdminID","",$Expire,"/",$CookieDomain);
		setcookie("Username","",$Expire,"/",$CookieDomain);
		setcookie("RoleID","",$Expire,"/",$CookieDomain);
		$this->showJson(-100,$msg);
	}

    
    protected function isAdmin()
    {
    	$AdminID = $this->getAdminID();
    	$AdminIDs = AclRoles::getAdminIDs();
    	if(in_array($AdminID,$AdminIDs)){
    		return true;
    	}
    	return false;
    }
    
    protected function getAdminID()
    {
    	return empty($this->session->AdminID)?0:$this->session->AdminID;
    }
    

    
    /**
     * 记录日志
     */
    public function log($msg , $priority=\Phalcon\Logger::ERROR){
        $this->logger->log($msg, $priority);
    }
    
    
}
