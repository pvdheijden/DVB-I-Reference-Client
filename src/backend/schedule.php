<?php
include 'configuration.php';
header('Content-Type: text/xml');
header("Access-Control-Allow-Origin: *");
$timeformat = "Y-m-d\TH:i:s\Z";
date_default_timezone_set ("Zulu");
if(isset($_GET['now_next']) && isset($_GET['sid']) && $_GET['now_next'] == "true" ) {
    $now_next = getNowNext($_GET['sid']);
    if($now_next != NULL) {
        echo $now_next;
        exit();
    }
    $present_start = rand(1,45);
    $present_duration = 45;
    $following_duration = 30;
    $present_starttime = time()-($present_start *60);
    $present_starttime = $present_starttime - ( $present_starttime % 60);
    $following_starttime = $present_starttime + ($present_duration *60);
    $following_endtime =  $following_starttime + ($following_duration * 60);
    $schedule= file_get_contents("nownext.xml");
    $schedule =str_replace("2013-09-25T11:15:00Z",date($timeformat, $present_starttime),$schedule);
    $schedule =str_replace("2013-09-25T12:00:00Z",date($timeformat, $following_starttime),$schedule);
    $schedule =str_replace("2013-09-25T12:30:00.000Z",date($timeformat, $following_endtime),$schedule);
    $schedule =str_replace("SERVICE_ID_TEMPLATE",$_GET['sid'],$schedule);
    $schedule =str_replace("INSTALL~~LOCATION",$install_location,$schedule);
    echo $schedule;
}
else if(isset($_GET['start']) && isset($_GET['end']) && isset($_GET['sid']) ){
    $schedule_start = intval($_GET['start']);
    $schedule_end = intval($_GET['end']);
    $start_of_day = time();
    $start_of_day = $start_of_day - ($start_of_day % 86400);

    //Limit schedule requests to +-28 days from current date
    if( $schedule_end-$schedule_start < 0 || $schedule_start < ($start_of_day - 60*60*24*28) || $schedule_end > ($start_of_day + 60*60*24*29))  {
        http_response_code(400);
        exit();
    }
    $sid = $_GET['sid'];
    $inclusive = false;
    if(isset($_GET['inclusive']) && $_GET['inclusive'] == "true") {
        $inclusive = true;
    }
    $schedule = getSchdeule($sid,$schedule_start,$schedule_end,$inclusive);
    if($schedule  != NULL) {
        echo $schedule;
        exit();
    }
    $program_length = rand(10,60);
    $start = $schedule_start;
    if($inclusive) {
     $start = $start - (rand(1,$program_length)*60);
    }
    else {
     $start = $start + (rand(1,$program_length)*60);
    }
    $start = $start - ( $start % 60);
    $schedule_start = $start;
    $programs = "";
    $schedules = "";
    $index = 1;
    $id = "crid://dvbi-reference/".$sid.".".$index;
    while($start < $schedule_end) {
        $schedule = file_get_contents("schedule_event_template.xml");
        $schedule =str_replace("PROGRAM_ID_TEMPLATE",$id,$schedule);
        $schedule =str_replace("START_TIME_TEMPLATE",date($timeformat, $start),$schedule);
        $schedule =str_replace("DURATION_TEMPLATE","PT".$program_length."M",$schedule);
        $schedules = $schedules.$schedule;
        $program = file_get_contents("program_information_template.xml");
        $program =str_replace("PROGRAM_ID_TEMPLATE",$id,$program);
        $program =str_replace("INSTALL~~LOCATION",$install_location,$program);
        $programs = $programs.$program;
        $index++;
        $id = "crid://".$sid.".".$index;
        $start = $start +($program_length*60);
        $program_length = rand(10,60);
    }
    $schedule_document = file_get_contents("schedule_template.xml");
    $schedule_document =str_replace( "SERVICE_ID_TEMPLATE",$_GET['sid'],$schedule_document);
    $schedule_document =str_replace( "START_TEMPLATE",date($timeformat, $schedule_start),$schedule_document);
    $schedule_document =str_replace( "END_TEMPLATE",date($timeformat, $start),$schedule_document);
    $schedule_document =str_replace( "<!--PROGRAMS-->",$programs,$schedule_document);
    $schedule_document =str_replace( "<!--GROUPS-->","",$schedule_document);
    $schedule_document =str_replace( "<!--SCHEDULES-->",$schedules,$schedule_document);
    echo $schedule_document;
}

