<?php
include "headers.php";

class User
{

  function signup($json)
  {
    // {"username": "test", "password": "test"}
    include "connection.php";
    $json = json_decode($json, true);
    if (recordExists($json["username"], "tbl_users", "user_username")) {
      return -1;
    }
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
    // {"room_name": "test", "room_description": "test", "room_status": 1}
    include "connection.php";
    $json = json_decode($json, true);
    $roomName = $json['room_name'];
    $randomNumber = rand(1, 9999);
    $passCode = trim(ucfirst($roomName))[0] . $randomNumber;

    $sql = "INSERT INTO tbl_room(room_name, room_description, room_code, room_status) 
      VALUES (:room_name, :room_description, :room_code, :room_status)";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':room_name', $roomName);
    $stmt->bindParam(':room_description', $json['room_description']);
    $stmt->bindParam(':room_code', $passCode);
    $stmt->bindParam(':room_status', $json["room_status"]);
    $stmt->execute();
    if ($stmt->rowCount() > 0) {
      $roomId = $conn->lastInsertId(); // Get the last inserted ID
      echo json_encode(["room_id" => $roomId, "room_code" => $passCode]);
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

    $sql = "SELECT COUNT(*) FROM tbl_team_participants WHERE team_roomId = :team_roomId AND team_name = :team_name";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(":team_name", $json["team_name"]);
    $stmt->bindParam(":team_roomId", $roomId);
    $stmt->execute();
    $count = $stmt->fetchColumn();

    // if existing na ang team name sa specific nga room
    if ($count > 0) {
      return -1;
    }

    $sql = "INSERT INTO tbl_team_participants(team_roomId, team_name, team_level) 
    VALUES(:team_roomId, :team_name, 1)";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':team_roomId', $roomId);
    $stmt->bindParam(':team_name', $json['team_name']);
    $stmt->execute();

    $lastId = $conn->lastInsertId();
    $sql = "SELECT a.*, b.room_status 
    FROM tbl_team_participants as a 
    INNER JOIN tbl_room as b ON a.team_roomId = b.room_id 
    WHERE team_id = :team_id";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':team_id', $lastId);
    $stmt->execute();
    return $stmt->rowCount() > 0 ? json_encode($stmt->fetch(PDO::FETCH_ASSOC)) : 0;
    // return $stmt->rowCount() > 0 ? $roomId : 0;
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
    $sql = "SELECT * FROM tbl_team_participants WHERE team_roomId = :team_roomId";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':team_roomId', $json['team_roomId']);
    $stmt->execute();
    return $stmt->rowCount() > 0 ? json_encode($stmt->fetchAll(PDO::FETCH_ASSOC)) : 0;
  }

