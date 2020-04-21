<?php
require_once("/var/www/html/CZCRM/configs/api_config.php");
require_once("/var/www/html/CZCRM/configs/config.php");
require_once("/var/www/html/CZCRM/"._MODULE_PATH."DATABASE/DatabaseManageri.php");
require_once("/var/www/html/CZCRM/"._MODULE_PATH."DATABASE/database_config.php");
require_once("/var/www/html/CZCRM/"._MODULE_PATH."FUNCTIONS/functions.php");
require_once ("/var/www/html/CZCRM/classes/function_log.class.php");
require_once ("/var/www/html/CZCRM/api/db_utility.class.php");
class securityHandler extends DATABASE_MANAGER{
	private $DB, $DB_H;
	
	function __construct($dataJson=""){
		parent::__construct(DB_HOST, DB_USERNAME, DB_PASSWORD,GDB_NAME);
		$this->DB_H = $this->CONNECT();
		$this->dataJson = $dataJson;
	} 	 
	
	public function generateToken(){
		$token_expiry_period = _TOKEN_EXPIRY_PERIOD_;
		$dataJson = (isset($this->dataJson) && !empty($this->dataJson))?$this->dataJson:'';
		$dataArr =	json_decode($dataJson,true);
		$client_key	= (isset($dataArr['key']) && !empty($dataArr['key']))?$dataArr['key']:'';
		$msg_string = '';
		if(!empty($client_key)){
			//****************************Random Token Generation**********************************************
			$permitted_chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'.$client_key;
			$secret_key = substr(str_shuffle($permitted_chars), 0, 32);

			$random_string = random_bytes(16);
			$refresh_token = bin2hex($random_string);
			
			$algo_array = hash_hmac_algos();
			$sizeOfAlgos = count($algo_array) - 1;
			$selected_algo_index = rand(0,$sizeOfAlgos);
			$selected_algo = (isset($algo_array[$selected_algo_index]) && !empty($algo_array[$selected_algo_index]))?$algo_array[$selected_algo_index]:'sha256';
			
			$token = hash_hmac($selected_algo,$random_string,$secret_key);
			$token_expiry_time=time()+$token_expiry_period;

			$query = 'update clientRegistrationBasic set client_token="'.$token.'",refresh_token="'.$refresh_token.'",token_expiry_time="'.$token_expiry_time.'",is_blocked="false" where client_key="'.$client_key.'"';
			$exe_query = $this->EXECUTE_QUERY($query, $this->DB_H);		
			if($exe_query){
				$msg_array = array('status'=>'success','msg'=>'200 Ok','statusCode'=>200,'token'=>$token,'refresh_token'=>$refresh_token);
				$msg_string = json_encode($msg_array);
			}else{
				$msg_array = array('status'=>'error','msg'=>'500 Internal Server Error','statusCode'=>500);
				$msg_string = json_encode($msg_array);
			}
		}else{
			$msg_array = array('status'=>'error','msg'=>'422 Not Processable','statusCode'=>422);  //Semantic error
			$msg_string = json_encode($msg_array);
		}
		return $msg_string;	
	}
	
	public function refreshToken(){
		$dataJson = (isset($this->dataJson) && !empty($this->dataJson))?$this->dataJson:'';
		$dataArr=	json_decode($dataJson,true);
		$refresh_token	= (isset($dataArr['refresh_token']) && !empty($dataArr['refresh_token']))?$dataArr['refresh_token']:'';
		$client_key	= (isset($dataArr['key']) && !empty($dataArr['key']))?$dataArr['key']:'';
		$msg_string = '';
		//****************************Token Refreshing**********************************************
		
		if(!empty($refresh_token)){
			$query = 'select client_token,refresh_token,is_blocked from clientRegistrationBasic where client_key="'.$client_key.'"';
			$exe_query = $this->EXECUTE_QUERY($query, $this->DB_H);
			if($exe_query){
				$fetch_query = $this->FETCH_ARRAY($exe_query, MYSQLI_ASSOC);
				$refresh_token_fetched = (isset($fetch_query['refresh_token']) && !empty($fetch_query['refresh_token']))?$fetch_query['refresh_token']:'';
				$client_token = (isset($fetch_query['client_token']) && !empty($fetch_query['client_token']))?$fetch_query['client_token']:'';
				if(!empty($client_token) && !empty($refresh_token_fetched) && ($refresh_token == $refresh_token_fetched) && ($is_blocked != 'true')){
					$token_expiry_time=time()+$token_expiry_period;
					$random_string = random_bytes(16);
					$new_refresh_token = bin2hex($random_string);
					$query = 'update clientRegistrationBasic set token_expiry_time="'.$token_expiry_time.'",refresh_token="'.$new_refresh_token.'" where client_key="'.$client_key.'"';
					$exe_query = $this->EXECUTE_QUERY($query, $this->DB_H);
					if($exe_query){
						$data_array = array('refresh_token'=>$new_refresh_token);
						$msg_array = array('status'=>'success','msg'=>'200 Ok','statusCode'=>200,'data'=>$data_array);
						$msg_string = json_encode($msg_array);
					}else{
						$msg_array = array('status'=>'error','msg'=>'500 Internal Server Error','statusCode'=>500);
						$msg_string = json_encode($msg_array);
					}
				}else{		
					$msg_array = array('status'=>'error','msg'=>'401 Unauthorized','statusCode'=>401);     
					$msg_string = json_encode($msg_array);
				}
			}else{
				$msg_array = array('status'=>'error','msg'=>'500 Internal Server Error','statusCode'=>500);
				$msg_string = json_encode($msg_array);
			}
		}else{
			$msg_array = array('status'=>'error','msg'=>'422 Not Processable','statusCode'=>422);     //Semantic error
			$msg_string = json_encode($msg_array);
		}
		return $msg_string;
	}
	
