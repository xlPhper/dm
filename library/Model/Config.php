<?php
/**
 * Created by PhpStorm.
 * User: tim
 * Date: 2018/11/13
 * Time: 4:19 PM
 */
class Model_Config extends DM_Model
{
    protected $_name = "configs";
    protected $_primary = "ID";

    public function set($key, $value)
    {
        $data = $this->get($key);
        if($data === false){
            //新增
            $data = [
                'Key' => $key,
                'Value' => $value
            ];
            $this->_db->insert($this->_name, $data);
        }else{
            //更新
            $data = [
                'Value' => $value
            ];
            $where = $this->_db->quoteInto("`Key` = ?", $key);
            $this->_db->update($this->_name, $data, $where);
        }
    }

    public function get($key, $format = null)
    {
        $select = $this->_db->select();
        $select->from($this->_name, "Value")
               ->where("`Key` = ?", $key);
        $row = $this->_db->fetchRow($select);
        $data = false;
        if(isset($row['Value'])){
            switch ($format){
                case 'json':
                    $data = json_decode($row['Value'], true);
                    break;
                default:
                    $data = $row['Value'];
            }
        }
        return $data;
    }
}