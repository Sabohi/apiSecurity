<?php
	require_once ("configs/config.php");
	require_once (_MODULE_PATH . "DATABASE/database_config.php");
	require_once (_MODULE_PATH . "DATABASE/DatabaseManageri.php");
	require_once (_MODULE_PATH . "SESSION/session_config.php");
	require_once (_MODULE_PATH . "SESSION/session.php");
	require_once (_MODULE_PATH . "FUNCTIONS/functions.php");
	require_once (_MODULE_PATH . "PERMISSIONS/permission.php");
	
	$tab_st = isset($_REQUEST["call_no"])?$_REQUEST["call_no"]:"";
?>
<script>
	var page = 1;
	var stopSearch = false;
	function resetTicketSearch(){
		stopSearch = false;	
	}
</script>
<div id="loading-icon" style="position: fixed;top:50%;left:40%;z-index: 999999999;display:none;" ><img src="images/loader.gif" style="height:100px;width:100px;"></div>
<form action="" method="post" id='search_ticket_form' class='form-horizontal' enctype='multipart/form-data'>
	<div class="container">
		<div class="row">
			<label class="radio-inline" style="padding-top: 0px;">
				<input type="radio" name="optradio" checked style="height: auto !important; margin-top: 0px;"> Ticket
			</label>
			<label class="radio-inline" style="padding-top: 0px;">
				<input type="radio" name="optradio" style="height: auto !important; margin-top: 0px;"> Lead
			</label>
		</div>
		<br>
		<div class="row">
			<input id="search_ticket_field" name="search_ticket_field" placeholder='Docket Number' onkeyup="javascript:autoFill('search_ticket_field','docket_no','','ticket_details','','group by docket_no','');resetTicketSearch();" maxlength="25" class="form-control-users" type="text" style='width:80%;margin-right:2%;'>
			<button type='button' name='search_ticket' id='search_ticket' value="search" class='btn bg-teal-400'>Search</button>
			<button type='button' name='clearSearch' id='clearSearch' value="clear" class='btn bg-teal-400'>Clear</button>
		</div>
	</div>
</form>
<div id="pickerDivTicket"></div>
<script type="text/javascript">	
	//~ Function for creating ticket div
	//~ Parameters(1=>success/failure/empty,2=>result string,3=>text entered in search field,4=>search/load)
	function createGridTicket(messageCase="",result,search_ticket="",mode="search"){
		if(messageCase == "" && result == ""){
			$('#loading-icon').hide();
			$("#pickerDivTicket").html("");
		}else{
			var delay = 500;
			var tab_id = '<?=$tab_st?>';
			$.ajax({
				url : "search_ticket_ajax.php",
				data : "messageCase="+messageCase+"&result="+result+"&search_string="+search_ticket+"&tab_id="+tab_id+"&mode="+mode,
				method: "post",
				success: function(html)
				{
					if(mode == "search"){
						$("#pickerDivTicket").html(html);
						$('#loading-icon').hide();
					}else{
						setTimeout(function() {
							$('#search_ticket_table tr:last').after(html);
							$('#loading-icon').hide();
						}, delay);
					}
					
					//~Increment only if result is found
					if(messageCase == "success"){
						page++;
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
					$("#pickerDivTicket").html("");
					console.log("Error!!!");
				},
			});
		}
	}	
		
	//~ Function to send request to api
	function show_tickets(search_ticket,mode="search"){
		var source_page = "search_ticket";

		var key = '<?=isset($_SESSION['CLIENT_KEY'])?$_SESSION['CLIENT_KEY']:""?>';

		var data = "module_name_ajax=search_ticket&reqType=searchTicket&docket_no="+search_ticket.trim()+"&page="+page+"&key="+key.trim();
		
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
	
				createGridTicket(messageCase,result[0],search_ticket,mode);	
				
			},
			error: function(){
				console.log("Error!!!");
			},
		});		
	}
	
	//~ search tickets based on docket number
	$("#search_ticket").click(function(){
		page = 1;
		
		var ret=true;
		var validator = $("#search_ticket_form").validate(
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
		var validation_result_search_ticket =	$("#search_ticket_form").valid();
		
		
		var search_ticket = $("#search_ticket_field").val();
		
		//~ if(search_ticket != undefined && search_ticket != ""){
		if(validation_result_search_ticket){
			if(stopSearch == false){
				show_tickets(search_ticket,"search");
			}
		}else{
			//~ bootbox.alert("Please enter docket number.");
			createGridTicket("","");
		}
	});	
	
	//~ Load more tickets based on docket number
	function load_more_tickets(button_id){
		var search_ticket = $('#'+button_id).attr('data-ticket');
		if(stopSearch == false){
			show_tickets(search_ticket,"load");
		}
	}
	//~Function to clear search
	$("#clearSearch").click(function(){
		$("#search_ticket_field").val("");
		page = 1;
		stopSearch = false;
		createGridTicket("","");
	});
	$(document).ready(function() {
		$("#search_ticket_form #search_ticket_field").keydown(function(event){
			if(event.keyCode == 13) {
			  event.preventDefault();
			  return false;
			}
		});
	});
</script>