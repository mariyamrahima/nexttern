<?php
$password = "adminmariyam";
$hashed = password_hash($password, PASSWORD_BCRYPT);
echo $hashed;
?>