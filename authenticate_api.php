<?php
	$configFileContent = file_get_contents("/var/www/html/CZCRM/configs/config.txt");
	$fileArr = json_decode($configFileContent,true);
	$ticket_app_name = isset($fileArr['TICKET_APP_NAME'])?$fileArr['TICKET_APP_NAME']:'';
	require_once("/var/www/html/CZCRM/configs/api_config.php");

	$allowedApp=array($ticket_app_name);

	function domainCheck(){
		global $functionConfiguration,$allowedDomain,$allowedApp;
        $host=isset($_SERVER["HTTP_HOST"])?$_SERVER["HTTP_HOST"]:"";  
        $domain= isset($_SERVER["HTTP_REFERER"])?parse_url($_SERVER["HTTP_REFERER"], PHP_URL_HOST):'';
        $localHeader=isset($_SERVER["HTTP_X_CZAPP"])?$_SERVER["HTTP_X_CZAPP"]:"";  
        if(!empty($localHeader) && in_array(strtolower($localHeader),$allowedApp) && ($host=="127.0.0.1" || $host=="localhost")){
			return true;
        }
        else if((in_array($domain,$allowedDomain) && !empty($domain))|| $allowedDomain[0]=="*"){
            return true;
        }
        else{
            return array("status"=>"error","statusCode"=>"405","message"=>"Invalid Domain!!");
        }   
		fclose($file);		
    }

	function sessionCheck(){
		global $functionConfiguration,$allowedDomain;
		
		if(isset($_SESSION["USER_ID"]) && !empty($_SESSION["USER_ID"])){
			//fwrite($file,$_SESSION["USER_ID"]);
			return true;
		}
		else{
			//fwrite($file,"Authentication Failed!");
			return array("status"=>"error","statusCode"=>"401","message"=>"Authentication Failed!!");
		}
	}
	
	function permissionCheck(){
		global $functionConfiguration,$allowedDomain;
		return true;
	}

	function authApi($reqType){ 
		global $functionConfiguration,$allowedDomain;
		foreach ($functionConfiguration[$reqType] as $funcName){
			$func_call = '$a = '.$funcName . '();';
			eval($func_call); 
			if($a!==true){
				return $a;
			}
		}
		return true;
		
	}
?>