function getNowNext( $sid ) {
    global $timeformat;
    $sid_file = str_replace(":","_",$sid);
    if (strpos($sid_file, '/') !== false || strpos($sid_file, '..') !== false || file_exists("./schedule_templates/".$sid_file.".xml") === false) {
        return NULL;
    }
    $dateformat = "Y-m-d";
    $current_time = time();
    $schedule_str= file_get_contents("./schedule_templates/".$sid_file.".xml");
    $schedule_str =str_replace( "DATE_TEMPLATE",date($dateformat,$current_time),$schedule_str);
    $schedule = new SimpleXMLElement($schedule_str);
    $next = NULL;
    for($i = 0; $i < count($schedule->ProgramDescription->ProgramLocationTable->Schedule->ScheduleEvent);$i++) {
        $event = $schedule->ProgramDescription->ProgramLocationTable->Schedule->ScheduleEvent[$i];
        $start = strtotime($event->PublishedStartTime);
        if($start > $current_time) {
            $next = $event;
            break;
        }
    }
    if($next != NULL) {
        $now = $schedule->ProgramDescription->ProgramLocationTable->Schedule->ScheduleEvent[$i-1];
    }
    else {
        $now = $schedule->ProgramDescription->ProgramLocationTable->Schedule->ScheduleEvent[count($schedule->ProgramDescription->ProgramLocationTable->Schedule->ScheduleEvent)-1];
        $next = $schedule->ProgramDescription->ProgramLocationTable->Schedule->ScheduleEvent[0];
    }
	if ($now != NULL || $next != NULL){
		$groupInformationTable = new SimpleXMLElement('<GroupInformationTable/>');
		if ($now != NULL){
			$groupInformation = $groupInformationTable->addChild('GroupInformation');
			$groupInformation->addAttribute('groupId','crid://dvb.org/metadata/schedules/now-next/now');
			$groupInformation->addAttribute('numOfItems','1');
			$groupInformation->addAttribute('ordered','true');

			$groupType = $groupInformation->addChild('GroupType');
			$groupType->addAttribute('value','otherCollection');
			$groupType->addAttribute('xsi:type','ProgramGroupTypeType','http://www.w3.org/2001/XMLSchema-instance');
			$basicDescription = $groupInformation->addChild('BasicDescription');

		}
		if ($next != NULL){

			$groupInformation = $groupInformationTable->addChild('GroupInformation');
			$groupInformation->addAttribute('groupId','crid://dvb.org/metadata/schedules/now-next/later');
			$groupInformation->addAttribute('numOfItems','1');
			$groupInformation->addAttribute('ordered','true');

			$groupType = $groupInformation->addChild('GroupType');
			$groupType->addAttribute('value','otherCollection');
			$groupType->addAttribute('xsi:type','ProgramGroupTypeType','http://www.w3.org/2001/XMLSchema-instance');
			$basicDescription = $groupInformation->addChild('BasicDescription');

		}
		$domxml = dom_import_simplexml($groupInformationTable);
		$groupstr = $domxml->ownerDocument->saveXML($domxml->ownerDocument->documentElement);
	}

    $now_program = NULL;
    $next_program = NULL;
    foreach ($schedule->ProgramDescription->ProgramInformationTable->ProgramInformation as $program) {
        if((string)$program['programId'] == (string)$now->Program['crid']) {
            $now_program = $program;
	    $member = $now_program->addChild('MemberOf');
	    $member->addAttribute('crid','crid://dvb.org/metadata/schedules/now-next/now');
	    $member->addAttribute('index','1');
            if($next_program != NULL) {
                break;
            }
        }
        if((string)$program['programId'] == (string)$next->Program['crid']) {
            $next_program = $program;
	    $member = $next_program->addChild('MemberOf');
	    $member->addAttribute('crid','crid://dvb.org/metadata/schedules/now-next/later');
	    $member->addAttribute('index','1');
            if($now_program != NULL) {
                break;
            }
        }

    }

    $endtime = strtotime($next->PublishedStartTime);
    $endtime += ISO8601ToSeconds($next->PublishedDuration);
    $schedule_document = file_get_contents("schedule_template.xml");
    $schedule_document =str_replace( "START_TEMPLATE",$now->PublishedStartTime,$schedule_document);
    $schedule_document =str_replace( "END_TEMPLATE",date($timeformat, $endtime),$schedule_document);
    $schedule_document =str_replace( "<!--PROGRAMS-->",$now_program->asXML().$next_program->asXML(),$schedule_document);
    $schedule_document =str_replace( "<!--GROUPS-->",$groupstr,$schedule_document);
    $schedule_document =str_replace( "<!--SCHEDULES-->",$now->asXML().$next->asXML(),$schedule_document);
    $schedule_document =str_replace( "SERVICE_ID_TEMPLATE",$sid,$schedule_document);
    return $schedule_document;
}

