<?php
/**
 * Created by PhpStorm.
 * User: Ekko
 * Date: 2018/11/15
 * Time: 16:49
 */
class Model_Note extends DM_Model
{
    public static $table_name = "notes";
    protected $_name = "notes";
    protected $_primary = "NoteID";

    /**
     * 获取未完成的便签
     *
     * @param $amdinID
     * @return array
     */
    public function getNoteList($amdinID)
    {
        $select = $this->select()->from($this->_name,['NoteID','Content','Status','CreateTime']);
        $select->where('Status = 1');
        $select->where('AdminID = ?',$amdinID);

        return $this->_db->fetchAll($select);
    }
}