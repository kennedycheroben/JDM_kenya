<?php
require_once dirname(__FILE__) . '/../../core/db_connect.php';

session_unset();
session_destroy();
header('Location: login.php');
exit;
