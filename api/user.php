 <?php
  include "headers.php";

  class User
  {
    function createRoom($json)
    {
      // {"room_userId": "1", "room_name": "test", "room_description": "test" }
      include "connection.php";
      $json = json_decode($json, true);
      $roomName = $json['room_name'];
      $randomNumber = rand(1, 9999);
      $passCode = trim(ucfirst($roomName))[0] . $randomNumber;
      $sql = "INSERT INTO tbl_room(room_userId, room_name, room_description, room_code) 
      VALUES ( :room_userId, :room_name, :room_description, :room_code)";
      $stmt = $conn->prepare($sql);
      $stmt->bindParam(':room_userId', $json['room_userId']);
      $stmt->bindParam(':room_name', $roomName);
      $stmt->bindParam(':room_description', $json['room_description']);
      $stmt->bindParam(':room_code', $passCode);
      $stmt->execute();
      return $stmt->rowCount() > 0 ? 1 : 0;
    }
  } //user



  $json = isset($_POST["json"]) ? $_POST["json"] : "0";
  $operation = isset($_POST["operation"]) ? $_POST["operation"] : "0";

  $user = new User();

  switch ($operation) {
    case "createRoom":
      echo $user->createRoom($json);
      break;
  }
