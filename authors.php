<?php
ini_set('max_execution_time', -1);
$sql = "SELECT * FROM `kl_users`";
$DBuser = 'root';
$DBpass = $_ENV['MYSQL_ROOT_PASSWORD'];

$database = 'mysql:host=database:3306;dbname=wp_migration';
$dbh = new PDO($database, $DBuser, $DBpass, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));
$rows = $dbh->query($sql, PDO::FETCH_ASSOC);
$res = [];
foreach ($rows->fetchAll() as $row){


    $display_name = $row['display_name'];
        $display_name = explode(" ", $display_name);
        $first_name = '';
        $last_name = '';
        if(isset($display_name[0])) $first_name = $display_name[0];
        if(isset($display_name[1])) $last_name = $display_name[1];
    $res[] = ['slug'=>['ru'=>$row['user_login']], 'username'=>$row['user_login'],
        'first_name'=>['ru'=>$first_name], 'last_name'=>['ru'=>$last_name], 'email'=>$row['user_email'], 'id'=>$row['ID']
        ];
}
file_put_contents('authors.json', json_encode($res, JSON_PRETTY_PRINT));