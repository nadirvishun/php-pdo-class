<?php

/**
 * PDO封装类
 * //todo,改版为链式查询
 */
class PdoMysql
{
    protected $config = array();//链接参数配置信息
    protected $pdo = null;//保存链接标识符
    protected $persistent = false;//是否开启长连接
    protected $PDOStatement = null;//保存PDOStatement对象
    protected $queryStr = null;//保存最后的执行操作
    protected $primaryKey = 'id';//主键
    protected $fetchModel = null;//默认fetch获取方式
    protected $bindArr = array();
    protected static $_instance = null;//实例

    /**
     * 构造函数,私有方法,防止实例化,单例模式
     * @param array $dbConfig
     */
    private function __construct($dbConfig = array())
    {
        //判定pdo扩展是否开启
        if (!class_exists("PDO")) {
            $this->throw_exception('不支持PDO，请先开启');
        }
        if (empty($dbConfig['host'])) {
            $this->throw_exception('没有定义数据库配置，请先定义');
        }
        $this->config = $dbConfig;
        //组装dsn
        $this->config['dsn'] = $this->config['dbms'] . ":host=" . $this->config['host'] . ";dbname=" . $this->config['database'];
        //额外参数
        if (empty($this->config['params'])) {
            $this->config['params'] = array();
        }
        if (!isset($this->pdo)) {
            if ($this->persistent) {
                //开启长连接
                $this->config['params'][constant('PDO::ATTR_PRESISTENT')] = true;
            }
            try {
                $this->pdo = new PDO($this->config['dsn'], $this->config['username'], $this->config['password'], $this->config['params']);
                if (!$this->pdo) {
                    $this->throw_exception('PDO链接错误');
                    return false;
                }
            } catch (PDOException $e) {
                $this->throw_exception($e->getMessage());
            }
            //设置字符编码
            $charset = empty($this->config['charset']) ? 'UTF8' : $this->config['charset'];
            $this->pdo->exec('SET NAMES ' . $charset);
        }
    }

    /**
     * 私有方法,防止克隆
     */
    private function __clone()
    {

    }

    /**
     * 静态方法获取实例
     * @param $dbConfig
     * @return PdoMysql
     */
    public static function getInstance($dbConfig)
    {
        if (self::$_instance == null) {
            self::$_instance = new self($dbConfig);
        }
        return self::$_instance;
    }

    /**
     * 获取全部数据,用于select方法
     * 当param传递参数时，单独调用bindValue方法和bindMultiValue方法无效
     * @param null $sql
     * @param null $param
     * @return mixed
     */
    public function getAll($sql = null, $param = null, $fetchModel = PDO::FETCH_ASSOC)
    {
        if (empty($sql)) {
            $this->throw_exception('数据库语句为空');
        } else {
            $this->query($sql, $param);
        }
        $result = $this->PDOStatement->fetchAll($fetchModel);
        return $result;
    }

    /**
     * 获取单条数据,用于select方法
     * 当param传递参数时，单独调用bindValue和bindMultiValue方法无效
     * @param null $sql
     * @param null $param
     * @return mixed
     */
    public function getRow($sql = null, $param = null, $fetchModel = PDO::FETCH_ASSOC)
    {
        if (empty($sql)) {
            $this->throw_exception('数据库语句为空');
        } else {
            $this->query($sql, $param);
        }
        $result = $this->PDOStatement->fetch($fetchModel);
        return $result;
    }

    /**
     * query查询,获取PDOStatement集合
     * 当param传递参数时，单独调用bindValue和bindMultiValue无效
     * @param string $sql
     * @param null $param 参数格式与bindMultiValue方法的参数一致
     * @return bool
     */
    public function query($sql = null, $param = null)
    {
        //判断之前是否有结果集
        if (!empty($this->PDOStatement)) {
            $this->free();
        }
        $this->queryStr = $sql;
        $this->PDOStatement = $this->pdo->prepare($this->queryStr);
        if (!empty($param)) {
            $this->clearBindValue();
            $this->bindMultiValue($param);
        }
        //绑定参数
        if (!empty($this->bindArr)) {
            foreach ($this->bindArr as $value) {
                $this->PDOStatement->bindValue($value[0], $value[1], $value[2]);
            }
        }
        $res = $this->PDOStatement->execute();
        $this->clearBindValue();//将绑定的参数清空
        $this->haveErrorThrowException();
        return $res;
    }

    /**
     * 绑定单个参数
     * @param $key
     * @param $value
     * @param $type
     */
    public function bindValue($key, $value, $type = null)
    {
        if (empty($type)) {
            if (is_int($value)) {
                $type = PDO::PARAM_INT;
            } elseif (is_bool($value)) {
                $type = PDO::PARAM_BOOL;
            } elseif (is_null($value)) {
                $type = PDO::PARAM_NULL;
            } else {
                $type = PDO::PARAM_STR;
            }
        }
        $this->bindArr[] = array($key, $value, $type);
    }

