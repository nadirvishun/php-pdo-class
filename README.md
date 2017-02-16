# php-pdo-class
 ### 说明
 最初是按照幕客网中[《PDO—数据库抽象层》](http://www.imooc.com/learn/164)中课程所编写的pdo封装，在此基础上又做了改进，但仍然是简单的封装。
 ### 相关方法
 - 获取对象
 ``` 
 require_once('pdoMysql.class.php');
 $config = require_once('config.php');
 $db = PdoMysql::getInstance($config['db']);
 ```
 - 增insert()，成功返回自增的行数，失败返回false
 ```
 $insert_data = array('name' => 'sj', 'sex' => 1);
 $db->insert('student', $insert_data);
 ```
 - 修update(),成功true,失败false
 ```
 $update_data = array('name' => 'update');
 $name='sj';
 //$update_where="name={$name}";//字符串方式，简单但是有注入风险
 $update_where = array("name=:name", array(':name' => $name));//预处理绑定参数方式
 $db->update('student',$update_data,$update_where);
 ```
 - 删delete(),成功true,失败false
 ```
  $delete_where = array("name=:name", array(':name' => 'update'));
  $db->delete('student',$delete_where);
  ```
 - 查getAll(),getRow(),findAll(),find(),其中find语句是对get语句的进一步简单封装，
  ```
  //getAll()
  $sql = "SELECT * FROM student WHERE id>:id";
  $id = 1;
  $db->bindValue(':id', $id);//绑定一个参数
  $db->getAll($sql);
  //findAll()
  $res = $db->findAll('student', array("id>:id", array(':id' => 2)), 'name');
  //getRow()
  $sql = "SELECT * FROM student WHERE id>:id AND id <:id2";
  $id = 1;
  $id2 = 3;
  $db->bindMultiValue(array(':id'=>$id,":id2"=>$id2));//绑定多个参数
  $res = $db->getRow($sql);
  //find()
  $res = $db->find('student', array("id>:id", array(':id' => 2)), 'name');
  ```
  - 其它常用方法
  ```
  $db->bindValue(':id', $id);//绑定单个参数，可多次调用
  $db->bindMultiValue(array(':id'=>$id,":id2"=>$id2));//绑定多个参数
  $db->getLastSql();//获取最后查询的sql语句
  $db->getLastInsertId();//获取insert插入的自增ID
  $db->getAffectNum();//获取受影响的条数
  ``