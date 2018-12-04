<?php

require_once APPLICATION_PATH . '/controllers/Admin/AdminBase.php';

class Admin_IndexController extends AdminBase
{
    public function indexAction()
    {
        $this->showJson(1, 'ok', ['IsTest' => 'Y']);
    }
}