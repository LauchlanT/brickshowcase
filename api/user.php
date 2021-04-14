<?php

//Handle user CRUD

include_once("../helpers/error.php");
include_once("../helpers/auth.php");
include_once("../helpers/db.php");
session_start();

//Build basic error message JSON
function errorBuilder($message) {
	return '{"result":null, "error":"'.$message.'"}';
}

//Build basic result message JSON
function resultBuilder($message) {
	return '{"result":"'.$message.'", "error":null}';
}

//Same as resultBuilder, but expects JSON child rather than string
function resultJSONBuilder($jsonMessage) {
	return '{"result":'.$jsonMessage.', "error":null}';
}

//Encode a User object as a JSON string for public use
function userEncode($user) {
	if ($user->mocs !== null) {
		return '{ "userId":"'.$user->userId.'", "username":"'.$user->username.'", "userIcon":"'.$user->userIcon.'", "description":"'.$user->description.'", "joinDate":"'.$user->joinDate.'", "mocs":'.$user->mocs.' }';
	}	
	return '{ "userId":"'.$user->userId.'", "username":"'.$user->username.'", "userIcon":"'.$user->userIcon.'", "description":"'.$user->description.'", "joinDate":"'.$user->joinDate.'" }';
}

function login($email, $password) {
	//Verify that user isn't already logged in
	$userId = authenticate();
	if ($userId !== null) {
		return errorBuilder("You are already logged in");
	}
	//Check if credentials are valid for non pending non deleted user
	$db = new DatabaseAccessor();
	$userData = $db->getUserByEmail($email);
	if ($userData === null) {
		return errorBuilder("This user could not be found");
	}
	if ($userData->status === 0 || $userData->status === 2) {
		return errorBuilder("This account is not currently activated");
	}
	if (!password_verify($password, $userData->password)) {
		return errorBuilder("Incorrect password");
	}
	//Create session and cookie (set cookie and in db)
	//In future, add option for user to select expiry duration and if they don't want non-session cookie
	$_SESSION['userId'] = $userData->userId;
	$_SESSION['expiry'] = time() + 7200;
	setcookie("sessionId",session_id(),time() + 7200,"/",$GLOBALS['configRootDomain'],true,true);
	if (!$db->createSession(session_id(), $userData->userId, time() + 7200)) {
		session_destroy();
		unset($_COOKIE['sessionId']); //Just to clear the variable here
		setcookie("sessionId", null, time() - 3600, "/");
		return errorBuilder("Unable to log in due to server error");
	} else {
		//Session, cookie, and database cookie record set successfully
		return resultBuilder("Login successful");
	}
}

function logout() {
	//Verify that user is logged in
	$userId = authenticate();
	if ($userId === null) {
		return errorBuilder("You are already logged out");
	}
	//Destroy session and remove db session entry
	$sessionId = session_id();
	//I may want to check return values of all this
	unset($_COOKIE['sessionId']); //Just to clear the variable here
	setcookie("sessionId", null, time() - 3600, "/");
	$_SESSION['userId'] = null;
	session_destroy();
	$db = new DatabaseAccessor();
	$db->deleteSession($sessionId);
	//Return status of logout
	return resultBuilder("Logout successful");
}

function validateUsername($username) {
	//Verify username is in valid character set and not too long or too short
	if (!mb_check_encoding($username, "ASCII")) {
		return errorBuilder("Usernames can only contain ASCII characters");
	}
	//Check if there's leading or trailing whitespace, null bytes, tabs, newlines, returns, nul-bytes, or duplicate whitespace
	$stripped = trim($username);
	$stripped = preg_replace("/[^a-zA-z0-9\s\-_=+!@#$%^&*()?><,.'\"`~]/", "", $stripped);
	$stripped = preg_replace("/\s/", " ", $stripped);
	$stripped = preg_replace("/\s\s+/", " ", $stripped);
	if ($stripped !== $username) {
		return errorBuilder("Usernames can only contain letters, numbers, spaces between words, and characters -_=+!@#$%^&*()?><,.'\"`~"); 
	}
	if (strlen($username) < 2 || strlen($username) > 30) {
		return errorBuilder("Usernames must be not less than 2 and not more than 30 characters long");
	}
	return null;
}

