<?php

//Handle MOC CRUD

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

//Return error string if any field is invalid, else return true
function validateMoc($title, $text, $thumb, $privacy, $filter) {
	return true;
}

//Return parsed text for MOC for handling markdown
function parseMocText($text) {
	return $text;
}

//Return parsed text for comment for handling whatever
function parseCommentText($text) {
	return $text;
}

//Create MOC
function createMoc($mocTitle, $mocText, $mocThumb, $mocPrivacy, $mocFilter) {
	//Check that user is authenticated
	$userId = authenticate();
	if ($userId === null) {
		return errorBuilder("You must be logged in to post new MOCs");
	}
	//Parse all fields for validity
	if (validateMoc($mocTitle, $mocText, $mocThumb, $mocPrivacy, $mocFilter) !== true) {
		return errorBuilder(validateMoc($mocTitle, $mocText, $mocThumb, $mocPrivacy, $mocFilter));
	}
	//Parse any special text handling
	$mocText = parseMocText($mocText);
	//Insert into db
	$db = new DatabaseAccessor();
	$mocLink = $db->createMoc($userId, $mocTitle, $mocText, $mocThumb, $mocPrivacy, $mocFilter);
	//Return status with link to new moc
	if ($mocLink->error) {
		return errorBuilder($mocLink->message);
	} else {
		return resultBuilder($mocLink->message);
	}
}

//Edit MOC
function editMoc($mocId, $mocTitle, $mocText, $mocThumb, $mocPrivacy, $mocFilter) {
	//Check that user is authenticated
	$userId = authenticate();
	if ($userId === null) {
		return errorBuilder("You must be logged in to edit your MOCs");
	}
	//Check that user created the moc (and the moc exists)
	$db = new DatabaseAccessor();
	$mocUser = $db->getMocCreator($mocId);
	if ($mocUser !== $userId) {
		return errorBuilder("You cannot edit MOCs you did not create");
	}
	//Parse all fields for validity
	if (validateMoc($mocTitle, $mocText, $mocThumb, $mocPrivacy, $mocFilter) !== true) {
		return errorBuilder(validateMoc($mocTitle, $mocText, $mocThumb, $mocPrivacy, $mocFilter));
	}
	//Parse any special text handling
	$mocText = parseMocText($mocText);
	//Update db
	$mocLink = $db->updateMoc($mocId, $mocTitle, $mocText, $mocThumb, $mocPrivacy, $mocFilter);
	//Return status with link to moc (?)
	if ($mocLink->error) {
		return errorBuilder($mocLink->message);
	} else {
		return resultBuilder($mocLink->message);
	}
}

//Delete MOC
function deleteMoc($mocId, $password) {
	//Check that user is authenticated
	$userId = authenticate();
	if ($userId === null) {
		return errorBuilder("You must be logged in to delete your MOCs");
	}
	//Check that user made the moc
	$db = new DatabaseAccessor();
	$mocUser = $db->getMocCreator($mocId);
	if ($mocUser !== $userId) {
		return errorBuilder("You cannot delete MOCs you did not create");
	}
	//Check that password is valid
	if (!$db->verifyPassword($userId, $password)) {
		return errorBuilder("The password is not correct");
	}
	//Delete from db
	$deleteResult = $db->deleteMoc($mocId);
	//Return status
	if ($deleteResult->error) {
		return errorBuilder($deleteResult->message);
	} else {
		return resultBuilder($deleteResult->message);
	}
}

//Like MOC
function likeMoc($mocId) {
	//Check that user is authenticated
	$userId = authenticate();
	if ($userId === null) {
		return errorBuilder("You must be logged in to like MOCs");
	}
	//Ensure user is not creator of MOC
	$db = new DatabaseAccessor();
	$mocUser = $db->getMocCreator($mocId);
	if ($mocUser === $userId) {
		return errorBuilder("You cannot like your own MOCs");
	}
	//Attempt to add like for moc
	$likeStatus = $db->likeMoc($mocId, $userId);
	if ($likeStatus->error) {
		return errorBuilder($likeStatus->message);
	} else {
		return resultBuilder($likeStatus->message);
	}
}

//Unlike MOC
function unlikeMoc($mocId) {
	//Check that user is authenticated
	$userId = authenticate();
	if ($userId === null) {
		return errorBuilder("You must be logged in to unlike MOCs");
	}
	//Ensure user is not creator of MOC
	$db = new DatabaseAccessor();
	$mocUser = $db->getMocCreator($mocId);
	if ($mocUser === $userId) {
		return errorBuilder("You cannot unlike your own MOCs");
	}
	//Attempt to delete like for moc
	$likeStatus = $db->unlikeMoc($mocId, $userId);
	if ($likeStatus->error) {
		return errorBuilder($likeStatus->message);
	} else {
		return resultBuilder($likeStatus->message);
	}
}

//Treasure MOC
function treasureMoc($mocId) {
	//Check that user is authenticated
	$userId = authenticate();
	if ($userId === null) {
		return errorBuilder("You must be logged in to treasure MOCs");
	}
	//Ensure user is not creator of MOC
	$db = new DatabaseAccessor();
	$mocUser = $db->getMocCreator($mocId);
	if ($mocUser === $userId) {
		return errorBuilder("You cannot treasure your own MOCs");
	}
	//Attempt to add treasure for moc
	$treasureStatus = $db->treasureMoc($mocId, $userId);
	if ($treasureStatus->error) {
		return errorBuilder($treasureStatus->message);
	} else {
		return resultBuilder($treasureStatus->message);
	}
}

