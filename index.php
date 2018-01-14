<?php
$str = "abcdefg";
$len = strlen($str);
$newstr = " ";

for($i=$len; $i>=0; $i--){
	$newstr .=  $str{$i};
}

echo $newstr;
echo "eeee";

?>