function validateEmail($email) {
	return null;
}

function validatePassword($password, $passwordConfirm) {
	//Check that passwords match and are at least one character
	if (strlen($password) < 1) {
		return errorBuilder("Password must be at least one character long");
	}
	if ($password !== $passwordConfirm) {
		return errorBuilder("Passwords do not match");
	}
	//Alert user that passwords longer than 72 characters are not supported
	if (strlen($password) > 72) {
		return errorBuilder("Passwords cannot be greater than 72 characters or bytes");
	}
	return null;
}


function register($username, $email, $password, $passwordConfirm) {
	//Verify user isn't already logged in
	if (authenticate() !== null) {
		return errorBuilder("You are already logged in");
	}
	//Check that password is acceptable and matches
	if (validatePassword($password, $passwordConfirm) != null) {
		return validatePassword($password, $passwordConfirm);
	}
	//Check that username is valid
	if (validateUsername($username) != null) {
		return validateUsername($username);
	}
	//Check that email is valid
	if (validateEmail($email) != null) {
		return validateEmail($email);
	}
	//Check username/email isn't in use or reserved from usernames and users tables
	//Insert username into usernames table and create user in users table
	//Ideally do all this in one transaction
	$db = new DatabaseAccessor();
	$result = $db->createUser($username, $email, $password);
	if ($result->error) {
		return errorBuilder($result->message);
	}
	//Generate verification code, store in database, send email
	$rawBytes = random_bytes(60);
	$verificationCode = md5($rawBytes); //Just to convert to URL safe characters
	if (!$db->addVerificationCode($verificationCode, $result->message, time() + 86400)) {
		return errorBuilder("Error creating verification code, please request a new verification code");
	}
	$to = $email;
  $subject = "MOCShare Account Verification";
  $message = "

  Thank you for signing up to MOCShare, $username!

  Please click this link to activate your account: https://www.".$GLOBALS['configRootDomain']."/verify.php/account/$verificationCode

  If you did not wish to create an account, click \"Cancel Registration\" after following the link above.

  ";
  $headers = "From:registration@".$GLOBALS['configRootDomain']."\r\n";
  mail($to,$subject,$message,$headers);
	//Return status of registration
	return resultBuilder("Registration successful, verification email sent to $email");
}

function verifyRegistration($code) {
	//Verify user isn't already logged in
	if (authenticate() !== null) {
		return errorBuilder("You are already logged in");
	}
	//Get database entry for verification code
	$db = new DatabaseAccessor();
	$verificationRecord = $db->getVerificationRecord($code);
	if ($verificationRecord === null) {
		return errorBuilder("This verification code does not exist anymore, but you can request a new verification email");
	}
	//Check that it's not expired (if it is, delete)
	if ($verificationRecord->expiry < time()) {
		$db->deleteVerificationRecord($code);
		return errorBuilder("This verification code has expired, you can request a new verification email however");
	}
	//Check that user is pending verification (if they aren't, delete verification record)
	$userStatus = $db->getUserStatus($verificationRecord->userId);
	if ($userStatus !== 2) {
		$db->deleteVerificationRecord($code);
		return errorBuilder("This verification code is for a user that is not pending verification");
	}
	//Set user to verified
	if (!$db->setUserStatus($verificationRecord->userId, 1)) {
		return errorBuilder("Verification failed, please try again");
	}
	//Delete verification record
	$db->deleteVerificationRecord($code);
	//Return status of verification
	return resultBuilder("Account verified successfully, you can now log in!");
}

