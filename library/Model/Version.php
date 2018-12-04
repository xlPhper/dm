<?php
/**
 * Created by PhpStorm.
 * User: Ekko
 * Date: 2018/8/13
 * Ekko: 13:34
 */
class Model_Version extends DM_Model
{
    public static $table_name = "version";
    protected $_name = "version";
    protected $_primary = "VersionID";

    public function findByID($VersionID)
    {
        $select = $this->select();
        $select->where('VersionID = ?',$VersionID);
        return $this->_db->fetchRow($select);
    }

    /**
     * @param $IsTest 是否是测试 1-是测试 0-不是测试
     * @return array
     */
    public function findList($IsTest)
    {
        $where = '';
        switch ($IsTest){
            case 0:
                $where = ' where IsTest = 0';
                break;
            case 1:
                $where = ' where IsTest = 1';
                break;
        }
        $sql = "select `VersionID`,`PackageName`,`VersionName`,`VersionCode`,`DownloadUrl`,`NeedRestart`,`Describe` from `version`".$where;
        return $this->_db->fetchAll($sql);
    }
}