function getSchdeule( $sid,$start,$end,$inclusive ) {
    global $timeformat;
    $sid_file = str_replace(":","_",$sid);
    if (is_int($start) === false || is_int($end) === false || strpos($sid_file, '/') !== false || strpos($sid_file, '..') !== false || file_exists("./schedule_templates/".$sid_file.".xml") === false) {
        return NULL;
    }

    $dateformat = "Y-m-d";
    $current_time = $start;
    $schedule_str= file_get_contents("./schedule_templates/".$sid_file.".xml");
    $schedule_str =str_replace( "DATE_TEMPLATE",date($dateformat,$current_time),$schedule_str);
    $schedule = New Simplexmlelement($schedule_str);
    $programs = array();
    $end_reached = false;
    $previous_count = -1;
    while($end_reached == false  && count($schedule->ProgramDescription->ProgramLocationTable->Schedule->ScheduleEvent) > 0 ) {

        for($i = 0; $i < count($schedule->ProgramDescription->ProgramLocationTable->Schedule->ScheduleEvent);$i++) {
            $event = $schedule->ProgramDescription->ProgramLocationTable->Schedule->ScheduleEvent[$i];
            $event_start = strtotime($event->PublishedStartTime);
            $event_end = $event_start + ISO8601ToSeconds($event->PublishedDuration);
            if((($inclusive &&  $start <= $event_end )||  $start <= $event_start )&& $event_start < $end) {
                array_push($programs,$event);
            }
            if($event_end > $end) {
                $end_reached = true;
                break;
            }
        }
        if($previous_count == count($programs)) {
            //No new programs found, break
            break;
        }
        $previous_count = count($programs);
        if($end_reached == false) {
            $current_time += 24*60*60;
            $schedule_str= file_get_contents("./schedule_templates/".$sid_file.".xml");
            $schedule_str =str_replace( "DATE_TEMPLATE",date($dateformat,$current_time),$schedule_str);
            $schedule = New Simplexmlelement($schedule_str);
        }
    }
    $last = $programs[count($programs)-1];
    $endtime = strtotime($last->PublishedStartTime);
    $endtime += ISO8601ToSeconds($last->PublishedDuration);
    $schedule_document = file_get_contents("schedule_template.xml");
    $schedules = "";
    $program_infos = array();
    $info_str = "";
    foreach($programs as $program) {
        $schedules .= $program->asXML();
        $program_id =  (string)$program->Program['crid'];
        if(!array_key_exists($program_id,$program_infos)) {
            foreach ($schedule->ProgramDescription->ProgramInformationTable->ProgramInformation as $program_info) {
                if((string)$program_info['programId'] == $program_id) {
                        $program_infos[$program_id] = true;
                        $info_str .= $program_info->asXML();
                        break;
                }
            }
        }
    }
    $schedule_document =str_replace( "START_TEMPLATE",$programs[0]->PublishedStartTime,$schedule_document);
    $schedule_document =str_replace( "END_TEMPLATE",date($timeformat, $endtime),$schedule_document);
    $schedule_document =str_replace( "<!--PROGRAMS-->",$info_str,$schedule_document);
    $schedule_document =str_replace( "<!--GROUPS-->",'',$schedule_document);
    $schedule_document =str_replace( "<!--SCHEDULES-->",$schedules,$schedule_document);
    $schedule_document =str_replace( "SERVICE_ID_TEMPLATE",$sid,$schedule_document);

    return $schedule_document;
}

function ISO8601ToSeconds($ISO8601){
	$interval = new DateInterval($ISO8601);

	return ($interval->d * 24 * 60 * 60) +
		($interval->h * 60 * 60) +
		($interval->i * 60) +
		$interval->s;
}
?>
