<?php
session_start();
session_destroy();
header('Location: ' . 'http://localhost/wecanshop/index.php');
exit;
