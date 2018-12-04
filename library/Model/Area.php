<?php
/**
 * Created by PhpStorm.
 * User: Ekko
 * Date: 2018/11/12
 * Time: 11:54
 */
class Model_Area extends DM_Model
{
    public static $table_name = "area";
    protected $_name = "area";
    protected $_primary = "AreaID";

    public function findByID($areaID)
    {
        $select = $this->select();
        $select->where('AreaID = ?',$areaID);
        return $this->_db->fetchRow($select);
    }

    /**
     * 根据地区Code查询
     *
     * @param $areaCode
     * @return mixed
     */
    public function findByAreaCode($areaCode)
    {
        $select = $this->select();
        $select->where('AreaCode = ?',$areaCode);
        return $this->_db->fetchRow($select);
    }

    /**
     * 父查询
     *
     * @return array
     */
    public function getChild($parentAreaID = 0)
    {
        $select = $this->select()->from($this->_name,['AreaID','AreaName','AreaCode','ParentAreaID']);
        $select->where('ParentAreaID = ?',$parentAreaID);
        return $this->_db->fetchAll($select);
    }


    /**
     * 根据地区Code查询
     *
     * @param $areaCode
     * @return mixed
     */
    public function findByCodeNames($areaCodes = array())
    {
        if ($areaCodes){
            $select = $this->select()->from($this->_name,['AreaCode','AreaName']);
            $select->where('AreaCode in (?)',$areaCodes);
            $data = $this->_db->fetchAll($select);

            $res = [];

            foreach ($data as $d){
                $res[$d['AreaCode']] = $d['AreaName'];
            }

            return $res;
        }else{

            return false;
        }

    }
}