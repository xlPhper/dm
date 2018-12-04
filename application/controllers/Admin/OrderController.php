<?php
require_once APPLICATION_PATH . '/controllers/Admin/AdminBase.php';

class Admin_OrderController extends AdminBase
{
    /**
     * 商品列表
     */
    public function goodsListAction()
    {
        $Name = trim($this->_getParam("Name"));
        $CategoryID = (int)$this->_getParam("CategoryID",0);
        $model = new Model_Order_Goods();
        $select = $model->select();
        if ($CategoryID > 0) {
            $select->where('Status = ?', 1)->where('CategoryID = ?', $CategoryID);
        } else {
            $select->where('Status != ?', 3);
        }
        if (!empty($Name)) {
            $select->where('Name like ?', '%'.$Name.'%');
        }
        $CompanyId = $this->admin["CompanyId"];
        $select->where("CompanyId = ?",$CompanyId);
        $select->order('DisplayOrder desc');
        $res = $model->fetchAll($select)->toArray();
        $categories = (new Model_Order_GoodsCategories())->fetchAll()->toArray();
        $model->getFiled($res,"CategoryID" ,$categories ,"Name","CategoryName");
        $model->getFiled($res,"CategoryID" ,$categories ,"Identify");
        $this->showJson(1, '', $res);
    }
    /**
     * 商品添加
     */
    public function goodsAddAction()
    {
        $GoodsID = (int)$this->_getParam("GoodsID",0);
        $CategoryID = (int)$this->_getParam("CategoryID",0);
        $Name = trim($this->_getParam("Name"));
        $Remark = trim($this->_getParam("Remark"));
        $displayOrder = (int)$this->_getParam('DisplayOrder', 0);
        $ProvidePrice = (int)$this->_getParam('ProvidePrice', 0);

        $model = new Model_Order_Goods();
        if(empty($Name) || empty($CategoryID)){
            $this->showJson(0,"参数不能为空");
        }

        if($ProvidePrice < 0){
            $this->showJson(0,"供货价格不能为空");
        }

        $db = $model->getAdapter();
        try {
            $db->beginTransaction();
            $row = $model->fetchRow("Name = '{$Name}' and Status != 3 and GoodsID != {$GoodsID}");
            if($row){
                throw new Exception("名称不能重复");
            }
            $Time = date("Y-m-d H:i:s");
            $CompanyId = $this->admin["CompanyId"];
            if(!$GoodsID){
                $model->insert([
                    "Name"          => $Name,
                    "CategoryID"    => $CategoryID,
                    "Remark"        => $Remark,
                    "CreateTime"    => $Time,
                    'DisplayOrder'  => $displayOrder,
                    "CompanyId"     => $CompanyId,
                    "ProvidePrice"  => $ProvidePrice,
                ]);
            }else{
                $model->update([
                    "Name"       => $Name,
                    "Remark"   => $Remark,
                    "UpdateTime" => $Time,
                    'DisplayOrder' => $displayOrder,
                    "ProvidePrice"  => $ProvidePrice,
                ],"GoodsID = {$GoodsID}");
            }
            $db->commit();
            $this->showJson(1,"更新成功");
        } catch (Exception $e) {
            $db->rollBack();
            $this->showJson(0,"更新失败：".$e->getMessage());
        }
    }
    /**
     * 商品删除
     */
    public function goodsDeleteAction()
    {
        $this->showJson(0,"禁止删除");
        $GoodsID = (int)$this->_getParam("GoodsID",0);
        $model = new Model_Order_Goods();
        $model->delete("GoodsID = {$GoodsID}");
        $this->showJson(1,"删除成功");
    }
    /**
     * 商品状态
     */
    public function goodsStatusAction()
    {
        $GoodsID = $this->_getParam("GoodsID",[]);
        $Status = (int)$this->_getParam("Status",0);
        if (!is_array($GoodsID) || count($GoodsID) == 0){
            $this->showJson(0,"请选择商品");
        }
        if(!in_array($Status,[1,2,3])){
            $this->showJson(0,"Status error");
        }
        $model = new Model_Order_Goods();
        $model->update(["Status" => $Status],["GoodsID in (?)" => $GoodsID]);
        $this->showJson(1,"更新成功");
    }
    /**
     * 商品分类列表
     */
    public function goodsCategoryListAction()
    {
        $model = new Model_Order_GoodsCategories();
        $CompanyId = $this->admin["CompanyId"];
        $res = $model->fetchAll("CompanyId = {$CompanyId}");
        $this->showJson(1, '', $res->toArray());
    }
    /**
     * 商品分类添加
     */
    public function goodsCategoryAddAction()
    {
        $CategoryID = (int)$this->_getParam("CategoryID",0);
        $Name = trim($this->_getParam("Name"));
        $Identify = trim($this->_getParam("Identify"));
        $model = new Model_Order_GoodsCategories();
        if(empty($Name) || empty($Identify)){
            $this->showJson(0,"参数不能为空");
        }
        $db = $model->getAdapter();
        try {
            $db->beginTransaction();
            $row = $model->fetchRow("Identify = '{$Identify}' and CategoryID != {$CategoryID}");
            if($row){
                throw new Exception("标识不能重复");
            }
            $CompanyId = $this->admin["CompanyId"];
            if(!$CategoryID){
                $model->insert([
                    "Name"       => $Name,
                    "Identify"   => $Identify,
                    "CategoryID" => $CategoryID,
                    "CompanyId"  => $CompanyId,
                ]);
            }else{
                $model->update([
                    "Name"       => $Name,
                    "Identify"   => $Identify
                ],"CategoryID = {$CategoryID}");
            }
            $db->commit();
            $this->showJson(1,"更新成功");
        } catch (Exception $e) {
            $db->rollBack();
            $this->showJson(0,"更新失败：".$e->getMessage());
        }
    }
    /**
     * 商品分类删除
     */
    public function goodsCategoryDeleteAction()
    {
        $this->showJson(0,"禁止删除");
        $CategoryID = (int)$this->_getParam("CategoryID",0);
        $model = new Model_Order_GoodsCategories();
        $model->delete("CategoryID = {$CategoryID}");
        $this->showJson(1,"删除成功");
    }

