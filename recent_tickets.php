<?php
// if three condition will be true (FEATURE, assignType, show_ticket_history) then show history;

	require_once ("configs/config.php");
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

/*	$wChild = "";
	//User can see only his child's tickets
        $user_id = isset($_SESSION['USER_ID'])?$_SESSION['USER_ID']:'';

		$lw = $tw=array();
		//if _TICKET_CREATED_BY_ = 1, User will see tickets which are last assigned to him
		if(_TICKET_CREATED_BY_ == 1){
			$tw[]= " created_by_id = '".$user_id."' ";
		}

		//if _TICKET_LAST_ASSIGNED_ = 1, User will see tickets which are last assigned to him
		if(_TICKET_LAST_ASSIGNED_ == 1){
			$tw[] = " last_assigned_to_user_id = '".$user_id."' ";
		}
		
		$twstr =" AND ( ". implode(" OR ",$tw);
		if(empty($tw)){
			$twstr .= "1=1";
		}
		

		//if _LEAD_CREATED_BY_ = 1, User will see lead which are last assigned to him
		if(_LEAD_CREATED_BY_ == 1){
			$lw[]= " created_by_id = '".$user_id."' ";
		}

		//if _LEAD_LAST_ASSIGNED_ = 1, User will see lead which are last assigned to him
		if(_LEAD_LAST_ASSIGNED_ == 1){
			$lw[] = " last_assigned_to_user_id = '".$user_id."' ";
		}
		
		$lwstr =" AND ( ". implode(" OR ",$lw);
		if(empty($lw)){
			$lwstr .= "1=1";
		}

        if($user_id!=1 && !empty($user_id)){
        	$childString = fetchChild($user_id);
            $wChild = " OR assigned_to_user_id in (".$childString.") ";
		}
		$twstr .="$wChild ) ";
		$lwstr .="$wChild ) ";
*/
	if(isset($_POST["go_to_recent_tickets"])){
		$data_json = '{"module_name_ajax":"recent_ticket","reqType":"recentTicket","person_id":"'.$person_id.'","page":"1","key":"'.$key.'"}';
	}
	else if(isset($_POST["go_to_recent_tickets"])){
	    $data_json = '{"module_name_ajax":"recent_ticket","reqType":"recentLead","person_id":"'.$person_id.'","page":"1","key":"'.$key.'"}';	
	}else{
		$data_json = '{"module_name_ajax":"recent_ticket","reqType":"recentTicket","person_id":"'.$person_id.'","page":"1","key":"'.$key.'"}';
	}
        $encoded_data_json = base64_encode($data_json);
	$ticket_count	=	$lead_count	=	0;
	if(!empty($person_id)){
		$query_lead_count = "select count(*) as lead_count from lead_details where person_id=".$person_id." and lead_status_id<>2";
		$exe_lead_count = $DB->EXECUTE_QUERY($query_lead_count,$DB_H); 

		$tName_lead_count = $DB->FETCH_ARRAY($exe_lead_count,MYSQLI_ASSOC);
		$lead_count = $tName_lead_count['lead_count'];
		$query_ticket_count = "select count(*) as ticket_count from ticket_details_report where person_id=".$person_id." and ticket_status_id<>2"; 
		$exe_ticket_count = $DB->EXECUTE_QUERY($query_ticket_count,$DB_H); 

		$tName_ticket_count = $DB->FETCH_ARRAY($exe_ticket_count,MYSQLI_ASSOC);
		$ticket_count = $tName_ticket_count['ticket_count'];
	}
    $clientId	=	$_SESSION['CLIENT_ID'];
	$dynamic_file_tickets= array();
	$dynamic_file_json_tickets='';
	if(file_exists("/var/www/html/CZCRM/dynamic_config/ticket_details_customized_".$clientId)){
		$dynamic_file_json_tickets =file_get_contents("/var/www/html/CZCRM/dynamic_config/ticket_details_customized_".$clientId);	
		$dynamic_file_tickets = json_decode($dynamic_file_json_tickets,true);
	}
	if($dynamic_file_tickets){
		$basic_fields_json_tickets = base64_decode($dynamic_file_tickets['Basic']['customized_fields']['data']);
		$basic_fields_tickets = json_decode($basic_fields_json_tickets,true);
	}
	
