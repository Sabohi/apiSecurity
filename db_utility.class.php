<?php
	//~ require_once("interactionManager.class.php");
	require_once("/var/www/html/CZCRM/configs/config.php");
	require_once (_ADMIN_MODULE_PATH . "FUNCTIONS/functions.php");
	require_once (_ADMIN_MODULE_PATH . "FUNCTIONS/cz_Keyinfo.php");
	// include_once("../function_mail.php");
	// include_once("../function_sms.php");
	require_once("../classes/ticketHandler.class.php");                     //~Class for ticket related functions
	require_once("../classes/leadHandler.class.php");                       //~Class for lead related functions
	//~Class for escalation related functions
	require_once("../classes/smsHandler.class.php");                                //~Class for sms related functions
	require_once("../notification/notification.php");               //~For notification
	require_once ("/var/www/html/CZCRM/classes/function_log.class.php");
	
	//require_once("interactionManager.class.php");
	class db_utility extends DATABASE_MANAGER  {
		private $dataArray, $responseData;
		//private $campaign_id_array,$list_id_array;
		private $reqType, $DB, $DB_H, $client_id,$src;
		
		//----Main Function which decide how to process app
		public function __construct($clientID) {
			
			$this->client_id = $clientID;
			$db_name=($this->client_id==0)?"czcrm_generic":"crm_manager_".$clientID;
			
			parent:: __construct(DB_HOST, DB_USERNAME, DB_PASSWORD,$db_name);
			$this->DB_H = $this->CONNECT();
			//  $this->INITIATE_DATABASE("czmobile_" . $clientID);
			//~ $FLP = new logs_creation();
		}
		public  function process_request($data) {
			$data = (json_decode($data))?json_decode($data):$data;
			$FLP = new logs_creation($this->client_id);
			
			$FLP->prepare_log("1","======inside process request========and client id===".$this->client_id."===",$data);
			
			$this->dataArray = $data;
			$this->src=isset($src)?$src:'';
			$this->reqType = $data->reqType;
			$func_call = '$this->responseData=$this->' . $this->reqType . '();';
			eval($func_call);
			$FLP->prepare_log("1","======response data==============",$this->responseData);
			return $this->responseData;
		}
		//--------This is for logging sync data ------///////////////////////////////////////////////////////////////////////////////////////////////////////
		
		
		private function recentTicket(){
			$FLP = new logs_creation($this->client_id);
			$FLP->prepare_log("1","======DB UTILITY==============","recentTicket");
			$person_id=$this->dataArray->person_id;
			if(isset ($person_id) && !empty($person_id)){
				$TH = new ticketHandler($this->client_id);
				$ticketJson=json_encode($this->dataArray);
				$returned_data=$TH->recentTicket($ticketJson);
				if(isset($returned_data['Error'])){
					return $this->result='{"Error":"'.$returned_data['Error'].'"}';
					}else{
					return $this->result='{"Success":'.$returned_data.'}';
				}
			}
			else{
				return $this->result='{"Error":"No person selected for recent tickets"}';
			}
		}
		
		private function getTicketCreationTime(){
			$FLP = new logs_creation($this->client_id);
			$FLP->prepare_log("1","======DB UTILITY==============","getTicketCreationTime");
			$ticket_id=$this->dataArray->ticket_id;
			if(isset($ticket_id) && !empty($ticket_id)){
				$TH = new ticketHandler($this->client_id);
				$returned_data = $TH->getTicketCreationTime($ticket_id);
				if(isset($returned_data['Error'])){
					return $this->result='{"Error":"'.$returned_data['Error'].'"}';
					}else{
					return $this->result='{"Success":'.$returned_data.'}';
				}
			}
			else{
				return $this->result='{"Error":"Required parameters missing"}';
			}
		}
		
		private function reqTicketLatest(){
			$FLP = new logs_creation($this->client_id);
			$FLP->prepare_log("1","======DB UTILITY==============","reqTicketLatest");
			$cust_id=$this->getCustID();
			
			$query="select docket_no,ticket_status,created_on from ticket_details as a left join ticket_status as b on a.ticket_status_id=b.ticket_status_id where person_id=$cust_id and b.ticket_status not like '%close%' and b.ticket_status not like '%resolve%' order by created_on desc limit 1";
			$resultData=$this->EXECUTE_QUERY($query,$this->DB_H);
			$ticketArray=array();
			if($this->GET_ROWS_COUNT($resultData)){
				$resultRow=$this->FETCH_ARRAY($resultData,MYSQLI_ASSOC);
				return $this->result="{'docket_no':'".$resultRow["docket_no"]."','status':'".$resultRow["ticket_status"]."','created_on':'".$resultRow["created_on"]."','statusCode':200}";
			}
			else{
				return $this->result='{"Error":"No tickets Available","statusCode":422}';
			}
		}
		
		private function fnEscalationExecuterule(){
			$FLP = new logs_creation($this->client_id);
			$FLP->prepare_log("1","======DB UTILITY==============","fnEscalationExecuterule");
			$ticket_id=isset($this->dataArray->ticket_id)?trim($this->dataArray->ticket_id):'';
			$rule_id=isset($this->dataArray->rule_id)?trim($this->dataArray->rule_id):'';
			$docket_number=isset($this->dataArray->docket_number)?trim($this->dataArray->docket_number):'';
			$type=isset($this->dataArray->type)?trim($this->dataArray->type):'T';
			//~ Escalation Code - START
			require_once("../classes/escalationHandler.class.php");
			$EH = new escalationHandler($this->client_id);
			$executeEscalationRuleResult = $EH->fn_escalation_execute_rule($ticket_id,$rule_id,$docket_number,$type);
			//~ Escalation Code - END
			
			if($executeEscalationRuleResult){
				return $this->result='{"Success":"Escalation rule executed successfully"}';
				}else{
				return $this->result='{"Error":"Some error occurs"}';
			}
		}
		
		private function reqTicketStatus(){
			$FLP = new logs_creation($this->client_id);
			$FLP->prepare_log("1","======DB UTILITY==============","reqTicketStatus");
			
			$query="select ticket_status,created_on,docket_no from ticket_details as a left join ticket_status as b on a.ticket_status_id=b.ticket_status_id where docket_no like '%".$this->dataArray->ticket_no."'";
			$query_obj=$this->EXECUTE_QUERY($query,$this->DB_H);
			
			if($this->GET_ROWS_COUNT($query_obj)){
				$resultRow=$this->FETCH_ARRAY($query_obj,MYSQLI_ASSOC);
				return $this->result="{'docket_no':'".$resultRow["docket_no"]."','status':'".$resultRow["ticket_status"]."','created_on':'".$resultRow["created_on"]."','statusCode':200}";
			}
			else{
				return $this->result='{"Error":"No tickets Available","statusCode":422}';
			}
		}

		private function get_mandatory_flds($module_name,$clinet_id, $req_type = ''){
			$FLP = new logs_creation($this->client_id);
			$module_arry=array("person"=>"1","ticket"=>"2","lead"=>"3");
			$file_name_arry=array("person"=>"person_info_","ticket"=>"ticket_details_customized_","lead"=>"lead_details_");
			$dynamic_file_json_str='';
			$dynamic_file_arry= array();
			$mandatroy_fld_arry=array();
			$show_ticket_lead = $show_on_form = 0;
		
			if($module_name == 'person'){
				if(($req_type == 'createTicket') || ($req_type == 'createLead')){
					$FLP->prepare_log("1","======add/update person is called from======","createTicket or createLead");
					$show_ticket_lead = 1;
				}else if(($req_type == 'addPerson') || ($req_type == 'updatePerson')){
					$FLP->prepare_log("1","======add/update person is called from======","addPerson or updatePerson");
					$show_on_form = 1;
				}
			}
			
			if(file_exists("/var/www/html/CZCRM/dynamic_config/".$file_name_arry[$module_name].$clinet_id)){
				$dynamic_file_json_str = file_get_contents("/var/www/html/CZCRM/dynamic_config/person_info_".$clinet_id);
				$dynamic_file_arry = json_decode($dynamic_file_json_str,true);
				$flds_json=base64_decode($dynamic_file_arry['Basic']['customized_fields']['data']);
				$flds_arry=json_decode($flds_json,true);
				
			}else{
				$query="select field_name,field_mandatory from basic_form_fields where delete_flag='0' and field_mandatory='1' and form_id='".$module_arry[$module_name]."'";
				$resultData=$this->EXECUTE_QUERY($query,$this->DB_H);
				while($resultRow=$this->FETCH_ARRAY($resultData,MYSQLI_ASSOC)){
					$flds_arry[$resultRow['field_name']]['name']=$resultRow['field_name'];
					$flds_arry[$resultRow['field_name']]['mandatory']=$resultRow['field_mandatory'];
				}
			}
			foreach($flds_arry as $key=>$val){
				if($val['mandatory']==1){
					if(($show_ticket_lead == 1) && ($val['show_ticket_lead']==1)){
						$FLP->prepare_log("1","======mandatroy_fld_arry======",$mandatroy_fld_arry);

						$mandatroy_fld_arry[$val['name']]=$val['name'];	
					}else if(($show_on_form == 1) && ($val['display_field']==1)){
						$FLP->prepare_log("1","======mandatroy_fld_arry======",$mandatroy_fld_arry);

						$mandatroy_fld_arry[$val['name']]=$val['name'];	
					}else if(($show_ticket_lead == 0) && ($show_on_form == 0)){
						$FLP->prepare_log("1","======mandatroy_fld_arry======",$mandatroy_fld_arry);

						$mandatroy_fld_arry[$val['name']]=$val['name'];	
					}
				}
			}
			$FLP->prepare_log("1","======mandatroy_fld_arry======",$mandatroy_fld_arry);

			return $mandatroy_fld_arry; 
		}

		private function getCustID(){
			$FLP = new logs_creation($this->client_id);
			$FLP->prepare_log("1","======DB UTILITY==============","getCustID");
			$this->src=$src=$this->dataArray->source;
			$person_id = 0;
			if($src=='chat' || $src=="email"){
				$srcVal=$this->dataArray->email;
				$person_id      =       searchFromFile($srcVal,'email',$this->client_id);
			}
			else{
				$srcVal=substr($this->dataArray->phone,-10);
				$person_id      =       searchFromFile($srcVal,'mobile',$this->client_id);
				if($person_id == 0){
					$query="select person_id from person_info where phone1 like '".$srcVal."%' or phone2 like '".$srcVal."%' ";
					$resultData=$this->EXECUTE_QUERY($query,$this->DB_H);
					if($this->GET_ROWS_COUNT($resultData)){
						$resultRow=$this->FETCH_ARRAY($resultData,MYSQLI_ASSOC);
						$person_id = $resultRow["person_id"];
					}
				}
			}
			
			if((strtolower($person_id)!='invalid_search') && $person_id){
				return $person_id;
			}
			else{
				return $user_id=$this->createCustomer($src,$srcVal);
			}
		}
		
		private function generateDocket(){
			$FLP = new logs_creation($this->client_id);
			$FLP->prepare_log("1","======DB UTILITY==============","generateDocket");
			$queryDocket = "select sequenceNo from autoDocketno ";
			$resultDocket=$this->EXECUTE_QUERY($queryDocket,$this->DB_H);
			$rowDocket=$this->FETCH_ARRAY($resultDocket,MYSQLI_ASSOC);
			$docketNumberSuffix = $rowDocket["sequenceNo"];
			$docketNumber=$rowDocket["sequenceNo"]+1;
			$queryUpdateDocket = "update autoDocketno set sequenceNo=".$docketNumber;
			$this->EXECUTE_QUERY($queryUpdateDocket,$this->DB_H);
			$docketPrefix = date("d-m-Y-");
			$docketNumber= $docketPrefix . str_pad($docketNumberSuffix,5,0,STR_PAD_LEFT);
			return $docketNumber;
		}
		
		private function omniLogout(){
			$FLP = new logs_creation($this->client_id);
			$FLP->prepare_log("1","======DB UTILITY==============","omniLogout");
			$uuid=isset($this->dataArray->uuid)?$this->dataArray->uuid:"";
			$key=isset($this->dataArray->key)?$this->dataArray->key:"";
			$packetID=isset($this->dataArray->packetID)?$this->dataArray->packetID:"";
			$query_loggedin_live="select * from loggedin_live where uuid='".$this->MYSQLI_REAL_ESCAPE ($this->DB_H,$uuid)."'";
			
			if($loginRs=$this->EXECUTE_QUERY($query_loggedin_live,$this->DB_H)) {
				if($loginObj=$this->FETCH_OBJECT($loginRs)) {
					$loginArr=explode(" ", $loginObj->loggedin_time);
					$loginArr=explode("-", $loginArr[0]);
					$report_table = "loggedin_live_report_".$loginArr[0]."_".$loginArr[1];
					$query_loggedout_time="update $report_table set loggedout_time=now() where uuid ='".$this->MYSQLI_REAL_ESCAPE ($this->DB_H,$uuid)."' and  loggedin_time='".$loginObj->loggedin_time."'";
					$this->EXECUTE_QUERY($query_loggedout_time,$this->DB_H);
					$query_loggedout_time_generic="update ".GDB_NAME.".$report_table set loggedout_time=now() where uuid ='".$this->MYSQLI_REAL_ESCAPE ($this->DB_H,$uuid)."' and  loggedin_time='".$loginObj->loggedin_time."'";
					$this->EXECUTE_QUERY($query_loggedout_time_generic,$this->DB_H);
					session_id($loginObj->client_session);
					session_start();
					$_SESSION = array();
					// If it's desired to kill the session, also delete the session cookie.
					// Note: This will destroy the session, and not just the session data!
					if (ini_get("session.use_cookies")) {
						$params = session_get_cookie_params();
						setcookie(session_name(), '', time() - 42000,
						$params["path"], $params["domain"],
						$params["secure"], $params["httponly"]
						);
					}
					
					// Finally, destroy the session.
					session_destroy();
					$query_delete="delete from loggedin_live where uuid='".$this->MYSQLI_REAL_ESCAPE ($this->DB_H,$uuid)."'";
					$this->EXECUTE_QUERY($query_delete,$this->DB_H);
					$query_delete_generic="delete from ".GDB_NAME.".loggedin_live where uuid='".$this->MYSQLI_REAL_ESCAPE ($this->DB_H,$uuid)."'";
					$this->EXECUTE_QUERY($query_delete_generic,$this->DB_H);
				}
			}
		}
		
		private function omniLogin(){
			$FLP = new logs_creation($this->client_id);
			$FLP->prepare_log("1","======in omniLogin==============","here");
			$configFileContent = file_get_contents("/var/www/html/CZCRM/configs/config.txt");
			$fileArr = json_decode($configFileContent,true);
			$FLP->prepare_log("1","===result array====",$this->dataArray);
			$email=isset($this->dataArray->email)?$this->dataArray->email:"";
			$phone=isset($this->dataArray->phone)?$this->dataArray->phone:"";
			$uuid=isset($this->dataArray->uuid)?$this->dataArray->uuid:"";
			$key=isset($this->dataArray->key)?$this->dataArray->key:"";
			$packetID=isset($this->dataArray->packetid)?$this->dataArray->packetid:"";
			$role=isset($this->dataArray->role)?$this->dataArray->role:"";
			$query_agent="select client_id,user_id,registration_id from czcrm_generic.userAuth where uuid=  '$uuid'";
			$result = $this->EXECUTE_QUERY($query_agent,$this->DB_H);
			$FLP->prepare_log("1","===result====",$this->getLastQuery());
			if($this->GET_ROWS_COUNT($result)){
				require_once("../classes/redisHandler.class.php");
                                $redis = new redisHandler();

				//~ $this->prepare_log("=====in if result=====","herere");
				$FLP->prepare_log("1","=====in if result=====","herere");
				$row_agent=$this->FETCH_ARRAY($result,MYSQLI_ASSOC);
				$user_id=$row_agent["user_id"];
				//~ $this->prepare_log("=====user id=====",$user_id);
				$FLP->prepare_log("1","=====user id=====",$user_id);
				$client_id=$row_agent["registration_id"];
				$FLP->prepare_log("1","=====client id=====",$client_id);
				$length=32;
				$token = bin2hex(random_bytes($length));
				$redis->setRedis($token, '{"role":"'.$role.'","user_id":"'.$user_id.'","client_id":"'.$client_id.'","email":"'.$email.'","phone":"'.$phone.'","uuid":"'.$uuid.'"}');
			/*	$redis = new Redis();
				$redis->connect('127.0.0.1', 6379);
				$redis->set($token, '{"role":"'.$role.'","user_id":"'.$user_id.'","client_id":"'.$client_id.'","email":"'.$email.'","phone":"'.$phone.'","uuid":"'.$uuid.'"}');
				$redis->expire($token,'36000');
				$redis->close();*/
				$data=base64_encode('{"role":"'.$role.'","otp":"'.$token.'"}');
				
				$packetToBeSent1="action: login_otp\r\npacketID: ".$packetID."\r\ndata: ".$data."\r\nenc_type: base64\r\nuuid: ".$uuid."\r\nsrc: ticketing\r\nkey: ".$key."\r\ndestination: ".$fileArr["OCMS_NODE_ID"]."\r\nsource: ".$fileArr["TICKET_NODE_ID"]."\r\ndest_app: ".$fileArr["OCMS_APP_NAME"]."\r\n\r\n";
				//~ $this->prepare_log("=====packetToBeSent1=====",$packetToBeSent1);
				$FLP->prepare_log("1","=====packetToBeSent1=====",$packetToBeSent1);
				
				$redis_packet_key = isset($fileArr["REDIS_PACKET_KEY"])?$fileArr["REDIS_PACKET_KEY"]:'';
				
				if(!empty($redis_packet_key)){
					$FLP->prepare_log("1","======in omniLogin==============","here  uuuuuuuuu");
					//require_once("../classes/redisHandler.class.php");
					//$redis = new redisHandler();
					$redis->lpushRedis($redis_packet_key,$packetToBeSent1);
				}
				
				$FLP->prepare_log("1","=====after redis=====","------------");
				return $this->result="{'OTP':'$token'}";
			}
			else{
				return $this->result="{'Error':'Invalid user details'}";
			}
		}
		private function omniSignup(){
			$FLP = new logs_creation($this->client_id);
			$FLP->prepare_log("1","======DB UTILITY==============","omniSignup");
			$email=isset($this->dataArray->email)?$this->dataArray->email:"";
			$phone=isset($this->dataArray->phone)?$this->dataArray->phone:"";
			$name=isset($this->dataArray->name)?$this->dataArray->name:"";
			$buid=isset($this->dataArray->buid)?$this->dataArray->buid:"";
			$uuid=isset($this->dataArray->uuid)?$this->dataArray->uuid:"";
			$key=isset($this->dataArray->key)?$this->dataArray->key:"";
			$src=isset($this->dataArray->src)?$this->dataArray->src:"";
			$route_src=isset($this->dataArray->source)?$this->dataArray->source:"";
			$suid=isset($this->dataArray->suid)?$this->dataArray->suid:"";
			$timestamp=time();
			$filecontent =file_get_contents("/var/www/html/CZCRM/configs/config.txt");
			$fileArr = json_decode($filecontent,true);
			//~ $this->prepare_log("=====dataArray===",$this->dataArray);
			$FLP->prepare_log("1","=====dataArray===",$this->dataArray);
			if(empty($email)||empty($phone)||empty($name)){
				return $this->result="{'Error':'Invalid data parameters! Email,phone & name are mandatory fields'}";
			}
			else{
				$tName = "clientRegistrationBasic";
				$v = array (
				"fullName"                      =>array(STRING,$name),
				"mobile"                        =>array(STRING,$phone),
				"email"                         =>array(STRING,$email),
				"registrationTime"      =>array(STRING,$timestamp),
				"verificationStatus"=>array(STRING,'VERIFIED'),
				"verifiedOn"            =>array(STRING,$timestamp),
				"OTP"                           =>array(STRING,""),
				"OTPValid"                      =>array(STRING,""),
				"client_key"            =>array(STRING,$key),
				"buid"          =>array(STRING,$buid)
				);
				$destination = $dest_app = '';
				if(strtoupper($src)=='ECOM'){
					$src="ECOM";
					$destination = $fileArr['PROVISIONING_NODE_ID'];
					$dest_app = $fileArr['PROVISIONING_APP_NAME'];
				}
				else
				{
					$src="OMNI";
					//~ $destination = $fileArr['OMNI_NODE_ID'];
					//~ $dest_app = $fileArr['OMNI_APP_NAME'];
					//as suggested by rituraj sir
					$destination = $fileArr['PROVISIONING_NODE_ID'];
					$dest_app = $fileArr['PROVISIONING_APP_NAME'];
				}
				$tNamed = $this->INSERT($tName,$v,$this->DB_H);
				//~ $this->prepare_log("clientRegistrationBasic query",$this->getLastQuery());
				$FLP->prepare_log("1","clientRegistrationBasic query",$this->getLastQuery());
				$registration_id= mysqli_insert_id($this->DB_H);
				if($registration_id){
					$queryUpdateRegister = "update clientRegistrationBasic set status=1,client_database = concat('".DB_PREFIX."',registration_id) where registration_id = $registration_id";
					$resultRegister = $this->EXECUTE_QUERY($queryUpdateRegister,$this->DB_H);
					$FLP->prepare_log("1","update clientRegistrationBasic query",$this->getLastQuery());
					if($resultRegister){
						$FLP->prepare_log("1","clientRegistrationBasic updated",'continue');
						//entry in redis queue for mail/sms work
						$client_database = DB_PREFIX.$registration_id;
						$packetToBeSent="queue_name:".$buid."_queue"."#mail_flag:0#sms_flag:0#client_database:".$client_database."#client_key:".$buid;
						
						$data_redis = array("key"=>$buid,"value"=>$packetToBeSent);
						$data_redis_json =base64_encode(json_encode($data_redis));
						//$final_api  = "http://".DEFAULT_API_SERVER."/"._BASEDIR_."/setRedis_mutt.php";
						$final_api  = _CALL_API_DNS."/setRedis_mutt.php";
						$array=array("postData"=>$data_redis_json);
						$my_result = do_remote($final_api,$array);
						
						$client_query="insert into  clientDetails (registration_id,fullName,mobile,email,companyName,country,registrationTime,status,escalation_flag,mail_flag,auto_assign_flag,client_key,buid) select registration_id,fullName,mobile,email,companyName,country,registrationTime,status,escalation_flag,mail_flag,auto_assign_flag,client_key,buid from  clientRegistrationBasic where registration_id=$registration_id";
						$result_client=$this->EXECUTE_QUERY($client_query,$this->DB_H);
						$FLP->prepare_log("1","clientDetails query",$this->getLastQuery());
						$client_id=mysqli_insert_id($this->DB_H);
						if($client_id){
							//$json_data='{"name":"'.$name.'","email":'.$email.'","phone":"'.$phone.'","client_key":"'.$token.'","companyName":"'.$company_name.'","country":"'.$country.'"}';
							$json_data='{"name":"'.$name.'","email":'.$email.'","phone":"'.$phone.'","client_key":"'.$token.'","country":"'.$country.'"}';
							$tName  ="userAuth";
							$v = array (
							"username"                      =>array(STRING,$name),
							"firstname"                     =>array(STRING,"admin"),
							"user_password"         =>array(MYSQL_FUNCTION,'PASSWORD("'.$password.'")'),
							"registration_id"       =>array(STRING,$registration_id),
							"user_id"       =>array(STRING,1),
							"mobile"        =>array(STRING,$phone),
							"email" =>array(STRING,$email),
							"uuid"=>array(STRING,$uuid)
							);
							$tName  =       $this->INSERT($tName,$v, $this->DB_H);
							$last_inserted_id= mysqli_insert_id($this->DB_H);
							if($last_inserted_id){//----Code to be reviewed & check from here
								$query="Create database if not exists crm_manager_$registration_id";
								$this->EXECUTE_QUERY($query,$this->DB_H);
								include_once '/var/www/html/CZCRM/classes/customDB.class.php';
								$DB_obj=New customDB();
								$DB_obj->populateDB($registration_id);
								$query_user = "INSERT INTO crm_manager_$registration_id.users (user_id,user_name,user_password,first_name,phone_mobile,email1,status,user_flag,first_login_date,changepassword_on,uuid,source_app,suid,add_status)VALUES (1,'admin',PASSWORD('".$password."'),'".$username."','".$phone."','".$email."','ACTIVE',0,CURDATE(),CURDATE(),'".$uuid."','".$src."','".$suid."','1')";
								$this->EXECUTE_QUERY($query_user,$this->DB_H);
								$user_id=mysqli_insert_id($this->DB_H);
								$FLP->prepare_log("1","query_user query",$this->getLastQuery());
								if($user_id){
									$fp_login_logout_service=fopen("/var/www/html/client_id_log","a+");
									fwrite($fp_login_logout_service,$registration_id.":180,180");
									fwrite($fp_login_logout_service,"\n");
									fclose($fp_login_logout_service);
									
									//~ Create json file for users
									$table_name0 = DB_PREFIX.$registration_id.".users";
									$module_name0 = "users";
									$client_file_name0 = "/var/www/html/CZCRM/master_data_config/".$registration_id."/";
									if (!file_exists($client_file_name0)){
										mkdir($client_file_name0, 0777,true);
									}
									// $client_file_name0 = "/var/www/html/CZCRM/master_data_config/".$module_name0."_".$registration_id;
									$client_file_name0 = "/var/www/html/CZCRM/master_data_config/".$registration_id."/".$module_name0.".txt";
									$FLP->prepare_log("1","client file name",$client_file_name0);
									$data_array0 = array();
									
									$f0 = array("user_id","user_name","dept_id","status","assign_type");
									
									$qry0 = $this->SELECT ($table_name0,$f0,$_BLANK_ARRAY," AND user_name!='admin'",$this->DB_H);
									
									while($tRows0 = $this->FETCH_ARRAY ($qry0,MYSQLI_ASSOC)){
										if($tRows0['assign_type']=='ticket' || $tRows0['assign_type']=='both'){
											if($tRows0['status']=="ACTIVE"){
												$data_array0[$registration_id]['TICKET']['ACTIVE'][$tRows0['dept_id']][$tRows0['user_id']]=$tRows0['user_name'];
												$data_array0[$registration_id]['TICKET']['ACTIVE_ARRAY'][$tRows0['user_id']]=$tRows0['user_name'];
											}
											$data_array0[$registration_id]['TICKET']['ALL'][$tRows0['dept_id']][$tRows0['user_id']]=$tRows0['user_name'];
											$data_array0[$registration_id]['TICKET']['SEARCH'][$tRows0['user_id']]=$tRows0['user_name'];
										}
										if($tRows0['assign_type']=='lead' || $tRows0['assign_type']=='both'){
											if($tRows0['status']=="ACTIVE"){
												$data_array0[$registration_id]['LEAD']['ACTIVE'][$tRows0['dept_id']][$tRows0['user_id']]=$tRows0['user_name'];
												$data_array0[$registration_id]['LEAD']['ACTIVE_ARRAY'][$tRows0['user_id']]=$tRows0['user_name'];
											}
											$data_array0[$registration_id]['LEAD']['ALL'][$tRows0['dept_id']][$tRows0['user_id']]=$tRows0['user_name'];
											$data_array0[$registration_id]['LEAD']['SEARCH'][$tRows0['user_id']]=$tRows0['user_name'];
										}
										if($tRows0['assign_type']=='both'){
											if($tRows0['status']=="ACTIVE"){
												$data_array0[$registration_id]['BOTH']['ACTIVE'][$tRows0['dept_id']][$tRows0['user_id']]=$tRows0['user_name'];
												$data_array0[$registration_id]['BOTH']['ACTIVE_ARRAY'][$tRows0['user_id']]=$tRows0['user_name'];
											}
											$data_array0[$registration_id]['BOTH']['ALL'][$tRows0['dept_id']][$tRows0['user_id']]=$tRows0['user_name'];
											$data_array0[$registration_id]['BOTH']['SEARCH'][$tRows0['user_id']]=$tRows0['user_name'];
										}
									}
									
									$data_json0 = json_encode($data_array0,true);
									$fp0 = fopen($client_file_name0,"w");
									fwrite($fp0,$data_json0);
									fclose($fp0);
									//~ Create json file for country
									$table_name1 = DB_PREFIX.$registration_id.".country";
									$module_name1 = "country";
									$client_file_name1 = "/var/www/html/CZCRM/master_data_config/".$registration_id."/";
									if (!file_exists($client_file_name1)){
										mkdir($client_file_name1, 0777,true);
									}
									$client_file_name1 = "/var/www/html/CZCRM/master_data_config/".$registration_id."/".$module_name1.".txt";
									$data_array1 = array();
									$f1 = array("nicename");
									$qry1 = $this->SELECT ($table_name1,$f1,$_BLANK_ARRAY,"",$this->DB_H);
									while($tRows1 = $this->FETCH_ARRAY ($qry1,MYSQLI_ASSOC)){
										$data_array1[$registration_id]['ALL'][$tRows1['nicename']] = $tRows1['nicename'];
									}
									$data_json1 = json_encode($data_array1,true);
									$fp1 = fopen($client_file_name1,"w");
									fwrite($fp1,$data_json1);
									fclose($fp1);
									//~ Create json file for priority
									$table_name2 = DB_PREFIX.$registration_id.".priority";
									$module_name2 = "priority";
									$client_file_name2 = "/var/www/html/CZCRM/master_data_config/".$registration_id."/";
									if (!file_exists($client_file_name2)){
										mkdir($client_file_name2, 0777,true);
									}
									$client_file_name2 = "/var/www/html/CZCRM/master_data_config/".$registration_id."/".$module_name2.".txt";
									$data_array2 = array();
									$f2 = array("priority_id","priority_name","status");
									$qry2 = $this->SELECT ($table_name2,$f2,$_BLANK_ARRAY,"",$this->DB_H);
									while($tRows2 = $this->FETCH_ARRAY ($qry2,MYSQLI_ASSOC)){
										if($tRows2['status']=="ACTIVE"){
											$data_array2[$registration_id]['TICKET']['ACTIVE'][$tRows2['priority_id']] = $tRows2['priority_name'];
											$data_array2[$registration_id]['TICKET']['ACTIVE_ARRAY'][$tRows2['priority_id']] = $tRows2['priority_name'];
										}
										$data_array2[$registration_id]['TICKET']['ALL'][$tRows2['priority_id']] = $tRows2['priority_name'];
									}
									
									$data_json2 = json_encode($data_array2,true);
									$fp2 = fopen($client_file_name2,"w");
									fwrite($fp2,$data_json2);
									fclose($fp2);
									//~ Create json file for source_tab
									$table_name3 = DB_PREFIX.$registration_id.".source_tab";
									$module_name3 = "source";
									$client_file_name3 = "/var/www/html/CZCRM/master_data_config/".$registration_id."/";
									if (!file_exists($client_file_name3)){
										mkdir($client_file_name3, 0777,true);
									}
									$client_file_name3 = "/var/www/html/CZCRM/master_data_config/".$registration_id."/".$module_name3.".txt";
									$data_array3 = array();
									$f3 = array("id","source");
									$qry3 = $this->SELECT ($table_name3,$f3,$_BLANK_ARRAY,"",$this->DB_H);
									while($tRows3 = $this->FETCH_ARRAY ($qry3,MYSQLI_ASSOC)){
										$data_array3[$registration_id]['TICKET']['ALL'][$tRows3['id']] = $tRows3['source'];
										$data_array3[$registration_id]['TICKET']['SEARCH_VAL'][$tRows3['source']] = $tRows3['source'];
									}
									$data_json3 = json_encode($data_array3,true);
									$fp3 = fopen($client_file_name3,"w");
									fwrite($fp3,$data_json3);
									fclose($fp3);
									//~ Create json file for ticket_type
									$table_name4 = DB_PREFIX.$registration_id.".ticket_type";
									$module_name4 = "ticket_type";
									$client_file_name4 = "/var/www/html/CZCRM/master_data_config/".$registration_id."/";
									if (!file_exists($client_file_name4)){
										mkdir($client_file_name4, 0777,true);
									}
									$client_file_name4 = "/var/www/html/CZCRM/master_data_config/".$registration_id."/".$module_name4.".txt";
									$FLP->prepare_log("1","ticket type",$client_file_name4);
									$data_array4 = array();
									$f4 = array("id","ticket_type","status");
									$qry4 = $this->SELECT ($table_name4,$f4,$_BLANK_ARRAY,"",$this->DB_H);
									while($tRows4 = $this->FETCH_ARRAY ($qry4,MYSQLI_ASSOC)){
										if($tRows4['status']=="ACTIVE"){
											$data_array4[$registration_id]['TICKET']['ACTIVE'][$tRows4['id']] = $tRows4['ticket_type'];
											$data_array4[$registration_id]['TICKET']['ACTIVE_ARRAY'][$tRows4['id']] = $tRows4['ticket_type'];
										}
										$data_array4[$registration_id]['TICKET']['ALL'][$tRows4['id']] = $tRows4['ticket_type'];
										$data_array4[$registration_id]['TICKET']['SEARCH'][$tRows4['id']] = $tRows4['ticket_type'];
									}
									$data_json4 = json_encode($data_array4,true);
									$fp4 = fopen($client_file_name4,"w");
									fwrite($fp4,$data_json4);
									fclose($fp4);
									//~ Create json file for ticket_status
									$table_name5 = DB_PREFIX.$registration_id.".ticket_status";
									$module_name5 = "ticket_status";
									$client_file_name5 = "/var/www/html/CZCRM/master_data_config/".$registration_id."/";
									if (!file_exists($client_file_name5)){
										mkdir($client_file_name5, 0777,true);
									}
									$client_file_name5 = "/var/www/html/CZCRM/master_data_config/".$registration_id."/".$module_name5.".txt";
									$FLP->prepare_log("1","ticket status",$client_file_name5);
									$data_array5 = array();
									$f5 = array("ticket_status_id","ticket_status","status");
									$qry5 = $this->SELECT ($table_name5,$f5,$_BLANK_ARRAY,"",$this->DB_H);
									while($tRows5 = $this->FETCH_ARRAY ($qry5,MYSQLI_ASSOC)){
										if($tRows5['status']=="ACTIVE"){
											$data_array5[$registration_id]['TICKET']['ACTIVE'][$tRows5['ticket_status_id']] = $tRows5['ticket_status'];
											$data_array5[$registration_id]['TICKET']['ACTIVE_ARRAY'][$tRows5['ticket_status_id']] = $tRows5['ticket_status'];
											if($tRows5['ticket_status'] == 'NEW' || $tRows5['ticket_status'] == 'CLOSED'){
												$data_array5[$registration_id]['TICKET']['COND'][$tRows5['ticket_status_id']] = $tRows5['ticket_status'];
											}
										}
										$data_array5[$registration_id]['TICKET']['ALL'][$tRows5['ticket_status_id']] = $tRows5['ticket_status'];
									}
									
									$data_json5 = json_encode($data_array5,true);
									$fp5 = fopen($client_file_name5,"w");
									fwrite($fp5,$data_json5);
									fclose($fp5);
									$data=base64_encode('{"buid":"'.$buid.'","uuid":"'.$uuid.'","suid":"'.$suid.'","app_type":"TICKET","key":"'.$key.'","status":"success"}');
									
									$packetToBeSent2="action: omni_signup_success\r\npacketID: ".$packetID."\r\ndata: ".$data."\r\nenc_type: base64\r\nbuid: ".$buid."\r\nuuid: ".$uuid."\r\ndestination: ".$destination."\r\nsource: ".$fileArr["TICKET_NODE_ID"]."\r\ndest_app: ".$dest_app."\r\n\r\n";
									
									$redis_packet_key = isset($fileArr["REDIS_PACKET_KEY"])?$fileArr["REDIS_PACKET_KEY"]:'';
									if((strtoupper($src)=='ECOM') && (!empty($redis_packet_key))){
										require_once("../classes/redisHandler.class.php");
										$redis = new redisHandler();
										$redis->lpushRedis($redis_packet_key,$packetToBeSent2);
									}
									return $this->result='{"client_key":"'.$key.'","passcode":"'.$password.'"}';
								}
								else{
									$recovery_query1="delete from clientRegistrationBasic where registration_id=$registration_id";
									$this->EXECUTE_QUERY($recovery_query1,$this->DB_H);
									$recovery_query2="delete from clientDetails where client_id=$client_id";
									$this->EXECUTE_QUERY($recovery_query2,$this->DB_H);
									$recovery_query2="delete from userAuth where registration_id=$client_id";
									$this->EXECUTE_QUERY($recovery_query2,$this->DB_H);
									$recovery_query3="DROP database crm_manager_$registration_id";
									$this->EXECUTE_QUERY($recovery_query3,$this->DB_H);
									return $this->result="{'Error':'User already exists with another company'}";
								}
							}
							else{
								$recovery_query1="delete from clientRegistrationBasic where registration_id=$registration_id";
								$this->EXECUTE_QUERY($recovery_query1,$this->DB_H);
								$recovery_query2="delete from clientDetails where client_id=$client_id";
								$this->EXECUTE_QUERY($recovery_query2,$this->DB_H);
								return $this->result="{'Error':'Internal server error'}";
							}
						}
						else{
							$recovery_query1="delete from clientRegistrationBasic where registration_id=$registration_id";
							$this->EXECUTE_QUERY($recovery_query1,$this->DB_H);
							return $this->result="{'Error':'Client already exists'}";
						}
						}else{
						$FLP->prepare_log("1","clientRegistrationBasic not updated",'stop');
					}
				}
				else{
					$query_check="select count(*) as check_count from clientRegistrationBasic where mobile='$mobile' or email='$email'";
					$result_check=$this->EXECUTE_QUERY($query_check,$this->DB_H);
					$row_check=$this->FETCH_ARRAY($result_check,MYSQLI_ASSOC);
					if($row_check["check_count"]>0){
						$data3=base64_encode('{"buid":"'.$buid.'","uuid":"'.$uuid.'","suid":"'.$suid.'","app_type":"TICKET","key":"'.$key.'","status":"already exists"}');
						$packetToBeSent3="action: omni_signup_success\r\npacketID: ".$packetID."\r\ndata: ".$data3."\r\nenc_type: base64\r\nbuid: ".$buid."\r\nuuid: ".$uuid."\r\ndestination: ".$destination."\r\nsource: ".$fileArr["TICKET_NODE_ID"]."\r\ndest_app: ".$dest_app."\r\n\r\n";
						$redis_packet_key = isset($fileArr["REDIS_PACKET_KEY"])?$fileArr["REDIS_PACKET_KEY"]:'';
						if(isset($redis_packet_key) && !empty($redis_packet_key)){
							require_once("../classes/redisHandler.class.php");
							$redis = new redisHandler();
							$redis->lpushRedis($redis_packet_key,$packetToBeSent3);
						}
						
						return $this->result="{'Error':'Values already exists'}";
					}
					else{
						return $this->result="{'Error':'Internal server error 3'}";
					}
				}
			}
		}
		//~ Function for attaching a call with existing ticket
		private function mapCallTicket(){
			$FLP = new logs_creation($this->client_id);
			$FLP->prepare_log("1","======DB UTILITY==============","mapCallTicket");
			$existing_docket_no=isset($this->dataArray->existing_docket_no)?$this->dataArray->existing_docket_no:"";
			$tname = 'ticket_details';
			$f = array("ticket_id");
			$where1 = " and docket_no='".$existing_docket_no."'";
			$where_blank = array();
			$tname  =       $this->SELECT($tname,$f,$where_blank,$where1,$this->DB_H);
			$data = $this->FETCH_ARRAY($tname,MYSQLI_ASSOC);
			if($data['ticket_id']=='')
			{
				return $this->result='{"Error":"Docket number entered is invalid, please enter again"}';
			}
			else
			{
				$TH = new ticketHandler($this->client_id);
				$mappingJson=json_encode($this->dataArray);
				$returned_data=$TH->mapCallTicket($data['ticket_id'],$mappingJson);
				if(isset($returned_data['Error'])){
					return $this->result='{"Error":"'.$returned_data['Error'].'"}';
					}else if(isset($returned_data['Success'])){
					return $this->result='{"Success":"'.$returned_data['Success'].'"}';
					}else{
					return $this->result='{"Error":"Error while attaching call with docket entered"}';
				}
			}
		}
		private function createTicketApi(){
			$FLP = new logs_creation($this->client_id);
			$FLP->prepare_log("1","======DB UTILITY CREATETICKET API==============",$this->dataArray);
			$person_name=isset($this->dataArray->person_name)?trim($this->dataArray->person_name):'';
			$mobile_no=isset($this->dataArray->mobile_no)?trim($this->dataArray->mobile_no):'';
			$phone=isset($this->dataArray->phone)?trim($this->dataArray->phone):'';
			$created_by=isset($this->dataArray->created_by)?trim($this->dataArray->created_by):'';
			$CLIENT_FOLDER=isset($this->dataArray->CLIENT_FOLDER)?trim($this->dataArray->CLIENT_FOLDER):'';
			$this->dataArray->omni_not_added = 1;
			$this->dataArray->source_info = $mobile_no;
			$this->dataArray->call_phone_no = $phone;
			$this->dataArray->phone1 = $phone;
			$this->dataArray->source = 'Bulk Upload';
			$this->dataArray->module_name_ajax = 'create_ticket';
			$this->dataArray->reqType = 'createTicket';
			if(isset($mobile_no) && !empty($mobile_no)){
				if(file_exists("/var/www/html/CZCRM/master_data_config/".$this->client_id."/ticket_default_fields.json")){
					$fileData = file_get_contents("/var/www/html/CZCRM/master_data_config/".$this->client_id."/ticket_default_fields.json");
				}
				else{
					$fileData = file_get_contents("/var/www/html/CZCRM/configs/ticket_default_fields.json");
				}
				$defaultArray = json_decode($fileData,true);
				$default_tickettype = (isset($defaultArray['ticket_type']) && !empty($defaultArray['ticket_type']))?$defaultArray['ticket_type']:0;
				$default_dis = (isset($defaultArray['disposition_name']) && !empty($defaultArray['disposition_name']))?$defaultArray['disposition_name']:0;
				$default_subdis = (isset($defaultArray['sub_disposition_name']) && !empty($defaultArray['sub_disposition_name']))?$defaultArray['sub_disposition_name']:0;
				$default_priority_name = (isset($defaultArray['priority_name']) && !empty($defaultArray['priority_name']))?$defaultArray['priority_name']:'';
				
				$querycust = "select field_name from customized_form_fields  where form_id = 2 and delete_flag=0";
				$exe_querycust = $this->EXECUTE_QUERY($querycust,$this->DB_H);
				while($fetch_cust = $this->FETCH_ARRAY($exe_querycust,MYSQLI_ASSOC)){
						$field_name = $fetch_cust['field_name'];
						if(!isset($this->dataArray->$field_name)){
								$this->dataArray->$field_name = "";
						}
				}
				$querycust1 = "select * from customized_form_fields where form_id = 1 and show_ticket_lead = 1 and delete_flag=0";
				$exe_querycust1 = $this->EXECUTE_QUERY($querycust1,$this->DB_H);
				while($fetch_cust1 = $this->FETCH_ARRAY($exe_querycust1,MYSQLI_ASSOC)){
						$field_name = $fetch_cust1['field_name'];
						if(!isset($this->dataArray->$field_name)){
								$this->dataArray->$field_name = "";
						}
				}
				$FLP->prepare_log("1","======DB UTILITY CREATETICKET API after==============",$this->dataArray);

				$person_info_final=$this->checkPerson();
				$person_info_final_json_decode = json_decode($person_info_final, true);
				$person_id = trim($person_info_final_json_decode['person_id']);
				$this->dataArray->person_id=$person_id;
				$FLP->prepare_log("1","======DB UTILITY CREATETICKET API person_id==============",$person_id);
				$person_name = trim($person_info_final_json_decode['person_name']);
				$ticket_type = (isset($this->dataArray->ticket_type_name) && !empty($this->dataArray->ticket_type_name))?$this->dataArray->ticket_type_name:$default_tickettype;
				
				$disposition_name = (isset($this->dataArray->disposition_name) && !empty($this->dataArray->disposition_name))?$this->dataArray->disposition_name:$default_dis;
				
				$sub_disposition_name = (isset($this->dataArray->sub_disposition_name) && !empty($this->dataArray->sub_disposition_name))?$this->dataArray->sub_disposition_name:$default_subdis;
				
				$priority = (isset($this->dataArray->priority) && !empty($this->dataArray->priority))?$this->dataArray->priority:$default_priority_name;
				
				$ticket_status_name = (isset($this->dataArray->ticket_status_name) && !empty($this->dataArray->ticket_status_name))?$this->dataArray->ticket_status_name:'';
				
				$assigned_to_dept_name = (isset($this->dataArray->assigned_to_dept_name) && !empty($this->dataArray->assigned_to_dept_name))?$this->dataArray->assigned_to_dept_name:'';
				$assigned_to_user_name = (isset($this->dataArray->assigned_to_user_name) && !empty($this->dataArray->assigned_to_user_name))?$this->dataArray->assigned_to_user_name:'';
				
				$agent_remarks = (isset($this->dataArray->agent_remarks) && !empty($this->dataArray->agent_remarks))?$this->dataArray->agent_remarks:'';
				$this->dataArray->agent_remarks = base64_encode($agent_remarks);
				
				$problem_reported = (isset($this->dataArray->problem_reported) && !empty($this->dataArray->problem_reported))?$this->dataArray->problem_reported:'';
				$this->dataArray->problem_reported = base64_encode($problem_reported);

				$this->dataArray->dept_name  = $this->dataArray->assigned_to_dept_id = $this->dataArray->ticket_type  = $this->dataArray->assigned_to_user_id=$this->dataArray->user_id1 =$this->dataArray->agent_id= $this->dataArray->created_by_id=$this->dataArray->disposition=$this->dataArray->sub_disposition=$this->dataArray->user_name = $this->dataArray->priority_name= $this->dataArray->ticket_status= 0;
								
				 if(!empty($assigned_to_dept_name)){
						$query_dept = "SELECT dept_id from departments where dept_name ='".$assigned_to_dept_name."'";
						$tName_dept = $this->EXECUTE_QUERY($query_dept,$this->DB_H);
						$fetch_dept = $this->FETCH_ARRAY($tName_dept,MYSQLI_ASSOC);
						$this->dataArray->dept_name  = $this->dataArray->assigned_to_dept_id = isset($fetch_dept['dept_id'])?$fetch_dept['dept_id']:'0';
						if(empty($this->dataArray->assigned_to_dept_id)){
							$FLP->prepare_log("1","Error==============","wrong DEPT");
						}
				}
				if(!empty($this->dataArray->assigned_to_dept_id)){
					if(!empty($assigned_to_user_name)){
						$query_user = "SELECT user_id from users where user_name ='".$assigned_to_user_name."' and dept_id='".$this->dataArray->assigned_to_dept_id."' and status='ACTIVE'";
						$tName_user = $this->EXECUTE_QUERY($query_user,$this->DB_H);
						$fetch_user = $this->FETCH_ARRAY($tName_user,MYSQLI_ASSOC);
						$this->dataArray->user_name  = $this->dataArray->assigned_to_user_id = isset($fetch_user['user_id'])?$fetch_user['user_id']:'0';
						if(empty($this->dataArray->assigned_to_user_id)){
							$FLP->prepare_log("1","Error==============","wrong USER");
						}
					}
				}
				if(!empty($created_by)){
					$query_user_create = "SELECT user_id from users where user_name ='".$created_by."' and status='ACTIVE'";
					$tName_user_create = $this->EXECUTE_QUERY($query_user_create,$this->DB_H);
					$fetch_user_create = $this->FETCH_ARRAY($tName_user_create,MYSQLI_ASSOC);
					$this->dataArray->user_id1 = $this->dataArray->agent_id = $this->dataArray->created_by_id = isset($fetch_user_create['user_id'])?$fetch_user_create['user_id']:'0';
					if(empty($this->dataArray->created_by_id)){
							$FLP->prepare_log("1","Error==============","wrong create by name");
					}
				}
				if(!empty($ticket_type)){
					$query_tickettpye = "SELECT id from ticket_type where ticket_type ='".$ticket_type."'  and status='ACTIVE'";
					$tName_tickettype = $this->EXECUTE_QUERY($query_tickettpye,$this->DB_H);
					$fetch_tickettype = $this->FETCH_ARRAY($tName_tickettype,MYSQLI_ASSOC);
					$this->dataArray->ticket_type  = isset($fetch_tickettype['id'])?$fetch_tickettype['id']:'0';
					if(empty($this->dataArray->ticket_type)){
						$FLP->prepare_log("1","Error==============","wrong ticket type");
					}
				}
				if(isset($this->dataArray->ticket_type) && !empty($this->dataArray->ticket_type)){
					$query_dis = "SELECT id from disposition_tab where status='ACTIVE' and disposition_name ='".$disposition_name."' and source_type = 'TICKET' and ticket_type_id = '".$this->dataArray->ticket_type."'";
					$tName_dis = $this->EXECUTE_QUERY($query_dis,$this->DB_H);
					$fetch_dis = $this->FETCH_ARRAY($tName_dis,MYSQLI_ASSOC);
					$this->dataArray->disposition = isset($fetch_dis['id'])?$fetch_dis['id']:'0';
					if(empty($this->dataArray->disposition)){
						$FLP->prepare_log("1","Error==============","wrong disposition");
					}
				}
				if(isset($this->dataArray->disposition) && !empty($this->dataArray->disposition)){
					if(!empty($sub_disposition_name)){
						$query_sub_dis = "SELECT id from sub_disposition_tab where status='ACTIVE' and disposition_id='".$this->dataArray->disposition."' and sub_disposition_name ='".$sub_disposition_name."' and source_type = 'TICKET' and ticket_type_id = '".$this->dataArray->ticket_type."'";
						$tName_sub_dis = $this->EXECUTE_QUERY($query_sub_dis,$this->DB_H);
						$fetch_sub_dis = $this->FETCH_ARRAY($tName_sub_dis,MYSQLI_ASSOC);
						$this->dataArray->sub_disposition = isset($fetch_sub_dis['id'])?$fetch_sub_dis['id']:'0';
						if(empty($this->dataArray->sub_disposition)){
								$FLP->prepare_log("1","Error==============","wrong sub disposition");
						}
					}
				}
				if(!empty($priority)){
					$query_priority = "SELECT priority_id from priority where status='ACTIVE' and priority_name ='".$priority."'";
					$tName_priority = $this->EXECUTE_QUERY($query_priority,$this->DB_H);
					$fetch_priority = $this->FETCH_ARRAY($tName_priority,MYSQLI_ASSOC);
					$this->dataArray->priority_name = isset($fetch_priority['priority_id'])?$fetch_priority['priority_id']:'0';
					if(empty($this->dataArray->priority_name)){
							$FLP->prepare_log("1","Error==============","wrong priority_name");
					}
				}
				if(!empty($ticket_status_name)){
						$query_status = "SELECT ticket_status_id from ticket_status where status='ACTIVE' and ticket_status ='".$ticket_status_name."' and ticket_type_flag = 1";
						$tName_status = $this->EXECUTE_QUERY($query_status,$this->DB_H);
						$fetch_status = $this->FETCH_ARRAY($tName_status,MYSQLI_ASSOC);
						$this->dataArray->ticket_status = isset($fetch_status['ticket_status_id'])?$fetch_status['ticket_status_id']:'0';
						if(empty($this->dataArray->ticket_status)){
								$FLP->prepare_log("1","Error==============","wrong status");
						}
				}
				$FLP->prepare_log("1","======DB UTILITY CREATETICKET API FINAL ARRAY==============",$this->dataArray);
				$CREATE_TICKET = $this->createTicket();
				print ($CREATE_TICKET);
				$FLP->prepare_log("1","===CREATE_TICKET===========",$CREATE_TICKET);			
			}
			else{
				return $this->result='{"status":"error","message":"Unable to create Ticket! Person mobile is blank"}';
			}
		}
		//~ Wrapper function for creating ticket - 3/11/2017 by sabohi
		private function createTicket(){
			$FLP = new logs_creation($this->client_id);
			$FLP->prepare_log("1","======DB UTILITY==============","createTicket");
			$FLP->prepare_log("1","======data received==============",json_encode($this->dataArray));
			//~ Parameters required for both add person api(reqAddPerson) and create ticket api(reqCreateTicket)
			$person_id=isset($this->dataArray->person_id)?trim($this->dataArray->person_id):0;
			$person_name=isset($this->dataArray->person_name)?trim($this->dataArray->person_name):'';
			$mobile_no=isset($this->dataArray->mobile_no)?trim($this->dataArray->mobile_no):'';
			$cuid=isset($this->dataArray->cuid)?trim($this->dataArray->cuid):'0';
			//~ $ticket_status_id = isset($this->dataArray->ticket_status_id)?$this->dataArray->ticket_status_id:'';
			//$ticket_status_id = isset($this->dataArray->ticket_status)?$this->dataArray->ticket_status:'';
			/////////code by nitin////////////
			$moduleName=$this->dataArray->module_name_ajax;
			//////////end of code///////////////
			//~ $this-> prepare_log("=====person_id======",$this->dataArray);
			//////////////////////////////////////////////////for creating person selected fields to show in ticket grid
			$this->dataArray->person_flds_show = "";
			$person_flds_file="/var/www/html/CZCRM/master_data_config/".$this->client_id."/person_fields_json.txt";
			if(file_exists($person_flds_file)){
				$person_fld_json        =       file_get_contents($person_flds_file);
				$person_fld_json_arry           =       json_decode($person_fld_json,true);
				$prsn_fld_str='{';
				foreach($person_fld_json_arry as $prsn_key => $prsn_val){
					$prsn_fld_str .='"'.$prsn_val.'":"'.$this->dataArray->$prsn_key.'",';
				}
				$prsn_fld_str =rtrim($prsn_fld_str,',');
				$prsn_fld_str .='}';
				$this->dataArray->person_flds_show = base64_encode($prsn_fld_str);
				}else{
				$person_flds_file="/var/www/html/CZCRM/person_fields_json.txt";
				if(file_exists($person_flds_file)){
					$person_fld_json        =       file_get_contents($person_flds_file);
					$person_fld_json_arry   =       json_decode($person_fld_json,true);
					$prsn_fld_str='{';
					if($this->dataArray->source=='ivr' && !empty($person_id)){
						$this->dataArray->search_person_param = 'person_id';
						$this->dataArray->search_person_param_value = $person_id;
						
						$person_info_final = $this->searchPersonNew();
						$FLP->prepare_log("1","======person_info_final==============",$person_info_final);
						
						$person_info_final_json_decode = json_decode($person_info_final, true);
						$FLP->prepare_log("1","======person_info_final_json_decode==============",$person_info_final_json_decode);
						
						$search_person_status = (isset($person_info_final_json_decode['status']) && !empty($person_info_final_json_decode['status']))?$person_info_final_json_decode['status']:'';
						
						if(!empty($search_person_status) && ($search_person_status == 'success')){
							$person_array = isset($person_info_final_json_decode['data'][0])?$person_info_final_json_decode['data'][0]:array();
							$FLP->prepare_log("1","======person_array==============",$person_array);
						}
						foreach($person_fld_json_arry as $prsn_key => $prsn_val){
							$prsn_fld_str .='"'.$prsn_val.'":"'.$person_array[$prsn_key].'",';
						}
					}
					else{
						// $person_fld_json        =       file_get_contents($person_flds_file);
						// $person_fld_json_arry   =       json_decode($person_fld_json,true);
						// $prsn_fld_str='{';
						foreach($person_fld_json_arry as $prsn_key => $prsn_val){
							$prsn_fld_str .='"'.$prsn_val.'":"'.$this->dataArray->$prsn_key.'",';
						}
					}
					$prsn_fld_str =rtrim($prsn_fld_str,',');
					$prsn_fld_str .='}';
					$this->dataArray->person_flds_show = base64_encode($prsn_fld_str);
				}
			}
			//////////////////////////////////////////////
			$parentapp = isset($this->dataArray->parentapp)?trim($this->dataArray->parentapp):"";
			//////////////////////////////code by nitin for server side validtion
			$moduleName1 = $this->dataArray->module_name_ajax.'_'.$this->client_id;
			$error_hashArray        =       api_ValidationFunction($moduleName1);
			$error_flag                     =       0;
			$error_flag             =       ShowErrorDiv($error_hashArray);
			$errors = explode("#TVT#",$error_flag);
			if($errors[0]!=0){
				// return $this->result='{"Error":"'.$errors[1].'"}';
				return $this->result='{"status":"error","message":"'.$errors[1].'","statusCode":422}';
			}
			else{
				if(!empty($parentapp) && ($parentapp == 1) && !empty($cuid)){
					$queryCheck="select person_id from person_info where cuid=".$cuid;
					$result_check=$this->EXECUTE_QUERY($queryCheck,$this->DB_H);
					$row_check=$this->FETCH_ARRAY($result_check,MYSQLI_ASSOC);
					$person_id = (isset($row_check['person_id']) && !empty($row_check['person_id']))?$row_check['person_id']:0;
				}
				else{
					if(empty($person_id) && !empty($mobile_no)){
						$person_info_final=$this->checkPerson();
						$person_info_final_json_decode = json_decode($person_info_final, true);
						$person_id = trim($person_info_final_json_decode['person_id']);
						$this->dataArray->person_id=$person_id;
						$person_name = trim($person_info_final_json_decode['person_name']);
					}
					//~ Add person if doesnt exists or update the existing person with the updated paramets found
					$FLP->prepare_log("1","======person id==============",$person_id);
					if(empty($person_id)){
						$person_info_final=$this->addPerson();
						$person_info_final_json_decode = json_decode($person_info_final, true);
						$FLP->prepare_log("1","======person add result==============",$person_info_final_json_decode);
						
						$add_person_status = (isset($person_info_final_json_decode['status']) && !empty($person_info_final_json_decode['status']))?$person_info_final_json_decode['status']:'error';
						
						if($add_person_status == 'success'){
							$person_id = (isset($person_info_final_json_decode['data']['person_id']) && !empty($person_info_final_json_decode['data']['person_id']))?trim($person_info_final_json_decode['data']['person_id']):0;
							$this->dataArray->person_id=$person_id;
							$person_name = (isset($person_info_final_json_decode['data']['person_name']) && !empty($person_info_final_json_decode['data']['person_name']))?trim($person_info_final_json_decode['data']['person_name']):'';
							}
							else{
								return $this->result='{"status":"error","message":"Unable to create ticket","statusCode":422}';
							}
					}
					else{
						$this->dataArray->no_cust_fields = "no";
						$this->dataArray->from_create_ticket = "true";
						$person_info_final=$this->updatePerson();
						$person_info_final_json_decode = json_decode($person_info_final, true);
						$FLP->prepare_log("1","=======person_info_final_json_decode=======",$person_info_final_json_decode);
						$update_person_status = (isset($person_info_final_json_decode['status']) && !empty($person_info_final_json_decode['status']))?$person_info_final_json_decode['status']:'error';
						if($update_person_status == 'success'){
							if(!isset($this->dataArray->person_mail) && empty($this->dataArray->person_mail)){
								$this->dataArray->person_mail = (isset($person_info_final_json_decode['data']['person_mail']) && !empty($person_info_final_json_decode['data']['person_mail']))?trim($person_info_final_json_decode['data']['person_mail']):'';
							}
							$person_name =$this->dataArray->person_name;
						}else{
							return $this->result='{"status":"error","message":"Unable to create ticket","statusCode":422}';
						}
					}
				}
				$error_not_exists = 1;
				$tpf = (isset($this->dataArray->tpf) && !empty($this->dataArray->tpf))?$this->dataArray->tpf:'';
				if(!empty($tpf) && ($tpf == 1)){
					
					$dept_phone = (isset($this->dataArray->dept_phone) && !empty($this->dataArray->dept_phone))?$this->dataArray->dept_phone:'';
					
					if(!empty($dept_phone)){
						$query_get_dept_id = "SELECT dept_id from departments where dept_phone ='".$dept_phone."'";
						$tName_get_dept_id = $this->EXECUTE_QUERY($query_get_dept_id,$this->DB_H);
						
						if($tName_get_dept_id){
							$fetch_get_dept_id = $this->FETCH_ARRAY($tName_get_dept_id,MYSQLI_ASSOC);
							$this->dataArray->assigned_to_dept_id = isset($fetch_get_dept_id['dept_id'])?$fetch_get_dept_id['dept_id']:'0';
							$FLP->prepare_log("1","assigned_to_dept_id fetched on basis of dept_phone",$this->dataArray->assigned_to_dept_id);
						}
					}
					
                    $this->dataArray->phone1 = (isset($this->dataArray->phone_no) && !empty($this->dataArray->phone_no))?$this->dataArray->phone_no:'';
					
                    $ticket_type_name = (isset($this->dataArray->ticket_type_name) && !empty($this->dataArray->ticket_type_name))?$this->dataArray->ticket_type_name:'';
                    $disposition_name = (isset($this->dataArray->disposition_name) && !empty($this->dataArray->disposition_name))?$this->dataArray->disposition_name:'';
                    $sub_disposition_name = (isset($this->dataArray->sub_disposition_name) && !empty($this->dataArray->sub_disposition_name))?$this->dataArray->sub_disposition_name:'';
                    $priority_name = (isset($this->dataArray->priority_name) && !empty($this->dataArray->priority_name))?$this->dataArray->priority_name:'';
					//$ticket_status_name = (isset($this->dataArray->ticket_status_name) && !empty($this->dataArray->ticket_status_name))?$this->dataArray->ticket_status_name:'';
					
					$issue_mandatory_flag = (isset($this->dataArray->issue_mandatory_flag) && !empty($this->dataArray->issue_mandatory_flag))?$this->dataArray->issue_mandatory_flag:'';
					if((!empty($issue_mandatory_flag) && ($issue_mandatory_flag == 1)) && (empty($ticket_type_name) || empty($disposition_name) || empty($sub_disposition_name))){
						$error_not_exists = 0;
						$mand_array = array();
						if(empty($ticket_type_name)){
							$mand_array[] = 'Ticket type';
						}
						if(empty($disposition_name)){
							$mand_array[] = 'Disposition';
						}
						if(empty($sub_disposition_name)){
							$mand_array[] = 'Sub Disposition';
						}
						$mand_string = implode(",",$mand_array)." ";
						// return $this->result='{"Error":"Required fields '.$mand_string.'missing"}';
						return $this->result='{"status":"error","message":"Required fields '.$mand_string.'missing", "statusCode": 422}';                          
					}
				}
				//~ If person added/updated successfully, create ticket
				if($error_not_exists){
					if(isset($person_id) && !empty($person_id)){
						if(!empty($tpf) && ($tpf == 1)){
							if(!empty($ticket_type_name)){
								$query_tt = "SELECT id from ticket_type where status='ACTIVE' and ticket_type ='".$ticket_type_name."'";
								$tName_tt = $this->EXECUTE_QUERY($query_tt,$this->DB_H);
								$fetch_tt = $this->FETCH_ARRAY($tName_tt,MYSQLI_ASSOC);
								$this->dataArray->ticket_type = isset($fetch_tt['id'])?$fetch_tt['id']:'0';
								if(empty($this->dataArray->ticket_type)){
									$FLP->prepare_log("1","Error==============","wrong ticket type");
								}
							}
							
							if(isset($this->dataArray->ticket_type) && !empty($this->dataArray->ticket_type)){
								if(!empty($disposition_name)){
									$query_dis = "SELECT id from disposition_tab where status='ACTIVE' and ticket_type_id = '".$this->dataArray->ticket_type."' and disposition_name ='".$disposition_name."'";
									$tName_dis = $this->EXECUTE_QUERY($query_dis,$this->DB_H);
									$fetch_dis = $this->FETCH_ARRAY($tName_dis,MYSQLI_ASSOC);
									$this->dataArray->disposition = isset($fetch_dis['id'])?$fetch_dis['id']:'0';
									if(empty($this->dataArray->disposition)){
										$FLP->prepare_log("1","Error==============","wrong disposition");
									}
								}
								
								if(isset($this->dataArray->disposition) && !empty($this->dataArray->disposition)){
									if(!empty($sub_disposition_name)){
										$query_sub_dis = "SELECT id from sub_disposition_tab where status='ACTIVE' and ticket_type_id = '".$this->dataArray->ticket_type."' and disposition_id='".$this->dataArray->disposition."' and sub_disposition_name ='".$sub_disposition_name."'";
										$tName_sub_dis = $this->EXECUTE_QUERY($query_sub_dis,$this->DB_H);
										$fetch_sub_dis = $this->FETCH_ARRAY($tName_sub_dis,MYSQLI_ASSOC);
										$this->dataArray->sub_disposition = isset($fetch_sub_dis['id'])?$fetch_sub_dis['id']:'0';
										if(empty($this->dataArray->sub_disposition)){
											$FLP->prepare_log("1","Error==============","wrong sub disposition");
										}
									}
								}
							}
							
							if(!empty($priority_name)){
								$query_pr = "SELECT priority_id from priority where priority_name ='".$priority_name."'";
								$tName_pr = $this->EXECUTE_QUERY($query_pr,$this->DB_H);
								$fetch_pr = $this->FETCH_ARRAY($tName_pr,MYSQLI_ASSOC);
								$this->dataArray->priority_name = isset($fetch_pr['priority_id'])?$fetch_pr['priority_id']:'0';
								if(empty($this->dataArray->priority_name)){
									$FLP->prepare_log("1","Error==============","wrong priority name");
								}
							}
							$this->dataArray->ticket_status = 1;
							/*if(!empty($ticket_status_name)){
								$query_ts = "SELECT ticket_status_id from ticket_status where ticket_status ='".$ticket_status_name."'";
								$tName_ts = $this->EXECUTE_QUERY($query_ts,$this->DB_H);
								$fetch_ts = $this->FETCH_ARRAY($tName_ts,MYSQLI_ASSOC);
								$this->dataArray->ticket_status = isset($fetch_ts['ticket_status_id'])?$fetch_ts['ticket_status_id']:'0';
								if(empty($this->dataArray->ticket_status)){
								$FLP->prepare_log("1","Error==============","wrong ticket status");
								}
							}*/
						}
						
						//~ $this->prepare_log("Success","Person added successfully");
						$this->dataArray->person_id = $person_id;
						$this->dataArray->person_name = $person_name;
						//~ Calculate SLA time for ticket - START
						$query_sub_dis = "select time_type, sla_time from sub_disposition_tab where source_type='TICKET' and id=".$this->dataArray->sub_disposition;
						$tName_sub_dis = $this->EXECUTE_QUERY($query_sub_dis,$this->DB_H);
						$fetch_sub_dis = $this->FETCH_ARRAY($tName_sub_dis,MYSQLI_ASSOC);
						$time_type = isset($fetch_sub_dis['time_type'])?$fetch_sub_dis['time_type']:'';
						$sla_time = isset($fetch_sub_dis['sla_time'])?$fetch_sub_dis['sla_time']:'';
						$this->dataArray->sla_time = '';
						if(!empty($time_type) && !empty($sla_time)){
							require_once("../classes/escalationHandler.class.php");
							$EH = new escalationHandler($this->client_id);
							//~ Fetching time plan
							$query_time_plan = "select plan_id from time_plan";
							$exe_time_plan = $this->EXECUTE_QUERY($query_time_plan,$this->DB_H);
							$fetch_time_plan = $this->FETCH_ARRAY($exe_time_plan,MYSQLI_ASSOC);
							$time_plan = isset($fetch_time_plan['plan_id'])?$fetch_time_plan['plan_id']:'';
							
							//~ Fetching holiday array
							$rs_holiday_arr = array();
							$tableName      =       "holidays";
							$fields         =       array (
							"holiday_date"
							);
							$where          =       array();
							$others         =       " and holiday_date between date(now()) and date_add(date(now()),INTERVAL 8 MONTH)";
							if($rs_date=$this->SELECT($tableName,$fields,$where,$others,$this->DB_H))
							{
								while($rs_holiday_obj=$this->FETCH_OBJECT($rs_date))
								{
									$rs_holiday_arr[$rs_holiday_obj->holiday_date]=1;
								}
							}
							$created_on = date('Y-m-d H:i:s');
							$scheculed_time = $EH->fn_escalation_calculate_hours($sla_time,$time_type,$created_on,$time_plan,$rs_holiday_arr);
							$this->dataArray->sla_time = $scheculed_time;
						}
						//~ Calculate SLA time for ticket - END
						$TH = new ticketHandler($this->client_id);
						$ticketJson=json_encode($this->dataArray);
						$returned_data=$TH->createTicket($ticketJson);
						$returned_data = explode("#$#",$returned_data);
						$docket_no = $returned_data[0];
						$ticket_id = $returned_data[1];
						$client_id = $this->client_id;
						if(isset($ticket_id) && !empty($ticket_id)){
							
							$priority_id = (isset($this->dataArray->priority_name) && !empty($this->dataArray->priority_name))?$this->dataArray->priority_name:0;
							$ticket_status_id = (isset($this->dataArray->ticket_status) && !empty($this->dataArray->ticket_status))?$this->dataArray->ticket_status:0;
							$source = (isset($this->dataArray->source) && !empty($this->dataArray->source))?$this->dataArray->source:'';
							$ticket_type_id = (isset($this->dataArray->ticket_type) && !empty($this->dataArray->ticket_type))?$this->dataArray->ticket_type:0;
							$time = time();
							/*
							// Code for dashboard - START
							$FLP->prepare_log("1","dashboard work", "   START   ");
							require_once("/var/www/html/CZCRM/classes/dashboardHandler.class.php");
							$DH = new dashboardHandler($client_id);
							
							$dashboard_ticket_created_data_arr = array("entity_type"=>"ticket","event"=>"ticket_created","client_id"=>"$client_id","ticket_id"=>"$ticket_id","ticket_status_id"=>$ticket_status_id,"ticket_priority_id"=>$priority_id,"ticket_type_id"=>$ticket_type_id,"ticket_source"=>$source);

							$dashboard_ticket_created_data = base64_encode(json_encode($dashboard_ticket_created_data_arr));
							$FLP->prepare_log("1","dashboard_ticket_created_data", $dashboard_ticket_created_data);

							$DH->pushDashboardDataToRedisList($dashboard_ticket_created_data);
							$FLP->prepare_log("1","dashboard work", "   END   ");
							
							// Code for dashboard - END
								*/					
							//~ Escalation Code - START
							require_once("../classes/escalationHandler.class.php");
							$EH = new escalationHandler($this->client_id);
							//~ Getting escalation ip
							$configFileContent = file_get_contents("/var/www/html/CZCRM/configs/config.txt");
							$configFileArr = json_decode($configFileContent,true);
							$escalation_ip = isset($configFileArr["ESCALATION_IP"])?$configFileArr["ESCALATION_IP"]:"";
							$executed_rules = $EH->fn_executed_rules($ticket_id,'T');

							$URL = "http://".$escalation_ip."/checkApplicable?ticketId=$ticket_id&executedRules=".$executed_rules."&type=T&db=".$this->client_id."";
							$FLP->prepare_log("1","escalation URL",$URL);
							$result=do_remote_without_json($URL,"");
							//~ Escalation Code - END
							//~ Fetching parameters for mail and SMS - START
							//$query_fetch_details="select assigned_to_user_id,problem_reported,agent_remarks,action_taken,docket_no,source,a.created_on,time(a.created_on) as creation_time,a.modified_on,time_format(timediff(NOW(),a.created_on),'%Hh %im') as time_elapsed,last_escalated_on,ticket_assigned_time,pr.priority_name,dt.disposition_name,sdt.sub_disposition_name,tt.ticket_type,cm.company_name,dpts.dept_name,us.user_name,ts.ticket_status from ticket_details as a left join priority as pr on a.priority_id=pr.priority_id left join disposition_tab as dt on a.disposition_id=dt.id left join sub_disposition_tab as sdt on a.sub_disposition_id=sdt.id left join ticket_type as tt on tt.id=a.ticket_type_id left join company_info as cm on cm.company_id=a.company_id left join departments as dpts on dpts.dept_id=a.assigned_to_dept_id left join users as us on us.user_id=a.assigned_to_user_id left join ticket_status as ts on a.ticket_status_id=ts.ticket_status_id where ticket_id=$ticket_id";
							$query_fetch_details="select assigned_to_user_id,problem_reported,agent_remarks,action_taken,docket_no,source,a.created_on,time(a.created_on) as creation_time,a.modified_on,time_format(timediff(NOW(),a.created_on),'%Hh %im') as time_elapsed,last_escalated_on,ticket_assigned_time,pr.priority_name,dt.disposition_name,sdt.sub_disposition_name,tt.ticket_type,dpts.dept_name,us.user_name,ts.ticket_status from ticket_details as a left join priority as pr on a.priority_id=pr.priority_id left join disposition_tab as dt on a.disposition_id=dt.id left join sub_disposition_tab as sdt on a.sub_disposition_id=sdt.id left join ticket_type as tt on tt.id=a.ticket_type_id left join departments as dpts on dpts.dept_id=a.assigned_to_dept_id left join users as us on us.user_id=a.assigned_to_user_id left join ticket_status as ts on a.ticket_status_id=ts.ticket_status_id where ticket_id=$ticket_id";
							$result_fetch_details = $this->EXECUTE_QUERY($query_fetch_details,$this->DB_H);
							$row_fetch_details = $this->FETCH_ARRAY($result_fetch_details,MYSQLI_ASSOC);
							$short_docket_number = substr($docket_no,8);
							$searchString=array(
							"%DOCKET_NUMBER%",
							"%PERSON_NAME%",
							"%SUBJECT%",
							"%TICKET_STATUS%",
							"%PROBLEM_REPORTED%",
							"%AGENT_REMARKS%",
							"%ACTION_TAKEN%",
							"%PRIORITY%",
							"%ASSIGNED_USER%",
							"%ASSIGNED_DEPT%",
							"%CREATION_TIME%",
							"%CREATION_DATE_TIME%",
							"%LAST_UPDATION_TIME%",
							//"%COMPANY_NAME%",
							"%DOCKET_SHORT%",
							"%TICKET_TYPE%",
							"%DISPOSITION%",
							"%SUB_DISPOSITION%",
							"%TIME_ELAPSED%",
							"%LAST_ESCALATION_TIME%",
							"%ASSIGNMENT_TIME%"
							);
							$replaceString=array(
							$docket_no,
							$person_name,
							strip_tags($row_fetch_details['problem_reported']),
							$row_fetch_details['ticket_status'],
							$row_fetch_details['problem_reported'],
							$row_fetch_details['agent_remarks'],
							$row_fetch_details['action_taken'],
							$row_fetch_details['priority_name'],
							$row_fetch_details['user_name'],
							$row_fetch_details['dept_name'],
							$row_fetch_details['creation_time'],
							$row_fetch_details['created_on'],
							$row_fetch_details['modified_on'],
							//$row_fetch_details['company_name'],
							$short_docket_number,
							$row_fetch_details['ticket_type'],
							$row_fetch_details['disposition_name'],
							$row_fetch_details['sub_disposition_name'],
							$row_fetch_details['time_elapsed'],
							$row_fetch_details['last_escalated_on'],
							$row_fetch_details['ticket_assigned_time'],
							);
							$person_mail =  isset($this->dataArray->person_mail)?$this->dataArray->person_mail:"";
							$mobile_no = isset($this->dataArray->mobile_no)?$this->dataArray->mobile_no:"";
							$assigned_to_user_id = isset($this->dataArray->assigned_to_user_id)?$this->dataArray->assigned_to_user_id:"";
							$assigned_to_dept_id = isset($this->dataArray->assigned_to_dept_id)?$this->dataArray->assigned_to_dept_id:"";
							$created_by = isset($this->dataArray->created_by)?$this->dataArray->created_by:"";
							$created_by_id = isset($this->dataArray->created_by_id)?$this->dataArray->created_by_id:"";
							$user_mail = $user_phone = "";
							$client_folder = $this->dataArray->CLIENT_FOLDER;
							if(!empty($assigned_to_dept_id)){
								$tNameDept      =       "departments";
								$farrdept               =               array("dept_email","dept_phone","dept_name");
								$whdept = array (
								"dept_id" => array (STRING, $assigned_to_dept_id)
								);
								$tNameDept = $this->SELECT($tNameDept,$farrdept,$whdept,_BLANK_,$this->DB_H);
								$tNameDept = $this->FETCH_ARRAY ($tNameDept, MYSQLI_ASSOC);
								$dept_mail = $tNameDept["dept_email"];
								$dept_phone = $tNameDept["dept_phone"];
								$dept_name_timeline = "Department ".$tNameDept["dept_name"];
								if(empty($assigned_to_user_id)){
									//code for timeline history start//
									$datetime       =       time();
									$datajson       =       '{"action":"ticket_assigned","date_time":"'.$datetime.'","action_by":"'.$created_by.'","action_to":"'.$dept_name_timeline.'","ticket_id":"'.$ticket_id.'","docket_no":"'.$docket_no.'","client_folder":"'.$client_folder.'","type":"TICKET","client_id":"'.$client_id.'"}';
									timeline_history($datajson);
									//code for timeline history end//
								}
							}
							if(!empty($assigned_to_user_id)){
								$tNameUser      =       "users";
								$farruser               =               array("email1","phone_mobile","user_name");
								$whuser = array (
								"user_id" => array (STRING, $assigned_to_user_id)
								);
								$tNameUser = $this->SELECT($tNameUser,$farruser,$whuser,_BLANK_,$this->DB_H);
								$tNameUser = $this->FETCH_ARRAY ($tNameUser, MYSQLI_ASSOC);
								$user_mail = $tNameUser["email1"];
								$user_phone = $tNameUser["phone_mobile"];
								$assignusername = $tNameUser["user_name"];
								
								//code for timeline history start//
								$datetime       =       time();
								
								$datajson       =       '{"action":"ticket_assigned","date_time":"'.$datetime.'","action_by":"'.$created_by.'","action_to":"'.$assignusername.'('.$tNameDept["dept_name"].')","ticket_id":"'.$ticket_id.'","docket_no":"'.$docket_no.'","client_folder":"'.$client_folder.'","type":"TICKET","client_id":"'.$client_id.'"}';
								timeline_history($datajson);
								
								//code for timeline history end//
								
							}
							
							$filename       =       array();
							//~ Fetching parameters for mail and SMS - END
							//~ Send mail to person if person's mail is found
							if(!empty($person_mail))
							{
								$tName="mail_services as a left join mail_event as b on a.event=b.id left join mail_rule_conf as c on a.template_id=c.conf_id";
								$mail_status= "";
								if($ticket_status_id=='1'){
									$mail_status="Ticket Open(Caller)";
									}else if($ticket_status_id=='2'){
									$mail_status="Ticket Closed";
								}
								$w = array("ticket_status"=>array(STRING,$mail_status));
								$f = array("server","subject","from_address","message","signature","salutation","cc_address","bcc_address","conf_id");
								
								$tNameExe = $this->SELECT($tName,$f,$w," and dept_id=".$assigned_to_dept_id." and c.status=1",$this->DB_H);
								$mail_rule_found = $this->GET_ROWS_COUNT($tNameExe);
								if(!$mail_rule_found){
									$tNameExe = $this->SELECT($tName,$f,$w," and default_dept=true and c.status=1",$this->DB_H);
									$mail_rule_found = $this->GET_ROWS_COUNT($tNameExe);
								}
								$f = $this->FETCH_ARRAY($tNameExe,MYSQLI_ASSOC);
								$mail = $person_mail;
								$msg = $f["message"];
								$subject = $f["subject"];
								$conf_id = $f["conf_id"];
								$mail_mesg=$f["salutation"]."<br><br>".$f["message"]."<br><br><br>".$f["signature"];
								$subject=str_replace($searchString,$replaceString,$subject);
								$mail_msg=str_replace($searchString,$replaceString,$mail_mesg);
								if($mail_rule_found)
								{
									require_once("../classes/emailHandler.class.php");
									$EM_H = new emailHandler($this->client_id);
									$EM_H->createMail($subject,$msg,$f["from_address"],$f["bcc_address"],$f["cc_address"],$f["server"],$mail_msg,$mail,'AUTO REPLY',$ticket_id,$filename,$conf_id,$docket_no,'','1','','','','T',$created_by,$created_by_id);
								}
							}
							
							//~ Send SMS to person if person's number is found
							if(!empty($mobile_no))
							{
								//~ Send whatspp message - START
								$tName_wht = "whatsapp_services as a left join mail_event as b on a.event=b.id left join whatsapp_rule_conf as c on a.template_id=c.conf_id";
								$mail_status= "";
								if($ticket_status_id=='1'){
									$mail_status="Ticket Open(Caller)";
									}else if($ticket_status_id=='2'){
									$mail_status="Ticket Closed";
								}
								$w_wht = array("ticket_status"=>array(STRING,$mail_status));
								$f_wht = array("message","to_address","server");
								$tName_wht_details=$this->SELECT($tName_wht,$f_wht,$w_wht," and dept_id=".$assigned_to_dept_id,$this->DB_H);
								$wht_rule_found = $this->GET_ROWS_COUNT($tName_wht_details);
								
								if(!$wht_rule_found){
									$tName_wht_details=$this->SELECT($tName_wht,$f_wht,$w_wht," and default_dept=true",$this->DB_H);
									$wht_rule_found = $this->GET_ROWS_COUNT($tName_wht_details);
								}
								
								if($wht_rule_found)
								{
									$fetch_wht = $this->FETCH_ARRAY($tName_wht_details,MYSQLI_ASSOC);
									$wht_msg=str_replace($searchString,$replaceString,$fetch_wht["message"]);
									
									require_once("/var/www/html/CZCRM/classes/whatsAppHandler.class.php");
									$W_H = new whatsAppHandler($this->client_id);
									
									$type = 'T';
									$waArr = array(
									"whatsapp_account_id" => $fetch_wht['server'],
									"message" => $wht_msg,
									"mobile_no" => $mobile_no,
									"ticket_id" => $ticket_id,
									"ticket_status" => $mail_status,
									"type" => $type,
									"created_by" => $created_by,
									"created_by_id" => $created_by_id,
									);
									
									$W_H->sendWAMessage($waArr);
								}
								//~ Send whatspp message - END
								
								$tName_sms="sms_services as a left join mail_event as b on a.event=b.id left join sms_rule_conf as c on a.template_id=c.conf_id";
								$mail_status= "";
								if($ticket_status_id=='1'){
									$mail_status="Ticket Open(Caller)";
									}else if($ticket_status_id=='2'){
									$mail_status="Ticket Closed";
								}
								$w_sms=array("ticket_status"=>array(STRING,$mail_status));
								$f_sms=array("message","to_address","server");
								$tName_sms_details=$this->SELECT($tName_sms,$f_sms,$w_sms," and c.status=1 ",$this->DB_H);
								$sms_rule_found = $this->GET_ROWS_COUNT($tName_sms_details);
								$fetch_sms=$this->FETCH_ARRAY($tName_sms_details,MYSQLI_ASSOC);
								if($sms_rule_found)
								{
									$msg=str_replace($searchString,$replaceString,$fetch_sms["message"]);
									$type = 'T';
									$smsArray = array(
									"sms_gateway_id" => $fetch_sms["server"],
									"message" => $msg,
									"mobile_no" => $mobile_no,
									"ticket_id" => $ticket_id,
									"ticket_status" => $mail_status,
									"auto_reply" => 1,
									"type" => $type,
									"created_by" => $created_by,
									"created_by_id" => $created_by_id,
									);
									$S_H = new smsHandler($this->client_id);
									$S_H->sendSMS($smsArray);
								}
							}
							
							//~ Send mail to user if user's mail is found
							if(!empty($user_mail)){
								$tName="mail_services as a left join mail_event as b on a.event=b.id left join mail_rule_conf as c on a.template_id=c.conf_id";
								$w = array("ticket_status"=>array(STRING,'Ticket Assigned'));
								$f = array("server","subject","from_address","message","signature","salutation","cc_address","bcc_address","conf_id");
								$tNameExe = $this->SELECT($tName,$f,$w," and dept_id=".$assigned_to_dept_id." and c.status=1",$this->DB_H);
								$mail_rule_found = $this->GET_ROWS_COUNT($tNameExe);
								if(!$mail_rule_found){
									$tNameExe = $this->SELECT($tName,$f,$w," and default_dept=true and c.status=1",$this->DB_H);
									$mail_rule_found = $this->GET_ROWS_COUNT($tNameExe);
								}
								$f = $this->FETCH_ARRAY($tNameExe,MYSQLI_ASSOC);
								$mail = $user_mail;
								$msg=$f["message"];
								$subject = $f["subject"];
								$conf_id = $f["conf_id"];
								$mail_mesg=$f["salutation"]."<br><br>".$f["message"]."<br><br><br>".$f["signature"];
								$subject=str_replace($searchString,$replaceString,$subject);
								$mail_msg=str_replace($searchString,$replaceString,$mail_mesg);
								if($mail_rule_found)
								{
									require_once("../classes/emailHandler.class.php");
									$EM_H = new emailHandler($this->client_id);
									$EM_H->createMail($subject,$msg,$f["from_address"],$f["bcc_address"],$f["cc_address"],$f["server"],$mail_msg,$mail,'AUTO REPLY',$ticket_id,$filename,$conf_id,$docket_no,'','1','','','','T',$created_by,$created_by_id);
								}
							}
							
							//Send mail to department
							if(!empty($dept_mail)){
								$tName="mail_services as a left join mail_event as b on a.event=b.id left join mail_rule_conf as c on a.template_id=c.conf_id";
								$w = array("ticket_status"=>array(STRING,'Department Ticket Assigned'));
								$f = array("server","subject","from_address","message","signature","salutation","cc_address","bcc_address","conf_id");
								$tNameExe = $this->SELECT($tName,$f,$w," and dept_id=".$assigned_to_dept_id." and c.status=1",$this->DB_H);
								$mail_rule_found = $this->GET_ROWS_COUNT($tNameExe);
								if(!$mail_rule_found){
									$tNameExe = $this->SELECT($tName,$f,$w," and default_dept=true and c.status=1",$this->DB_H);
									$mail_rule_found = $this->GET_ROWS_COUNT($tNameExe);
								}
								$f = $this->FETCH_ARRAY($tNameExe,MYSQLI_ASSOC);
								$mail = $dept_mail;
								$msg=$f["message"];
								$subject = $f["subject"];
								$conf_id = $f["conf_id"];
								$mail_mesg=$f["salutation"]."<br><br>".$f["message"]."<br><br><br>".$f["signature"];
								$subject=str_replace($searchString,$replaceString,$subject);
								$mail_msg=str_replace($searchString,$replaceString,$mail_mesg);
								if($mail_rule_found)
								{
									require_once("../classes/emailHandler.class.php");
									$EM_H = new emailHandler($this->client_id);
									$EM_H->createMail($subject,$msg,$f["from_address"],$f["bcc_address"],$f["cc_address"],$f["server"],$mail_msg,$mail,'AUTO REPLY',$ticket_id,$filename,$conf_id,$docket_no,'','1','','','','T',$created_by,$created_by_id);
								}
							}
							//~ Send SMS to person if user's number is found
							if(!empty($user_phone))
							{
								//~ Send whatspp message - START
								$tName_wht = "whatsapp_services as a left join mail_event as b on a.event=b.id left join whatsapp_rule_conf as c on a.template_id=c.conf_id";
								$mail_status = "Ticket Assigned";
								$w_wht = array("ticket_status"=>array(STRING,$mail_status));
								$f_wht = array("message","to_address","server");
								$tName_wht_details=$this->SELECT($tName_wht,$f_wht,$w_wht," and dept_id=".$assigned_to_dept_id,$this->DB_H);
								$wht_rule_found = $this->GET_ROWS_COUNT($tName_wht_details);
								$fetch_wht = $this->FETCH_ARRAY($tName_wht_details,MYSQLI_ASSOC);
								
								if(!$wht_rule_found){
									$tName_wht_details=$this->SELECT($tName_wht,$f_wht,$w_wht," and default_dept=true",$this->DB_H);
									$wht_rule_found = $this->GET_ROWS_COUNT($tName_wht_details);
								}
								
								if($wht_rule_found)
								{
									$wht_msg=str_replace($searchString,$replaceString,$fetch_wht["message"]);
									
									require_once("/var/www/html/CZCRM/classes/whatsAppHandler.class.php");
									$W_H = new whatsAppHandler($this->client_id);
									$type = 'T';
									$waArr = array(
									"whatsapp_account_id" => $fetch_wht['server'],
									"message" => $wht_msg,
									"mobile_no" => $user_phone,
									"ticket_id" => $ticket_id,
									"ticket_status" => $mail_status,
									"type" => $type,
									"created_by" => $created_by,
									"created_by_id" => $created_by_id,
									);
									
									$W_H->sendWAMessage($waArr);
								}
								//~ Send whatspp message - END
								
								$tName_sms="sms_services as a left join mail_event as b on a.event=b.id left join sms_rule_conf as c on a.template_id=c.conf_id";
								$mail_status = "Ticket Assigned";
								$w_sms = array("ticket_status"=>array(STRING,$mail_status));
								$f_sms = array("message","to_address","server");
								$tName_sms_details=$this->SELECT($tName_sms,$f_sms,$w_sms," and c.status=1 ",$this->DB_H);
								$FLP->prepare_log("1","======tName_sms_details===========", $this-> getLastQuery());
								$sms_rule_found = $this->GET_ROWS_COUNT($tName_sms_details);
								$fetch_sms=$this->FETCH_ARRAY($tName_sms_details,MYSQLI_ASSOC);
								if($sms_rule_found)
								{
									$msg=str_replace($searchString,$replaceString,$fetch_sms["message"]);
									$type = 'T';
									$smsArray = array(
									"sms_gateway_id" => $fetch_sms["server"],
									"message" => $msg,
									"mobile_no" => $user_phone,
									"ticket_id" => $ticket_id,
									"ticket_status" => $mail_status,
									"auto_reply" => 1,
									"type" => $type,
									"created_by" => $created_by,
									"created_by_id" => $created_by_id,
									);
									
									$S_H = new smsHandler($this->client_id);
									$S_H->sendSMS($smsArray);
								}
							}
							
							//~ Send SMS to department if department's number is found
							if(!empty($dept_phone))
							{
								//~ Send whatspp message - START
								$tName_wht = "whatsapp_services as a left join mail_event as b on a.event=b.id left join whatsapp_rule_conf as c on a.template_id=c.conf_id";
								$mail_status = "Department Ticket Assigned";
								$w_wht = array("ticket_status"=>array(STRING,$mail_status));
								$f_wht = array("message","to_address","server");
								$tName_wht_details=$this->SELECT($tName_wht,$f_wht,$w_wht," and dept_id=".$assigned_to_dept_id,$this->DB_H);
								$wht_rule_found = $this->GET_ROWS_COUNT($tName_wht_details);
								$fetch_wht = $this->FETCH_ARRAY($tName_wht_details,MYSQLI_ASSOC);
								
								if(!$wht_rule_found){
									$tName_wht_details=$this->SELECT($tName_wht,$f_wht,$w_wht," and default_dept=true",$this->DB_H);
									$wht_rule_found = $this->GET_ROWS_COUNT($tName_wht_details);
								}
								
								if($wht_rule_found)
								{
									$wht_msg=str_replace($searchString,$replaceString,$fetch_wht["message"]);
									
									require_once("/var/www/html/CZCRM/classes/whatsAppHandler.class.php");
									$W_H = new whatsAppHandler($this->client_id);
									$type = 'T';
									$waArr = array(
									"whatsapp_account_id" => $fetch_wht['server'],
									"message" => $wht_msg,
									"mobile_no" => $dept_phone,
									"ticket_id" => $ticket_id,
									"ticket_status" => $mail_status,
									"type" => $type,
									"created_by" => $created_by,
									"created_by_id" => $created_by_id,
									);
									
									$W_H->sendWAMessage($waArr);
								}
								//~ Send whatspp message - END
								
								$tName_sms="sms_services as a left join mail_event as b on a.event=b.id left join sms_rule_conf as c on a.template_id=c.conf_id";
								$mail_status = "Department Ticket Assigned";
								$w_sms = array("ticket_status"=>array(STRING,$mail_status));
								$f_sms = array("message","to_address","server");
								$tName_sms_details=$this->SELECT($tName_sms,$f_sms,$w_sms," and c.status=1 ",$this->DB_H);
								$FLP->prepare_log("1","======tName_sms_details===========", $this-> getLastQuery());
								$sms_rule_found = $this->GET_ROWS_COUNT($tName_sms_details);
								$fetch_sms=$this->FETCH_ARRAY($tName_sms_details,MYSQLI_ASSOC);
								if($sms_rule_found)
								{
									$msg=str_replace($searchString,$replaceString,$fetch_sms["message"]);
									$type = 'T';
									$smsArray = array(
									"sms_gateway_id" => $fetch_sms["server"],
									"message" => $msg,
									"mobile_no" => $dept_phone,
									"ticket_id" => $ticket_id,
									"ticket_status" => $mail_status,
									"auto_reply" => 1,
									"type" => $type,
									"created_by" => $created_by,
									"created_by_id" => $created_by_id,
									);
									
									$S_H = new smsHandler($this->client_id);
									$S_H->sendSMS($smsArray);
								}
							}
							//~ Notification on ticket creation - START
							//~ Pass client_id later on in constructor when notification starts working for multiple clients
							$notificationObj = new notification('',$this->client_id);
							if(!empty($row_fetch_details['user_name']))
							{
								$notification_message = "New Ticket \n\rDocket Number ".$docket_no." created by ".$created_by." and assigned to ".$row_fetch_details['user_name'].".";
							}
							else
							{
								$notification_message = "New Ticket \n\rDocket Number ".$docket_no." created by ".$created_by;
							}
							$notification_event = "CREATE";
							$flag = "";
							$notification_type = "New Ticket";
							
							$notificationObj->saveCreateTicketNotification($notification_event,$notification_message,$created_by,$ticket_id,$flag,$notification_type);
							//~ Notification on ticket creation - END
							// return $this->result='{"Success":"Ticket created successfully","docket_no":"'.$docket_no.'","person_id":"'.$person_id.'"}';
							
							$ticket_data = array();
							$ticket_data['docket_no'] = $docket_no;
							$ticket_data['person_id'] = $person_id;
							return $this->result='{"status":"success","message":"Ticket created successfully","data":'.json_encode($ticket_data).', "statusCode":200}';
							
							}else{
							//~ else show error message
							// return $this->result='{"Error":"Unable to create Ticket"}';
							return $this->result='{"status":"error","message":"Unable to create ticket","statusCode":500}';
						}
					}
					else{
						// return $this->result='{"Error":"Unable to create Ticket"}';
						return $this->result='{"status":"error","message":"Unable to create ticket","statusCode":422}';
					}
				}
			}
		}
		private function checkPerson(){
			$FLP = new logs_creation($this->client_id);
			$FLP->prepare_log("1","======DB UTILITY==============","checkPerson");
			$client_id = $this->client_id;
			$mobile_no=isset($this->dataArray->mobile_no)?$this->dataArray->mobile_no:"";
			// Get Entry if already exists.
			$select_person = "SELECT person_id, person_name from person_info where mobile_no=".$mobile_no;
			$tName_person = $this->EXECUTE_QUERY($select_person,$this->DB_H);
			$fetch_person = $this->FETCH_ARRAY($tName_person,MYSQLI_ASSOC);
			$person_id = isset($fetch_person['person_id'])?$fetch_person['person_id']:'';
			$person_name = isset($fetch_person['person_name'])?$fetch_person['person_name']:'';
			
			return $this->result='{"person_id":"'.$person_id.'","person_name":"'.$person_name.'"}';
		}
		
		private function addPerson(){
			$FLP = new logs_creation($this->client_id);
			$FLP->prepare_log("1","======DB UTILITY==============","addPersoninfo");
			
			$FLP->prepare_log("1","======Data received in add person==============",$this->dataArray);
			$client_id = $this->client_id;
			$first_name=isset($this->dataArray->first_name)?$this->dataArray->first_name:"";
			$last_name=isset($this->dataArray->last_name)?$this->dataArray->last_name:"";
			$person_name=$first_name." ".$last_name;
			$fathers_name=isset($this->dataArray->fathers_name)?$this->dataArray->fathers_name:"";
			$phone=isset($this->dataArray->phone1)?$this->dataArray->phone1:"";
			$mobile_no=isset($this->dataArray->mobile_no)?$this->dataArray->mobile_no:"";
			$person_mail=isset($this->dataArray->person_mail)?$this->dataArray->person_mail:"";
			$dob=isset($this->dataArray->dob)?$this->dataArray->dob:"";
			$country_id=isset($this->dataArray->country_id)?$this->dataArray->country_id:"";
			$state_id=isset($this->dataArray->state_id)?$this->dataArray->state_id:"";
			$city_id=isset($this->dataArray->city_id)?$this->dataArray->city_id:"";
			$country_name=isset($this->dataArray->country_name)?$this->dataArray->country_name:"";
			$state_name=isset($this->dataArray->state_name)?$this->dataArray->state_name:"";
			$city_name=isset($this->dataArray->city_name)?$this->dataArray->city_name:"";
			$additional_info=isset($this->dataArray->additional_info)?$this->dataArray->additional_info:"";
			
			if(!empty($additional_info)){
				if(IsBase64($additional_info)){
					$additional_info =base64_decode($additional_info);
				}else{
					if(IsBase64Url($additional_info)){
						$additional_info =base64url_decode($additional_info);
					}
				}
			}
			$decodedAdditionalInfo = $additional_info;
			$modified_by=isset($this->dataArray->modified_by)?$this->dataArray->modified_by:"";
			$cuid=isset($this->dataArray->cuid)?$this->dataArray->cuid:0;
			$tpf=isset($this->dataArray->tpf)?$this->dataArray->tpf:0;
			$omniFlag=isset($this->dataArray->omniFlag)?$this->dataArray->omniFlag:'';
			
			$mandatory_filds_arry = $this->get_mandatory_flds('person',$client_id,$this->dataArray->reqType);
			$madatry_flag=true;
			foreach($mandatory_filds_arry as $key =>$val){
				if(empty($this->dataArray->$val)){
					$madatry_flag=false;
					break;
				}
			}
			if((!empty($tpf) && ($tpf == 1) && (empty($mobile_no))) || (($omniFlag != 1) && (!$tpf) && (!$madatry_flag)) || (($omniFlag == 1) && empty($cuid))){
			$FLP->prepare_log("1","======mandatory===flags=====add person======",$tpf."/---/".$mobile_no."/---/".$madatry_flag."/---/".$this->dataArray->omniFlag);
				$FLP->prepare_log("1","======error==============","Required parameters missing");
				return $this->result='{"status":"error","Required parameters missing","statusCode":422}';
			}else{
				//~ $moduleName = "person_form1";
				/////////code by nitin////////////
				$moduleName=$this->dataArray->module_name_ajax;
				$moduleName1 = $this->dataArray->module_name_ajax.'_'.$client_id;
				//////////end of code///////////////
				$error_hashArray        =       api_ValidationFunction($moduleName1);
				$error_flag                     =       0;
				$error_flag             =       ShowErrorDiv($error_hashArray);
				$errors = explode("#TVT#",$error_flag);
				if($errors[0]!=0){
					// return $this->result='{"Error":"'.$errors[1].'"}';
					$FLP->prepare_log("1","======error==============",$errors[1]);
					return $this->result='{"status":"error","message":"'.$errors[1].'","statusCode":422}';
				}
				else{
					$customized_table_name = "person_info_cust";
					$country_id = $state_id = $city_id = 0;
					if(!empty($country_name)){
						$query_country = "SELECT nicename,id from country where nicename='".$country_name."'";
						$tName_country = $this->EXECUTE_QUERY($query_country,$this->DB_H);
						$fetch_country = $this->FETCH_ARRAY($tName_country,MYSQLI_ASSOC);
						$country_id = isset($fetch_country['id'])?$fetch_country['id']:'0';
					}
					if(!empty($state_name)){
						$query_state = "SELECT state_name,id from state_tab where state_name='".$state_name."'";
						$tName_state = $this->EXECUTE_QUERY($query_state,$this->DB_H);
						$fetch_state = $this->FETCH_ARRAY($tName_state,MYSQLI_ASSOC);
						$state_id = isset($fetch_state['id'])?$fetch_state['id']:'0';
					}
					if(!empty($city_name)){
						$query_city = "SELECT city_name,id from city_tab where city_name='".$city_name."'";
						$tName_city = $this->EXECUTE_QUERY($query_city,$this->DB_H);
						$fetch_city = $this->FETCH_ARRAY($tName_city,MYSQLI_ASSOC);
						$city_id = isset($fetch_city['id'])?$fetch_city['id']:'0';
					}
					//~ $query_person="insert into person_info(first_name,last_name,person_name,fathers_name,phone1,mobile_no,person_mail,dob,country_name,state_name,city_name,additional_info,cuid,country_id,state_id,city_id)values('".$first_name."','".$last_name."','".$person_name."','".$fathers_name."','".$phone."','".$mobile_no."','".$person_mail."','".$dob."','".$country_name."','".$state_name."','".$city_name."','".$decodedAdditionalInfo."','".$cuid."','".$country_id."','".$state_id."','".$city_id."')";
					$mail_column = $mail_val = "";
					if($person_mail!=''){
						$mail_column = ',person_mail';
						$mail_val = ",'".$person_mail."'";
					}
					// $query_person="insert into person_info(first_name,last_name,person_name,fathers_name,phone1,mobile_no,person_mail,dob,country_name,state_name,city_name,additional_info,cuid,country_id,state_id,city_id,modified_by,modified_on_unix)values('".$first_name."','".$last_name."','".$person_name."','".$fathers_name."','".$phone."','".$mobile_no."','".$person_mail."','".$dob."','".$country_name."','".$state_name."','".$city_name."','".$decodedAdditionalInfo."','".$cuid."','".$country_id."','".$state_id."','".$city_id."','".$modified_by."',UNIX_TIMESTAMP(now()))";
					$query_person="insert into person_info(first_name,last_name,person_name,fathers_name,phone1,mobile_no,dob,country_name,state_name,city_name,additional_info,cuid,country_id,state_id,city_id,modified_by,modified_on_unix".$mail_column.")values('".$first_name."','".$last_name."','".$person_name."','".$fathers_name."','".$phone."','".$mobile_no."','".$dob."','".$country_name."','".$state_name."','".$city_name."','".$decodedAdditionalInfo."','".$cuid."','".$country_id."','".$state_id."','".$city_id."','".$modified_by."',UNIX_TIMESTAMP(now())".$mail_val.")";
					
					$this->EXECUTE_QUERY($query_person,$this->DB_H);
					$FLP->prepare_log("1","======insert query==============",$this->getLastquery());
					
					$person_id=$this->getLastInsertedID($this->DB_H);
					
					if($person_id && !isset($this->dataArray->no_cust_fields)){
						//~ Customized fields Save
						$tNameCustomized = "customized_form as a left join customized_form_fields as b on a.id=b.form_id";
						$f = array (
						"cust_table",
						"field_name",
						"field_type",
						"link_id"
						);
						
						$where = array (
						"module_name" => array (STRING, 'Person Module'),
						"delete_flag" => array(STRING,'0'),
						);
						
						$tNameCustomizedSelect = $this->SELECT($tNameCustomized, $f, $where, _BLANK_, $this->DB_H);
						$customized_fields_array = array();
						while($tNameFetch = $this->FETCH_ARRAY ($tNameCustomizedSelect, MYSQLI_ASSOC))
						{
							$customized_table_name = $tNameFetch['cust_table'];
							if(isset($tNameFetch['field_name']) && !empty($tNameFetch['field_name']) && isset($tNameFetch['field_type']) && !empty($tNameFetch['field_type'])){
								$customized_fields_array[$tNameFetch['field_name']] = $tNameFetch['field_type'] ;
							}
							
							$link_id = $tNameFetch['link_id'];
						}
						//$value_to_input = '';
						if(isset($customized_fields_array) && is_array($customized_fields_array)){
							foreach ($customized_fields_array as $key => $val) {
								$received_value = isset($this->dataArray->$key)?$this->dataArray->$key:"";
								if(!empty($key) && isset($received_value)){
									
									if($val == 'freetext' || $val == 'htmleditor'){
										$encoded_data = $decoded_data = '';
										// $rpce_arry=array("\\r\\n","\n","\\n","\\r","\r");
										$rpce_arry=array("\\r\\n","\\n");
                                        
										// $encoded_data = str_replace(' ','+',$received_value);
										$encoded_data = $received_value;
										if(!empty($encoded_data)){
											if(IsBase64($encoded_data)){
												$decoded_data =base64_decode($encoded_data);
												$decoded_data=str_replace($rpce_arry,"\r\n",$decoded_data);
											}else{
												if(IsBase64Url($encoded_data)){
													$decoded_data =base64url_decode($encoded_data);
													$decoded_data=str_replace($rpce_arry,"\r\n",$decoded_data);
												}else{
													$encoded_data=str_replace($rpce_arry,"\r\n",$encoded_data);
													$decoded_data=$encoded_data;
												}
											}
										}else{
											$encoded_data=str_replace($rpce_arry,"\r\n",$encoded_data);
											$decoded_data=$encoded_data;
										}
										//$decoded_data = base64url_decode($encoded_data);
										$customized_fields_v[$key] =  Array(STRING,$decoded_data);
										}else{
										$value_to_input = '';
										if(is_array($received_value))
										{
											foreach ($received_value as $key1 => $value_multiple) {
												$value_to_input .= $value_multiple."$$##$$";
											}
										}
										else
										{
											$value_to_input = $received_value;
										}
										$customized_fields_v[$key] =  Array(STRING,$value_to_input);
									}
								}
							}
						}
					}
					
					if($person_id){
						if(!empty($mobile_no)){
							insertDocketSource('mobile',$mobile_no,$person_id,$client_id);
						}
						if(!empty($person_mail)){
							insertDocketSource('email',$person_mail,$person_id,$client_id);
						}
						
						$customized_fields_v['person_id'] = Array(STRING,$person_id);
						$tNameCustomizedInsert = $this->INSERT($customized_table_name,$customized_fields_v,$this->DB_H);
						$FLP->prepare_log("1","======customized insert query==============",$this->getLastquery());
						
						if($tNameCustomizedInsert){
							// return $this->result='{"Success":"Person added successfully","person_id":"'.$person_id.'","person_name":"'.$person_name.'"}';
							$personArr = array("person_id"=>"$person_id","person_name"=>"$person_name","person_mail"=>"$person_mail","person_mobile"=>"$mobile_no");
							return $this->result='{"status":"success","message":"Person added successfully","data":'.json_encode($personArr).',"statusCode":200}';
						}else{
							//delete person
							$delete_person= "delete from person_info where person_id=".$person_id;
							$delete_person = $this->EXECUTE_QUERY($delete_person,$this->DB_H);
							$FLP->prepare_log("1","======Delete person==============",$this->getLastquery());
							return $this->result='{"status":"error","message":"Error in adding person","statusCode":500}';
						}
					}
					else{
						// return $this->result='{"Error":"Can\'t add! Person already exists."}';
						return $this->result='{"status":"error","message":"Can\'t add! Person already exists","statusCode":422}';
					}
				}
			}
		}
		
		private function updatePerson(){
			$FLP = new logs_creation($this->client_id);
			$FLP->prepare_log("1","======DB UTILITY==============","updatePerson");
			$FLP->prepare_log("1","======data received==============",$this->dataArray);
			
			$cuid=isset($this->dataArray->cuid)?$this->dataArray->cuid:0;
			$person_id=isset($this->dataArray->person_id)?$this->dataArray->person_id:0;
			$update_person_param = isset($this->dataArray->update_person_param)?$this->dataArray->update_person_param:"";
			$update_person_param_value = isset($this->dataArray->update_person_param_value)?$this->dataArray->update_person_param_value:"";
			
			$this->dataArray->$update_person_param = $update_person_param_value;
			
			$FLP->prepare_log("1","======now array==============",$this->dataArray);
			$omniFlag=isset($this->dataArray->omniFlag)?$this->dataArray->omniFlag:'';

			//if(empty($person_id)){
			if((empty($person_id)) && (!empty($update_person_param)) && (!empty($update_person_param_value))){
				$this->dataArray->search_person_param = $update_person_param;
				$this->dataArray->search_person_param_value = $update_person_param_value;
			}else if((!empty($person_id)) || (!empty($cuid))){
				////gaurav code start
				$FLP->prepare_log("1","======parentapp==============",$parentapp);
				
				$FLP->prepare_log("1","======cuid==============",$cuid);
			
				if((!empty($parentapp) && ($parentapp == 1)) || !empty($cuid)){
					$queryCheck="select person_id from person_info where cuid=".$cuid;
					$result_check=$this->EXECUTE_QUERY($queryCheck,$this->DB_H);
					$row_check=$this->FETCH_ARRAY($result_check,MYSQLI_ASSOC);
					$person_id = $row_check['person_id'];
				}
				
				$this->dataArray->search_person_param = "person_id";
				$this->dataArray->search_person_param_value = $person_id;
			}else{
				return $this->result='{"status":"error","message":"Required parameters missing","statusCode":422}';
			}
			
			$person_info_final = $this->searchPersonNew();
			$FLP->prepare_log("1","======person_info_final==============",$person_info_final);
			
			$person_info_final_json_decode = json_decode($person_info_final, true);
			$FLP->prepare_log("1","======person_info_final_json_decode==============",$person_info_final_json_decode);
			
			$search_person_status = (isset($person_info_final_json_decode['status']) && !empty($person_info_final_json_decode['status']))?$person_info_final_json_decode['status']:'';
			
			$FLP->prepare_log("1","======person_info_final_json_decode==============","search_person_status update".$search_person_status);


			if(!empty($search_person_status) && ($search_person_status == 'success')){
				
				$person_array = isset($person_info_final_json_decode['data'][0])?$person_info_final_json_decode['data'][0]:array();
				$FLP->prepare_log("1","======person_array==============",$person_array);
				
				$person_id=isset($this->dataArray->person_id)?$this->dataArray->person_id:(isset($person_array['person_id'])?$person_array['person_id']:'');
				$FLP->prepare_log("1","======person_id==============",$person_id);
				
				$client_id = $this->client_id;
				$first_name=isset($this->dataArray->first_name)?$this->dataArray->first_name:(isset($person_array['first_name'])?$person_array['first_name']:'');
				
				$last_name=isset($this->dataArray->last_name)?$this->dataArray->last_name:(isset($person_array['last_name'])?$person_array['last_name']:'');
				
				//~ $person_name=$first_name." ".$middle_name." ".$last_name;
				
				if(!empty($first_name) && !empty($last_name)){
					$person_name=$first_name." ".$last_name;
				}else{
					$person_name=isset($this->dataArray->person_name)?$this->dataArray->person_name:(isset($person_array['person_name'])?$person_array['person_name']:'');
				}
				
				$fathers_name=isset($this->dataArray->fathers_name)?$this->dataArray->fathers_name:(isset($person_array['fathers_name'])?$person_array['fathers_name']:'');
				
				$phone = isset($this->dataArray->phone)?$this->dataArray->phone:(isset($person_array['phone1'])?$person_array['phone1']:'');
				
				$mobile_no = isset($this->dataArray->mobile_no)?$this->dataArray->mobile_no:(isset($person_array['mobile_no'])?$person_array['mobile_no']:'');
				

				$person_mail = isset($this->dataArray->person_mail)?$this->dataArray->person_mail:(isset($person_array['person_mail'])?$person_array['person_mail']:'');
				
				$FLP->prepare_log("1","======person_mail==============",$person_mail);
				
				$dob = isset($this->dataArray->dob)?$this->dataArray->dob:(isset($person_array['dob'])?$person_array['dob']:'');
				
				$country_id = isset($this->dataArray->country_id)?$this->dataArray->country_id:(isset($person_array['country_id'])?$person_array['country_id']:0);
				
				$state_id = isset($this->dataArray->state_id)?$this->dataArray->state_id:(isset($person_array['state_id'])?$person_array['state_id']:0);
				
				$city_id = isset($this->dataArray->city_id)?$this->dataArray->city_id:(isset($person_array['city_id'])?$person_array['city_id']:0);
				
				$country_name = isset($this->dataArray->country_name)?$this->dataArray->country_name:(isset($person_array['country_name'])?$person_array['country_name']:'');
				
				$state_name = isset($this->dataArray->state_name)?$this->dataArray->state_name:(isset($person_array['state_name'])?$person_array['state_name']:'');
				
				$city_name = isset($this->dataArray->city_name)?$this->dataArray->city_name:(isset($person_array['city_name'])?$person_array['city_name']:'');
				$CLIENT_FOLDER = isset($this->dataArray->CLIENT_FOLDER)?$this->dataArray->CLIENT_FOLDER:'';
				
				$additional_info=isset($this->dataArray->additional_info)?$this->dataArray->additional_info:(isset($person_array['additional_info'])?$person_array['additional_info']:'');
				$encodedAdditionalInfo = str_replace(' ','+',$additional_info);
				$decodedAdditionalInfo = base64url_decode($encodedAdditionalInfo);
				$cuid=isset($this->dataArray->cuid)?$this->dataArray->cuid:0;
				$tpf=isset($this->dataArray->tpf)?$this->dataArray->tpf:0;
				$modified_by=isset($this->dataArray->modified_by)?$this->dataArray->modified_by:"";
				$mandatory_filds_arry=$this->get_mandatory_flds('person',$client_id,$this->dataArray->reqType);
				$FLP->prepare_log("1","======mandatory fld arry==============",$mandatory_filds_arry);
				$madatry_flag=true;
				foreach($mandatory_filds_arry as $key =>$val){
					if(empty($this->dataArray->$val)){
						$madatry_flag=false;
						break;
					}
				}
				$FLP->prepare_log("1","======mandatory===flags===========",$tpf."/---/".$mobile_no."/---/".$madatry_flag."/---/".$this->dataArray->omniFlag);
               	if((!empty($tpf) && ($tpf == 1) && (empty($mobile_no))) || (($omniFlag != 1) && (!$tpf) && (!$madatry_flag)) || (($omniFlag == 1) && empty($cuid))){
					return $this->result='{"status":"error","message":"Required parameters missing","statusCode":422}';
				}else{
					//~ Code for server side validation
					//~ $moduleName = "person_form1";
					//~ $moduleName = "person_form1";
					/////////code by nitin////////////
					$moduleName=$this->dataArray->module_name_ajax;
					$moduleName1 = $this->dataArray->module_name_ajax.'_'.$client_id;
					//////////end of code///////////////
					$error_hashArray        =       api_ValidationFunction($moduleName1);
					$error_flag                     =       0;
					$error_flag             =       ShowErrorDiv($error_hashArray);
					$errors = explode("#TVT#",$error_flag);
					if($errors[0]!=0){
						return $this->result='{"status":"error","message":"'.$errors[1].'","statusCode":422}';
					}
					else{
						$customized_table_name = "person_info_cust";
						if($omniFlag == 1){
							$queryCheck="select person_id from person_info where cuid=".$cuid;
							$result_check=$this->EXECUTE_QUERY($queryCheck,$this->DB_H);
							$row_check=$this->FETCH_ARRAY($result_check,MYSQLI_ASSOC);
							$person_id=$row_check["person_id"];
						}
						if($person_id){
								$country_id = $state_id = $city_id = 0;
								if(!empty($country_name)){
									$query_country = "SELECT nicename,id from country where nicename='".$country_name."'";
									$tName_country = $this->EXECUTE_QUERY($query_country,$this->DB_H);
									$fetch_country = $this->FETCH_ARRAY($tName_country,MYSQLI_ASSOC);
									$country_id = isset($fetch_country['id'])?$fetch_country['id']:'0';
								}
								if(!empty($state_name)){
									$query_state = "SELECT state_name,id from state_tab where state_name='".$state_name."'";
									$tName_state = $this->EXECUTE_QUERY($query_state,$this->DB_H);
									$fetch_state = $this->FETCH_ARRAY($tName_state,MYSQLI_ASSOC);
									$state_id = isset($fetch_state['id'])?$fetch_state['id']:'0';
								}
								if(!empty($city_name)){
									$query_city = "SELECT city_name,id from city_tab where city_name='".$city_name."'";
									$tName_city = $this->EXECUTE_QUERY($query_city,$this->DB_H);
									$fetch_city = $this->FETCH_ARRAY($tName_city,MYSQLI_ASSOC);
									$city_id = isset($fetch_city['id'])?$fetch_city['id']:'0';
								}
							$person_col =$dob_col = "";
							if($person_mail!=''){
								$person_col = ",person_mail='".$person_mail."'";
							}
							$customzied_header_file = "";
							if(isset($CLIENT_FOLDER) && !empty($CLIENT_FOLDER)){
								$customzied_header_file = "/var/www/html/CZCRM/".$CLIENT_FOLDER."/customized_header.json";
							}
							$customizedArrayHeader = array();
							if($customzied_header_file!=''){
								if(file_exists($customzied_header_file)){
									$header_customized1 = file_get_contents($customzied_header_file);
									$customizedArray1 = json_decode($header_customized1,true);
									$customizedArrayHeader = $customizedArray1['HEADER'];
									
								}
							}
							if(count($customizedArrayHeader)>0){
								if(in_array('Date of Birth',$customizedArrayHeader)){
									$dob_col = ",dob='".$dob."'";
								}
							}
							if(isset($this->dataArray->from_create_ticket)){
								// $queryMain="update person_info set first_name='".$first_name."',last_name='".$last_name."',person_name='".$person_name."',phone1='".$phone."',mobile_no='".$mobile_no."',person_mail='".$person_mail."',cuid='".$cuid."',country_id='".$country_id."',country_name='".$country_name."',state_id='".$state_id."',state_name='".$state_name."',city_id='".$city_id."',city_name='".$city_name."',modified_by = '".$modified_by."',modified_on_unix = UNIX_TIMESTAMP(now()) where person_id='".$person_id."'";
								$queryMain="update person_info set first_name='".$first_name."',last_name='".$last_name."',person_name='".$person_name."',phone1='".$phone."',mobile_no='".$mobile_no."'".$person_col.",cuid='".$cuid."',country_id='".$country_id."',country_name='".$country_name."',state_id='".$state_id."',state_name='".$state_name."',city_id='".$city_id."',city_name='".$city_name."'".$dob_col.",modified_by = '".$modified_by."',modified_on_unix = UNIX_TIMESTAMP(now()) where person_id='".$person_id."'";
							}else{
								// $queryMain="update person_info set first_name='".$first_name."',last_name='".$last_name."',person_name='".$person_name."',fathers_name='".$fathers_name."',phone1='".$phone."',mobile_no='".$mobile_no."',person_mail='".$person_mail."',dob='".$dob."',country_id='".$country_id."',country_name='".$country_name."',state_id='".$state_id."',state_name='".$state_name."',city_id='".$city_id."',city_name='".$city_name."',additional_info='".$decodedAdditionalInfo."',cuid='".$cuid."',modified_by = '".$modified_by."',modified_on_unix = UNIX_TIMESTAMP(now()) where person_id='".$person_id."'";
								$queryMain="update person_info set first_name='".$first_name."',last_name='".$last_name."',person_name='".$person_name."',fathers_name='".$fathers_name."',phone1='".$phone."',mobile_no='".$mobile_no."'".$person_col.",dob='".$dob."',country_id='".$country_id."',country_name='".$country_name."',state_id='".$state_id."',state_name='".$state_name."',city_id='".$city_id."',city_name='".$city_name."',additional_info='".$decodedAdditionalInfo."',cuid='".$cuid."',modified_by = '".$modified_by."',modified_on_unix = UNIX_TIMESTAMP(now()) where person_id='".$person_id."'";
							}
							$FLP->prepare_log("1",'queryMain===',$queryMain);
							$result=$this->EXECUTE_QUERY($queryMain,$this->DB_H);
							if(!empty($mobile_no)){
								insertDocketSource('mobile',$mobile_no,$person_id,$client_id);
							}
							if(!empty($person_mail)){
								insertDocketSource('email',$person_mail,$person_id,$client_id);
							}
							
							if($result){
								//~ Customized fields Save
								$tNameCustomized = "customized_form as a left join customized_form_fields as b on a.id=b.form_id";
								
								$f = array (
								"cust_table",
								"field_name",
								"field_type",
								"link_id"
								);
								
								$where = array (
								"module_name" => array (STRING, 'Person Module'),
								"delete_flag" => array(STRING,'0'),
								);
								
								$tNameCustomizedSelect = $this->SELECT($tNameCustomized, $f, $where, _BLANK_, $this->DB_H);
								$customized_fields_array = array();
								while($tNameFetch = $this->FETCH_ARRAY ($tNameCustomizedSelect, MYSQLI_ASSOC))
								{
									$customized_table_name = $tNameFetch['cust_table'];
									if(isset($tNameFetch['field_name']) && !empty($tNameFetch['field_name']) && isset($tNameFetch['field_type']) && !empty($tNameFetch['field_type'])){
										$customized_fields_array[$tNameFetch['field_name']] = $tNameFetch['field_type'] ;
									}
									
									$link_id = $tNameFetch['link_id'];
								}
								$FLP->prepare_log("1","======customized_fields_array==============",$customized_fields_array);
								//$value_to_input = '';
								if(isset($customized_fields_array) && is_array($customized_fields_array)){
									foreach ($customized_fields_array as $key => $val) {
										$received_value = isset($this->dataArray->$key)?$this->dataArray->$key:"";
										if(!empty($key) && isset($this->dataArray->$key)){
											
											if($val == 'freetext' || $val == 'htmleditor'){
												$encoded_data = $decoded_data = '';
												
												//$encoded_data = str_replace(' ','+',$received_value);
												$encoded_data = $received_value;
												// $rpce_arry=array("\\r\\n","\n","\\n","\\r","\r");
												$rpce_arry=array("\\r\\n","\\n");
												if(!empty($encoded_data)){
													if(IsBase64($encoded_data)){
														$decoded_data =base64_decode($encoded_data);
														$decoded_data=str_replace($rpce_arry,"\r\n",$decoded_data);
													}else{
														if(IsBase64Url($encoded_data)){
															$decoded_data =base64url_decode($encoded_data);
															$decoded_data=str_replace($rpce_arry,"\r\n",$decoded_data);
														}else{
															$encoded_data=str_replace($rpce_arry,"\r\n",$encoded_data);
															$decoded_data=$encoded_data;
														}
													}
												}else{
													$encoded_data=str_replace($rpce_arry,"\r\n",$encoded_data);
													$decoded_data=$encoded_data;
												}
												$customized_fields_v[$key] =  Array(STRING,$decoded_data);
											}else{
												$value_to_input = '';
												if(is_array($received_value))
												{
													foreach ($received_value as $key1 => $value_multiple) {
														$value_to_input .= $value_multiple."$$##$$";
													}
												}
												else
												{
													$value_to_input = $received_value;
												}
												$customized_fields_v[$key] =  Array(STRING,$value_to_input);
											}
										}
									}
								}
								
								$w = Array ("person_id" => Array (STRING, $person_id));
								$tNameCustomizedPerson = $this->UPDATE ($customized_table_name, $customized_fields_v, $w, $this->DB_H);
							}
							
							if($result){
								$personArr = array("person_id"=>"$person_id","person_mail"=>"$person_mail","person_mobile"=>"$mobile_no");
								return $this->result='{"status":"success","message":"Person updated successfully","data":'.json_encode($personArr).',"statusCode":200}';
							}
							else{
								return $this->result='{"status":"error","message":"Error in updating person","statusCode":500}';
							}
						}
					}
				}
			}else{
				//Call Add person API
				$this->dataArray->phone1 = isset($this->dataArray->phone)?$this->dataArray->phone:"";
				$person_info_final=$this->addPerson();
				$person_info_final_json_decode = json_decode($person_info_final, true);
				$FLP->prepare_log("1","========addPerson result===========",$person_info_final_json_decode);
				$add_person_status = (isset($person_info_final_json_decode['status']) && !empty($person_info_final_json_decode['status']))?$person_info_final_json_decode['status']:'error';
				
				if($add_person_status == 'success'){
					$person_id = (isset($person_info_final_json_decode['data']['person_id']) && !empty($person_info_final_json_decode['data']['person_id']))?trim($person_info_final_json_decode['data']['person_id']):'';
					$person_mail = (isset($person_info_final_json_decode['data']['person_mail']) && !empty($person_info_final_json_decode['data']['person_mail']))?trim($person_info_final_json_decode['data']['person_mail']):'';
					$mobile_no = (isset($person_info_final_json_decode['data']['person_mobile']) && !empty($person_info_final_json_decode['data']['person_mobile']))?trim($person_info_final_json_decode['data']['person_mobile']):'';
					$personArr = array("person_id"=>"$person_id","person_mail"=>"$person_mail","person_mobile"=>"$mobile_no");
					return $this->result='{"status":"success","message":"Person updated successfully","data":'.json_encode($personArr).',"statusCode":422}';
				}else{
					$FLP->prepare_log("1","======problem in adding non existing person==============",$person_info_final_json_decode['message']);
					return $this->result='{"status":"error","message":"Error in updating person","statusCode":500}';
				}
			}
		}
		
		private function searchPersonNew(){
			$FLP = new logs_creation($this->client_id);
			$FLP->prepare_log("1","======DB UTILITY==============","searchPersonNew");
			
			$search_person_param = isset($this->dataArray->search_person_param)?$this->dataArray->search_person_param:"";
			$search_person_param_value = isset($this->dataArray->search_person_param_value)?$this->dataArray->search_person_param_value:"";
			if($search_person_param == 'person_id'){
				$search_person_param = 'a.'.$search_person_param;
			}
			
			if(empty($search_person_param_value)){
				$FLP->prepare_log("1","Error","This field is required");
				return $this->result='{"status":"error","message":"This field is required","statusCode":422}';
			}
			else{
				$FLP->prepare_log("1","Enter","If");
				
				if((!empty($search_person_param)) && (!empty($search_person_param_value))){
					$FLP->prepare_log("1","Working","Fine");
					$search_person_param_value = $this->MYSQLI_REAL_ESCAPE($this->DB_H,$search_person_param_value);
					
					$querySearchCount = "SELECT count(DISTINCT a.person_id) as count FROM person_info as a left join person_info_cust as d on a.person_id=d.person_id WHERE $search_person_param = '".$search_person_param_value."' ORDER BY a.person_id";
					$resultSearchCount = $this->EXECUTE_QUERY($querySearchCount,$this->DB_H);
					
					$FLP->prepare_log("1","Working",$this->getLastQuery());
					
					$searchCountFetch = $this->FETCH_ARRAY($resultSearchCount,MYSQLI_ASSOC);
					$searchCount = $searchCountFetch['count'];
					
					if($searchCount){
						$querySearch = "SELECT * FROM person_info as a left join person_info_cust as d on a.person_id=d.person_id WHERE $search_person_param = '".$search_person_param_value."' ORDER BY a.person_id";
						$resultSearch = $this->EXECUTE_QUERY($querySearch,$this->DB_H);
						$FLP->prepare_log("1","Working",$this->getLastQuery());
						
						$i=0;
						while($rowOpen=$this->FETCH_ARRAY($resultSearch,MYSQLI_ASSOC)){
							$rowOpen['additional_info'] = (isset($rowOpen['additional_info']) && !empty($rowOpen['additional_info']))?base64url_encode($rowOpen['additional_info']):'';
							$personArr[$i] = $rowOpen;
							$i++;
						}
						$FLP->prepare_log("1","personArr",$personArr);
						
					}
					if($resultSearch && $searchCount){
						return $this->result='{"status":"success","data":'.json_encode($personArr).',"statusCode":200}';
					}
					else{
						return $this->result='{"status":"error","message":"No record found","statusCode":422}';
					}
				}else{
					$FLP->prepare_log("1","Enter","Else");
					return $this->result='{"status":"error","message":"Required parameters missing","statusCode":422}';
				}
			}
		}
		
		private function searchPerson(){
			$FLP = new logs_creation($this->client_id);
			$FLP->prepare_log("1","======DB UTILITY==============","searchPerson");
			$search_person=isset($this->dataArray->search_person)?$this->dataArray->search_person:"";
			
			if(empty($search_person)){
				return $this->result='{"Error":"Please enter mobile number or email address of person","statusCode":422}';
			}
			else{
				if(isset($search_person)){
					$search_person = $this->MYSQLI_REAL_ESCAPE($this->DB_H,$search_person);
					$querySearch = "SELECT a.person_id,person_name,fathers_name,state_name,city_name,phone1,mobile_no,IF((dob='0000-00-00'),'',dob) as dob,person_mail,additional_info,cuid FROM person_info as a WHERE person_mail like '".$search_person."%' or mobile_no like '".$search_person."%' or phone1 like '".$search_person."%' or phone2 like '".$search_person."%' or  person_name like '".$search_person."%' ORDER BY a.person_id";
					$resultSearch=$this->EXECUTE_QUERY($querySearch,$this->DB_H);
					$searchCount = $this->GET_ROWS_COUNT($resultSearch);
					if($searchCount){
						$i=0;
						while($rowOpen=$this->FETCH_ARRAY($resultSearch,MYSQLI_ASSOC)){
							$personArr[$i]=array("person_id"=>$rowOpen["person_id"],"person_name"=>$rowOpen["person_name"],"fathers_name"=>$rowOpen["fathers_name"],"state_name"=>$rowOpen["state_name"],"city_name"=>$rowOpen["city_name"],"phone1"=>$rowOpen["phone1"],"mobile_no"=>$rowOpen["mobile_no"],"dob"=>$rowOpen["dob"],"person_mail"=>$rowOpen["person_mail"],"additional_info"=>$rowOpen["additional_info"],"cuid"=>$rowOpen["cuid"]);
							$i++;
						}
					}
					if($resultSearch && $searchCount){
						return $this->result='{"Success":'.json_encode($personArr).',"statusCode":200}';
					}
					else{
						return $this->result='{"Error":"No Record Found","statusCode":422}';
					}
				}
			}
		}
		//~ Function to reset password after forgot password - Sabohi@28/8/2018
		private function resetPassword(){
			$FLP = new logs_creation($this->client_id);
			$FLP->prepare_log("1","======DB UTILITY==============","resetPassword");
			$password=$this->dataArray->password;
			$re_password=$this->dataArray->re_password;
			include_once '/var/www/html/CZCRM/classes/crypt.class.php';
			$objCrypt = new crypt(0);
			
			$data = $objCrypt->decrypt((string)$this->dataArray->data,0);
			$dataArr=explode("#TVT#", $data);
			$email=$dataArr[0];
			$mobile=$dataArr[1];
			$registration_id = $dataArr[2];
			$old_password = $dataArr[3];
			$FLP->prepare_log("1","old password",$old_password);
			
			//Compare new password with old password
			$pc ="select password('".$password."') as passencryp ";
			$pc=$this->EXECUTE_QUERY($pc,$this->DB_H);
			$pC = $this->FETCH_ARRAY ($pc, MYSQLI_ASSOC);
			$new_password = $pC['passencryp'];
			if(!empty($data) && !empty($password) && !empty($re_password)){
				if($new_password != $old_password){
					$tName = GDB_NAME.".userAuth";
					$v = array (
					"user_password"         =>array(MYSQL_FUNCTION,'PASSWORD("'.$password.'")'),
					);
					$w      =       array(
					"mobile"=>Array(STRING,$mobile),
					"email"=>Array(STRING,$email)
					);
					$tName_user     =       $this->UPDATE($tName,$v,$w, $this->DB_H);
					$tName_client= DB_PREFIX.$registration_id.".users";
					
					$w1     =       array(
					"phone_mobile"=>Array(STRING,$mobile),
					"email1"=>Array(STRING,$email)
					);
					$tName_user1    =       $this->UPDATE($tName_client,$v,$w1, $this->DB_H);
					if($tName_user && $tName_user1){
						return $this->result='{"Success":"Password changed successfully."}';
						}else{
						return $this->result='{"Error":"Error in changing password."}';
					}
					}else{
					return '{"Error":"New password should not match old Password."}';
				}
			}
			else{
				return '{"Error":"Invalid request attempt."}';
			}
		}
		
		private function resetPasswordLink(){
			$FLP = new logs_creation($this->client_id);
			$FLP->prepare_log("1","======DB UTILITY==============","resetPasswordLink");
			$email=$this->dataArray->email;
			if(!empty($email)){
				$query_check_email="select registration_id,username,email,mobile,user_password from ".GDB_NAME.".userAuth where email like '%".$email."%'";
				$resultCheck=$this->EXECUTE_QUERY($query_check_email,$this->DB_H);
				if(mysqli_num_rows($resultCheck)){
					$rowCheck=$this->FETCH_ARRAY($resultCheck,MYSQLI_ASSOC);
					$FLP->prepare_log("1","rowCheck",$rowCheck);
					//Enter new OTPwith validty and send in forgot_password-reset password link
					$otp = rand(100000,999999);
					$validity = time()+(24*60*60);
					$tNameClient = "clientRegistrationBasic";
					$OTPUpdate= array("OTP"=>array(STRING,$otp),"OTPValid"=>array(STRING,$validity));
					$wAuth = array("registration_id"=>array(STRING,$rowCheck["registration_id"]));
					$tNameClientUpdate= $this->UPDATE($tNameClient,$OTPUpdate,$wAuth,$this->DB_H);
					$str=$rowCheck["email"]."#TVT#".$rowCheck["mobile"]."#TVT#".$rowCheck["registration_id"]."#TVT#".$rowCheck["user_password"]."#TVT#".$otp;
					include_once '/var/www/html/CZCRM/classes/crypt.class.php';
					$objCrypt = new crypt(0);
					$string = $objCrypt->encrypt($str,0);
					if(!empty($rowCheck["email"]))
					{
						$resetPasswordLink = "http://".DEFAULT_CRM_NAME."/"._BASEDIR_."/forget_password.php?".$string;
						$user_name = $rowCheck["username"];
						$searchString=array(
						'%LINK%',
						'%USER_NAME%',
						'%SENDER_NAME%'
						);
						
						$replaceString=array(
						$resetPasswordLink,
						$user_name,
						"C-Zentrix Team"
						);
						
						$tName="mail_services as a left join mail_event as b on a.event=b.id left join mail_rule_conf as c on a.template_id=c.conf_id";
						
						$w=array("ticket_status"=>array(STRING,"Reset Password"));
						$f=array("server","subject","from_address","message","signature","salutation","cc_address","bcc_address","conf_id");
						$tName=$this->SELECT($tName,$f,$w," and c.status=1",$this->DB_H);
						$mail_rule_found = $this->GET_ROWS_COUNT($tName);
						$f=$this->FETCH_ARRAY($tName,MYSQLI_ASSOC);
						$to_addr = $rowCheck["email"];
						$msg = $f["message"];
						$subject = $f["subject"];
						$conf_id = $f["conf_id"];
						$mail_mesg=$f["salutation"]."<br><br>".$f["message"]."<br><br><br>".$f["signature"];
						$ticket_id = "";
						$subject = str_replace($searchString,$replaceString,$subject);
						$mail_msg = str_replace($searchString,$replaceString,$mail_mesg);
						if($mail_rule_found)
						{
							require_once("/var/www/html/CZCRM/classes/emailHandler.class.php");
							$EM_H = new emailHandler($this->client_id);
							$EM_H->createMail($subject,$msg,$f["from_address"],$f["bcc_address"],$f["cc_address"],$f["server"],$mail_msg,$to_addr,'AUTO REPLY',$ticket_id,"",$conf_id,'','',0);
						}
					}
					
					return $this->result='{"Success":"Reset password link sent to your email ID!!"}';
				}
				else{
					return $this->result='{"Error":"Invalid Email!!"}';
				}
			}
			else{
				return $this->result='{"Error":"Invalid Request!!"}';
			}
		}
		
		//~ created by sabohi @04/12/2018
		//~ For updating various entities of ticket (ticketing widget)
		//~ Required parameters :- ticket_id, entity_name, entity_value, modified_by, modified_by_id, modified_by_dept_name, previous_ticket_status
		private function updateTickets(){
			$FLP = new logs_creation($this->client_id);
			$FLP->prepare_log("1","=====inside updateTickets====","-------------");
			$ticketJson=json_encode($this->dataArray);
			//~ $FLP->prepare_log("1","=====ticket json====",print_r($ticketJson,true));
			$ticket_id = isset($this->dataArray->ticket_id)?$this->dataArray->ticket_id:"";
			$ticket_type = isset($this->dataArray->ticket_type)?$this->dataArray->ticket_type:"";
			$modified_by = isset($this->dataArray->modified_by)?$this->dataArray->modified_by:"";
			$modified_by_id = isset($this->dataArray->modified_by)?$this->dataArray->modified_by_id:"";
			$modified_by_dept_id = isset($this->dataArray->modified_by_dept_id)?$this->dataArray->modified_by_dept_id:"";
			$entity_name = isset($this->dataArray->entity_name)?$this->dataArray->entity_name:"";
			$entity_value = isset($this->dataArray->entity_value)?$this->dataArray->entity_value:"";
			$disposition_id = isset($this->dataArray->disposition_id)?$this->dataArray->disposition_id:"";
			$sub_disposition_id = isset($this->dataArray->sub_disposition_id)?$this->dataArray->sub_disposition_id:"";
			$disposition_name = isset($this->dataArray->disposition_name)?$this->dataArray->disposition_name:"";
			$sub_disposition_name = isset($this->dataArray->sub_disposition_name)?$this->dataArray->sub_disposition_name:"";
			$priority_id = isset($this->dataArray->priority_id)?$this->dataArray->priority_id:"";
			$ticket_status_id = isset($this->dataArray->ticket_status_id)?$this->dataArray->ticket_status_id:"";
			$ticket_status = isset($this->dataArray->ticket_status)?$this->dataArray->ticket_status:"";
			$action_taken = isset($this->dataArray->action_taken)?$this->dataArray->action_taken:"";
			$agent_remarks = isset($this->dataArray->agent_remarks)?$this->dataArray->agent_remarks:"";
			$update_type = isset($this->dataArray->update_type)?$this->dataArray->update_type:"";
			$previous_ticket_status = isset($this->dataArray->previous_ticket_status)?$this->dataArray->previous_ticket_status:"";
			$remarks = isset($this->dataArray->remarks)?$this->dataArray->remarks:"";
			if(empty($ticket_id)){
				return $this->result='{"Error":"Required parameters missing."}';
			}
			else{
				$TH = new ticketHandler($this->client_id);
				$returned_data=$TH->updateTickets($ticketJson);
				
				if(isset($returned_data['Error'])){
					return $this->result='{"Error":"'.$returned_data['Error'].'"}';
					}else{
					
					$result = $this->result='{"Success":"'.$returned_data['Success'].'"}';
					$FLP->prepare_log("1","=====success result is====",$result);
					return $result;
					//~ Has to be decided - with vikas kapoor
					/*
						//~ Getting escalation ip
						$configFileContent = file_get_contents("/var/www/html/CZCRM/configs/config.txt");
						$configFileArr = json_decode($configFileContent,true);
						$escalation_ip = isset($configFileArr["ESCALATION_IP"])?$configFileArr["ESCALATION_IP"]:"";
						$survey_crm_ip = isset($configFileArr["SURVEY_CRM_IP"])?$configFileArr["SURVEY_CRM_IP"]:"";
						
						if(!empty($escalation_ip)){
						require_once("../classes/escalationHandler.class.php");
						$EH = new escalationHandler($this->client_id);
						$delete_applicable_rule_query = "delete from escalation_rule_applicable where ticket_id=".$ticket_id." and type='T'";
						$delete_applicable_rule = $this->EXECUTE_QUERY($delete_applicable_rule_query,$this->DB_H);
						
						$executed_rules = $EH->fn_executed_rules($ticket_id,'T');
						$URL = "http://".$escalation_ip."/checkApplicable?ticketId=$ticket_id&executedRules=".$executed_rules."&type=T&db=".$_SESSION["CLIENT_ID"]."";
						$result =       do_remote_without_json($URL,"");
						}
						
						//~ Fetch all data
						$query_assign="select a.created_by,a.created_by_id,a.ticket_status_id,a.ticket_status,a.assigned_to_user_id,a.problem_reported,a.docket_no,a.source,a.created_on,time(a.created_on) as creation_time,a.modified_on,a.priority_name,time_format(timediff(NOW(),a.created_on),'%Hh %im') as time_elapsed,a.last_escalated_on,a.ticket_assigned_time,a.ticket_type,a.disposition_name,a.sub_disposition_name,a.company_name,a.assigned_to_dept_name from ticket_details_report as a where ticket_id=$ticket_id";
						$result_assign = $this->EXECUTE_QUERY($query_assign,$this->DB_H);
						$row_assign = $this->FETCH_ARRAY($result_assign,MYSQLI_ASSOC);
						$query_issue = $row_assign;
						
						//~ Mail work - start
						$created_by = !empty($query_issue["created_by"])?$query_issue["created_by"]:'';
						$created_by_id = !empty($query_issue["created_by_id"])?$query_issue["created_by_id"]:0;
						$assign_to_user_id = !empty($query_issue["assigned_to_user_id"])?$query_issue["assigned_to_user_id"]:0;
						$ticket_status_id       = !empty($query_issue["ticket_status_id"])?$query_issue["ticket_status_id"]:0;
						$ticket_status_mail     = !empty($query_issue["ticket_status"])?$query_issue["ticket_status"]:'';
						$ticket_status_mail_db = !empty($query_issue["ticket_status"])?$query_issue["ticket_status"]:'';
						$issues_reported = !empty($query_issue["problem_reported"])?$query_issue["problem_reported"]:'';
						$docket_no = !empty($query_issue["docket_no"])?$query_issue["docket_no"]:'';
						$short_docket_number = substr($query_issue["docket_no"],8);
						
						$query_mail_from = "select mail_from,mail_cc,subject,mail_references,message_id from mail where ticket_id='$ticket_id' and flow like 'in' order by mail_id desc";
						$query_mail_from = $this->EXECUTE_QUERY ($query_mail_from, $this->DB_H);
						$query_mail_from = $this->FETCH_ARRAY($query_mail_from,MYSQLI_ASSOC);
						
						$mail   =       $query_mail_from["mail_from"];
						$mail_cc        =       $query_mail_from["mail_cc"];
						$database_mail_subject  =       $query_mail_from["subject"];
						$references = $query_mail_from["message_id"]." ".$query_mail_from["mail_references"];
						
						$query = "select group_concat(DISTINCT b.person_name) as person,group_concat(person_mail) as email1,mobile_no from ticket_details as a left join person_info as b on a.person_id=b.person_id where ticket_id in (".$ticket_id.")";
						
						$query = $this->EXECUTE_QUERY ($query, $this->DB_H);
						
						$query=$this->FETCH_ARRAY($query,MYSQLI_ASSOC);
						
						$mail                           =               !empty($mail)?$mail:$query["email1"];
						$person_mobile_no       =               !empty($query["mobile_no"])?$query["mobile_no"]:0;
						$person_name            =               (!empty($query["person"])?$query["person"]:'Customer');
						
						if($ticket_status_id == '2'){
						$ticket_status_mail='Ticket Closed';
						}
						if($ticket_status_id == '3'){
						$ticket_status_mail='Ticket Inprogress';
						}
						if($ticket_status_id == '6'){
						$ticket_status_mail='Ticket Reopen';
						}
						if($ticket_status_id == '4'){
						$ticket_status_mail='Ticket Resolved';
						}
						
						//Defining search string for mail and sms_gateway_id
						$searchString=array(
						"%DOCKET_NUMBER%",
						"%PERSON_NAME%",
						"%SUBJECT%",
						"%TICKET_STATUS%",
						"%PROBLEM_REPORTED%",
						"%AGENT_REMARKS%",
						"%ACTION_TAKEN%",
						"%PRIORITY%",
						"%ASSIGNED_USER%",
						"%ASSIGNED_DEPT%",
						"%CREATION_TIME%",
						"%CREATION_DATE_TIME%",
						"%LAST_UPDATION_TIME%",
						"%COMPANY_NAME%",
						"%DOCKET_SHORT%",
						"%TICKET_TYPE%",
						"%DISPOSITION%",
						"%SUB_DISPOSITION%",
						"%TIME_ELAPSED%",
						"%LAST_ESCALATION_TIME%",
						"%ASSIGNMENT_TIME%",
						"unique_id=",
						);
						
						$tName="mail_services as a left join mail_event as b on a.event=b.id left join mail_rule_conf as c on a.template_id=c.conf_id";
						$w=array("ticket_status"=>array(STRING,$ticket_status_mail));
						
						$f=array("server","subject","from_address","message","signature","salutation","cc_address","bcc_address","conf_id");
						
						$tName=$this->SELECT($tName,$f,$w," and c.status=1",$this->DB_H);
						
						$mail_rule_found = $this->GET_ROWS_COUNT($tName);
						if($mail_rule_found){
						$f=$this->FETCH_ARRAY($tName,MYSQLI_ASSOC);
						
						$subject_mail   =       $f["subject"];
						$msg            =       $f["message"];
						$mail_mesg      =       $f["salutation"]."<br><br>".$f["message"]."<br><br><br>".$f["signature"];
						$conf_id        =       $f["conf_id"];
						
						$subject   = $f["subject"];
						$pos = strpos($mail_mesg, "feedback_form.php");
						if($pos!==false){
						$query_person = "select encrypted_docket_no from ticket_details_customized where ticket_id=".$ticket_id;
						$query_person_exec = $this->EXECUTE_QUERY($query_person,$this->DB_H);
						$query_person_fetch = $this->FETCH_ARRAY($query_person_exec,MYSQLI_ASSOC);
						$survey_encrypted_docket = $query_person_fetch['encrypted_docket_no'];
						$docket_noEncoded = "";
						//If ticket is reopened //
						//Replace the new docket no encoded with the previous one so that same survey form is opened//
						if(!empty($survey_encrypted_docket)){
						$docket_noEncoded = $survey_encrypted_docket;
						}
						else
						{
						$docket_noEncoded = generateRandomString1(15);
						}
						$mail_mesgSurveyArr = explode("form_id=",$mail_mesg);
						$mail_mesgSurveyFormArr = explode("&amp;",$mail_mesgSurveyArr[1]);
						$form_id = $mail_mesgSurveyFormArr[0];
						
						if($form_id){
						if(!empty($survey_crm_ip)){
						$survey_time = strtotime(date("Y-m-d H:i:s"));
						$json_string = '{"unique_id":"'.$docket_noEncoded.'","docket_no":"'.$docket_no.'","cust_name":"'.$person_name.'","c_email":"'.$mail.'","cust_phone":"'.$person_mobile_no.'","disp":"'.$query_issue["disposition_name"].'","subdisp":"'.$query_issue["sub_disposition_name"].'","survey_senttime":"'.$survey_time.'","form_id":"'.$form_id.'","agent_name":"'.$modified_by.'","department":"'.$modified_by_dept_name.'"}';
						$url_to_pass = 'http://'.$survey_crm_ip.'/CZCRM/apps/survey_crm_linkage.php?data='.urlencode($json_string).'&client_id='.$this->client_id;
						do_remote($url_to_pass,'');
						$this->prepare_log("Suvey Url==",$url_to_pass);
						}
						
						$tNameCustomized = "ticket_details_customized";
						
						$fCustomized = array(
						"encrypted_docket_no" => array(STRING,$docket_noEncoded),
						);
						
						$where111=array("ticket_id"=>array(STRING,$ticket_id));
						$tName=$this->UPDATE($tNameCustomized,$fCustomized,$where111,$this->DB_H);
						}
						}
						
						if($assign_to_user_id){
						$queryDeptHeadMail = "select first_name,email1,phone_mobile from users where user_id='$assign_to_user_id'";
						$queryDeptHeadMail = $this->EXECUTE_QUERY ($queryDeptHeadMail, $this->DB_H);
						$queryDeptHeadMail = $this->FETCH_ARRAY($queryDeptHeadMail,MYSQLI_ASSOC);
						$mail_send      =       $queryDeptHeadMail["email1"];
						$user_phone = $queryDeptHeadMail["phone_mobile"];
						}
						
						$replaceString=array(
						$docket_no,
						$person_name,
						$database_mail_subject,
						$ticket_status_mail,
						$issues_reported,
						$agent_remarks,
						$action_taken,
						$query_issue['priority_name'],
						$queryDeptHeadMail['first_name'],
						$query_issue['dept_name'],
						$query_issue['creation_time'],
						$query_issue['created_on'],
						$query_issue['modified_on'],
						$query_issue['company_name'],
						$short_docket_number,
						$query_issue['ticket_type'],
						$query_issue['disposition_name'],
						$query_issue['sub_disposition_name'],
						$query_issue['time_elapsed'],
						$query_issue['last_escalated_on'],
						$query_issue['ticket_assigned_time'],
						"unique_id=".$docket_noEncoded,
						);
						
						$subject = str_replace($searchString,$replaceString,$subject);
						$mail_mesg = str_replace($searchString,$replaceString,$mail_mesg);
						$bcc_address = "";
						$cc_address  = "";
						
						if(!empty($mail)){
						require_once("../classes/emailHandler.class.php");
						$EM_H = new emailHandler($this->client_id);
						$EM_H->createMail($subject,$msg,$f["from_address"],$bcc_address,$mail_cc,$f["server"],$mail_msg,$mail,'AUTO REPLY',$ticket_id,$filename,$conf_id,$docket_no,'','1','','','','T',$created_by,$created_by_id);
						}
						}
						//~ Mail work - end
						
						//~ SMS work - start
						if(!empty($person_mobile_no)){
						$tName_sms = "sms_services as a left join mail_event as b on a.event=b.id left join sms_rule_conf as c on a.template_id=c.conf_id";
						$w_sms = array("ticket_status"=>array(STRING,$ticket_status_mail));
						$f_sms = array("message","to_address","server");
						$tName_sms_details = $this->SELECT($tName_sms,$f_sms,$w_sms," and c.status=1",$this->DB_H);
						$sms_rule_found = $this->GET_ROWS_COUNT($tName_sms_details);
						
						if($sms_rule_found){
						$fetch_sms = $this->FETCH_ARRAY($tName_sms_details,MYSQLI_ASSOC);
						//~ $where = " and sms_gateway_id='".$fetch_sms["server"]."'";
						//~ $sms_gateway_info = newSMSgateway($where);
						
						if($sms_gateway_info){
						$replaceString=array(
						$docket_no,
						$person_name,
						$database_mail_subject,
						$ticket_status_mail,
						$issues_reported,
						$agent_remarks,
						$action_taken,
						$query_issue['priority_name'],
						$queryDeptHeadMail['first_name'],
						$query_issue['dept_name'],
						$query_issue['creation_time'],
						$query_issue['created_on'],
						$query_issue['modified_on'],
						$query_issue['company_name'],
						$short_docket_number,
						$query_issue['ticket_type'],
						$query_issue['disposition_name'],
						$query_issue['sub_disposition_name'],
						$query_issue['time_elapsed'],
						$query_issue['last_escalated_on'],
						$query_issue['ticket_assigned_time'],
						);
						
						$msg = str_replace($searchString,$replaceString,$fetch_sms["message"]);
						$type = 'T';
						$smsArray = array(
						"sms_gateway_id" => $fetch_sms["server"],
						"message" => $msg,
						"mobile_no" => $person_mobile_no,
						"ticket_id" => $ticket_id,
						"ticket_status" => $ticket_status_mail,
						"auto_reply" => 1,
						"type" => $type,
						"created_by" => $created_by,
						"created_by_id" => $created_by_id,
						);
						$S_H = new smsHandler($this->client_id);
						$S_H->sendSMS($smsArray);
						}
						}
						}
						//~ SMS work - end
						
						//~ Entry in ticket history - start
						if($query_issue["source"] == "Self Created")
						{
						$ticket_status11 = "Engineer/Helpdesk Reply";
						}
						else
						{
						$ticket_status11 =      "Ticket Updated";
						}
						$change_code = _CHANGE_CODE_ARRAY;
						$createTicketHistoryResult = $TH->createTicketHistory($ticket_id,$ticketArr,$change_code["ticket_edit"],$ticket_status11);
						//~ Entry in ticket history - end
						
						//~ Auto assignment work - when???
						if(($entity_name == 'priority_name' || $entity_name == 'ticket_status_id') && (!empty($assign_to_user_id))){
						$this->prepare_log("update","userQueue");
						$returned_data = $TH->updateUserQueue($assign_to_user_id);
						}
						
						//~ Notification work - start
						$notification_type= "Ticket Updated";
						$notification_event= "UPDATE";
						
						$queryTicketDetails = "select docket_no from ticket_details where ticket_id=".$ticket_id;
						$tNameTicketDetails = $this->EXECUTE_QUERY($queryTicketDetails,$this->DB_H);
						$docketNumber = $DB->FETCH_ARRAY($tNameTicketDetails,MYSQLI_ASSOC);
						$notification_message = "Ticket Updated \n\rTicket with Docket Number ".$query_issue['docket_no']." updated by  ".$modified_by.".";
						$flag = "";
						$notificationObj = new notification('',$this->client_id);
						$notificationObj->saveCreateTicketNotification($notification_event,$notification_message,$modified_by,$ticket_id,$flag,$notification_type);
					//~ Notification work - end*/
					
				}
			}
		}
		
		private function updateTicketMobile(){
			$FLP = new logs_creation($this->client_id);
			$FLP->prepare_log("1","======DB UTILITY==============","updateTicketMobile");
			$ticketJson=json_encode($this->dataArray);
			$ticket_id = isset($this->dataArray->ticket_id)?$this->dataArray->ticket_id:"";
			$disposition_id = isset($this->dataArray->disposition_id)?$this->dataArray->disposition_id:"";
			$sub_disposition_id = isset($this->dataArray->sub_disposition_id)?$this->dataArray->sub_disposition_id:"";
			$ticket_status = isset($this->dataArray->ticket_status)?$this->dataArray->ticket_status:"";
			$previous_ticket_status = isset($this->dataArray->previous_ticket_status)?$this->dataArray->previous_ticket_status:"";
			$agent_remarks = isset($this->dataArray->agent_remarks)?$this->dataArray->agent_remarks:"";
			$modified_by = isset($this->dataArray->modified_by)?$this->dataArray->modified_by:"";
			$modified_by_id = isset($this->dataArray->modified_by_id)?$this->dataArray->modified_by_id:"";
			$docket_no = isset($this->dataArray->docket_no)?$this->dataArray->docket_no:"";
			$key = isset($this->dataArray->key)?$this->dataArray->key:"";
			$CLIENT_ID = isset($this->dataArray->client_id)?$this->dataArray->client_id:"";
			$CLIENT_FOLDER = isset($this->dataArray->CLIENT_FOLDER)?$this->dataArray->CLIENT_FOLDER:"";
			$DEPT_ID = isset($this->dataArray->DEPT_ID)?$this->dataArray->DEPT_ID:0;
			$DEPT_NAME = isset($this->dataArray->DEPT_NAME)?$this->dataArray->DEPT_NAME:"";
			$action_taken = isset($this->dataArray->action_taken)?$this->dataArray->action_taken:"";
			$priority = isset($this->dataArray->priority_id)?$this->dataArray->priority_id:"";
			$ticket_type_id = isset($this->dataArray->ticket_type_id)?$this->dataArray->ticket_type_id:"";
			
			if(empty($ticket_id)){
				return $this->result='{"Error":"Required parameters misssing."}';
			}
			else{
				$TH = new ticketHandler($CLIENT_ID);
				$returned_data=$TH->updateTicketMobile($ticketJson);
				$FLP->prepare_log("1","======message packet for update==============", $returned_data);
				
				if(isset($returned_data['Error'])){
					return $this->result='{"Error":"'.$returned_data['Error'].'"}';
					}else{
					
					//~ Getting escalation ip
					require_once("../classes/escalationHandler.class.php");
					$EH = new escalationHandler($CLIENT_ID);
					$configFileContent = file_get_contents("/var/www/html/CZCRM/configs/config.txt");
					$configFileArr = json_decode($configFileContent,true);
					$escalation_ip = isset($configFileArr["ESCALATION_IP"])?$configFileArr["ESCALATION_IP"]:"";
					
					$delete_applicable_rule_query = "delete from escalation_rule_applicable where ticket_id=".$ticket_id." and type='T'";
					$delete_applicable_rule = $this->EXECUTE_QUERY($delete_applicable_rule_query,$this->DB_H);
					
					$executed_rules = $EH->fn_executed_rules($ticket_id,'T');
					$URL = "http://".$escalation_ip."/checkApplicable?ticketId=$ticket_id&executedRules=".$executed_rules."&type=T&db=".$CLIENT_ID."";
					$result =       do_remote_without_json($URL,"");
					$query_assign="select a.created_by,a.created_by_id,a.assigned_to_user_id,a.assigned_to_dept_id,a.problem_reported,a.docket_no,a.source,a.created_on,time(a.created_on) as creation_time,a.modified_on,a.priority_name,time_format(timediff(NOW(),a.created_on),'%Hh %im') as time_elapsed,a.last_escalated_on,a.ticket_assigned_time,a.ticket_type,a.disposition_name,a.sub_disposition_name,a.assigned_to_dept_name,a.assigned_to_user_name from ticket_details_report as a where ticket_id=$ticket_id";
					
					$result_assign=$this->EXECUTE_QUERY($query_assign,$this->DB_H);
					$row_assign=$this->FETCH_ARRAY($result_assign,MYSQLI_ASSOC);
					$query_issue=$row_assign;
					$assign_to_user_id=!empty($row_assign["assigned_to_user_id"])?$row_assign["assigned_to_user_id"]:0;
					$assigned_to_dept_id=!empty($row_assign["assigned_to_dept_id"])?$row_assign["assigned_to_dept_id"]:0;
					if(!empty($assign_to_user_id)){
						$query_count="select count(*) as assign_count,sum(if(priority_id=3,1,0)) as critical_count from ticket_details where assigned_to_user_id=$assign_to_user_id and ticket_status_id<>2";
						$result_count=$this->EXECUTE_QUERY($query_count,$this->DB_H);
						$row_count=$this->FETCH_ARRAY($result_count,MYSQLI_ASSOC);
						$assign_count=!empty($row_count["assign_count"])?$row_count["assign_count"]:0;
						$critical_count=!empty($row_count["critical_count"])?$row_count["critical_count"]:0;
						$query_update="update UsersQue set ticket_queue_count=$assign_count ,ticket_critical_queue_count=$critical_count where user_id=$assign_to_user_id";
						$result_update=$this->EXECUTE_QUERY($query_update,$this->DB_H);
					}
					
					///-----Vikas's code ends here
					///////////////////mail code for ticket status udate///////////////////////////
					$SURVEYIP = file_get_contents("/var/www/html/CZCRM/configs/config.txt");
					$survey_ip = json_decode($SURVEYIP,true);
					$SURVEY_CRM_IP = $survey_ip['SURVEY_CRM_IP'];
					$query1="select ticket_status from ticket_status where ticket_status_id=".$ticket_status;
					$query1 = $this->EXECUTE_QUERY ($query1, $this->DB_H);
					$query1=$this->FETCH_ARRAY($query1,MYSQLI_ASSOC);
					
					$ticket_status_mail     = $ticket_status_mail_db =      $query1["ticket_status"];
					$issues_reported        =       $query_issue["problem_reported"];
					$docket_no=$query_issue["docket_no"];
					$short_docket_number = substr($query_issue["docket_no"],8);
					
					$query_mail_from="select mail_from,mail_cc,subject,mail_references,message_id from mail where ticket_id='$ticket_id' and flow like 'in' order by mail_id desc";
						$query_mail_from = $this->EXECUTE_QUERY ($query_mail_from, $this->DB_H);
					$query_mail_from=$this->FETCH_ARRAY($query_mail_from,MYSQLI_ASSOC);
					$mail   =       $query_mail_from["mail_from"];
					$mail_cc        =       $query_mail_from["mail_cc"];
					$database_mail_subject  =       $query_mail_from["subject"];
					$references=$query_mail_from["message_id"]." ".$query_mail_from["mail_references"];
					
					$query = "select group_concat(DISTINCT b.person_name) as person,group_concat(person_mail) as email1,mobile_no from ticket_details as a left join person_info as b on a.person_id=b.person_id where ticket_id in (".$ticket_id.")";
					$query = $this->EXECUTE_QUERY ($query, $this->DB_H);
					$query=$this->FETCH_ARRAY($query,MYSQLI_ASSOC);
					$mail                           =               !empty($mail)?$mail:$query["email1"];
					$person_mobile_no       =               !empty($query["mobile_no"])?$query["mobile_no"]:0;
					$person_name            =                       (!empty($query["person"])?$query["person"]:'Customer');
					if($ticket_status=='2'){
						$ticket_status_mail='Ticket Closed';
					}
					if($ticket_status=='3'){
						$ticket_status_mail='Ticket Inprogress';
					}
					if($ticket_status=='6'){
						$ticket_status_mail='Ticket Reopen';
					}
					if($ticket_status=='4'){
						$ticket_status_mail='Ticket Resolved';
					}
					
					//Defining search string for mail and sms_gateway_id
					$searchString=array(
					"%DOCKET_NUMBER%",
					"%PERSON_NAME%",
					"%SUBJECT%",
					"%TICKET_STATUS%",
					"%PROBLEM_REPORTED%",
					"%AGENT_REMARKS%",
					"%ACTION_TAKEN%",
					"%PRIORITY%",
					"%ASSIGNED_USER%",
					"%ASSIGNED_DEPT%",
					"%CREATION_TIME%",
					"%CREATION_DATE_TIME%",
					"%LAST_UPDATION_TIME%",
					//"%COMPANY_NAME%",
					"%DOCKET_SHORT%",
					"%TICKET_TYPE%",
					"%DISPOSITION%",
					"%SUB_DISPOSITION%",
					"%TIME_ELAPSED%",
					"%LAST_ESCALATION_TIME%",
					"%ASSIGNMENT_TIME%",
					"unique_id=",
					);
					
					$tName="mail_services as a left join mail_event as b on a.event=b.id left join mail_rule_conf as c on a.template_id=c.conf_id";
					$w=array("ticket_status"=>array(STRING,$ticket_status_mail));
					
					$f=array("server","subject","from_address","message","signature","salutation","cc_address","bcc_address","conf_id");
					
					$tNameExe = $this->SELECT($tName,$f,$w," and dept_id=".$assigned_to_dept_id." and c.status=1",$this->DB_H);
					$mail_rule_found = $this->GET_ROWS_COUNT($tNameExe);
					if(!$mail_rule_found){
						$tNameExe = $this->SELECT($tName,$f,$w," and default_dept=true and c.status=1",$this->DB_H);
						$mail_rule_found = $this->GET_ROWS_COUNT($tNameExe);
					}
					
					if($mail_rule_found){
						$f = $this->FETCH_ARRAY($tNameExe,MYSQLI_ASSOC);
						
						$subject_mail   =       $f["subject"];
						$msg            =       $f["message"];
						$mail_mesg      =       $f["salutation"]."<br><br>".$f["message"]."<br><br><br>".$f["signature"];
						$conf_id        =       $f["conf_id"];
						$subject   = $f["subject"];
						$pos = strpos($mail_mesg, "feedback_form.php");
						if($pos!==false){
							$query_person = "select encrypted_docket_no from ticket_details_customized where ticket_id=".$ticket_id;
							//~ $query_person_exec = mysqli_query($DB_H,$query_person);
							$query_person_exec = $this->EXECUTE_QUERY($query_person,$this->DB_H);
							$query_person_fetch = $this->FETCH_ARRAY($query_person_exec,MYSQLI_ASSOC);
							$survey_encrypted_docket = $query_person_fetch['encrypted_docket_no'];
							$docket_noEncoded = "";
							//If ticket is reopened //
							//Replace the new docket no encoded with the previous one so that same survey form is opened//
							if(!empty($survey_encrypted_docket)){
								$docket_noEncoded = $survey_encrypted_docket;
							}
							else
							{
								$docket_noEncoded = generateRandomString(15);
							}
							$mail_mesgSurveyArr= explode("form_id=",$mail_mesg);
							$mail_mesgSurveyFormArr= explode("&amp;",$mail_mesgSurveyArr[1]);
							$form_id= $mail_mesgSurveyFormArr[0];
							
							if($form_id){
								$survey_time = strtotime(date("Y-m-d H:i:s"));
								$json_string = '{"unique_id":"'.$docket_noEncoded.'","docket_no":"'.$docket_no.'","cust_name":"'.$person_name.'","c_email":"'.$mail.'","cust_phone":"'.$person_mobile_no.'","disp":"'.$query_issue["disposition_name"].'","subdisp":"'.$query_issue["sub_disposition_name"].'","survey_senttime":"'.$survey_time.'","form_id":"'.$form_id.'","agent_name":"'.$modified_by.'","department":"'.$DEPT_NAME.'"}';
								//$url_to_pass = 'http://'.$SURVEY_CRM_IP.'/CZCRM/apps/survey_crm_linkage.php?data='.urlencode($json_string).'&client_id='.$CLIENT_ID;
								$url_to_pass  = _CALL_API_DNS.'/apps/survey_crm_linkage.php?data='.urlencode($json_string).'&client_id='.$CLIENT_ID;
								do_remote($url_to_pass,'');
								
								//logSurvey("Suvey Url==",$url_to_pass);
								
								$tNameCustomized = "ticket_details_customized";
								
								$fCustomized = array(
								"encrypted_docket_no" => array(STRING,$docket_noEncoded),
								);
								
								$where111=array("ticket_id"=>array(STRING,$ticket_id));
								$tName=$this->UPDATE($tNameCustomized,$fCustomized,$where111,$this->DB_H);
							}
						}
						//print $assign_to_user_id;
						if($assign_to_user_id){
							$queryDeptHeadMail = "select first_name,email1,phone_mobile from users  where user_id='$assign_to_user_id'";
							$queryDeptHeadMail = $this->EXECUTE_QUERY ($queryDeptHeadMail, $this->DB_H);
							$queryDeptHeadMail=$this->FETCH_ARRAY($queryDeptHeadMail,MYSQLI_ASSOC);
							$mail_send      =       $queryDeptHeadMail["email1"];
							$user_phone = $queryDeptHeadMail["phone_mobile"];
						}
						$replaceString=array(
						$docket_no,
						$person_name,
						$database_mail_subject,
						$ticket_status_mail,
						$issues_reported,
						$agent_remarks,
						$action_taken,
						$query_issue['priority_name'],
						$query_issue['assigned_to_user_name'],
						$query_issue['assigned_to_dept_name'],
						$query_issue['creation_time'],
						$query_issue['created_on'],
						$query_issue['modified_on'],
						//$query_issue['company_name'],
						$short_docket_number,
						$query_issue['ticket_type'],
						$query_issue['disposition_name'],
						$query_issue['sub_disposition_name'],
						$query_issue['time_elapsed'],
						$query_issue['last_escalated_on'],
						$query_issue['ticket_assigned_time'],
						"unique_id=".$docket_noEncoded,
						
						);
						
						$subject = str_replace($searchString,$replaceString,$subject);
						//$subject = .' - '.$subject;
						$mail_mesg = str_replace($searchString,$replaceString,$mail_mesg);
						$bcc_address = "";
						$cc_address  = "";
						
						if(!empty($mail)){
							
							require_once("../classes/emailHandler.class.php");
							$EM_H = new emailHandler($CLIENT_ID);
							$EM_H->createMail($subject,$mail_mesg,$f["from_address"],$bcc_address,$mail_cc,$f["server"],$mail_msg,$mail,'AUTO REPLY',$ticket_id,$filename,$conf_id,$docket_no,$references,'1','','');
						}
					}
					
					if(!empty($person_mobile_no)){
						//~ Send whatspp message - START
						
						$tName_wht = "whatsapp_services as a left join mail_event as b on a.event=b.id left join whatsapp_rule_conf as c on a.template_id=c.conf_id";
						$w_wht = array("ticket_status"=>array(STRING,$ticket_status_mail));
						$f_wht = array("message","to_address","server");
						
						$tName_wht_details=$this->SELECT($tName_wht,$f_wht,$w_wht," and dept_id=".$assigned_to_dept_id,$this->DB_H);
						$wht_rule_found = $this->GET_ROWS_COUNT($tName_wht_details);
										
						if(!$wht_rule_found){
							$tName_wht_details=$this->SELECT($tName_wht,$f_wht,$w_wht," and default_dept=true",$this->DB_H);
							$wht_rule_found = $this->GET_ROWS_COUNT($tName_wht_details);
						}
								
						if($wht_rule_found)
						{
							$fetch_wht = $this->FETCH_ARRAY($tName_wht_details,MYSQLI_ASSOC);
							$replaceString=array(
							$docket_no,
							$person_name,
							$database_mail_subject,
							$ticket_status_mail,
							$issues_reported,
							$agent_remarks,
							$action_taken,
							$query_issue['priority_name'],
							$query_issue['assigned_to_user_name'],
							$query_issue['assigned_to_dept_name'],
							$query_issue['creation_time'],
							$query_issue['created_on'],
							$query_issue['modified_on'],
							//      $query_issue['company_name'],
							$short_docket_number,
							$query_issue['ticket_type'],
							$query_issue['disposition_name'],
							$query_issue['sub_disposition_name'],
							$query_issue['time_elapsed'],
							$query_issue['last_escalated_on'],
							$query_issue['ticket_assigned_time'],
							);
							
							$wht_msg=str_replace($searchString,$replaceString,$fetch_wht["message"]);
							
							require_once("/var/www/html/CZCRM/classes/whatsAppHandler.class.php");
							$W_H = new whatsAppHandler($CLIENT_ID);
							$type = 'T';
							$waArr = array(
							"whatsapp_account_id" => $fetch_wht['server'],
							"message" => $wht_msg,
							"mobile_no" => $person_mobile_no,
							"ticket_id" => $ticket_id,
							"ticket_status" => $ticket_status_mail,
							"type" => $type,
							"created_by" => $query_issue['created_by'],
							"created_by_id" => $query_issue['created_by_id'],
							);
							
							$W_H->sendWAMessage($waArr);
						}
						//~ Send whatspp message - END
						
						
						$tName_sms="sms_services as a left join mail_event as b on a.event=b.id left join sms_rule_conf as c on a.template_id=c.conf_id";
						$w_sms=array("ticket_status"=>array(STRING,$ticket_status_mail));
						$f_sms=array("message","to_address","server");
						$tName_sms_details=$this->SELECT($tName_sms,$f_sms,$w_sms," and c.status=1",$this->DB_H);
						$sms_rule_found = $this->GET_ROWS_COUNT($tName_sms_details);
						
						if($sms_rule_found){
							$fetch_sms=$this->FETCH_ARRAY($tName_sms_details,MYSQLI_ASSOC);
							$replaceString=array(
							$docket_no,
							$person_name,
							$database_mail_subject,
							$ticket_status_mail,
							$issues_reported,
							$agent_remarks,
							$action_taken,
							$query_issue['priority_name'],
							$query_issue['assigned_to_user_name'],
							$query_issue['assigned_to_dept_name'],
							$query_issue['creation_time'],
							$query_issue['created_on'],
							$query_issue['modified_on'],
							//$query_issue['company_name'],
							$short_docket_number,
							$query_issue['ticket_type'],
							$query_issue['disposition_name'],
							$query_issue['sub_disposition_name'],
							$query_issue['time_elapsed'],
							$query_issue['last_escalated_on'],
							$query_issue['ticket_assigned_time'],
							);
							$msg=str_replace($searchString,$replaceString,$fetch_sms["message"]);
							$type = 'T';
							$smsArray = array(
							"sms_gateway_id" => $fetch_sms["server"],
							"message" => $msg,
							"mobile_no" => $person_mobile_no,
							"ticket_id" => $ticket_id,
							"ticket_status" => $ticket_status_mail,
							"auto_reply" => 1,
							"type" => $type,
							);
							
							$S_H = new smsHandler($CLIENT_ID);
							$S_H->sendSMS($smsArray);
						}
					}
					
					///////////////////end of mail code///////////////////////////////////////////
					$tName2="ticket_history";
					if($query_issue["source"]=="Self Created"){
						$ticket_status11 = "Engineer/Helpdesk Reply";
					}
					else{
						$ticket_status11 =      "Ticket Updated";
					}
					$code_array = _CHANGE_CODE_ARRAY;
					$f=array(
					"ticket_id"=>array(STRING,$ticket_id),
					"change_code"=>array(STRING,$code_array["ticket_edit"]),
					"change_value"=>array(STRING, $ticket_status11),
					"remark"=>array(STRING,$agent_remarks),
					"change_by"=>array(STRING,$modified_by),
					"change_on"=>array(MYSQL_FUNCTION,'NOW()'),
					"ticket_status_history"=>array(STRING,$ticket_status_mail_db)
					);
					
					$this->INSERT($tName2,$f,$this->DB_H);
					$notificationObj = new notification('',$CLIENT_ID);
					$notification_type= "Ticket Updated";
					$notification_event= "UPDATE";
					
					$queryTicketDetails = "select docket_no from ticket_details where ticket_id=".$ticket_id;
					$tNameTicketDetails = $this->EXECUTE_QUERY($queryTicketDetails,$this->DB_H);
					$docketNumber = $this->FETCH_ARRAY($tNameTicketDetails,MYSQLI_ASSOC);
					$notification_message = "Ticket Updated \n\rTicket with Docket Number ".$docketNumber['docket_no']." updated by  ".$modified_by.".";
					$flag = "";
					
					$notificationObj->saveCreateTicketNotification($notification_event,$notification_message,$modified_by,$ticket_id,$flag,$notification_type);
					$FLP->prepare_log("1","======message packet for final==============", $returned_data['Success']);
					
					return $this->result='{"Success":"'.$returned_data['Success'].'"}';
					//return $this->result = '{"Success":"Followup Date Time Updated Successfully"}';
					
				}
			}
		}
		private function assignTicketMobile(){
			$FLP = new logs_creation($this->client_id);
			$FLP->prepare_log("1","======DB UTILITY==============","assignTicketMobile");
			$ticketJson=json_encode($this->dataArray);
			$ticket_id = isset($this->dataArray->ticket_id)?$this->dataArray->ticket_id:"";
			$assignTicket_remarks = isset($this->dataArray->assignTicket_remarks)?$this->dataArray->assignTicket_remarks:"";
			$user_id = isset($this->dataArray->user_name)?$this->dataArray->user_name:"";
			$assigned_to_dept_id = isset($this->dataArray->dept_id)?$this->dataArray->dept_id:"";
			$modified_by = isset($this->dataArray->modified_by)?$this->dataArray->modified_by:"";
			$modified_by_id = isset($this->dataArray->modified_by_id)?$this->dataArray->modified_by_id:"";
			$key = isset($this->dataArray->key)?$this->dataArray->key:"";
			$client_id = isset($this->dataArray->client_id)?$this->dataArray->client_id:"";
			$CLIENT_FOLDER = isset($this->dataArray->CLIENT_FOLDER)?$this->dataArray->CLIENT_FOLDER:"";
			$DEPT_ID = isset($this->dataArray->DEPT_ID)?$this->dataArray->DEPT_ID:0;
			$DEPT_NAME = isset($this->dataArray->DEPT_NAME)?$this->dataArray->DEPT_NAME:"";
			
			if(empty($ticket_id)){
				return $this->result='{"Error":"Required parameters misssing."}';
			}
			else{
				$TH = new ticketHandler($client_id);
				// $returned_data=$TH->assignTicketMobile($ticketJson);
				///------For auto assign
				$query_pre="select priority_id,docket_no,assigned_to_user_id,source,source_info,ticket_status_id,ticket_status from ticket_details_report where ticket_id=$ticket_id ";
				$result_pre=$this->EXECUTE_QUERY($query_pre,$this->DB_H);
				$resultRow_pre=$this->FETCH_ARRAY($result_pre,MYSQLI_ASSOC);
				
				$assigned_to_user_id_old=$resultRow_pre["assigned_to_user_id"];
				$priority_id_hidden=$resultRow_pre["priority_id"];
				$docket_no_hidden=$resultRow_pre["docket_no"];
				$source_hidden=$resultRow_pre["source"];
				$source_info_hidden=$resultRow_pre["source_info"];
				$ticket_status=$resultRow_pre["ticket_status"];
				$assignTicket_remarksFinal=$assignTicket_remarks;
				//////////----------Auto assign code ends--------
				$query= "Select uuid,user_name from users where user_id=$user_id";
				$query = $this -> EXECUTE_QUERY($query,$this->DB_H);
				$data = $this->FETCH_ARRAY ($query, MYSQLI_ASSOC);
				$uuid = $data["uuid"];
				//~ $source_app = $data["source_app"];
				$agent_name = $assigned_to_user_name = $data["user_name"];
				
				$query= "Select dept_name from departments where dept_id=$assigned_to_dept_id";
				$query = $this -> EXECUTE_QUERY($query,$this->DB_H);
				$data =$this->FETCH_ARRAY ($query, MYSQLI_ASSOC);
				if(empty($assigned_to_user_name))
				{
					$assigned_to_user_name = "Department ".$data["dept_name"];
					}else{
					$assigned_to_user_name .= "(".$data["dept_name"].")";
				}
				$assigned_by =!empty($user_id)?$modified_by:'';
				$f=array(
				"ticket_assigned_remarks"=>array(STRING,$assignTicket_remarksFinal),
				"modified_on"=>array(MYSQL_FUNCTION,'NOW()'),
				"modified_on_unix"              =>      array(STRING , strtotime(date('Y-m-d'))),
				"modified_by"=>array(STRING,$modified_by),
				"modified_by_id"=>array(STRING,$modified_by_id),
				"assigned_to_dept_id"=>array(STRING,$assigned_to_dept_id),
				"assigned_by"=>array(STRING,$assigned_by),
				"assigned_to_user_id"=>array(STRING,$user_id),
				"ticket_assigned_time"=>array(MYSQL_FUNCTION,'NOW()'),
				);
				
				$where=array("ticket_id"=>array(STRING,$ticket_id));
				
				$tName = "ticket_details";
				$tName=$this->UPDATE($tName,$f,$where,$this->DB_H);
				if($tName){
					$f_array = array(
					"ticket_assigned_remarks"       =>      $assignTicket_remarksFinal,
					"modified_on"                           =>  date('Y-m-d H:i:s'),
					"modified_on_unix"                      =>      strtotime(date('Y-m-d')),
					"modified_by"                           =>      $modified_by,
					"modified_by_id"                        =>      $modified_by_id,
					"assigned_to_dept_id"           =>      $assigned_to_dept_id,
					"assigned_to_user_id"           =>      $user_id,
					"assigned_by"                           =>      $assigned_by,
					"ticket_assigned_time"          =>  date('Y-m-d H:i:s'),
					);
					
					$f_json = json_encode($f_array);
					$returned_data=$TH->createReportEntry($ticket_id,$f_json,'assign');
					
					//~ Changed by sabohi - for entry in ticket_details_30 table containing data of last 30 days
					$Reporting_array        =       '{"ticket_id":"'.$ticket_id.'","action":"UPDATE"}';
					maintainMonthlyTable($Reporting_array,$this,$this->DB_H);
					
					//~ appendQueue($ReportingArray);
					$ReportingArray =       '{"ticket_id":"'.$ticket_id.'","action":"ASSIGN"}';
					GENERATELOGS_CZCRM($ReportingArray,"ASSIGN Ticket",1);
					
					//End of code
					//code for timeline history start//
					$datetime       =       time();
					$datajson       =       '{"action":"ticket_assigned","date_time":"'.$datetime.'","action_by":"'.$modified_by.'","action_to":"'.$assigned_to_user_name.'","ticket_id":"'.$ticket_id.'","docket_no":"'.$docket_no_hidden.'","client_folder":"'.$CLIENT_FOLDER.'","type":"TICKET","client_id":"'.$client_id.'"}';
					timeline_history($datajson);
					
					//code for timeline history end//
					$ticket_status_name= $ticket_status;
					//----Added by vikas for unprocessed ticvket handling --////
					$query="delete from unprocessed_tickets where ticket_id=".$ticket_id." and type='T'";
					$this->EXECUTE_QUERY($query,$this->DB_H);
					
					//----code to be changed for citical
					if(!empty($assigned_to_user_id_old)){
						$query="update UsersQue set ticket_queue_count=ticket_queue_count-1 where user_id=$assigned_to_user_id_old";
						$this->EXECUTE_QUERY($query,$this->DB_H);
					}
					///// Code for entry in unprocessed is user not assigned
					
					if(empty($user_id)){
						$source_infodept = getDepatmentInfo($source_hidden,$assigned_to_dept_id,$this,$this->DB_H);
						$query="insert into unprocessed_tickets(ticket_id,priority_id,source,source_value,docket_no,assigned_to_dept_id,inserted_time)values('$ticket_id','$priority_id_hidden','$source_hidden','$source_infodept','$docket_no_hidden','$assigned_to_dept_id','".strtotime(date("Y-m-d H:i:s"))."') ";
						$this->EXECUTE_QUERY($query,$this->DB_H);
						
						}else{
						$user_id_post=$this->MYSQLI_REAL_ESCAPE ($this->DB_H,$user_id);
						$query="update UsersQue set ticket_queue_count=ticket_queue_count+1,ticket_max_assign_count=ticket_max_assign_count+1 where user_id=$user_id_post";
						$this->EXECUTE_QUERY($query,$this->DB_H);
					}
					//--vikas code ends here---
					$tName2="ticket_history";
					$code_array = _CHANGE_CODE_ARRAY;
					$f=array(
					"ticket_id"=>array(STRING,$ticket_id),
					"change_code"=>array(STRING,$code_array["change_assign"]),
					"change_value"=>array(STRING, "New Assignment- Assigned to ".$assigned_to_user_name),
					"remark"=>array(STRING,$assignTicket_remarksFinal),
					"change_by"=>array(STRING,$modified_by),
					"change_on"=>array(MYSQL_FUNCTION,'NOW()'),
					"ticket_status_history"=>array(STRING,$ticket_status_name),
					);
					
					$this->INSERT($tName2,$f,$this->DB_H);
					//~ Getting escalation ip
					require_once("../classes/escalationHandler.class.php");
					$EH = new escalationHandler($CLIENT_ID);
					$configFileContent = file_get_contents("/var/www/html/CZCRM/configs/config.txt");
					$configFileArr = json_decode($configFileContent,true);
					$escalation_ip = isset($configFileArr["ESCALATION_IP"])?$configFileArr["ESCALATION_IP"]:"";
					
					$delete_applicable_rule_query = "delete from escalation_rule_applicable where ticket_id=".$ticket_id." and type='T'";
					$delete_applicable_rule = $this->EXECUTE_QUERY($delete_applicable_rule_query,$this->DB_H);
					
					$executed_rules = $EH->fn_executed_rules($ticket_id,'T');
					$URL = "http://".$escalation_ip."/checkApplicable?ticketId=$ticket_id&executedRules=".$executed_rules."&type=T&db=".$CLIENT_ID."";
					$result =       do_remote_without_json($URL,"");
					
					
					$query_issue="select a.created_by,a.created_by_id,a.source,a.call_session_id,a.source_info,docket_no,a.assigned_to_dept_id,a.priority_id,pi.person_name,ticket_status,priority_name,problem_reported,agent_remarks,action_taken,a.created_on,a.created_on,time(a.created_on) as creation_time,a.modified_on,time_format(timediff(NOW(),a.created_on),'%Hh %im') as time_elapsed,last_escalated_on,ticket_assigned_time,
					ticket_type,disposition_name,sub_disposition_name,dept_name     from ticket_details as a left join users as b on a.assigned_to_user_id=b.user_id left join ticket_status as c on a.ticket_status_id=c.ticket_status_id left join person_info as pi on a.person_id=pi.person_id left join person_info_cust as pic on pi.person_id=pic.person_id left join priority as d on a.priority_id=d.priority_id left join ticket_type as tt on tt.id=a.ticket_type_id left join disposition_tab as dt on dt.id=a.disposition_id left join sub_disposition_tab as sdt on sdt.id=a.sub_disposition_id left join departments as dpts on dpts.dept_id=a.assigned_to_dept_id where ticket_id='$ticket_id'";
					
					$query_issue = $this->EXECUTE_QUERY ($query_issue, $this->DB_H);
					$query_issue = $this->FETCH_ARRAY($query_issue,MYSQLI_ASSOC);
					
					//~ Notification work ticketing - START
					$notificationObj = new notification('',$CLIENT_ID);
					$flag = "";
					$notification_event= "ASSIGN";
					$notification_type = "Newly Assigned";
					$notification_message = "Newly Assigned \n\rTicket with Docket Number ".$query_issue['docket_no']." assigned to ".$assigned_to_user_name." by ".$modified_by;
					$notificationObj->saveCreateTicketNotification($notification_event,$notification_message,$modified_by,$ticket_id,$flag,$notification_type);
					//~ Notification work ticketing - END
					
					$docket_short=substr($query_issue['docket_no'],-5);
					$user_id_post=$this->MYSQLI_REAL_ESCAPE ($this->DB_H,$user_id);
					$queryDeptHeadMail = "select first_name,email1,phone_mobile from users  where user_id='$user_id_post'";
					$queryDeptHeadMail = $this->EXECUTE_QUERY ($queryDeptHeadMail, $this->DB_H);
					$queryDeptHeadMail=$this->FETCH_ARRAY($queryDeptHeadMail,MYSQLI_ASSOC);
					$mail_send      =       $queryDeptHeadMail["email1"];
					$user_phone = $queryDeptHeadMail["phone_mobile"];
					
					//for dept
					$assigned_to_dept_id_post=$this->MYSQLI_REAL_ESCAPE ($this->DB_H,$assigned_to_dept_id);
					$queryDeptHeadMail_dept = "select dept_email,dept_phone from departments  where dept_id='$assigned_to_dept_id_post'";
					$queryDeptHeadMail_dept = $this->EXECUTE_QUERY ($queryDeptHeadMail_dept, $this->DB_H);
					$queryDeptHeadMail_dept=$this->FETCH_ARRAY($queryDeptHeadMail_dept,MYSQLI_ASSOC);
					$mail_send_dept =       $queryDeptHeadMail_dept["dept_email"];
					$dept_phone = $queryDeptHeadMail_dept["dept_phone"];
					
					$database_mail_subject = "";
					//~ Defining search string for mail and sms_gateway_id
					$searchString=array(
					"%DOCKET_NUMBER%",
					"%PERSON_NAME%",
					"%SUBJECT%",
					"%TICKET_STATUS%",
					"%PROBLEM_REPORTED%",
					"%AGENT_REMARKS%",
					"%ACTION_TAKEN%",
					"%PRIORITY%",
					"%ASSIGNED_USER%",
					"%ASSIGNED_DEPT%",
					"%CREATION_TIME%",
					"%CREATION_DATE_TIME%",
					"%LAST_UPDATION_TIME%",
					"%DOCKET_SHORT%",
					"%TICKET_TYPE%",
					"%DISPOSITION%",
					"%SUB_DISPOSITION%",
					"%TIME_ELAPSED%",
					"%LAST_ESCALATION_TIME%",
					"%ASSIGNMENT_TIME%"
					);
					
					$tName="mail_services as a left join mail_event as b on a.event=b.id left join mail_rule_conf as c on a.template_id=c.conf_id";
					$w=array("ticket_status"=>array(STRING,'Ticket Assigned'));
					$f=array("server","subject","from_address","message","signature","salutation","cc_address","bcc_address","conf_id");
					//~ $tName1=$this->SELECT($tName,$f,$w," and c.status=1",$this->DB_H);
					//~ $mail_rule_found = $this->GET_ROWS_COUNT($tName1);
					//code by noumya for department wise template
					$tName1 = $this->SELECT($tName,$f,$w," and dept_id=".$assigned_to_dept_id." and c.status=1",$this->DB_H);
					$mail_rule_found = $this->GET_ROWS_COUNT($tName1);
					if(!$mail_rule_found){
						$tName1 = $this->SELECT($tName,$f,$w," and default_dept=true and c.status=1",$this->DB_H);
						$mail_rule_found = $this->GET_ROWS_COUNT($tName1);
					}
					//end code by noumya for department wise template
					$w_dept=array("ticket_status"=>array(STRING,'Department Ticket Assigned'));
					$tName_dept=$this->SELECT($tName,$f,$w_dept," and c.status=1",$this->DB_H);
					$mail_rule_found_dept = $this->GET_ROWS_COUNT($tName_dept);
					if($mail_rule_found  || $mail_rule_found_dept)
					{
						$f=$this->FETCH_ARRAY($tName1,MYSQLI_ASSOC);
						$subject_mail   =       $f["subject"];
						$msg            =       $f["message"];
						$mail_mesg      =       $f["salutation"]."<br><br>".$f["message"]."<br><br><br>".$f["signature"];
						$conf_id        =       $f["conf_id"];
						$subject   = $f["subject"];
						//for dept
						$f_dept=$this->FETCH_ARRAY($tName_dept,MYSQLI_ASSOC);
						
						$subject_mail_dept      =       $f_dept["subject"];
						$msg_dept               =       $f_dept["message"];
						$mail_mesg_dept =       $f_dept["salutation"]."<br><br>".$f_dept["message"]."<br><br><br>".$f_dept["signature"];
						$conf_id_dept   =       $f_dept["conf_id"];
						$subject_dept   = $f_dept["subject"];
						
						//~ Fetch details from mail
						$query="select subject,message_id,mail_references from mail where ticket_id=".$ticket_id."  order by length(mail_references) desc ,mail_date desc limit 1";
						
						$resultMail=$this->EXECUTE_QUERY($query,$this->DB_H);
						$references="";
						if($this->GET_ROWS_COUNT($resultMail)){
							$result=$this->FETCH_ARRAY($resultMail,MYSQLI_ASSOC);
							$database_mail_subject = $result["subject"];
							$messageID=$result["message_id"];
							$references=$result["mail_references"];
							if(!empty($messageID) || !empty($references)){
								$references=$messageID." ".$references;
							}
							$references=rtrim($references);
						}
						
						$replaceString=array(
						$query_issue['docket_no'],
						$query_issue["person_name"],
						$database_mail_subject,
						$query_issue['ticket_status'],
						$query_issue["problem_reported"],
						$query_issue["agent_remarks"],
						$query_issue["action_taken"],
						$query_issue['priority_name'],
						$queryDeptHeadMail['first_name'],
						$query_issue['dept_name'],
						$query_issue['creation_time'],
						$query_issue['created_on'],
						$query_issue['modified_on'],
						$docket_short,
						$query_issue['ticket_type'],
						$query_issue['disposition_name'],
						$query_issue['sub_disposition_name'],
						$query_issue['time_elapsed'],
						$query_issue['last_escalated_on'],
						$query_issue['ticket_assigned_time'],
						);
						$subject = str_replace($searchString,$replaceString,$subject);
						$mail_mesg = str_replace($searchString,$replaceString,$mail_mesg);
						$subject_dept = str_replace($searchString,$replaceString,$subject_dept);
						$mail_mesg_dept = str_replace($searchString,$replaceString,$mail_mesg_dept);
						
						$bcc_address = $mail_cc = "";
						$filename = $_BLANK_ARRAY;
						require_once("../classes/emailHandler.class.php");
						$EM_H = new emailHandler($client_id);
						if(!empty($mail_send)){
							$EM_H->createMail($subject,$mail_mesg,$f["from_address"],$bcc_address,$mail_cc,$f["server"],$mail_mesg,$mail_send,'AUTO REPLY',$ticket_id,$filename,$conf_id,$query_issue['docket_no'],$references,'1','','');
						}
						if(!empty($mail_send_dept)){
							$EM_H->createMail($subject_dept,$mail_mesg_dept,$f["from_address"],$bcc_address,$mail_cc,$f_dept["server"],$mail_mesg_dept,$mail_send_dept,'AUTO REPLY',$ticket_id,$filename,$conf_id_dept,$query_issue['docket_no'],$references,'1','','');
						}
					}
					if(!empty($user_phone) || !empty($dept_phone))
					{
						//~ Send whatspp message - START
						$mail_status = 'Ticket Assigned';
						$tName_wht = "whatsapp_services as a left join mail_event as b on a.event=b.id left join whatsapp_rule_conf as c on a.template_id=c.conf_id";
						$w_wht = array("ticket_status"=>array(STRING,$mail_status));
						$f_wht = array("message","to_address","server");
						$tName_wht_details=$this->SELECT($tName_wht,$f_wht,$w_wht," and dept_id=".$assigned_to_dept_id,$this->DB_H);
						$wht_rule_found = $this->GET_ROWS_COUNT($tName_wht_details);
										
						if(!$wht_rule_found){
							$tName_wht_details=$this->SELECT($tName_wht,$f_wht,$w_wht," and default_dept=true",$this->DB_H);
							$wht_rule_found = $this->GET_ROWS_COUNT($tName_wht_details);
						}
						
						//for dept
						$mail_status_dept = 'Department Ticket Assigned';
						$w_wht_dept = array("ticket_status"=>array(STRING,$mail_status_dept));
						$tName_wht_details_dept=$this->SELECT($tName_wht,$f_wht,$w_wht_dept," and dept_id=".$assigned_to_dept_id,$this->DB_H);
						$wht_rule_found_dept = $this->GET_ROWS_COUNT($tName_wht_details_dept);
										
						if(!$wht_rule_found_dept){
							$tName_wht_details_dept=$this->SELECT($tName_wht,$f_wht,$w_wht_dept," and default_dept=true",$this->DB_H);
							$wht_rule_found_dept = $this->GET_ROWS_COUNT($tName_wht_details_dept);
						}
						
						if($wht_rule_found || $wht_rule_found_dept)
						{
							$fetch_wht = $this->FETCH_ARRAY($tName_wht_details,MYSQLI_ASSOC);
							$replaceString=array(
							$query_issue['docket_no'],
							$query_issue["person_name"],
							$database_mail_subject,
							$query_issue['ticket_status'],
							$query_issue["problem_reported"],
							$query_issue["agent_remarks"],
							$query_issue["action_taken"],
							$query_issue['priority_name'],
							$queryDeptHeadMail['first_name'],
							$query_issue['dept_name'],
							$query_issue['creation_time'],
							$query_issue['created_on'],
							$query_issue['modified_on'],
							$docket_short,
							$query_issue['ticket_type'],
							$query_issue['disposition_name'],
							$query_issue['sub_disposition_name'],
							$query_issue['time_elapsed'],
							$query_issue['last_escalated_on'],
							$query_issue['ticket_assigned_time'],
							);
							$wht_msg=str_replace($searchString,$replaceString,$fetch_wht["message"]);
							$fetch_wht_dept = $this->FETCH_ARRAY($tName_wht_details_dept,MYSQLI_ASSOC);
							$wht_msg_dept=str_replace($searchString,$replaceString,$fetch_wht_dept["message"]);
							require_once("/var/www/html/CZCRM/classes/whatsAppHandler.class.php");
							$W_H = new whatsAppHandler($client_id);
							$type = 'T';
							$waArr = array(
							"whatsapp_account_id" => $fetch_wht['server'],
							"message" => $wht_msg,
							"mobile_no" => $user_phone,
							"ticket_id" => $ticket_id,
							"ticket_status" => $mail_status,
							"type" => $type,
							"created_by" => $query_issue['created_by'],
							"created_by_id" => $query_issue['created_by_id'],
							);
							
							$W_H->sendWAMessage($waArr);
							//for dept
							$waArr_dept = array(
							"whatsapp_account_id" => $fetch_wht_dept['server'],
							"message" => $wht_msg_dept,
							"mobile_no" => $dept_phone,
							"ticket_id" => $ticket_id,
							"ticket_status" => $mail_status_dept,
							"type" => $type,
							"created_by" => $query_issue['created_by'],
							"created_by_id" => $query_issue['created_by_id'],
							);
							$W_H->sendWAMessage($waArr_dept);
							
						}
						//~ Send whatspp message - END
						
						$tName_sms="sms_services as a left join mail_event as b on a.event=b.id left join sms_rule_conf as c on a.template_id=c.conf_id";
						$w_sms=array("ticket_status"=>array(STRING,'Ticket Assigned'));
						$f_sms=array("message","to_address","server");
						$tName_sms_details=$this->SELECT($tName_sms,$f_sms,$w_sms," and c.status=1",$this->DB_H);
						
						$sms_rule_found = $this->GET_ROWS_COUNT($tName_sms_details);
						//for dept
						$w_sms_dept=array("ticket_status"=>array(STRING,'Department Ticket Assigned'));
						$tName_sms_details_dept=$this->SELECT($tName_sms,$f_sms,$w_sms_dept," and c.status=1",$this->DB_H);
						$sms_rule_found_dept = $this->GET_ROWS_COUNT($tName_sms_details_dept);
						
						if($sms_rule_found || $sms_rule_found_dept)
						{
							$fetch_sms=$this->FETCH_ARRAY($tName_sms_details,MYSQLI_ASSOC);
							$replaceString=array(
							$query_issue['docket_no'],
							$query_issue["person_name"],
							$database_mail_subject,
							$query_issue['ticket_status'],
							$query_issue["problem_reported"],
							$query_issue["agent_remarks"],
							$query_issue["action_taken"],
							$query_issue['priority_name'],
							$queryDeptHeadMail['first_name'],
							$query_issue['dept_name'],
							$query_issue['creation_time'],
							$query_issue['created_on'],
							$query_issue['modified_on'],
							//$query_issue['company_name'],
							$docket_short,
							$query_issue['ticket_type'],
							$query_issue['disposition_name'],
							$query_issue['sub_disposition_name'],
							$query_issue['time_elapsed'],
							$query_issue['last_escalated_on'],
							$query_issue['ticket_assigned_time'],
							);
							$msg=str_replace($searchString,$replaceString,$fetch_sms["message"]);
							$type = 'T';
							$smsArray = array(
							"sms_gateway_id" => $fetch_sms["server"],
							"message" => $msg,
							"mobile_no" => $user_phone,
							"ticket_id" => $ticket_id,
							"ticket_status" => "Ticket Assigned",
							"auto_reply" => 1,
							"type" => $type,
							);
							
							$S_H = new smsHandler($client_id);
							$S_H->sendSMS($smsArray);
							
							$fetch_sms_dept=$this->FETCH_ARRAY($tName_sms_details_dept,MYSQLI_ASSOC);
							$msg_dept=str_replace($searchString,$replaceString,$fetch_sms_dept["message"]);
							$type = 'T';
							$smsArraydept = array(
							"sms_gateway_id" => $fetch_sms_dept["server"],
							"message" => $msg_dept,
							"mobile_no" => $dept_phone,
							"ticket_id" => $ticket_id,
							"ticket_status" => "Ticket Assigned",
							"auto_reply" => 1,
							"type" => $type,
							);
							$S_H = new smsHandler($client_id);
							$S_H->sendSMS($smsArraydept);
						}
					}
					return $this->result = '{"Success":"Ticket Assigned Successfully"}';
				}
				else{
					return $this->result='{"Error":"Error in Assignment"}';
				}
			}
		}
		private function privateNotetMobile(){
			require_once ("/var/www/html/CZCRM/modules/DATABASE/MongoClient.class.php");
			$created_on             =       date("Y-m-d H:i:s");
			$ticketJson=json_encode($this->dataArray);
			$ticket_id = isset($this->dataArray->ticket_id)?$this->dataArray->ticket_id:"";
			$lead_id = isset($this->dataArray->lead_id)?$this->dataArray->lead_id:"";
			$private_note = isset($this->dataArray->private_note)?$this->dataArray->private_note:"";
			$docket_num = isset($this->dataArray->docket_num)?$this->dataArray->docket_num:"";
			$created_by = isset($this->dataArray->created_by)?$this->dataArray->created_by:"";
			$key = isset($this->dataArray->key)?$this->dataArray->key:"";
			$client_id = isset($this->dataArray->client_id)?$this->dataArray->client_id:"";
			$CLIENT_FOLDER = isset($this->dataArray->CLIENT_FOLDER)?$this->dataArray->CLIENT_FOLDER:"";
			if(empty($ticket_id)){
				return $this->result='{"Error":"Required parameters misssing."}';
			}
			else{
				$idVal                  =       "";
				$jsonArray              =       array();
				if(!empty($ticket_id)){
					$idVal                  =       $ticket_id;
					$action_type    =       'ticket';
				}
				elseif(!empty($lead_id)){
					$idVal          =       $lead_id;
					$action_type    =       'lead';
				}
				$datetime       =       time();
				if(!empty($idVal)){
					if($action_type=='ticket'){
						$jsonArray['ticket_id'] = (int) $idVal;
						$jsonArray['created_on'] =      $created_on;
						$jsonArray['created_by'] =      $created_by;
						$jsonArray['private_note'] =    $private_note;
						$collection = "ticket_private_note";
						$datajson       =       '{"action":"ticket_privatenote","date_time":"'.$datetime.'","action_by":"'.$created_by.'","action_to":"","ticket_id":"'.$idVal.'","docket_no":"'.$docket_num.'","client_folder":"'.$CLIENT_FOLDER.'","type":"TICKET","comment_text":"'.$private_note.'"}';
					}
					elseif($action_type=='lead'){
						$jsonArray['lead_id'] = (int) $idVal;
						$jsonArray['created_on'] =      $created_on;
						$jsonArray['created_by'] =      $created_by;
						$jsonArray['private_note'] =    $private_note;
						$collection = "lead_private_note";
						$datajson       =       '{"action":"lead_privatenote","date_time":"'.$datetime.'","action_by":"'.$created_by.'","action_to":"","lead_id":"'.$idVal.'","docket_no":"'.$docket_num.'","client_folder":"'.$CLIENT_FOLDER.'","type":"LEAD","comment_text":"'.$private_note.'"}';
					}
					$db = "crm_manager_".$client_id;
					$u=$p='';
					$mongo  =       new MongoClient('127.0.0.1',$u,$p,$db);
					$mongo->connectDB();
					$mongo->INSERT($collection,$jsonArray,'');
					timeline_history($datajson);
					
				}
				return $this->result = '{"Success":"Private Note Added Successfully"}';
			}
		}
		
		private function myTickets(){
			$FLP = new logs_creation($this->client_id);
			$ticketJson=json_encode($this->dataArray);
			$FLP->prepare_log("1","======my ticket json==============", $ticketJson);
			$user_id = isset($this->dataArray->user_id)?$this->dataArray->user_id:"";
			$FLP->prepare_log("1","======user id==============", $user_id);
			if(empty($user_id)){
				$FLP->prepare_log("1","======in empty user==============", "-------------------");
				return $this->result='{"Error":"Required parameters misssing."}';
			}
			else{
				if(isset($user_id)){
					$FLP->prepare_log("1","====== in user id found==============","++++++++++++++++");
					$TH = new ticketHandler($this->client_id);
					$returned_data=$TH->myTickets($ticketJson);
					//~ $this->prepare_log("======ticket data=============",print_r($returned_data,true));
				}
				if(isset($returned_data['Error'])){
					return $this->result='{"Error":"'.$returned_data['Error'].'"}';
					$FLP->prepare_log("1","======error result=============",$this->result);
					}else{
					return $this->result='{"Success":'.$returned_data.'}';
					$FLP->prepare_log("1","======success result=============",$this->result);
				}
			}
		}
		
		private function searchTicket(){
			$FLP = new logs_creation($this->client_id);
			$FLP->prepare_log("1","======DB UTILITY==============","searchTicket");
			$ticketJson=json_encode($this->dataArray);
			$docket_no = isset($this->dataArray->docket_no)?$this->dataArray->docket_no:"";
			if(empty($docket_no)){
				return $this->result='{"Error":"Please enter docket number."}';
			}
			else{
				if(isset($docket_no)){
					//~ $search_ticket = str_replace ("*", "%", $this->MYSQLI_REAL_ESCAPE($this->DB_H,$search_ticket));
					$TH = new ticketHandler($this->client_id);
					$returned_data=$TH->searchTicket($ticketJson);
				}
				if(isset($returned_data['Error'])){
					$FLP->prepare_log("1","----Error----",$returned_data['Error']);
					return $this->result='{"Error":"'.$returned_data['Error'].'"}';
					}else{
					return $this->result='{"Success":'.$returned_data.'}';
				}
			}
		}

		private function applyChange(){
			$FLP = new logs_creation($this->client_id);
			$FLP->prepare_log("1","======function==============","=====applyChange=====");
			$client_id = isset($this->client_id)?$this->client_id:"";
			$module_name = isset($this->dataArray->module_name)?$this->dataArray->module_name:"";
			$table_name = isset($this->dataArray->table_name)?$this->dataArray->table_name:"";

			switch($module_name){
				case 'users':
				$f=array("user_id","user_name","dept_id","status","assign_type");
				// $qry = $DB->SELECT ($table_name , $f, $_BLANK_ARRAY, " AND user_name!='admin'", $DB_H);
				$qry = $this->SELECT ($table_name , $f, $_BLANK_ARRAY, " AND user_id!='1'", $this->DB_H);
				while($tRows = $this->FETCH_ARRAY ($qry,MYSQLI_ASSOC)){
					if($tRows['assign_type']=='ticket' || $tRows['assign_type']=='both'){
						if($tRows['status']=="ACTIVE"){
							$data_array[$client_id]['TICKET']['ACTIVE'][$tRows['dept_id']][$tRows['user_id']]=$tRows['user_name'];
							$data_array[$client_id]['TICKET']['ACTIVE_ARRAY'][$tRows['user_id']]=$tRows['user_name'];
						}
						$data_array[$client_id]['TICKET']['ALL'][$tRows['dept_id']][$tRows['user_id']]=$tRows['user_name'];
						$data_array[$client_id]['TICKET']['SEARCH'][$tRows['user_id']]=$tRows['user_name'];
					}
					if($tRows['assign_type']=='lead' || $tRows['assign_type']=='both'){
						if($tRows['status']=="ACTIVE"){
							$data_array[$client_id]['LEAD']['ACTIVE'][$tRows['dept_id']][$tRows['user_id']]=$tRows['user_name'];
							$data_array[$client_id]['LEAD']['ACTIVE_ARRAY'][$tRows['user_id']]=$tRows['user_name'];
						}
						$data_array[$client_id]['LEAD']['ALL'][$tRows['dept_id']][$tRows['user_id']]=$tRows['user_name'];
						$data_array[$client_id]['LEAD']['SEARCH'][$tRows['user_id']]=$tRows['user_name'];
					}
					if($tRows['assign_type']=='both'){
						if($tRows['status']=="ACTIVE"){
							$data_array[$client_id]['BOTH']['ACTIVE'][$tRows['dept_id']][$tRows['user_id']]=$tRows['user_name'];
							$data_array[$client_id]['BOTH']['ACTIVE_ARRAY'][$tRows['user_id']]=$tRows['user_name'];
						}
						$data_array[$client_id]['BOTH']['ALL'][$tRows['dept_id']][$tRows['user_id']]=$tRows['user_name'];
						$data_array[$client_id]['BOTH']['SEARCH'][$tRows['user_id']]=$tRows['user_name'];
					}
				}
				break;
				case 'department':
					$f=array("dept_id","dept_name");
					$qry = $this->SELECT ($table_name , $f, $_BLANK_ARRAY, "", $this->DB_H);
					while($tRows = $this->FETCH_ARRAY ($qry,MYSQLI_ASSOC)){
						$data_array[$client_id]['TICKET']['ALL'][$tRows['dept_id']]=$tRows['dept_name'];
						$data_array[$client_id]['TICKET']['ACTIVE_ARRAY'][$tRows['dept_id']]=$tRows['dept_name']; //to be review by subohi
						$data_array[$client_id]['LEAD']['ALL'][$tRows['dept_id']]=$tRows['dept_name'];
						$data_array[$client_id]['LEAD']['ACTIVE_ARRAY'][$tRows['dept_id']]=$tRows['dept_name']; //to be review by subohi
					}
				break;
				default:
			}


			$folder = "/var/www/html/CZCRM/master_data_config/".$client_id;
			$file_name = "/var/www/html/CZCRM/master_data_config/".$client_id."/".$module_name.".txt";
			$entry_date = date('Y-m-d');
		
			if (!file_exists($folder)){
				mkdir($folder, 0777,true);
			}

			$data_json = json_encode($data_array,true);	
			$fp = fopen($file_name,"w");
			fwrite($fp,$data_json);
			fclose($fp);

			$final_api  = _CALL_API_DNS."/global_apply_change_tp.php";
                        $array=array("table_name"=>$table_name,"module_name"=>$module_name,"CLIENT_ID"=>$client_id);
                        $my_result = do_remote($final_api,$array);
			$FLP->prepare_log("1","======function end==============","=====applyChange=====");
		}
		
		private function addUser(){
			$FLP = new logs_creation($this->client_id);
			$FLP->prepare_log("1","======DB UTILITY==============","adduser");
			$email=isset($this->dataArray->email)?$this->dataArray->email:"";
			$mobile_no=isset($this->dataArray->mobile_no)?$this->dataArray->mobile_no:"";
			$first_name=isset($this->dataArray->first_name)?$this->dataArray->first_name:"";
			$last_name=isset($this->dataArray->last_name)?$this->dataArray->last_name:"";
			$role=isset($this->dataArray->role)?$this->dataArray->role:"";
			$uuid=isset($this->dataArray->uuid)?$this->dataArray->uuid:"";
			$duid=isset($this->dataArray->duid)?$this->dataArray->duid:"";
			$status=isset($this->dataArray->status)?$this->dataArray->status:"";
			$length=4;
			$password = bin2hex(random_bytes($length));
			
			if(empty($email)||empty($mobile_no)||empty($first_name)||empty($role)|| empty($duid)){
				return $this->result='{"Error":"Required parameters missing"}';
			}
			else{
				$role_id=0;
				if(strtolower($role)=="admin"){
					$role_id=1;
				}
				else if(strtolower($role)=="agent"){
					$role_id=2;
				}
				//------in this code q=we need to add fails if
				$query_dept="select dept_id from departments where duid=".$duid;
				$result_dept=$this->EXECUTE_QUERY($query_dept,$this->DB_H);
				$row_dept=$this->FETCH_ARRAY($result_dept,MYSQLI_ASSOC);
				$dept_id=$row_dept["dept_id"];
				//---------------------------
				$query_user = "INSERT INTO users (user_name,user_password,first_name,last_name,phone_mobile,email1,status,user_flag,role_id,first_login_date,changepassword_on,uuid,dept_id,source_app)VALUES ('".$first_name."',PASSWORD('".$password."'),'".$first_name."','".$last_name."','".$mobile_no."','".$email."','".$status."',0,'$role_id',CURDATE(),CURDATE(),'".$uuid."','".$dept_id."','OMNI')";
				$this->EXECUTE_QUERY($query_user,$this->DB_H);
				$user_id=mysqli_insert_id($this->DB_H);
				if($user_id){
					$tName  ="czcrm_generic.userAuth";
					$v = array (
					"username"                      =>array(STRING,$first_name),
					"firstname"                     =>array(STRING,$first_name),
					//"lastname"                    =>array(STRING,$last_name),
					"user_password"         =>array(MYSQL_FUNCTION,'PASSWORD("'.$password.'")'),
					"registration_id"       =>array(STRING,$this->client_id),
					//      "client_id"     =>array(STRING,$client_id),
					"user_id"       =>array(STRING,$user_id),
					"mobile"        =>array(STRING,$mobile_no),
					"email" =>array(STRING,$email),
					"uuid"=>array(STRING,$uuid)
					);
					$tName  =       $this->INSERT($tName,$v, $this->DB_H);
					$last_inserted_id= mysqli_insert_id($this->DB_H);
					if($last_inserted_id!=0){
						
						//$json_array = '{"module_name":"users","table_name":"users"}';
						$this->dataArray->module_name = "users";
                        $this->dataArray->table_name = "users";
						$this->applyChange();
						
						return $this->result='{"passcode":"'.$password.'"}';
					}
					else{
						return $this->result='{"Error":"User already exists"}';
					}
				}
				else{
					return $this->result='{"Error":"User already exists"}';
				}
				
			}
		}
		
		private function addUuidUser(){
			$FLP = new logs_creation($this->client_id);
			$FLP->prepare_log("1","======DB UTILITY==============","addUuidUser");
			$packet_id = isset($this->dataArray->packetid)?$this->dataArray->packetid:"";
			$uuid=isset($this->dataArray->uuid)?$this->dataArray->uuid:"";
			$buid=isset($this->dataArray->buid)?$this->dataArray->buid:"";
			$fetched_buid = '';
			$positive_flag = $negative_flag = 0;
			if(isset($this->dataArray->Error)){
				//~ if((isset($this->dataArray->error_status)) && ($this->dataArray->error_status == 1)){
				$query_check_buid =  "select buid from ".GDB_NAME.".clientRegistrationBasic where registration_id=".$this->client_id;
				$exe_check_buid = $this->EXECUTE_QUERY($query_check_buid,$this->DB_H);
				$fetch_check_buid = $this->FETCH_ARRAY($exe_check_buid,MYSQLI_ASSOC);
				$fetched_buid = $fetch_check_buid['buid'];
				//~ }
				if((!empty($fetched_buid)) && ($fetched_buid == $buid)){
					$positive_flag = 1;
					}else{
					$negative_flag = 1;
				}
			}
			else if(isset($this->dataArray->Success) && !empty($packet_id)){
				$positive_flag = 1;
				}else{
				$negative_flag = 1;
			}
			
			if($positive_flag == 1){
				$query_update_uuid = "UPDATE users set uuid='".$uuid."' where packet_id=".$packet_id;
				$this->EXECUTE_QUERY($query_update_uuid,$this->DB_H);
				$query_update_uuid1 = "UPDATE ".GDB_NAME.".userAuth set uuid='".$uuid."' where packet_id=".$packet_id;
				$this->EXECUTE_QUERY($query_update_uuid1,$this->DB_H);
				$query_packet_delete = "delete from packet_queue where packet_id=".$packet_id;
				$this->EXECUTE_QUERY($query_packet_delete,$this->DB_H);
				
				if(!empty($uuid)){
					require_once("../classes/provisionHandler.class.php");
					$PH = new provisionHandler($this->client_id);
					$PH->addUserProvision($uuid);
				}
				
				}else if($negative_flag == 1){
				$query_update_uuid = "UPDATE users set add_status='2' where packet_id=".$packet_id." and add_status!='1' ";
				$this->EXECUTE_QUERY($query_update_uuid,$this->DB_H);
			}
		}
		
		private function ackAddUserProvision(){
			$FLP = new logs_creation($this->client_id);
			$FLP->prepare_log("1","======DB UTILITY==============","ackAddUserProvision");
			require_once("../classes/provisionHandler.class.php");
			$PH = new provisionHandler($this->client_id);
			$userAckJson=json_encode($this->dataArray);
			$PH->ackAddUserProvision($userAckJson);
		}
		private function expireUserProvision(){
			$FLP = new logs_creation($this->client_id);
			$FLP->prepare_log("1","======DB UTILITY==============","expireUserProvision");
			require_once("../classes/provisionHandler.class.php");
			$PH = new provisionHandler($this->client_id);
			$userAckJson=json_encode($this->dataArray);
			$PH->expireUserProvision($userAckJson);
		}
		
		private function updateUser(){
			$FLP = new logs_creation($this->client_id);
			$FLP->prepare_log("1","======DB UTILITY==============","updateUser");
			$email=isset($this->dataArray->email)?$this->dataArray->email:"";
			$mobile_no=isset($this->dataArray->mobile_no)?$this->dataArray->mobile_no:"";
			$first_name=isset($this->dataArray->first_name)?$this->dataArray->first_name:"";
			$last_name=isset($this->dataArray->last_name)?$this->dataArray->last_name:"";
			$role=isset($this->dataArray->role)?$this->dataArray->role:"";
			$uuid=isset($this->dataArray->uuid)?$this->dataArray->uuid:"";
			$duid=isset($this->dataArray->duid)?$this->dataArray->duid:"";
			$status=isset($this->dataArray->status)?$this->dataArray->status:"";
			$check_query="select user_id from czcrm_generic.userAuth where uuid=".$uuid;
			$checkResult=$this->EXECUTE_QUERY($check_query,$this->DB_H);
			$checkRow=$this->FETCH_ARRAY($checkResult,MYSQLI_ASSOC);
			$user_id=$checkRow["user_id"];
			if($user_id){
				
				if(empty($email)||empty($mobile_no)||empty($first_name)||empty($role)|| empty($duid)){
					return $this->result='{"Error":"Required parameters missing"}';
				}
				else{
					$role_id=0;
					if(strtolower($role)=="admin"){
						$role_id=1;
					}
					else if(strtolower($role)=="agent"){
						$role_id=2;
					}
					
					//------in this code q=we need to add fails if
					$query_dept="select dept_id from departments where duid=".$duid;
					$result_dept=$this->EXECUTE_QUERY($query_dept,$this->DB_H);
					$row_dept=$this->FETCH_ARRAY($result_dept,MYSQLI_ASSOC);
					$dept_id=$row_dept["dept_id"];
					//---------------------------
					$query_user = "update users set first_name='".$first_name."',phone_mobile='".$mobile_no."',last_name='".$last_name."',email1='".$email."',status='".$status."',role_id='".$role_id."',dept_id='".$dept_id."' where uuid='".$uuid."'";
					$result=$this->EXECUTE_QUERY($query_user,$this->DB_H);
					//$user_id=mysqli_insert_id($this->DB_H);
					if($result){
						$tName  ="czcrm_generic.userAuth";
						$v = array (
						"firstname"                     =>array(STRING,$first_name),
						//"lastname"                    =>array(STRING,$last_name),
						);
						$tName  =       $this->UPDATE($tName,$v, $this->DB_H);
						if($tName){
							//$json_array = '{"module_name":"users","table_name":"users"}';
							$this->dataArray->module_name = "users";
                                                	$this->dataArray->table_name = "users";
							$this->applyChange();
						}
					}
					else{
						return $this->result='{"Error":"Internal server error"}';
					}
				}
			}
			else{
				return $this->addUser();
			}
		}
		
		private function addDepartment(){
			$FLP = new logs_creation($this->client_id);
			$FLP->prepare_log("1","======DB UTILITY==============","addDepartment");
			$dept_name=isset($this->dataArray->dept_name)?$this->dataArray->dept_name:"";
			$description=isset($this->dataArray->description)?$this->dataArray->description:"";
			$dept_email=isset($this->dataArray->dept_email)?$this->dataArray->dept_email:"";
			$dept_phone=isset($this->dataArray->dept_phone)?$this->dataArray->dept_phone:"";
			$duid=isset($this->dataArray->duid)?$this->dataArray->duid:"";
			
			if(empty($duid)||empty($dept_name)){
				return $this->result='{"Error":"Required parameters missing"}';
			}
			else{
				//---------------------------
				$query_user = "INSERT INTO departments (dept_name,dept_email,dept_phone,duid,description,created_on,created_by)VALUES ('".$dept_name."','".$dept_email."','".$dept_phone."','".$duid."','".$description."',NOW(),'API')";
				$this->EXECUTE_QUERY($query_user,$this->DB_H);
				$dept_id=mysqli_insert_id($this->DB_H);
				if($dept_id){
					
					//$json_array = '{"module_name":"department","table_name":"departments"}';
					$this->dataArray->module_name = "department";
                    $this->dataArray->table_name = "departments";
					$this->applyChange($json_array);
					
					return $this->result='{"Success":"Department added successfully"}';
				}
				else{
					return $this->result='{"Error":"User already exists"}';
				}
				
			}
			
		}
		private function ivrTicket(){
			$FLP = new logs_creation($this->client_id);
			$FLP->prepare_log("1","======DB UTILITY==============","ivrTicket");
			if(!empty($this->client_id))
			{
				$folderInfo     =       file_get_contents("../configs/client_wise_folderInfo.txt");
				$folderArr      =       json_decode($folderInfo,true);
				$clientfolder   =       "";
				if(isset($folderArr[$this->client_id])){
					$clientfolder   =       $folderArr[$this->client_id];
				}
				$dataArray1= $this->dataArray;
				$result = require_once("../classes/$clientfolder/appsHandler.php");
				//$result = require_once("/var/www/html/CZCRM/classes/$clientfolder/appsHandler.php");
                $this->result='{"Success":"$result"}';
			}
			else{
				return $this->result='{"Error":"Client id  not exists"}';
			}
		}
		
		private function updateDepartment(){
			$FLP = new logs_creation($this->client_id);
			$FLP->prepare_log("1","======DB UTILITY==============","updateDepartment");
			$dept_name=isset($this->dataArray->dept_name)?$this->dataArray->dept_name:"";
			$description=isset($this->dataArray->description)?$this->dataArray->description:"";
			$dept_email=isset($this->dataArray->dept_email)?$this->dataArray->dept_email:"";
			$dept_phone=isset($this->dataArray->dept_phone)?$this->dataArray->dept_phone:"";
			$duid=isset($this->dataArray->duid)?$this->dataArray->duid:"";
			if(empty($duid)||empty($dept_name)){
				return $this->result='{"Error":"Required parameters missing"}';
			}
			else{
				//---------------------------
				$query_user = "update departments set dept_name='".$dept_name."',dept_email='".$dept_email."',dept_phone='".$dept_phone."',duid='".$duid."',description='".$description."',modified_on=NOW(),modified_by='API' where duid='".$duid."'";
				$dept_check = $this->EXECUTE_QUERY($query_user,$this->DB_H);
				$dept_id=mysqli_insert_id($this->DB_H);
			//	if($dept_id){
				if($dept_check){
					
					//$json_array = '{"module_name":"department","table_name":"departments"}';
					
					$this->dataArray->module_name = "department";
					$this->dataArray->table_name = "departments";
					$this->applyChange();
				}
				else{
					$query_check="select dept_id from departments where duid=".$duid;
					$result_check=$this->EXECUTE_QUERY($query_check,$this->DB_H);
					$numRows=mysqli_num_rows();
					
					return $this->result='{"Error":"Error in updation!!"}';
				}
			}
		}
		
		private function addProvision(){
			$FLP = new logs_creation($this->client_id);
			$FLP->prepare_log("1","======DB UTILITY==============","addProvision");
			require_once("../classes/provisionHandler.class.php");
			$provsionJson = json_encode($this->dataArray);
			$PH = new provisionHandler($this->client_id);
			$PH->addProvision($provsionJson);
		}
		
		// Lead creation function --------------  4-Sept-2018
		
		private function createLeadApi(){
			$FLP = new logs_creation($this->client_id);
			$FLP->prepare_log("1","======DB UTILITY CREATELEAD API==============",$this->dataArray);
			$person_name=isset($this->dataArray->person_name)?trim($this->dataArray->person_name):'';
			$mobile_no=isset($this->dataArray->mobile_no)?trim($this->dataArray->mobile_no):'';
			$phone=isset($this->dataArray->phone)?trim($this->dataArray->phone):'';
			$created_by=isset($this->dataArray->created_by)?trim($this->dataArray->created_by):'';
			$CLIENT_FOLDER=isset($this->dataArray->CLIENT_FOLDER)?trim($this->dataArray->CLIENT_FOLDER):'';
			$this->dataArray->omni_not_added = 1;
			$this->dataArray->source_info = $mobile_no;
			$this->dataArray->call_phone_no = $mobile_no;
			$this->dataArray->phone1 = $phone;
			$this->dataArray->source = 'Manual';
			$this->dataArray->module_name_ajax = 'create_lead';
			$this->dataArray->reqType = 'createLead';
			$CLIENT_FOLDER = $this->dataArray->CLIENT_FOLDER;
			if(isset($mobile_no) && !empty($mobile_no)){
				if(file_exists("/var/www/html/CZCRM/master_data_config/".$this->client_id."/default_fields.json")){
					$fileData = file_get_contents("/var/www/html/CZCRM/master_data_config/".$this->client_id."/default_fields.json");
				}
				else{
					$fileData = file_get_contents("/var/www/html/CZCRM/configs/default_fields.json");
				}
				$defaultArray = json_decode($fileData,true);
				$default_dis = (isset($defaultArray['disposition_name']) && !empty($defaultArray['disposition_name']))?$defaultArray['disposition_name']:0;
				$default_subdis = (isset($defaultArray['sub_disposition_name']) && !empty($defaultArray['sub_disposition_name']))?$defaultArray['sub_disposition_name']:0;
				$default_leadsource = (isset($defaultArray['lead_source_name']) && !empty($defaultArray['lead_source_name']))?$defaultArray['lead_source_name']:'';
				$default_product = (isset($defaultArray['product_type_name']) && !empty($defaultArray['product_type_name']))?$defaultArray['product_type_name']:'';
				$default_leadstate = (isset($defaultArray['lead_state']) && !empty($defaultArray['lead_state']))?$defaultArray['lead_state']:0;
				$querycust = "select field_name from customized_form_fields  where form_id = 3 and delete_flag=0";
				$exe_querycust = $this->EXECUTE_QUERY($querycust,$this->DB_H);
				while($fetch_cust = $this->FETCH_ARRAY($exe_querycust,MYSQLI_ASSOC)){
						$field_name = $fetch_cust['field_name'];
						if(!isset($this->dataArray->$field_name)){
							$this->dataArray->$field_name = "";
						}
				}
				$querycust1 = "select * from customized_form_fields where form_id = 1 and show_ticket_lead = 1 and delete_flag=0";
				$exe_querycust1 = $this->EXECUTE_QUERY($querycust1,$this->DB_H);
				while($fetch_cust1 = $this->FETCH_ARRAY($exe_querycust1,MYSQLI_ASSOC)){
						$field_name = $fetch_cust1['field_name'];
						if(!isset($this->dataArray->$field_name)){
								$this->dataArray->$field_name = "";
						}
				}
				//lead cutomized feilds for upload start//
				$customzied_header_file = "";
				$customizedArrayHeader1 =array();
				if(isset($CLIENT_FOLDER) && !empty($CLIENT_FOLDER)){
					$customzied_header_file = "/var/www/html/CZCRM/".$CLIENT_FOLDER."/customized_header.json";
				}
				if($customzied_header_file!=''){
					if(file_exists($customzied_header_file)){
						$header_customized1 = file_get_contents($customzied_header_file);
						$customizedArray1 = json_decode($header_customized1,true);
						$customizedArrayHeader1 = $customizedArray1['Remove_fields'];
					}
				}
				if(count($customizedArrayHeader1)>0){
					foreach($customizedArrayHeader1 as $keycust=>$valcust){
						$this->dataArray->$keycust = $this->dataArray->$valcust;
					}
				}
				$FLP->prepare_log("1","======DB UTILITY CREATELEAD CUST==============",$this->dataArray);
				//lead cutomized feilds for upload end//



				$FLP->prepare_log("1","======DB UTILITY CREATELEAD API after==============",$this->dataArray);

				$person_info_final=$this->checkPerson();
				$person_info_final_json_decode = json_decode($person_info_final, true);
				$person_id = trim($person_info_final_json_decode['person_id']);
				$this->dataArray->person_id=$person_id;
				$FLP->prepare_log("1","======DB UTILITY CREATELEAD API person_id==============",$person_id);

				$person_name = trim($person_info_final_json_decode['person_name']);
				$disposition_name = (isset($this->dataArray->disposition_name) && !empty($this->dataArray->disposition_name))?$this->dataArray->disposition_name:$default_dis;
				$sub_disposition_name = (isset($this->dataArray->sub_disposition_name) && !empty($this->dataArray->sub_disposition_name))?$this->dataArray->sub_disposition_name:$default_subdis;
				$lead_source_name = (isset($this->dataArray->lead_source_name) && !empty($this->dataArray->lead_source_name))?$this->dataArray->lead_source_name:$default_leadsource;
				$product_type_name = (isset($this->dataArray->product_type_name) && !empty($this->dataArray->product_type_name))?$this->dataArray->product_type_name:$default_product;
				$lead_status_name = (isset($this->dataArray->lead_status_name) && !empty($this->dataArray->lead_status_name))?$this->dataArray->lead_status_name:'';
				$lead_state = (isset($this->dataArray->lead_state) && !empty($this->dataArray->lead_state))?$this->dataArray->lead_state:$default_leadstate;
				$assigned_to_dept_name = (isset($this->dataArray->assigned_to_dept_name) && !empty($this->dataArray->assigned_to_dept_name))?$this->dataArray->assigned_to_dept_name:'';
				$assigned_to_user_name = (isset($this->dataArray->assigned_to_user_name) && !empty($this->dataArray->assigned_to_user_name))?$this->dataArray->assigned_to_user_name:'';

				$this->dataArray->lead_dept  = $this->dataArray->assigned_to_dept_id = $this->dataArray->lead_user  = $this->dataArray->assigned_to_user_id=$this->dataArray->user_id1 = $this->dataArray->created_by_id=$this->dataArray->disposition_id=$this->dataArray->sub_disposition_id=$this->dataArray->lead_source=$this->dataArray->product_type=$this->dataArray->lead_status1 = $this->dataArray->lead_status_id= $this->dataArray->lead_state_id=$this->dataArray->sub_disposition_name= $this->dataArray->disposition_name=0;
				 if(!empty($assigned_to_dept_name)){
						$query_dept = "SELECT dept_id from departments where dept_name ='".$assigned_to_dept_name."'";
						$tName_dept = $this->EXECUTE_QUERY($query_dept,$this->DB_H);
						$fetch_dept = $this->FETCH_ARRAY($tName_dept,MYSQLI_ASSOC);
						$this->dataArray->lead_dept  = $this->dataArray->assigned_to_dept_id = isset($fetch_dept['dept_id'])?$fetch_dept['dept_id']:'0';
						if(empty($this->dataArray->assigned_to_dept_id)){
								$FLP->prepare_log("1","Error==============","wrong DEPT");
						}
				}
				if(!empty($this->dataArray->assigned_to_dept_id)){
						if(!empty($assigned_to_user_name)){
								$query_user = "SELECT user_id from users where user_name ='".$assigned_to_user_name."' and dept_id='".$this->dataArray->assigned_to_dept_id."' and status='ACTIVE'";
								$tName_user = $this->EXECUTE_QUERY($query_user,$this->DB_H);
								$fetch_user = $this->FETCH_ARRAY($tName_user,MYSQLI_ASSOC);
								$this->dataArray->lead_user  = $this->dataArray->assigned_to_user_id = isset($fetch_user['user_id'])?$fetch_user['user_id']:'0';
								if(empty($this->dataArray->assigned_to_user_id)){
										$FLP->prepare_log("1","Error==============","wrong USER");
								}
						}
				}
				if(!empty($created_by)){
						$query_user_create = "SELECT user_id from users where user_name ='".$created_by."' and status='ACTIVE'";
						$tName_user_create = $this->EXECUTE_QUERY($query_user_create,$this->DB_H);
						$fetch_user_create = $this->FETCH_ARRAY($tName_user_create,MYSQLI_ASSOC);
						$this->dataArray->user_id1 = $this->dataArray->created_by_id = isset($fetch_user_create['user_id'])?$fetch_user_create['user_id']:'0';
						if(empty($this->dataArray->created_by_id)){
								$FLP->prepare_log("1","Error==============","wrong create by name");
						}
				}

				if(!empty($disposition_name)){
						$query_dis = "SELECT id from disposition_tab where status='ACTIVE' and disposition_name ='".$disposition_name."'";
						$tName_dis = $this->EXECUTE_QUERY($query_dis,$this->DB_H);
						$fetch_dis = $this->FETCH_ARRAY($tName_dis,MYSQLI_ASSOC);
						$this->dataArray->disposition_name = $this->dataArray->disposition_id = isset($fetch_dis['id'])?$fetch_dis['id']:'0';
						if(empty($this->dataArray->disposition_id)){
								$FLP->prepare_log("1","Error==============","wrong disposition");
						}
				}
				 if(isset($this->dataArray->disposition_id) && !empty($this->dataArray->disposition_id)){
						if(!empty($sub_disposition_name)){
								$query_sub_dis = "SELECT id from sub_disposition_tab where status='ACTIVE' and disposition_id='".$this->dataArray->disposition_id."' and sub_disposition_name ='".$sub_disposition_name."'";
								$tName_sub_dis = $this->EXECUTE_QUERY($query_sub_dis,$this->DB_H);
								$fetch_sub_dis = $this->FETCH_ARRAY($tName_sub_dis,MYSQLI_ASSOC);
								$this->dataArray->sub_disposition_name = $this->dataArray->sub_disposition_id = isset($fetch_sub_dis['id'])?$fetch_sub_dis['id']:'0';
								if(empty($this->dataArray->sub_disposition_id)){
										$FLP->prepare_log("1","Error==============","wrong sub disposition");
								}
						}
				}
				if(!empty($lead_source_name)){
						$query_lead_source = "SELECT id from system_lead_source where status='ACTIVE' and source ='".$lead_source_name."'";
						$tName_lead_source = $this->EXECUTE_QUERY($query_lead_source,$this->DB_H);
						$fetch_lead_source = $this->FETCH_ARRAY($tName_lead_source,MYSQLI_ASSOC);
						$this->dataArray->lead_source = isset($fetch_lead_source['id'])?$fetch_lead_source['id']:'0';
						if(empty($this->dataArray->lead_source)){
								$FLP->prepare_log("1","Error==============","wrong Lead Source");
						}
				}
				if(!empty($product_type_name)){
						$query_product = "SELECT id from product_tab where product_name ='".$product_type_name."'";
						$tName_product = $this->EXECUTE_QUERY($query_product,$this->DB_H);
						$fetch_product = $this->FETCH_ARRAY($tName_product,MYSQLI_ASSOC);
						$this->dataArray->product_type = isset($fetch_product['id'])?$fetch_product['id']:'0';
						if(empty($this->dataArray->product_type)){
								$FLP->prepare_log("1","Error==============","wrong Product");
						}
				}
				if(!empty($lead_status_name)){
						$query_status = "SELECT ticket_status_id from ticket_status where status='ACTIVE' and ticket_status ='".$lead_status_name."'";
						$tName_status = $this->EXECUTE_QUERY($query_status,$this->DB_H);
						$fetch_status = $this->FETCH_ARRAY($tName_status,MYSQLI_ASSOC);
						$this->dataArray->lead_status1 = $this->dataArray->lead_status_id = isset($fetch_status['ticket_status_id'])?$fetch_status['ticket_status_id']:'0';
						if(empty($this->dataArray->lead_status_id)){
								$FLP->prepare_log("1","Error==============","wrong status");
						}
				}
				if(!empty($lead_state)){
						$query_state = "SELECT id from lead_state_tab where lead_state ='".$lead_state."'";
						$tName_state = $this->EXECUTE_QUERY($query_state,$this->DB_H);
						$fetch_state = $this->FETCH_ARRAY($tName_state,MYSQLI_ASSOC);
						$this->dataArray->lead_state_id = $this->dataArray->lead_state= isset($fetch_state['id'])?$fetch_state['id']:'0';
						if(empty($this->dataArray->lead_state_id)){
								$FLP->prepare_log("1","Error==============","wrong State");
						}
				}
					$FLP->prepare_log("1","======DB UTILITY CREATELEAD API FINAL ARRAY==============",$this->dataArray);

				$CREATE_LEAD = $this->createLead();
				print ($CREATE_LEAD);
				$FLP->prepare_log("1","===CREATE_LEAD===========",$CREATE_LEAD);
			}
			else{
				return $this->result='{"Error":"Unable to create Lead! Person mobile is blank"}';
			}
		}
		private function createLead(){
			$FLP = new logs_creation($this->client_id);
			if($this->dataArray->tpf==1){
				$_GET = json_decode(json_encode($this->dataArray), true);
			}

			$FLP->prepare_log("1","======DB UTILITY==============","createLead");
			//////////////////////////////////////////////////for creating person selected fields to show in ticket grid
			$this->dataArray->person_flds_show = "";
			$FLP->prepare_log("1","======createLead data array ==============",$this->dataArray);
			$person_flds_file="/var/www/html/CZCRM/master_data_config/".$this->client_id."/person_fields_json.txt";
			if(file_exists($person_flds_file)){
				$person_fld_json        =       file_get_contents($person_flds_file);
				$person_fld_json_arry           =       json_decode($person_fld_json,true);
				$prsn_fld_str='{';
					foreach($person_fld_json_arry as $prsn_key => $prsn_val){
						$prsn_fld_str .='"'.$prsn_val.'":"'.$this->dataArray->$prsn_key.'",';
					}
					$prsn_fld_str =rtrim($prsn_fld_str,',');
				$prsn_fld_str .='}';
				$this->dataArray->person_flds_show = base64_encode($prsn_fld_str);
			}else{
				$person_flds_file="/var/www/html/CZCRM/person_fields_json.txt";
				if(file_exists($person_flds_file)){
					$person_fld_json        =       file_get_contents($person_flds_file);
					$person_fld_json_arry           =       json_decode($person_fld_json,true);
					$prsn_fld_str='{';
					foreach($person_fld_json_arry as $prsn_key => $prsn_val){
						$prsn_fld_str .='"'.$prsn_val.'":"'.$this->dataArray->$prsn_key.'",';
					}
					$prsn_fld_str =rtrim($prsn_fld_str,',');
					$prsn_fld_str .='}';
					$this->dataArray->person_flds_show = base64_encode($prsn_fld_str);
				}
			}
			//////////////////////////////////////////////
			//~ Parameters required for both add person api(reqAddPerson) and create ticket api(reqCreateTicket)
			$person_id=isset($this->dataArray->person_id)?trim($this->dataArray->person_id):0;
			$person_name = '';
			if(isset($this->dataArray->first_name) && isset($this->dataArray->last_name)){
				$person_name = trim($this->dataArray->first_name." ".$this->dataArray->last_name);
			}
			else{
				$person_name=isset($this->dataArray->person_name)?trim($this->dataArray->person_name):'';
			}
			$cuid=isset($this->dataArray->cuid)?trim($this->dataArray->cuid):'0';
			$parentapp = isset($this->dataArray->parentapp)?trim($this->dataArray->parentapp):"";
			$moduleName = $this->dataArray->module_name_ajax;
			$moduleName1= $this->dataArray->module_name_ajax.'_'.$this->client_id;
			$FLP->prepare_log("1","======module name ajax==============",$moduleName1);
			$error_hashArray        =       api_ValidationFunction($moduleName1);
			$error_flag             =       0;
			$error_flag             =       ShowErrorDiv($error_hashArray);
			
			$errors = explode("#TVT#",$error_flag);
			if($errors[0]!=0){
				$FLP->prepare_log("1","======DB UTILITY==============","error found");
				$FLP->prepare_log("1","======DB UTILITY==============",$errors[1]);
				return $this->result='{"Error":"'.$errors[1].'","statusCode":422}';
			}
			else{
				$FLP->prepare_log("1","======DB UTILITY==============","error not found ");

				if(!empty($parentapp) && ($parentapp == 1) && !empty($cuid)){

					$FLP->prepare_log("1","======person exist==============","person exist");

					$queryCheck="select person_id from person_info where cuid=".$cuid;
					$result_check=$this->EXECUTE_QUERY($queryCheck,$this->DB_H);
					$row_check=$this->FETCH_ARRAY($result_check,MYSQLI_ASSOC);
					$person_id=$row_check["person_id"];
				}
				else{
					//~ Add person if doesnt exists or update the existing person with the updated paramets found
					$FLP->prepare_log("1","======person add==============","person add");
				
					if(empty($person_id)){
						$person_info_final=$this->addPerson();
						$person_info_final_json_decode = json_decode($person_info_final, true);
						$add_person_status = (isset($person_info_final_json_decode['status']) && !empty($person_info_final_json_decode['status']))?$person_info_final_json_decode['status']:'error';
						
						if($add_person_status == 'success'){
							$person_id = (isset($person_info_final_json_decode['data']['person_id']) && !empty($person_info_final_json_decode['data']['person_id']))?trim($person_info_final_json_decode['data']['person_id']):0;
							$person_name = (isset($person_info_final_json_decode['data']['person_name']) && !empty($person_info_final_json_decode['data']['person_name']))?trim($person_info_final_json_decode['data']['person_name']):'';
						}else{
							return $this->result='{"Error":"Unable to create Lead","statusCode":500}';
						}
					}else{
						$this->dataArray->no_cust_fields = "no";
						$this->dataArray->from_create_ticket = "true";
						$person_info_final=$this->updatePerson();
						$person_info_final_json_decode = json_decode($person_info_final, true);
						$update_person_status = (isset($person_info_final_json_decode['status']) && !empty($person_info_final_json_decode['status']))?$person_info_final_json_decode['status']:'error';
						
						if($update_person_status == 'error'){
							return $this->result='{"Error":"Unable to create Lead","statusCode":500}';
						}
					}
				}
				
				//~ If person added/updated successfully, create ticket
				if(isset($person_id) && !empty($person_id)){
					$lead_deprt_id = isset($this->dataArray->assigned_to_dept_id)?$this->dataArray->assigned_to_dept_id:'';
					$lead_user_id = isset($this->dataArray->assigned_to_user_id)?$this->dataArray->assigned_to_user_id:'';
					$this->dataArray->assigned_to_dept_id=$lead_deprt_id;
					$this->dataArray->assigned_to_user_id=$lead_user_id;
					
					$this->dataArray->person_id = $person_id;
					$this->dataArray->person_name = $person_name;
					
					//~ Calculate SLA time for ticket - START
					$query_sub_dis = "select time_type, sla_time from sub_disposition_tab where source_type='LEAD' and id=".$this->dataArray->sub_disposition_id;
					$tName_sub_dis = $this->EXECUTE_QUERY($query_sub_dis,$this->DB_H);
					$fetch_sub_dis = $this->FETCH_ARRAY($tName_sub_dis,MYSQLI_ASSOC);
					$time_type = isset($fetch_sub_dis['time_type'])?$fetch_sub_dis['time_type']:'';
					$sla_time = isset($fetch_sub_dis['sla_time'])?$fetch_sub_dis['sla_time']:'';
					
					$this->dataArray->sla_time = '';
					if(!empty($time_type) && !empty($sla_time)){
						require_once("../classes/escalationHandler.class.php");
						$EH = new escalationHandler($this->client_id);
						
						//~ Fetching time plan
						$query_time_plan = "select plan_id from time_plan";
						$exe_time_plan = $this->EXECUTE_QUERY($query_time_plan,$this->DB_H);
						$fetch_time_plan = $this->FETCH_ARRAY($exe_time_plan,MYSQLI_ASSOC);
						
						$time_plan = isset($fetch_time_plan['plan_id'])?$fetch_time_plan['plan_id']:'';
						//~ Fetching holiday array
						$rs_holiday_arr = array();
						
						$tableName      =       "holidays";
						$fields         =       array (
						"holiday_date"
						);
						$where          =       array();
						$others         =       " and holiday_date between date(now()) and date_add(date(now()),INTERVAL 8 MONTH)";
						
						if($rs_date=$this->SELECT($tableName,$fields,$where,$others,$this->DB_H))
						{
							while($rs_holiday_obj=$this->FETCH_OBJECT($rs_date))
							{
								$rs_holiday_arr[$rs_holiday_obj->holiday_date]=1;
							}
						}
						
						$created_on = date('Y-m-d H:i:s');
						$scheculed_time = $EH->fn_escalation_calculate_hours($sla_time,$time_type,$created_on,$time_plan,$rs_holiday_arr);
						$this->dataArray->sla_time = $scheculed_time;
					}
					//~ Calculate SLA time for ticket - END
					$TH = new leadHandler($this->client_id);
					$ticketJson=json_encode($this->dataArray);
					$returned_data=$TH->createLead($ticketJson);
					$returned_data = explode("#$#",$returned_data);
					$docket_no = $returned_data[0];
					$ticket_id = $returned_data[1];
					
					$clientID = $this->client_id;

					$lead_state_id = (isset($this->dataArray->lead_state_id) && !empty($this->dataArray->lead_state_id))?$this->dataArray->lead_state_id:0;
					$lead_status_id = (isset($this->dataArray->lead_status_id) && !empty($this->dataArray->lead_status_id))?$this->dataArray->lead_status_id:0;
					$disposition_id = (isset($this->dataArray->disposition_id) && !empty($this->dataArray->disposition_id))?$this->dataArray->disposition_id:0;
					$lead_source_id = (isset($this->dataArray->lead_source) && !empty($this->dataArray->lead_source))?$this->dataArray->lead_source:0;

					$time = time();

					//~ Escalation Code - START
					require_once("../classes/escalationHandler.class.php");
					$EH = new escalationHandler($this->client_id);
					//~ Getting escalation ip
					$configFileContent = file_get_contents("/var/www/html/CZCRM/configs/config.txt");
					$configFileArr = json_decode($configFileContent,true);
					$escalation_ip = isset($configFileArr["ESCALATION_IP"])?$configFileArr["ESCALATION_IP"]:"";
					$executed_rules = $EH->fn_executed_rules($ticket_id,'L');
					$URL = "http://".$escalation_ip."/checkApplicable?ticketId=$ticket_id&executedRules=".$executed_rules."&type=L&db=".$this->client_id."";
					$result=do_remote_without_json($URL,"");
					//~ Escalation Code - END
					
					//      $query_fetch_details="select source,assigned_to_user_id,agent_remarks,docket_no,lead_source,a.created_on,time(a.created_on) as creation_time,a.modified_on,time_format(timediff(NOW(),a.created_on),'%Hh %im') as time_elapsed,last_escalated_on,assigned_time,product_type,product_subtype,disposition_name,assigned_to_user_id,assigned_to_dept_id,sub_disposition_name,company_name,assigned_to_dept_name,assigned_to_user_name,lead_status,lead_status_id,lead_state,lead_state_id from lead_details as a where lead_id=$ticket_id";
					$query_fetch_details="select source,assigned_to_user_id,agent_remarks,docket_no,lead_source,a.created_on,time(a.created_on) as creation_time,a.modified_on,time_format(timediff(NOW(),a.created_on),'%Hh %im') as time_elapsed,last_escalated_on,assigned_time,product_type,product_subtype,disposition_name,assigned_to_user_id,assigned_to_dept_id,sub_disposition_name,assigned_to_dept_name,assigned_to_user_name,lead_status,lead_status_id,lead_state,lead_state_id from lead_details as a where lead_id=$ticket_id";
					
					$result_fetch_details = $this->EXECUTE_QUERY($query_fetch_details,$this->DB_H);
					$row_fetch_details = $this->FETCH_ARRAY($result_fetch_details,MYSQLI_ASSOC);
					$lead_status_id = $row_fetch_details['lead_status_id'];
					$short_docket_number = substr($docket_no,8);
					
					$searchString=array(
					"%LEAD_NUMBER%",
					"%PERSON_NAME%",
					//"%SUBJECT%",
					"%LEAD_STATUS%",
					//"%PROBLEM_REPORTED%",
					"%AGENT_REMARKS%",
					//"%ACTION_TAKEN%",
					//"%PRIORITY%",
					"%ASSIGNED_USER%",
					"%ASSIGNED_DEPT%",
					"%CREATION_TIME%",
					"%CREATION_DATE_TIME%",
					"%LAST_UPDATION_TIME%",
					//"%COMPANY_NAME%",
					"%LEAD_SHORT%",
					//"%TICKET_TYPE%",
					"%DISPOSITION%",
					"%SUB_DISPOSITION%",
					"%TIME_ELAPSED%",
					"%LAST_ESCALATION_TIME%",
					"%ASSIGNMENT_TIME%",
					"%PRODUCT_TYPE%",
					"%PRODUCT_SUB_TYPE%",
					"%LEAD_STATE%"
					);
					$replaceString=array(
					$docket_no,
					$person_name,
					$row_fetch_details['lead_status'],
					//$row_fetch_details['problem_reported'],
					$row_fetch_details['agent_remarks'],
					//$row_fetch_details['action_taken'],
					//$row_fetch_details['priority_name'],
					$row_fetch_details['assigned_to_user_name'],
					$row_fetch_details['assigned_to_dept_name'],
					$row_fetch_details['creation_time'],
					$row_fetch_details['created_on'],
					$row_fetch_details['modified_on'],
					//$row_fetch_details['company_name'],
					$short_docket_number,
					//$row_fetch_details['ticket_type'],
					$row_fetch_details['disposition_name'],
					$row_fetch_details['sub_disposition_name'],
					$row_fetch_details['time_elapsed'],
					$row_fetch_details['last_escalated_on'],
					$row_fetch_details['assigned_time'],
					$row_fetch_details['product_type'],
					$row_fetch_details['product_subtype'],
					$row_fetch_details['lead_state'],
					);
					
					$person_mail =  isset($this->dataArray->person_mail)?$this->dataArray->person_mail:"";
					$mobile_no = isset($this->dataArray->mobile_no)?$this->dataArray->mobile_no:"";
					$assigned_to_user_id = isset($this->dataArray->assigned_to_user_id)?$this->dataArray->assigned_to_user_id:"";
					$assigned_to_dept_id = isset($this->dataArray->assigned_to_dept_id)?$this->dataArray->assigned_to_dept_id:"";
					$created_by = isset($this->dataArray->created_by)?$this->dataArray->created_by:"";
					$created_by_id = isset($this->dataArray->created_by_id)?$this->dataArray->created_by_id:"";
					$client_folder = $this->dataArray->CLIENT_FOLDER;
					$user_mail = $user_phone = "";
					if(!empty($assigned_to_dept_id)){
						$tNameDept      =       "departments";
						$farrdept               =               array("dept_email","dept_phone","dept_name");
						$whdept = array (
						"dept_id" => array (STRING, $assigned_to_dept_id)
						);
						$tNameDept = $this->SELECT($tNameDept,$farrdept,$whdept,_BLANK_,$this->DB_H);
						$tNameDept = $this->FETCH_ARRAY ($tNameDept, MYSQLI_ASSOC);
						$dept_mail = $tNameDept["dept_email"];
						$dept_phone = $tNameDept["dept_phone"];
						$deptaneme_timeline = "Department ".$tNameDept["dept_name"];
						if(empty($assigned_to_user_id)){
							//code for timeline history start//
							$datetime       =       time();
							$datajson       =       '{"action":"lead_assigned","date_time":"'.$datetime.'","action_by":"'.$created_by.'","action_to":"'.$deptaneme_timeline.'","lead_id":"'.$ticket_id.'","docket_no":"'.$docket_no.'","client_folder":"'.$client_folder.'","type":"LEAD","client_id":"'.$clientID.'"}';
							timeline_history($datajson);
							
							//code for timeline history end//
						}
					}
					if(!empty($assigned_to_user_id)){
						$tNameUser      =       "users";
						$farruser               =               array("email1","phone_mobile","user_name");
						$whuser = array (
						"user_id" => array (STRING, $assigned_to_user_id)
						);
						$tNameUser = $this->SELECT($tNameUser,$farruser,$whuser,_BLANK_,$this->DB_H);
						$tNameUser = $this->FETCH_ARRAY ($tNameUser, MYSQLI_ASSOC);
						$user_mail = $tNameUser["email1"];
						$user_phone = $tNameUser["phone_mobile"];
						$assigneduserName = $tNameUser["user_name"];
						//code for timeline history start//
						$datetime       =       time();
						$datajson       =       '{"action":"lead_assigned","date_time":"'.$datetime.'","action_by":"'.$created_by.'","action_to":"'.$assigneduserName.'('.$tNameDept["dept_name"].')","lead_id":"'.$ticket_id.'","docket_no":"'.$docket_no.'","client_folder":"'.$client_folder.'","type":"LEAD","client_id":"'.$clientID.'"}';
						timeline_history($datajson);
						
						//code for timeline history end//
						
					}
					
					
					$filename       =       array();
					
					//~ Fetching parameters for mail and SMS - END
					
					//~ Send mail to person if person's mail is found
					if(!empty($person_mail))
					{
						$tName="mail_services as a left join mail_event as b on a.event=b.id left join mail_rule_conf as c on a.template_id=c.conf_id";
						$mail_status= "";
						if($lead_status_id=='1'){
							$mail_status="Lead Open(Caller)";
							}else if($lead_status_id=='2'){
							$mail_status="Lead Closed";
						}
						$w = array("ticket_status"=>array(STRING,$mail_status));
						$f = array("server","subject","from_address","message","signature","salutation","cc_address","bcc_address","conf_id");
						
						//~ $tName = $this->SELECT($tName,$f,$w," and c.status=1",$this->DB_H);
						//~ $mail_rule_found = $this->GET_ROWS_COUNT($tName);
						//code by noumya for department wise template
						$tName1 = $this->SELECT($tName,$f,$w," and dept_id=".$assigned_to_dept_id." and c.status=1",$this->DB_H);
						$mail_rule_found = $this->GET_ROWS_COUNT($tName1);
						if(!$mail_rule_found){
							$tName1 = $this->SELECT($tName,$f,$w," and default_dept=true and c.status=1",$this->DB_H);
							$mail_rule_found = $this->GET_ROWS_COUNT($tName1);
						}
						//end code by noumya for department wise template
						$f = $this->FETCH_ARRAY($tName1,MYSQLI_ASSOC);
						$mail = $person_mail;
						$msg = $f["message"];
						$subject = $f["subject"];
						$conf_id = $f["conf_id"];
						$mail_mesg=$f["salutation"]."<br><br>".$f["message"]."<br><br><br>".$f["signature"];
						$subject=str_replace($searchString,$replaceString,$subject);
						$mail_msg=str_replace($searchString,$replaceString,$mail_mesg);
						if($mail_rule_found)
						{
							require_once("../classes/emailHandler.class.php");
							$EM_H = new emailHandler($this->client_id);
							$EM_H->createMail($subject,$msg,$f["from_address"],$f["bcc_address"],$f["cc_address"],$f["server"],$mail_msg,$mail,'AUTO REPLY',$ticket_id,$filename,$conf_id,$docket_no,'','1','','','','L',$created_by,$created_by_id);
						}
					}
					//~ Send SMS to person if person's number is found
					if(!empty($mobile_no))
					{
						//~ Send whatspp message - START
						$tName_wht = "whatsapp_services as a left join mail_event as b on a.event=b.id left join whatsapp_rule_conf as c on a.template_id=c.conf_id";
						$mail_status= "";
						if($lead_status_id=='1'){
							$mail_status="Lead Open(Caller)";
							}else if($lead_status_id=='2'){
							$mail_status="Lead Closed";
						}
						$w_wht = array("ticket_status"=>array(STRING,$mail_status));
						$f_wht = array("message","to_address","server");
						$tName_wht_details=$this->SELECT($tName_wht,$f_wht,$w_wht," and dept_id=".$assigned_to_dept_id,$this->DB_H);
						$wht_rule_found = $this->GET_ROWS_COUNT($tName_wht_details);
						$fetch_wht = $this->FETCH_ARRAY($tName_wht_details,MYSQLI_ASSOC);
						
						if(!$wht_rule_found){
							$tName_wht_details=$this->SELECT($tName_wht,$f_wht,$w_wht," and default_dept=true",$this->DB_H);
							$wht_rule_found = $this->GET_ROWS_COUNT($tName_wht_details);
						}
						
						if($wht_rule_found)
						{
							$wht_msg=str_replace($searchString,$replaceString,$fetch_wht["message"]);
							
							require_once("/var/www/html/CZCRM/classes/whatsAppHandler.class.php");
							$W_H = new whatsAppHandler($this->client_id);
							$type = 'L';
							$waArr = array(
							"whatsapp_account_id" => $fetch_wht['server'],
							"message" => $wht_msg,
							"mobile_no" => $mobile_no,
							"ticket_id" => $ticket_id,
							"ticket_status" => $mail_status,
							"type" => $type,
							"created_by" => $created_by,
							"created_by_id" => $created_by_id,
							);
							
							$W_H->sendWAMessage($waArr);
						}
						//~ Send whatspp message - END
						
						$tName_sms="sms_services as a left join mail_event as b on a.event=b.id left join sms_rule_conf as c on a.template_id=c.conf_id";
						$mail_status= "";
						if($lead_status_id=='1'){
							$mail_status="Lead Open(Caller)";
							}else if($lead_status_id=='2'){
							$mail_status="Lead Closed";
						}
						$w_sms=array("ticket_status"=>array(STRING,$mail_status));
						$f_sms=array("message","to_address","server");
						$tName_sms_details=$this->SELECT($tName_sms,$f_sms,$w_sms," and c.status=1 ",$this->DB_H);
						$sms_rule_found = $this->GET_ROWS_COUNT($tName_sms_details);
						$fetch_sms=$this->FETCH_ARRAY($tName_sms_details,MYSQLI_ASSOC);
						
						if($sms_rule_found)
						{
							$msg=str_replace($searchString,$replaceString,$fetch_sms["message"]);
							$type = 'L';
							$smsArray = array(
							"sms_gateway_id" => $fetch_sms["server"],
							"message" => $msg,
							"mobile_no" => $mobile_no,
							"ticket_id" => $ticket_id,
							"ticket_status" => $mail_status,
							"auto_reply" => 1,
							"type" => $type,
							"created_by" => $created_by,
							"created_by_id" => $created_by_id,
							);
							
							$S_H = new smsHandler($this->client_id);
							$S_H->sendSMS($smsArray);
						}
					}
					
					//~ Send SMS to person if person's number is found
					//~ Send mail to user if user's mail is found
					
					if(!empty($user_mail)){
						$tName="mail_services as a left join mail_event as b on a.event=b.id left join mail_rule_conf as c on a.template_id=c.conf_id";
						$w = array("ticket_status"=>array(STRING,'Lead Assigned'));
						$f = array("server","subject","from_address","message","signature","salutation","cc_address","bcc_address","conf_id");
						//~ $tName = $this->SELECT($tName,$f,$w," and c.status=1",$this->DB_H);
						//~ $mail_rule_found = $this->GET_ROWS_COUNT($tName);
						//code by noumya for department wise template
						$tName1 = $this->SELECT($tName,$f,$w," and dept_id=".$assigned_to_dept_id." and c.status=1",$this->DB_H);
						$mail_rule_found = $this->GET_ROWS_COUNT($tName1);
						if(!$mail_rule_found){
							$tName1 = $this->SELECT($tName,$f,$w," and default_dept=true and c.status=1",$this->DB_H);
							$mail_rule_found = $this->GET_ROWS_COUNT($tName1);
						}
						//end code by noumya for department wise template
						$f=$this->FETCH_ARRAY($tName1,MYSQLI_ASSOC);
						$mail = $user_mail;
						$msg=$f["message"];
						$subject = $f["subject"];
						$conf_id = $f["conf_id"];
						$mail_mesg=$f["salutation"]."<br><br>".$f["message"]."<br><br><br>".$f["signature"];
						$subject=str_replace($searchString,$replaceString,$subject);
						$mail_msg=str_replace($searchString,$replaceString,$mail_mesg);
						if($mail_rule_found)
						{
							require_once("../classes/emailHandler.class.php");
							$EM_H = new emailHandler($this->client_id);
							$EM_H->createMail($subject,$msg,$f["from_address"],$f["bcc_address"],$f["cc_address"],$f["server"],$mail_msg,$mail,'AUTO REPLY',$ticket_id,$filename,$conf_id,$docket_no,'','1','','','','L',$created_by,$created_by_id);
						}
					}
					
					//Send mail to department
					if(!empty($dept_mail)){
						$tName="mail_services as a left join mail_event as b on a.event=b.id left join mail_rule_conf as c on a.template_id=c.conf_id";
						$w = array("ticket_status"=>array(STRING,'Department Lead Assigned'));
						$f = array("server","subject","from_address","message","signature","salutation","cc_address","bcc_address","conf_id");
						//~ $tName = $this->SELECT($tName,$f,$w," and c.status=1",$this->DB_H);
						//~ $mail_rule_found = $this->GET_ROWS_COUNT($tName);
						//code by noumya for department wise template
						$tName1 = $this->SELECT($tName,$f,$w," and dept_id=".$assigned_to_dept_id." and c.status=1",$this->DB_H);
						$mail_rule_found = $this->GET_ROWS_COUNT($tName1);
						if(!$mail_rule_found){
							$tName1 = $this->SELECT($tName,$f,$w," and default_dept=true and c.status=1",$this->DB_H);
							$mail_rule_found = $this->GET_ROWS_COUNT($tName1);
						}
						//end code by noumya for department wise template
						$f=$this->FETCH_ARRAY($tName1,MYSQLI_ASSOC);
						$mail = $dept_mail;
						$msg=$f["message"];
						$subject = $f["subject"];
						$conf_id = $f["conf_id"];
						$mail_mesg=$f["salutation"]."<br><br>".$f["message"]."<br><br><br>".$f["signature"];
						$subject=str_replace($searchString,$replaceString,$subject);
						$mail_msg=str_replace($searchString,$replaceString,$mail_mesg);
						if($mail_rule_found)
						{
							require_once("../classes/emailHandler.class.php");
							$EM_H = new emailHandler($this->client_id);
							$EM_H->createMail($subject,$msg,$f["from_address"],$f["bcc_address"],$f["cc_address"],$f["server"],$mail_msg,$mail,'AUTO REPLY',$ticket_id,$filename,$conf_id,$docket_no,'','1','','','','T',$created_by,$created_by_id);
						}
					}
					
					//~ Send SMS to person if user's number is found
					if(!empty($user_phone))
					{
						//~ Send whatspp message - START
						$tName_wht = "whatsapp_services as a left join mail_event as b on a.event=b.id left join whatsapp_rule_conf as c on a.template_id=c.conf_id";
						$mail_status = "Lead Assigned";
						$w_wht = array("ticket_status"=>array(STRING,$mail_status));
						$f_wht = array("message","to_address","server");
						$tName_wht_details=$this->SELECT($tName_wht,$f_wht,$w_wht," and dept_id=".$assigned_to_dept_id,$this->DB_H);
						$wht_rule_found = $this->GET_ROWS_COUNT($tName_wht_details);
						$fetch_wht = $this->FETCH_ARRAY($tName_wht_details,MYSQLI_ASSOC);
						
						if(!$wht_rule_found){
							$tName_wht_details=$this->SELECT($tName_wht,$f_wht,$w_wht," and default_dept=true",$this->DB_H);
							$wht_rule_found = $this->GET_ROWS_COUNT($tName_wht_details);
						}
						
						if($wht_rule_found)
						{
							$wht_msg=str_replace($searchString,$replaceString,$fetch_wht["message"]);
							
							require_once("/var/www/html/CZCRM/classes/whatsAppHandler.class.php");
							$W_H = new whatsAppHandler($this->client_id);
							
							$type = 'L';
							$waArr = array(
							"whatsapp_account_id" => $fetch_wht['server'],
							"message" => $wht_msg,
							"mobile_no" => $user_phone,
							"ticket_id" => $ticket_id,
							"ticket_status" => $mail_status,
							"type" => $type,
							"created_by" => $created_by,
							"created_by_id" => $created_by_id,
							);
							
							$W_H->sendWAMessage($waArr);
						}
						//~ Send whatspp message - END
						
						$tName_sms="sms_services as a left join mail_event as b on a.event=b.id left join sms_rule_conf as c on a.template_id=c.conf_id";
						$mail_status = "Lead Assigned";
						$w_sms = array("ticket_status"=>array(STRING,$mail_status));
						$f_sms = array("message","to_address","server");
						$tName_sms_details=$this->SELECT($tName_sms,$f_sms,$w_sms," and c.status=1 ",$this->DB_H);
						$sms_rule_found = $this->GET_ROWS_COUNT($tName_sms_details);
						$fetch_sms=$this->FETCH_ARRAY($tName_sms_details,MYSQLI_ASSOC);
						
						if($sms_rule_found)
						{
							$msg=str_replace($searchString,$replaceString,$fetch_sms["message"]);
							$type = 'L';
							$smsArray = array(
							"sms_gateway_id" => $fetch_sms["server"],
							"message" => $msg,
							"mobile_no" => $user_phone,
							"ticket_id" => $ticket_id,
							"ticket_status" => $mail_status,
							"auto_reply" => 1,
							"type" => $type,
							"created_by" => $created_by,
							"created_by_id" => $created_by_id,
							);
							
							$S_H = new smsHandler($this->client_id);
							$S_H->sendSMS($smsArray);
						}
					}
					
					//~ Send SMS to department if department's number is found
					if(!empty($dept_phone))
					{
						//~ Send whatspp message - START
						$tName_wht = "whatsapp_services as a left join mail_event as b on a.event=b.id left join whatsapp_rule_conf as c on a.template_id=c.conf_id";
						$mail_status = "Department Lead Assigned";
						$w_wht = array("ticket_status"=>array(STRING,$mail_status));
						$f_wht = array("message","to_address","server");						
						$tName_wht_details=$this->SELECT($tName_wht,$f_wht,$w_wht," and dept_id=".$assigned_to_dept_id,$this->DB_H);
						$wht_rule_found = $this->GET_ROWS_COUNT($tName_wht_details);
						$fetch_wht = $this->FETCH_ARRAY($tName_wht_details,MYSQLI_ASSOC);
						
						if(!$wht_rule_found){
							$tName_wht_details=$this->SELECT($tName_wht,$f_wht,$w_wht," and default_dept=true",$this->DB_H);
							$wht_rule_found = $this->GET_ROWS_COUNT($tName_wht_details);
						}
						
						if($wht_rule_found)
						{
							$wht_msg=str_replace($searchString,$replaceString,$fetch_wht["message"]);
							
							require_once("/var/www/html/CZCRM/classes/whatsAppHandler.class.php");
							$W_H = new whatsAppHandler($this->client_id);
							$type = 'T';
							$waArr = array(
							"whatsapp_account_id" => $fetch_wht['server'],
							"message" => $wht_msg,
							"mobile_no" => $dept_phone,
							"ticket_id" => $ticket_id,
							"ticket_status" => $mail_status,
							"type" => $type,
							"created_by" => $created_by,
							"created_by_id" => $created_by_id,
							);
							
							$W_H->sendWAMessage($waArr);
						}
						//~ Send whatspp message - END
						
						$tName_sms="sms_services as a left join mail_event as b on a.event=b.id left join sms_rule_conf as c on a.template_id=c.conf_id";
						$mail_status = "Department Lead Assigned";
						$w_sms = array("ticket_status"=>array(STRING,$mail_status));
						$f_sms = array("message","to_address","server");
						$tName_sms_details=$this->SELECT($tName_sms,$f_sms,$w_sms," and c.status=1 ",$this->DB_H);
						$FLP->prepare_log("1","======tName_sms_details===========", $this-> getLastQuery());
						$sms_rule_found = $this->GET_ROWS_COUNT($tName_sms_details);
						$fetch_sms=$this->FETCH_ARRAY($tName_sms_details,MYSQLI_ASSOC);
						if($sms_rule_found)
						{
							$msg=str_replace($searchString,$replaceString,$fetch_sms["message"]);
							$type = 'T';
							$smsArray = array(
							"sms_gateway_id" => $fetch_sms["server"],
							"message" => $msg,
							"mobile_no" => $dept_phone,
							"ticket_id" => $ticket_id,
							"ticket_status" => $mail_status,
							"auto_reply" => 1,
							"type" => $type,
							"created_by" => $created_by,
							"created_by_id" => $created_by_id,
							);
							
							$S_H = new smsHandler($this->client_id);
							$S_H->sendSMS($smsArray);
						}
					}
					// PUSH DATA IN REDIS FOR PERFORMANCE REPORT 
					if($ticket_status_id!=2 && !empty($assigned_to_user_id)){
						$report_date = date("Y-m-d");
						$report_dateUnix = strtotime(date("Y-m-d h:i:s"));
						$agentID 		= $assigned_to_user_id;
						$deptID 		= $assigned_to_dept_id;
						$query_get_dept_id 	= "SELECT dept_name from departments where dept_id ='".$deptID."'";
						$tName_get_dept_id 	= $this->EXECUTE_QUERY($query_get_dept_id,$this->DB_H);
						$dept_data 			= $this->FETCH_ARRAY($tName_get_dept_id,MYSQLI_ASSOC);
						$deptName 			= $dept_data['dept_name'];

						$query_get_user_id 	= "SELECT user_name,parent_user_id from users where user_id = '".$agentID."'";
						$exeQueryUsers 		= $this->EXECUTE_QUERY($query_get_user_id,$this->DB_H);
						$get_user_data 		= $this->FETCH_ARRAY($exeQueryUsers,MYSQLI_ASSOC);	
						$teamLeadID 		= isset($get_user_data['parent_user_id'])?$get_user_data['parent_user_id']:'';
						$agentName 			= isset($get_user_data['user_name'])?$get_user_data['user_name']:'';

						if(!empty($teamLeadID)){
							$query_get_parent_user_id 	= "SELECT user_name as team_leader,parent_user_id as manager_id from users where parent_user_id = '".$teamLeadID."'";
							$exeQueryParentUsers 		= $this->EXECUTE_QUERY($query_get_parent_user_id,$this->DB_H);
							$get_parent_data 			= $this->FETCH_ARRAY($exeQueryParentUsers,MYSQLI_ASSOC);	
							$team_lead_name 			= isset($get_parent_data['team_leader'])?$get_parent_data['team_leader']:'';
							$managerID 					= isset($get_parent_data['manager_id'])?$get_parent_data['manager_id']:"";
						}
						if(!empty($managerID)){
							$query_get_manager 	= "SELECT user_name as manager_name from users where user_id = '".$managerID."'";
							$exeQueryManager 	= $this->EXECUTE_QUERY($query_get_manager,$this->DB_H);
							$get_manager_data 	= $this->FETCH_ARRAY($exeQueryManager,MYSQLI_ASSOC);	
							$managerName   		= isset($get_manager_data['manager_name'])?$get_manager_data['manager_name']:'';
						}
						$dataArr_packet=array("team_lead_name"=>$team_lead_name,"team_lead_id"=>$teamLeadID,"agent_name"=>$agentName,"agent_id"=>$agentID,"dept_name"=>$deptName,"dept_id"=>$deptID,"manager_name"=>$managerName,"manager_id"=>$managerID,"incoming_task"=>"1","processed_task"=>"0","type"=>"ticket","unix_date"=>$report_dateUnix,"date"=>$report_date);
						$json_data = json_encode($dataArr_packet);
						pushPerformanceData($json_data);
					}
					//~ Notification on ticket creation - START
					//~ Pass client_id later on in constructor when notification starts working for multiple clients
					$FLP->prepare_log("1","======notification===========","notifiy");
					$notificationObj = new notification('',$this->client_id);
					if(!empty($row_fetch_details['assigned_to_user_name']))
					{
						$notification_message = "New Lead \n\rLead Number ".$docket_no." created by ".$created_by." and assigned to ".$row_fetch_details['assigned_to_user_name'].".";
						$FLP->prepare_log("1","======notification if===========",$notification_message);

					}
					else
					{
						$notification_message = "New Lead \n\rLead Number ".$docket_no." created by ".$created_by;
						$FLP->prepare_log("1","======notification else===========",$notification_message);

					}
					$notification_event = "LEAD CREATE";
					$flag = "";
					$notification_type = "New Lead ";
					$notificationObj->saveCreateTicketNotification($notification_event,$notification_message,$created_by,$ticket_id,$flag,$notification_type,'L');
					
					return $this->result='{"Success":"Lead created successfully","docket_no":"'.$docket_no.'","person_id":"'.$person_id.'","statusCode":200}';
				}
				else{
					return $this->result='{"Error":"Unable to create Lead","statusCode":422}';
				}
			}
		}
		private function searchLead(){
			$FLP = new logs_creation($this->client_id);
			$FLP->prepare_log("1","======DB UTILITY==============","SearchLead");
			$ticketJson=json_encode($this->dataArray);
			$docket_no = isset($this->dataArray->docket_no)?$this->dataArray->docket_no:"";
			if(empty($docket_no)){
				return $this->result='{"Error":"Please enter lead number."}';
			}
			else{
				if(isset($docket_no)){
					//~ $search_ticket = str_replace ("*", "%", $this->MYSQLI_REAL_ESCAPE($this->DB_H,$search_ticket));
					$TH = new leadHandler($this->client_id);
					$returned_data=$TH->searchLead($ticketJson);
				}
				if(isset($returned_data['Error'])){
					return $this->result='{"Error":"'.$returned_data['Error'].'"}';
					}else{
					return $this->result='{"Success":'.$returned_data.'}';
				}
			}
		}
		private function recentLead(){
			$FLP = new logs_creation($this->client_id);
			$FLP->prepare_log("1","======DB UTILITY==============","createLead");
			$person_id=$this->dataArray->person_id;
			if(isset ($person_id) && !empty($person_id)){
				$TH = new leadHandler($this->client_id);
				$ticketJson=json_encode($this->dataArray);
				$returned_data=$TH->recentLead($ticketJson);
				if(isset($returned_data['Error'])){
					return $this->result='{"Error":"'.$returned_data['Error'].'"}';
					}else{
					return $this->result='{"Success":'.$returned_data.'}';
				}
			}
			else{
				return $this->result='{"Error":"No person selected for recent lead"}';
			}
		}
		
		
		private function callConnect(){
			$FLP = new logs_creation($this->client_id);
			$FLP->prepare_log("1","======DB UTILITY==============","callconnect");
			require_once("../classes/reportHandler.class.php");
			$jsonArray=array("data"=>$this->dataArray,"event"=>"callConnect","type"=>"CDR");
			$jsonObj=json_encode($jsonArray);
			$reporter=new reportHandler($this->client_id);
			return $this->result =$reporter->setReport($jsonObj);

		}
		
		private function callDisconnect(){
			$FLP = new logs_creation($this->client_id);
			$FLP->prepare_log("1","======DB UTILITY==============","callDisconnect");
			require_once("../classes/reportHandler.class.php");
			$cond=array();
			// $cond=["session_id"=>$this->dataArray->session_id];
			$cond=array("session_id"=>$this->dataArray->session_id);
			$jsonArray=array("data"=>$this->dataArray,"cond"=>$cond,"type"=>"CDR","event"=>"callDisconnect");
			$jsonObj=json_encode($jsonArray);
			$reporter=new reportHandler($this->client_id);
			return $this->result=$reporter->setReport($jsonObj);
		}
		
		private function linkPerson(){
			$FLP = new logs_creation($this->client_id);
			$FLP->prepare_log("1","======DB UTILITY==============","linkPerson");
			require_once("../classes/reportHandler.class.php");
			$cond=array();
			// $cond=["session_id"=>$this->dataArray->session_id];
			$cond=array("session_id"=>$this->dataArray->session_id);
			$jsonArray=array("data"=>$this->dataArray,"cond"=>array("session_id"=>$this->dataArray->session_id),"cond"=>$cond,"type"=>"CDR","event"=>"linkPerson");
			$jsonObj=json_encode($jsonArray);
			$reporter=new reportHandler($this->client_id);
			return $this->result=$reporter->setReport($jsonObj);
		}
		
		private function linkTicket(){
			$FLP = new logs_creation($this->client_id);
			$FLP->prepare_log("1","======DB UTILITY==============","linkTivket");
			require_once("../classes/reportHandler.class.php");
			$cond=array();
			// $cond=["session_id"=>$this->dataArray->session_id];
			$cond=array("session_id"=>$this->dataArray->session_id);
			$jsonArray=array("data"=>$this->dataArray,"cond"=>array("session_id"=>$this->dataArray->session_id),"cond"=>$cond,"type"=>"CDR","event"=>"linkTicket");
			$jsonObj=json_encode($jsonArray);
			$reporter=new reportHandler($this->client_id);
			return $this->result=$reporter->setReport($jsonObj);
		}


		//{"id":"432","key":"2227397115179433984","status":"1","type":"sms","curlResponse":"No error","smsResponse":"1707|919717881303","reqType":"updateMailSmsQueue"}
		private function updateMailSmsQueue(){
			$FLP = new logs_creation($this->client_id);
			$FLP->prepare_log("1","======DB UTILITY==============","updateMailSmsQueue");
			$FLP->prepare_log("1","======Data Received==============",$this->dataArray);
			$id=$this->dataArray->id;
			$status=$this->dataArray->status;
			$type=$this->dataArray->type;

			$responseReceived = $this->dataArray->smsResponse;

			$FLP->prepare_log("1","dataArray",$this->dataArray);
			$w1 = '';

			if($status == 0){
				$FLP->prepare_log("1","======status received is=======BOTH=======","===zero===");
				$ack_state = 0;
				$w1 .= ',reschedule_time=UNIX_TIMESTAMP()+retry_interval';
			}else if($status == 1){
				$FLP->prepare_log("1","======status received is==============","===one===");
				if($type=="mail"){
					$FLP->prepare_log("1","======ack_state is======MAIL========","===one===");
					$ack_state = 1;
					$w1 .= ',mail_status="done"';
				}
				else if($type=="sms"){
					$select_query="select success_keyword from sms_list where sms_id=".$id;
					$exe_query = $this->EXECUTE_QUERY($select_query,$this->DB_H);
					$FLP->prepare_log("1","=======check success_keyword========",$this->getLastQuery());
					$fetch_query = $this->FETCH_ARRAY($exe_query, MYSQLI_ASSOC);
					$success_keyword = isset($fetch_query['success_keyword'])?$fetch_query['success_keyword']:'';
					$FLP->prepare_log("1","=======success_keyword is========",$success_keyword);

					//check if received response is json
					$responseReceivedIsJSON = is_string($responseReceived) && is_array(json_decode($responseReceived, true)) && (json_last_error() == JSON_ERROR_NONE) ? true : false;

					$isValid = 0;
					if($responseReceivedIsJSON){
						$FLP->prepare_log("1","=======received response is========","===JSON===");
						$responseReceivedArray = json_decode($responseReceived, true);
						$FLP->prepare_log("1","=======responseReceivedArray is========",$responseReceivedArray);
						foreach($responseReceivedArray as $key=>$value){
							$FLP->prepare_log("1","=======value is========",$value);
							if(strpos($value,$success_keyword) == 0){
								$FLP->prepare_log("1","=======success keyword matched in response=======",$value);
								$isValid = 1;
							}
						}
					}else if(strpos($responseReceived,$success_keyword) !== false){
						$FLP->prepare_log("1","=======success keyword matched in response=======",$responseReceived);
						$isValid = 1;
					}else{
						$FLP->prepare_log("1","=======success keyword is not matched in response=======",$responseReceived);
						$isValid = 0;
					}

					if($isValid){
						$FLP->prepare_log("1","======ack_state is=====SMS=========","===one===");
						$ack_state = 1;
						$w1 .= ',sms_status="done"';
					}else{
						$FLP->prepare_log("1","======ack_state is======SMS=======","===zero===");
						$ack_state = 0;
						$w1 .= ',reschedule_time=UNIX_TIMESTAMP()+retry_interval,status_msg="'.$responseReceived.'"';
					}
				}
			}

			if($type=="sms"){
				$query="update sms_list set ack_state=$ack_state,max_retries=(max_retries+1)".$w1." where sms_id=".$id;
				$this->EXECUTE_QUERY($query,$this->DB_H);
				$FLP->prepare_log("1","======QUERY FOR SMS=======",$this->getLastQuery());
				return $this->result='{"Success":"updated successfully!!"}';
			}
			elseif($type=="mail"){
				$query="update mail_list set ack_state=$status,max_retries=(max_retries+1)".$w1." where mail_id=".$id;
				$this->EXECUTE_QUERY($query,$this->DB_H);
				$FLP->prepare_log("1","======QUERY FOR MAIL=======",$this->getLastQuery());
				return $this->result='{"Success":"updated successfully!!"}';
			}
			else{
				return $this->result='{"Error":"Invalid Type!!"}';
			}
		}
		
		function mergeWithParent(){
			$FLP = new logs_creation($this->client_id);
			$FLP->prepare_log("1","=========coming======","=======mergeWithParent========");
			$logText = date ("Y-m-d h:i:s") .$URL."\r\n";
			require_once("../classes/mailThreadHandler.class.php");
			$TH = new mailThreadHandler($this->client_id);
			$FLP->prepare_log("1","=========mailThreadHandler======","=======mailThreadHandler========");
			$mailArr=$TH->mapwithThread(json_encode($this->dataArray,true));
			$FLP->prepare_log("1","=========mailThreadHandler======",$mailArr);
			return $this->result="{'Success':'Successfully Inserted'}";
		}
		function getMaildata(){
			$FLP = new logs_creation($this->client_id);
			$FLP->prepare_log("1","=========coming======","=======getMaildata========");
			//~ $FLP->prepare_log("1","=========dataArray======",print_r($this->dataArray,true));
			$logText = date ("Y-m-d h:i:s") .$URL."\r\n";
			$FLP->prepare_log("1","=========url is======",$logText);
			$src=$this->src;
			require_once("../classes/mailActionHandler.class.php");
			$TH = new mailActionHandler($this->client_id,'','');
			$flag =isset($this->dataArray->flag)?$this->dataArray->flag:"";
			if(strtoupper($flag)=='TRASH'){
				$table = "mail_thread_trash ";
				}else{
				//~ $table = "mail_thread as a  left join users as f on a.assigned_to_user_id = f.user_id left join departments as g on g.dept_id = a.assigned_to_dept_id";
				$table = "mail_thread ";
			}
			$where =isset($this->dataArray->where)?$this->dataArray->where:"";
			$limit =isset($this->dataArray->limit)?$this->dataArray->limit:"";
			
			$ticket_status_display = "select ticket_status,display_status from ticket_status_display";
			$statusStr      =       "";
			$ticket_status_display = $this->EXECUTE_QUERY($ticket_status_display,$this->DB_H);
			$FLP->prepare_log("1","=========ticket_status_display======",$ticket_status_display);
			while($ticket_status_res = $this->FETCH_ARRAY ($ticket_status_display)){
				$ticket_display_array[$ticket_status_res[1]]=$ticket_status_res[0];
				if($ticket_status_res[0]=='CLOSED'){
					$statusStr.=" a.status='".$ticket_status_res[1]."' OR";
				}
			}
			$statusStr1     =       trim($statusStr,'OR');
			$statusStr      =       "if($statusStr1,'-',TIME_FORMAT(SEC_TO_TIME((unix_timestamp(now()) - unix_timestamp(mail_date))),'%H:%i:%s'))";
			$preSelectField="mail_id,mail_from,mail_to,mail_transaction_id,mail_date,subject,thread_count";
			$dbField="mail_id,mail_from,mail_to,mail_transaction_id,mail_date,subject,thread_count";
			$selectField =isset($this->dataArray->selectField)?$this->dataArray->selectField:$preSelectField;
			$field_name = "mail_id";
			$FLP->prepare_log("1","=========selectField======",$selectField);
			$FLP->prepare_log("1","=========dbField======",$dbField);
			$order = isset($this->dataArray->order)?$this->dataArray->order:"";
			//if(!empty($where) &&)
			$mailArr=$TH->mail_data($table,$where,$limit,$selectField,$dbField,$order);
			//~ $FLP->prepare_log("1","=========mailArr======",print_r($this->result=$mailArr,true));
			return $this->result=$mailArr;
		}
		function getMailThreaddata(){
			$FLP = new logs_creation($this->client_id);
			$FLP->prepare_log("1","=========coming======","=======getMailThreaddata========");
			$mail_id =isset($this->dataArray->mail_id)?$this->dataArray->mail_id:"";
			$sendMailBoxesType =isset($this->dataArray->sendMailBoxesType)?$this->dataArray->sendMailBoxesType:"";
			$user_name =isset($this->dataArray->user_name)?$this->dataArray->user_name:"";
			//~ $FLP->prepare_log("1","=========coming======",print_r($this->dataArray,true));
			require_once("../classes/mailActionHandler.class.php");
			$TH = new mailActionHandler($this->client_id,'','');
			$mailThreadArr=$TH->mail_threaddata($mail_id,$sendMailBoxesType,$user_name);
			return $this->result=$mailThreadArr;
		}
		function getMailThreadattachment(){
			$FLP = new logs_creation($this->client_id);
			$FLP->prepare_log("1","=========coming======","=======getMailThreaddata========");
			$mail_id =isset($this->dataArray->mail_id)?$this->dataArray->mail_id:"";
			$mail_attach = "select * from mail_attachment where mail_id=".$mail_id;
			$mail_attach_query = $this->EXECUTE_QUERY($mail_attach,$this->DB_H);
			if($mail_attach_query){
				$i=0;
				while($mail_attach_array = $this->FETCH_ARRAY ($mail_attach_query,MYSQLI_ASSOC))
				{
					//$this->prepare_log("qselect_field_array==================================s====",$select_field_array);
					foreach($mail_attach_array as $key=>$val)
					{
						//      $this->prepare_log("in foreachlog======================================",print_r($json_array,true));
						
						$json_array[$i][$key]=$mail_attach_array[$key];
					}
					$i++;
					
				}
			}
			else
			{
				$json_array["Failure"]="Failed";
				$FLP->prepare_log("1","json_array log======================================",$json_array);
			}
			//~ $FLP->prepare_log("1","final array return======================================",print_r($json_array,true));
			return base64_encode(json_encode($json_array));
		}
		
		////////////////followup Details/////////////////
		
		function followupDetails(){
			$FLP = new logs_creation($this->client_id);
			require_once ("/var/www/html/CZCRM/modules/DATABASE/MongoClient.class.php");
			$FLP->prepare_log("1","=======row_followeeeeeeeeeeeeeeeeeeeeeeeeee========",print_r($dataArray,true));

			$lead_id = isset($this->dataArray->lead_id)?$this->dataArray->lead_id:"";
			$followup_date = isset($this->dataArray->followup_date)?$this->dataArray->followup_date:"";
			$followup_time_arr= isset($followup_date)?explode(" ", $followup_date):"array()";
			$followup_time  =       "";
			if(count($followup_time_arr)>0){
					$followup_time  =       $followup_time_arr['1'].":00";
					$followup_date  =       $followup_time_arr['0'];
			}
			// $followup_time= isset($this->dataArray->followup_time)?$this->dataArray->followup_time:":00";
			$followup_remarks= str_replace(array("\r","\n"),'<br>',isset($this->dataArray->followup_remarks)?$this->dataArray->followup_remarks:"");
			//~ $followup_remarks= isset($this->dataArray->followup_remarks)?$this->dataArray->followup_remarks:"";
			$user_id = isset($this->dataArray->user_id)?$this->dataArray->user_id:"";
			$assigned_to_id = isset($this->dataArray->assigned_to_id)?$this->dataArray->assigned_to_id:"";
			$user_mail = isset($this->dataArray->user_mail)?$this->dataArray->user_mail:"";
			$user_phone = isset($this->dataArray->user_phone)?$this->dataArray->user_phone:"";

			$self_mail_status = isset($this->dataArray->self_mail_status)?$this->dataArray->self_mail_status:"";
			$self_sms_status = isset($this->dataArray->self_sms_status)?$this->dataArray->self_sms_status:"";
			$cust_mail_status = isset($this->dataArray->cust_mail_status)?$this->dataArray->cust_mail_status:"";
			$cust_sms_status = isset($this->dataArray->cust_sms_status)?$this->dataArray->cust_sms_status:"";
			$self_popup = isset($this->dataArray->self_popup)?$this->dataArray->self_popup:"";
			$cust_popup = isset($this->dataArray->cust_popup)?$this->dataArray->cust_popup:"";
			$followup_check = isset($this->dataArray->followup_check)?$this->dataArray->followup_check:"0";
			$lead_flag = isset($this->dataArray->lead_flag)?$this->dataArray->lead_flag:"0";

			// Session Variables
			$client_folder  =       isset($this->dataArray->client_folder)?$this->dataArray->client_folder:"";
			$agent_name1    =       isset($this->dataArray->agent_name)?$this->dataArray->agent_name:"";

			$date_time = $followup_date ." ".$followup_time;
			$FOLLOWUPDATE       = $followup_date;
			$unix_dateTime = strtotime(date("Y-m-d",strtotime($date_time)));
			$crnt_DT = date("Y-m-d h:i:s");
			$current_time=strtotime($crnt_DT);
			$querydept = "select dept_id from users where user_id=$assigned_to_id";
			$exe_dept =  $this->EXECUTE_QUERY($querydept,$this->DB_H);
			$fetch_dept = $this->FETCH_ARRAY($exe_dept,MYSQLI_ASSOC);
			 $dept_id = $fetch_dept['dept_id'];
			//////////////////activity//////////////
			$client_id = isset($this->dataArray->client_id)?$this->dataArray->client_id:"";
			activityTracker("ADD" ,"Followup Details",$client_id,$agent_name1,$user_id);

			if(empty($lead_id) || empty($followup_date) || empty($followup_time) || empty($followup_remarks) ){
					return $this->result = '{"Error":"Required Parameter missing"}';
			}
			else{
				if(!empty($unix_dateTime)){
					//if($query_followup) {
						$qq = "select docket_no,person_name,person_mail,person_mobile from lead_details where lead_id =".$lead_id;
						$exe_qry = $this->EXECUTE_QUERY($qq,$DB_H);
						$result = $this->FETCH_ARRAY($exe_qry,MYSQLI_ASSOC);
						$docket_no = $result['docket_no'];
						$person_name = $result['person_name'];
						$person_mobile = $result['person_mobile'];
						$person_mail = $result['person_mail'];
						$category_id = 1;
						$auth_token = generateRandomString(8);

						// $crrnt_time = date("Y-m-d h:i:s");
						$datetime   = time();
						$json_arry=array('action'=>'followup_add','date_time'=>$datetime,'action_by'=>$agent_name1,'lead_id'=>$lead_id,'client_folder'=>$client_folder,'docket_no'=>$docket_no,'followup_remarks'=>$followup_remarks,'followup_datetime'=>$date_time,'client_id'=>$this->client_id);
						$datajson =json_encode($json_arry);
						//~ $datajson   =       '{"action":"followup_add","date_time":"'.$datetime.'","action_by":"'.$agent_name1.'","lead_id":"'.$lead_id.'","client_folder":"'.$client_folder.'","docket_no":"'.$docket_no.'","followup_remarks":"'.$followup_remarks.'","followup_datetime":"'.$date_time.'","client_id":"'.$this->client_id.'"}';
						timeline_history($datajson);
						if($followup_check==1){
								// $query_update = "update followup_history set expire_followup = 1,token_id='".$auth_token."',followup_status='Expired' where created_by_id='".$user_id."' and followup_datetime='".$date_time."' and expire_followup=0";
								if($lead_flag==1){
									$query_update = "update followup_history set expire_followup = 1,token_id='".$auth_token."',followup_status='Expired' where lead_id='".$lead_id."' and expire_followup=0";
									
									$query_followup = "UPDATE lead_details SET followup_status = 'Expired' where lead_id = '".$lead_id."'";
									$query_followup = $this->EXECUTE_QUERY($query_followup,$this->DB_H);

									$query_followup1 = "UPDATE lead_details_30 SET followup_status = 'Expired' where lead_id = '".$lead_id."'";
									$this->EXECUTE_QUERY($query_followup1,$this->DB_H);
								}
								else{
									$queryselId = "select lead_id from followup_history where assigned_to_id='".$assigned_to_id."' and followup_datetime='".$date_time."' and expire_followup=0";
									$exeID = $this->EXECUTE_QUERY($queryselId,$this->DB_H);
									$fetchID = $this->FETCH_ARRAY($exeID,MYSQLI_ASSOC);
									$exlead_id = $fetchID['lead_id'];
									
									$query_followup = "UPDATE lead_details SET followup_status = 'Expired' where lead_id = '".$exlead_id."'";
									$query_followup = $this->EXECUTE_QUERY($query_followup,$this->DB_H);

									$query_followup1 = "UPDATE lead_details_30 SET followup_status = 'Expired' where lead_id = '".$exlead_id."'";
									$this->EXECUTE_QUERY($query_followup1,$this->DB_H);

									$query_update = "update followup_history set expire_followup = 1,token_id='".$auth_token."',followup_status='Expired' where assigned_to_id='".$assigned_to_id."' and followup_datetime='".$date_time."' and expire_followup=0";
								}
								$this->EXECUTE_QUERY($query_update,$this->DB_H);
						}
						$query_followup = "UPDATE lead_details SET followup_datetime ='".$date_time."',followup_remarks = '".$followup_remarks."',followup_dt_unix='".$unix_dateTime."',followup_status = 'Scheduled' where lead_id = '".$lead_id."'";
						$query_followup = $this->EXECUTE_QUERY($query_followup,$this->DB_H);

						$query_followup1 = "UPDATE lead_details_30 SET followup_datetime ='".$date_time."',followup_remarks = '".$followup_remarks."',followup_dt_unix='".$unix_dateTime."',followup_status = 'Scheduled' where lead_id = '".$lead_id."'";
						$this->EXECUTE_QUERY($query_followup1,$this->DB_H);

						$v_array=array(
						"lead_id"                       => array(STRING, $lead_id),
						"docket_no"                     => array(STRING, $docket_no),
						"followup_datetime"             => array(STRING, $date_time),
						"followup_datetime_unix"		=> array(STRING, strtotime($date_time)),
						"followup_remarks"              => array(STRING, $followup_remarks),
						"followup_status"               => array(STRING, 'Scheduled'),
						"created_by_id"                 => array(STRING, $user_id),
						"assigned_to_id"                => array(STRING, $assigned_to_id),
						"assigned_to_dept_id"           => array(STRING, $dept_id),
						"created_by_name"               => array(STRING, $agent_name1),
						"self_email"                    => array(STRING, $self_mail_status),
						"self_sms"                     	=> array(STRING, $self_sms_status),
						"self_popup"                    => array(STRING, $self_popup),
						"customer_email"                => array(STRING, $cust_mail_status),
						"customer_sms"                  => array(STRING, $cust_sms_status),
						"person_name"                   => array(STRING, $person_name),
						"person_mobile"                 => array(STRING, $person_mobile),
						"person_mail"                   => array(STRING, $person_mail),
						"created_by_email"              => array(STRING, $user_mail),
						"created_by_phone"              => array(STRING, $user_phone),
						"token_id"                      => array(STRING, $auth_token),
						"followup_date"                 => array(STRING, $FOLLOWUPDATE),
						);
						$tName_follow   =       "followup_history";
						$this->INSERT($tName_follow, $v_array, $this->DB_H);
						$history_id=$this->getLastInsertedID($this->DB_H);
						$final_array = $final_array_history = array();
						$db = "crm_manager_".$client_id;
						$u=$p='';
						$collection = "followup_pop";
						$collection_history = "followup_pop_history";
						$mongo  =       new MongoClient('127.0.0.1',$u,$p,$db);
						$mongo->connectDB();

						$FOLLOWUP_TIME_UNIX = strtotime($date_time);
						if($followup_check==1){
								// $criteria = array("USER_ID"=>$user_id,"FOLLOWUP_TIME_UNIX"=>$FOLLOWUP_TIME_UNIX,"CLOSE_FLAG"=>0);
								if($lead_flag==1){
										$criteria = array("DOCKET_NO"=>$docket_no,"CLOSE_FLAG"=>0);
										$criteria_history = array("DOCKET_NO"=>$docket_no,"EXPIRE_FLAG"=>0);
								}
								else{
										$criteria = array("ASSIGNED_TO_ID"=>$assigned_to_id,"FOLLOWUP_TIME_UNIX"=>$FOLLOWUP_TIME_UNIX,"CLOSE_FLAG"=>0);
										$criteria_history = array("ASSIGNED_TO_ID"=>$assigned_to_id,"FOLLOWUP_TIME_UNIX"=>$FOLLOWUP_TIME_UNIX,"EXPIRE_FLAG"=>0);
								}
								$newdata = array("CLOSE_FLAG"=>1);
								$ret = $mongo->UPDATE($collection,$newdata,$criteria);

								// $criteria_history = array("USER_ID"=>$user_id,"FOLLOWUP_TIME_UNIX"=>$FOLLOWUP_TIME_UNIX,"EXPIRE_FLAG"=>0);
								$newdata_history = array("EXPIRE_FLAG"=>1,"FOLLOWUP_STATUS"=>"Expired");
								$ret = $mongo->UPDATE($collection_history,$newdata_history,$criteria_history);
						}
						$FOLLOWUP_UPDATE_REMARKS = "";
						$final_array_history['HISTORY_ID']      = $history_id;
						$final_array_history['LEAD_ID'] = $lead_id;
						$final_array_history['USER_ID'] = $user_id;
						$final_array_history['ASSIGNED_TO_ID'] = $assigned_to_id;
						$final_array_history['ASSIGNED_TO_DEPT_ID'] = $dept_id;
						$final_array_history['FOLLOWUP_TIME_UNIX']      = (int) $FOLLOWUP_TIME_UNIX;
						$final_array_history['DOCKET_NO']       =       $docket_no;
						$final_array_history['FOLLOWUP_TIME']   = $date_time;
						$final_array_history['FOLLOWUP_REMARKS']        = $followup_remarks;
						$final_array_history['PERSON_NAME']     = $person_name;
						$final_array_history['FOLLOWUP_STATUS'] = "Scheduled";
						$final_array_history['FOLLOWUP_UPDATE_REMARKS'] = $FOLLOWUP_UPDATE_REMARKS;
						$final_array_history['EXPIRE_FLAG']     = 0;
						 $mongo->INSERT($collection_history,$final_array_history,'');
						if($self_popup==1){
								$final_array['HISTORY_ID']      = $history_id;
								$final_array['USER_ID'] = $user_id;
								$final_array['ASSIGNED_TO_ID'] = $assigned_to_id;
								$final_array['ASSIGNED_TO_DEPT_ID'] = $dept_id;
								$final_array['LEAD_ID'] = $lead_id;
								$final_array['FOLLOWUP_TIME_UNIX']      = (int) $FOLLOWUP_TIME_UNIX;
								$final_array['DOCKET_NO']       =       $docket_no;
								$final_array['FOLLOWUP_TIME']   = $date_time;
								$final_array['FOLLOWUP_REMARKS']        = $followup_remarks;
								$final_array['PERSON_NAME']     = $person_name;
								$final_array['CLOSE_FLAG']      = 0;
								$mongo->INSERT($collection,$final_array,'');
						}
						return $this->result = '{"Success":"Followup Date Time Updated Successfully"}';
					//}
					//else{
						//return $this->result = '{"Error":"Followup Date Time Not Updated"}';
				//	}
				}
			}
		}

		function deletePerson(){
			$client_id = $this->client_id;
			$FLP = new logs_creation($client_id);
			$FLP->prepare_log("1","======DB UTILITY==============","deletePerson");
		
			$FLP->prepare_log("1","======Data received in delete person==============",$this->dataArray);
		
			$configFileContent = file_get_contents("/var/www/html/CZCRM/configs/config.txt");
			$fileArr = json_decode($configFileContent,true);
			$ticket_app_name = isset($fileArr['TICKET_APP_NAME'])?$fileArr['TICKET_APP_NAME']:'';

			$source_app_name = isset($this->dataArray->source_app_name)?$this->dataArray->source_app_name:'';
			
			$data = array("source"=>$ticket_app_name);

			$cuid=isset($this->dataArray->cuid)?$this->dataArray->cuid:0;


			if(empty($cuid) || (($source_app_name == 'SELF') && empty($person_id))){
				
				return $this->result='{"status":"error","Required parameters missing abcd	","data":'.json_encode($data).'}';
			}else{	
				if(!empty($cuid)){
					$result_person = "select person_id,mobile_no,person_mail from person_info where cuid =".$cuid;
					$exe_result_person = $this->EXECUTE_QUERY($result_person,$this->DB_H);
					$fetch_result_person = $this->FETCH_ARRAY($exe_result_person, MYSQLI_ASSOC);
	
					$person_id = isset($fetch_result_person['person_id'])?$fetch_result_person['person_id']:0;
				}else{
					$person_id = isset($this->dataArray->person_id)?$this->dataArray->person_id:0;
				}
				
				$mobile_no = isset($fetch_result_person['mobile_no'])?$fetch_result_person['mobile_no']:'';
				$person_mail = isset($fetch_result_person['person_mail'])?$fetch_result_person['person_mail']:'';
		
				if($person_id){
					$num = 0;
					//For ticketing user
					if($source_app_name == 'SELF'){
						$result= "select * from ticket_details where person_id =".$person_id;
						$exe_result = $this->EXECUTE_QUERY($result,$this->DB_H);
						$num=$this->GET_ROWS_COUNT($exe_result);
					}
					
					if($num == 0)
					{    
						$query_delete_from_person_info= "delete from person_info where person_id=$person_id";
						$delete_query = $this->EXECUTE_QUERY($query_delete_from_person_info,$this->DB_H);
						if($delete_query){
							insertDocketSource('mobile',$mobile_no,$person_id,$client_id,'DELETE');
							insertDocketSource('email',$person_mail,$person_id,$client_id,'DELETE');
						}
						return $this->result='{"status":"success","message":"Person deleted successfully","data":'.json_encode($data).'}';
					}
					else
					{
						return $this->result='{"status":"error","message":"Can\'t delete Person! Mapping exists for the selected value in ticket details","data":'.json_encode($data).'}';
					}
				}else{
					return $this->result='{"status":"error","message":"Can\'t delete person! Person doesn\'t exists","data":'.json_encode($data).'}';
				}
			}
		}
		
		function deleteDepartment(){
			$FLP = new logs_creation($this->client_id);
			$FLP->prepare_log("1","======DB UTILITY==============","deleteDepartment");
		
			$FLP->prepare_log("1","======Data received in deleteDepartment==============",$this->dataArray);
		
			$configFileContent = file_get_contents("/var/www/html/CZCRM/configs/config.txt");
			$fileArr = json_decode($configFileContent,true);
			$ticket_app_name = isset($fileArr['TICKET_APP_NAME'])?$fileArr['TICKET_APP_NAME']:'';
			
			$data = array("source"=>$ticket_app_name);
			$duid=isset($this->dataArray->duid)?$this->dataArray->duid:0;
			
			if(empty($duid)){
				return $this->result='{"status":"error","Required parameters missing","data":'.json_encode($data).'}';
			}else{	
				$result_dept = "select dept_id from departments where duid =".$duid;
				$exe_result_dept = $this->EXECUTE_QUERY($result_dept,$this->DB_H);
				$fetch_result_dept = $this->FETCH_ARRAY($exe_result_dept, MYSQLI_ASSOC);
				$dept_id = isset($fetch_result_dept['dept_id'])?$fetch_result_dept['dept_id']:0;
				
				//~ Check if user of this dept is loggedin 
				$query_active_dept = 'select * from loggedin_live where dept_id = '.$dept_id;
				$execute_query_active_dept = $this->EXECUTE_QUERY($query_active_dept,$this->DB_H);
				$active_dept_number = $this->GET_ROWS_COUNT($execute_query_active_dept);
				
				$active_dept_caption = "";
				//~ if user of this dept is loggedin, dept cant be saved
				if($active_dept_number){
					$str = 'is';
					if($active_dept_number == 1){
						$str = "is";
					}else if($active_dept_number > 1){
						$str = "are";
					}
		
					$active_dept_caption="Can\'t delete department! Users of this department ".$str." currently logged in.";
		
					return $this->result='{"status":"error","message":"'.$active_dept_caption.'","data":'.json_encode($data).'}';		
				}else{
					//~ if user of this dept is not loggedin, resume normal working
					$query_select_user_name_from_users= "select user_name from users where dept_id='$dept_id'";
					$query=$this->EXECUTE_QUERY($query_select_user_name_from_users,$this->DB_H);
					$num_rows = $this->GET_ROWS_COUNT ($query);
					if(!(empty($num_rows)))
					{
						return $this->result='{"status":"error","message":"Can\'t delete Department! Users exists in this department.","data":'.json_encode($data).'}';		
					}
					else
					{	
						$tName = 'departments';
						$w = array ("dept_id" => array(INT, $dept_id));
						$tName = $this->DELETE ($tName, $w, $this->DB_H);
						$this->dataArray->module_name = "department";
						$this->dataArray->table_name = "departments";
						$this->applyChange($json_array);
					
						return $this->result='{"status":"error","message":"Department deleted successfully.","data":'.json_encode($data).'}';			
					}
				}	
			}
		}

		function deleteUser(){
			$client_id = $this->client_id;
			$FLP = new logs_creation($client_id);
			$FLP->prepare_log("1","======DB UTILITY==============","deleteUser");
		
			$FLP->prepare_log("1","======Data received in deleteUser==============",$this->dataArray);
			
			$configFileContent = file_get_contents("/var/www/html/CZCRM/configs/config.txt");
			$fileArr = json_decode($configFileContent,true);
			$ticket_app_name = isset($fileArr['TICKET_APP_NAME'])?$fileArr['TICKET_APP_NAME']:'';
			
			$data = array("source"=>$ticket_app_name);

			$uuid=isset($this->dataArray->uuid)?$this->dataArray->uuid:0;
			
			if(empty($uuid)){
				return $this->result='{"status":"error","Required parameters missing","data":'.json_encode($data).'}';
			}else{	
		
				///////////////
				$result_user = "select user_id,user_name from users where uuid =".$uuid;
				$exe_result_user = $this->EXECUTE_QUERY($result_user,$this->DB_H);
				$fetch_result_user = $this->FETCH_ARRAY($exe_result_user, MYSQLI_ASSOC);
				$user_id = isset($fetch_result_user['user_id'])?$fetch_result_user['user_id']:0;
				$user_name = isset($fetch_result_user['user_name'])?$fetch_result_user['user_name']:'';
				
				//~ Check if this user is loggedin 
				$query_active_users = 'select * from loggedin_live where user_id = '.$user_id;
				$execute_query_active_users = $this->EXECUTE_QUERY($query_active_users,$this->DB_H);
				$active_users_number = $this->GET_ROWS_COUNT($execute_query_active_users);
				
				$active_users_caption="";
				if($active_users_number){
					//If this user is logged in
					return $this->result='{"status":"error","Can\'t delete user! This user is currently logged in","data":'.json_encode($data).'}';
				}else{
					$query = "delete from users where user_id=$user_id";
					$result_user = $this->EXECUTE_QUERY($query,$this->DB_H);
					
					if($result_user){
						$this->dataArray->module_name = "users";
						$this->dataArray->table_name = "users";
						$this->applyChange();
		
						$del_userAuth = "delete from ".GDB_NAME.".userAuth where user_id=$user_id and registration_id =".$client_id;
						$result_userAuth=$this->EXECUTE_QUERY($del_userAuth,$this->DB_H);
						return $this->result='{"status":"success","message":"User deleted successfully","data":'.json_encode($data).'}';
					}else{
						return $this->result='{"status":"error","message":"Error in deleting user","data":'.json_encode($data).'}';
					}
				}	
			}
		}
/*
		//Function for dashboard's ticket/lead stats component
		function getTicketLeadsInfo(){
			$FLP = new logs_creation($this->client_id);
			$client_id = isset($this->client_id)?$this->client_id:'';
			
			$FLP->prepare_log("1","=======[Function File Name]========","db_utility.class.php");
			$FLP->prepare_log("1","=======[Function entry point]========","getTicketLeadsInfo");
			
			$infoRequired = isset($this->dataArray->infoRequired)?$this->dataArray->infoRequired:"";
			$timePeriod = isset($this->dataArray->timePeriod)?$this->dataArray->timePeriod:"";

			$FLP->prepare_log("1","=======[Data Recieved]========",$this->dataArray);

			if(empty($infoRequired) || empty($timePeriod)){
				return $this->result = '{"status":"error","message":"Required parameters missing"}';
			}
			else {
				$FLP->prepare_log("1","=======[Calling]========","getTicketLeadsInfo of dashboardHandler");

				require_once("/var/www/html/CZCRM/classes/dashboardHandler.class.php");
				// $FLP->prepare_log("1","=======[here]========","1");
				$DH = new dashboardHandler($this->client_id);
				// $FLP->prepare_log("1","=======[here]========","2");
				$userJson=json_encode($this->dataArray);
				// $FLP->prepare_log("1","=======[here]========","3");
				$returned_data=$DH->getTicketLeadsInfo($userJson);	
				// $FLP->prepare_log("1","=======[here]========","4");
				$FLP->prepare_log("1"," Data returned",$returned_data);
				return $this->result = $returned_data;
			}		
		}

		//Function for dashboard's ticket/lead task component
		function getTasksInfo(){
			$FLP = new logs_creation($this->client_id);
			$client_id = isset($this->client_id)?$this->client_id:'';
			
			$FLP->prepare_log("1","=======[Function File Name]========","db_utility.class.php");
			$FLP->prepare_log("1","=======[Function entry point]========","getTasksInfo");
			
			$infoRequired = isset($this->dataArray->infoRequired)?$this->dataArray->infoRequired:"";

			$FLP->prepare_log("1","=======[Data Recieved]========",$this->dataArray);

			if(empty($infoRequired)){
				return $this->result = '{"status":"error","message":"Required parameter missing"}';
			}
			else {
				$FLP->prepare_log("1","=======[Calling]========","getTasksInfo of dashboardHandler");

				require_once("/var/www/html/CZCRM/classes/dashboardHandler.class.php");
				$FLP->prepare_log("1","=======[here]========","1");
				$DH = new dashboardHandler($this->client_id);
				$FLP->prepare_log("1","=======[here]========","2");
				$userJson=json_encode($this->dataArray);
				$FLP->prepare_log("1","=======[here]========","3");
				$returned_data=$DH->getTasksInfo($userJson);	
				$FLP->prepare_log("1","=======[here]========","4");
				$FLP->prepare_log("1"," Data returned",$returned_data);
				return $this->result = $returned_data;
			}		
		}

		//Function for dashboard's ticket/lead mail component
		function getMailsInfo(){
			$FLP = new logs_creation($this->client_id);
			$client_id = isset($this->client_id)?$this->client_id:'';
			
			$FLP->prepare_log("1","=======[Function File Name]========","db_utility.class.php");
			$FLP->prepare_log("1","=======[Function entry point]========","getMailsInfo");
			
			$infoRequired = isset($this->dataArray->infoRequired)?$this->dataArray->infoRequired:"";

			$FLP->prepare_log("1","=======[Data Recieved]========",$this->dataArray);

			if(empty($infoRequired)){
				return $this->result = '{"status":"error","message":"Required parameter missing"}';
			}
			else {
				$FLP->prepare_log("1","=======[Calling]========","getMailsInfo of dashboardHandler");

				require_once("/var/www/html/CZCRM/classes/dashboardHandler.class.php");
				$FLP->prepare_log("1","=======[here]========","1");
				$DH = new dashboardHandler($this->client_id);
				$FLP->prepare_log("1","=======[here]========","2");
				$userJson=json_encode($this->dataArray);
				$FLP->prepare_log("1","=======[here]========","3");
				$returned_data=$DH->getMailsInfo($userJson);	
				$FLP->prepare_log("1","=======[here]========","4");
				$FLP->prepare_log("1"," Data returned",$returned_data);
				return $this->result = $returned_data;
			}		
		}

		//Function for dashboard's user component
		function getUsersInfo(){
			$FLP = new logs_creation($this->client_id);
			$client_id = isset($this->client_id)?$this->client_id:'';
			
			$FLP->prepare_log("1","=======[Function File Name]========","db_utility.class.php");
			$FLP->prepare_log("1","=======[Function entry point]========","getUsersInfo");
			
			$infoRequired = isset($this->dataArray->infoRequired)?$this->dataArray->infoRequired:"";

			$FLP->prepare_log("1","=======[Data Recieved]========",$this->dataArray);

			if(empty($infoRequired)){
				return $this->result = '{"status":"error","message":"Required parameter missing"}';
			}
			else {
				$FLP->prepare_log("1","=======[Calling]========","getUsersInfo of dashboardHandler");

				require_once("/var/www/html/CZCRM/classes/dashboardHandler.class.php");
				$FLP->prepare_log("1","=======[here]========","1");
				$DH = new dashboardHandler($this->client_id);
				$FLP->prepare_log("1","=======[here]========","2");
				$userJson=json_encode($this->dataArray);
				$FLP->prepare_log("1","=======[here]========","3");
				$returned_data=$DH->getUsersInfo($userJson);	
				$FLP->prepare_log("1","=======[here]========","4");
				$FLP->prepare_log("1"," Data returned",$returned_data);
				return $this->result = $returned_data;
			}		
		}

		//Function for dashboard's priority component
		function getPriorityInfo(){
			$FLP = new logs_creation($this->client_id);
			$client_id = isset($this->client_id)?$this->client_id:'';
			
			$FLP->prepare_log("1","=======[Function File Name]========","db_utility.class.php");
			$FLP->prepare_log("1","=======[Function entry point]========","getPriorityInfo");
			
			$infoRequired = isset($this->dataArray->infoRequired)?$this->dataArray->infoRequired:"";

			$FLP->prepare_log("1","=======[Data Recieved]========",$this->dataArray);

			if(empty($infoRequired)){
				return $this->result = '{"status":"error","message":"Required parameter missing"}';
			}
			else {
				$FLP->prepare_log("1","=======[Calling]========","getPriorityInfo of dashboardHandler");

				require_once("/var/www/html/CZCRM/classes/dashboardHandler.class.php");
				$FLP->prepare_log("1","=======[here]========","1");
				$DH = new dashboardHandler($this->client_id);
				$FLP->prepare_log("1","=======[here]========","2");
				$json=json_encode($this->dataArray);
				$FLP->prepare_log("1","=======[here]========","3");
				$returned_data=$DH->getPriorityInfo($json);	
				$FLP->prepare_log("1","=======[here]========","4");
				$FLP->prepare_log("1"," Data returned",$returned_data);
				return $this->result = $returned_data;
			}		
		}

		//Function for dashboard's ticket status component
		function getStatusInfo(){
			$FLP = new logs_creation($this->client_id);
			$client_id = isset($this->client_id)?$this->client_id:'';
			
			$FLP->prepare_log("1","=======[Function File Name]========","db_utility.class.php");
			$FLP->prepare_log("1","=======[Function entry point]========","getStatusInfo");
			
			$infoRequired = isset($this->dataArray->infoRequired)?$this->dataArray->infoRequired:"";

			$FLP->prepare_log("1","=======[Data Recieved]========",$this->dataArray);

			if(empty($infoRequired)){
				return $this->result = '{"status":"error","message":"Required parameter missing"}';
			}
			else {
				$FLP->prepare_log("1","=======[Calling]========","getStatusInfo of dashboardHandler");

				require_once("/var/www/html/CZCRM/classes/dashboardHandler.class.php");
				$FLP->prepare_log("1","=======[here]========","1");
				$DH = new dashboardHandler($this->client_id);
				$FLP->prepare_log("1","=======[here]========","2");
				$json=json_encode($this->dataArray);
				$FLP->prepare_log("1","=======[here]========","3");
				$returned_data=$DH->getStatusInfo($json);	
				$FLP->prepare_log("1","=======[here]========","4");
				$FLP->prepare_log("1"," Data returned",$returned_data);
				return $this->result = $returned_data;
			}		
		}

		//Function for dashboard's ticket type component
		function getTypeInfo(){
			$FLP = new logs_creation($this->client_id);
			$client_id = isset($this->client_id)?$this->client_id:'';
			
			$FLP->prepare_log("1","=======[Function File Name]========","db_utility.class.php");
			$FLP->prepare_log("1","=======[Function entry point]========","getTypeInfo");
			
			$infoRequired = isset($this->dataArray->infoRequired)?$this->dataArray->infoRequired:"";

			$FLP->prepare_log("1","=======[Data Recieved]========",$this->dataArray);

			if(empty($infoRequired)){
				return $this->result = '{"status":"error","message":"Required parameter missing"}';
			}
			else {
				$FLP->prepare_log("1","=======[Calling]========","ticketTypeInfo of dashboardHandler");

				require_once("/var/www/html/CZCRM/classes/dashboardHandler.class.php");
				$FLP->prepare_log("1","=======[here]========","1");
				$DH = new dashboardHandler($this->client_id);
				$FLP->prepare_log("1","=======[here]========","2");
				$json=json_encode($this->dataArray);
				$FLP->prepare_log("1","=======[here]========","3");
				$returned_data=$DH->getTypeInfo($json);	
				$FLP->prepare_log("1","=======[here]========","4");
				$FLP->prepare_log("1"," Data returned",$returned_data);
				return $this->result = $returned_data;
			}		
		}

		//Function for dashboard's user component
		function getStateInfo(){
			$FLP = new logs_creation($this->client_id);
			$client_id = isset($this->client_id)?$this->client_id:'';
			
			$FLP->prepare_log("1","=======[Function File Name]========","db_utility.class.php");
			$FLP->prepare_log("1","=======[Function entry point]========","getStateInfo");
			
			$infoRequired = isset($this->dataArray->infoRequired)?$this->dataArray->infoRequired:"";

			$FLP->prepare_log("1","=======[Data Recieved]========",$this->dataArray);

			if(empty($infoRequired)){
				return $this->result = '{"status":"error","message":"Required parameter missing"}';
			}
			else {
				$FLP->prepare_log("1","=======[Calling]========","getStateInfo of dashboardHandler");

				require_once("/var/www/html/CZCRM/classes/dashboardHandler.class.php");
				$FLP->prepare_log("1","=======[here]========","1");
				$DH = new dashboardHandler($this->client_id);
				$FLP->prepare_log("1","=======[here]========","2");
				$json=json_encode($this->dataArray);
				$FLP->prepare_log("1","=======[here]========","3");
				$returned_data=$DH->getStateInfo($json);	
				$FLP->prepare_log("1","=======[here]========","4");
				$FLP->prepare_log("1"," Data returned",$returned_data);
				return $this->result = $returned_data;
			}		
		}

		//Function for dashboard's disposition component
		function getDispositionInfo(){
			$FLP = new logs_creation($this->client_id);
			$client_id = isset($this->client_id)?$this->client_id:'';
			
			$FLP->prepare_log("1","=======[Function File Name]========","db_utility.class.php");
			$FLP->prepare_log("1","=======[Function entry point]========","getDispositionInfo");
			
			$infoRequired = isset($this->dataArray->infoRequired)?$this->dataArray->infoRequired:"";

			$FLP->prepare_log("1","=======[Data Recieved]========",$this->dataArray);

			if(empty($infoRequired)){
				return $this->result = '{"status":"error","message":"Required parameter missing"}';
			}
			else {
				$FLP->prepare_log("1","=======[Calling]========","getDispositionInfo of dashboardHandler");

				require_once("/var/www/html/CZCRM/classes/dashboardHandler.class.php");
				$FLP->prepare_log("1","=======[here]========","1");
				$DH = new dashboardHandler($this->client_id);
				$FLP->prepare_log("1","=======[here]========","2");
				$json=json_encode($this->dataArray);
				$FLP->prepare_log("1","=======[here]========","3");
				$returned_data=$DH->getDispositionInfo($json);	
				$FLP->prepare_log("1","=======[here]========","4");
				$FLP->prepare_log("1"," Data returned",$returned_data);
				return $this->result = $returned_data;
			}		
		}
		*/
	}
?>
