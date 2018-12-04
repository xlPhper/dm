<?php

class AdminBase extends DM_Controller
{
    const STATUS_OK = 1;
    const STATUS_FAIL = 0;
    const STATUS_NEED_LOGIN = -100;

    protected $adminWxIds = [];
    protected $admin = [];
    public function init()
    {
        parent::init();

        // 安全访问
        if (!$this->isSafeAccess()) {
//            $this->showJson(0, '无权访问');
        }
        $paramAll = $this->getAllParams();
        $controllerName = $paramAll['controller'];
        $actionName = $paramAll['action'];
        $notCheckArray = array('admin_admin/login', 'admin_admin/logout');
        if (!in_array("{$controllerName}/{$actionName}", $notCheckArray)) {
            $this->isLogin();
        }
        if ($this->isOpenPlatform()) {
//            $this->initAdminWxIds();
        }
    }

    /**
     * 初始化管理员WxIds
     */
    private function initAdminWxIds()
    {
        $this->adminWxIds = Model_Weixin::getInstance()->getWxIdsByAdminId($this->getLoginUserId());
    }

    /**
     * 是否为对外开放平台
     */
    protected function isOpenPlatform()
    {
        return trim($this->_getParam('platform')) === 'open';
    }

    protected function isGroupPlatform()
    {
        return trim($this->_getParam('platform')) === 'group';
    }

    public function isSafeAccess()
    {
        $salt = 'XI7^[zi[M+!*kFs+';
        $s = trim($this->_getParam('s', ''));
        $callback = trim($this->_getParam('callback', ''));
        $t = trim($this->_getParam('t', ''));
        return md5($callback . $t . $salt) == $s;
    }

    protected function isLogin()
    {
        $token = $this->getToken();
        $Jwt = new DM_Jwt();
        $Jwt->parse($token);
        $Time = $Jwt->token->getClaim("exp");
        $AdminID = $Jwt->token->getClaim("AdminID");
        if ($Time<time()){
            $this->logout("请重新登录");
        }
        $adminModel = new Model_Role_Admin();
        $admin = $adminModel->getInfoByID($AdminID);
        $this->admin = $admin;
        if(!$admin){
            $this->logout("账号不存在");
        }
        if ($admin['Status']){
            return true;
        }
        $this->logout("用户禁用");
        return false;
    }

    /**
     * @name 退出登录
     */
    public function logout($msg = "退出成功", $isActive = false){
        $CookieDomain = $this->_config["resources"]["session"]["cookie_domain"];
        $MaxLifeTime = $this->_config["resources"]["session"]["gc_maxlifetime"];
        $Expire = time() - $MaxLifeTime;
        setcookie("AdminID","",$Expire,"/",$CookieDomain);
        setcookie("Username","",$Expire,"/",$CookieDomain);
        if ($this->isOpenPlatform()) {
            setcookie("Token",'',$Expire,"/",$CookieDomain);
        } else {
            // 群控管理后台
            setcookie("QkToken",'',$Expire,"/",$CookieDomain);
        }
        if ($isActive) {
            $this->showJson(self::STATUS_OK, $msg);
        } else {
            $this->showJson(self::STATUS_NEED_LOGIN, $msg);
        }
    }

    /**
     * 获取当前登录用户
     */
    public function getLoginUserId()
    {
        $token = $this->getToken();
        $Jwt = new DM_Jwt();
        $Jwt->parse($token);
        $AdminID= (int)$Jwt->token->getClaim("AdminID");

        return $AdminID;
    }

    protected function getToken()
    {
        if ($this->isOpenPlatform()) {
            $token = isset($_SERVER['HTTP_TOKEN']) ? trim($_SERVER['HTTP_TOKEN']) : (isset($_COOKIE['Token']) ? trim($_COOKIE['Token']) : '');
        } else {
            $token = isset($_SERVER['HTTP_QKTOKEN']) ? trim($_SERVER['HTTP_QKTOKEN']) : (isset($_COOKIE['QkToken']) ? trim($_COOKIE['QkToken']) : '');
        }
        if (empty($token)) {
            $this->logout('请登录');
        }
        return $token;
    }
}