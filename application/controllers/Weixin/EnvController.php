<?php
/**
 * Created by PhpStorm.
 * User: Tim
 * Date: 2018/4/24
 * Time: 23:51
 */
class Weixin_EnvController extends DM_Controller
{
    /**
     * 环境获取
     */
    public function getAction()
    {
        $Weixin = trim($this->_getParam("Weixin"));
        if(empty($Weixin)){
            $this->showJson(0,"weixin is required");
        }
        $weixinModel = new Model_Weixin();
        $wx = $weixinModel->fetchRow("Weixin = '$Weixin' or Alias = '$Weixin'");
        if(!$wx){
            $this->showJson(0,"weixin not found");
        }
        $model = new Model_Weixin_Env();
        $d = $model->fetchRow("WeixinID = {$wx["WeixinID"]}");
        if(!$d){
           $this->showJson(0,"env not found");
        }
        $data = $d->toArray();
        $data["Weixin"]      = $wx["Weixin"];
        $data["Alias"]       = $wx["Alias"];
        $data["Nickname"]    = $wx["Nickname"];
        $data["PhoneNumber"] = $wx["PhoneNumber"];
        $this->showJson(1,"",$data);
    }
    /**
     * 环境添加
     */
    public function addAction()
    {
        $Weixin = trim($this->_getParam("Weixin"));
        if(empty($Weixin)){
            $this->showJson(0,"weixin is required");
        }
        $SN = trim($this->_getParam("SN"));
        $weixinModel = new Model_Weixin();
        $row = $weixinModel->fetchRow("Weixin = '$Weixin' or Alias = '$Weixin'");
        if(!$row){
            $this->showJson(0,"weixin not found");
        }
        $WeixinID = $row["WeixinID"];
        $IMEI = trim($this->_getParam("IMEI"));
        $AndroidID = trim($this->_getParam("AndroidID"));
        $MAC  = trim($this->_getParam("MAC"));
        $SSID = trim($this->_getParam("SSID"));
        $Product = trim($this->_getParam("Product"));
        $Model = trim($this->_getParam("Model"));
        $Vendor = trim($this->_getParam("Vendor"));
        $Brand = trim($this->_getParam("Brand"));
        if(empty($IMEI)){
            $this->showJson(0,"IMEI is required");
        }
        $CreateTime = date("Y-m-d H:i:s");
        $data = [
            "WeixinID" => $WeixinID,
            "IMEI" => $IMEI,
            "AndroidID" => $AndroidID,
            "MAC" => $MAC,
            "SSID" => $SSID,
            "Product" => $Product,
            "Model" => $Model,
            "Vendor" => $Vendor,
            "Brand" => $Brand,
            "SN" => $SN
        ];
        try {
            $envModel = new Model_Weixin_Env();
            $res = $envModel->getEnv($WeixinID);
            if($res){
                $envModel->update($data,['WeixinID = ?' => $WeixinID]);
            }else{
                $data["CreateTime"] = $CreateTime;
                $envModel->insert($data);
            }
            $this->showJson(1,"保存成功");
        } catch (Exception $e) {
            $this->showJson(0,"保存失败".$e->getMessage());
        }
    }
}