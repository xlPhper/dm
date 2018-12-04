<?php

require_once APPLICATION_PATH . '/controllers/Admin/AdminBase.php';

class Admin_CategoryController extends AdminBase
{
    // 分类列表
    public function listAction()
    {
        $search = $this->getParam('Search',null);
        $type = $this->getParam('Type', '');
        $state = $this->getParam('State', 2); // 1-查数量 2-不查数量
        if (empty($type)){
            $this->showJson(0, '选择你需要查询分类的Type');
        }
        if (!in_array($type,CATEGORY_TYPES)){
            $this->showJson(0, '分类不合法');
        }
        $res = (new Model_Category())->getCategoriesByType($type,$search);
        // 开始的分类等级
        $res = $this->getChild($res,$state,$type);

        $this->showJson(self::STATUS_OK, '', $res);
    }

    public function childAction()
    {
        $parentId = (int)$this->_getParam('ParentID');

        $categories = (new Model_Category())->fetchAll(['ParentID = ?' => $parentId])->toArray();

        $this->showJson(self::STATUS_OK, '操作成功', $categories);
    }

    /**
     * 删除分类
     */
    public function delAction()
    {
        $category_id = $this->getParam('CategoryID', '');
        if (empty($category_id)) {
            $this->showJson(0, '无分类ID信息');
        }
        $category_id_data = explode(',',$category_id);

        $model = new Model_Category();
        $model->getAdapter()->beginTransaction();

        foreach ($category_id_data as $val){
            $category_info = $model->getCategoryById($category_id);
            switch ($category_info['Type']){
                case 'PHONE':
                    $phone_model = new Model_Phones();
                    $phone_model->delete(['CategoryID = ?' => $val]);
                    $being = false;
                    break;
                case 'WEIXIN':
                    $weixin_model = new Model_Weixin();
                    $being = $weixin_model->findIsCategory($val);
                    break;
                case 'WX_GROUP':
                    $group_model = new Model_Group();
                    $being = $group_model->findIsCategory($val);
                    break;
                case 'CHANNEL':
                    $weixin_model = new Model_Weixin();
                    $being = $weixin_model->findIsChannel($val);
                    break;
                case CATEGORY_TYPE_WEIXINFRIEND:
                    $friend_model = new Model_Weixin_Friend();
                    $being = $friend_model->getFriendNumsByCategoryID($val);
                    break;
            }
            if (isset($being) && $being['Num']){
                $this->showJson(self::STATUS_FAIL, '该分类下存在信息不可删除');
            }
            $res = $model->delete(['CategoryID = ?'=>$val]);
            if ($res == false){
                $model->getAdapter()->rollBack();
                $this->showJson(self::STATUS_FAIL, '删除失败');
            }
        }
        $model->getAdapter()->commit();
        $this->showJson(self::STATUS_OK, '删除成功');

    }

    /**
     * 添加分类
     */
    public function addAction()
    {
        $name = $this->getParam('Name', '');
        if (empty($name)){
            $this->showJson(self::STATUS_FAIL, '参数Name不存在');
        }
        $parentId = (int)$this->_getParam('ParentID', 0);

        $type = $this->getParam('Type', '');
        if (empty($type)){
            $this->showJson(0, '选择你需要查询分类的Type');
        }
        if (!in_array($type,CATEGORY_TYPES)){
            $this->showJson(0, '分类不合法');
        }

        $model = new Model_Category();
        $categroy_name = $model->findCategoryByNameType($name,$type);
        if ($categroy_name){
            $this->showJson(0,'改分类已添加');
        }

        // 获取管理员ID
        $admin_id = $this->getLoginUserId();

        $data = [
            'Name'=>$name,
            'Type'=>$type,
            'ParentID' => $parentId,
            'CreateDate' => date('Y-m-d H:i:s'),
            'AdminID' => $admin_id,
        ];
        $res = $model->insert($data);
        if ($res == false){
            $this->showJson(self::STATUS_FAIL, '添加失败');
        }else{
            $this->showJson(self::STATUS_OK, '添加成功');
        }
    }

    /**
     * 统计分类下的数据数量
     * @param $type
     */
    public function isCategory($res,$type)
    {
        switch ($type){
            case  CATEGORY_TYPE_PHONE:
                $phone_model = new Model_Phones();
                foreach ($res as &$val) {
                    $phones = $phone_model->findIsCategory($val['CategoryID']);
                    $val['Num'] = $phones['Num'];
                }
                break;
            case  CATEGORY_TYPE_WEIXIN:
                $weixin_model = new Model_Weixin();
                foreach ($res as &$val) {
                    $weixins = $weixin_model->findIsCategory($val['CategoryID']);
                    $val['Num']  = $weixins['Num'];
                }
                break;
            case  CATEGORY_TYPE_WXGROUP:
                $group_model = new Model_Group();
                foreach ($res as &$val) {
                    $groups = $group_model->findIsCategory($val['CategoryID']);
                    $val['Num']  = $groups['Num'];
                }
                break;
            case  CATEGORY_TYPE_CHANNEL:
                $weixin_model = new Model_Weixin();
                foreach ($res as &$val) {
                    $weixins = $weixin_model->findIsChannel($val['CategoryID']);
                    $val['Num']  = $weixins['Num'];
                }
                break;
            case  CATEGORY_TYPE_WEIXINFRIEND:
                $friend_model = new Model_Weixin_Friend();
                foreach ($res as &$val) {
                    $friends = $friend_model->getFriendNumsByCategoryID($val['CategoryID']);
                    $val['Num']  = $friends['Num'];
                }
                break;
        }
        return $res;
    }

    /**
     * 递归查询分类
     * @param $res
     */
    public function getChild($res,$state,$type)
    {
        if ($state == 1){
            $res = $this->isCategory($res,$type);
        }
        foreach ($res as &$val){
            $child_category = (new Model_Category())->findChildCategory($val['CategoryID']);
            if ($child_category){
                $child_category = $this->getChild($child_category,$state,$type);
                $val['Child'] = $child_category;
            }
        }
        return $res;
    }
}