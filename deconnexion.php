<?php
session_start();
session_unset(); // Unset all session variables
session_destroy(); // Destroy the session
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0"); // Prevents caching
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
header("Pragma: no-cache");
header("Location: connection.php"); // Redirect to login page
exit;
?>