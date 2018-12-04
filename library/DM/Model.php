<?php

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/24
 * Time: 14:29
 */
//require_once APPLICATION_PATH . '/../library/Zend/Controller/Front.php';

class DM_Model extends Zend_Db_Table
{
    /**
     * @var DM_Model
     */
    protected static $modelInstances = [];
    protected static $table_name = '';

    /**
     * @return DM_Model
     */
    public static function getInstance()
    {
        $tableName = static::$table_name;
        if (!isset(static::$modelInstances[$tableName]) || !static::$modelInstances[$tableName] instanceof static) {
            static::$modelInstances[$tableName] = new static;
        }

        return static::$modelInstances[$tableName];
    }

    public function getTableName()
    {
        return $this->_name;
    }

    public function getAll()
    {
        $select = $this->_db->select();
        $select->from($this->_name);
        return $this->_db->fetchAll($select);
    }


    public function getInfo($id)
    {
        $select = $this->_db->select();
        $select->from($this->_name)
            ->where("{$this->_primary} = ?", $id);
        return $this->_db->fetchRow($select);
    }

    public function getResult($select, $page, $pagesize)
    {
        return $this->_getList($select, $page, $pagesize);
    }

    protected function _getList($select, $page, $pagesize)
    {
        $adapter = new Zend_Paginator_Adapter_DbSelect($select);
        $paginator = new Zend_Paginator($adapter);
        $paginator->setItemCountPerPage($pagesize)
            ->setCurrentPageNumber($page);
        $items = $paginator->getCurrentItems();
        $dataArray = array();
        foreach ($items as $key => $item) {
            $dataArray[$key] = $item;
        }
        $data = array(
            'Page' => $page,
            'Pagesize' => $pagesize,
            'TotalCount' => $paginator->getTotalItemCount(),
            'TotalPage' => ceil($paginator->getTotalItemCount() / $pagesize),
            'Results' => $dataArray
        );
        return $data;
    }

    /**
     * 增加二维数组的一列 来自 表中的一个字段或二维表数组一个字段 优化left join 不作为where条件
     * @param array $source 原始二维数组
     * @param string $sourceField 原始条件字段
     * @param string|array $table 查询的表
     * @param string $returnField 返回字段
     * @param string $aliasField 返回字段的别名 默认$returnField 两者不一致时使用
     * @param string $tableField 在表中的字段 默认$sourceField 两者不一致时使用
     * @return $source -> item + $field
     */
    public function getFiled(&$source, $sourceField, $table, $returnField, $aliasField = "", $tableField = "")
    {
        $res = $table;
        $tableField = empty($tableField) ? $sourceField : $tableField;
        if (!is_array($table)) {
            $inFields = [];
            foreach ($source as $item) {
                $inFields[] = $item[$sourceField];
            }
            $inFields = array_unique($inFields);
            $inWhere = "'" . implode("','", $inFields) . "'";
            $sql = "select {$tableField},{$returnField} from {$table} where {$tableField} in ($inWhere)";
            $res = $this->getHashSlaveDB()->fetchAll($sql);
        }
        $fields = [];
        foreach ($res as $r) {
            $key = $r[$tableField]??"";
            $fields[$key] = $r[$returnField]??"";
        }
        $aliasField = empty($aliasField) ? $returnField : $aliasField;
        foreach ($source as $k => $item) {
            if (!empty($fields[$item[$sourceField]])) {
                $source[$k][$aliasField] = $fields[$item[$sourceField]];
            } else {
                $source[$k][$aliasField] = "";
            }
        }
        return $source;
    }

    /**
     * 通过主键获取
     *
     * @return Zend_Db_Table_Row_Abstract
     */
    public function getByPrimaryId($id)
    {
        return $this->fetchRow(array($this->getPrimary() . " = ? " => $id));
    }

    /**
     * 通过主键获取
     *
     * @return Zend_Db_Table_Row_Abstract
     */
    public function getByPrimaryIdForUpdate($id)
    {
        return $this->fetchRow($this->select()->forUpdate()->where($this->getPrimary() . " = ? ", $id));
    }

