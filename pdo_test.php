<?php
//测试
header('content-type:text/html;charset=utf-8');
require_once('pdoMysql.class.php');
$config = require_once('config.php');
$db = PdoMysql::getInstance($config['db']);

//创建数据库
$create_sql = <<<EOF
CREATE TABLE IF NOT EXISTS student (
id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
name VARCHAR(50) NOT NULL,
sex TINYINT UNSIGNED NOT NULL
)ENGINE = InnoDB DEFAULT CHARSET=utf8
EOF;
$db->query($create_sql);

//插入数据
//$insert_arr = array(
//    array(
//        'name' => 'tom',
//        'sex' => 1
//    ),
//    array(
//        'name' => 'kity',
//        'sex' => 0
//    ),
//    array(
//        'name' => 'sam',
//        'sex' => 0
//    ),
//);
//
//foreach ($insert_arr as $insert){
//    $db->insert('student',$insert);
//}

//getAll获取数据
$sql = "SELECT * FROM student WHERE id>:id";
$id = 1;
//$db->bindValue(':id', $id);
//$res = $db->getAll($sql);
$res = $db->getAll($sql,array(':id'=>$id));
echo 'getAll()<br>';
echo $db->getLastSql();
var_dump($res);

//findAll获取数据
$id = 1;
//$res = $db->findAll('student', "id>{$id}", 'name');//$id这样不太安全
$res = $db->findAll('student', array("id>:id", array(':id' => 2)), 'name');
echo '<hr>';
echo 'findAll()<br>';
echo $db->getLastSql();
var_dump($res);

//getRow获取数据
$sql = "SELECT * FROM student WHERE id>:id and id<:id2";
$id = 1;
$id2=3;
$db->bindMultiValue(array(
    ':id'=>$id,
    ":id2"=>$id2
));
$res = $db->getRow($sql);
echo '<hr>';
echo 'getRow()<br>';
echo $db->getLastSql();
var_dump($res);

//find获取数据
$id = 2;
//$res = $db->findAll('student', "id>{$id}", 'name');//$id这样不太安全
$res = $db->find('student', array("id>:id", array(':id' => 2)), 'name');
echo '<hr>';
echo 'find()<br>';
echo $db->getLastSql();
var_dump($res);

//insert，顺便获取插入的值
$insert_data = array('name' => 'sj', 'sex' => 1);
$return = $db->insert('student', $insert_data);//返回值即为最后插入的数值
$insert_id=$db->getLastInsertId();//同样也可以用方法获取
echo '<hr>';
echo 'insert()<br>';
echo $db->getLastSql();
var_dump($return);
var_dump($insert_id);

//update,顺便测试影响的行数
$update_data = array('name' => 'update');
$update_where = array("name=:name", array(':name' => 'sj'));
$return=$db->update('student',$update_data,$update_where);
echo '<hr>';
echo 'update()<br>';
echo $db->getLastSql();
$affect_num=$db->getAffectNum();
var_dump($affect_num);

//delete,删除
$delete_where = array("name=:name", array(':name' => 'update'));
$return =$db->delete('student',$delete_where);
echo '<hr>';
echo 'delete()<br>';
echo $db->getLastSql();
$affect_num=$db->getAffectNum();
var_dump($affect_num);

//简单的join方法等
$sql="SELECT a.*,b.name AS name2 FROM student AS a JOIN student AS b ON a.id=b.id";
$res=$db->getAll($sql);
echo '<hr>';
echo '复杂语句<br>';
echo $db->getLastSql();
var_dump($res);