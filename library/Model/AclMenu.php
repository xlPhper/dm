<?php

class Model_AclMenu extends DM_Model
{
    public static $table_name = "acl_menus";
    protected $_name = "acl_menus";
    protected $_primary = "MenuID";


    /**
     * 后台菜单配置
     * @return array
     */
    public static function systemMenuConfigs()
    {
        return
            [
                [
                    'MenuID' => 1,
                    'MenuName' => '首页',
                    'MenuCode' => 'index',
                    'ParentID' => 0,
                    'IsDisplay' => 'Y',
                    'Type' => 'TAB',
                    'Platform' => 'SYSTEM'
                ],
                [
                    'MenuID' => 2,
                    'MenuName' => '微信号',
                    'MenuCode' => 'weixin',
                    'ParentID' => 0,
                    'IsDisplay' => 'Y',
                    'Type' => 'TAB',
                    'Platform' => 'SYSTEM'
                ],
                [
                    'MenuID' => 3,
                    'MenuName' => '微信群',
                    'MenuCode' => 'group',
                    'ParentID' => 0,
                    'IsDisplay' => 'Y',
                    'Type' => 'TAB',
                    'Platform' => 'SYSTEM'
                ],
                [
                    'MenuID' => 4,
                    'MenuName' => '朋友圈',
                    'MenuCode' => 'album',
                    'ParentID' => 0,
                    'IsDisplay' => 'Y',
                    'Type' => 'TAB',
                    'Platform' => 'SYSTEM'
                ],
                [
                    'MenuID' => 5,
                    'MenuName' => '任务系统',
                    'MenuCode' => 'task',
                    'ParentID' => 0,
                    'IsDisplay' => 'Y',
                    'Type' => 'TAB',
                    'Platform' => 'SYSTEM'
                ],
                [
                    'MenuID' => 6,
                    'MenuName' => '数据统计',
                    'MenuCode' => 'stat',
                    'ParentID' => 0,
                    'IsDisplay' => 'Y',
                    'Type' => 'TAB',
                    'Platform' => 'SYSTEM'
                ],
                [
                    'MenuID' => 7,
                    'MenuName' => '系统管理',
                    'MenuCode' => 'sys',
                    'ParentID' => 0,
                    'IsDisplay' => 'Y',
                    'Type' => 'TAB',
                    'Platform' => 'SYSTEM'
                ],
                [
                    'MenuID' => 20100,
                    'MenuName' => '微信管理',
                    'MenuCode' => 'weixin.manage',
                    'ParentID' => 1,
                    'IsDisplay' => 'Y',
                    'Type' => 'MENU',
                    'Platform' => 'SYSTEM'
                ],
                [
                    'MenuID' => 20101,
                    'MenuName' => '微信号列表',
                    'MenuCode' => 'weixin.manage.numbers',
                    'ParentID' => 20100,
                    'IsDisplay' => 'Y',
                    'Type' => 'MENU',
                    'Platform' => 'SYSTEM'
                ],
                [
                    'MenuID' => 20102,
                    'MenuName' => '消息列表',
                    'MenuCode' => 'weixin.manage.messages',
                    'ParentID' => 20100,
                    'IsDisplay' => 'Y',
                    'Type' => 'MENU',
                    'Platform' => 'SYSTEM'
                ],
                [
                    'MenuID' => 20103,
                    'MenuName' => '朋友圈列表',
                    'MenuCode' => 'weixin.manage.albums',
                    'ParentID' => 20100,
                    'IsDisplay' => 'Y',
                    'Type' => 'MENU',
                    'Platform' => 'SYSTEM'
                ],
                [
                    'MenuID' => 20104,
                    'MenuName' => '回复模板',
                    'MenuCode' => 'weixin.manage.templates',
                    'ParentID' => 20100,
                    'IsDisplay' => 'Y',
                    'Type' => 'MENU',
                    'Platform' => 'SYSTEM'
                ],
                [
                    'MenuID' => 20200,
                    'MenuName' => '好友',
                    'MenuCode' => 'weixin.friend',
                    'ParentID' => 1,
                    'IsDisplay' => 'Y',
                    'Type' => 'MENU',
                    'Platform' => 'SYSTEM'
                ],
                [
                    'MenuID' => 20201,
                    'MenuName' => '手机号加好友',
                    'MenuCode' => 'weixin.friend.phone_add',
                    'ParentID' => 20200,
                    'IsDisplay' => 'Y',
                    'Type' => 'MENU',
                    'Platform' => 'SYSTEM'
                ],
            ];
    }

    /**
     * 分类树
     */
    public function tree($parentId = 0)
    {
        $s = $this->fromSlaveDB()->select()
            ->where('IsDisplay = ?', 'Y')
            ->where('ParentID = ?', $parentId)
            ->order('DisplayOrder asc');

        $menus = $this->fetchAll($s)->toArray();

        foreach ($menus as &$menu) {
            $menu['Children'] = $this->leftMenuTree($menu['MenuID']);
        }

        return $menus;
    }

    /**
     * 左侧菜单树
     */
    public function leftMenuTree($parentId = 0)
    {
        $s = $this->fromSlaveDB()->select()
            ->where('Type = ?', 'MENU')
            ->where('IsDisplay = ?', 'Y')
            ->where('ParentID = ?', $parentId)
            ->order('DisplayOrder asc');

        $menus = $this->fetchAll($s)->toArray();

        foreach ($menus as &$menu) {
            $menu['Children'] = $this->leftMenuTree($menu['MenuID']);
        }

        return $menus;
    }

    /**
     * 菜单顶部tabs
     */
    public function topTabs()
    {
        $s = $this->fromSlaveDB()->select()
            ->where('Type = ?', 'TAB')
            ->where('IsDisplay = ?', 'Y')
            ->order('DisplayOrder asc');

        return $this->fetchAll($s)->toArray();
    }
}