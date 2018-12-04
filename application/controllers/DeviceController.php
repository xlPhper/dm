<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/7/16
 * Time: 13:40
 */
class DeviceController extends DM_Controller
{

    public function listAction()
    {
        $online = $this->_getParam('Online', null);
    }

    public function editAction()
    {
        // DeviceNO  SerialNum
        $deviceNo = trim($this->_getParam('DeviceNO', ''));
        $serialNum = trim($this->_getParam('SerialNum', ''));

        if ($deviceNo === '') {
            $this->showJson(0, '设备号非法');
        }
        if ($serialNum === '') {
            $this->showJson(0, '编号非法');
        }

        try {
            (new Model_Device())->update(['SerialNum' => $serialNum], ['DeviceNO = ?' => $deviceNo]);
        } catch (\Exception $e) {
            $this->showJson(0, '更新失败,err:'.$e->getMessage());
        }

        $this->showJson(1, '更新成功');
    }

    /**
     * 设备暂停
     */
    public function pauseAction()
    {
        $deviceNo = trim($this->_getParam('DeviceNO', ''));
        if ($deviceNo === '') {
            $this->showJson(0, '设备号非法');
        }

        try {
            (new Model_Device())->update(['Status' => 'PAUSE'], ['DeviceNO = ?' => $deviceNo]);
        } catch (\Exception $e) {
            $this->showJson(0, '暂停失败,err:'.$e->getMessage());
        }

        $this->showJson(1, '暂停成功');
    }

    /**
     * 设备启动
     */
    public function runAction()
    {
        $deviceNo = trim($this->_getParam('DeviceNO', ''));
        if ($deviceNo === '') {
            $this->showJson(0, '设备号非法');
        }

        try {
            (new Model_Device())->update(['Status' => 'RUNNING'], ['DeviceNO = ?' => $deviceNo]);
        } catch (\Exception $e) {
            $this->showJson(0, '启动失败,err:'.$e->getMessage());
        }

        $this->showJson(1, '启动成功');
    }

    /**
     * 设备异常
     */
    public function exceptAction()
    {
        $deviceNO = trim($this->_getParam('DeviceNO',null));
        $message = trim($this->_getParam('ExecptMessage', null));
        if ($deviceNO == null) {
            $this->showJson(0, '设备号非法');
        }

        $device_model = new Model_Device();

//        RUNNING
        try {
            $device_model->update(['Status' => 'EXCEPT','ExceptMessage' => $message], ['DeviceNO = ?' => $deviceNO]);
        } catch (\Exception $e) {
            $this->showJson(0, '设置异常失败,err:'.$e->getMessage());
        }

        $this->showJson(1, '设置异常成功');
    }
}