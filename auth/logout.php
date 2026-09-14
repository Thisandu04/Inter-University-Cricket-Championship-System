<?php
session_start();
session_unset();
session_destroy();
header("Location: /inter-university-cricket-tournament/auth/login.php");
exit;