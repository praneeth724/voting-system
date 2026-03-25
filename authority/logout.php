<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

session_destroy();
header('Location: ' . BASE_URL . '/authority/login.php');
exit;
