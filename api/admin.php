<?php
include "headers.php";

class Admin
{

} //user


$json = isset($_POST["json"]) ? $_POST["json"] : "0";
$operation = isset($_POST["operation"]) ? $_POST["operation"] : "0";

$admin = new Admin();

// switch ($operation) {
//   case "getPendingPost":
//     echo $admin->getPendingPost();
//     break;
// }