    /**
     * 一次性绑定多个参数
     * @param array $data 格式：array(':id'=>1,":username"=>'abc')
     * 注：绑定前是否先调用clearBindValue比较好，还是与单个共用比较好，目前是共用
     */
    public function bindMultiValue($data)
    {
        if (!empty($data)) {
            foreach ($data as $key => $value) {
                $this->bindValue($key, $value);
            }
        }
    }

    /**
     * 清空绑定参数
     */
    public function clearBindValue()
    {
        $this->bindArr = array();
    }

    /**
     * 设置获取模式
     * 缺点就是设置后以后所有的都会是这个模式，所以查询完成后需要再设置下模式下，且像是PDO::FETCH_COLUMN等是多个参数的，用PDOStatement::setFetchMode有问题
     * 另一种方法是作为参数在query中，但这样缺点就是用的query的封装方法都要多这个参数，很别扭
     * 目前采用第二种方法，此方法暂无作用
     * @param  $fetchModel
     */
    public function setFetchMode($fetchModel = null)
    {
        $this->fetchModel = $fetchModel;
    }

    /**
     * 获取基本拆分参数的全部数据
     * 缺陷：只能获取关联数组，不能使用join操作，如果需要join，可以直接用getAll方法
     * @param $tableName
     * @param null $where
     * @param string $fields
     * @param null $order
     * @param null $limit
     * @param null $group
     * @param null $having
     * @return mixed
     */
    public function findAll($tableName, $where = null, $fields = '*', $order = null, $limit = null, $group = null, $having = null)
    {
        $this->clearBindValue();
        $sql = 'SELECT ' . $this->parseField($fields) . ' FROM ' . $tableName
            . $this->parseWhere($where)
            . $this->parseGroup($group)
            . $this->parseHaving($having)
            . $this->parseOrder($order)
            . $this->parseLimit($limit);
        return $this->getAll($sql);
    }

    /**
     * 获取基本拆分参数的单条数据
     * 缺陷：只能获取关联数组，不能使用join操作，如果需要join，可以直接用getRow方法
     * @param $tableName
     * @param null $where
     * @param string $fields
     * @param null $order
     * @param null $limit
     * @param null $group
     * @param null $having
     * @return mixed
     */
    public function find($tableName, $where = null, $fields = '*', $order = null, $limit = null, $group = null, $having = null)
    {
        $this->clearBindValue();
        $sql = 'SELECT ' . $this->parseField($fields) . ' FROM ' . $tableName
            . $this->parseWhere($where)
            . $this->parseGroup($group)
            . $this->parseHaving($having)
            . $this->parseOrder($order)
            . $this->parseLimit($limit);
        return $this->getRow($sql);
    }

    /**解析WHERE
     * @param $where
     * 参数传递方式：
     * 1、string 例如："id=2 and username='abc'";
     * 2、array 例如：（太繁琐，但是如果想优化也不好优化，除非改成链式，可多次where）
     * array(
     *  'id=:id and username=:username',//预处理语句
     *  array(//绑定的参数
     *      ':id'=>2,
     *      ':username'=>'abc'
     *  )
     * )
     * 等同于id=:id and username=:username，第二个数组为绑定的参数
     * 主要是为了可以预处理绑定参数，防止sql注入，
     * @return string
     */
    public function parseWhere($where)
    {
        $whereStr = '';
        if (is_string($where) && !empty($where)) {
            $whereStr .= $where;
        } elseif (is_array($where) && !empty($where)) {
            $whereStr .= $where[0];
            foreach ($where[1] as $key => $value) {
                $this->bindValue($key, $value);
            }
        }
        return empty($whereStr) ? '' : " WHERE " . $whereStr;
    }

    /**
     * 解析GROUP
     * @param $group
     * 参数示例，方法1：'id'，等同于 GROUP BY id
     * 方法2：array(id，name)等同于GROUP BY id,name
     * @return string
     */
    public function parseGroup($group)
    {
        $groupStr = '';
        if (is_array($group)) {
            $groupStr .= implode(',', $group);
        } elseif (is_string($group)) {
            $groupStr .= $group;
        }
        return empty($groupStr) ? '' : " GROUP BY " . $groupStr;
    }