	public function revokeToken(){
		$dataJson = (isset($this->dataJson) && !empty($this->dataJson))?$this->dataJson:'';
		$dataArr=	json_decode($dataJson,true);
		$client_key	= (isset($dataArr['key']) && !empty($dataArr['key']))?$dataArr['key']:'';
		$client_token	= (isset($dataArr['token']) && !empty($dataArr['token']))?$dataArr['token']:'';
		$msg_string = '';
		//****************************Token Revoking**********************************************
		if(!empty($client_key) && !empty($client_token)){
			$query = 'update clientRegistrationBasic set is_blocked="true" where client_key="'.$client_key.'" and client_token="'.$client_token.'"';
			$exe_query = $this->EXECUTE_QUERY($query, $this->DB_H);
			if($exe_query){
				$msg_array = array('status'=>'success','msg'=>'200 Ok','statusCode'=>200);
				$msg_string = json_encode($msg_array);
			}else{
				$msg_array = array('status'=>'error','msg'=>'500 Internal Server Error','statusCode'=>500);
				$msg_string = json_encode($msg_array);
			}
		}else{
			$msg_array = array('status'=>'error','msg'=>'422 Not Processable','statusCode'=>422);     //Semantic error
			$msg_string = json_encode($msg_array);
		}
		return $msg_string;
	}
	
	public function checkToken(){
		$dataJson = (isset($this->dataJson) && !empty($this->dataJson))?$this->dataJson:'';
		$dataArr=	json_decode($dataJson,true);
		$client_key	= (isset($dataArr['key']) && !empty($dataArr['key']))?$dataArr['key']:'';
		$client_token	= (isset($dataArr['token']) && !empty($dataArr['token']))?$dataArr['token']:'';
		$req_type	= (isset($dataArr['req_type']) && !empty($dataArr['req_type']))?$dataArr['req_type']:'';
		$msg_string = '';
		//****************************Token Revoking**********************************************
		if(!empty($client_key) && !empty($client_token)){
			$query = 'select registration_id,is_blocked from clientRegistrationBasic where client_key="'.$client_key.'" and client_token="'.$client_token.'"';
			$exe_query = $this->EXECUTE_QUERY($query, $this->DB_H);
			if($exe_query){
				$rows_count = $this->GET_ROWS_COUNT($exe_query);
				if($rows_count){
					$fetch_query = $this->FETCH_ARRAY($exe_query, MYSQLI_ASSOC);
					$is_blocked = (isset($fetch_query['is_blocked']) && !empty($fetch_query['is_blocked']))?$fetch_query['is_blocked']:'';
					$client_id = (isset($fetch_query['registration_id']) && !empty($fetch_query['registration_id']))?$fetch_query['registration_id']:'';
					if($is_blocked == 'true'){
						$msg_array = array('status'=>'error','msg'=>'401 Unauthorized','statusCode'=>401);     
						$msg_string = json_encode($msg_array);
					}else{
						$db_utility = new db_utility($client_id);
						$msg_string = $db_utility->process_request($dataJson);
					}
				}else{
					$msg_array = array('status'=>'error','msg'=>'401 Unauthorized','statusCode'=>401);     
					$msg_string = json_encode($msg_array);
				}	
			}else{
				$msg_array = array('status'=>'error','msg'=>'500 Internal Server Error','statusCode'=>500);
				$msg_string = json_encode($msg_array);
			}
		}else{
			$msg_array = array('status'=>'error','msg'=>'422 Not Processable','statusCode'=>422);     //Semantic error
			$msg_string = json_encode($msg_array);
		}
		return $msg_string;
	}
}
?>