<?php

/**
 * Created by PhpStorm.
 * User: Tim
 * Date: 2018/4/25
 * Time: 0:06
 */
class Model_Orders extends DM_Model
{
    public static $table_name = "orders";
    protected $_name = "orders";
    protected $_primary = "OrderID";

    /**
     * OrderNo date . count+1
     * @param $CategoryID
     * @throws Zend_Db_Select_Exception
     * @return string
     */
    public function getOrderNo($CategoryID)
    {
        $date = date("Ymd");
        $select = $this->select()->from($this->_name,"count(*) as count");
        $select->columns(new Zend_Db_Expr(
            "(select Identify from order_goods_categories where CategoryID = {$CategoryID}) as Identify"));
        $select->where("DATE_FORMAT(CreateTime,'%Y%m%d') = ?",$date);
        $select->limit(1);
//        var_dump($select->__toString());exit();
        $res = $select->query()->fetch();
        $count = $res["count"] + 1;
        return $res['Identify'].$date.sprintf('%04d',$count);
    }

    /**
     * 获取前后两天的订单数/订单总金额
     */
    public function getTheseDayData($weixins,$sartDate,$endDate)
    {
        $select = $this->fromSlaveDB()->select()->from($this->_name,['COUNT(OrderID) as OrderNum','SUM(TotalAmount) as TotalAmount','OrderDate']);
        $select->where('Seller in (?)',$weixins);
        $select->where('OrderDate >= ?',$sartDate);
        $select->where('OrderDate <= ?',$endDate);
        $select->group('OrderDate');
        $select->order('OrderDate Desc');
        $data = $this->_db->fetchAll($select);

        $res = [];

        foreach ($data as $d){
            $res[$d['OrderDate']] = $d;
        }
        return $res;
    }


    /**
     * 获取当天下单人数
     * @param $OrderDate
     */
    public function getTheDayBuyerNum($OrderDate)
    {
        $select = $this->fromSlaveDB()->select()->from($this->_name,['COUNT(Buyer) as BuyerNum']);
        $select->where('OrderDate = ?',$OrderDate);
        $select->group('Buyer');
        $data = $this->_db->fetchRow($select);
        return $data == false?0:$data['BuyerNum'];
    }

    /**
     * 统计表统计使用
     *
     * @param $weixins
     * @param $sartDate
     * @param $endDate
     * @return array
     */
    public function getOrderStat($weixins,$sartDate,$endDate)
    {
        $select = $this->fromSlaveDB()->select()->from($this->_name,["DATE_FORMAT(OrderDate,'%Y-%m-%d') as OrderDate",'Seller','Buyer','OrderDate','TotalAmount']);
        $select->where('Seller in (?)',$weixins);
        $select->where('OrderDate >= ?',$sartDate);
        $select->where('OrderDate <= ?',$endDate);
        $data = $this->_db->fetchAll($select);

        $buyer = [];

        foreach ($data as $d){
            if (!in_array($d['Buyer'],$buyer)){
                $buyer[] = $d['Buyer'];
            }
        }
        return ['Result'=>$data,'Buyer'=>$buyer];
    }


}