    /**
     * 解析HAVING(纯字符串的having)
     * @param $having
     * 参数传递方式：
     * 1、string 例如："id=2 and username='abc'";
     * 2、array 例如：（太繁琐，但是如果想优化也不好优化，除非改成链式，可多次where）
     * array(
     *  'id=:id and username=:username',
     *  array(
     *      ':id'=>2,
     *      ':username'=>'abc'
     *  )
     * )
     * 等同于id=:id and username=:username，第二个数组为绑定的参数
     * 主要是为了可以预处理绑定参数，防止sql注入，
     * @return string
     */
    public function parseHaving($having)
    {
        $havingStr = '';
        if (is_string($having) || !empty($having)) {
            $havingStr .= $having;
        } elseif (is_array($having) && !empty($having)) {
            $havingStr .= $having[0];
            foreach ($having[1] as $key => $value) {
                $this->bindValue($key, $value);
            }
        }
        return empty($havingStr) ? '' : " HAVING " . $havingStr;
    }

    /**
     * 解析ORDER BY
     * @param $order
     * 参数示例，方法1：'id DESC',等同于ORDER BY id DESC
     * 方法2：array('id DESC','name DESC'),等同于ORDER BY id DESC,name DESC
     * @return string
     */
    public function parseOrder($order)
    {
        $orderStr = '';
        if (is_array($order)) {
            $orderStr .= implode(',', $order);
        } elseif (is_string($order)) {
            $orderStr .= $order;
        }
        return empty($orderStr) ? '' : " ORDER BY " . $orderStr;
    }

    /**
     * 解析LIMIT
     * @param $limit
     * 参数示例，方法1：'10'，等同于limit(10)
     * 方法2：array(10，20)，等同于limit(10，20)
     * @return string
     */
    public function parseLimit($limit)
    {
        $limitStr = '';
        if (is_array($limit)) {
            if (count($limit) > 1) {
                $limitStr .= $limit[0] . ',' . $limit[1];
            } else {
                $limitStr .= $limit[0];
            }
        } elseif (is_string($limit) || is_numeric($limit)) {
            $limitStr .= $limit;
        }
        return empty($limitStr) ? '' : " LIMIT " . $limitStr;
    }

    /**
     * 根据主键来查询
     * 没什么意义，因为主键要关联具体的数据表，所以此方法写在这边并不合适
     * @param $tabName
     * @param $priID
     * @param string $fields
     * @return mixed
     */
    public function findById($tabName, $priID, $fields = '*')
    {
        $sql = "SELECT %s FROM %s WHERE " . $this->primaryKey . "=%d";
        return $this->getRow(sprintf($sql, $this->parseField($fields), $tabName, $priID));
    }

    /**
     * 设置主键
     * 同上
     * @param string $key
     */
    public function setPrimaryKey($key = 'id')
    {
        $this->primaryKey = $key;
    }

    /**
     * 解析字段
     * @param $fields
     * 方法一：'id,username'
     * 方法二：array('id','username')，相当于select id,username
     * @return string
     */
    public function parseField($fields)
    {
        //如果是数组,则转转换为字符串
        if (is_array($fields) && !empty($fields)) {
            $fieldsStr = implode(',', $fields);
        } elseif (is_string($fields) && !empty($fields)) {
            $fieldsStr = $fields;
        } else {
            $fieldsStr = '*';
        }
        return $fieldsStr;
    }

    /**
     * 增删改操作,返回受影响的条数
     * 不建议使用，有sql注入危险
     * @param null $sql
     * @return bool|int
     */
    public function execute($sql = null)
    {
        if (empty($sql)) {
            $this->throw_exception('数据库语句为空');
        } else {
            $this->queryStr = $sql;
        }
        if (!empty($this->PDOStatement)) {//将结果集清空,方便返回$this->pdo的错误信息
            $this->free();
        }
        $result = $this->pdo->exec($this->queryStr);
        $this->haveErrorThrowException();
        if ($result === false) {
            return false;
        } else {
            return $result;
        }
    }

    /**
     * 写入数据
     * @param $tableName
     * @param array $data
     * @return bool 成功返回插入的最后ID，失败返回false
     */
    public function insert($tableName, $data = array())
    {
        //下面的简单封装并不能阻止sql注入，除非再加过滤，所以下面修改为预处理的形式
//        $sql = '';
//        if (is_array($data) && !empty($data)) {
//            $keys = array();
//            $values = array();
//            foreach ($data as $key => $value) {
//                $keys[] = $key;
//                $values[] = $value;
//            }
//            $sql = "INSERT " . $tableName . "(" . implode(',', $keys) . ") VALUES('" . implode('\',\'', $values) . "')";
//        }
//        return $this->execute($sql);
        $this->clearBindValue();//清空绑定数组
        $sql = '';
        if (is_array($data) && !empty($data)) {
            $keys = array();
            $values = array();
            foreach ($data as $key => $value) {
                $keys[] = $key;
                $values[] = ':' . $key;
                $this->bindValue(':' . $key, $value);
            }
            $sql = "INSERT " . $tableName . "(" . implode(',', $keys) . ") VALUES(" . implode(',', $values) . ")";
        }
        if ($this->query($sql)) {
            $lastInsertId = $this->getLastInsertId();
            return $lastInsertId;
        } else {
            return false;
        }
    }