  function addRiddle($jsonArray)
  {
    include "connection.php";
    $decodedArray = json_decode($jsonArray, true);

    // Initialize an array to hold the scan codes
    $scanCodes = [];

    // Check if JSON decoding was successful and is an array
    if (!is_array($decodedArray)) {
      // Log error or return an error code/message
      return json_encode(["error" => "Invalid JSON"]); // Return an error in JSON format
    }

    // Assuming $decodedArray should contain an array under a key, e.g., 'riddles'
    if (!isset($decodedArray['riddles']) || !is_array($decodedArray['riddles'])) {
      // The decoded JSON does not have the expected structure
      return json_encode(["error" => "Invalid structure"]); // Return an error in JSON format
    }

    $conn->beginTransaction();
    try {
      foreach ($decodedArray['riddles'] as $json) {
        $roomId = $json['rid_roomId'];

        // Prepare SQL to get the next riddle level
        $sql = "SELECT COUNT(rid_id) + 1 as NumberOfRiddles FROM tbl_riddles WHERE rid_roomId = :roomId";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(":roomId", $roomId, PDO::PARAM_INT);
        $stmt->execute();
        $riddleLevel = $stmt->fetchColumn();

        // Insert the riddle into the database
        $sql = "INSERT INTO tbl_riddles (rid_riddle, rid_answer, rid_hint, rid_level, rid_roomId)
                        VALUES (:rid_riddle, :rid_answer, :rid_hint, :rid_level, :rid_roomId)";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':rid_riddle', $json['rid_riddle']);
        $stmt->bindParam(':rid_answer', $json['rid_answer']);
        $stmt->bindParam(':rid_hint', $json['rid_hint']);
        $stmt->bindParam(':rid_level', $riddleLevel, PDO::PARAM_INT);
        $stmt->bindParam(':rid_roomId', $roomId, PDO::PARAM_INT);
        $stmt->execute();
        $lastId = $conn->lastInsertId();

        // Generate and update the scan code
        $hint = $json['rid_hint'];
        $randomNumber = rand(1, 9999);
        $scanCode = trim(ucfirst($hint))[0] . $randomNumber . $lastId;
        $sql = "UPDATE tbl_riddles SET rid_scanCode = :rid_scanCode WHERE rid_id = :rid_id";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':rid_scanCode', $scanCode);
        $stmt->bindParam(':rid_id', $lastId, PDO::PARAM_INT);
        $stmt->execute();

        // Add the generated scanCode to the array
        $scanCodes[] = $scanCode;
      }
      $conn->commit();

      // Return the array of scan codes on success
      return json_encode(["success" => true, "scanCodes" => $scanCodes]);
    } catch (Exception $e) {
      $conn->rollBack();
      // Return the error message on failure
      return json_encode(["error" => $e->getMessage()]);
    }
  }


  function scanRiddle($json)
  {
    // {"team_roomId": "7", "team_id": "1", "rid_scanCode": "R39573", "room_status": 1}
    include "connection.php";
    $json = json_decode($json, true);

    $teamLevel = getTeamLevel($json['team_roomId'], $json['team_id']);
    $riddleLevel = getRiddleLevel($json['team_roomId'], $json['rid_scanCode']);
    $riddleCode = getRiddleCode($riddleLevel, $json["team_roomId"]);

    // validation sa scan if ang team level kay sakto sa riddle level
    if ($teamLevel !== $riddleLevel) {
      return -1;
    }

    // validation if ang scan kay sakto sa riddle code
    if ($json["rid_scanCode"] !== $riddleCode) {
      return -2;
    }


    // validation if ang room kay dili kailangan og challenge
    if ($json["room_status"] == 0) {
      $sql = "UPDATE tbl_team_participants SET team_level = team_level + 1 WHERE team_id = :team_id";
      $stmt = $conn->prepare($sql);
      $stmt->bindParam(':team_id', $json['team_id']);
      $stmt->execute();

      $sql2 = "SELECT rid_hint FROM tbl_riddles WHERE rid_roomId = :team_roomId AND rid_level = :rid_level + 1";
      $stmt2 = $conn->prepare($sql2);
      $stmt2->bindParam(':team_roomId', $json['team_roomId']);
      $stmt2->bindParam(':rid_level', $riddleLevel);
      $stmt2->execute();

      // eh return niya ang hint sa next riddle// mo return siyag 2 if wala nay next hint
      return 5;
    }

    $sql = "SELECT * FROM tbl_riddles WHERE rid_roomId = :rid_roomId AND rid_scanCode = :rid_scanCode";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':rid_roomId', $json['team_roomId']);
    $stmt->bindParam(':rid_scanCode', $json['rid_scanCode']);
    $stmt->execute();
    // kwaon ang details sa specific riddle
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
      return -1;
    }

    $sql = "UPDATE tbl_team_participants SET team_level = team_level + 1 WHERE team_id = :team_id";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':team_id', $json['team_id']);
    $stmt->execute();

    $sql2 = "SELECT rid_hint FROM tbl_riddles WHERE rid_roomId = :rid_roomId AND rid_level = :rid_level + 1";
    $stmt2 = $conn->prepare($sql2);
    $stmt2->bindParam(':rid_roomId', $json['rid_roomId']);
    $stmt2->bindParam(':rid_level', $json['rid_level']);
    $stmt2->execute();

    // eh return niya ang hint sa next riddle
    return $stmt2->rowCount() > 0 ? json_encode($stmt2->fetch(PDO::FETCH_ASSOC)) : 2;
  }


  function isTeamDone($json)
  {
    // {"roomId": "7", "team_id": "3"}
    include "connection.php";
    $json = json_decode($json, true);
    $riddleCount = getRiddleCount($json['roomId']);
    $sql = "SELECT team_level FROM tbl_team_participants WHERE team_id = :team_id";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':team_id', $json['team_id']);
    $stmt->execute();
    $teamLevel = $stmt->fetchColumn();
    // if na complete na nila tanan riddle, return og 1 else 0
    return $teamLevel > $riddleCount ? 1 : 0;
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

function getRiddleCount($roomId)
{
  include "connection.php";
  $sql = "SELECT COUNT(*) AS riddleCount FROM tbl_riddles WHERE rid_roomId = :rid_roomId";
  $stmt = $conn->prepare($sql);
  $stmt->bindParam(':rid_roomId', $roomId);
  $stmt->execute();
  return $stmt->fetchColumn();
}

function getRiddleCode($riddleLevel, $roomId)
{
  include "connection.php";
  $sql = "SELECT rid_scanCode FROM tbl_riddles WHERE rid_roomId = :rid_roomId AND rid_level = :rid_level";
  $stmt = $conn->prepare($sql);
  $stmt->bindParam(':rid_roomId', $roomId);
  $stmt->bindParam(':rid_level', $riddleLevel);
  $stmt->execute();
  return $stmt->fetchColumn();
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
  case "isTeamDone":
    echo $user->isTeamDone($json);
    break;
}
