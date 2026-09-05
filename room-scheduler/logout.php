<?php
session_start();
session_unset(); // remove all session 
session_destroy(); // destroy destroy get wrecked
header("Location: login.php"); // redirect to login page
exit();
?>
