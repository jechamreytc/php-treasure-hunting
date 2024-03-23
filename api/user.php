<?php
include "headers.php";

class User
{

  function signup($json)
  {
    // {"username": "test", "password": "test"}
    include "connection.php";
    $json = json_decode($json, true);
    $sql = "INSERT INTO tbl_users(user_username, user_password)
      VALUES (:username, :password)";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':username', $json['username']);
    $stmt->bindParam(':password', $json['password']);
    $stmt->execute();
    return $stmt->rowCount() > 0 ? 1 : 0;
  }

  function login($json)
  {
    // {"username": "test", "password": "test"}
    include "connection.php";
    $json = json_decode($json, true);
    $sql = "SELECT * FROM tbl_users WHERE user_username = :username AND  BINARY user_password = :password";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(":username", $json['username']);
    $stmt->bindParam(":password", $json['password']);
    $stmt->execute();
    return $stmt->rowCount() > 0 ? json_encode($stmt->fetch(PDO::FETCH_ASSOC)) : 0;
  }

  function createRoom($json)
  {
    // {"room_name": "test", "room_description": "test" }
    include "connection.php";
    $json = json_decode($json, true);
    $roomName = $json['room_name'];
    $randomNumber = rand(1, 9999);
    $passCode = trim(ucfirst($roomName))[0] . $randomNumber;
      
    $sql = "INSERT INTO tbl_room(room_name, room_description, room_code) 
      VALUES (:room_name, :room_description, :room_code)";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':room_name', $roomName);
    $stmt->bindParam(':room_description', $json['room_description']);
    $stmt->bindParam(':room_code', $passCode);
    $stmt->execute();
    if ($stmt->rowCount() > 0) {
      echo json_encode(["room_code" => $passCode]);
    } else {
      echo "0";
    }
  }

  function getAllRiddles($json)
  {
    
    include "connection.php";
    $json = json_decode($json, true);
    $sql = "SELECT * FROM tbl_riddles WHERE rid_roomId = :rid_roomId";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':rid_roomId', $json['rid_roomId']);
    $stmt->execute();
    return $stmt->rowCount() > 0 ? json_encode($stmt->fetchAll(PDO::FETCH_ASSOC)) : 0;
  }

  function getRoomDetails($json)
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
  function joinRoom($json)
  {
    // {"room_code": "T6456", "team_name": "test"}
    include "connection.php";
    $json = json_decode($json, true);
    $roomId = getRoomId($json['room_code']);

    // if(recordExists($json["team_name"], "tbl_team_participants", "team_name")) {
    //   return -1;
    // }
    $sql = "INSERT INTO tbl_team_participants(team_roomId, team_name, team_level) 
    VALUES(:team_roomId, :team_name, 1)";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':team_roomId', $roomId);
    $stmt->bindParam(':team_name', $json['team_name']);
    $stmt->execute();
    return $stmt->rowCount() > 0 ? $roomId : 0;
  }

  function removeParticipant($json)
  {
    // {"team_id": "3"}
    include "connection.php";
    $json = json_decode($json, true);
    $sql = "DELETE FROM tbl_team_participants WHERE team_id = :team_id";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':team_id', $json['team_id']);
    $stmt->execute();
    return $stmt->rowCount() > 0 ? 1 : 0;
  }

  function getParticipants($json)
  {
    // {"team_roomId": "7"}
    include "connection.php";
    $json = json_decode($json, true);
    $sql = "SELECT * FROM tbl_team_participants WHERE team_roomId = :team_roomId AND team_status = 1";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':team_roomId', $json['team_roomId']);
    $stmt->execute();
    return $stmt->rowCount() > 0 ? json_encode($stmt->fetchAll(PDO::FETCH_ASSOC)) : 0;
  }

  function addRiddle($json)
  {
    // {"rid_roomId": "7", "rid_riddle": "test", "rid_answer": "test", "rid_hint": "naa sa likod sa cr", "rid_level": "1"}
    include "connection.php";
    $json = json_decode($json, true);
    $conn->beginTransaction();

    try {
      $sql = "SELECT COUNT(rid_id) + 1 as NumberOfRiddles FROM tbl_riddles WHERE rid_roomId = :roomId";
      $stmt = $conn->prepare($sql);
      $stmt->bindParam(":roomId", $json['rid_roomId']);
      $stmt->execute();
      $riddleLevel = $stmt->fetchColumn();
      // echo "riddle level: " . $riddleLevel;

      if ($stmt->rowCount() > 0) {
        $sql = "INSERT INTO tbl_riddles(rid_riddle, rid_answer, rid_hint, rid_level, rid_roomId) 
          VALUES (:rid_riddle, :rid_answer, :rid_hint, :rid_level, :rid_roomId)";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':rid_riddle', $json['rid_riddle']);
        $stmt->bindParam(':rid_answer', $json['rid_answer']);
        $stmt->bindParam(':rid_hint', $json['rid_hint']);
        $stmt->bindParam(':rid_level', $riddleLevel);
        $stmt->bindParam(':rid_roomId', $json['rid_roomId']);
        $stmt->execute();
        $lastId = $conn->lastInsertId();

        if ($stmt->rowCount() > 0) {
          $riddle = $json['rid_riddle'];
          $randomNumber = rand(1, 9999);
          $scanCode = trim(ucfirst($riddle))[0] . $randomNumber . $lastId;
          $sql = "UPDATE tbl_riddles SET rid_scanCode = :rid_scanCode WHERE rid_id = :rid_id";
          $stmt = $conn->prepare($sql);
          $stmt->bindParam(':rid_scanCode', $scanCode);
          $stmt->bindParam(':rid_id', $lastId);
          $stmt->execute();
        }
      }
      $conn->commit();
      return 1;
    } catch (Exception $e) {
      $conn->rollBack();
      return $e;
    }
  }

  function scanRiddle($json)
  {
    // {"team_roomId": "7", "team_id": "1", "rid_scanCode": "R39573"}
    include "connection.php";
    $json = json_decode($json, true);

    $teamLevel = getTeamLevel($json['team_roomId'], $json['team_id']);
    $riddleLevel = getRiddleLevel($json['team_roomId'], $json['rid_scanCode']);
    // validation sa scan if ang team level kay sakto sa riddle level
    if ($teamLevel !== $riddleLevel) {
      return -1;
    }
    $sql = "SELECT * FROM tbl_riddles WHERE rid_roomId = :rid_roomId AND rid_scanCode = :rid_scanCode";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':rid_roomId', $json['team_roomId']);
    $stmt->bindParam(':rid_scanCode', $json['rid_scanCode']);
    $stmt->execute();
    return $stmt->rowCount() > 0 ? json_encode($stmt->fetch(PDO::FETCH_ASSOC)) : 0;
  }

  function answerRiddle($json)
  {
    // {"rid_roomId": "7", "rid_level": 3, "team_id": "1", "answer": "Riddle ko to"}
    include "connection.php";
    $json = json_decode($json, true);
    $teamAnswer = $json["answer"];
    $riddleAnswer = getRiddleAnswer($json['rid_roomId'], $json['rid_level']);
    // validation if ang answer kay sakto sa riddle answer
    if ($teamAnswer !== $riddleAnswer) {
      return 0;
    }

    $sql = "UPDATE tbl_team_participants SET team_level = team_level + 1 WHERE team_id = :team_id";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':team_id', $json['team_id']);
    $stmt->execute();
    return 1;
  }
} //user

