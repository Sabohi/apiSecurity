<?php
require_once("/var/www/html/CZCRM/configs/api_config.php");
$allowedDomains = implode(",",$allowedDomainTpt);
header("Access-Control-Allow-Origin: ".$allowedDomains); 
require_once ("/var/www/html/CZCRM/configs/config.php");
require_once (_ADMIN_MODULE_PATH . "DATABASE/database_config.php");
require_once (_ADMIN_MODULE_PATH . "DATABASE/DatabaseManageri.php");
require_once (_ADMIN_MODULE_PATH . "FUNCTIONS/functions.php");
require_once ("/var/www/html/CZCRM/classes/function_log.class.php"); 
include_once("/var/www/html/CZCRM/class_for_login_logout.php");
$FLP = new logs_creation();
require_once("../classes/securityHandler.class.php");

$msg_array = array();
$msg_string = '';

$request_method = isset($_SERVER['REQUEST_METHOD'])?$_SERVER['REQUEST_METHOD']:'';

if ((isset($_SERVER['REQUEST_METHOD'])) && (($_SERVER['REQUEST_METHOD'] === 'POST') || ($_SERVER['REQUEST_METHOD'] === 'GET'))) {
	$data = isset($_REQUEST['data'])?$_REQUEST['data']:'';
	if(!empty($data)){
		$FLP->prepare_log("1",$data,"Requested data is");
		$isBase64 = IsBase64($data);
		if($isBase64)
		{
			$received_data = base64_decode($data, true);
			$data = json_decode($received_data, true);
			$req_type = (isset($data['req_type']) && !empty($data['req_type']))?$data['req_type']:'';
			if(!empty($req_type)){
				$data['reqType'] = $data['req_type'];
				$new_data = json_encode($data);
				$method = isset($methodsAllowed[$req_type])?$methodsAllowed[$req_type]:'NA';
				if($method == 'NA'){
					$msg_array = array('status'=>'error','msg'=>'422 Not Processable','statusCode'=>422);
					$msg_string = json_encode($msg_array);
				}else if($request_method == $method){
					$countData = count($data);
					if($countData<=$dataAllowedTpt){
						foreach($data as $key=>$value){
							$data[$key] = strip_tags($value);
						}
						$SH = new securityHandler($new_data);
						$token_methods = array('generateToken','refreshToken','revokeToken');
						if(in_array($req_type,$token_methods)){
							eval('$msg_string = $SH->'.$req_type.'();');
						}else{
							$msg_string = $SH->checkToken();
						}
					}else{
						$msg_array = array('status'=>'error','msg'=>'422 Not Processable','statusCode'=>422);
						$msg_string = json_encode($msg_array);
					}
				}else{
					$msg_array = array('status'=>'error','msg'=>'405 Method Not Allowed','statusCode'=>405);
					$msg_string = json_encode($msg_array);
				}
			}else{
				$msg_array = array('status'=>'error','msg'=>'422 Not Processable','statusCode'=>422);
				$msg_string = json_encode($msg_array);
			}
		}
		else{
			$msg_array = array('status'=>'error','msg'=>'406 Not Acceptable','statusCode'=>406);
			$msg_string = json_encode($msg_array);
		}
	}else{
		$msg_array = array('status'=>'error','msg'=>'422 Not Processable','statusCode'=>422);
		$msg_string = json_encode($msg_array);
	}
}else{
	$msg_array = array('status'=>'error','msg'=>'405 Method Not Allowed','statusCode'=>405);
	$msg_string = json_encode($msg_array);
}

if(empty($msg_string)){
	$msg_array = array('status'=>'error','msg'=>'Unknown error');
	$msg_string = json_encode($msg_array);
}

print $msg_string;
?>