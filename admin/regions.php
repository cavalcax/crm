<?php
require_once '../config/db.php';
require_once '../helpers/functions.php';

requireLogin();
header("Location: clients.php");
exit;
?>