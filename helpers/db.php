<?php

include_once("error.php");

class ReturnMessage {
	
	public $error; //Boolean
	public $message; //String
	
	public function __construct($error, $message) {
		$this->error = $error;
		$this->message = $message;
	}
	
}

class Session {
	
	public $sessionId;
	public $userId;
	public $expiry;
	
	public function __construct($sessionId, $userId, $expiry) {
		$this->sessionId = $sessionId;
		$this->userId = $userId;
		$this->expiry = $expiry;
	}
	
}

class VerificationRecord {
	
	public $verificationCode;
	public $userId;
	public $expiry;
	
	public function __construct($verificationCode, $userId, $expiry) {
		$this->verificationCode = $verificationCode;
		$this->userId = $userId;
		$this->expiry = $expiry;
	}
	
}

class User {
	
	public $userId;
	public $username;
	public $email;
	public $userIcon;
	public $password;
	public $description;
	public $joinDate;
	public $status;
	
	public function __construct() {
		//Empty constructor, fill fields manually as not all are always needed
	}
	
}

//Database class that makes all calls to the database
class DatabaseAccessor {

	private $pdo;
	
	public function __construct() {
		include_once("connection.php");
		$this->pdo = new PDO($dbdsn, $dbuser, $dbpass, $dbopt);
	}
	
	//Create a new session, used in user.php
	public function createSession($sessionId, $userId, $expiry) {
		//Insert session
		//Return true if success else false
		$stmt = $this->pdo->prepare("INSERT INTO `sessions` (`sessionid`, `userid`, `expiry`) VALUES (:sessionid, :userid, :expiry)");
		$stmt->bindParam(":sessionid", $sessionId);
		$stmt->bindParam(":userid", $userId);
		$stmt->bindParam(":expiry", $expiry);
		$stmt->execute();
		return $stmt->rowCount() === 1;
	}
	
	//Return Session object of the requested session, used in auth.php
	public function getSessionInfo($sessionId) {
		//Check database for specified session id
		$stmt = $this->pdo->prepare("SELECT `userid`, `expiry` FROM `sessions` WHERE `sessionid` = :sessionid LIMIT 1");
		$stmt->bindParam(":sessionid", $sessionId);
		$stmt->execute();
		//If exists, save to Session object and return, else return null
		if ($row = $stmt->fetch()) {
			return new Session($sessionId, $row['userid'], $row['expiry']);
		} else {
			return NULL;
		}
	}
	
	//Delete session, used in user.php
	public function deleteSession($sessionId) {
		//Check database for specified session id
		//If it exists, delete and return true, else return false
		$stmt = $this->pdo->prepare("DELETE FROM `sessions` WHERE `sessionid` = :sessionid LIMIT 1");
		$stmt->bindParam(":sessionid", $sessionId);
		return $stmt->execute();
	}
	
	//Create a new user, used in user.php
	public function createUser($username, $email, $password) {
		//Do password hashing/salting outside transaction for speed
		$hashword = password_hash($password, PASSWORD_DEFAULT);
		//Transaction to check user validity and create if possible
		if ($this->pdo->beginTransaction()) {
			//Check if username is in use (case insensitive) (ensure database collation is case insensitive, not bin)
			//Check if email is in use (case insensitive)
			$stmt = $this->pdo->prepare("SELECT `username` FROM `usernames` WHERE `username` = :username LIMIT 1");
			$stmt2 = $this->pdo->prepare("SELECT `email` FROM `users` WHERE `email` = :email LIMIT 1");
			$stmt->bindParam(":username", $username);
			$stmt2->bindParam(":email", $email);
			$stmt->execute();
			$stmt2->execute();
			//If either are, return message stating which are in use
			if ($stmt->rowCount() === 1) {
				$this->pdo->rollBack();
				if ($stmt2->rowCount() === 1) {
					return new ReturnMessage(true, "Email and username are already in use.");
				} else {
					return new ReturnMessage(true, "Username is already in use.");
				}
			}
			if ($stmt2->rowCount() === 1) {
				$this->pdo->rollBack();
				return new ReturnMessage(true, "Email is already in use.");
			}
			//Add username to usernames table and user to users table
			$stmt3 = $this->pdo->prepare("INSERT INTO `usernames` (`username`) VALUES (:username)");
			$stmt4 = $this->pdo->prepare("INSERT INTO `users` (`username`, `email`, `usericon`, `password`, `description`, `joindate`, `status`) VALUES (:username, :email, 'default.jpg', :password, 'Welcome to my homepage!', NOW(), 2)");
			$stmt3->bindParam(":username", $username);
			$stmt4->bindParam(":username", $username);
			$stmt4->bindParam(":email", $email);
			$stmt4->bindParam(":password", $hashword);
			$stmt3Status = $stmt3->execute();
			$stmt4Status = $stmt4->execute();
			if (!$stmt3Status || !$stmt4Status) {
				$this->pdo->rollBack();
				return new ReturnMessage(true, "Error creating user, please try again");
			}
			if (!$this->pdo->commit()) {
				return new ReturnMessage(true, "Error committing transaction to add new user to database");
			}
		} else {
			return new ReturnMessage(true, "Error starting transaction to create user");
		}
		//Get and return userId as message with error false, or if transaction failed return error and message
		$stmt5 = $this->pdo->prepare("SELECT `userid` FROM `users` WHERE `email` = :email LIMIT 1");
		$stmt5->bindParam(":email", $email);
		$stmt5->execute();
		if ($row = $stmt->fetch()) {
			return new ReturnMessage(false, $row['userid']);
		} else {
			return new ReturnMessage(true, "User may have been created but could not access id");
		}
	}
	
