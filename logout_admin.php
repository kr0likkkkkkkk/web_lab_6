<?php
session_start();
session_destroy();
header('HTTP/1.1 401 Unauthorized');
header('Location: admin.php');
exit();
?>