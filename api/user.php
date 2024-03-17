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

    function joinRoom($json)
    {
      // {"team_roomId": "7", "team_userId": "1", "team_name": "Team mo to"}
      include "connection.php";
      $json = json_decode($json, true);
      $sql = "INSERT INTO tbl_team_participants(team_roomId, team_userId, team_name, team_status, team_level) 
      VALUES ( :team_roomId, :team_userId, :team_name, 0, 1)";
      $stmt = $conn->prepare($sql);
      $stmt->bindParam(':team_roomId', $json['team_roomId']);
      $stmt->bindParam(':team_userId', $json['team_userId']);
      $stmt->bindParam(':team_name', $json['team_name']);
      $stmt->execute();
      return $stmt->rowCount() > 0 ? 1 : 0;
    }

    function getPendingParticipants($json)
    {
      // {"team_roomId": "7"}
      include "connection.php";
      $json = json_decode($json, true);
      $sql = "SELECT * FROM tbl_team_participants WHERE team_roomId = :team_roomId AND team_status = 0";
      $stmt = $conn->prepare($sql);
      $stmt->bindParam(':team_roomId', $json['team_roomId']);
      $stmt->execute();
      return $stmt->rowCount() > 0 ? json_encode($stmt->fetchAll(PDO::FETCH_ASSOC)) : 0;
    }

    function approveParticipant($json)
    {
      // {"team_userId": "1"}
      include "connection.php";
      $json = json_decode($json, true);
      $sql = "UPDATE tbl_team_participants SET team_status = 1 WHERE team_userId = :team_userId";
      $stmt = $conn->prepare($sql);
      $stmt->bindParam(':team_userId', $json['team_userId']);
      $stmt->execute();
      return $stmt->rowCount() > 0 ? 1 : 0;
    }

    function removeParticipant($json)
    {
      // {"team_userId": "1"}
      include "connection.php";
      $json = json_decode($json, true);
      $sql = "DELETE FROM tbl_team_participants WHERE team_userId = :team_userId";
      $stmt = $conn->prepare($sql);
      $stmt->bindParam(':team_userId', $json['team_userId']);
      $stmt->execute();
      return $stmt->rowCount() > 0 ? 1 : 0;
    }

    function getApprovedParticipants($json)
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
      // {"rid_roomId": "7", "rid_riddle": "Riddle mo to", "rid_answer": "Riddle ko to"}
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
          $sql = "INSERT INTO tbl_riddles(rid_riddle, rid_answer, rid_level, rid_roomId) 
          VALUES (:rid_riddle, :rid_answer, :rid_level, :rid_roomId)";
          $stmt = $conn->prepare($sql);
          $stmt->bindParam(':rid_riddle', $json['rid_riddle']);
          $stmt->bindParam(':rid_answer', $json['rid_answer']);
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
      // {"team_roomId": "7", "team_userId": "1", "rid_scanCode": "R39573", "answer": "Riddle ko to"}
      include "connection.php";
      $json = json_decode($json, true);
      $conn->beginTransaction();
      try {
        $teamLevel = getTeamLevel($json['team_roomId'], $json['team_userId']);
        $riddleLevel = getRiddleLevel($json['team_roomId'], $json['rid_scanCode']);
        $teamAnswer = $json["answer"];
        $riddleAnswer = getRiddleAnswer($json['team_roomId'], $json['rid_scanCode']);
        // echo "riddle level: " . $riddleLevel;
        // echo "riddle answer: " . $riddleAnswer;

        // validation sa scan if ang team level kay sakto sa riddle level
        if ($teamLevel !== $riddleLevel) {
          $conn->commit();
          return -1;
        }

        // validation if ang answer kay sakto sa riddle answer
        if ($teamAnswer !== $riddleAnswer) {
          $conn->commit();
          return -2;
        }

        $sql = "UPDATE tbl_team_participants SET team_level = team_level + 1 WHERE team_roomId = :team_roomId AND team_userId = :team_userId";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':team_roomId', $json['team_roomId']);
        $stmt->bindParam(':team_userId', $json['team_userId']);
        $stmt->execute();
        $conn->commit();
        return 1;
      } catch (Exception $e) {
        $conn->rollBack();
        return $e;
      }
    }
  } //user

  function getTeamLevel($roomId, $teamId)
  {
    // {"team_roomId": "7"}
    include "connection.php";
    $sql = "SELECT team_level FROM tbl_team_participants WHERE team_roomId = :team_roomId AND team_userId = :team_userId";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':team_roomId', $roomId);
    $stmt->bindParam(':team_userId', $teamId);
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

  function getRiddleAnswer($roomId, $riddleScanCode)
  {
    // {"rid_roomId": "7"}
    include "connection.php";
    $sql = "SELECT rid_answer FROM tbl_riddles WHERE rid_roomId = :rid_roomId AND rid_scanCode = :rid_scanCode";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':rid_roomId', $roomId);
    $stmt->bindParam(':rid_scanCode', $riddleScanCode);
    $stmt->execute();
    return $stmt->rowCount() > 0 ? $stmt->fetchColumn() : 0;
  }

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
    case "getPendingParticipants":
      echo $user->getPendingParticipants($json);
      break;
    case "approveParticipant":
      echo $user->approveParticipant($json);
      break;
    case "getApprovedParticipants":
      echo $user->getApprovedParticipants($json);
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
  }
