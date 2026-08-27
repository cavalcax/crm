<?php
require_once 'config/db.php';
require_once 'helpers/functions.php';

if (isLoggedIn()) {
    header("Location: admin/index.php");
    exit;
}
else {
    header("Location: login.php");
    exit;
}