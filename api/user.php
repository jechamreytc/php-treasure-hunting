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

    function findRoom($json)
    {
      // {"room_code": "T6456"}
      include "connection.php";
      $json = json_decode($json, true);
      $sql = "SELECT * FROM tbl_room WHERE room_code = :room_code";
      $stmt = $conn->prepare($sql);
      $stmt->bindParam(':room_code', $json['room_code']);
      $stmt->execute();
      return $stmt->rowCount() > 0 ? json_encode($stmt->fetch(PDO::FETCH_ASSOC)) : 0;
    }

    function joinRoom($json){
      // {"team_roomId": "7", "team_userId": "1", "team_name": "Team mo to"}
      include "connection.php";
      $json = json_decode($json, true);
      $sql = "INSERT INTO tbl_team_participants(team_roomId, team_userId, team_name, team_status) 
      VALUES ( :team_roomId, :team_userId, :team_name, 0)";
      $stmt = $conn->prepare($sql);
      $stmt->bindParam(':team_roomId', $json['team_roomId']);
      $stmt->bindParam(':team_userId', $json['team_userId']);
      $stmt->bindParam(':team_name', $json['team_name']);
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
    case "findRoom":
      echo $user->findRoom($json);
      break;
    case "joinRoom":
      echo $user->joinRoom($json);
      break;
  }
