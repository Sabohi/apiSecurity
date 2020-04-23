<?php
require_once ("/var/www/html/CZCRM/classes/function_log.class.php");

	// Function to fetch all recursive childs of the user_id provided            @Sabohi    16/12/2019
	// Input : user id of the user whose child users string is required
	// Result : comma separated string of all childs including user himself
	function fetchChild($user_id = "",$DB="",$DB_H=""){
		$final_array = $childArray = array();
		$w_cond = '';

		if(is_array($user_id) && sizeof($user_id)){
			$user_id_string = implode(",",$user_id);
			$w_cond = " where parent_user_id IN (".$user_id_string.")";
		}else if(!empty($user_id)){
			$w_cond = " where parent_user_id = ".$user_id;	
		}

		if(empty($DB) && empty($DB_H)){
			global $DB , $DB_H;
		}
		$childString = "";

		if(!empty($w_cond)){
			$select_childs = "SELECT user_id from users ".$w_cond;
			$exe_childs = $DB->EXECUTE_QUERY($select_childs, $DB_H);
			
			while($fetch_childs = $DB->FETCH_ARRAY($exe_childs, MYSQLI_ASSOC)){
				if(isset($fetch_childs['user_id']) && !empty($fetch_childs['user_id'])){
					array_push($childArray, $fetch_childs['user_id']);
				}	
			}
			$childSize = sizeof($childArray);
			if($childSize != 0){
				$childArrayRecursive = fetchChild($childArray,$DB,$DB_H);
				 
				if(!empty($childArrayRecursive)){
					$user_id_array = explode(",",$childArrayRecursive);
					$final_array = array_merge($user_id_array, $childArray);
				}else{
					$final_array = $childArray;
				}
			}else{
				$final_array = $childArray;
			}
			
		}
		if(!is_array($user_id)){
			array_push($final_array, $user_id);
		}
		$childString = implode(",",$final_array);		
		return $childString;
	}

	function fetchChildRoles($role_id = "",$DB="",$DB_H=""){
		$final_array = $childArray = array();
		$w_cond = '';

		if(is_array($role_id) && sizeof($role_id)){
			$role_id_string = implode(",",$role_id);
			$w_cond = " where parent_role_name IN (".$role_id_string.")";
		}else if(!empty($role_id)){
			$w_cond = " where parent_role_name = ".$role_id;	
		}

		if(empty($DB) && empty($DB_H)){
			global $DB , $DB_H;
		}

		$childString = "";

		if(!empty($w_cond)){
			$select_childs = "SELECT role_id from roles ".$w_cond;
			$exe_childs = $DB->EXECUTE_QUERY($select_childs, $DB_H);
			while($fetch_childs = $DB->FETCH_ARRAY($exe_childs, MYSQLI_ASSOC)){
				if(isset($fetch_childs['role_id']) && !empty($fetch_childs['role_id'])){
					array_push($childArray, $fetch_childs['role_id']);
				}	
			}
			$childSize = sizeof($childArray);
			if($childSize != 0){
				$childArrayRecursive = fetchChildRoles($childArray,$DB,$DB_H);
				 
				if(!empty($childArrayRecursive)){
					$role_id_array = explode(",",$childArrayRecursive);
					$final_array = array_merge($role_id_array, $childArray);
				}else{
					$final_array = $childArray;
				}
			}else{
				$final_array = $childArray;
			}
			
		}
		if(!is_array($role_id)){
			array_push($final_array, $role_id);
		}
		$childString = implode(",",$final_array);		
		return $childString;
	}

	//~ Send packets to omni
	function sendPacket($dataJson=""){
		$FLP = new logs_creation($client_id);
		$FLP->prepare_log("1","====function===", "=======sendPacket=======");

		$dataArr = json_decode($dataJson, true);

		$configFileContent = file_get_contents("/var/www/html/CZCRM/configs/config.txt");
		$configFileArr = json_decode($configFileContent,true);
		$ticket_app_name = isset($configFileArr["TICKET_APP_NAME"])?$configFileArr["TICKET_APP_NAME"]:'ticket';
		$redis_key = isset($configFileArr["REDIS_PACKET_KEY"])?$configFileArr["REDIS_PACKET_KEY"]:'';

		$src = isset($ticket_app_name)?$ticket_app_name:'ticket';
		$key = isset($dataArr['key'])?$dataArr['key']:'';
		$session_id = isset($dataArr['session_id'])?$dataArr['session_id']:'';
		$docket_no = isset($dataArr['docket_no'])?$dataArr['docket_no']:'';
		$uuid = isset($dataArr['uuid'])?$dataArr['uuid']:'';
		$assigned_to_dept_id = isset($dataArr['assigned_to_dept_id'])?$dataArr['assigned_to_dept_id']:'';
		// $agent_name = isset($dataArr['agent_name'])?$dataArr['agent_name']:'';

		$client_id = isset($dataArr['client_id'])?$dataArr['client_id']:'';
		$agent_id = isset($dataArr['agent_id'])?$dataArr['agent_id']:'';
		// $assign_by_id = isset($dataArr['assign_by_id'])?$dataArr['assign_by_id']:'';
		// $assign_by_name = isset($dataArr['assign_by_name'])?$dataArr['assign_by_name']:'';
		$previous_userName = isset($dataArr['previous_userName'])?$dataArr['previous_userName']:'';
		$previous_userId = isset($dataArr['previous_userId'])?$dataArr['previous_userId']:'';

		//$auto_assign = isset($dataArr['auto_assign'])?$dataArr['auto_assign']:0;
		//$flow = isset($dataArr['flow'])?$dataArr['flow']:0;
		
		$FLP->prepare_log("1","====here===", "send auto assign packet for omni");
		$assignment_array	=	array();
	
		//$src = $query_issue["source"];
		
		// $docket_no = $ticket_id;
		//$connect_time = date('Y-m-d H:i:s');
		//$disconnect_time = date('Y-m-d H:i:s');

		$connect_time = time();
		$disconnect_time = time();
		
		$reqType="manual_assign";

		$assignment_array	=	array("reqType"=>$reqType,"src"=>$ticket_app_name,"docket_number"=>$docket_no,"key"=>$key,"session_id"=>$session_id,"connect_time"=>$connect_time,"disconnect_time"=>$disconnect_time,"agent_id"=>$agent_id,"dept_id"=>$assigned_to_dept_id,"assign_by_id" =>$uuid,"type"=>"MANUAL","previous_userId"=>$previous_userId,"mail_trans_no"=>"","APP_PARAM"=>"TICKETING");

		$assignment_data = json_encode($assignment_array);
		
		$data=base64_encode($assignment_data);
		
		$packetID = time();		
		
		$packetToBeSent="action: ".$reqType."\r\npacketID: ".$packetID."\r\ndata: ".$data."\r\nenc_type: base64\r\ndestination: ".$configFileArr["OCMS_NODE_ID"]."\r\nsource: ".$configFileArr["TICKET_NODE_ID"]."\r\ndest_app: ".$configFileArr["OCMS_APP_NAME"]."\r\nkey: ".$key."\r\n\r\n";
	
		//$packetToBeSent="action: auto_assign\r\npacketID: ".$packetID."\r\ndata: ".$data."\r\nenc_type: base64\r\ndestination: ".$configFileArr["OCMS_NODE_ID"]."\r\nsource: ".$configFileArr["TICKET_NODE_ID"]."\r\ndest_app: ".$configFileArr["OCMS_APP_NAME"]."\r\nkey: ".$key."\r\n\r\n";
	
		require_once("/var/www/html/CZCRM/classes/redisHandler.class.php");
		$redis = new redisHandler();
		$redis->lpushRedis($redis_key,$packetToBeSent);
	}
	
	//~ Function to check if data is base64 encoded or not
	function IsBase64($s) {
		if (!preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $s)) return false;

		// Decode the string in strict mode and check the results
		$decoded = base64_decode($s, true);
		if(false === $decoded) return false;

		// if string returned contains not printable chars
		if (0 < preg_match('/((?![[:graph:]])(?!\s)(?!\p{L}))./', $decoded, $matched)) return false;

		// Encode the string again
		if(base64_encode($decoded) != $s) return false;

		return true;
	}
	function IsBase64Url($s) {
		if (!preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $s)) return false;

		// Decode the string in strict mode and check the results
		$decoded = base64url_decode($s, true);
		if(false === $decoded) return false;

		// if string returned contains not printable chars
		if (0 < preg_match('/((?![[:graph:]])(?!\s)(?!\p{L}))./', $decoded, $matched)) return false;

		// Encode the string again
		if(base64url_encode($decoded) != $s) return false;

		return true;
	}
	function GENERATELOGS_CZCRM($DATA,$BLOCK,$flag=0){
		$file_name      =       "/var/log/czentrix/CZCRM/czcrm_logs";
		$date   =       date("Y-m-d H:i:s");
		if(file_exists($file_name)) {
			$fp     =       fopen($file_name,"a+");
			if($flag==1){
				fwrite($fp,"(".$BLOCK."=====".$date.")    ");
				fwrite($fp,print_r($DATA,true));
				fwrite($fp,"\n");
			}
			else{
				fwrite($fp,"(".$BLOCK."=====".$date.")=====".$DATA."\n");
			}
			fclose($fp);
		}
	}
	function GENERATELOGS_CZCRM_REDIS_MONGO($DATA,$BLOCK,$flag=0){
		$file_name      =       "/var/log/czentrix/CZCRM/redis_mongo_logs";
		$date   =       date("Y-m-d H:i:s");
		if(!file_exists($file_name)){
			exec("touch $file_name");
			exec("chmod 777 $file_name");
		}
		if(file_exists($file_name)) {
			$fp     =       fopen($file_name,"a+");
			if($flag==1){
				fwrite($fp,"(".$BLOCK."=====".$date.")    ");
				fwrite($fp,print_r($DATA,true));
				fwrite($fp,"\n");
			}
			else{
				fwrite($fp,"(".$BLOCK."=====".$date.")=====".$DATA."\n");
			}
			fclose($fp);
		}
	}
	//~ Function to fetch entities of a module from file rather than database
	//~ Parameters - $MasterName-module name,$clientId - id of client
	function masterDataValues($MasterName,$action='edit',$clientId=0,$type='TICKET',$param1='',$param2='',$param3=''){
		$type = strtoupper($type);
		$fpp  = fopen("/var/log/czentrix/CZCRM/log_test", 'a+');
		//~ fwrite($fpp,"inside nitin \n");
		fwrite($fpp,"Master Values--[".$MasterName."]--".$clientId."\n");
		if($clientId==0){
			$clientId    =    $_SESSION['CLIENT_ID'];
		}

		$jsonArr    =    array();

		if(!empty($clientId)){
			// $masterArray    =    array("ticket_type"=>"ticket_type_","disposition"=>"disposition_","sub_disposition"=>"sub_disposition_","priority"=>"priority_","ticket_status"=>"ticket_status_","users"=>"users_","department"=>"department_","country"=>"country_","state"=>"state_","city"=>"city_","lead_source"=>"lead_source_","product_type"=>"product_","lead_state"=>"lead_state_");
			$masterArray    =    array("ticket_type"=>"ticket_type","disposition"=>"disposition","sub_disposition"=>"sub_disposition","priority"=>"priority","ticket_status"=>"ticket_status","users"=>"users","department"=>"department","country"=>"country","state"=>"state","city"=>"city","lead_source"=>"lead_source","product_type"=>"product","lead_state"=>"lead_state","source"=>"source","priority_mapping"=>"priority_mapping");

			// $fileName    =    isset($masterArray[$MasterName])?$masterArray[$MasterName].$clientId:'';
			$fileName    =    isset($masterArray[$MasterName])?$masterArray[$MasterName].'.txt':'';

			if(!empty($fileName)){
				// $jsonData = file_get_contents("/var/www/html/CZCRM/master_data_config/".$fileName);
				$file_content="/var/www/html/CZCRM/master_data_config/".$clientId."/".$fileName;
				if(file_exists($file_content)){
					$jsonData = file_get_contents("/var/www/html/CZCRM/master_data_config/".$clientId."/".$fileName);
					fwrite($fpp,"Master Values--[".$jsonData."]--".$clientId."\n");
					$jsonAr = json_decode($jsonData,true);
				}
				if($action == 'add'){
					$case_fetch = "ACTIVE";
				}
				else if($action == 'search'){
					$case_fetch = "SEARCH";
				}else if($action == 'independent'){
					$case_fetch = "ACTIVE_ARRAY";
				}else if($action == 'cust_cond'){
					$case_fetch = "COND";
				}
				else if($action=="SEARCH_VAL"){
					$case_fetch = "SEARCH_VAL";
				}
				else{
					$case_fetch = "ALL";
				}

				if($MasterName == 'country' || $MasterName == 'state' || $MasterName == 'city'){
					if(!empty($param1) && !empty($param2) && !empty($param3)){
						$jsonArr = $jsonAr[$clientId][$case_fetch][$param1][$param2][$param3];
					}else if(!empty($param1) && !empty($param2) ){
						$jsonArr = $jsonAr[$clientId][$case_fetch][$param1][$param2];
					}else if(!empty($param1)){
					$jsonArr = $jsonAr[$clientId][$case_fetch][$param1];
					}
					else{
						$jsonArr = $jsonAr[$clientId][$case_fetch];
					}
				}else{
					if(!empty($param1) && !empty($param2) && !empty($param3)){
						$jsonArr = $jsonAr[$clientId][$type][$case_fetch][$param1][$param2][$param3];
					}else if(!empty($param1) && !empty($param2) ){
						$jsonArr = $jsonAr[$clientId][$type][$case_fetch][$param1][$param2];
					}else if(!empty($param1)){
						$jsonArr = $jsonAr[$clientId][$type][$case_fetch][$param1];
					}
					else{
						if(isset($jsonAr[$clientId][$type][$case_fetch])){
							$jsonArr = $jsonAr[$clientId][$type][$case_fetch];
						}
					}
				}

				/*print_r($fileName);
					print "===================";
					print_r($case_fetch);
				print_r($jsonAr[$clientId][$case_fetch]);*/
			}
		}
		//~ fwrite($fpp,"-------------nitin--------");
		//~ fwrite($fpp,$jsonAr[$clientId][$case_fetch]);
		//~ fwrite($fpp,$case_fetch);
		//~ fwrite($fpp,print_r($jsonArr,true));
		//~ fwrite($fpp,"\n");
		fwrite($fpp,"line 97==".print_r($jsonArr, true));
		fclose($fpp);

		return $jsonArr;
	}

	//----- START: Function to update ALL affected tables in case any parent entity changes -----
	//----- DEFINITION - Module(Entity) Updated, Id of entity updated, Updated Parent Ticket Type, Updated Parent Disposition
	function updateTablesOnParentChange($entity_updated='',$id_entity_updated='',$updated_ticket_type='',$updated_disposition=''){

		global $DB,$DB_H;

		$decrypted_id_entity_updated= decrypt_data($id_entity_updated);

		$sub_disposition_table = 'sub_disposition_tab';
		$priority_mapping_table = 'priority_mapping';
		//$ticket_details_table = 'ticket_details';

		switch ($entity_updated) {
			case "disposition":
			if($updated_ticket_type!=''){
				$setField = Array (
				'ticket_type_id'=>Array(STRING,$updated_ticket_type),
				);
				$setFieldPM = Array (
				'ticket_type'=>Array(STRING,$updated_ticket_type),
				);
				$where = Array (
				"disposition_id" => Array (STRING, $decrypted_id_entity_updated)
				);
				$dispositionTNameSD = $DB->UPDATE ($sub_disposition_table, $setField, $where, $DB_H);
				$dispositionTNamePM = $DB->UPDATE ($priority_mapping_table, $setFieldPM, $where, $DB_H);
				//$dispositionTNameTD = $DB->UPDATE ($ticket_details_table, $setField, $where, $DB_H);
			}
			break;
			case "sub_disposition":
			if($updated_ticket_type!='' && $updated_disposition!=''){
				// $setField = Array (
				// 'ticket_type_id'=>Array(STRING,$updated_ticket_type),
				// 'disposition_id'=>Array(STRING,$updated_disposition),
				// );
				$setFieldPM = Array (
				'ticket_type'=>Array(STRING,$updated_ticket_type),
				'disposition_id'=>Array(STRING,$updated_disposition),
				);
				$where = Array (
				"sub_disposition_id" => Array (STRING, $decrypted_id_entity_updated)
				);
				$subDispositionTNamePM = $DB->UPDATE ($priority_mapping_table, $setFieldPM, $where, $DB_H);
				//$subDispositionTNameTD = $DB->UPDATE ($ticket_details_table, $setField, $where, $DB_H);
			}
			break;
			default:
		}
	}
	//----- END: -----
	//----START: Function to return array for search parameters----
	function getArrval($arr,$level,$keyval)
	{
		$arrVal=array();
		$arrVal['']="None";
		if($level == "0"){
			foreach($arr as $key=>$val){
				if(!empty($key)) {
					$arrVal[trim($key)]=trim($key);
				}
			}
		}
		else if($level == "1") {
			foreach($arr as $key=>$val){
				$tmp_arr=array();
				if(isset($arr[$key][$keyval])) {
					$tmp_arr=explode(",",$arr[$key][$keyval]);
					foreach($tmp_arr as $key=>$val){
						if(!empty($val)) {
							$arrVal[trim($val)]=trim($val);
						}
					}
				}
			}
		}
		return $arrVal;
	}
	//----Function for license check
	function validateLicense($str){
		// print $str;
		if(!file_exists("/var/www/html/skipLicense")){
			if ($str) {
				$statcode=`/var/www/html/statcodegen`;
				$revstat="";
				$len=0;
				$czstr="";
				for($i=31;$i>=0;$i--){
					$revstat.=$statcode[$i];
				}
				$skip_bytes = 2;
				$use_bytes = 2;
				$bytePointer = 0;
				while($bytePointer < 64) {
					$bytePointer = $bytePointer + $skip_bytes;
					$tmpByteRead = $use_bytes;
					while($tmpByteRead) {
						if(isset($str[$bytePointer]))
						{
							$czstr .= $str[$bytePointer];
							$bytePointer++;
							$tmpByteRead--;
						}
						else
						{
							break;
						}

					}
				}
				if($czstr === $revstat){
					return 1;
				}
				else{
					return 0;
				}
				}else {
				return 0;
			}
		}
		else{
			return 1;
		}
	}


	function getLicense(){
		$key="11223344556677889900009988776655443322111122334455667788990000998877665544332211";
		$str=czentrixq($key,80,2,2);
		return $str;
		//return "czentrix";
	}
	//-------function to add ticketQueue List entry by vikas
	function appendQueue($data){
		$dataArr=json_decode($data,true);
		global $DB,$DB_H;
		$query_queue="insert into ticketQueue (ticket_id,action)values('".$dataArr["ticket_id"]."','".$dataArr["action"]."')";
		$DB->EXECUTE_QUERY($query_queue,$DB_H);
	}

	//----Check for comma as last character in input value and remove it
	function removeLastComma($input_value)
	{
		$len = strlen($input_value);
		$comma = substr($input_value,$len-1);
		$checkForCommaAtLast = strcmp($comma,",");

		//If last character of input number is a comma then remove that comma and start the validation
		if($checkForCommaAtLast == 0){
			$input_value = substr($input_value,0,$len-1);
		}
		return $input_value;
	}
	function getImage($mail_add,$source) {
		global $DB , $DB_H,$_BLANK_ARRAY;
		$img = _BLANK_;
		$taddress = "addressBook";
		if(strstr($mail_add,"<")){
			$splitForName = explode("<" , $mail_add);
			if(strstr($splitForName[1] , ">")){
				$splitForEmail = explode(">" , $splitForName[1]);
				$emailAdd = $splitForEmail[0];
			}
		}
		else{
			$emailAdd = $mail_add;
		}
		if($emailAdd){
			//~ $fwhere = " and email_address like '%".$emailAdd."%'";
			$fwhere = " and email_address like '".$emailAdd."%'";
		}
		else{
			$fwhere = " and email_address like ''";
		}
		$countRows = $_BLANK_ARRAY;
		$countRows[] = "count(1) as total";
		$countTable = $DB->SELECT ($taddress, $countRows,$_BLANK_ARRAY_, $fwhere, $DB_H);
		$tRows = $DB->FETCH_ARRAY ($countTable);
		$tRows = $tRows[0];
		$total = $tRows;
		//print "==============".$total."====";
		$a = addslashes($mail_add);
		//$b = addslashes($result["fromName"]);
		if(!$total) {
			if($source=="mail_from")
			{
				$img = "<img src ='./images/new_16/add-to-address-book.png' id='addressBookimg_mail_from' title='add to address book' onclick='javascript:displayoptions(\"".$a."\",\"addressBookimg_mail_from\",\"mail_from\")' style='display:'></img>";
			}
			elseif($source=="mail_bcc")
			{
				//$img = "<img src ='./images/new_16/add-to-address-book.png' id='addressBookimg_mail_bcc' title='add to address book' onclick='displayoptions(\"".$a."\",\"addressBookimg_mail_bcc\",\"mail_bcc\")'  style='display:'></img>";
			}
			elseif($source=="mail_cc")
			{
				//$img = "<img src ='./images/new_16/add-to-address-book.png' id='addressBookimg_mail_cc' title='add to address book' onclick='displayoptions(\"".$a."\",\"addressBookimg_mail_cc\",\"mail_cc\")'  style='display:'></img>";
			}
			elseif($source=="cust_email")
			{
				//$img = "<img  src ='./images/new_16/add-to-address-book.png' id='addressBookimg_cust_email' title='add to address book' onclick='displayoptions(\"".$a."\",\"addressBookimg_cust_email\",\"cust_email\")' style='display:'></img>";
			}
			elseif($source=="cc_addr")
			{
				//$img = "<img src ='./images/new_16/add-to-address-book.png' id='addressBookimg_mail_cc_addr' title='add to address book' onclick='displayoptions(\"".$a."\",\"addressBookimg_mail_cc_addr\",\"cc_addr\")' style='display:'></img>";
			}
			elseif($source=="bcc_addr")
			{
				//$img = "<img src ='./images/new_16/add-to-address-book.png' id='addressBookimg_mail_bcc_addr' title='add to address book' onclick='displayoptions(\"".$a."\",\"addressBookimg_mail_bcc_addr\",\"bcc_addr\")' style='display:'></img>";
			}
		}
		return $img;
	}

	function getSrcCreateImage($mail_mesg_content){
		// print "INSIDE FUNCTION";
		// print $mail_mesg_content;die;
		$hostname= gethostname();
		$imagearray = array();
		$img = array();
		preg_match_all('/<img[^>]+>/i',$mail_mesg_content, $imgTagArray,PREG_SET_ORDER);  //extract image tag

		for ($i = 0; $i < count($imgTagArray); $i++) {
			array_push($imagearray, $imgTagArray[$i][0]);  //push image tags in array
		}
		foreach( $imagearray as $img_tag){
			preg_match_all('/(src)=("[^"]*")/i',$img_tag, $imageinfo[$img_tag]); //find out src
		}
		$index=0;
		// print_r($imagearray);
		foreach($imagearray as $img_tag) {
			// print $index."============hello=============<br>";
			$ImageSrc = str_replace('"', '', $imageinfo[$img_tag][2][0]);   //img src
			preg_match('/data/',$ImageSrc,$match);/// meaning image coming in text/html with full code written in image src
			$existenceOfData = count($match);

			if($existenceOfData){

				list($prefix,$imgsrc) = explode(",", $ImageSrc);//$ImageSrc contains src in this format src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAABVYAAAMA"
				list($var1,$var2) = explode("/", $prefix);
				list($var3,$var4) = explode(";", $var2);
				$imgExt = $var3;
				if($_SESSION["CLIENT_ID"])
				{
					$imgNamePathtemp= "mail_attachments/$hostname/".$_SESSION["CLIENT_ID"]."/";
					$imgNamePath = "mail_attachments/$hostname/".$_SESSION["CLIENT_ID"]."/temp_".$index.str_replace('.','_',microtime(true));
					// $imgName = "mail_attachments/temp_".$index.str_replace('.','_',microtime(true));
				}
				else{
					$imgNamePathtemp= "mail_attachments/$hostname/".$_SESSION["CLIENT_ID"]."/";
					//       $imgName = "mail_attachments/temp_".$index.str_replace('.','_',microtime(true));
					$imgNamePath = "mail_attachments/$hostname/".$_SESSION["CLIENT_ID"]."/temp_".$index.str_replace('.','_',microtime(true));
				}

				$ingNameWithContent=explode("/",$imgNamePath);

				// $ingNameWithExt = $imgName.".".$imgExt;
				$ingNameWithContent=explode("_", $ingNameWithContent[2]);
				// print_r($ingNameWithContent);exit;
				$ingNamePathWithExt = $imgNamePath.".".$imgExt;
				$handle = fopen("/var/www/html/CZCRM/".$ingNamePathWithExt, 'w+') or die('Cannot open file:  '.$ingNamePathWithExt);
				$ImageSrcData = base64_decode($imgsrc);
				fwrite($handle, $ImageSrcData);
				fclose($handle);
				$temp1 = fopen("/var/log/czentrix/CZCRM/imgname",'a+');
				fwrite ($temp1,"/var/www/html/CZCRM/".$ingNamePathWithExt."\n");
				fwrite($temp1, $imgNamePath."\n");
				fclose($temp1);
				//  $mail_mesg_content = str_replace($ImageSrc,$ingNameWithContent[0]."_".$ingNameWithContent[1],$mail_mesg_content) ;

				$limit   = 1;
				//Mutt
				// $mail_mesg_content = str_replace_limit($ImageSrc,$imgNamePathtemp.$ingNameWithContent[0]."_".$ingNameWithContent[1]."_".$ingNameWithContent[2],$mail_mesg_content, $limit);
				//PHP
				$mail_mesg_content = str_replace_limit($ImageSrc,$ingNamePathWithExt,$mail_mesg_content, $limit);

				// $mail_mesg_content = preg_replace("/".$ImageSrc."/",$imgNamePathtemp.$ingNameWithContent[0]."_".$ingNameWithContent[1]."_".$ingNameWithContent[2],$mail_mesg_content,1) ;
			}

			$index++;
		}
		//  print "<br><hr>";
		//print "====================================================";
		// print $mail_mesg_content;
		return $mail_mesg_content;
	}






	// function getSrcCreateImage($mail_mesg_content){
	//          // print "INSIDE FUNCTION";
	//          // print $mail_mesg_content;die;
	// 					$hostname= gethostname();
	// 					$imagearray = array();
	// 					$img = array();
	// 					preg_match_all('/<img[^>]+>/i',$mail_mesg_content, $imgTagArray,PREG_SET_ORDER);  //extract image tag

	// 					for ($i = 0; $i < count($imgTagArray); $i++) {
	// 						array_push($imagearray, $imgTagArray[$i][0]);  //push image tags in array
	// 					}
	// 					foreach( $imagearray as $img_tag){
	// 								preg_match_all('/(src)=("[^"]*")/i',$img_tag, $imageinfo[$img_tag]); //find out src
	// 					}
	// 					$index=0;
	// 					print_r($imagearray);
	// 					foreach($imagearray as $img_tag) {
	// 						// print $index."============hello=============<br>";
	// 						 $ImageSrc = str_replace('"', '', $imageinfo[$img_tag][2][0]);   //img src

	// 						 preg_match('/data/',$ImageSrc,$match);/// meaning image coming in text/html with full code written in image src
	//   					     $existenceOfData = count($match);

	// print $existenceOfData;die;
	// 						 if($existenceOfData){

	// 							  list($prefix,$imgsrc) = explode(",", $ImageSrc);//$ImageSrc contains src in this format src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAABVYAAAMA"
	// 							  list($var1,$var2) = explode("/", $prefix);
	// 							  list($var3,$var4) = explode(";", $var2);
	// 							  $imgExt = $var3;
	// 							  if($_SESSION["CLIENT_ID"])
	// 							  {
	// 									 $imgNamePathtemp= "mail_attachments/$hostname/".$_SESSION["CLIENT_ID"]."/";
	// 									 $imgNamePath = "mail_attachments/$hostname/".$_SESSION["CLIENT_ID"]."/temp_".$index.str_replace('.','_',microtime(true));
	// 									// $imgName = "mail_attachments/temp_".$index.str_replace('.','_',microtime(true));
	// 							  }
	// 							  else{
	// 									 $imgNamePathtemp= "mail_attachments/$hostname/".$_SESSION["CLIENT_ID"]."/";
	// 								//	 $imgName = "mail_attachments/temp_".$index.str_replace('.','_',microtime(true));
	// 									 $imgNamePath = "mail_attachments/$hostname/".$_SESSION["CLIENT_ID"]."/temp_".$index.str_replace('.','_',microtime(true));
	// 							  }

	// 							  $ingNameWithContent=explode("/",$imgNamePath);

	// 							 // $ingNameWithExt = $imgName.".".$imgExt;
	// 							  $ingNameWithContent=explode("_", $ingNameWithContent[2]);
	// 							 // print_r($ingNameWithContent);exit;
	// 							  $ingNamePathWithExt = $imgNamePath.".".$imgExt;
	// 							  $handle = fopen("/var/www/html/CZCRM/".$ingNamePathWithExt, 'w+') or die('Cannot open file:  '.$ingNamePathWithExt);
	// 							  $ImageSrcData = base64_decode($imgsrc);
	// 							  fwrite($handle, $ImageSrcData);
	// 							  fclose($handle);
	// 							 $temp1 = fopen("/var/log/imgname",'a+');
	// 							   fwrite ($temp1,"/var/www/html/CZCRM/".$ingNamePathWithExt."\n");
	// 								fwrite($temp1, $imgNamePath."\n");
	// 								fclose($temp1);
	// 						    //  $mail_mesg_content = str_replace($ImageSrc,$ingNameWithContent[0]."_".$ingNameWithContent[1],$mail_mesg_content) ;

	// 							 $limit   = 1;
	//                //Mutt
	// 							$mail_mesg_content = str_replace_limit($ImageSrc,$imgNamePathtemp.$ingNameWithContent[0]."_".$ingNameWithContent[1]."_".$ingNameWithContent[2],$mail_mesg_content, $limit);
	// 							print $mail_mesg_content;die;
	//                //PHP
	//                //~ $mail_mesg_content = str_replace_limit($ImageSrc,$ingNamePathWithExt,$mail_mesg_content, $limit);

	// 						     // $mail_mesg_content = preg_replace("/".$ImageSrc."/",$imgNamePathtemp.$ingNameWithContent[0]."_".$ingNameWithContent[1]."_".$ingNameWithContent[2],$mail_mesg_content,1) ;

	// 						}

	// 					$index++;
	// 					}
	//         //  print "<br><hr>";
	// //print "====================================================";
	//          // print $mail_mesg_content;
	// 					return $mail_mesg_content;
	// 	}
	function str_replace_limit($search, $replace, $string, $limit = 1) {
		$pos = strpos($string, $search);

		if ($pos === false) {
			return $string;
		}

		$searchLen = strlen($search);

		for ($i = 0; $i < $limit; $i++) {
			$string = substr_replace($string, $replace, $pos, $searchLen);

			$pos = strpos($string, $search);

			if ($pos === false) {
				break;
			}
		}

		return $string;
	}


	function createMailAddr($mail_addr,$incoming_mail_adr)
	{
		$str_mail="";
		$is_id_exist="";
		if(!empty($mail_addr))
		{
			$arr_mail=explode(",",$mail_addr);
			foreach($arr_mail as $ak =>$av)
			{
				$is_id_exist =	preg_match("/$incoming_mail_adr/", $arr_mail[$ak],$matches);
				if(empty($is_id_exist))
				$str_mail.=$arr_mail[$ak].",";
			}
			$str_mail=preg_replace("/,$/","",$str_mail);
		}

		return $str_mail;
	}
	function Parent_lead_id($parent_id){
		global $DB , $DB_H,$_BLANK_ARRAY;
		//$parent_arr = $_BLANK_ARRAY
		GLOBAL $parent;
		// print "select child_leadID from merge_lead_status where parent_leadID in (".$parent_id.")";
		$q = "select child_leadID from merge_lead_status where parent_leadID in (".$parent_id.")";
		$qry = $DB->EXECUTE_QUERY($q,$DB_H);
		while($f =  $DB->FETCH_ARRAY($qry,MYSQLI_ASSOC))
		{
			$child_leadID = $f['child_leadID'];
			$parent .=$child_leadID.",";
			if(!empty($child_leadID))
			{
				$parent .=$child_leadID.",";
				Parent_lead_id($child_leadID);
			}
			//exit;
		}


		//print("====".$parent."====");
		return $parent;

	}

	function Parent_mail_lead_id($parent_id){
		global $DB , $DB_H,$_BLANK_ARRAY;
		//$parent_arr = $_BLANK_ARRAY
		GLOBAL $parent_mail_id;
		// print "select child_leadID from merge_lead_status where parent_leadID in (".$parent_id.")";
		$q1 = "select child_mailID from merge_mail_status where parent_mailID in (".$parent_id.")";
		$qry = $DB->EXECUTE_QUERY($q1,$DB_H);
		while($f =  $DB->FETCH_ARRAY($qry,MYSQLI_ASSOC))
		{
			$child_leadID = $f['child_mailID'];
			$parent_mail_id .=$child_leadID.",";
			if(!empty($child_leadID))
			{
				$parent_mail_id .=$child_leadID.",";
				Parent_mail_lead_id($child_leadID);
			}
			//exit;
		}


		//print("====".$parent."====");
		return $parent_mail_id;

	}
	//----END: Function to return array for search parameters----
	function write_log($message, $logfile='') {
		// Determine log file
		if($logfile == '') {
			// checking if the constant for the log file is defined
			if (defined(DEFAULT_LOG) == TRUE) {
				$logfile = DEFAULT_LOG;
			}
			// the constant is not defined and there is no log file given as input
			else {
				error_log('No log file defined!',0);
				return array(status => false, message => 'No log file defined!');
			}
		}

		// Get time of request
		if( ($time = $_SERVER['REQUEST_TIME']) == '') {
			$time = time();
		}

		// Get IP address
		if( ($remote_addr = $_SERVER['REMOTE_ADDR']) == '') {
			$remote_addr = "REMOTE_ADDR_UNKNOWN";
		}

		// Get requested script
		if( ($request_uri = $_SERVER['REQUEST_URI']) == '') {
			$request_uri = "REQUEST_URI_UNKNOWN";
		}

		// Format the date and time
		$date = date("Y-m-d H:i:s", $time);

		// Append to the log file
		if($fd = @fopen($logfile, "a")) {
			$result = fputcsv($fd, array($date, $remote_addr, $request_uri, $message));
			fclose($fd);

			if($result > 0)
			return array(status => true);
			else
			return array(status => false, message => 'Unable to write to '.$logfile.'!');
		}
		else {
			return array(status => false, message => 'Unable to open log '.$logfile.'!');
		}
	}
	function getClientIP() {

		if (isset($_SERVER)) {

			if (isset($_SERVER["HTTP_X_FORWARDED_FOR"]))
			return $_SERVER["HTTP_X_FORWARDED_FOR"];

			if (isset($_SERVER["HTTP_CLIENT_IP"]))
			return $_SERVER["HTTP_CLIENT_IP"];

			return $_SERVER["REMOTE_ADDR"];
		}

		if (getenv('HTTP_X_FORWARDED_FOR'))
		return getenv('HTTP_X_FORWARDED_FOR');

		if (getenv('HTTP_CLIENT_IP'))
		return getenv('HTTP_CLIENT_IP');

		return getenv('REMOTE_ADDR');
	}
	function integerCombobox($name, $start, $limit, $padding, $defval,$diff = 1,$selectedVal=NULL) {
		//echo $selectedVal;
		//exit;
		$numbers = range ($start, $limit, $diff);
		$maxLen = strlen ($limit);
		$str = "<select name='".$name."' id='".$name."' class='integerCombobox'>\n";
		$str .= "<option value=' '>".$defval."</option>\n";
		foreach ($numbers as $av) {
			if ($padding)
			$av = str_pad ($av, $maxLen, 0, STR_PAD_LEFT);
			$str .= "<option value='".$av."' ".($av==$selectedVal?'selected':'').">".$av."</option>\n";
		}
		$str .= "</select>\n";
		return $str;
	}
	//////////////////**************************Function added by vikas 19/07/2011 for curl request**************************////
	function do_remote($url,$fields_string,$received_headers="") {
		
		/********************change by Gaurav**********************/

		$client_id = isset($_SESSION['CLIENT_ID'])?$_SESSION['CLIENT_ID']:'';
		$user_id = isset($_SESSION['USER_ID'])?$_SESSION['USER_ID']:'';
		$session_id = isset($_SESSION['SESSION_ID'])?$_SESSION['SESSION_ID']:'';

		if(IsBase64($fields_string['postData']))
		$fields_string['postData'] = base64_decode($fields_string['postData']);

		$FLP = new logs_creation($client_id);
		$FLP->prepare_log("1","====fields_string===", $fields_string);

		$fields_arr = json_decode($fields_string['postData']);
		$fields_arr->user_id1 = $user_id;
		$fields_arr->session_id1 = $session_id;
		$fields_string['postData'] = json_encode($fields_arr);

		$FLP->prepare_log("1","====fields_string===", $fields_string);

		$fields_string['postData'] = base64_encode($fields_string['postData']);

		/**************************END****************************/

		$configArr = file_get_contents("/var/www/html/CZCRM/configs/config.txt");
		$configarr = json_decode($configArr,true);
		$ticket_app_name = $configarr['TICKET_APP_NAME'];

		$headers = array("X-CZApp: ".$ticket_app_name,"Cookie: ".session_name()."=".session_id());
		if(!empty($received_headers)){
			$headers = array_merge($headers,$received_headers);
		}
		$ch = curl_init ();
		curl_setopt($ch, CURLOPT_URL, "$url");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_HTTPHEADER,$headers);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT,30);

		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

		curl_setopt($ch,CURLOPT_POST,!empty($fields_string));
		curl_setopt($ch,CURLOPT_POSTFIELDS,$fields_string);

		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);


		$result=curl_exec($ch);

		if ($httpCode != 0){
			$result = "Error In Connection";
			return $result;
		}
		curl_close ($ch);

		$result_arr=json_decode($result);
		return $result_arr;
	}
	function curlReq( $url, $fields_string,$http_header = array() ) {
	$fields_string = $fields_string;
	$ch = curl_init ();
	curl_setopt($ch, CURLOPT_URL, "$url");
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	if( count($http_header) > 0 ) {
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_HTTPHEADER,  $http_header);
	}
	else {
		curl_setopt($ch, CURLOPT_HEADER, 0);
	}
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT,30);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
	if( !empty( $fields_string ) ) {
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch,CURLOPT_POSTFIELDS,$fields_string);
	}
	$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$result=curl_exec($ch);	
	if ( $httpCode != 0 ) {
		$result = "Error In Connection";
		return $result;
	}
	curl_close ($ch);
	return $result;
	}
	function do_remote_without_json($url,$fields_string="",$received_headers="") {
		$configArr = file_get_contents("/var/www/html/CZCRM/configs/config.txt");
		$configarr = json_decode($configArr,true);
		$ticket_app_name = $configarr['TICKET_APP_NAME'];

		$headers = array("X-CZApp: ".$ticket_app_name,"Cookie: ".session_name()."=".session_id());
		if(!empty($received_headers)){
			$headers = array_merge($headers,$received_headers);
		}
		$fields_string = $fields_string;
		$ch = curl_init ();
		curl_setopt($ch, CURLOPT_URL, "$url");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_HTTPHEADER,$headers);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT,30);

		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

		//  curl_setopt($ch,CURLOPT_POST,!empty($fields_string));
		if(!empty($fields_string)){
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch,CURLOPT_POSTFIELDS,$fields_string);
		}
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);


		$result=curl_exec($ch);

		if ($httpCode != 0){
			$result = "Error In Connection";
			return $result;
		}
		curl_close ($ch);

		return $result;
	}
	///////////////////*********************************//////////////////////
	function nullCheckR($val) {
		$as_arr = array();
		$as_arr= explode(".",$val);
		$as_val = str_replace(".","_",$as_arr[1]);
		$val = "if(isnull(".$val."),'',".$val.") as $as_val";
		return $val;
	}

	function nullCheck ($val) {
		$val = "if(isnull(".$val."),'',".$val.") as ".str_replace(".","_",$val);
		return $val;
	}


	function showErrors (& $eArr) {
		global $_ERROR_CODE;
		$retVal = "";
		foreach ($eArr as $av) {
			switch ($av->severtiy) {
				case 1 :
				$retVal .= $_ERROR_CODE[$av->errorCode] . "<br>";
				break;
				case 2 :
				$retVal .= $_ERROR_CODE[$av->errorCode] . "<br>";
				break;
				default:
				$retVal .= $_ERROR_CODE[$av->errorCode] . "<br>";
				break;
			}
		}
		return $retVal;
	}

	function arrayToString (& $value, $seprator) {
		$retValue = NULL;
		if (!is_array ($value))
		return $retValue;

		$retValue = _BLANK_;
		foreach ($value as $ak => $av) {
			$retValue .= $ak . "=" . "'" . $av . "'" . $seprator;
		}
		return $retValue;
	}


	function setLocalDBTimeZone ($tz) {
		$in = "SET SESSION time_zone='".$tz."'";
		$GLOBALS["DB"]->EXECUTE_QUERY ($in, $GLOBALS["DB_H"]);
	}


	function priorityArray () {
		$arr = array (
		"normal" => "Normal",
		"high" => "High",
		"critical" => "Critical"
		);
		return $arr;
	}

	//Below mention function with where condition 28-07-2011 Gaurav
	function valueArray ($tName,$value,$display,$where,$extraParam="",$defVal="") {

		global $DB,$DB_H;
		$f=array($value,$display);

		$tName=$DB->SELECT($tName,$f,$where,$extraParam,$DB_H);
		$arr = array();
		if(!empty($defVal)){

			$arr[""]=$defVal;
		}

		while($f=$DB->FETCH_ARRAY($tName,MYSQLI_ASSOC))
		{
			if(!empty($f[$value])){
				$arr[$f[$value]]=$f[$display];
			}
		}

		return $arr;
	}

	function valueArray2 ($tName,$value,$display,$where,$extraParam="",$defVal="") {
		global $DB,$DB_H;
		$f=array($value,$display);

		$table = $tName;
		$tName=$DB->SELECT($tName,$f,$where,$extraParam,$DB_H);
		$arr = array();
		if(!empty($defVal)){

			$arr[""]=$defVal;
		}

		while($f=$DB->FETCH_ARRAY($tName,MYSQLI_ASSOC))
		{
			if(!empty($f[$value])){
				$arr[$f[$value]]=$f[$display];
			}
		}
		return $arr;
	}


	function valueArray3 ($tName,$value,$where,$extraParam="",$defVal="") {
		//print $where;
		global $DB,$DB_H;
		$f=array($value);
		$table = $tName;
		$tName=$DB->SELECT($tName,$f,$where,$extraParam,$DB_H);
		// print $DB->getLastQuery();
		$arr = array();
		if(!empty($defVal)){

			$arr[""]=$defVal;
		}

		while($f=$DB->FETCH_ARRAY($tName,MYSQLI_ASSOC))
		{
			if(!empty($f[$value])){
				$arr[]=mysqli_real_escape_string($DB_H,$f[$value]);
			}
		}
		// print_r($arr);
		return $arr;
	}


	function valueArrayExt($tName,$value,$display,$where,$extraParam="",$defVal="") {
		global $DB,$DB_H;
		$valu = $value;
		$disp = $display;
		if(strstr($value, '.') !== false){
			$valueArrays = explode(".",$value);
			$valu = $valueArrays[1];
		}
		if(strstr($display, '.') !== false){
			$displayArrays = explode(".",$display);
			$disp = $displayArrays[1];
		}

		$f=array($value,$display);

		$tName=$DB->SELECT($tName,$f,$where,$extraParam,$DB_H);
		$arr = array();
		if(!empty($defVal)){

			$arr[""]=$defVal;
		}

		while($f=$DB->FETCH_ARRAY($tName,MYSQLI_ASSOC))
		{

			if(!empty($f[$valu])){
				$arr[$f[$valu]]=$f[$disp];
			}
		}

		return $arr;
	}







	function logCallHistory ($arr) {
		$tName = "call_history";
		//print_r($arr);
		$f = array (
		"ct_num" => array (STRING, $arr["ct_num"]),
		"user_id" => array (STRING, $arr["user_id"]),
		"user_name" => array (STRING, $arr["user_name"]),
		"agent_id" => array (STRING, $arr["agent_id"]),
		"session_id" => array (STRING, $arr["session_id"]),
		"monitor_file_name" => array (STRING, $arr["monitor_file_name"]),
		"date_entered" => array (MYSQL_FUNCTION, "NOW()")
		);
		return $GLOBALS["DB"]->INSERT ($tName, $f, $GLOBALS["DB_H"]);
	}





	function showCallHistory (& $condArr, $ticketID) {
		global $DB, $DB_H, $_BLANK_ARRAY;

		$headers = $_BLANK_ARRAY;
		$headers[] = new GridProperty ("Call Date", "date_entered", _PLAIN_TEXT_, _BLANK_, NO, NO, NO, _BLANK_, _BLANK_, _BLANK_, _BLANK_, YES);
		$headers[] = new GridProperty ("Username", "user_name", _PLAIN_TEXT_, _BLANK_, NO, NO, NO, _BLANK_, _BLANK_, _BLANK_, _BLANK_, YES);
		$headers[] = new GridProperty ("Agent ID", "agent_id", _PLAIN_TEXT_, _BLANK_, NO, NO, NO, _BLANK_, _BLANK_, _BLANK_, _BLANK_, YES);
		$headers[] = new GridProperty ("Session ID", "session_id", _PLAIN_TEXT_, _BLANK_, NO, NO, NO, _BLANK_, _BLANK_, _BLANK_, _BLANK_, YES);
		$headers[] = new GridProperty ("File Name", "monitor_file_name", _HREF_, _BLANK_, NO, NO, NO, "target='_blank'", "playCallRecord.php?record="._VAL_, _BLANK_, _VAL_, YES);


		if($_SESSION['USERNAME']=="admin")
		{
			$headers[] = new GridProperty (_BLANK_, "monitor_file_name", _HREF_, _BLANK_, NO, NO, NO, _BLANK_, "downloadSoundFile.php?host=".urlencode ($_SESSION["TELEPHONY_BOX_IP"])."&record="._VAL_, _BLANK_, " Download ", false);
		}

		$tAtt = array (
		"width"			=>	"20%",
		"cellspacing"	=>	"0",
		"cellpadding"	=>	"5",
		"border"		=>	"0",
		"class"			=>	"listView",
		"height"		=>	"32"
		);
		$rAtt = array (
		"height"		=>	"20"
		);
		$thAtt = array (
		"width"			=>	"18%",
		"align"			=>	"center",
		"class"			=>	"listViewThS1",
		"scope"			=>	"col",
		"nowrap"		=>	"yes"
		);
		$tdAtt = array (
		"valign"		=>	"top",
		"class"			=>	"oddListRowS1",
		"scope"			=>	"row",
		"nowrap"		=>	"yes",
		"bgcolor"		=>	"#FAF1CF",
		"align"			=>	"center"
		);

		$str = _BLANK_;
		$GRID = new Grid($headers);
		$GRID->setKeyField ("date_entered");
		$GRID->enablePrintSave(NO);
		$GRID->setTableAttributes ($tAtt);
		$GRID->setTrAttributes ($rAtt);
		$GRID->setTdAttributes ($thAtt);

		$tName = "call_history";
		$f = array (
		"user_id",
		"user_name",
		"agent_id",
		"session_id",
		"monitor_file_name",
		"date_entered",
		//"CONCAT(DATE_FORMAT(DATE(date_entered), '".$_SESSION["CRM_DATE_FORMAT"]."'), ' ', TIME(date_entered)) AS date_entered_changed"
		);
		$where = $condArr;
		$tName = $DB->SELECT ($tName, $f, $where, " ORDER BY date_entered ASC ", $DB_H);
		//print($DB->getLastQuery());


		$str .= $GRID->startGrid (NO, NO, NO);
		$str .= $GRID->getHeaderRow ();

		$tdColor = _BLANK_;
		$i = 0;
		while ($f = $DB->FETCH_ARRAY ($tName, MYSQLI_ASSOC)) {
			if (($i % 2) == 0)
			$tdAtt["bgcolor"] = "#FFFFFF";
			else
			$tdAtt["bgcolor"] = "#F1F1F1";

			$GRID->setTdAttributes ($tdAtt);
			$str .= $GRID->setResultRow ($f);
			$i++;
		}
		$str .= $GRID->endGrid ();
		//----------------End Grid Code -------------------------------------------------------------------

		//-----------------Start Table View ------------------------------
		$record_id = $property_div = $file_to_forward = _BLANK_;
		$extraParameters = "<input type='button' id='addCallHistoryBtn' value='Add Call History' class='button' onclick='javascript:openPopup(\"fetchCallInfo.php?ticketID=".$ticketID."\",\"width=800,height=500,directories=no,resizable=yes,scrollbars=yes\");' />";
		$caption = "Call History";

		include(_INCLUDE_PATH."generateTableView.php");
		//-------------------End Table View -----------------------------
	}
	function getUsername ($userID, &$fieldsArr) {
		global $DB, $DB_H;
		$tName = "users";
		$f =& $fieldsArr;
		$where = array ();
		$where["user_id"] = array ();
		$where["user_id"][] = STRING;
		$where["user_id"][] = $userID;

		$tName = $DB->SELECT ($tName, $f, $where, _BLANK_, $DB_H);
		$tName = $DB->FETCH_ARRAY ($tName, MYSQLI_ASSOC);
		return $tName;
	}
	//~ function getUserHierarchyID($userID) {
	//~ global $DB, $DB_H;
	//~ $tName = "user_hierarchy";
	//~ $f =array("reporting_to_user_id");
	//~ $where = array ();
	//~ $where["reporting_by_user_id"] = array ();
	//~ $where["reporting_by_user_id"][] = STRING;
	//~ $where["reporting_by_user_id"][] = $userID;

	//~ $tName = $DB->SELECT ($tName, $f, $where, _BLANK_, $DB_H);
	//~ $tName = $DB->FETCH_ARRAY ($tName, MYSQLI_ASSOC);
	//~ return $tName;
	//~ }


	function getUsernameinfo ($userID) {
		global $DB, $DB_H;
		$user_name='';
		if(!empty($userID))
		{
			$tName = "users";
			$f =array("user_name");
			$where = array ();
			$where["user_id"] = array ();
			$where["user_id"][] = STRING;
			$where["user_id"][] = $userID;

			$tName = $DB->SELECT ($tName, $f, $where, _BLANK_, $DB_H);
			$tName = $DB->FETCH_ARRAY ($tName, MYSQLI_ASSOC);
			$user_name=$tName["user_name"];

		}
		return $user_name;
	}

	// 4,October 2011 : Deepak Malik
	// Function to get a field value based on id from a table.

	function convertId($tName,$fGiven,$fNeeded,$value)
	{
		global $DB, $DB_H;
		$fields=array($fGiven);
		$Where=array($fNeeded=>array(STRING,$value));
		$result=$DB->SELECT($tName,$fields,$Where,_BLANK_,$DB_H);
		if($row=$DB->FETCH_ARRAY($result,MYSQLI_ASSOC)) {
			$ret=$row[$fGiven];
			if(empty($ret))
			{
				$ret=false;
			}
		}
		else {
			$ret=false;
		}
		return($ret);
	}

	function createTemplate($strTemplate)
	{
		$finalStr = '';
		$errFlag = false;
		$initial = explode("[[",$strTemplate);
		if(!empty($initial[0]))
		{
			$finalStr = $finalStr.'print '."'$initial[0]';";
		}
		for($i=1;($i<count($initial)) && !$errFlag;$i++)
		{
			$final = explode("]]",$initial[$i]);
			$fields = explode(".",$final[0]);

			if (!strcasecmp($fields[0],"CRM"))
			{
				$fname = $fields[1];
				if(isset($fields[2]))
				{
					if(!strcasecmp($fields[2],"VAL"))
					{
						$finalStr = $finalStr."\$field_info['".$fname."']['done']=1;";
						$finalStr = $finalStr."print \$actualValues[\$field_info['".$fname."']['name']];";
						//$finalStr = $finalStr."print htmlspecialchars(\ $actualValues->\$cust['".$fname."']['name'],ENT_QUOTES);";
					}
					else if(!strcasecmp($fields[2],"LABEL"))
					{
						$finalStr = $finalStr."print \$actualValues[\$field_info['".$fname."']['name']];";
						//$finalStr = $finalStr."print htmlspecialchars(\$custf->\$cust['".$fname."']['name'],ENT_QUOTES);";
					}
					else if(!strcasecmp($fields[2],"EXIST"))
					{
						$finalStr = $finalStr."\$field_info['".$fname."']['done']=1;";
						//$finalStr = $finalStr."print htmlspecialchars(\$custf->\$cust['".$fname."']['name'],ENT_QUOTES);";
					}
					else
					{
						$errFlag = true;
						print "Error in script. Wrong Field:- $fname Type:- $fields[2]  used for Value or Label";
					}
				}
				else
				{
					$finalStr = $finalStr."\$field_info['".$fname."']['done']=1;";
					$finalStr = $finalStr."createField(\$field_info['".$fname."'] , 'YES');";
					//$finalStr = $finalStr."createField(\$field_info['".$fname."']['type'],\$field_info['".$fname."']['name'],\$field_info['".$fname."']['tmpname'],\$actualValues,\$field_info['".$fname."']['displayname']);";
				}
			}
			else
			{
				$errFlag = true;
				print "Error in Customized Value";
			}
			if (!empty($final[1]))
			{
				//print "'$final[1]'";
				$finalStr = $finalStr.'print '."'$final[1]';";
			}
		}
		if($errFlag)
		{
			return 0;
		}
		else
		{
			$finalStr = preg_replace("/temparea/","textarea",$finalStr);
			return $finalStr;
		}
	}


	function ValidationFunction($moduleName){
		// print "here";die;
		$error_hash="";
		include('ValidationInfo/'.$moduleName.'_Validation_info.php');
		require_once ("/var/www/html/"._BASEDIR_."/formvalidator.php");
		$validator = new FormValidator();
		$ValidationInformationArr	=	json_decode($ValidationInformation);
		foreach($ValidationInformationArr as $key=>$val){
			$NameField	=	$val->name;
			$ValField	=	$val->ValidationType;
			$ValError	=	$val->ValidationError;

			$FieldName	=	isset($val->FieldName)?$val->FieldName." ":'';

			$ValFieldarray	=	explode('#TVT#',$ValField);
			$ValErrorarray	=	explode('#TVT#',$ValError);
			foreach($ValFieldarray as $k1=>$validType){
				$errorStr	=	$FieldName.$ValErrorarray[$k1];
				$validator->addValidation($NameField,$validType,$errorStr);
			}
		}
		if(!$validator->ValidateForm()){
			$error_hash = $validator->GetErrors();
		}
		return	$error_hash;
	}
	function api_ValidationFunction($moduleName){
		$ftph=fopen("/var/log/czentrix/CZCRM/log_sync.txt","a+");
		//~ fwrite($ftph,"inside function api_ValidationFunction");
		// print "here";die;
		$error_hash="";

		include('../ValidationInfo/'.$moduleName.'_Validation_info.php');
		//~ fwrite($ftph,"inside function api_ValidationFunction");
		//~ fwrite($ftph,$ValidationInformation);

		require_once ("../formvalidator.php");

		$validator = new FormValidator();
		$ValidationInformationArr	=	json_decode($ValidationInformation);
		//~ fwrite($ftph,"line1151----------------------------------------------------------------------------------------------------nitin");
		fwrite($ftph,print_r($ValidationInformationArr,true));
		foreach($ValidationInformationArr as $key=>$val){
			fwrite($ftph,"===========key=============".$key);
			fwrite($ftph,"===========val=============".print_r($val,true));
			$NameField	=	$val->name;
			$ValField	=	$val->ValidationType;
			$ValError	=	$val->ValidationError;
			$FieldName	=	isset($val->FieldName)?$val->FieldName." ":'';
			$ValFieldarray	=	explode('#TVT#',$ValField);
			$ValErrorarray	=	explode('#TVT#',$ValError);
			fwrite($ftph,print_r($ValFieldarray,true));
			fwrite($ftph,print_r("ValErrorarray===================",true));
			fwrite($ftph,print_r($ValErrorarray,true));
			foreach($ValFieldarray as $k1=>$validType){
				//~ fwrite($ftph,"k1==========".$k1);
				//~ fwrite($ftph,"validType==========".$validType."<br>");
				$errorStr	=	$FieldName.$ValErrorarray[$k1];
				$validator->addValidation($NameField,$validType,$errorStr);

				fwrite($ftph,"call validator =============line 1169==============".print_r($validator,true));
			}
		}
		if(!$validator->ValidateForm()){
			$error_hash = $validator->GetErrors();
		}
		fclose($ftph);
		return	$error_hash;
	}
	function base64url_encode($data) {
		return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
	}

	function base64url_decode($data) {
		return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
	}
	function encrypt_data($msg,$keypass=''){
		$msg	=	"_".$msg."_";
		$method	=	'aes256';
		// print "<hr>ENCRYPT";
		$ivhex	=	_IV_HEX_;
		if(empty($keypass)){
			$pass	=	_KEYPASSWORD_;
		}
		else
		{
			$pass = $keypass;
		}
		// print "<hr>";

		$encryptedData	=	openssl_encrypt($msg, $method, $pass,true,$ivhex);
		// print $encryptedData;
		//print(base64url_encode($encryptedData));
		return base64url_encode($encryptedData);
	}
	function decrypt_data($encryptedData,$keypass=''){
		//print($encryptedData);
		$method	=	'aes256';
		// print "<hr>DECRYPT";
		$ivhex  = _IV_HEX_;
		if(empty($keypass)){
			$pass = _KEYPASSWORD_;
		}
		else
		{
			$pass = $keypass;
		}
		// print "<hr>";

		$encryptedData	=	base64url_decode($encryptedData);
		$decryptedData	=	openssl_decrypt($encryptedData, $method, $pass,true,$ivhex);
		$prefix			=	substr($decryptedData,0,1);
		$suffix			=	substr($decryptedData,-1);
		if($prefix=='_' && $suffix=='_'){
			$decryptedData	=	str_replace("_","",$decryptedData);
		}
		else{
			$decryptedData	=	"";
		}
		return $decryptedData;
	}
	function encrypt_api($msg,$keypass=''){
		$msg	=	"_".$msg."_";
		$method	=	'aes256';
		// print "<hr>ENCRYPT";
		$ivhex	=	'12345';
		if(empty($keypass)){
			$pass	=	'CZENTRIX';
		}
		else
		{
			$pass = $keypass;
		}
		// print "<hr>";

		$encryptedData	=	openssl_encrypt($msg, $method, $pass,true,$ivhex);
		//print(base64url_encode($encryptedData));
		return base64url_encode($encryptedData);
	}
	function decrypt_api($encryptedData,$keypass=''){
		// print($encryptedData);
		$method	=	'aes256';
		// print "<hr>DECRYPT";
		$ivhex  = '12345';
		if(empty($keypass)){
			$pass = 'CZENTRIX';
		}
		else
		{
			$pass = $keypass;
		}
		// print "<hr>";

		$encryptedData	=	base64url_decode($encryptedData);
		// print $encryptedData;
		$decryptedData	=	openssl_decrypt($encryptedData, $method, $pass,true,$ivhex);
		$prefix			=	substr($decryptedData,0,1);
		$suffix			=	substr($decryptedData,-1);
		// print $decryptedData;
		if($prefix=='_' && $suffix=='_'){
			$decryptedData	=	str_replace("_","",$decryptedData);
		}
		else{
			$decryptedData	=	"";
		}
		return $decryptedData;
	}
	function CreateJsonValidaton($FormName,$config){
		$JsonStr				=	"";
		$ValidationArray		=	array();
		if((is_array($config)) && (!empty($config))){
			foreach($config as $key=>$val){
				$ValidationArray[]	=	array(
				"name"				=>	isset($config[$key]['name'])?$config[$key]['name']:'',
				"ValidationType"	=>	isset($config[$key]['ValidationType'])?$config[$key]['ValidationType']:'',
				"ValidationError"	=>	isset($config[$key]['ValidationError'])?$config[$key]['ValidationError']:'',
				"FieldName"			=>	isset($config[$key]['ErrorField'])?$config[$key]['ErrorField']:'',
				);
			}
		}
		if(count($ValidationArray)>0){
			$JsonStr	=	json_encode($ValidationArray);
		}
		$folderName	=	'ValidationInfo';
		$ValidationFile = $FormName.'_Validation_info.php';
		$FilePath	=	$folderName."/".$ValidationFile;
		if(!file_exists($FilePath)){
			if(!empty($JsonStr)) {
				exec("touch $FilePath");
				exec("chmod 777 $FilePath");
				$JsonStr=preg_replace("/,$/","",$JsonStr);
				$fp=fopen("$FilePath","w");
				fwrite($fp,'<?php'."\n");
				fwrite($fp,'$ValidationInformation =');
				fwrite($fp,"'".$JsonStr."';\n");
				fwrite($fp,'?>'."\n");
				fclose($fp);
			}
			}else if(!empty($JsonStr)) {
			$JsonStr=preg_replace("/,$/","",$JsonStr);
			$fp=fopen("$FilePath","w");
			fwrite($fp,'<?php'."\n");
			fwrite($fp,'$ValidationInformation =');
			fwrite($fp,"'".$JsonStr."';\n");
			fwrite($fp,'?>'."\n");
			fclose($fp);
		}

	?>
	<script language="Javascript" type="text/javascript">
		var formNameArray = document.getElementsByTagName("form");
		for(var k=0;k<formNameArray.length;k++){
			var tmpEle = document.createElement("input");
			tmpEle.type = "hidden";
			tmpEle.name = "hiddenUq";
			tmpEle.value = "<?=$_SESSION['uniquetime']?>";
			formNameArray[k].appendChild(tmpEle);
		}
	</script>
	<?php
		if(!empty($JsonStr)){
			return $JsonStr;
		}

	}

	function ShowErrorDiv($error_hashArray){
		$error_flag = 0;
		$error_div="";
		if((is_array($error_hashArray))&&(count($error_hashArray))){
			//~ $error_div =  "<div id='error_div11' style='background-color: red; color: white; font-size: 16px; padding: 1px; border-radius: 5px; width: auto; font-weight: bold;'><img onclick='document.getElementById(\"error_div11\").style.display=\"none\";' src='/images/icon_close.png'>";
			foreach($error_hashArray as $inpname => $inp_err){
				foreach($inp_err as $errors){
					// echo "<p>$errors</p>\n";
					$error_div .= "$errors!!";
					//~ $error_div .= "$errors \r\n";
					$error_flag=1;
				}
			}
			//$error_div.= "</div>\n";
		}
		$errorss = $error_flag."#TVT#".$error_div;
		return $errorss;
	}
	function GENERATELOGS_DECISION($DATA,$BLOCK,$flag=0){
		//$file_name = $file_name;
		//if(empty($file_name)){
		$file_name      =       "/var/log/czentrix/CZCRM/decision_maker_generation";
		//}
		$date   =       date("Y-m-d H:i:s");
		if(file_exists($file_name)) {
			$fp     =       fopen($file_name,"a+");
			if($flag==1){
				fwrite($fp,"(".$BLOCK."=====".$date.")    ");
				fwrite($fp,print_r($DATA,true));
				fwrite($fp,"\n");
			}
			else{
				fwrite($fp,"(".$BLOCK."=====".$date.")=====".$DATA."\n");
			}
			fclose($fp);
		}
	}

	function getFileName($source,$client_id){
		$folder_name = 'file_data';
		$path = '/var/www/html/CZCRM/'.$folder_name.'/'.$client_id.'/';
		if(!file_exists($path)){
			exec("mkdir $path");
			exec("chmod 777 $path");
		}
		$fileArr  = array('email'=>"email.txt",'phone'=>"phone_num.txt",'mobile'=>"phone_num.txt",'docket'=>"docket_num.txt",'lead'=>"lead_num.txt",'report_links'=>"report_link.txt","message_id"=>"message_id.txt",'ticket_summary'=>"ticket_summary.txt");
		return $path.$fileArr[$source];
	}
	// function getFileName($source,$client_id){
	// 	$path = '/var/www/html/CZCRM/';
	// 	$fileArr  = array('email'=>"email_{$client_id}.txt",'phone'=>"phone_num_{$client_id}.txt",'mobile'=>"phone_num_{$client_id}.txt",'docket'=>"docket_num_{$client_id}.txt",'lead'=>"lead_num_{$client_id}.txt",'report_links'=>"report_link_{$client_id}.txt","message_id"=>"message_id_{$client_id}.txt",'ticket_summary'=>"ticket_summary_{$client_id}.txt");
	// 	return $path.$fileArr[$source];
	// }
	function insertDocketSource($source,$source_info,$person_id,$client_id,$type='WRITE'){
		$redis_key = 'bulk_upload';
		$FLP = new logs_creation($client_id);
		$configArr = file_get_contents("/var/www/html/CZCRM/configs/config.txt");
		$configarr = json_decode($configArr,true);
		$TICKETING_IP = $configarr['TICKETING_IP'];
		$path = "/CZCRM/";
		$PROTOCOL = $configarr['PROTOCOL'];
		$CALL_API_DNS = $configarr['CALL_API_DNS'];
		global $DB,$DB_H;
		//~ $sourceVal	=	$source_info."#TVT#".$person_id;
		$sourceVal	=	"#TVT#".$source_info."#TVT#".$person_id."#TVT#";
		$dataArray = array($source=>$sourceVal,"client_id"=>$client_id,"event"=>"file_write","type"=>$type);
		$dataJson	=	base64_encode(json_encode($dataArray));
		$fp	=	fopen("/var/log/czentrix/CZCRM/redis_logs", 'a+');
		fwrite($fp, "-----------------\n\r START TIME :". date("Y-m-d hh:mi:ss"));
		try{
			fwrite($fp, "-----------------\n\r STEP : 1");
			require_once("/var/www/html/CZCRM/classes/redisHandler.class.php");
			fwrite($fp, "-----------------\n\r STEP : 2");
			$redis = new redisHandler();
			fwrite($fp, "-----------------\n\r REDIS HANDLER :".print_r($redis,true));
			$redis->lpushRedis($redis_key,$dataJson);
			fwrite($fp,'-----------------\n\r docket_source: {"'.$source.'":"'.$sourceVal.'","client_id":"'.$client_id.'"}');
			fwrite($fp, "-----------------\n\r END TIME :". date("Y-m-d hh:mi:ss")."\n\r\n\r");
			fclose($fp);
		}
		catch (Exception $e) {
			//$URL = $PROTOCOL."://".$TICKETING_IP.$path."service_person_details.php?data=$dataJson";
			//$URL = $CALL_API_DNS."/service_person_details.php?data=$dataJson";
			//$result_esc=do_remote_without_json($URL,"");
			$getHostNamesJson	=	file_get_contents('/var/www/html/CZCRM/configs/hostname.json');
			$getHostNamesArr	=	json_decode($getHostNamesJson, true);
			$getHostNames		=	$getHostNamesArr['APP_SERVER'];
				
			$nonAccessableHost = array();
			$accessableHost = '';
			$isError = false;
			foreach ($getHostNames as $hostname) {	
				$URL = "http://$hostname/CZCRM/service_person_details.php?data=$dataJson";
				$result_esc=do_remote_without_json($URL,"");
				if($result_esc!=''){
					$result_esc = trim($result_esc);
					$RESULT = strip_tags($result_esc);
					$RES = preg_replace("/[\n\r]/","",$RESULT); 
					$NEWRES .= $RES.',';
					$NEWRES = trim(trim($NEWRES),',');
					if(!empty($NEWRES)){
						array_push($nonAccessableHost, $hostname);
						$isError = true;
					}else{
						$accessableHost = $hostname; 
					}
				}
			}
			if($isError && ($accessableHost!='')){
				foreach($nonAccessableHost as $host){
					$url = "http://%HOSTNAME%/CZCRM/service_person_details.php";
					$post_data = $dataJson;
					if(($host == 'localhost') || ($host == '127.0.0.1')){
						$url_host = getHostName();
					}else{
						$url_host = $host;
					}
					$created_on = date('Y-m-d H:i:s');
					$request_host= gethostname();
					$error = 'server not accessable';
					$query1 = "INSERT INTO `pending_requests` (module_name,url_host,url,post_data,request_host,error,created_on) VALUES ('service_person_details','".$url_host."','".$url."','".$post_data."','".$request_host."','".$error."','".$created_on."')";
					$exe_query1 = $DB->EXECUTE_QUERY($query1,$DB_H);
				}
			}
			$FLP->prepare_log("18",$e,"REDIS ERROR");
		}
	}
	/*function writeToFile($val,$source,$client_id,$actual_data=''){
		$fileName	=	getFileName($source,$client_id);
		if(file_exists($fileName)){
			exec("chmod 777 $fileName");
		}
		else{
			exec("touch $fileName");
			exec("chmod 777 $fileName");
		}
		if(file_exists($fileName)){
			$val = trim($val);
			$value = explode("#TVT#", $val);
			$value_check	=	$value['0']."#TVT#";
			if(!empty($val)){
				exec("grep -i -n '".$value_check."' $fileName", $output);
				if(count($output)==0){
					$fp	=	fopen($fileName, 'a+');
					if(!empty($actual_data)){
						fwrite($fp, "'".$actual_data."'");
					}
					else{
						fwrite($fp, "'".$val."'");
					}
					fwrite($fp,"\n");
					fclose($fp);
				}
				else{
					$resp	=	explode(":",$output[0]);
					// $resp2	=	explode("#TVT#",$resp[1]);
					$lineNum=	$resp[0];
					// $idval	=	$resp2[1];
					//~ $cmd	=	"sed -i '".$lineNum."s/".$resp['1']."/".$val."/' $filePath";
					$cmd	=	"sed -i '".$lineNum."s/".$resp['1']."/".$val."/' $fileName";
					exec($cmd);
				}
			}
		}
	}*/
	function deleteFromFile($val,$source,$client_id){
		$fpp  = fopen("/var/log/czentrix/CZCRM/delete_log", 'a+');
		fwrite($fpp, "Inside function deleteentry\n");
		$fileName       =       getFileName($source,$client_id);
		if(file_exists($fileName)){
			exec("chmod 777 $fileName");
			$val = trim($val);
			$value = explode("#TVT#", $val);
			$value_check_entity    =       "#TVT#".$value['1']."#TVT#";
			$value_check_id    =       "#TVT#".$value['2']."#TVT#";
			$removeString = "/".$value_check_id."/d";
			fwrite($fpp, $removeString);
			fwrite($fpp, $val);
			if(!empty($val)){
				fwrite($fpp, "here 1 delete\n");
				exec("grep -n '".$value_check_id."' $fileName", $output_id);
				fwrite($fpp,print_r($output_id));

				if(count($output_id)>0){
					exec("sed -i '".$removeString."' $fileName");
				}
			}
		}
	}
	function writeToFile($val,$source,$client_id,$actual_data=''){
		$fpp  = fopen("/var/log/czentrix/CZCRM/writelogs", 'a+');
		fwrite($fpp, "Inside function writeToFile\n");
		fwrite($fpp, "actual data= $actual_data\n");
		fwrite($fpp, "source= $source\n");
		$fileName       =       getFileName($source,$client_id);
		if(file_exists($fileName)){
				fwrite($fpp, "Inside file exists\n");
				fwrite($fpp, "filename = $fileName\n");
				exec("chmod 777 $fileName");
		}
		else{
				fwrite($fpp, "Inside to else\n");
				fwrite($fpp, "filename = $fileName\n");
				exec("touch $fileName");
				exec("chmod 777 $fileName");
		}
		if(file_exists($fileName)){
				fwrite($fpp, "Inside file exists again\n");
				$val = trim($val);
				$value = explode("#TVT#", $val);
				$value_check_entity    =       "#TVT#".$value['1']."#TVT#";
				$value_check_id    =       "#TVT#".$value['2']."#TVT#";
				fwrite($fpp, $val);
				if(!empty($val)){
						fwrite($fpp, "here 1\n");
						fwrite($fpp,$value_check_id."\n");
						//exec("grep -i -n '".$value_check_entity."' $fileName", $output_entity);
						exec("grep -n '".$value_check_id."' $fileName", $output_id);
						//if((count($output_entity)==0) && (count($output_id)==0)){
						if(count($output_id)==0){
								fwrite($fpp, "123\n");
								$fp     =       fopen($fileName, 'a+');
								if(!empty($actual_data)){
									fwrite($fpp,"hwere 111\n");
									fwrite($fp, "'".$actual_data."'");
								}
								else{
									fwrite($fpp,"hwere \n");
									fwrite($fp, "'".$val."'");
								}
								//~ fwrite($fp, "'".$val."'");
								fwrite($fp,"\n");
								fclose($fp);
						}
						else{
								fwrite($fpp, "here 456\n");
								$resp   =       explode(":",$output_id[0]);
								fwrite($fpp, print_r($resp,true));
								$lineNum=       $resp[0];
								fwrite($fpp, $lineNum."\n");
								fwrite($fpp,trim($resp['1'],"'")."\n");
								fwrite($fpp, $val."\n");
								 fwrite($fpp, $filePath."\n");
								$change_code = !empty($resp['1'])?trim($resp['1'],"'"):'';
								$cmd    =       "sed -i '".$lineNum." s/".$change_code."/".$val."/' $fileName";
								fwrite($fpp, $cmd."\n");
								exec($cmd);
						}
				}
		}
		fclose($fpp);
	}
	function searchFromFile($val,$source,$client_id,$autoFill=false){
		$fpp  = fopen("/var/log/czentrix/CZCRM/writelogs", 'a+');
		fwrite($fpp, "source= $source\n");
		fwrite($fpp, "autoFill= $autoFill\n");
		fwrite($fpp, "client_id= $client_id\n");


		$fileName = getFileName($source,$client_id);
		fwrite($fpp, "fileName= $fileName\n");
		fwrite($fpp, "val= $val\n");

		//~ $fpp  = fopen("/tmp/log_test", 'a+');
		$result1	=	exec("grep -c '".$val."' $fileName", $output1);
		fwrite($fpp, "result1= $result1\n");

		if($result1<=10){
			$result   = exec("grep '".$val."' $fileName", $output);
			$ids  = '';
			$idsArr = array();
			foreach ($output as $key => $value) {
				$newArr = explode('#TVT#', $value);
				//~ $idsArr[] = $newArr['1'];
				$idsArr[] = $newArr['2'];
			}
			$idvar  = implode(",", $idsArr);
			$ids  = str_replace("'", "", $idvar);
			return $ids;
		}
		else{
			if($autoFill){
				return '';
			}else{
				return 'invalid_search';
			}
		}
	}

	// Maintain monthly table for ticket_details_report.
	function maintainMonthlyTable($json_para, $DB='',$DB_H=''){
		if(empty($DB) && empty($DB_H)){
			global $DB, $DB_H;
		}

		$json_arr	=	json_decode($json_para, true);
		$ticket_id	=	$json_arr['ticket_id'];
		// $tableName	=	$json_arr['table_name'];
		$action		=	strtolower($json_arr['action']);

		$tableName	=	"ticket_details_report";
		$tableName_30	=	$tableName."_30";

		switch ($action) {
			case 'insert':
			$query1	=	"INSERT INTO {$tableName_30} SELECT * FROM {$tableName} WHERE ticket_id='$ticket_id'";
			$query1	=	$DB->EXECUTE_QUERY($query1, $DB_H);
			break;

			case 'update':
			$delQ	=	"DELETE FROM {$tableName_30} WHERE ticket_id='$ticket_id'";
			$delQ	=	$DB->EXECUTE_QUERY($delQ, $DB_H);

			$query1	=	"INSERT INTO {$tableName_30} SELECT * FROM {$tableName} WHERE ticket_id='$ticket_id'";
			$query1	=	$DB->EXECUTE_QUERY($query1, $DB_H);

			break;
			default:
			# code...
			break;
		}
	}

	function getFormFields($modulename){
		$FLP = new logs_creation($_SESSION['CLIENT_ID']);
		$module	=	$modulename;
		$html_file = "dynamic_config/".$module."_".$_SESSION['CLIENT_ID'];
		// $html_file = "dynamic_config/".$module."_310";
		if(file_exists($html_file)){
			$content	=	file_get_contents($html_file);
			$data_arr	=	json_decode($content, true);
			$basic_data	=	$data_arr['Basic']['customized_fields'];
			if(isset($data_arr['Customized']['customized_fields'])){
				$customized_data	=	$data_arr['Customized']['customized_fields'];
				}else{
				$customized_data	=	array();
			}

			$person_info_data	=	base64_decode($basic_data['data']);
			$person_info_data_arr	=	json_decode($person_info_data, true);
			if(count($customized_data)>0){
				$customized_data_data	=	base64_decode($customized_data['data']);
				$customized_data_data_arr	=	json_decode($customized_data_data, true);
				$FLP->prepare_log("18",$customized_data_data_arr, "=====customized_data_data_arr PERSON INFO====");
				$new_array	=	array_merge($person_info_data_arr,$customized_data_data_arr);
			}
			else{
				$new_array	=	$person_info_data_arr;
			}

			$FLP->prepare_log("18",$new_array, "=====NEW ARRAY PERSON INFO====");
			foreach ($new_array as $key => $value) {
				$responseArr[$key]	=	$value['displayname'];
			}
			$responseArr['Status']	=	1;
		}
		else{
			$responseArr['Status']	=	0;
			$responseArr['Msg']	=	'File Not Exists!!';
		}
		return base64_encode(json_encode($responseArr));
	}

	// Function to capture logs.
	// code by- shailendra

	function logWrite($data,$file_name,$action,$clientID=''){

		$hostname	=	gethostname();
		$client_folder	=	'';
		if(isset($_SESSION['CLIENT_FOLDER'])){
			$client_folder = $_SESSION['CLIENT_FOLDER'];
		}
		else{
			$file_content = "configs/client_wise_folderInfo.txt";
			if(file_exists($file_content)){
				$folderInfo	=	file_get_contents($file_content);
				$folderArr	=	json_decode($folderInfo,true);
				$client_folder	=	isset($folderArr[$clientID])?$folderArr[$clientID]:"";
			}
		}

		if(!empty($client_folder)){
			$file_path	=	"/var/log/czentrix/CZCRM/$client_folder/$hostname/";
		}
		else {
			$file_path	=	"/var/log/czentrix/CZCRM/$hostname/";
		}

		if(!is_dir($file_path)){
			if(!mkdir($file_path,'0777',true)){
			$file_path	=	"/var/log/czentrix/CZCRM/";
			}
		}

		$org_file_name	=	$file_path.$file_name;

		if(!file_exists($org_file_name)){
			exec("touch $org_file_name");
			exec("chmod 777 $org_file_name");
		}

		//$FLP->prepare_log("18",$org_file_name."=ff===".$file_path."===".$file_name."==".$clientID."====".$data."====".$action, "=====NEW LOG WRITE====");

		if(file_exists($org_file_name)){
			$fpp	=	fopen($org_file_name, "a+");
			fwrite($fpp, "[".$action."]--[".date("Y-m-d H:i:s")."]------");
			fwrite($fpp, $data."\n");
			fclose($fpp);
		}
		else{
			exec("touch $org_file_name");
			exec("chmod 777 $org_file_name");
			//logWrite($data,$file_name,$action,$clientID);
		}

	}
	function masterDataValuesSave($MasterName,$PrvElementId=0,$elementId,$clientId=0,$type='TICKET'){
		$fpp  = fopen("/var/log/czentrix/CZCRM/function_log_test", 'a+');
		fwrite($fpp,"Master Values--[".$MasterName."]--".$PrvElementId."====".$elementId."===".$clientId."\n");
		if($clientId==0){
			$clientId	=	$_SESSION['CLIENT_ID'];
		}
		$jsonArr	=	array();
		if(!empty($clientId)){
			// $masterArray1	=	array("disposition"=>"disposition_","product_type"=>"product_","ticket_status"=>"ticket_status_","lead_source"=>"lead_source_","departments"=>"departments_","country"=>"country_","lead_state"=>"lead_state_");
			$masterArray1	=	array("disposition"=>"disposition","product_type"=>"product","ticket_status"=>"ticket_status","lead_source"=>"lead_source","departments"=>"department","country"=>"country","lead_state"=>"lead_state");
			// $masterArray2	=	array("sub_disposition"=>"sub_disposition_","sub_product_type"=>"sub_product_type_","users"=>"users_",);
			$masterArray2	=	array("sub_disposition"=>"sub_disposition","sub_product_type"=>"sub_product_type","users"=>"users",);
			if(isset($masterArray1[$MasterName])){
				// $fileName	=	isset($masterArray1[$MasterName])?$masterArray1[$MasterName].$clientId:'';
				$fileName	=	isset($masterArray1[$MasterName])?$masterArray1[$MasterName].'.txt':'';
				if(!empty($fileName)){
					fwrite($fpp,print_r($fileName,true));
					fwrite($fpp,"\n");

					// $jsonData=	file_get_contents("/var/www/html/CZCRM/master_data_config/".$fileName);
					$file_content = "/var/www/html/CZCRM/master_data_config/".$clientId."/".$fileName;
					if(file_exists($file_content)){
						$jsonData=	file_get_contents($file_content);
						fwrite($fpp,print_r($jsonData,true));
						fwrite($fpp,"\n");

						$jsonAr = json_decode($jsonData,true);
						fwrite($fpp,print_r($jsonAr,true));
						fwrite($fpp,"\n");
						$jsonArr = $jsonAr[$clientId][$type]['ALL'][$elementId];
					}
				}
			}
			elseif(isset($masterArray2[$MasterName])){
				// $fileName	=	isset($masterArray2[$MasterName])?$masterArray2[$MasterName].$clientId:'';
				$fileName	=	isset($masterArray2[$MasterName])?$masterArray2[$MasterName].'.txt':'';
				if(!empty($fileName)){
					fwrite($fpp,print_r($fileName,true));
					fwrite($fpp,"\n");

					// $jsonData=	file_get_contents("/var/www/html/CZCRM/master_data_config/".$fileName);
					$file_content = "/var/www/html/CZCRM/master_data_config/".$clientId."/".$fileName;
					if(file_exists($file_content)){
						$jsonData=	file_get_contents($file_content);
						fwrite($fpp,print_r($jsonData,true));
						fwrite($fpp,"\n");
						$jsonAr = json_decode($jsonData,true);
						fwrite($fpp,print_r($jsonAr,true));
						fwrite($fpp,"\n");
						$jsonArr = $jsonAr[$clientId][$type]['ALL'][$PrvElementId][$elementId];
					}
				}
			}

		}
		fwrite($fpp,"VALUES----------\n");
		fwrite($fpp,print_r($jsonArr,true));
		fwrite($fpp,"\n");
		fclose($fpp);
		$ElementValue	=	$jsonArr;
		return	$ElementValue;
	}
	function CreateLeadHash($strUnique){
		$filePath	=	fopen("/var/www/html/CZCRM/leadinfoHash","a+");
		fwrite($filePath,$strUnique."\n");
		fclose($filePath);
	}
	function CheckLeadHash($strUnique,$Updstr){
		$filePath= "/var/www/html/CZCRM/leadinfoHash";
		$result = 	exec("grep -n '" .$strUnique. "' $filePath", $output);
		$resp	=explode(":",$output[0]);
		$resp2	=explode("#S$#",$resp[1]);
		$lineNum=$resp[0];
		$strLead=$resp2[2];

		$leadArr	=	explode("#$#",$strLead);
		$leadDate	=	$leadArr[0];

		$updateStr	=	$leadDate."#$#".$Updstr;


		$cmd	=	"sed -i '".$lineNum."s/".$strLead."/".$updateStr."/' $filePath";
		//print($cmd);
		//exit();
		exec($cmd);
		return $strLead;
	}
	function getCityState($MasterName,$elementId,$clientId=0){
		$fpp  = fopen("/var/log/czentrix/CZCRM/log_test1", 'a+');
		fwrite($fpp,"Master Values--[".$MasterName."]--".$PrvElementId."====".$elementId."===".$clientId."\n");
		if($clientId==0){
			$clientId	=	$_SESSION['CLIENT_ID'];
		}
		if(!empty($clientId)){
			//$fileName	=	isset($MasterName)?$MasterName."_".$clientId:'';
			$fileName = '/var/www/html/CZCRM/master_data/'.$MasterName.'_'.$clientId;
			$fp	=	fopen($fileName, 'r');
			$val = "#TVT#".$elementId."#TVT#";
			exec("grep '" .$val. "' $fileName", $output);
			fwrite($fpp,"grep '" .$val. "' $fileName");
			fwrite($fpp,print_r($output,true));
			$nameval = '';
			foreach ($output as $key => $value) {
				$name1 = explode('#TVT#', $value);
				$nameval = $name1['2'];
				fwrite($fpp,print_r($nameval,true));
			}
		}
		return $nameval;
	}
	// Used for third party api search on create Ticket/ lead
	function render_tpa_search($DataJson){
		$DataArray	=	json_decode($DataJson,true);
		$str		=	'';
		$searchParms=	'';
		$counti	=	0;
		$str	.=	'<div class="row" style="margin-bottom:1%;">';
		foreach($DataArray as $key=>$val){
			foreach($val as $kk=>$vv){
				$type			=$vv['type'];
				$field_name		=$vv['field_name'];
				$searchParms	.=$field_name.",";
				$field_label	=$vv['field_label'];
				$style			=$vv['style'];
				$class			=$vv['class'];

				if($counti>0 && $counti%3==0){
					$str	.=	'</div><div class="row" style="margin-bottom:1%;">';
				}
				$str	.=	'<div class="col-sm-4 col-md-4 col-lg-4">';
				$str	.=	'<input type="'.$type.'" placeholder="'.$field_label.'" style="'.$style.'" class="'.$class.'" name="'.$field_name.'" id="'.$field_name.'">';
				$str 	.=	'</div>';
				$counti++;
			}
		}

		$searchParms	=	trim($searchParms,",");
		$searchP		=	'<div class="col-sm-4 col-md-4 col-lg-4"><input type="hidden" name="search_parms" id="search_parms" value="'.$searchParms.'"></div></div>';
		// $str 	.=	'';
		return $str.$searchP;
	}


	function maintainMonthlyTableLead($ticketId,$action,$conditionValue='',$DB='', $DB_H=''){
		// global $DB,$DB_H;
		if(empty($DB) && empty($DB_H)){
			global $DB, $DB_H;
		}
		if(!empty($ticketId)){
			$tableArray	=	array("lead_details_30");
			foreach($tableArray as $kk){
				if($action=='insert'){
					$insertData	=	"INSERT INTO $kk SELECT * from lead_details where lead_id='".$ticketId."'";
					$DB->EXECUTE_QUERY($insertData,$DB_H);
				}
				elseif($action=='update'){
					$deleteData	=	"DELETE FROM $kk where lead_id='".$ticketId."'";
					$DB->EXECUTE_QUERY($deleteData,$DB_H);

					$insertData	=	"INSERT INTO $kk SELECT * from lead_details where lead_id='".$ticketId."'";
					$DB->EXECUTE_QUERY($insertData,$DB_H);
					// $insertData	=	"UPDATE $kk SET $conditionValue where lead_id='".$ticketId."'";
					// GENERATELOGS_Report($insertData,"====UPDATE QUERY====",0);
					// $DB->EXECUTE_QUERY($insertData,$DB_H);
				}
				// elseif($action=='delete'){
				// $deleteData	=	"DELETE FROM $kk where lead_id='".$ticketId."'";
				// $DB->EXECUTE_QUERY($deleteData,$DB_H);
				// }
			}
		}
	}

	function createMongoArray($f_array){
		if(is_array($f_array)){
			if(count($f_array)>0){
				foreach ($f_array as $key => $value) {
					$newArray[$key]	=	$value['1'];
				}
				return $newArray;
			}
		}
	}

	//////////////////function for user Activity//////////////////////
	function activityTracker($action,$modulename,$client_id='',$userName='',$userId=''){
		//GENERATELOGS_upload_redis($action."======".$modulename,"asdfg",1);
		$date = date("Y-m-d");
		$date1 = date("Y-m-d H:i:s");
		if(!empty($_SESSION['USER_ID'])){
			$user_id = $_SESSION['USER_ID'];
		}
		else{
			$user_id = $userId;
		}
		if(!empty($_SESSION['USERNAME'])){
			$user_name = $_SESSION['USERNAME'];
		}
		else{
			$user_name = $userName;
		}
		if(!empty($_SESSION['CLIENT_ID'])){
			$client_id = $_SESSION['CLIENT_ID'];
		}
		else{
			$client_id = $client_id;
		}
		$jsn_data	=	array ('Date' => $date,'ClientID' =>$client_id,'UserID'=>$user_id,'UserName'=>$user_name,'SessionID'=>session_id(),'Action' => array ("action"=>$action, "module"=>$modulename, "date"=>$date1, "user_name"=>$user_name));
		//$jsn_data=	array ('Date' => $date1,'ClintID' =>$_SESSION["USER_ID"],'Module' =>$modulename,'Action' => array (0 => $action,1 => $date));
		$encode_data = json_encode($jsn_data,true);
		$file_content  = "/var/www/html/CZCRM/configs/master_master.json";
		if(file_exists($file_content)){
			$filedata = file_get_contents($file_content);
			$filedata = json_decode($filedata,true);
			$password = $filedata['REDIS_MASTER'];
			$port = $filedata['PORT'];
			$connection = $filedata['M1'];
			$redis = new Redis();
			//$pass = 'd!(ti0n@ry\$54321';
			$redis->connect($connection,$port);
			if(!empty($password)){
				$redis->auth($password);
			}
			if($redis){
				$insert = $redis->lpush($date."_activityTracker",$encode_data);
			}
		}
	}
	// Function to set Apply Change for ALL modules with Global Apply Change.
	function setApplyChange($dataJson,$DB='',$DB_H=''){			// $dataJson 	=	'{"module_name":"MODULENAME","table_name":"TABLENAME"}';
		if(empty($DB) && empty($DB_H)){
			global $DB, $DB_H;
		}
		$result	=	"";
		$tb = "system_module_queue";
		$dataArray	=	json_decode($dataJson, true);
		$fp	=	fopen("/var/log/czentrix/CZCRM/apply_change_log", "a+");
		//$fp	=	fopen("/var/log/czentrix/Support_CRM/apply_change_log", "a+");
		if(count($dataArray)>0){
			fwrite($fp, "(====".date("Y-m-d H:i:s")."====)".$dataJson);
			$v_array	=	array();
			foreach ($dataArray as $col => $val) {
				$v_array[$col]	=	array("STRING", $DB->MYSQLI_REAL_ESCAPE($DB_H,$val));
			}
			$insertQ	=	$DB->INSERT($tb, $v_array, $DB_H);
			$status		=	$DB->getLastInsertedID($DB_H);
			fwrite($fp, "======".$DB->getLastQuery()."======".$status."\n");
			if($status){
				$result	=	"Success";
			}
		}
		else{
			$result		=	"Error";
			fwrite($fp, $result."\n");
		}
		fclose($fp);
		return $result;
	}
	function CreateTaskHash($strUnique,$clientId){
		$filePath	=	fopen("/var/www/html/CZCRM/systemHash/taskinfoHash_$clientId","a+");
		fwrite($filePath,$strUnique."\n");
		fclose($filePath);
	}
	function CheckTaskHash($strUnique,$Updstr,$client_Id){
		$FLP = new logs_creation($client_Id);
		$FLP->prepare_log("18",$strUnique,"string");
		$FLP->prepare_log("18",$Updstr,"updatestr");
		$FLP->prepare_log("18",$client_Id,"client id");
		$filePath= "/var/www/html/CZCRM/systemHash/taskinfoHash_$client_Id";
		$result = 	exec("grep -n '" .$strUnique. "' $filePath", $output);
		$FLP->prepare_log("18",$output,"output aaayr");

		$resp	=explode(":",$output[0]);
		$resp2	=explode("#S$#",$resp[1]);
		$lineNum=$resp[0];
		$strLead=$resp2[2];
		$leadArr	=	explode("#$#",$strLead);
		$leadDate	=	$leadArr[0];
		$updateStr	=	$leadDate."#$#".$Updstr;
		$cmd	=	"sed -i '".$lineNum."s/".$strLead."/".$updateStr."/' $filePath";
		exec($cmd);
		return $strLead;
	}

	function task_history($data){

		global $DB,$DB_H;
		$task_status=$task_priority='';
		$task_unique_id	=	0;
		$DataArr	=	json_decode($data,true);
		$action		=	$DataArr['action'];
		$date_time	=	$DataArr['date_time'];
		$action_by	=	$DataArr['action_by'];
		$docket_no	=	$DataArr['docket_no'];
		$client_id	=	$DataArr['client_id'];
		$month		=	date("M",$date_time);
		$date		=	date("d",$date_time);
		$time		=	date("H:i",$date_time);
		$action_to		=	$DataArr['action_to'];
		$task_unique_id	=	$DataArr['task_unique_id'];
		$task_id		=	$DataArr['task_id'];
		$task_status	=	$DataArr['task_status'];
		$task_duedate	=	$DataArr['task_duedate'];
		$task_priority	=	$DataArr['task_priority'];
		$type			=	$DataArr['type'];
		if(strtolower($action)=='task_created'){

			$Dateunix	=	strtotime(date("Y-m-d",strtotime($task_duedate)));
			if(!empty($task_priority)){
				$upd1		=	"UPDATE task_priority_wise_summary SET task_count=(task_count+1) where priority='".$task_priority."' AND dateunix='".$Dateunix."' and user_name='".$action_to."' and type='".$type."'";
				$upd1	=	$DB->EXECUTE_QUERY($upd1,$DB_H);
				$AFFECTED_ROW	=	$DB->GET_AFFECTED_ROW($DB_H);
				if(!$AFFECTED_ROW){
					$ins1	=	"INSERT INTO task_priority_wise_summary (dateunix,priority,task_count,user_name,type) VALUES ('".$Dateunix."','".$task_priority."',1,'".$action_to."','".$type."')";
					$ins1	=	$DB->EXECUTE_QUERY($ins1,$DB_H);
				}
			}
			if(!empty($task_status)){
				$strUnique	=	"#S$#$docket_no.$task_unique_id#S$#$Dateunix#$#$task_status";
				CreateTaskHash($strUnique,$client_id);
				$upd1		=	"UPDATE task_status_wise_summary SET task_count=(task_count+1) where task_status='".$task_status."' AND dateunix='".$Dateunix."' and user_name='".$action_to."' and type='".$type."'";
				$upd1	=	$DB->EXECUTE_QUERY($upd1,$DB_H);
				$AFFECTED_ROW	=	$DB->GET_AFFECTED_ROW($DB_H);
				if(!$AFFECTED_ROW){
					$ins1	=	"INSERT INTO task_status_wise_summary (dateunix,task_status,task_count,user_name,type) VALUES ('".$Dateunix."','".$task_status."',1,'".$action_to."','".$type."')";
					$ins1	=	$DB->EXECUTE_QUERY($ins1,$DB_H);
				}
			}
		}
		elseif(strtolower($action)=='task_closed'){
			$Dateunix	=	strtotime(date("Y-m-d"));
			//$action_by	=	'Naveen';
			$strUnique	 =	"#S$#$docket_no.$task_unique_id#S$#";
			$updateUnique=	$task_status;
			$result		 =	CheckTaskHash($strUnique,$updateUnique,$client_id);
			$taskArr	=	explode("#$#",$result);
			$taskdate	=	$taskArr[0];
			$oldStatus	=	$taskArr[1];
			if($oldStatus!=$task_status){
				$update	 =	"UPDATE task_status_wise_summary SET task_count=(task_count-1) where task_status='".$oldStatus."' AND dateunix='".$taskdate."' AND user_name='".$action_to."' AND type='".$type."'";
				$DB->EXECUTE_QUERY($update,$DB_H);

				$update	 =	"UPDATE task_status_wise_summary SET task_count=(task_count+1) where task_status='".$task_status."' AND dateunix='".$Dateunix."' AND user_name='".$action_to."' AND type='".$type."'";
				$DB->EXECUTE_QUERY($update,$DB_H);
				$AFFECTED_ROW	=	$DB->GET_AFFECTED_ROW($DB_H);
				if(!$AFFECTED_ROW){
					$ins1	=	"INSERT INTO task_status_wise_summary (dateunix,task_status,task_count,user_name,type) VALUES ('".$Dateunix."','".$task_status."',1,'".$action_to."','".$type."')";
					$ins1	=	$DB->EXECUTE_QUERY($ins1,$DB_H);
				}
			}
		}
	}

	function timeline_history($data){
		$mongo	=	new MongoClient('127.0.0.1',$u,$p,$db);
		$mongo->connectDB();
		$check = $mongo->checkServerRespond();
		if(empty($check)){
			return true;
		}else{
			global $DB,$DB_H;
			require_once ("/var/www/html/CZCRM/modules/DATABASE/MongoClient.class.php");
			$fff	=	fopen("/var/log/czentrix/CZCRM/czcrm_timeline","a+");
			fwrite($fff,$data."\n");
			$task_status=$task_priority='';
			$task_unique_id	=	0;
				$cond = array();
			$DataArr	=	json_decode($data,true);
			$type		=	isset($DataArr['type'])?$DataArr['type']:'';
			$action		=	$DataArr['action'];
			$date_time	=	$DataArr['date_time'];
			$action_by	=	$DataArr['action_by'];
			$client_id 	=   isset($DataArr['client_id'])?$DataArr['client_id']:$_SESSION['CLIENT_ID'];
			$element_id	=	isset($DataArr['element_id'])?$DataArr['element_id']:$DataArr['lead_id'];
			$element_value=	isset($DataArr['element_value'])?$DataArr['element_value']:$DataArr['docket_no'];
			$typeIdVal	=	"";
			if(strtolower($type)=="ticket"){
				$typeIdVal		=	$DataArr['ticket_id'];
				$collection 	=   "ticket_timeline";
				$id_val = "ticket_id";
			}
			else{
				$typeIdVal		=	$DataArr['lead_id'];
				$collection     =   "lead_timeline";
				$id_val = "lead_id";
			}
			$clientFolder=	$DataArr['client_folder'];
			$docket_no=	$DataArr['docket_no'];
			$month		=	date("M",$date_time);
			$date		=	date("d",$date_time);
			$time		=	date("H:i",$date_time);
			$content	=	"";
			$timeline_action	=	"";
			$mail_id	=	"";
			$data_content = "";
			if(strtolower($action)=='lead_created' || strtolower($action)=='ticket_created'){
				$followup_date	=	isset($DataArr['followup_date'])?$DataArr['followup_date']:'';
				$timeline_action	=	$type;
				if(!empty($followup_date)){
					$content	=	ucfirst($timeline_action)." Created by $action_by & Followup marked on $followup_date";
					$data_content = $action_by;
					$action_type = "creation";
				}
				else{
					$assigned_to	=	isset($DataArr['action_to'])?$DataArr['action_to']:'';
					if(!empty($assigned_to)){
						$content	=	ucfirst($timeline_action)." Created by $action_by & Assigned to $assigned_to";
					}
					else{
						$content	=	ucfirst($timeline_action)." Created by $action_by";
					}
					$data_content = $action_by;
					$action_type = "creation";
				}
			}
			elseif(strtolower($action)=='lead_assigned' || strtolower($action)=='ticket_assigned'){
				$timeline_action	=	$type;
				$action_to	=	$DataArr['action_to'];
				$content	=	ucfirst($timeline_action)." Assigned to $action_to  by $action_by";
				$action_type = "assignment";
				$data_content = $action_by;
			}
			elseif(strtolower($action)=='ticket_privatenote' || strtolower($action)=='lead_privatenote'){
				$timeline_action	=	$type;
				$comment_text	=	$DataArr['comment_text'];
				$content	=	"Note added by $action_by , '$comment_text'";
				$action_type = "privatenote";
				$data_content = $comment_text;
			}
			elseif(strtolower($action)=='followup_add'){
				$timeline_action	=	$type;
				$followup_remarks	=	$DataArr['followup_remarks'];
				$content	=	"Followup marked on ".date("d M H:i",strtotime($DataArr['followup_datetime']))." by $action_by with remarks :\r\n <i>$followup_remarks</i>";
				$action_type = "followup";
				$data_content = $followup_remarks;

				fwrite($fff,$content." followup\n");


			}
			elseif(strtolower($action)=='lead_followup'){
				$timeline_action	=	$type;
				$followup_date	=	$DataArr['followup_date'];
				$content	=	ucfirst($timeline_action)." marked on $followup_date by $action_by";
				$action_type = "followup";
				$data_content = $action_by;

			}
			elseif(strtolower($action)=='lead_convert'){
				$timeline_action=	isset($type)?$type:"lead_convert";
				$comment_text	=	$DataArr['convert_remarks'];
				$lead_stage		=	$DataArr['lead_stage'];
				$lead_value	=	$DataArr['lead_value'];
				$content	=	"Lead Converted as $lead_stage by $action_by with Lead Value $lead_value";
				$action_type = "lead_convert";
				$data_content = $action_type;
			}
			elseif(strtolower($action)=='lead_attachment' || strtolower($action)=='ticket_attachment'){
				$timeline_action	=	"UPLOAD_ATTACH";
				$attach_description	=	$DataArr['attachment_description'];
				$filename 			=	$DataArr['filename'];
				$filepath 			=	$DataArr['filepath'];
				$attach_file		=	$DataArr['attach_file'];
				$filenameencode		=	base64_encode($filename);
				$content			=	"Attachment <a href='download.php?attach_file_name=$attach_file&filename=$filenameencode&path=$filepath'>$filename</a> added by $action_by";
				$action_type 		= 	"attachment";
				$data_content 		= 	$action_type;


			}
			elseif(strtolower($action)=='lead_email_sent' || strtolower($action)=='ticket_email_sent') {
				$timeline_action	=	$type;
				$action_to	=	$DataArr['action_to'];
				$content	=	ucfirst($timeline_action)." send to $action_to  by $action_by";
				$action_type = "outmail";
				$data_content = $action_by;
			}
			elseif(strtolower($action)=='ticket_task_created' || strtolower($action)=='lead_task_created'){
				$timeline_action	=	$type;
				$action_to	=	$DataArr['action_to'];
				$task_unique_id	=	$DataArr['task_unique_id'];
				$task_id	=	$DataArr['task_id'];
				$task_status	=	$DataArr['task_status'];
				$task_duedate	=	$DataArr['task_duedate'];
				$task_priority	=	$DataArr['task_priority'];
				$task_subject	=	isset($DataArr['task_subject'])?$DataArr['task_subject']:'';
				if(!empty($task_subject)){
					$content	=	"Task #$task_unique_id '".$task_subject."' Created by $action_by & Assigned to $action_to";
				}
				else{
					$content	=	"Task #$task_unique_id  Created by $action_by & Assigned to $action_to";
				}
				$action_type = "task";
				$data_content = $action_by;
			}
			elseif(strtolower($action)=='ticket_task_updated' || strtolower($action)=='lead_task_updated'){
				$timeline_action	=	$type;
				$action_to		=	$DataArr['action_to'];
				$action_by		=	$DataArr['action_by'];
				$task_unique_id	=	$DataArr['task_unique_id'];
				$task_id		=	$DataArr['task_id'];
				$task_status	=	$DataArr['task_status'];
				$task_duedate	=	$DataArr['task_duedate'];
				$task_priority	=	$DataArr['task_priority'];
				$update_type	=	isset($DataArr['update_type'])?$DataArr['update_type']:'';
				$task_subject	=	isset($DataArr['task_subject'])?$DataArr['task_subject']:'';
				if(!empty($task_subject)){
					if($update_type=='agent'){
						$content		=	"Task #$task_unique_id '".$task_subject."' Updated by $action_by as '".$task_status."'";
					}
					else{
						$content		=	"Task #$task_unique_id '".$task_subject."' Updated by $action_by & Assigned to $action_to";
					}
				}
				else{
					if($update_type=='agent'){
						$content		=	"Task #$task_unique_id Updated by $action_by as '".$task_status."'";
					}
					else{
						$content		=	"Task #$task_unique_id Updated by $action_by & Assigned to $action_to";
					}
				}
				$action_type = "task";
				$data_content = $action_by;
			}
			elseif(strtolower($action)=='ticket_task_closed' || strtolower($action)=='lead_task_closed'){
				$timeline_action	=	$type;
				$action_by	=	$DataArr['action_by'];
				$task_id	=	$DataArr['task_id'];
				$task_unique_id	=	$DataArr['task_unique_id'];
				$task_status	=	$DataArr['task_status'];
				$content	=	"Task #$task_unique_id Closed by $action_by";
				$action_type = "task";
				$data_content = $action_by;
			}
			elseif(strtolower($action)=='lead_updated' || strtolower($action)=='ticket_updated'){
				$timeline_action	=	$type;
				$action_by		=	$DataArr['action_by'];
				$followup_date	=	isset($DataArr['followup_date'])?$DataArr['followup_date']:'';
				if(!empty($followup_date)){
					$content	=	ucfirst($timeline_action)." Updated by $action_by & Followup marked on $followup_date";
					$action_type = "update";
					$data_content = $action_by;
				}
				else{
					$content	=	ucfirst($timeline_action)." Updated by $action_by";
					$action_type = "update";
					$data_content = $action_by;
				}
			}
			elseif(strtolower($action)=='lead_closed' || strtolower($action)=='ticket_closed'){
				$timeline_action	=	$type;
				$content	=	ucfirst($timeline_action)." Closed by $action_by";
				$action_type = "close";
				$data_content = $action_by;
			}
			$db = "crm_manager_".$client_id;
			$u=$p='';

			$cond	=	array($id_val => $typeIdVal);
			if(strtolower($action)=='lead_created' || strtolower($action)=='ticket_created'){
				$dataNew	=	array($id_val=>$typeIdVal,"history"=>array());
				$mongo->INSERT($collection,$dataNew,'');
				$dataNew = array("history"=>array("date"=>$date." ".$month,"time"=>$time,"content"=>$content,"data"=>$data_content,"action_case"=>$action_type));
				$mongo->UPDATE($collection,$dataNew,$cond,true,false,true);
			}
			else{
				$result	=	$mongo->SELECT($collection,$cond);
				if(count($result)>0){
					$dataNew = array("history"=>array("date"=>$date." ".$month,"time"=>$time,"content"=>$content,"data"=>$data_content,"action_case"=>$action_type));
					$mongo->UPDATE($collection,$dataNew,$cond,true,false,true);
				}
				else{
					$dataNew	=	array($id_val=>$typeIdVal,"history"=>array());
					$mongo->INSERT($collection,$dataNew,'');
					$dataNew = array("history"=>array("date"=>$date." ".$month,"time"=>$time,"content"=>$content,"data"=>$data_content,"action_case"=>$action_type));
					$mongo->UPDATE($collection,$dataNew,$cond,true,false,true);
				}
			}
		}
	}
	function encode_string($str){
		$str = trim ($str, " ");
		//$str = urldecode($singleMessage["message"]);
		// $str = htmlentities($str, ENT_NOQUOTES);
		$str = addslashes($str);
		$str = preg_replace("/[\n\r]/", "::ERNL::", $str);
		$str = str_replace("::ERNL::::ERNL::", "\\n", $str);
		return $str;
	}

	function decode_string($str){
		$str = str_replace(array("\\n ", "\\r"), "<br>", $str);
		$str = str_replace("\'", "'", $str);
		$str = rtrim($str,",");
		return $str;
	}
	function generateRandomString($length=15)
	{
		$characters_to_include = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz";
		$string_length = strlen($characters_to_include);
		$random_string = "";

		for($i=0;$i<$length;$i++)
		{
			$random_string .= $characters_to_include[rand(0,$string_length-1)];

		}

		return $random_string;
	}

	// Redis Function
	function setRedisDashboardData($value,$key=''){
		$FLP = new logs_creation( );
		if(!empty($value)){
			// $redisConfig	=	file_get_contents("/var/www/html/CZCRM/configs/master_master.json");
			// $configArr		=	json_decode($redisConfig,true);
			// $REDIS_SERVER	=	$configArr['M1'];
			// $REDIS_PORT		=	$configArr['PORT'];
			// $REDIS_PWD		=	$configArr['PWD'];
			if(empty($key)){
				$key='DASHBOARD_DATA';
			}
			if(!empty($key)){
				$FLP->prepare_log("18",$value,"====PACKET WRITE IN REDIS====");
				try{
					require_once("/var/www/html/CZCRM/classes/redisHandler.class.php");
					$redis = new redisHandler();
					$redis->lpushRedis($key,$value);
					// $redis = new Redis();
					// $redis->connect($REDIS_SERVER, $REDIS_PORT);
					// if(isset($REDIS_PWD) && !empty($REDIS_PWD)){
					// 	$redis->auth($REDIS_PWD);
					// }
					// $redis->lpush($key,$value);
					// $redis->close();
				}
				catch(Exception $e){
					$FLP->prepare_log("18",$e,"====REDIS CONNECT ERROR====");
				}
			}else{
				return "Key not defined!";
			}
		}else{
			return "Value should not be empty!";
		}
	}

	function getDepatmentInfo($source,$dept_id,$DB='',$DB_H=''){
		if(empty($DB) && empty($DB_H)){
			global $DB, $DB_H;
		}
		if(!empty($dept_id)){
			$query = "select dept_phone,dept_email from departments where dept_id=$dept_id";
			$exe_querydep = $DB->EXECUTE_QUERY($query,$DB_H);
			$fetchdept = $DB->FETCH_ARRAY($exe_querydep,MYSQLI_ASSOC);
			$dept_phone = $fetchdept['dept_phone'];
			$dept_email = $fetchdept['dept_email'];
			if(strtolower($source)=='call'){
				$sourceval = $dept_phone;
			}
			elseif(strtolower($source)=='email'){
				$sourceval = $dept_email;
			}
			else{
				$sourceval = $dept_phone;
			}
		}
		return  $sourceval;
	}
	function getYearMonthsArr($start_month1,$start_date1,$end_month1,$end_date1){
		$start_month1 = ltrim($start_month1,'0');
		if($start_date1 == $end_date1){
			$j = 1;
			for($i=$start_month1; $i<=$end_month1;$i++){
				$k = incrementIndex($i);
				$k=str_pad($k,2,"0",STR_PAD_LEFT);
				$tabExtArr[$j] =  $start_date1."_".$k;
				$j++;
			}
		}
		else{
			$j = 1;
			for($cy=$start_date1; $cy<=$end_date1; $cy++){
				if($cy == $start_date1){
					for($i = $start_month1; $i<=12; $i++){
						$k = incrementIndex($i);
						$k=str_pad($k,2,"0",STR_PAD_LEFT);
						$tabExtArr[$j] =  $start_date1."_".$k;
						$j++;
					}
				}
				else{
					if($cy == $end_date1){
						for($i=1; $i<=$end_month1;$i++){
							$k = incrementIndex($i);
							$k=str_pad($k,2,"0",STR_PAD_LEFT);
							$tabExtArr[$j] =  $end_date1."_".$k;
							$j++;
						}
					}
					else{
						for($i=1;$i<=12;$i++){
							$k = incrementIndex($i);
							$k=str_pad($k,2,"0",STR_PAD_LEFT);$k=str_pad($k,2,"",STR_PAD_LEFT);
							$tabExtArr[$j] =  $cy."_".$k;
							$j++;
						}
					}
				}
			}
		}
		return $tabExtArr;
	}
	function incrementIndex($k){
		if($k<9){
			$k ="0".$k;
		}
		return $k;
	}
		function encrypt_apidata($msg){
		$msg	=	"_".$msg."_";
		$method	=	'aes-256-cbc';
		$ivhex	=	'b1fa837eb66e3058709c912315f720d9';
		$pass	=	'12345678906546543211237899875430';
		$encryptedData	=	openssl_encrypt($msg, $method, $pass,true,$ivhex);
		return base64url_encode($encryptedData);
	}
	function decrypt_apidata($encryptedData){
		$method	=	'aes-256-cbc';
		$ivhex	=	'b1fa837eb66e3058709c912315f720d9';
		$pass	=	'12345678906546543211237899875430';
		$encryptedData	=	base64url_decode($encryptedData);
		$decryptedData	=	openssl_decrypt($encryptedData, $method, $pass,true,$ivhex);
		$prefix			=	substr($decryptedData,0,1);
		$suffix			=	substr($decryptedData,-1);
		if($prefix=='_' && $suffix=='_'){
			$decryptedData	=	trim($decryptedData,"_");
		}
		else{
			$decryptedData	=	"";
		}
		return $decryptedData;
	}

	function ticketViewDetails($client_id, $ticket_id, $agent_id, $action,$type=""){
		
		require_once("/var/www/html/CZCRM/classes/redisHandler.class.php");
		$redis = new redisHandler();
		if(strtolower($type) == "lead"){
			$redis_key	=	"LEADVIEW:$client_id:$ticket_id";
			$t = 'Lead';
		}else{
			$redis_key	=	"TICKETVIEW:$client_id:$ticket_id";
			$t = 'Docket';
		}
		$agent_key  = 	"TICKETLEADVIEW:$agent_id";
		if(!empty($agent_id)) {
			switch (strtolower($action)) {
				case 'add':
					# code...	
					if(!$redis->existRedis($redis_key)){
						if(!$redis->existRedis($agent_key)){
							$redis->setRedis($agent_key,$redis_key , '120');
						}
						$userName = isset($_SESSION['USERNAME'])?$_SESSION['USERNAME']:"";
						$val = $agent_id.'_'.$userName;
						$redis->setRedis($redis_key,$val , '120');
						// Need to add this in a SET or SORTED SET REDIS for Reporting purpose.
					}
					else{
						return 'Already Exists';
					}
					break;
				case 'remove_user':
					# code...
					if(!empty($agent_id)) {
						if($redis->existRedis($agent_key)){
							$result	=	$redis->getRedis($agent_key);
							$redis->delRedis($result);
							$redis->delRedis($agent_key);
						}
					}
					break;
				case 'remove':
					# code...
					if($redis->existRedis($redis_key)){
						$result	=	$redis->getRedis($redis_key);
						$redis_val =	explode("_",$result);
						$userId = $redis_val['0'];
						if($userId==$agent_id){
							if($redis->existRedis($redis_key)){
								$redis->delRedis($agent_key);
							}
							
							$redis->delRedis($redis_key);
						}
					}
					else{
						return '';
					}
					break;
				case 'check':
					# code...
					if(!empty($agent_id)) {
						if($redis->existRedis($redis_key)){
							// Check Val/agent_id
							$result	=	$redis->getRedis($redis_key);
							$redis_val =	explode("_",$result);
							$userId = $redis_val['0'];
							$UserName = $redis_val['1'];
							if($userId==$agent_id){
								if(!$redis->existRedis($agent_key)){
									$action = "remove";
									ticketViewDetails($client_id, $ticket_id, $agent_id, $action,$type);
								}else{
									$expiryTime	=	$redis->getExpiryRedis($redis_key);
									if($expiryTime<60){
										$redis->setRedis($redis_key, $result, '120');
										$redis->setRedis($agent_key, $redis_key, '120');
										// Need to add this in a SET or SORTED SET REDIS for Reporting purpose.
									}
								}
							}
							else{
								//return 'Another Agent is working on this '.$t.'.';
								return $UserName.' is working on this '.$t.'.';
							}
						}
						else{
							if(!$redis->existRedis($agent_key)){
								$redis->setRedis($agent_key, $redis_key , '120');
							}
							$userName = isset($_SESSION['USERNAME'])?$_SESSION['USERNAME']:"";
							$val = $agent_id.'_'.$userName;
							$redis->setRedis($redis_key, $val, '120');
							// Need to add this in a SET or SORTED SET REDIS for Reporting purpose.
						}
					}
					break;
				default:
					# code...
					break;
			}
		}
		else {
			return 'Another Agent is working on this '.$t;
		}
	}
	
	// PUSH DATA IN REDIS FOR PERFORMANCE REPORT DATA
	function pushPerformanceData($data_packet, $client_id = ''){
		require_once("/var/www/html/CZCRM/classes/redisHandler.class.php");
		$redis = new redisHandler();
		if(empty($client_id) && isset($_SESSION['CLIENT_ID'])) {
			$client_id =	$_SESSION['CLIENT_ID'];
		}
		if(!empty($client_id)) {
			// $redis->lpushRedis("PR_DATA:$client_id",$data_packet);
		}
		else {
			// $redis->lpushRedis("PR_DATA",$data_packet);
		}
	}

?>