function resendVerification($email) {
	//Check if user exists with this email, and if so that they're not approved
	$db = new DatabaseAccessor();
	$user = $db->getUserByEmail($email);
	if ($user->status !== 2) {
		return errorBuilder("An account for $email is not pending verification");
	}
	//Generate verification code, store in database, send email
	$rawBytes = random_bytes(60);
	$verificationCode = md5($rawBytes); //Just to convert to URL safe characters
	if (!$db->addVerificationCode($verificationCode, $user->userId, time() + 86400)) {
		return errorBuilder("Error sending verification code, please request a new verification code");
	}
	$to = $email;
  $subject = "MOCShare Account Verification Request";
  $message = "

  Thank you for signing up to MOCShare!

  Please click this link to activate your account: https://www.".$GLOBALS['configRootDomain']."/verify.php/account/$verificationCode

  If you did not wish to create an account, click \"Cancel Registration\" after following the link above.

  ";
  $headers = "From:registration@".$GLOBALS['configRootDomain']."\r\n";
  mail($to,$subject,$message,$headers);
	//Return status of resend
	return resultBuilder("New verification code sent to $email");
}

function cancelRegistration($code) {
	//Get database entry for verification code
	$db = new DatabaseAccessor();
	$verificationRecord = $db->getVerificationRecord($code);
	if ($verificationRecord === null) {
		return errorBuilder("This verification code does not exist anymore, but you can request a new verification email");
	}
	//Check that it's not expired (if it is, delete)
	if ($verificationRecord->expiry < time()) {
		$db->deleteVerificationRecord($code);
		return errorBuilder("This verification code has expired, you can request a new verification email however");
	}
	//Check that user is pending verification (if they aren't, delete verification record)
	$userStatus = $db->getUserStatus($verificationRecord->userId);
	if ($userStatus !== 2) {
		$db->deleteVerificationRecord($code);
		return errorBuilder("This verification code is for a user that is not pending verification");
	}
	//Delete user and username
	$result = $db->deleteUser($verificationRecord->userId);
	if ($result->error) {
		return $result->message;
	}
	//Delete verification record (should be done by deleteUser)
	//$db->deleteVerificationRecord($code);
	//Return status of verification cancellation
	return resultBuilder("Registration cancellation successful, all account details deleted");
}

function requestPasswordReset($email) {
	//Check if users exists with this email, and that they're approved
	//Verify that user isn't already logged in
	$userId = authenticate();
	if ($userId !== null) {
		return errorBuilder("You are already logged in");
	}
	//Check if credentials are valid for non pending non deleted user
	$db = new DatabaseAccessor();
	$userData = $db->getUserByEmail($email);
	if ($userData === null) {
		return errorBuilder("This user could not be found");
	}
	if ($userData->status === 0 || $userData->status === 2) {
		return errorBuilder("This account is not currently activated");
	}
	//Generate verification code, store in database, send email
	$rawBytes = random_bytes(60);
	$verificationCode = md5($rawBytes); //Just to convert to URL safe characters
	if (!$db->addVerificationCode($verificationCode, $userData->userId, time() + 3600)) {
		return errorBuilder("Error sending verification code, please request a new password reset");
	}
	$to = $email;
  $subject = "MOCShare Password Reset Request";
  $message = "
  Please click this link to set your new password: https://www.".$GLOBALS['configRootDomain']."/verify.php/password/$verificationCode

  This link is valid for one hour - you can request another reset if it expires.

  ";
  $headers = "From:registration@".$GLOBALS['configRootDomain']."\r\n";
  mail($to,$subject,$message,$headers);
	//Return status of request
	return resultBuilder("Password reset link sent to $email");
}

function verifyPasswordReset($code, $password, $passwordConfirm) {
	//Verify user isn't already logged in
	if (authenticate() !== null) {
		return errorBuilder("You are already logged in");
	}
	//Check that password is acceptable and matches
	if (validatePassword($password, $passwordConfirm) != null) {
		return validatePassword($password, $passwordConfirm);
	}
	//Get database entry for verification code
	$db = new DatabaseAccessor();
	$verificationRecord = $db->getVerificationRecord($code);
	if ($verificationRecord === null) {
		return errorBuilder("This reset code does not exist anymore, but you can request a new password reset email");
	}
	//Check that it's not expired (if it is, delete)
	if ($verificationRecord->expiry < time()) {
		$db->deleteVerificationRecord($code);
		return errorBuilder("This reset code has expired, you can request a new password reset email however");
	}
	//Set user's password to hashed/salted version of password
	if (!$db->setPassword($verificationRecord->userId, $password)) {
		return errorBuilder("There was an error resetting the password, please try again or request a new reset code");
	}
	//Delete verification record
	$db->deleteVerificationRecord($code);
	//Return status of reset
	return resultBuilder("Password updated successfully!");
}