//Untreasure Moc
function untreasureMoc($mocId) {
	//Check that user is authenticated
	$userId = authenticate();
	if ($userId === null) {
		return errorBuilder("You must be logged in to untreasure MOCs");
	}
	//Ensure user is not creator of MOC
	$db = new DatabaseAccessor();
	$mocUser = $db->getMocCreator($mocId);
	if ($mocUser === $userId) {
		return errorBuilder("You cannot untreasure your own MOCs");
	}
	//Attempt to delete treasure for moc
	$treasureStatus = $db->untreasureMoc($mocId, $userId);
	//Return status
	if ($treasureStatus->error) {
		return errorBuilder($treasureStatus->message);
	} else {
		return resultBuilder($treasureStatus->message);
	}
}

//Add comment
function addComment($mocId, $commentText) {
	//Check that user is authenticated
	$userId = authenticate();
	if ($userId === null) {
		return errorBuilder("You must be logged in to post comments");
	}
	//Parse text for validity
	$commentText = parseCommentText($commentText);
	//Attempt to add comment for moc
	$db = new DatabaseAccessor();
	$commentStatus = $db->postMocComment($mocId, $userId, $commentText);
	//Return status
	if ($commentStatus->error) {
		return errorBuilder($commentStatus->message);
	} else {
		return resultBuilder($commentStatus->message);
	}
}

//Edit comment
function editComment($commentId, $commentText) {
	//Check that user is authenticated
	$userId = authenticate();
	if ($userId === null) {
		return errorBuilder("You must be logged in to edit comments");
	}
	//Ensure that user posted the comment
	$db = new DatabaseAccessor();
	$mocUser = $db->getMocCommentCreator($commentId);
	if ($mocUser !== $userId) {
		return errorBuilder("You can only edit your own comments");
	}
	//Parse text for validity
	$commentText = parseCommentText($commentText);
	//Attempt to update comment
	$commentStatus = $db->editMocComment($commentId, $commentText);
	//Return status
	if ($commentStatus->error) {
		return errorBuilder($commentStatus->message);
	} else {
		return resultBuilder($commentStatus->message);
	}
}

//Delete comment
function deleteComment($commentId) {
	//Check that user is authenticated
	$userId = authenticate();
	if ($userId === null) {
		return errorBuilder("You must be logged in to delete comments");
	}
	//Ensure that user posted the comment
	$db = new DatabaseAccessor();
	$mocUser = $db->getMocCommentCreator($commentId);
	if ($mocUser !== $userId) {
		return errorBuilder("You can only delete your own comments");
	}
	//Attempt to delete comment
	$commentStatus = $db->deleteMocComment($commentId);
	//Return status
	if ($commentStatus->error) {
		return errorBuilder($commentStatus->message);
	} else {
		return resultBuilder($commentStatus->message);
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
	
		case "createMoc":
			if (isset($data->title) && isset($data->text) && isset($data->thumb) && isset($data->privacy) && isset($data->filter)) {
				return createMoc($data->title, $data->text, $data->thumb, $data->privacy, $data->filter);
			} else {
				return '{"result":null, "error":"MOC title, text, thumb, privacy, and filter must be sent"}';
			}
			break;
		
		case "editMoc":
			if (isset($data->mocId) && isset($data->title) && isset($data->text) && isset($data->thumb) && isset($data->privacy) && isset($data->filter)) {
				return editMoc($data->mocId, $data->title, $data->text, $data->thumb, $data->privacy, $data->filter);
			} else {
				return '{"result":null, "error":"mocId, title, text, thumb, privacy, and filter must be sent"}';
			}
			break;
			
		case "deleteMoc":
			if (isset($data->mocId) && isset($data->password)) {
				return deleteMoc($data->mocId, $data->password);
			} else {
				return '{"result":null, "error":"mocId and password must be sent"}';
			}
			break;
			
		case "likeMoc":
			if (isset($data->mocId)) {
				return likeMoc($data->mocId);
			} else {
				return '{"result":null, "error":"The mocId of the MOC must be sent"}';
			}
			break;
			
		case "unlikeMoc":
			if (isset($data->mocId)) {
				return unlikeMoc($data->mocId);
			} else {
				return '{"result":null, "error":"The mocId of the MOC must be sent"}';
			}
			break;
		
		case "treasureMoc":
			if (isset($data->mocId)) {
				return treasureMoc($data->mocId);
			} else {
				return '{"result":null, "error":"The mocId of the MOC must be sent"}';
			}
			break;
			
		case "untreasureMoc":
			if (isset($data->mocId)) {
				return untreasureMoc($data->mocId);
			} else {
				return '{"result":null, "error":"The mocId of the MOC must be sent"}';
			}
			break;
		
		case "addComment":
			if (isset($data->mocId) && isset($data->text)) {
				return addComment($data->mocId, $data->text);
			} else {
				return '{"result":null, "error":"mocId and comment text must be sent"}';
			}
			break;
			
		case "editComment":
			if (isset($data->commentId) && isset($data->text)) {
				return editComment($data->commentId, $data->text);
			} else {
				return '{"result":null, "error":"The commentId and text must be sent"}';
			}
			break;
		
		case "deleteComment":
			if (isset($data->commentId)) {
				return deleteComment($data->commentId);
			} else {
				return '{"result":null, "error":"The commentId of the comment to delete must be sent"}';
			}
			break;
			
		default:
			return '{"result":null, "error":"Requested endpoint does not exist"}';
			
	}
	
}

?>
