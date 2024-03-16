 <?php
  include "headers.php";

  class User
  {

  } //user

  

  $json = isset($_POST["json"]) ? $_POST["json"] : "0";
  $operation = isset($_POST["operation"]) ? $_POST["operation"] : "0";

  $user = new User();

  // switch ($operation) {
  //   case "login":
  //     echo $user->login($json);
  //     break;
  // }
