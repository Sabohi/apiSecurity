<?php
header("Access-Control-Allow-Origin: *"); // Temporary Code- Written by kamlesh
require_once ("/var/www/html/CZCRM/configs/config.php");
require_once (_ADMIN_MODULE_PATH . "DATABASE/database_config.php");
require_once (_ADMIN_MODULE_PATH . "DATABASE/DatabaseManageri.php");
require_once (_ADMIN_MODULE_PATH . "SESSION/session_config.php");
require_once (_ADMIN_MODULE_PATH . "SESSION/session.php");
//require_once("../function.php");
require_once (_ADMIN_MODULE_PATH . "FUNCTIONS/functions.php");
require_once ("/var/www/html/CZCRM/classes/function_log.class.php"); 
require_once("db_utility.class.php");
include_once("/var/www/html/CZCRM/class_for_login_logout.php");
require_once("request_manager.class.php");
require_once('authenticate_api.php');

$FLP = new logs_creation();
$FLP->prepare_log("1",$_COOKIE['TICKET'],"cookie data is");
$FLP->prepare_log("1",$_SESSION['USER_ID'],"session data is");
$FLP->prepare_log("1",$_REQUEST["postData"],"Requested data is");
$isBase64 = IsBase64($_REQUEST['postData']);
if($isBase64)
{
	$data = base64_decode($_REQUEST['postData']);
}
else{
	$data=$_REQUEST["postData"];
}

$FLP->prepare_log("1","Data received  is here",$data);


$FLP->prepare_log("1",$data,"JSON Data  is here");

$arr_data = json_decode($data, true);
$FLP->prepare_log("1",$arr_data,"Arr Data  is");

$authResult=authApi($arr_data["reqType"]);
$FLP->prepare_log("1",$authResult,"Auth Result");
//$authResult=true;
if($authResult===true){
	if(!empty($arr_data["session_id1"]) && !empty($arr_data["user_id1"])){
		$logEvent = new LOGIN_LOGOUT;
		$logEvent->maintainLastActivity($arr_data["user_id1"],$arr_data["session_id1"]);
	}
	$app_id=isset($_REQUEST['appID'])?$_REQUEST['appID']:0;
	if((isset($arr_data["skipAuth"]) && ($arr_data["skipAuth"])) || ((isset($_REQUEST["skipAuth"])) && ($_REQUEST["skipAuth"]))){
		$app_id="frYVWZ76F63T8geX6LXwBg";
	}
	$FLP->prepare_log("1",$app_id,"App ID  is");
	$RH=new request_manager($data,$app_id);
	$RH->handle_request();
}
else{
	$message = json_encode($authResult);
	print $message;
	header($_SERVER['SERVER_PROTOCOL']." ".$authResult['statusCode']." ".$authResult['msg']);
	exit;
}
?>