    /**
     * 根据多个主键ID获取信息
     *
     * @param array $ids
     * @return
     */
    public function getListByPrimaryId($ids)
    {
        if (empty($ids)) return NULL;
        return $this->fetchAll(array($this->getPrimary() . "  in (?) " => $ids));
    }

    /**
     * 根据多个主键ID获取信息
     *
     * @param array $ids
     * @return
     */
    public function getListByPrimaryIdForUpdate($ids)
    {
        if (empty($ids)) return NULL;
        return $this->fetchAll($this->select()->forUpdate()->where($this->getPrimary() . "  in (?) ", $ids));
    }

    public function getPrimary()
    {
        if (is_array($this->_primary)) {
            $tmpPrimary = $this->_primary;
            return array_pop($tmpPrimary);
        } else {
            return $this->_primary;
        }
    }

    // 设置从库后的备份适配器
    private $_adapterBackup = NULL;

    /**
     * Associative array containing all configured salve db's
     *
     * @var array
     */
    protected $_slaves = array();

    /**
     * slave键值匹配
     */
    protected $_slaveKVs = array();

    /**
     * @var Zend_Db_Adapter_Abstract
     */
    protected $_slaveHashed = NULL;

    /**
     * 是否含有从库
     * @var bool
     */
    protected $_hasSlaveDB = NULL;

    /**
     * 设置从从数据库获取
     *
     * 设置后select调用也同样生效，默认是master数据库
     *
     * @return DM_Model
     */
    public function fromSlaveDB()
    {
        if ($this->_db instanceof Zend_Db_Adapter_Abstract) {
            $this->_adapterBackup = $this->_db;
        }
        $this->_setAdapter($this->getAdapter());
        $this->_setAdapter($this->getHashSlaveDB());

        return $this;
    }

    /**
     * 从主库查询
     */
    public function fromMasterDB()
    {
        $this->_setAdapter($this->getDb());

        return $this;
    }

    /**
     * 调用从库后可以调用restoreOriginalAdapter恢复原来的适配器
     *
     * @return DM_Model
     */
    public function restoreOriginalAdapter()
    {
        if ($this->_adapterBackup instanceof Zend_Db_Adapter_Abstract) {
            $this->_setAdapter($this->_adapterBackup);
        }

        return $this;
    }

    public function getHashSlaveDB()
    {
        if ($this->_slaveHashed !== NULL) {
            return $this->_slaveHashed;
        }

        if (empty($this->_slaves)) {
            $this->_slaves = $this->getBootstrap()->getResource('multidb')->getSlaveDbs();
            $this->_slaveKVs = array_keys($this->_slaves);
        }

        //不存在从数据库 指向master
        if (empty($this->_slaves)) {
            $this->_slaveHashed = $this->getDb();
            return $this->_slaveHashed;
        }

        $hashValue = 0;
        $clientip = Zend_Controller_Front::getInstance()->getRequest()->getClientIp(true);
        if ($clientip) {
            $iplong = abs(ip2long($clientip));
            $hashValue = $iplong;
        } else {
            $pid = getmypid();
            if (!$pid) {
                throw new Exception('Slave: Failed to fetch Server pid.');
            }
            $hashValue = $pid;
        }
        $this->_slaveHashed = $this->_slaves[$this->_slaveKVs[$hashValue % count($this->_slaveKVs)]];
        //手工触发连接，用于判断是否可连
        $this->_slaveHashed->getConnection();
        if (!$this->_slaveHashed->isConnected()) {
            $config = $this->_slaveHashed->getConfig();
            if (isset($config['password'])) $config['password'] = '******';
            $this->_slaveHashed = $this->getDb();
        }

        return $this->_slaveHashed;
    }

    public function getDb($db = NULL)
    {
        return $this->getBootstrap()->getResource('multidb')->getDb($db);
    }

    public function getBootstrap()
    {
        return Zend_Controller_Front::getInstance()->getParam('bootstrap');
    }
}