    /**
     * 更新数据
     * @param $tableName
     * @param array $data
     * @param null $where
     * @param null $order
     * @param null $limit
     * @return bool
     */
    public function update($tableName, $data = array(), $where = null, $order = null, $limit = null)
    {
        //下面的简单封装并不能阻止sql注入，除非再加过滤，所以下面修改为预处理的形式
//        $sql = '';
//        if (is_array($data) && !empty($data)) {
//            $join_arr = array();
//            foreach ($data as $key => $value) {
//                $join_arr[] = $key . "='" . $value . "'";
//            }
//            $sql = "UPDATE " . $tableName . " SET " . implode(',', $join_arr)
//                . $this->parseWhere($where)
//                . $this->parseOrder($order)
//                . $this->parseLimit($limit);
//        }
//        return $this->execute($sql);
        $this->clearBindValue();//清空绑定数组
        $sql = '';
        if (is_array($data) && !empty($data)) {
            $join_arr = array();
            foreach ($data as $key => $value) {
                $join_arr[] = $key . " = :update" . $key;//增加update防止重名
                $this->bindValue(':update' . $key, $value);
            }
            $sql = "UPDATE " . $tableName . " SET " . implode(',', $join_arr)
                . $this->parseWhere($where)
                . $this->parseOrder($order)
                . $this->parseLimit($limit);
        }
        return $this->query($sql);

    }

    /**
     * 删除数据
     * @param $tableName
     * @param null $where
     * @param null $order
     * @param null $limit
     * @return bool|int
     */
    public function delete($tableName, $where = null, $order = null, $limit = null)
    {
        $this->clearBindValue();
        $sql = "DELETE FROM " . $tableName
            . $this->parseWhere($where)
            . $this->parseOrder($order)
            . $this->parseLimit($limit);
        return $this->query($sql);
    }

    /**
     * 开启事务
     */
    public function beginTransaction()
    {
        $this->pdo->beginTransaction();
    }

    /**
     * 开启事务
     */
    public function commit()
    {
        $this->pdo->commit();
    }

    /**
     * 开启事务
     */
    public function rollBack()
    {
        $this->pdo->rollBack();
    }

    /**
     * 获取数据库版本
     */
    public function getDbVersion()
    {
        return $this->pdo->getAttribute(constant("PDO::ATTR_SERVER_VERSION"));
    }

    /**
     * 设定是否开启长久链接
     */
    public function setPersistent($bool)
    {
        $this->persistent = $bool;
    }

    /**
     * 获取数据库中所有表
     */
    public function showTables()
    {
        $tables = array();
        $sql = "SHOW TABLES";
        $result = $this->getAll($sql);
        if (!empty($result)) {
            foreach ($result as $key => $value) {
                $tables[$key] = current($value);//current在foreach中很微妙，需注意使用
            }
        }
        return $tables;
    }

    /**
     *获取受影响的条数
     */
    public function getAffectNum()
    {
        return $this->PDOStatement->rowCount();
    }

    /**
     *获取最后插入的ID
     */
    public function getLastInsertId()
    {
        return $this->pdo->lastInsertId();
    }

    /**
     * 获取最后查询语句
     */
    public function getLastSql()
    {
        return $this->queryStr;
    }

    /**
     * 释放结果集
     */
    private function free()
    {
        $this->PDOStatement = null;
    }

    /**
     * 销毁对象,关闭数据库（没有用到啊）
     */
    public function close()
    {
        $this->pdo = null;
    }

    /**
     * 数据库错误处理方法
     */
    public function haveErrorThrowException()
    {
        $obj = empty($this->PDOStatement) ? $this->pdo : $this->PDOStatement;
        $arrError = $obj->errorInfo();
        if ($arrError[0] !== '00000') {
            $error = "SQL STATE : " . $arrError[0] . "<br>SQL ERROR : " . $arrError[2] .
                '<br> ERROR SQL : ' . $this->queryStr;
            $this->throw_exception($error);
        }
    }

    /**
     * 自定义错误处理方法
     * @param $errMsg
     */
    public function throw_exception($errMsg)
    {
        echo '<div style="width:100%;background:#abcdef;color:#000;font-size:20px;padding:20px 10px">' .
            $errMsg .
            '</div>';
        exit;
    }
}
