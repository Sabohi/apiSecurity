<?php
	require_once ("configs/config.php");
	//~ require_once ("checkSession.php");
	require_once (_MODULE_PATH . "GRID/grid_config.php");
	require_once (_MODULE_PATH . "GRID/grid_properties.php");
	require_once (_MODULE_PATH . "GRID/grid.php");
	require_once (_MODULE_PATH . "DATABASE/database_config.php");
	require_once (_MODULE_PATH . "DATABASE/DatabaseManageri.php");
	require_once (_MODULE_PATH . "SESSION/session_config.php");
	require_once (_MODULE_PATH . "SESSION/session.php");
	require_once (_MODULE_PATH . "FUNCTIONS/functions.php");
	
	$key = (isset($_SESSION['CLIENT_KEY'])&&!empty($_SESSION['CLIENT_KEY']))?$_SESSION['CLIENT_KEY']:'';
	
	$tab_id_rt = (isset($_REQUEST["call_no"])&&!empty($_REQUEST["call_no"]))?$_REQUEST["call_no"]:'';
	
	$person_id = (isset($_SESSION["CP"]["tab".$tab_id_rt]["found_person_id"]) && !empty($_SESSION["CP"]["tab".$tab_id_rt]["found_person_id"]))?($_SESSION["CP"]["tab".$tab_id_rt]["found_person_id"]):"";

    $data_json = '{"module_name_ajax":"recent_lead","reqType":"recentLead","person_id":"'.$person_id.'","page":"1","key":"'.$key.'"}';
    $encoded_data_json = base64_encode($data_json);
    $lead_count	=	0;
    // $query_lead_count = "select count(*) as lead_count from lead_details where person_id=".$person_id." and lead_status_id<>2";   
	if(!empty($person_id)){
		$query_lead_count = "select count(*) as lead_count from lead_details where person_id=".$person_id;   
		$exe_lead_count = $DB->EXECUTE_QUERY($query_lead_count,$DB_H); 
		$tName_lead_count = $DB->FETCH_ARRAY($exe_lead_count,MYSQLI_ASSOC);
		$lead_count = $tName_lead_count['lead_count'];
	}