    /**
     * 订单列表
     */
    public function listAction()
    {
        $page = $this->getParam('Page', 1);
        $pagesize = $this->getParam('Pagesize', 100);

        $AdminID = (int)$this->_getParam("AdminID",0);
        $SerialNum = $this->_getParam("SerialNum","");
        $Buyer = trim($this->_getParam("Buyer"));
        $BuyerName = trim($this->_getParam("BuyerName"));
        $Seller = trim($this->_getParam("Seller"));
        $SellerName = trim($this->_getParam("SellerName"));

        $OrderNo = trim($this->_getParam("OrderNo"));
        $Status = trim($this->_getParam("Status",-1));
        $PaymentMethod = trim($this->_getParam("PaymentMethod"));
        $MinTotalAmount = trim($this->_getParam("MinTotalAmount"));
        $MaxTotalAmount = trim($this->_getParam("MaxTotalAmount"));
        $CategoryID = (int)$this->_getParam("CategoryID",0);

        $StartOrderDate = trim($this->_getParam("StartOrderDate"));
        $EndOrderDate = trim($this->_getParam("EndOrderDate"));

        $StartShipTime = trim($this->_getParam("StartShipTime"));
        $EndShipTime = trim($this->_getParam("EndShipTime"));

        $StartPushTime = trim($this->_getParam("StartPushTime"));
        $EndPushTime = trim($this->_getParam("EndPushTime"));

        $Mobile = trim($this->_getParam("Mobile"));
        $Logistics = trim($this->_getParam("Logistics"));
        $ShipNo = trim($this->_getParam("ShipNo"));
        $Address = trim($this->_getParam("Address"));
        $Export = (int)$this->_getParam("Export",0);

        $PushOrder = (int)$this->_getParam('IsPush',-1);

        $model = new Model_Orders();
        $select = $model->fromSlaveDB()->select()->from($model->getTableName().' as o')->setIntegrityCheck(false);
        $select->joinLeft("weixins_view as w","w.Weixin = o.Seller",["NickName as SellerName","DeviceID"]);
        $select->columns(new Zend_Db_Expr("(select GROUP_CONCAT(CONCAT(g.NAME,'*',od.Quantity)) from order_detail as od 
        left join order_goods as g on od.GoodsID = g.GoodsID where od.OrderID = o.OrderID) as Detail"));
        if ($AdminID > 0){
            $select->where("o.AdminID = ?",$AdminID);
        }
        if (!empty($SerialNum)){
            $select->joinLeft("devices as d","w.DeviceID = d.DeviceID",[]);
            $select->where("d.SerialNum like ?","%{$SerialNum}%");
        }
        if (!empty($Buyer)){
            $select->where("o.Buyer like ?","%{$Buyer}%");
        }
        if (!empty($BuyerName)){
            $select->joinLeft("weixin_friends as wf","wf.Account = o.Buyer or wf.Alias = o.Buyer");
            $select->where("wf.NickName like ?","%{$BuyerName}%");
        }
        if (!empty($Seller)){
            $select->where("o.Seller like ?","%{$Seller}%");
        }
        if (!empty($SellerName)){
            $select->where("w.NickName like ?","%{$SellerName}%");
        }
        if (!empty($OrderNo)){
            $select->where("o.OrderNo like ?","%{$OrderNo}%");
        }
        if ($Status >= 0){
            $select->where("o.Status = ?",$Status);
        }
        if (!empty($PaymentMethod)){
            $select->where("o.PaymentMethod = ?",$PaymentMethod);
        }
        if (!empty($MinTotalAmount)){
            $select->where("o.TotalAmount >= ?",$MinTotalAmount);
        }
        if (!empty($MaxTotalAmount)){
            $select->where("o.TotalAmount < ?",$MaxTotalAmount);
        }
        if (!empty($StartOrderDate)){
            $select->where("o.OrderTime >= ?",$StartOrderDate);
        }
        if (!empty($EndOrderDate)){
            $EndOrderDate = date("Y-m-d",strtotime("$EndOrderDate +1 days"));
            $select->where("o.OrderTime < ?",$EndOrderDate);
        }
        if (!empty($StartShipTime)){
            $select->where("o.ShipTime >= ?",$StartShipTime);
        }
        if (!empty($EndShipTime)){
            $EndShipTime = date("Y-m-d",strtotime("$EndShipTime +1 days"));
            $select->where("o.ShipTime < ?",$EndShipTime);
        }

        if (!empty($StartPushTime)){
            $select->where("o.PushTime >= ?",$StartPushTime);
        }
        if (!empty($EndPushTime)){
            $select->where("o.PushTime <= ?",$EndPushTime);
        }

        if (!empty($Mobile)){
            $select->where("o.Mobile like ?","%{$Mobile}%");
        }
        if (!empty($Logistics)){
            $select->where("o.Logistics = ?",$Logistics);
        }
        if (!empty($ShipNo)){
            $select->where("o.ShipNo like ?","%{$ShipNo}%");
        }
        if (!empty($Address)){
            $select->where("o.Address like ?","%{$Address}%");
        }
        if ($CategoryID > 0){
            $select->where("o.CategoryID = ?",$CategoryID);
        }
        if($PushOrder != -1){
            if($PushOrder ==1){
                $select->where('o.Status != 0');
            }
            if($PushOrder ==2){
                $select->where('o.Status = 0');

            }
        }
        $CompanyId = $this->admin["CompanyId"];
        $select->where("o.CompanyId = ?",$CompanyId);
        $select->where("o.Status != 9");
        $select->group("o.OrderID");
        $select->order("o.OrderID desc");
//        echo $select->__toString();exit();
        $res = $model->getResult($select, $page, $pagesize);
        foreach ($res['Results'] as &$value){
            $value['OrderDate'] = ($value['OrderDate'] =='0000-00-00 00:00:00')?'':date('Y-m-d H:i',strtotime($value['OrderDate']));
            $value['ShipTime'] = ($value['ShipTime'] =='0000-00-00 00:00:00')?'':date('Y-m-d H:i',strtotime($value['ShipTime']));
            $value['PushTime'] = ($value['PushTime'] =='0000-00-00 00:00:00')?'':date('Y-m-d H:i',strtotime($value['PushTime']));
        }

        $Buyers = array_column($res["Results"],"Buyer");
        $NickNames = (new Model_Weixin_Friend())->getNickName($Buyers);
        $model->getFiled($res["Results"], "Buyer", $NickNames,"Nickname","BuyerName","Account");
        $model->getFiled($res["Results"], "AdminID", "admins","Username","AdminName");
        $model->getFiled($res["Results"], "DeviceID", "devices","SerialNum");

        if($Export) {
            $data = $res["Results"];
            $Order_Status = Order_Status;
            $Order_PaymentMethod = Order_PaymentMethod;
            foreach ($data as &$item) {
                $item["Status"]        = $Order_Status[$item["Status"]];
                $item["PaymentMethod"] = $Order_PaymentMethod[$item["PaymentMethod"]];
            }
            $excel = new DM_ExcelExport();
            if($Export == 2){
                $excel->setTemplateFile(APPLICATION_PATH . "/data/excel/order_ship.xls")
                    ->setData($data)->export();
            }else{
                $excel->setTemplateFile(APPLICATION_PATH . "/data/excel/order.xls")
                ->setData($data)->export();
            }
        }else{
            $this->showJson(1, '', $res);
        }
    }
    /**
     * 订单添加
     */
    public function addAction()
    {
        $OrderID = (int)$this->_getParam("OrderID",0);
        $CategoryID = (int)$this->_getParam("CategoryID",0);
        $Buyer = trim($this->_getParam("Buyer"));
        $BuyerAddTime = trim($this->_getParam("BuyerAddTime"));
        $BuyerRemark = trim($this->_getParam("BuyerRemark"));
        $Seller = trim($this->_getParam("Seller"));
        $OrderDate = trim($this->_getParam("OrderDate"));
        $OrderTime = trim($this->_getParam("OrderTime"));
        $YyNote  = trim($this->_getParam("YyNote"));

        $PaymentMethod = trim($this->_getParam("PaymentMethod"));
        $TotalAmount = trim($this->_getParam("TotalAmount"));
        $PaymentAmount = trim($this->_getParam("PaymentAmount"));
        $CollectAmount = trim($this->_getParam("CollectAmount"));
        $Freight = trim($this->_getParam("Freight"));

        $Consignee = trim($this->_getParam("Consignee"));
        $Address = trim($this->_getParam("Address"));
        $Mobile = trim($this->_getParam("Mobile"));
        
        $MasterID = trim($this->_getParam("MasterID"));
        $Logistics = trim($this->_getParam("Logistics"));
        $ShipNo = trim($this->_getParam("ShipNo"));
        $ShipTime = trim($this->_getParam("ShipTime"));

        $Detail = $this->_getParam("Detail",[]);

        $AdminID = (int)$this->_getParam("AdminID",0);

        $model = new Model_Orders();
        if($CategoryID == 0){
            $this->showJson(0,"商品父类必须");
        }

        if(!is_array($Detail) || count($Detail) == 0){
            $this->showJson(0,"请添加商品");
        }
        if(empty($Buyer) || empty($Seller)){
            $this->showJson(0,"买家、卖家不能为空");
        }
        if($TotalAmount < 0){
            $this->showJson("总金额必须");
        }

        $db = $model->getAdapter();

        $wx = (new Model_Weixin())->getWx($Seller);
        $WeixinID = 0;
        if($wx){
            $WeixinID = $wx["WeixinID"];
        }else{
            $this->showJson(0,"not found weixin {$Seller}");
        }
        if($this->isOpenPlatform()){
            if($AdminID <= 0){
                $this->showJson(0,"请选择管理员");
            }
        }else{
            if(!empty($wx["AdminID"])){
                $AdminIDs = explode(",",$wx["AdminID"]);
                $AdminID = $AdminIDs[0];
            }
        }
        $CompanyId = $this->admin["CompanyId"];
        try {
            $data = [
                "Buyer"         => $Buyer,
                "BuyerAddTime"  => $BuyerAddTime,
                "BuyerRemark"   => $BuyerRemark,
                "Seller"        => $Seller,
                "WeixinID"      => $WeixinID,
                "AdminID"       => $AdminID,
                "OrderDate"     => $OrderDate,
                "OrderTime"     => $OrderTime,
                "PaymentMethod" => $PaymentMethod,
                "TotalAmount"   => $TotalAmount,
                "PaymentAmount" => $PaymentAmount,
                "CollectAmount" => $CollectAmount,
                "Freight"       => $Freight,
                "Consignee"     => $Consignee,
                "Address"       => $Address,
                "Mobile"        => $Mobile,
                "MasterID"      => $MasterID,
                "Logistics"     => $Logistics,
                "ShipNo"        => $ShipNo,
                "ShipTime"      => $ShipTime,
                "YyNote"        => $YyNote,
            ];
            $db->beginTransaction();
            $detailModel = new Model_Order_Detail();
            $Time = date("Y-m-d H:i:s");;
            if(!$OrderID){
                $data["CreateTime"] = $Time;
                $data["CategoryID"] = $CategoryID;
                $data["OrderNo"] = $model->getOrderNo($CategoryID);
                $data["CompanyId"] = $CompanyId;
//                var_dump($data);exit();
                $OrderID = $model->insert($data);
            }else{
                $data["UpdateTime"] = $Time;
                $model->update($data,"OrderID = {$OrderID}");
            }
            $detailModel->delete("OrderID = {$OrderID}");
            foreach ($Detail as $item) {
                $d = [
                    "GoodsID"   => $item["GoodsID"],
                    "Quantity"  => $item["Quantity"],
                    "OrderID"   => $OrderID,
                    "ProvidePrice"  => $item['ProvidePrice']
                ];
                if($item["DetailID"] > 0){
                    $data["DetailID"] = $item["DetailID"];
                }
                $detailModel->insert($d);
            }
            $db->commit();
            $this->showJson(1,"更新成功");
        } catch (Exception $e) {
            $db->rollBack();
            $this->showJson(0,"更新失败：".$e->getMessage());
        }
    }

    /**
     * 推单
     */
    public function pushOrderAction()
    {
        $orderId = $this->_getParam('OrderID');
        $detail = $this->_getParam("Detail",[]);

        if ($orderId < 1) {
            $this->showJson(self::STATUS_FAIL, 'id非法');
        }
        if(!$detail){
            $this->showJson(self::STATUS_FAIL, '未更新任何数据');
        }

        $orderModel = new Model_Orders();
        $splitModel  = new Model_OrderSplit();

        //校验父订单顺便取出父订单的数据
        $order = $orderModel->fetchRow(['OrderID = ?' => $orderId])->toArray();

        if (!$order) {
            $this->showJson(self::STATUS_FAIL, 'id非法');
        }
        if(!in_array($order['Status'],[0,4])){
            $this->showJson(self::STATUS_FAIL, '该状态下无法操作');
        }

//        //根据供货商重新整理一个新数组
//        $SonArr = [];
//        foreach ($detail as $value){
//            if(!isset($SonArr[$value['ProvideSeller']])){
//                $SonArr[$value['ProvideSeller']]=$value;
//            }else{
//                $SonArr[$value['ProvideSeller']]['ProvideAmount']+=$value['ProvideAmount'];
//                $SonArr[$value['ProvideSeller']]['GoodsID'].= ','.$value['GoodsID'];
//            }
//        }
        
        //如果是编辑的话 将之前分配的订单进行软删除
        $split = $splitModel->fetchAll(['OrderID = ?' => $orderId,'DeleteTime = ?'=>'0000-00-00 00:00:00'])->toArray();
        if($split){
            $splitModel->update(['Status'=>9,'DeleteTime'=>date('Y-m-d H:i:s')],['OrderID = ?' => $orderId]);
        }
        //根据商品重新分配子订单
        //取出数组需要的供货商和供货总价分别插入到子订单中
        $salt = 1;//加盐
        $orderNo = $order['OrderNo'];
        $providerAmount = 0;//计算供货总金额
        foreach ($detail as $item){
            $data['OrderNo']        = $orderNo.'00'.$salt;
            $data['Status']         = 4;
            $data['OrderID']        = $orderId;
            $data['ProvideSeller']  = $item['ProvideSeller'];
            $data['ProvideAmount']  = $item['ProvideAmount'];
            $data['GoodsName']      = $item['GoodsName'];
            $data['SonGoodsNum']    = $item['SonGoodsNum'];
            $data['AddTime']        = date('Y-m-d H:i:s');

            $splitModel->insert($data);
            $salt++;
            $providerAmount +=$item['ProvideAmount'];
        }
        $profit = round(($order['TotalAmount'] - $providerAmount),2);

        $orderData = [
            'Profit'          => $profit,
            'ProvideAmount'   => $providerAmount,
            'Status'          => 4,
            'PushTime'        => date('Y-m-d H:i:s')
        ];
        $orderModel->update($orderData,['OrderID = ?' =>$orderId]);
        $this->showJson(1,'操作成功');
    }

    /**
     * 推单、物流详情商品显示
     */
    public function statGoodsAction()
    {
        $orderId = $this->_getParam('OrderID');
        $type    = $this->_getParam('Type',1);

        if ($orderId < 1) {
            $this->showJson(self::STATUS_FAIL, 'id非法');
        }
        $orderModel = new Model_Orders();
        $splitModel = new Model_OrderSplit();
        $detailModel = new Model_Order_Detail();
        $res = $splitModel->fetchAll(['OrderID = ?'=>$orderId,'DeleteTime = ?'=>'0000-00-00 00:00:00'])->toArray();
        $order = $orderModel->fetchRow(['OrderID = ?' => $orderId])->toArray();

        $data = [];
        if($type == 1){
            if(!$order['Status']){//显示默认的供货金额
                $select = $detailModel->fromSlaveDB()->select()->from($detailModel->getTableName().' as d',['Quantity as Num'])->setIntegrityCheck(false);
                $select->join('order_goods as g','g.GoodsID = d.GoodsID');
                $select->where('d.OrderID = ?',$orderId);
                $res = $detailModel->fetchAll($select)->toArray();

                foreach ($res as $value){
                    $data[] = [
                        'SplitID'           => '',
                        'ProvideSeller'     => '',
                        'ProvideAmount'     => round($value['Num'] * ($value['ProvidePrice'] * 100))/100,
                        'SonGoodsNum'       => $value['Num'],
                        'GoodsName'         => $value['Name'],
                        'OrderID'           => ''
                    ];
                }
            }else{
                foreach ($res as $value){
                    $data[] = [
                        'SplitID'           => $value['SplitID'],
                        'ProvideSeller'     => $value['ProvideSeller'],
                        'ProvideAmount'     => $value['ProvideAmount'],
                        'SonGoodsNum'       => $value['SonGoodsNum'],
                        'GoodsName'         => $value['GoodsName'],
                        'OrderID'           => $value['OrderID']
                    ];
                }
            }

        }
        if($type == 2){
            if($order['Status']!=0){
                foreach ($res as $value) {
                    $data[] = [
                        'SplitID'       => $value['SplitID'],
                        'ShipTime'      => ($value['ShipTime'] == '0000-00-00 00:00:00')?'':$value['ShipTime'],
                        'ShipNo'        => $value['ShipNo'],
                        'Logistics'     => $value['Logistics'],
                        'SonGoodsNum'   => $value['SonGoodsNum'],
                        'GoodsName'     => $value['GoodsName'],
                        'OrderID'       => $value['OrderID'],
                        'ProvideSeller' => $value['ProvideSeller']
                    ];
                }
            }
        }

        $this->showJson(1,'操作成功',$data);
    }

    /**
     * 添加物流
     */
    public function shipAction()
    {
        $orderId = $this->_getParam('OrderID');
        $detail = $this->_getParam("Detail",[]);

        if ($orderId < 1) {
            $this->showJson(self::STATUS_FAIL, 'id非法');
        }
        if(!$detail){
            $this->showJson(self::STATUS_FAIL, '未更新任何数据');
        }

        $orderModel = new Model_Orders();
        $splitModel = new Model_OrderSplit();
        $order = $orderModel->fetchRow(['OrderID = ?' => $orderId])->toArray();

        if($order['Status']==0){
            $this->showJson(self::STATUS_FAIL, '该状态下无法操作');
        }
        foreach ($detail as $item){
            $data = [
              'Logistics'       => $item['Logistics'],
              'ShipNo'          => $item['ShipNo'],
              'ShipTime'        => $item['ShipTime']
            ];
            $splitModel->update($data,['SplitID = ?'=>$item['SplitID'],'OrderID = ?'=>$item['OrderID'],'DeleteTime = ?'=>'0000-00-00 00:00:00']);
        }
        $this->showJson(1, '操作成功');
    }

    /**
     * 订单状态
     */
    public function statusAction()
    {
        $OrderID = $this->_getParam("OrderID",[]);
        $Status = (int)$this->_getParam("Status",-1);
        if (!is_array($OrderID) || count($OrderID) == 0){
            $this->showJson(0,"请选择订单");
        }
        if(!in_array($Status,[0,1,2,3,4,9])){
            $this->showJson(0,"Status error");
        }
        $model = new Model_Orders();
        $data  = [
            "Status" => $Status
        ];
        if($Status == 4){
            $data['PushTime']  = date('Y-m-d H:i:s');
        }
        $model->update($data,["OrderID in (?)" => $OrderID]);
        $this->showJson(1,"更新成功");
    }
    /**
     * 订单详情
     */
    public function infoAction()
    {
        $OrderID = (int)$this->_getParam("OrderID",0);
        $model = new Model_Orders();
        $Order = $model->fetchRow("OrderID = {$OrderID}")->toArray();
        $Detail = (new Model_Order_Detail())->fetchAll("OrderID = {$OrderID}")->toArray();
        $Order["Detail"] = $Detail;
        $this->showJson(1,'',$Order);
    }
    /**
     * 订单删除
     */
    public function deleteAction()
    {
        $this->showJson(0,"禁止删除");
        $OrderID = (int)$this->_getParam("OrderID",0);
        $model = new Model_Orders();
        $model->delete("OrderID = {$OrderID}");
        $this->showJson(1,"删除成功");
    }
    /**
     * 订单导入进行发货
     */
    public function importShipAction()
    {
        try {
            $res = (new DM_ExcelImport())->getData();
            $model = new Model_Orders();
            $data = [];
            unset($res[1]);
            foreach ($res as $r) {
                if(empty($r[0])){
                    continue;
                };
                $OrderNo = $r[0];
                $d = [
                    "Logistics" => $r[1],
                    "ShipNo"    => $r[2]
                ];
                $ShipTime = empty($r[3])?"":$r[3];
                if(preg_match("/\d{4}-\d{2}-\d{2}/", $ShipTime)){
                    $d["ShipTime"] = $ShipTime;
                }elseif(preg_match("/^\d+$/",$ShipTime)){
                    $d["ShipTime"] = date('Y-m-d', ($ShipTime - 25569) * 24*60*60);
                }else{
                    $d["ShipTime"] = date("Y-m-d");
                }
                $where = ["OrderNo = ?"=>$OrderNo];
                $row = $model->fetchRow($where);
                if($row){
                    if($row["Status"] == 0 || $row["Status"] == 4){
                        $d["Status"] = Order_Status_Ship;
                    }else{
                        $d["Status"] = $row["Status"];
                    }
                    $model->update($d,$where);
                    $d['OrderNo'] = $OrderNo;
                    if($row["ShipTime"] != "0000-00-00"){
                        $d["ShipTime"] = $row["ShipTime"];
                    }
                }else{
                    $d["OrderNo"] = $OrderNo." is not found";
                }
                $data[] = $d;
            }
            $this->showJson(1,"更新成功",$data);
        } catch (Exception $e) {
            $this->showJson(0,$e->getMessage());
        }
    }
    /**
     * 按管理员统计
     */
    public function statAdminAction()
    {
        $CategoryID = $this->_getParam('CategoryID',0);
        $StartDate = $this->_getParam('StartDate',date("Y-m-01"));
        $EndDate = $this->_getParam('EndDate',date("Y-m-d"));
        $DepartmentID = (int)$this->_getParam("DepartmentID",0);
        $model = new Model_Orders();
        $select = $model->fromSlaveDB()->select()->setIntegrityCheck(false);
        $select->from("orders as o",["sum(TotalAmount) as TotalAmount","count(o.OrderID) as TotalCount"]);
        $select->joinLeft("admins as a","o.AdminID = a.AdminID",["ifnull(Username,'') as AdminName","DepartmentID"]);
        $select->columns(new Zend_Db_Expr("(select count(wf.FriendID) from weixins as w2 
        inner join weixin_friends as wf on w2.WeixinID = wf.WeixinID and wf.IsDeleted = 0
        where w2.AdminID = o.AdminID) as TotalFriends"));
        $select->columns(new Zend_Db_Expr("(select count(*) from weixins
            where AdminID = o.AdminID) as WxCount"));
        $select->columns(new Zend_Db_Expr("(select sum(s.AddFriendNum) from weixins as w1 left join stats as s on w1.WeixinID = s.WeixinID
            where w1.AdminID = o.AdminID and Date >= '{$StartDate}' and Date <= '$EndDate') as FansCount"));
        $select->where("o.Status != 9");
        $select->where("OrderDate >= ?",$StartDate);
        $select->where("OrderDate <= ?",$EndDate);
        if ($CategoryID > 0){
            $select->where("o.CategoryID = ?",$CategoryID);
        }
        if ($DepartmentID > 0){
            $select->where("a.DepartmentID = ?",$DepartmentID);
        }
        $CompanyId = $this->admin["CompanyId"];
        $select->where("o.CompanyId = ?",$CompanyId);
        $select->group("o.AdminID");
//        var_dump($select->__toString());exit();
        $res = $select->query()->fetchAll();
        foreach ($res as &$r) {
            $r["AvgPrice"] = $r["TotalCount"]==0?"0.00":number_format($r['TotalAmount']/$r["TotalCount"],2);
        }
        $model->getFiled($res,"DepartmentID","departments","Name","DepartmentName");
        $this->showJson(1,'ok',$res);
    }
    /**
     * 按客户统计
     */
    public function statCustomAction()
    {
        $StartDate = $this->_getParam('StartDate',date("Y-m-01"));
        $EndDate = $this->_getParam('EndDate',date("Y-m-d"));
        $AdminID = (int)$this->_getParam("AdminID",0);
        $WeixinID = (int)$this->_getParam("WeixinID",0);
        $DepartmentID = (int)$this->_getParam("DepartmentID",0);
        $model = new Model_Orders();
        $select = $model->fromSlaveDB()->select()->setIntegrityCheck(false);
        $select->from("orders as o",["sum(TotalAmount) as TotalAmount","count(o.OrderID) as TotalCount","OrderDate"]);
        $select->joinLeft("weixins_view as w","w.Weixin = o.Seller",["Weixin","Nickname"]);
        $select->joinLeft("admins as a","o.AdminID = a.AdminID",["ifnull(Username,'') as AdminName","DepartmentID"]);
        $select->joinLeft("devices as d","w.DeviceID = d.DeviceID","SerialNum");
        $select->joinLeft("stats as s","w.WeixinID = s.WeixinID and s.Date = OrderDate",
            ["sum(FriendNum) as FriendNum","sum(AddFriendNum) as AddFriendNum"]);
        $select->columns(new Zend_Db_Expr("sum(if(o.OrderDate = o.BuyerAddTime,o.TotalAmount,0)) as NewTotalAmount"));
        $select->columns(new Zend_Db_Expr("sum(if(o.OrderDate = o.BuyerAddTime,1,0)) as NewTotalCount"));
        $select->columns(new Zend_Db_Expr("count(distinct if(o.OrderDate = o.BuyerAddTime,Buyer,null)) as NewCustomCount"));
        if ($AdminID > 0){
            $select->where("o.AdminID = ?",$AdminID);
        }
        if ($WeixinID > 0){
            $select->where("w.WeixinID = ?",$WeixinID);
        }
        if ($DepartmentID > 0){
            $select->where("a.DepartmentID = ?",$DepartmentID);
        }
        $CompanyId = $this->admin["CompanyId"];
        $select->where("o.CompanyId = ?",$CompanyId);
        $select->where("o.Status != 9");
        $select->where("OrderDate >= ?",$StartDate);
        $select->where("OrderDate <= ?",$EndDate);
        $select->group(["w.WeixinID","o.OrderDate"]);
//        var_dump($select->__toString());exit();
        $res = $select->query()->fetchAll();
        foreach ($res as &$r) {
            $r["OldTotalAmount"] = $r["TotalAmount"] - $r["NewTotalAmount"];
            $r["OldTotalCount"] = $r["TotalCount"] - $r["NewTotalCount"];
            $r["AddFriendNum"] = $r["AddFriendNum"]==0?$r["NewTotalCount"]:$r["AddFriendNum"];
            $r["OldFriendNum"] = $r["FriendNum"] - $r["AddFriendNum"];
            $r["OldAvg"] = $r["OldFriendNum"]==0?"0.00":number_format($r["OldTotalAmount"]/$r["OldFriendNum"],2);
            $r["NewRate"] = $r["NewCustomCount"]==0?0:floor($r["NewTotalCount"]/$r["NewCustomCount"]*100);
        }
        $model->getFiled($res,"DepartmentID","departments","Name","DepartmentName");
        $this->showJson(1,'ok',$res);
    }
    /**
     * 按销售统计
     */
    public function statSaleAction()
    {
        $StartDate = $this->_getParam('StartDate',date("Y-m-01"));
        $EndDate = $this->_getParam('EndDate',date("Y-m-d"));
        $CategoryID = $this->_getParam('CategoryID',0);
        $IsDepartment = (int)$this->_getParam("IsDepartment",0);
        $DepartmentID = (int)$this->_getParam("DepartmentID",0);
        $model = new Model_Orders();
        $select = $model->fromSlaveDB()->select()->setIntegrityCheck(false);
        $select->from("orders as o",["sum(TotalAmount) as TotalAmount","count(o.OrderID) as TotalCount","OrderDate"]);
        $select->joinLeft("weixins_view as w","w.Weixin = o.Seller",["Weixin","Nickname"]);
        $select->joinLeft("admins as a","o.AdminID = a.AdminID",["ifnull(Username,'') as AdminName","DepartmentID"]);
        $select->joinLeft("stats as s","w.WeixinID = s.WeixinID and s.Date = OrderDate",
            ["sum(FriendNum) as FriendNum","sum(AddFriendNum) as AddFriendNum"]);
        $select->columns(new Zend_Db_Expr("sum(if(o.OrderDate = o.BuyerAddTime,o.TotalAmount,0)) as NewTotalAmount"));
        $select->columns(new Zend_Db_Expr("sum(if(o.OrderDate = o.BuyerAddTime,1,0)) as NewTotalCount"));
        $select->columns(new Zend_Db_Expr("count(distinct if(o.OrderDate = o.BuyerAddTime,Buyer,null)) as NewCustomCount"));
        if ($CategoryID > 0){
            $select->where("o.CategoryID = ?",$CategoryID);
        }
        if ($DepartmentID > 0){
            $select->where("a.DepartmentID = ?",$DepartmentID);
        }
        $CompanyId = $this->admin["CompanyId"];
        $select->where("o.CompanyId = ?",$CompanyId);
        $select->where("o.Status != 9");
        $select->where("OrderDate >= ?",$StartDate);
        $select->where("OrderDate <= ?",$EndDate);
        $order = [];
        $group = [];
        if($IsDepartment > 0){
            $order[] = "a.DepartmentID asc";
            $group[] = "a.DepartmentID";
        }
        $order[] = "o.AdminID asc";
        $order[] = "o.OrderDate asc";
        $group[] = "o.OrderDate";
        $select->group($group);
        $select->order($order);
//        var_dump($select->__toString());exit();
        $res = $select->query()->fetchAll();
        foreach ($res as &$r) {
            $r["OldTotalAmount"] = $r["TotalAmount"] - $r["NewTotalAmount"];
            $r["OldTotalCount"] = $r["TotalCount"] - $r["NewTotalCount"];
            $r["NewFriendNum"] = $r["AddFriendNum"] < $r["NewCustomCount"]?$r["NewCustomCount"]:$r["AddFriendNum"];
            $r["OldFriendNum"] = $r["FriendNum"] - $r["AddFriendNum"];
            unset($r["AddFriendNum"]);

            $r['OrderAvg'] = $r["TotalCount"]==0?"0.00":number_format($r["TotalAmount"]/$r["TotalCount"],2);
            $r['OldOrderAvg'] = $r["OldTotalCount"]==0?"0.00":number_format($r["OldTotalAmount"]/$r["OldTotalCount"],2);
            $r['NewOrderAvg'] = $r["NewTotalCount"]==0?"0.00":number_format($r["NewTotalAmount"]/$r["NewTotalCount"],2);

            $r['NewOrderRate'] = $r["NewFriendNum"]==0?"0.00":number_format($r["NewTotalAmount"]/$r["NewFriendNum"],2);
            $r['OldOrderRate'] = $r["OldFriendNum"]==0?"0.00":number_format($r["OldTotalAmount"]/$r["OldFriendNum"],2);
            $r['NewCustomRate'] = $r["NewFriendNum"]==0?"0.00":number_format($r["NewTotalCount"]/$r["NewFriendNum"],2);
        }
        $model->getFiled($res,"DepartmentID","departments","Name","DepartmentName");
        $this->showJson(1,'ok',$res);
    }
}