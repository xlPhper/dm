<?php

require_once APPLICATION_PATH . '/controllers/Admin/AdminBase.php';

class Admin_AclController extends AdminBase
{
    /**
     * 添加菜单
     */
    public function initElementsAction()
    {
        $elements = Model_AclMenu::systemMenuConfigs();

        foreach ($elements as $element) {
//            $am = new AclMenus();
//            $am->save($element);
        }

        $this->returnJson(self::STATUS_OK, '操作成功');
    }

    /**
     * 菜单树
     */
    public function menuTreeAction()
    {
        $tree = (new Model_AclMenu())->tree();

        $this->showJson(self::STATUS_OK, '操作成功', $tree);
    }

    public function menuTabsAction()
    {

    }

    public function menuEditAction()
    {
        $menuName = trim($this->_getParam('MenuName', ''));
        if ($menuName === '') {
            $this->showJson(self::STATUS_FAIL, '菜单名称必填');
        }
        $type = trim($this->_getParam('Type'));
        if (!in_array($type, ['TAB', 'MENU'])) {
            $this->showJson(self::STATUS_FAIL, '菜单类型非法');
        }
    }

}