<?php
include 'db.php';

// Logout user
session_start();
session_destroy();

header('Location: ../index.php');
exit();
?>
