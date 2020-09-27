<?php

include_once("error.php");

//Database class that makes all calls to the database
class DatabaseAccessor {

	private $pdo;
	
	public function __construct() {
		include_once("connection.php");
		$this->pdo = new PDO($dbdsn, $dbuser, $dbpass, $dbopt);
	}
}