	//Store a verification code to activate a new account, used in user.php
	public function addVerificationCode($verificationCode, $userId, $expiry) {
		//Add verification code to database
		$stmt = $this->pdo->prepare("INSERT INTO `verificationcodes` (`verificationcode`, `userid`, `expiry`) VALUES (:verificationcode, :userid, :expiry)");
		$stmt->bindParam(":verificationcode", $verificationCode);
		$stmt->bindParam(":userid", $userId);
		$stmt->bindParam(":expiry", $expiry);
		//Return true if success else false
		return $stmt->execute();
	}
	
	//Access and return a verification record, used in user.php
	public function getVerificationRecord($verificationCode) {
		//Get record for code
		$stmt = $this->pdo->prepare("SELECT * FROM `verificationcodes` WHERE `verificationcode` = :verificationcode LIMIT 1");
		$stmt->bindParam(":verificationcode", $verificationCode);
		$stmt->execute();
		//Return VerificationRecord if found, else null
		if ($row = $stmt->fetch()) {
			return new VerificationRecord($row['verificationcode'], $row['userid'], $row['expiry']);
		} else {
			return null;
		}
	}
	
	//Delete a verification record, used in user.php
	public function deleteVerificationRecord($verificationCode) {
		//Delete record
		$stmt = $this->pdo->prepare("DELETE FROM `verificationcodes` WHERE `verificationcode` = :verificationcode LIMIT 1");
		$stmt->bindParam(":verificationcode", $verificationCode);
		//Return true if success else false
		return $stmt->execute();
	}
	
	//Get status of user, used in user.php
	public function getUserStatus($userId) {
		//Get user status
		$stmt = $this->pdo->prepare("SELECT `status` FROM `users` WHERE `userid` = :userid LIMIT 1");
		$stmt->bindParam(":userid", $userId);
		$stmt->execute();
		//Return user status or null if not found
		if ($row = $stmt->fetch()) {
			return $row['status'];
		} else {
			return null;
		}
	}
	
	//Set status of user, used in user.php
	public function setUserStatus($userId, $status) {
		//Set status
		$stmt = $this->pdo->prepare("UPDATE `users` SET `status` = :status WHERE `userid` = :userid LIMIT 1");
		$stmt->bindParam(":status", $status);
		$stmt->bindParam(":userid", $userId);
		//Return true on success else false
		return $stmt->execute();
	}
	
	//Get user object, used in user.php
	public function getUserByEmail($email) {
		//Find user by email
		$stmt = $this->pdo->prepare("SELECT * FROM `users` WHERE `email` = :email LIMIT 1");
		$stmt->bindParam(":email", $email);
		$stmt->execute();
		//Build user object, omitting description, joindate, and usericon
		//Return user object, or null if not found
		if ($row = $stmt->fetch()) {
			$user = new User();
			$user->userid = $row['userid'];
			$user->username = $row['username'];
			$user->email = $row['email'];
			$user->status = $row['status'];
			return $user;
		} else {
			return null;
		}
	}
	
	public function deleteUser($userId) {
		//TODO
	}
	
}
