<?php
/**
 * Created by PhpStorm.
 * User: Ekko
 * Date: 2018/7/27
 * Ekko: 15:21
 */
class Model_Category extends DM_Model
{
    public static $table_name = "categories";
    protected $_name = "categories";
    protected $_primary = "CategoryID";


    public function getCategoriesByType($type,$search = null,$adminIds = null,$platform = 'Group')
    {
        $select = $this->fromSlaveDB()->select();
        $select->where('Type = ?',$type);
        $select->where('ParentID = 0');
        if ($search){
            $select->where('Name like ?','%'.$search.'%');
        }
        if ($adminIds){
            $select->where('AdminID in (?)',$adminIds);
        }
        $select->where('Platform = ?',$platform);
        return $this->_db->fetchAll($select);
    }

    /**
     * 通过 id 和 type 获取分类
     */
    public function getCategoryByIdType($id, $type)
    {
        $select = $this->select()->where('CategoryID = ?', $id)->where('Type = ?', $type);
        return $this->_db->fetchRow($select);
    }

    /**
     * 通过 id 获取分类
     */
    public function getCategoryById($id)
    {
        $select = $this->select()->where('CategoryID = ?', $id);
        return $this->_db->fetchRow($select);
    }

    /**
     * 通过 id 的子分类
     */
    public function getChildCategoryID($id)
    {
        $select = $this->select()->where('ParentID = ?', $id);
        $data =  $this->_db->fetchAll($select);
        $res = array();
        foreach ($data as $val){
            $res[] = $val['CategoryID'];
        }
        return $res;
    }

    /**
     * 查询分类名称
     * @param string $ids 例:1,2,3
     */
    public function findCategoryName($ids)
    {
        $sql = "SELECT `Name` FROM `categories` WHERE CategoryID IN ({$ids})";
        $data = $this->_db->fetchAll($sql);
        $res = array();
        foreach ($data as $val){
            $res[] = $val['Name'];
        }
        return $res;
    }

    /**
     * 查询子分类
     */
    public function findChildCategory($id)
    {
        $select = $this->fromSlaveDB()->select()->where('ParentID = ?', $id)->where('ParentID != 0');
        return $this->_db->fetchAll($select);
    }

    /**
     * 发现父分类ids
     */
    public static function findParentIds($categoryId, $parentIds = [])
    {
        $category = (new self())->fetchRow(['CategoryID = ?' => $categoryId]);
        if ($category->ParentID > 0) {
            array_unshift($parentIds, $category->ParentID);
            $parentIds = self::findParentIds($category->ParentID, $parentIds);
        }

        return $parentIds;
    }

    /**
     * 发现子分类ids
     */
    public static function findChildIds($categoryId, $childIds = [])
    {
        $categories = (new self())->fetchAll(['ParentID = ?' => $categoryId]);
        foreach ($categories as $category) {
            $childIds[] = $category->CategoryID;
            $childIds = self::findChildIds($category->CategoryID, $childIds);
        }

        return $childIds;
    }

    /**
     * 用分类名查询
     */
    public function findCategoryByNameType($name, $type)
    {
       $select = $this->select()->where('Name = ?',$name)->where('Type = ?',$type);
       return $this->_db->fetchRow($select);
    }

    /**
     * 获取id对应名称
     * @param string $type
     * @return array id => name
     */
    public function getIdToName($type = null,$platform = PLATFORM_GROUP)
    {

        $select = $this->select();
        if($type) {
            $select->where('Type = ?',$type);
        }
        if ($platform){
            $select->where('Platform = ?',$platform);
        }
        $res = $this->_db->fetchAll($select);
        $data = [];
        foreach ($res as $r) {
            $data[$r["CategoryID"]] = $r["Name"];
        }
        return $data;
    }

    /**
     * 获取部门标签
     *
     * @param $departmentID
     * @return array
     */
    public function findByDepartmentID($search = '',$companyId,$departmentID,$type,$adminID,$platform = 'Group')
    {
        if (!in_array($type, CATEGORY_TYPES)) {
            return false;
        }
        $select = $this->select()->from($this->_name.' as c')->setIntegrityCheck(false);
        $select->joinLeft('admins as a','a.AdminID = c.AdminID',['a.Username']);
        $select->where('c.CompanyId = ?',$companyId);
        $select->where('c.DepartmentID in (?)',$departmentID);
        $select->where('c.Type = ?',$type);
        $select->where('c.Platform = ?',$platform);
        if (!empty($search)){
            $select->where('c.CategoryID LIKE ? OR c.Name LIKE ?','%'.$search.'%');
        }
        if (!empty($adminID)){
            $select->where('c.AdminID = ?',$adminID);
        }
        return $this->_db->fetchAll($select);
    }
}