?>
<SCRIPT language="javascript" type="text/javascript">
	var pageRecent = 2;
	var pageRecentLead = 2;
	var stopSearchRecent = false;
	$(document).ready(function(){
		$('#start_demo_icon').attr('data-tour','recent_tickets_module');
	});
	var page = 1;
	var stopSearch = false;
	function resetTicketSearch(){
		stopSearch = false;	
	}
	function createGridRecentTicket(messageCase,result){
		var tickt_rdio= $("#ticket_radio").is(':checked');
		var lead_rdio= $("#lead_radio").is(':checked');
		var call_prnt='';
		if(tickt_rdio){
			call_prnt='ticket';
		}else{
			call_prnt='lead';
		}
		
		var delay = 2000;
		$.ajax({
			url : "recent_tickets_ajax.php",
			data : "messageCase="+messageCase+"&result="+result+"&call_prnt="+call_prnt,
			method: "post",
			success: function(html)
			{
				setTimeout(function() {
					$("#recent_tickets_table_div .table tr:last").after(html);
					$('#loading-icon-recent').hide();
				},delay);
				//~Increment only if result is found
				if(messageCase == "success"){
					
					if(tickt_rdio){
						var pageRecent1 = pageRecent*10;
						var ticket_count = '<?=$ticket_count?>';
					}else{
						var pageRecent1 = pageRecentLead*10;
						var ticket_count = '<?=$lead_count?>';
					}
					
					if(pageRecent1<ticket_count){
						if(call_prnt == 'ticket'){
							//~ $('#lmt').css('display','block');
							$('#lmt').css('display','none');
						}else{
							//~ $('#lmt_lead').css('display','block');
							$('#lmt_lead').css('display','none');
						}
						if(tickt_rdio){
							pageRecent++;
						}else{
							pageRecentLead++;
						}
					}else{
						if(call_prnt == 'ticket'){
							$('#lmt').css('display','none');
						}else{
							$('#lmt_lead').css('display','none');
						}
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
				console.log("Error!!!");
			},
		});
	}
	function load_more_tickets1(){
		var user_id = '<?=isset($user_id)?$user_id:""?>';
		var tickt_rdio= $("#ticket_radio").is(':checked');
		var lead_rdio= $("#lead_radio").is(':checked');
		if(stopSearchRecent == false){
			var source_page = "search_ticket";
			var key = '<?=$key?>';
			var person_id = '<?=$person_id?>';
			if(tickt_rdio){
				var data = "module_name_ajax=recent_ticket&reqType=recentTicket&person_id="+person_id.trim()+"&page="+pageRecent+"&key="+key.trim()+"&user_id="+user_id;
			}else{
				var data = "module_name_ajax=recent_ticket&reqType=recentLead&person_id="+person_id.trim()+"&page="+pageRecentLead+"&key="+key.trim()+"&user_id="+user_id;
			}
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
						createGridRecentTicket(messageCase,result[0],);	
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
<div id="loading-icon" style="position: fixed;top:50%;left:40%;z-index: 999999999;display:none;" ><img src="images/loader.gif" style="height:100px;width:100px;"></div>
	<form action="" method="post" name='search_ticket_form' id='search_ticket_form' class='form-horizontal' enctype='multipart/form-data'>
		<div class="container">
			<div class="row">
				<?php 
				
					$checked	=	'checked';
					if(isset($_SESSION['FEATURE']['ticketing']) && $_SESSION['FEATURE']['ticketing']==1 && (((strtolower($assignType)=='ticket' || $assignType=='both') && $_SESSION["SETTINGS"]["show_ticket_history"]) || $_SESSION["USERNAME"]=="admin")){
				?>
					<label class="radio-inline" style="padding-top: 0px;">
						<input type="radio" name="optradio" id="ticket_radio" <?=$checked?> style="height: auto !important; margin-top: 0px;" onclick="fetch_data('ticket');"> Ticket
					</label>
					<?php
					$checked	=	'';
					}
			
					if(isset($_SESSION['FEATURE']['lead']) && $_SESSION['FEATURE']['lead']==1 && (((strtolower($assignType)=='lead' || $assignType=='both') && $_SESSION["SETTINGS"]["show_lead_history"]) || $_SESSION["USERNAME"]=="admin")){
					?>
					<label class="radio-inline" style="padding-top: 0px;">
						<input type="radio" name="optradio" id="lead_radio" <?=$checked?> style="height: auto !important; margin-top: 0px;" onclick="fetch_data('lead');"> Lead
					</label>
				<?php
					}
				
				?>
			</div>
			<br>
			<div class="row">
				<input id="search_ticket_field" name="search_ticket_field" placeholder='Docket Number' onkeyup="javascript:autoFill('search_ticket_field','docket_no','','ticket_details','','group by docket_no','');resetTicketSearch();" maxlength="25" class="form-control-users" type="text" style='width:80%;margin-right:2%;'>
				<button type='button' name='search_ticket' id='search_ticket' value="search" class='btn bg-teal-400'>Search</button>
				<button type='button' name='clearSearch' id='clearSearch' value="clear" class='btn bg-teal-400'>Clear</button>
			</div>
		</div>
	</form>
<?php 
//~ print_r($_POST);
   // $final_api  = "http://".DEFAULT_API_SERVER."/"._BASEDIR_."/api/request_manager.php";
    $final_api  = _CALL_API_DNS."/api/request_manager.php";
  
	$grid_ticket=$recent_tickets_array=array("postData"=>$encoded_data_json);
	
	$grid_ticket_json=isset($grid_ticket['postData'])?base64_decode($grid_ticket['postData']):'';
	$keyy=$perssn_id='';
	if($grid_ticket_json!=''){
		$grid_ticket_arry=json_decode($grid_ticket_json,true);
		$keyy=$grid_ticket_arry['key'];
		$perssn_id=$grid_ticket_arry['person_id'];
	}
    $my_result = do_remote($final_api,$recent_tickets_array);
	//Define module details
	$moduleName = "recent_tickets";
	$moduleFormName = "recent_tickets_form";
	
	//Page Limit Variables initialisation code
	
	$current_page=0;
	
	//~ List of field names
	$assigned_user = (isset($basic_fields_tickets['user_name']['displayname']) && !empty($basic_fields_tickets['user_name']['displayname']))?$basic_fields_tickets['user_name']['displayname']:'Assigned User';
	
	$assigned_dept = (isset($basic_fields_tickets['dept_name']['displayname']) && !empty($basic_fields_tickets['dept_name']['displayname']))?$basic_fields_tickets['dept_name']['displayname']:'Assigned Dept';
	
	$ticket_type = (isset($basic_fields_tickets['ticket_type']['displayname']) && !empty($basic_fields_tickets['ticket_type']['displayname']))?$basic_fields_tickets['ticket_type']['displayname']:'Ticket Type';
	
	$disposition = (isset($basic_fields_tickets['disposition']['displayname']) && !empty($basic_fields_tickets['disposition']['displayname']))?$basic_fields_tickets['disposition']['displayname']:'Disposition';
	
	$sub_disposition = (isset($basic_fields_tickets['sub_disposition']['displayname']) && !empty($basic_fields_tickets['sub_disposition']['displayname']))?$basic_fields_tickets['sub_disposition']['displayname']:'Sub Disposition';
	
	$priority_name = (isset($basic_fields_tickets['priority_name']['displayname']) && !empty($basic_fields_tickets['priority_name']['displayname']))?$basic_fields_tickets['priority_name']['displayname']:'Priority';
	
	$ticket_status = (isset($basic_fields_tickets['ticket_status']['displayname']) && !empty($basic_fields_tickets['ticket_status']['displayname']))?$basic_fields_tickets['ticket_status']['displayname']:'Ticket Status';
	
	$problem_reported = (isset($basic_fields_tickets['problem_reported']['displayname']) && !empty($basic_fields_tickets['problem_reported']['displayname']))?$basic_fields_tickets['problem_reported']['displayname']:'Problem Reported';
	
	$agent_remarks = (isset($basic_fields_tickets['agent_remarks']['displayname']) && !empty($basic_fields_tickets['agent_remarks']['displayname']))?$basic_fields_tickets['agent_remarks']['displayname']:'Agent Remarks';
	
	$abc = array ();
	
	$thAtt = Array (
		"align"			=>	"center",
		"style"			=> "background-color:#495F69;"
	);
	
	$tdAtt = $tAtt = $rAtt = array();

	$hiddenVariables="";
	$str = "";
	$str = "<div id='loading-icon-recent' style='position: fixed;top:50%;left:40%;z-index: 999999999;display:none;' ><img src='images/loader.gif' style='height:100px;width:100px;'></div><div id='recent_tickets_table_div'>";
	$GRID = new Grid($abc);
	$GRID->setKeyField ("ticket_id");
	$GRID->enablePrintSave(false);
	$property_div = "ticketsDetail_div";
	$GRID->setGridModuleName ($moduleName);
	$file_to_forward ="";
	$extraParameters = $hiddenVariables;
	$GRID->setTableAttributes ($tAtt);
	$GRID->setTrAttributes ($rAtt);
	$GRID->setTdAttributes ($thAtt);
	
	$total = "";
	$str .= $GRID->startGrid ($current_page,$total,false,$extraParameters);
	$str .= $GRID->startHead ();
	$str .= $GRID->getHeaderRow ();
	$str .= $GRID->endHead ();
	$str .= $GRID->startTbody ();

	$i = 0;
	$record_count = 0;
	
	if(isset($my_result->Success) && !empty($my_result->Success)){
		$tableArray = json_decode(json_encode($my_result->Success), True);
		$record_count = count($tableArray);
		foreach($tableArray  as $f){
			$f['docket_no'] = '<div title="'.$f["docket_no"].'"><b>'.$f["docket_no"].'</b></div>';
			
			$encodedProblemReported = str_replace(' ','+',$f["problem_reported"]);
			$decodedProblemReported = base64url_decode($encodedProblemReported);
			$problem_reported = wordwrap(strip_tags($decodedProblemReported),50,'<br>',true);
	
			$f["problem_reported"] =  '<div id="" class="" title="'.strip_tags($problem_reported).'">'.$problem_reported.'</div>';
			
			$row_color = isset($StatusColorArray[$f["ticket_status_id"]])?$StatusColorArray[$f["ticket_status_id"]]:"";
			if(!empty($row_color) && ($row_color!='#ffffff')){
				$tdAtt["class"] = "";
				// $rAtt["style"] = "background-color:".$row_color;
				// $tdAtt["style"] = "background-color:".$row_color;
				$f["ticket_status"]  = "<div style='background-color:".$row_color.";border-radius: 5px;padding:2px;' title='".$f["ticket_status"]."'>".$f["ticket_status"]."</div>";
			}
			$GRID->setTrAttributes ($rAtt);
			$GRID->setTdAttributes ($tdAtt);
			$str .= $GRID->setResultRow ($f);
			$i++;
		}
		
	}

	$str .= $GRID->endGrid ();
	
	if(!empty($record_count)){
		$str .="<br><button type='button' name='lmt' id='lmt' class='btn bg-teal-400' style='float:right; margin-bottom:10px; display:block;' data-ticket='' onClick='load_more_tickets1();' title='Load More Tickets'><i class='fa fa-spinner'></i></button><br><div class='clearfix'></div>";
		
	}
	
	$str .="</div>";
	
	$record_id= _BLANK_;
	$caption = "";
	
	include(_INCLUDE_PATH."generateTableView.php");
?>
<script>
	function fetch_data(val){
		pageRecent = 1;
		pageRecentLead = 1;
	
		if(val=='ticket'){
			 $('#lmt').css('display','block');
			$('#lmt').css('display','none');
			$('#lmt_lead').css('display','none');
			 $('#search_ticket_field').attr("placeholder", "Docket Number");
			var serch_param =$('#search_ticket_field').val();
			var serch_for='ticket';
			var search_ticket = serch_param;
			if(serch_param!=''){
				show_tickets(search_ticket,"search",serch_for);	
			}else{
				var perssn_id ='<?=$perssn_id?>';
				show_tickets(perssn_id,"grid_list",serch_for);
			}
		}else{
			$('#lmt').css('display','none');
			//~ $('#lmt_lead').css('display','block');
			$('#lmt_lead').css('display','none');
			$('#search_ticket_field').attr("placeholder", "Lead Number");
			var serch_param =$('#search_ticket_field').val();
			
			var serch_for='lead';
			var search_ticket = serch_param;
			if(serch_param!=''){
				show_tickets(search_ticket,"search",serch_for);
				
			}else{
				var perssn_id ='<?=$perssn_id?>';
				show_tickets(perssn_id,"grid_list",serch_for);
			}
		}
	}
	$("#search_ticket").click(function(){
		page = 1;
		var ret=true;
		/*var validator = $("#search_ticket_form").validate(
		{			
			ignore: [],	
			debug: false,
			rules:
			{
				search_ticket_field:
				{
					required : true,
				},
			
			},
			errorPlacement: function(error, element) {
				error.insertAfter(element.closest('.search-form'));
			},
		});
		var validation_result_search_ticket =	$("#search_ticket_form").valid();*/
		var validation_result_search_ticket=true;
		//~ alert(validation_result_search_ticket);
		var search_ticket = $("#search_ticket_field").val();
		var tickt_rdio= $("#ticket_radio").is(':checked');
		var lead_rdio= $("#lead_radio").is(':checked');
		var serch_for ='';
		if(tickt_rdio){
			serch_for='ticket';
		}else{
			serch_for='lead';
		}
		if(validation_result_search_ticket){
			if(search_ticket){
				if(stopSearch == false){
					show_tickets(search_ticket,"search",serch_for);
				}
			}else{
				show_tickets(search_ticket,"search",serch_for);
			//~ createGridTicket("","","","grid_list",serch_for);
			}
		}else{
			createGridTicket("","","");
		}
	});	
	$("#clearSearch").click(function(){
		$("#search_ticket_field").val("");
		page = 1;
		stopSearch = false;
		createGridTicket("","","");
		var tickt_rdio= $("#ticket_radio").is(':checked');
		var lead_rdio= $("#lead_radio").is(':checked');
		
		if(tickt_rdio){
			var serch_for='ticket';
			var perssn_id ='<?=$perssn_id?>';
			show_tickets(perssn_id,"grid_list",serch_for);			
		}else{
			var serch_for='lead';
			var perssn_id ='<?=$perssn_id?>';
			show_tickets(perssn_id,"grid_list",serch_for);
		}
	});
	function createGridTicket(messageCase="",result,search_ticket="",mode,srch_for){
		var tickt_rdio= $("#ticket_radio").is(':checked');
		var lead_rdio= $("#lead_radio").is(':checked');
		var total_count = '';
		if(srch_for == 'ticket'){
			total_count = '<?=$ticket_count?>';
		}else if(srch_for == 'lead'){
			total_count = '<?=$lead_count?>';
		}
		if(messageCase == "" && result == ""){
			$('#loading-icon').hide();
			$("#recent_tickets_table_div").html("");
			
		}else{
			var delay = 500;
			var tab_id = '<?=isset($tab_st)?$tab_id:""?>';
			$.ajax({
				url : "search_ticket_ajax.php",
				data : "messageCase="+messageCase+"&result="+result+"&search_string="+search_ticket+"&tab_id="+tab_id+"&mode="+mode+"&serch_prnt="+srch_for+"&total_count="+total_count,
				method: "post",
				success: function(html)
				{
					if(mode == "search"){
						$("#recent_tickets_table_div").html(html);
						$('#loading-icon').hide();
					}else{
						setTimeout(function() {
							$("#recent_tickets_table_div").html(html);
							$('#loading-icon').hide();
						}, delay);
					}
					//~Increment only if result is found
					if(messageCase == "success"){
						page=1;						
						if(tickt_rdio){
							var pageRecent1 = pageRecent*10;
							var ticket_count = '<?=$ticket_count?>';
							if(pageRecent1<ticket_count){
								//~ $('#lmt').css('display','block');
								$('#lmt').css('display','none');
								pageRecent++;
							}else{
								$('#lmt').css('display','none');
							}
						}else{
							var pageRecent1 = pageRecentLead*10;
							var ticket_count = '<?=$lead_count?>';
							if(pageRecent1<ticket_count){
								//~ $('#lmt_lead').css('display','block');
								$('#lmt_lead').css('display','none');
								pageRecentLead++;
							}else{
								$('#lmt_lead').css('display','none');
							}
						}
					}else{
						//update stopSearch flag(Stop further searching, if no record is found)
						stopSearch = true;
					}
				},
				beforeSend: function(){
					$('#loading-icon').show();
				},
				error: function(){
					$('#loading-icon').hide();
					$("#recent_tickets_table_div").html("");
					console.log("Error!!!");
				},
			});
		}
	}	
	//~ Function to send request to api
	function show_tickets(search_ticket,mode,srch_for){
		//~ alert("show tickets =========="+search_ticket+"//////////"+mode+"//////////"+srch_for);
		//~ show tickets ==========//////////grid_list//////////lead
		var source_page = "search_ticket";
	
		var key = '<?=isset($_SESSION['CLIENT_KEY'])?$_SESSION['CLIENT_KEY']:""?>';
		var user_id = '<?=isset($user_id)?$user_id:""?>';
		if(mode=='search'){
			if(srch_for=='ticket'){
				var data = "module_name_ajax=search_ticket&search_for="+srch_for+"&reqType=searchTicket&docket_no="+search_ticket.trim()+"&page="+page+"&key="+key.trim()+"&user_id="+user_id;
			}else{
				var data = "module_name_ajax=search_lead&search_for="+srch_for+"&reqType=searchLead&docket_no="+search_ticket.trim()+"&page="+page+"&key="+key.trim()+"&user_id="+user_id;
			}
		}else{
			if(srch_for=='ticket'){
				var data = "module_name_ajax=search_ticket&search_for="+srch_for+"&reqType=recentTicket&person_id="+search_ticket.trim()+"&page="+page+"&key="+key.trim()+"&user_id="+user_id;
			}else{
				var data = "module_name_ajax=search_lead&search_for="+srch_for+"&reqType=recentLead&person_id="+search_ticket.trim()+"&page="+page+"&key="+key.trim()+"&user_id="+user_id;
			}
		}
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
				createGridTicket(messageCase,result[0],search_ticket,mode,srch_for);				
			},
			error: function(){
				console.log("Error!!!");
			},
		});		
	}
	$("#person_detail_div").hide();
	</script>
	<?php
	if(isset($_POST["go_to_recent_tickets"])){
	?>
	<script>
		let ticket_radio = document.getElementById("ticket_radio");
		if(ticket_radio){
			ticket_radio.checked = true;
		}
		// document.getElementById("ticket_radio").checked = true;	
		fetch_data('ticket');
	</script>
	<?php	
	}
	if(isset($_POST["go_to_recent_leads"])){
	?>
	<script>
		let lead_radio = document.getElementById("lead_radio");
		if(lead_radio){
			lead_radio.checked = true;
		}
		// document.getElementById("lead_radio").checked = true;
		fetch_data('lead');
	</script>
	<?php
	}
	?>
	<script>
	$(function() {
		var a = ($("input[name='optradio']:checked").attr("onclick"));
		eval(a);
	});
</script>