function deleteAccount($password) {
	//TODO - Decide account deletion strategy
	//Delete from certain tables like session table, add comments/mocs to deleted user, delete user?
	//Or delete everything?
	//This implementation just updates the username and email, clears sessions, and sets user status to 0 - mocs and comments will still remain
	//Verify user is logged in
	$userId = authenticate();
	if ($userId === null) {
		return errorBuilder("You must be logged in to delete your account");
	}
	//Verify password is correct
	$db = new DatabaseAccessor();
	if (!$db->verifyPassword($userId, $password)) {
		return errorBuilder("The password is not correct");
	}
	//Delete account
	$result = $db->deleteUser($userId);
	if ($result->error) {
		return errorBuilder($result->message);
	}
	return resultBuilder("Account successfully deleted");
}

function changeEmail($password, $newEmail) {
	//Verify user is logged in
	$userId = authenticate();
	if ($userId === null) {
		return errorBuilder("You must be logged in to change your email");
	}
	//Verify email is valid
	if (validateEmail($newEmail) !== null) {
		return validateEmail($newEmail);
	}
	//Verify password is correct
	$db = new DatabaseAccessor();
	if (!$db->verifyPassword($userId, $password)) {
		return errorBuilder("The password is not correct");
	}
	//Check that email is not in use
	if ($db->getUserByEmail($newEmail) !== null) {
		return errorBuilder("There is already an account using this email");
	}
	//Generate verification code, store in database, send email containing url with code and email
	$rawBytes = random_bytes(60);
	$verificationCode = md5($rawBytes); //Just to convert to URL safe characters
	$verificationCode .= "&&".urlencode($newEmail);
	if (!$db->addVerificationCode($verificationCode, $userId, time() + 3600)) {
		return errorBuilder("Error sending verification code, please try again");
	}
	$to = $newEmail;
  $subject = "MOCShare Email Change Request";
  $message = "
  Please click this link to verify your new email: https://www.".$GLOBALS['configRootDomain']."/verify.php/email/$verificationCode

  This link is valid for one hour - you can make another request if it expires.

  ";
  $headers = "From:registration@".$GLOBALS['configRootDomain']."\r\n";
  mail($to,$subject,$message,$headers);
	//Return status of change request
	return resultBuilder("Email update request sent, please check your new email account for a verification email.");
}

function verifyChangeEmail($code, $password) {
	//Get email from code
	$codeParts = explode("&&", $code);
	if (count($codeParts) > 2) {
		return errorBuilder("Sorry, but email addresses containing '&&' are not currently supported. Please email 'lauchlantoal@gmail.com' to request manual intervention.");
	}
	$newEmail = urldecode($codeParts[1]);
	//Check that email is acceptable
	if (validateEmail($newEmail) != null) {
		return validateEmail($newEmail);
	}
	//Get database entry for verification code
	$db = new DatabaseAccessor();
	$verificationRecord = $db->getVerificationRecord($code);
	if ($verificationRecord === null) {
		return errorBuilder("This reset code does not exist anymore, but you can request a new email change");
	}
	//Check that it's not expired (if it is, delete)
	if ($verificationRecord->expiry < time()) {
		$db->deleteVerificationRecord($code);
		return errorBuilder("This code has expired, you can request a new email change however");
	}
	//Check that password is correct for this user
	if (!$db->verifyPassword($verificationRecord->userId, $password)) {
		return errorBuilder("The password is not correct");
	}
	//Check that email is not in use
	if ($db->getUserByEmail($newEmail) !== null) {
		return errorBuilder("There is already an account using this email");
	}
	//Set user's email
	if (!$db->setEmail($verificationRecord->userId, $newEmail)) {
		return errorBuilder("There was an error changing the email, please try again");
	}
	//Delete verification record
	$db->deleteVerificationRecord($code);
	//Return status of reset
	return resultBuilder("Email updated successfully!");
}

