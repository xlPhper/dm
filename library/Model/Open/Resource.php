<?php

/**
 * Created by PhpStorm.
 * User: Tim
 * Date: 2018/4/25
 * Time: 0:06
 */
class Model_Open_Resource extends DM_Model
{
    public static $table_name = "resource";
    protected $_name = "resource";
    protected $_primary = "ResourceID";

    /**
     * 关联批量添加
     * @param $DepartmentID
     * @param array $Resources
     * @param int $Related
     * @param int $Source
     */
    public function batchAdd($DepartmentID,$Resources,$Related = 0,$Source=1){
        $now = date("Y-m-d H:i:s");
        $Urls = [];
        foreach ($Resources as $r) {
            $Urls[] = $Url = $r["Url"];
            $data = [
                "DepartmentID" => $DepartmentID,
                "Url"          => $Url,
                "Type"         => $r["Type"],
                "Source"       => $Source,
                "Related"      => $Related,
                "UpdateTime"   => $now,
            ];
            $res = $this->fetchRow("DepartmentID = {$DepartmentID} and Url = '{$Url}'");
            if ($res){
                $this->update($data,['ResourceID = ?' => $res["ResourceID"] ]);
            }else{
                $data["CreateTime"] = $now;
                $this->insert($data);
            }
        }
        if($Related > 0){
            $where = ["Related = ? "=> $Related];
            if(count($Urls)){
                $where["Url not in(?)"] = $Urls;
            }
            $this->delete($where);
        }
    }
    public function batchGet($Related,$Source = 1,$DepartmentID = -1){
        if(!count($Related)){
            return [];
        }
        $select = $this->select()->from($this->_name,["ResourceID","Url","Related"])
            ->where("Related in (?)",$Related)
            ->where("Source = ?",$Source);
        if($DepartmentID > -1){
            $select->where("$DepartmentID = ?",$DepartmentID);
        }
        return $select->query()->fetchAll();
    }
}