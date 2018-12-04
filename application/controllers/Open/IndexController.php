<?php

require_once APPLICATION_PATH . '/controllers/Open/OpenBase.php';

class Open_IndexController extends OpenBase
{
    public function indexAction()
    {
        $this->showJson(1, 'ok', $this->getLoginUserId());
    }
}