function changePassword($password, $newPassword, $newPasswordConfirm) {
	//Verify user is logged in
	$userId = authenticate();
	if ($userId === null) {
		return errorBuilder("You must be logged in to change your password");
	}
	//Verify passwords match and are at least one character
	if (validatePassword($newPassword, $newPasswordConfirm) != null) {
		return validatePassword($newPassword, $newPasswordConfirm);
	}
	//Verify current password is correct
	$db = new DatabaseAccessor();
	if (!$db->verifyPassword($userId, $password)) {
		return errorBuilder("The password is not correct");
	}
	//Update user password to hashed/salted new password
	if (!$db->setPassword($userId, $newPassword)) {
		return errorBuilder("There was an error resetting the password, please try again or request a new reset code");
	}
	//Return status of change
	return resultBuilder("Password updated successfully!");
}

function changeUsername($password, $newUsername) {
	//Verify user is logged in
	$userId = authenticate();
	if ($userId === null) {
		return errorBuilder("You must be logged in to change your username");
	}
	//Verify username is valid
	if (validateUsername($newUsername) != null) {
		return validateUsername($newUsername);
	}
	//Verify password is correct
	$db = new DatabaseAccessor();
	if (!$db->verifyPassword($userId, $password)) {
		return errorBuilder("The password is not correct");
	}
	//Try to update current username to new username in username table
	$result = $db->setUsername($userId, $newUsername);
	if ($result->error) {
		return errorBuilder($result->message);
	}
	//Return status of change
	return resultBuilder("Username updated successfully!");
}

function getUser($userId) {
	$db = new DatabaseAccessor();
	$userInfo = $db->getUser($userId);
	if ($userInfo == null) {
		return errorBuilder("This user could not be found");
	} else if ($userInfo->status == 0) {
		return resultBuilder("This user's account has been deleted");
	} else if ($userInfo->status == 1) {
		return resultJSONBuilder(userEncode($userInfo));
	} else if ($userInfo->status == 2) {
		return resultBuilder("This user's account is pending verification");
	} else if ($userInfo->status == 3) {
		return resultBuilder("This user's account has been flagged for review");
	} else {
		return resultBuilder("This user's account is not available");
	}
}

function searchUsers($sortType, $timeframe, $sortOrder, $searchTerm, $limit, $offset) {
	if ($sortType != "date" && $sortType != "name" && $sortType != "mocnumber") {
		return errorBuilder("Invalid sort type");
	}
	if ($timeframe != "hour" && $timeframe != "day" && $timeframe != "week" && $timeframe != "month" && $timeframe != "year" && $timeframe != "all") {
		return errorBuilder("Invalid timeframe");
	}
	if ($sortOrder != "asc" && $sortOrder != "desc") {
		return errorBuilder("Invalid sort order");
	}
	if ($offset != null && is_numeric($offset) && (abs(intval($offset)-floatval($offset)) < 0.0001)) {
		$offset = intval($offset);
	} else {
		$offset = null;
	}
	$db = new DatabaseAccessor();
	$users = $db->searchUsers($sortType, $timeframe, $sortOrder, $searchTerm, $limit, $offset);
	if ($users == null || count($users) == 0) {
		return resultJSONBuilder("[]");
	} else {
		$userEncodings = "[";
		$i = 0;
		while ($i < (count($users)-1)) {
			$userEncodings .= userEncode($users[$i]).",";
			$i++;
		}
		$userEncodings .= userEncode($users[$i])."]";
		return resultJSONBuilder($userEncodings);
	}
}

