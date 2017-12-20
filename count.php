<?php
//php 统计数组个数
$arr = array('1011','1009','1011','1011','1009');
$result = array();

  foreach ($arr as $v) {
    $result[$v] = isset($result[$v]) ? $result[$v] : 0;
    $result[$v] = $result[$v] + 1;
  }

  $sum = '';
  foreach ($result as $value){
  	$sum += $value;
  }

print_r($result);
echo '<br >';
print_r($sum);
//php 自带的函数
echo '<br >';
$res = array_count_values($arr);
print_r($res);

?>