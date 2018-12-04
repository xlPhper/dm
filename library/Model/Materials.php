<?php
/**
 * Created by PhpStorm.
 * User: Ekko
 * Date: 2018/8/14
 * Ekko: 16:29
 */
class Model_Materials extends DM_Model
{
    public static $table_name = "materials";
    protected $_name = "materials";
    protected $_primary = "MaterialID";

    public function getByAppID($AppID)
    {
        if(empty($AppID)){
            return false;
        }
        $select = $this->_db->select();
        $select->from($this->_name)
            ->where("AppID = ?", $AppID);
        return $this->_db->fetchRow($select);
    }

    /**
     * 查询素材的标签列表
     */
    public function getMaterialCategoryList($CategoryIds)
    {
        $select = $this->fromSlaveDB()->select()->from($this->_name.' as m',[])->setIntegrityCheck(false);
        $select->joinLeft('categories as c','find_in_set(c.CategoryID,m.`TagIDs`)',['COUNT(c.CategoryID) as Num','c.Name','c.CategoryID']);
        $select->group('c.CategoryID');
        $select->where('c.CategoryID in (?)',$CategoryIds);
        return $this->_db->fetchAll($select);

    }

    /**
     * 根据标签查询
     */
    public function findByTagID($TagId)
    {
        $select = $this->fetchRow()->select()->from($this->_name,['MaterialID','TagIDs']);
        $select->where('find_in_set(?,TagIDs)',$TagId);
        return $this->_db->fetchAll($select);

    }

    /**
     * @param array $tagIDs 素材标签ID数组
     * @param array $field 要返回的字段
     * @return array
     * 根据标签ID数组返回对应的素材
     */
    public function findByTagIDs($tagIDs = [], $field = [])
    {
        $where = [];
        foreach ($tagIDs as $tagID){
            if ((int)$tagID>0){
                $where[] = "FIND_IN_SET(".(int)$tagID.", TagIDs)";
            }
        }
        if(empty($where)){
            return [];
        }
        $select = $this->fromSlaveDB()->select();
        if(!empty($field)){
            $select->from($this->getTableName(), $field);
        }
        return $select->where(implode(' or ', $where))->query()->fetchAll();
    }
}