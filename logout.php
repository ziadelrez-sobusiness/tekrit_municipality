<?php
require_once 'includes/auth.php';

$auth = new Auth();
$auth->logout();

header('Location: public/index.php');
exit();
?> 
