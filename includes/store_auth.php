<?php
require_once __DIR__.'/auth.php';
if(!in_array($_SESSION['role_name']??'', ['Store','Super Admin'], true)){header('Location: ../index.php');exit;}