function getRoomId($roomCode)
{
  include "connection.php";
  $sql = "SELECT room_id FROM tbl_room WHERE room_code = :room_code";
  $stmt = $conn->prepare($sql);
  $stmt->bindParam(':room_code', $roomCode);
  $stmt->execute();
  return $stmt->rowCount() > 0 ? $stmt->fetchColumn() : 0;
}

function recordExists($value, $table, $column)
{
  include "connection.php";
  $sql = "SELECT COUNT(*) FROM $table WHERE $column = :value";
  $stmt = $conn->prepare($sql);
  $stmt->bindParam(":value", $value);
  $stmt->execute();
  $count = $stmt->fetchColumn();
  return $count > 0;
}


function getTeamLevel($roomId, $teamId)
{
  // {"team_roomId": "7"}
  include "connection.php";
  $sql = "SELECT team_level FROM tbl_team_participants WHERE team_roomId = :team_roomId AND team_id = :team_id";
  $stmt = $conn->prepare($sql);
  $stmt->bindParam(':team_roomId', $roomId);
  $stmt->bindParam(':team_id', $teamId);
  $stmt->execute();
  return $stmt->rowCount() > 0 ? $stmt->fetchColumn() : 0;
}

function getRiddleLevel($roomId, $riddleScanCode)
{
  // {"rid_roomId": "7"}
  include "connection.php";
  $sql = "SELECT rid_level FROM tbl_riddles WHERE rid_roomId = :rid_roomId AND rid_scanCode = :rid_scanCode";
  $stmt = $conn->prepare($sql);
  $stmt->bindParam(':rid_roomId', $roomId);
  $stmt->bindParam(':rid_scanCode', $riddleScanCode);
  $stmt->execute();
  return $stmt->rowCount() > 0 ? $stmt->fetchColumn() : 0;
}

function getRiddleAnswer($roomId, $riddleLevel)
{
  // {"rid_roomId": "7"}
  include "connection.php";
  $sql = "SELECT rid_answer FROM tbl_riddles WHERE rid_roomId = :rid_roomId AND rid_level = :rid_level";
  $stmt = $conn->prepare($sql);
  $stmt->bindParam(':rid_roomId', $roomId);
  $stmt->bindParam(':rid_level', $riddleLevel);
  $stmt->execute();
  return $stmt->rowCount() > 0 ? $stmt->fetchColumn() : 0;
}


$json = isset($_POST["json"]) ? $_POST["json"] : "0";
$operation = isset($_POST["operation"]) ? $_POST["operation"] : "0";

$user = new User();

switch ($operation) {
  case "signup":
    echo $user->signup($json);
    break;
  case "createRoom":
    echo $user->createRoom($json);
    break;
  case "joinRoom":
    echo $user->joinRoom($json);
    break;
  case "getParticipants":
    echo $user->getParticipants($json);
    break;
  case "removeParticipant":
    echo $user->removeParticipant($json);
    break;
  case "addRiddle":
    echo $user->addRiddle($json);
    break;
  case "scanRiddle":
    echo $user->scanRiddle($json);
    break;
  case "answerRiddle":
    echo $user->answerRiddle($json);
    break;
  case "login":
    echo $user->login($json);
    break;
  case "getAllRiddles":
    echo $user->getAllRiddles($json);
    break;
  case "getRoomDetails":
    echo $user->getRoomDetails($json);
    break;
}