//Parse input JSON and pass to appropriate functions
//Return the return value of the function called, or JSON of error
function parseJSON($json) {

	$data = json_decode($json);
	if (is_null($data)) {
		return '{"result":null, "error":"Error decoding input JSON"}';
	}
	if (!isset($data->endpoint)) {
		return '{"result":null, "error":"Requests must include an endpoint"}';
	}
	
	//Call the appropriate function
	switch ($data->endpoint) {
	
		case "login":
			if (isset($data->email) && isset($data->password)) {
				return login($data->email, $data->password);
			} else {
				return '{"result":null, "error":"Email and password must be sent to log in"}';
			}
			break;
			
		case "logout":
			return logout();
			break;
			
		case "register":
			if (isset($data->username) && isset($data->email) && isset($data->password) && isset($data->passwordConfirm)) {
				return register($data->username, $data->email, $data->password, $data->passwordConfirm);
			} else {
				return '{"result":null, "error":"Necessary information for registration is missing"}';
			}
			break;
			
		case "verifyRegistration":
			if (isset($data->verificationCode)) {
				return verifyRegistration($data->verificationCode);
			} else {
				return '{"result":null, "error":"Verification code must be sent"}';
			}
			break;
			
		case "resendVerification":
			if (isset($data->email)) {
				return resendVerification($data->email);
			} else {
				return '{"result":null, "error":"Email to send verification code to must be input"}';
			}
			break;
			
		case "cancelRegistration":
			if (isset($data->verificationCode)) {
				return cancelRegistration($data->verificationCode);
			} else {
				return '{"result":null, "error":"Verification code must be sent"}';
			}
			break;
		
		case "requestPasswordReset":
			if (isset($data->email)) {
				return requestPasswordReset($data->email);
			} else {
				return '{"result":null, "error":"Email to send reset code to must be input"}';
			}
			break;
			
		case "verifyPasswordReset":
			if (isset($data->code) && isset($data->password) && isset($data->passwordConfirm)) {
				return verifyPasswordReset($data->code, $data->password, $data->passwordConfirm);
			} else {
				return '{"result":null, "error":"Verification code, password, and confirmation password must be sent"}';
			}
			break;
		
		case "deleteAccount":
			if (isset($data->password)) {
				return deleteAccount($data->password);
			} else {
				return '{"result":null, "error":"Password must be sent to confirm deletion of account"}';
			}
			break;
			
		case "changeEmail":
			if (isset($data->password) && isset($data->newEmail)) {
				return changeEmail($data->password, $data->newEmail);
			} else {
				return '{"result":null, "error":"Password and new email must be sent to request email change"}';
			}
			break;
		
		case "verifyChangeEmail":
			if (isset($data->code) && isset($data->password)) {
				return verifyChangeEmail($data->code, $data->password);
			} else {
				return '{"result":null, "error":"Verification code and password must be sent to change email"}';
			}
			break;
		
		case "changePassword":
			if (isset($data->password) && isset($data->newPassword) && isset($data->newPasswordConfirm)) {
				return changePassword($data->password, $data->newPassword, $data->newPasswordConfirm);
			} else {
				return '{"result":null, "error":"Current, new, and new confirmation passwords must be sent to change password"}';
			}
			break;
		
		case "changeUsername":
			if (isset($data->password) && isset($data->newUsername)) {
				return changeUsername($data->password, $data->newUsername);
			} else {
				return '{"result":null, "error":"Password and new username must be sent to change username"}';
			}
			break;
		
		case "getUser":
			if (isset($data->userId)) {
				return getUser($data->userId);
			} else {
				return '{"result":null, "error":"userId for user must be specified"}';
			}
			break;
		
		case "searchUsers":
			if (isset($data->sortType) && isset($data->timeframe) && isset($data->sortOrder)) {
				return searchUsers($data->sortType, $data->timeframe, $data->sortOrder, $data->searchTerm, 12, $data->offset);
			} else {
				return '{"result":null, "error":"Search sortType, timeframe, and sortOrder must be specified}';
			}
			break;
			
		default:
			return '{"result":null, "error":"Requested endpoint does not exist"}';
			
	}
	
}

?>
