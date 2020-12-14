<?php

include_once("../helpers/error.php");

//Parse needed api, like api.php/moc or api.php/user
$api = mb_substr($_SERVER['PATH_INFO'], 1);

//Include requested api
$done = false;
switch ($api) {
	
	case "user":
		include_once("../api/user.php");
		break;
		
	case "moc":
		include_once("../api/moc.php");
		break;
	
	default:
		$done = true;
		echo '{"result":null, "error":"Invalid API requested"}';

}

//Pass POST'ed JSON to included API
if (!$done) {
	echo parseJSON(file_get_contents('php://input'));
}

?>
