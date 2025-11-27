<?php
if(isset($_POST['message'])){
    $log_file = 'nhso_log.txt';
    $date = date('Y-m-d H:i:s');
    file_put_contents($log_file,"[$date] ".$_POST['message']."\n",FILE_APPEND);
}
?>
