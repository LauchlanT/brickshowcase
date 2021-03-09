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
		$userId = -1;
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
			$userId = $this->pdo->lastInsertId();
			if (!$this->pdo->commit()) {
				return new ReturnMessage(true, "Error committing transaction to add new user to database");
			}
		} else {
			return new ReturnMessage(true, "Error starting transaction to create user");
		}
		//Return userId as message with error false, or if transaction failed return error and message
		if ($userId > 0) {
			return new ReturnMessage(false, $userId);
		} else {
			return new ReturnMessage(true, "Failure to access user id");
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
		$stmt = $this->pdo->prepare("SELECT * FROM `users` WHERE `email` = :email AND `status` != 0 LIMIT 1");
		$stmt->bindParam(":email", $email);
		$stmt->execute();
		//Build user object, omitting description, joindate, and usericon
		//Return user object, or null if not found
		if ($row = $stmt->fetch()) {
			$user = new User();
			$user->userId = $row['userid'];
			$user->username = $row['username'];
			$user->email = $row['email'];
			$user->password = $row['password'];
			$user->status = $row['status'];
			return $user;
		} else {
			return null;
		}
	}
	
	//Salt/hash password and store in db for user, used in user.php
	public function setPassword($userId, $password) {
		//Calculate hash to store
		$hashword = password_hash($password, PASSWORD_DEFAULT);
		//Set password
		$stmt = $this->pdo->prepare("UPDATE `users` SET `password` = :password WHERE `userid` = :userid AND `status` != 0 LIMIT 1");
		$stmt->bindParam(":password", $hashword);
		$stmt->bindParam(":userid", $userId);
		//Return true on success else false
		return $stmt->execute();
	}
	
	//Return if password matches user's password, used in user.php
	public function verifyPassword($userId, $password) {
		//Get the password for the user
		$stmt = $this->pdo->prepare("SELECT `password` FROM `users` WHERE `userid` = :userid AND `status` != 0 LIMIT 1");
		$stmt->bindParam(":userid", $userId);
		if ($stmt->execute()) {
			if ($row = $stmt->fetch()) {
				return password_verify($password, $row['password']);
			} else {
				return false;
			}
		} else {
			return false;
		}
	}
	
	//Set email address for user, used in user.php
	public function setEmail($userId, $email) {
		//Set email
		$stmt = $this->pdo->prepare("UPDATE `users` SET `email` = :email WHERE `userid` = :userid AND `status` != 0 LIMIT 1");
		$stmt->bindParam(":email", $email);
		$stmt->bindParam(":userid", $userId);
		//Return true on success else false
		return $stmt->execute();
	}
	
	//Set username, in transaction, used in user.php
	public function setUsername($userId, $username) {
		//Transaction to check username validity and change if possible
		if ($this->pdo->beginTransaction()) {
			//Check if username is in use (case insensitive) (TODO: ensure database collation is case insensitive, not bin)
			$stmt = $this->pdo->prepare("SELECT `username` FROM `usernames` WHERE `username` = :username LIMIT 1");
			$stmt->bindParam(":username", $username);
			$stmt->execute();
			//If username is in use, abort and return message stating such
			if ($stmt->rowCount() === 1) {
				$this->pdo->rollBack();
				return new ReturnMessage(true, "Username is already in use.");
			}
			//Add username to usernames table and get current username of user
			$stmt2 = $this->pdo->prepare("INSERT INTO `usernames` (`username`) VALUES (:username)");
			$stmt3 = $this->pdo->prepare("SELECT `username` FROM `users` WHERE `userid` = :userid AND `status` != 0 LIMIT 1");
			$stmt2->bindParam(":username", $username);
			$stmt3->bindParam(":userid", $userId);
			if (!$stmt2->execute()) {
				$this->pdo->rollBack();
				return new ReturnMessage(true, "Error inserting new username, please try again.");
			}
			$stmt3->execute();
			$oldUsername = "";
			if ($row = $stmt3->fetch()) {
				$oldUsername = $row['username'];
			} else {
				$this->pdo->rollBack();
				return new ReturnMessage(true, "Error getting current username, please try again.");
			}
			//Change the user's username to the new username
			$stmt4 = $this->pdo->prepare("UPDATE `users` SET `username` = :username WHERE `userid` = :userid LIMIT 1");
			$stmt4->bindParam(":username", $username);
			$stmt4->bindParam(":userid", $userId);
			if (!$stmt4->execute()) {
				$this->pdo->rollBack();
				return new ReturnMessage(true, "Error updating username, please try again.");
			}
			//Delete the old username from the usernames table
			$stmt5 = $this->pdo->prepare("DELETE FROM `usernames` WHERE `username` = :username LIMIT 1");
			$stmt5->bindParam(":username", $oldUsername);
			if (!$stmt5->execute()){
				$this->pdo->rollBack();
				return new ReturnMessage(true, "Error removing old username, please try again.");
			}
			if (!$this->pdo->commit()) {
				return new ReturnMessage(true, "Error committing transaction to change username.");
			}
		} else {
			return new ReturnMessage(true, "Error starting transaction to create user");
		}
		//Return no error if successful
		return new ReturnMessage(false, "Username updated successfully");
	}
	
	public function deleteUser($userId) {
		//TODO - as tables are added, ensure deleteuser deletes from them appropriately
		if ($this->pdo->beginTransaction()) {
			//Disable safe mode so that non-primary keys can be used to select rows to delete
			$disableSafe = $this->pdo->prepare("SET SQL_SAFE_UPDATES = 0");
			if (!$disableSafe->execute()) {
				$this->pdo->rollBack();
				return new ReturnMessage(true, "Error preparing deletion, please try again.");
			}
			//Delete anything that should be deleted
			$stmt = $this->pdo->prepare("DELETE FROM `sessions` WHERE `userid` = :userid");
			$stmt2 = $this->pdo->prepare("DELETE FROM `verificationcodes` WHERE `userid` = :userid");
			$stmt->bindParam(":userid", $userId);
			$stmt2->bindParam(":userid", $userId);
			$stmtResult = $stmt->execute();
			$stmt2Result = $stmt2->execute();
			if (!($stmtResult && $stmt2Result)) {
				$this->pdo->rollBack();
				return new ReturnMessage(true, "Error deleting user data, please try again.");
			}
			//Create new DeletedUser username and get original username
			$stmt3 = $this->pdo->prepare("SELECT `username` FROM `usernames` WHERE `username` LIKE 'DeletedUser%' ORDER BY `username` DESC LIMIT 1");
			$stmt4 = $this->pdo->prepare("SELECT `username`, `email` FROM `users` WHERE `userid` = :userid LIMIT 1");
			$stmt4->bindParam(":userid", $userId);
			$stmt3->execute();
			$stmt4->execute();
			$latestDeleted = "DeletedUser0";
			if ($row = $stmt3->fetch()) {
				$latestDeleted = $row['username'];
			}
			$originalUsername = "";
			$originalEmail = "";
			if ($row = $stmt4->fetch()) {
				$originalUsername = $row['username'];
				$originalEmail = $row['email'];
			} else {
				$this->pdo->rollBack();
				return new ReturnMessage(true, "Error accessing user to delete, please try again.");
			}
			//Calculate next number for this deleted user
			$userNumber = intval(substr($latestDeleted, 11));
			$userNumber += 1;
			$newDeleted = "DeletedUser".$userNumber;
			$stmt5 = $this->pdo->prepare("INSERT INTO `usernames` (`username`) VALUES (:username)");
			$stmt5->bindParam(":username", $newDeleted);
			if (!$stmt5->execute()) {
				$this->pdo->rollBack();
				return new ReturnMessage(true, "Error with username deletion, please try again.");
			}
			//Update user info
			$stmt6 = $this->pdo->prepare("UPDATE `users` SET `username` = :username, `email` = :email, `usericon` = 'default.jpg', `password` = 'deleted', `description` = 'Deleted Account', `status` = 0 WHERE `userid` = :userid LIMIT 1");
			$stmt6->bindParam(":username", $newDeleted);
			$deletedEmail = "DeletedUser&&".$originalEmail;
			$stmt6->bindParam(":email", $deletedEmail);
			$stmt6->bindParam(":userid", $userId);
			if (!$stmt6->execute()) {
				$this->pdo->rollBack();
				return new ReturnMessage(true, "Error with user deletion, please try again.");
			}
			//Remove old username from username list
			$stmt7 = $this->pdo->prepare("DELETE FROM `usernames` WHERE `username` = :username LIMIT 1");
			$stmt7->bindParam(":username", $originalUsername);
			if (!$stmt7->execute()){
				$this->pdo->rollBack();
				return new ReturnMessage(true, "Error deleting current username, please try again.");
			}
			//Re-enable safe mode
			$enableSafe = $this->pdo->prepare("SET SQL_SAFE_UPDATES = 1;");
			if (!$enableSafe->execute()) {
				$this->pdo->rollBack();
				return new ReturnMessage(true, "Error completing deletion, please try again.");
			}
			if (!$this->pdo->commit()) {
				return new ReturnMessage(true, "Error committing transaction to delete user, please try again.");
			}
		} else {
			return new ReturnMessage(true, "Error starting transaction to delete user");
		}
		//Return no error if successful
		return new ReturnMessage(false, "User deleted successfully");
	}
	
	//TODO: Update MOC SQL calls to ignore deleted MOCs, so users can't like or add comments to them and such
	
	//Create MOC, used in moc.php
	public function createMoc($userId, $mocTitle, $mocText, $mocThumb, $mocPrivacy, $mocFilter) {
		$stmt = $this->pdo->prepare("INSERT INTO `mocs` (`userid`, `title`, `thumbnail`, `content`, `privacy`, `filter`, `postdate`, `lastedit`, `numcomments`, `numlikes`, `numviews`, `status`) VALUES (:userid, :title, :thumbnail, :content, :privacy, :filter, NOW(), NULL, 0, 0, 0, 1)");
		$stmt->bindParam(":userid", $userId);
		$stmt->bindParam(":title", $mocTitle);
		$stmt->bindParam(":thumbnail", $mocThumb);
		$stmt->bindParam(":content", $mocText);
		$stmt->bindParam(":privacy", $mocPrivacy);
		$stmt->bindParam(":filter", $mocFilter);
		if ($stmt->execute()) {
			$mocId = $this->pdo->lastInsertId();
			return new ReturnMessage(false, "https://www.".$GLOBALS['configRootDomain']."/moc.php/$mocId");
		} else {
			return new ReturnMessage(true, "Error posting this MOC, please try again.");
		}	
	}
	
	//Update existing MOC, used in moc.php
	public function updateMoc($mocId, $mocTitle, $mocText, $mocThumb, $mocPrivacy, $mocFilter) {
		$stmt = $this->pdo->prepare("UPDATE `mocs` SET `title` = :title, `thumbnail` = :thumbnail, `content` = :content, `privacy` = :privacy, `filter` = :filter, `lastedit` = NOW() WHERE `mocid` = :mocid AND `status` != 0");
		$stmt->bindParam(":title", $mocTitle);
		$stmt->bindParam(":thumbnail", $mocThumb);
		$stmt->bindParam(":content", $mocText);
		$stmt->bindParam(":privacy", $mocPrivacy);
		$stmt->bindParam(":filter", $mocFilter);
		$stmt->bindParam(":mocid", $mocId);
		if ($stmt->execute()) {
			return new ReturnMessage(false, "MOC successfully updated!");
		} else {
			return new ReturnMessage(true, "Error updating this MOC, please try again.");
		}
	}
	
	//Delete existing MOC, used in moc.php
	public function deleteMoc($mocId) {
		//TODO: Consider options other than just setting deleted flag to 0
		$stmt = $this->pdo->prepare("UPDATE `mocs` SET `status` = 0 WHERE `mocid` = :mocid");
		$stmt->bindParam(":mocid", $mocId);
		if ($stmt->execute()) {
			return new ReturnMessage(false, "MOC successfully deleted!");
		} else {
			return new ReturnMessage(true, "Error deleting this MOC, please try again.");
		}
	}
	
	//Get the user id of the user that posted a MOC, used in moc.php
	//Return null if moc does not exist or on error
	public function getMocCreator($mocId) {
		$stmt = $this->pdo->prepare("SELECT `userid` FROM `mocs` WHERE `mocid` = :mocid AND `status` != 0");
		$stmt->bindParam(":mocid", $mocId);
		if ($stmt->execute()) {
			if ($row = $stmt->fetch()) {
				return $row['userid'];
			} else {
				return null;
			}
		} else {
			return null;
		}
	}
	
	//TODO: Consider updating numlikes and numcomments for MOC in db with each operation
	//Add a like from given user to given MOC, used in moc.php
	public function likeMoc($mocId, $userId) {
		//TODO: Determine what happens if you try to insert duplicate record - currently silently fails with duplicate primary key constraint violation
		//Trying to like MOCs that doesn't exist also silently fails - should this also get handled?
		//Might be a non-issue - valid users shouldn't even be given the option to like if they already have
		$stmt = $this->pdo->prepare("INSERT INTO `moclikes` (`mocid`, `userid`, `likedate`) VALUES (:mocid, :userid, NOW())");
		$stmt->bindParam(":mocid", $mocId);
		$stmt->bindParam(":userid", $userId);
		if ($stmt->execute()) {
			return new ReturnMessage(false, "Like added!");
		} else {
			return new ReturnMessage(true, "Error adding like.");
		}
	}
	
	//Remove a like from given user to given MOC, used in moc.php
	public function unlikeMoc($mocId, $userId) {
		//TODO: Currently this just acts like it succeeded if the MOC is already not liked - should consider adding a more meaningful message? Could cost performance, and is likely only relevant for byzantine scenarios.
		$stmt = $this->pdo->prepare("DELETE FROM `moclikes` WHERE `mocid` = :mocid AND `userid` = :userid");
		$stmt->bindParam(":mocid", $mocId);
		$stmt->bindParam(":userid", $userId);
		if ($stmt->execute()) {
			return new ReturnMessage(false, "Like removed!");
		} else {
			return new ReturnMessage(true, "Error removing like.");
		}
	}
	
	//Add a treasure from given user to given MOC, used in moc.php
	public function treasureMoc($mocId, $userId) {
		$stmt = $this->pdo->prepare("INSERT INTO `moctreasures` (`mocid`, `userid`, `treasuredate`) VALUES (:mocid, :userid, NOW())");
		$stmt->bindParam(":mocid", $mocId);
		$stmt->bindParam(":userid", $userId);
		if ($stmt->execute()) {
			return new ReturnMessage(false, "MOC treasured!");
		} else {
			return new ReturnMessage(true, "Error treasuring MOC.");
		}
	}
	
	//Remove a treasure from given user to given MOC, used in moc.php
	public function untreasureMoc($mocId, $userId) {
		$stmt = $this->pdo->prepare("DELETE FROM `moctreasures` WHERE `mocid` = :mocid AND `userid` = :userid");
		$stmt->bindParam(":mocid", $mocId);
		$stmt->bindParam(":userid", $userId);
		if ($stmt->execute()) {
			return new ReturnMessage(false, "MOC untreasured!");
		} else {
			return new ReturnMessage(true, "Error untreasuring MOC.");
		}
	}
	
	//Add a comment to a MOC from the given user, used in moc.php
	public function postMocComment($mocId, $userId, $commentText) {
		$stmt = $this->pdo->prepare("INSERT INTO `moccomments` (`mocid`, `userid`, `content`, `postdate`, `status`) VALUES (:mocid, :userid, :content, NOW(), 1)");
		$stmt->bindParam(":mocid", $mocId);
		$stmt->bindParam(":userid", $userId);
		$stmt->bindParam(":content", $commentText);
		if ($stmt->execute()) {
			return new ReturnMessage(false, "Comment added!");
		} else {
			return new ReturnMessage(true, "Error adding comment.");
		}
	}
	
	//Edit a comment on a MOC, used in moc.php
	public function editMocComment($commentId, $commentText) {
		$stmt = $this->pdo->prepare("UPDATE `moccomments` SET `content` = :content, `lastedit` = NOW() WHERE `commentid` = :commentid AND `status` != 0");
		$stmt->bindParam(":content", $commentText);
		$stmt->bindParam(":commentid", $commentId);
		if ($stmt->execute()) {
			return new ReturnMessage(false, "Comment updated!");
		} else {
			return new ReturnMessage(true, "Error updating comment.");
		}
	}
	
	//Delete a comment on a MOC, used in moc.php
	public function deleteMocComment($commentId) {
		//TODO: Consider options other than just setting deleted flag to 0
		$stmt = $this->pdo->prepare("UPDATE `moccomments` SET `status` = 0 WHERE `commentid` = :commentid");
		$stmt->bindParam(":commentid", $commentId);
		if ($stmt->execute()) {
			return new ReturnMessage(false, "Comment successfully deleted!");
		} else {
			return new ReturnMessage(true, "Error deleting this comment, please try again.");
		}
	}
	
	//Get the userid of the creator of a comment on a MOC, used in moc.php
	public function getMocCommentCreator($commentId) {
		$stmt = $this->pdo->prepare("SELECT `userid` FROM `moccomments` WHERE `commentid` = :commentid AND `status` != 0");
		$stmt->bindParam(":commentid", $commentId);
		if ($stmt->execute()) {
			if ($row = $stmt->fetch()) {
				return $row['userid'];
			} else {
				return null;
			}
		} else {
			return null;
		}
	}
	
	public function getUser($userId) {
		//Find user by email
		$stmt = $this->pdo->prepare("SELECT * FROM `users` WHERE `userid` = :userid AND `status` != 0 LIMIT 1");
		$stmt->bindParam(":userid", $userId);
		$stmt->execute();
		//Build user object, omitting password and email
		//Return user object, or null if not found
		if ($row = $stmt->fetch()) {
			$user = new User();
			$user->userId = $row['userid'];
			$user->username = $row['username'];
			$user->usericon = $row['usericon'];
			$user->description = $row['description'];
			$user->joinDate = $row['joindate'];
			$user->status = $row['status'];
			return $user;
		} else {
			return null;
		}
	}
	
}
