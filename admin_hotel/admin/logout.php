<?php
require __DIR__ . '/app.php';
$_SESSION = [];
session_destroy();
redirect('login.php');
