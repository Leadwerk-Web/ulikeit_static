<?php
$s = "LÃ¤den fÃ¼r HÃ¤ndler â€“ schlieÃŸen";
var_dump(function_exists('iconv'));
$t = iconv('UTF-8', 'Windows-1252//IGNORE', $s);
var_dump($t);
?>
