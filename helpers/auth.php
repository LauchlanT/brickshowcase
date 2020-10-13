<?php

//Handle verification of user identity

include_once("error.php");
include_once("db.php");
session_start();


//Return the userid of the user if logged in, else return null
function authenticate() {
	
	//By default assume user is not logged in
	$userId = null;
	
	//If session exists, determine validity
	if (session_status() == PHP_SESSION_ACTIVE) {
		//If session userid is set and expiry is valid, set it as the userid to return
		if (isset($_SESSION['userId']) && isset($_SESSION['expiry']) && (time() < $_SESSION['expiry'])) {
			$userId = $_SESSION['userId'];
		}
	} else if ($userId === null && isset($_COOKIE['sessionId'])) {
		//Handle if session did not exist but user has session cookie
		//Get current cookie info
  	$db = new DatabaseAccessor();
		$session = $db->getSessionInfo($_COOKIE['sessionId']);
		//If the user exists in the database with this session, check if session is valid
  	if ($session !== null){
  		//Verify that the session has not expired
  		if (time() < $session->expiry) {
  			//Cookie is valid - create session and set userid to return
        $_SESSION['userId'] = $session->userId;
        $_SESSION['expiry'] = $session->expiry;
        $userId = $session->userId;
  		}
  	}
	}
	
	return $userId;
	
}
 
 
 
 
?>