?>
<SCRIPT language="javascript" type="text/javascript">
	var pageRecent = 2;
	var stopSearchRecent = false;
	$(document).ready(function(){
		$('#start_demo_icon').attr('data-tour','recent_leads_module');
	});
	
	function createGridRecentLead(messageCase,result){
		var delay = 2000;
		$.ajax({
			url : "recent_leads_ajax.php",
			data : "messageCase="+messageCase+"&result="+result,
			method: "post",
			success: function(html)
			{
				setTimeout(function() {
					$("#recent_leads_table_div .table tr:last").after(html);
					$('#loading-icon-recent').hide();
				},delay);
				//~Increment only if result is found
				if(messageCase == "success"){
					var pageRecent1 = pageRecent*10;
					var lead_count = '<?=$lead_count?>';
					if(pageRecent1<lead_count){
						$('#lmt').css('display','block');
						pageRecent++;
					}else{
						$('#lmt').css('display','none');
					}
				}else{
					//update stopSearch flag(Stop further searching, if no record is found)
					stopSearchRecent = true;
				}
			},
			beforeSend: function(){
				$('#loading-icon-recent').show();
			},
			error: function(){
				//~ $("#pickerDiv").html("");
				console.log("Error!!!");
			},
		});
	}
	
	function load_more_leads1(){
		if(stopSearchRecent == false){
		
			var source_page = "search_lead";

			var key = '<?=$key?>';
			var person_id = '<?=$person_id?>';
			
			var data = "module_name_ajax=recent_lead&reqType=recentLead&person_id="+person_id.trim()+"&page="+pageRecent+"&key="+key.trim();

			$.ajax({
				type: "POST",
				url: "call_to_api.php",
				xhrFields: {
					withCredentials: true
				},
				data: data,
				success: function(result){
					var result = result.split("#$#");
					
					var messageCase = ((result[1] != undefined) && (result[1] != '') && (result[1] != 0))?"success":"failure";
		
					if(messageCase == "success"){
						createGridRecentLead(messageCase,result[0]);	
						//~ pageRecent++;
					}else{
						//update stopSearch flag(Stop further searching, if no record is found)
						stopSearchRecent = true;
					}
				},
				error: function(){
					console.log("Error!!!");
				},
			});		
		}
	}
	</SCRIPT>
    <?php
    
    //$final_api  = "http://".DEFAULT_API_SERVER."/"._BASEDIR_."/api/request_manager.php";
    $final_api  = _CALL_API_DNS."/api/request_manager.php";
  
	$recent_leads_array=array("postData"=>$encoded_data_json);
    $my_result = do_remote($final_api,$recent_leads_array);
    
    //~ $my_result = json_decode(json_encode($my_result), True);
	
	//Define module details
	$moduleName = "recent_leads";
	$moduleFormName = "recent_leads_form";
	
	//Page Limit Variables initialisation code
	//~ $pageLimit="";
	$current_page=0;

	$abc = array ();
	$abc[] = new GridProperty ("Assigned User", "assigned_to_user_name", _PLAIN_TEXT_, _BLANK_, true, _BLANK_, _BLANK_,_BLANK_, _BLANK_, _BLANK_, _VAL_, true, true,true);
	$abc[] = new GridProperty ("Assigned Dept", "assigned_to_dept_name", _PLAIN_TEXT_,_BLANK_, true, _BLANK_,_BLANK_, _BLANK_, _BLANK_, _BLANK_, _VAL_, true, true,true);
	$abc[] = new GridProperty ("Docket No", "docket_no", _PLAIN_TEXT_,	_BLANK_, true, _BLANK_, _BLANK_, _BLANK_, _BLANK_, _BLANK_, _BLANK_, true, true,true);
	$abc[] = new GridProperty ("Lead Type", "lead_type", _PLAIN_TEXT_,	_BLANK_, true, _BLANK_, _BLANK_, _BLANK_, _BLANK_, _BLANK_, _VAL_, true, true,true);
	$abc[] = new GridProperty ("Priority", "priority_name", _PLAIN_TEXT_, _BLANK_, true, _BLANK_, _BLANK_, _BLANK_, _BLANK_, _BLANK_, _VAL_, true, true,true);
	$abc[] = new GridProperty ("Disposition", "disposition", _PLAIN_TEXT_, _BLANK_, true, _BLANK_, _BLANK_, _BLANK_, _BLANK_, _BLANK_, _VAL_, true, true,true);
	$abc[] = new GridProperty ("Sub Disposition", "sub_disposition",_PLAIN_TEXT_, _BLANK_, true, _BLANK_, _BLANK_, _BLANK_, _BLANK_, _BLANK_, _VAL_, true, true,true);
	$abc[] = new GridProperty ("Person Name", "person_name", _PLAIN_TEXT_,_BLANK_, true, _BLANK_, _BLANK_, _BLANK_, _BLANK_, _BLANK_, _VAL_, true, true,true);
	$abc[] = new GridProperty ("Lead Status", "lead_status",_PLAIN_TEXT_, _BLANK_, true, _BLANK_, _BLANK_, _BLANK_, _BLANK_, _BLANK_, _VAL_, true, true,true);
	$abc[] = new GridProperty ("Problem Reported", "problem_reported",_PLAIN_TEXT_, _BLANK_, true, _BLANK_, _BLANK_, _BLANK_, _BLANK_, _BLANK_, _VAL_, true, true,true);
	$abc[] = new GridProperty ("Created On", "created_on",_PLAIN_TEXT_, _BLANK_, true, _BLANK_, _BLANK_, _BLANK_, _BLANK_, _BLANK_, _VAL_, true, true,true);
	
	$thAtt = Array (
		"align"			=>	"center",
		"style"			=> "background-color:#495F69;"
	);
	
	$tdAtt = $tAtt = $rAtt = array();

	
	$hiddenVariables="";
	$str = "";
	$str = "<div id='loading-icon-recent' style='position: fixed;top:50%;left:40%;z-index: 999999999;display:none;' ><img src='images/loader.gif' style='height:100px;width:100px;'></div><div id='recent_leads_table_div'>";
	$GRID = new Grid($abc);
	$GRID->setKeyField ("lead_id");
	$GRID->enablePrintSave(false);
	$property_div = "leadsDetail_div";
	$GRID->setGridModuleName ($moduleName);
	$file_to_forward ="";
	$extraParameters = $hiddenVariables;
	$GRID->setTableAttributes ($tAtt);
	$GRID->setTrAttributes ($rAtt);
	$GRID->setTdAttributes ($thAtt);
	//~ $pageLimit = $GRID->GetPageLimit();
	
	$total = "";
	$str .= $GRID->startGrid ($current_page,$total,false,$extraParameters);
	$str .= $GRID->startHead ();
	$str .= $GRID->getHeaderRow ();
	$str .= $GRID->endHead ();
	$str .= $GRID->startTbody ();

	$i = 0;
	$record_count = 0;
	
	//~ $leadStatusColorArr = isset($_SESSION['SETTINGS']['status_color'])?$_SESSION['SETTINGS']['status_color']:array();
	
	if(isset($my_result->Success) && !empty($my_result->Success)){
		$tableArray = json_decode(json_encode($my_result->Success), True);
		$record_count = count($tableArray);
		foreach($tableArray  as $f){
			$f['docket_no'] = '<div title="'.$f["docket_no"].'"><b>'.$f["docket_no"].'</b></div>';
			
			$encodedProblemReported = str_replace(' ','+',$f["problem_reported"]);
			$decodedProblemReported = base64url_decode($encodedProblemReported);
			$problem_reported = wordwrap(strip_tags($decodedProblemReported),50,'<br>',true);
	
			$f["problem_reported"] =  '<div id="" class="" title="'.strip_tags($problem_reported).'">'.$problem_reported.'</div>';
			
			$row_color = isset($StatusColorArray[$f["lead_status_id"]])?$StatusColorArray[$f["lead_status_id"]]:"";
			if(!empty($row_color) && ($row_color!='#ffffff')){
				$tdAtt["class"] = "";
				// $rAtt["style"] = "background-color:".$row_color;
				// $tdAtt["style"] = "background-color:".$row_color;
				if(!empty($f["lead_status"])){
					$f["lead_status"]  = "<div style='background-color:".$row_color.";border-radius: 5px;height: 28px;padding:2px;' title='".$f["lead_status"]."'>".$f["lead_status"]."</div>";
				}
			}
			$GRID->setTrAttributes ($rAtt);
			$GRID->setTdAttributes ($tdAtt);
			$str .= $GRID->setResultRow ($f);
			$i++;
		}
		
	}else{
		//~ print_r($my_result->Error);
	}
	//~ if(!isset($my_result['Error'])){	
	//~ }

	$str .= $GRID->endGrid ();
	if(!empty($record_count)){
		if($record_count<$lead_count){
			$str .="<br>
			<button type='button' name='lmt' id='lmt' class='btn bg-teal-400' style='float:right; margin-bottom:10px;' data-lead='' onClick='load_more_leads1();' title='Load More Leads'><i class='fa fa-spinner'></i></button><br>
			<div class='clearfix'></div>";
		}
	}
	//~ div id='loading-icon-recent' style='position: fixed;top:50%;left:40%;z-index: 999999999;display:none;' ><img src='images/loader.gif' style='height:100px;width:100px;'></div><div id='recent_tickets_table_div'>
	
	
	$str .="</div>";
	
	//--------- End Grid Code ---------------------------------
	
	//-----------------Start Table View ------------------------------
	$record_id= _BLANK_;
	$caption = "";
	
	include(_INCLUDE_PATH."generateTableView.php");
	//-------------------End Table View -----------------------------
?>
