<?php
ob_start();

/**
*@author Dan Gyinaye Poku <dan.poku@ashesi.edu.gh>
*@author Makafui Fie <makafui.fie@ashesi.edu.gh>
*@author Stephan Ofosuhene <stephan.ofosuhene@ashesi.edu.gh>
*/



/**
*@package \almanac\Smsgh\
*/
require	'./Smsgh/Api.php';


/**
*@var string $location Contains name of geographical location of farm
*@var string $crop Crop type grown 
*@var string $start_date Planting date of the crop
*@var object $start_date_value Converts $start_date to a date
*@var integer $land_area Contains the land size value in acres
*@var integer $volume Contains irrigation needs of a crop in litres
*/
$location = $_REQUEST['loc'];
$crop = $_REQUEST['crop'];
$start_date = $_REQUEST['date'];
$start_date_value = new DateTime($start_date);
$land_area = $_REQUEST['area'];
$volume=0;


/**
*Rainfall(mm) requirements for maize at different stages of plant growth (Source: FAO)
*/
define('WATER_INTITIAL_STAGE', '4.445');
define('WATER_MID_STAGE', '8.89'); 
define('WATER_LATE_STAGE', '2.225');

/**
*@var object $date Get date and time from server
*/
$date = new DateTime();
$growth_stage = $start_date_value->diff($date);	//Calculate date difference between planting date and current date
$growth_stage = $growth_stage->days;	//Convert Unix timestamp to days


/**
*This method requests header information provided by user through frontline sms (version 1.6.16.3) platform 
*@return string  The location and crop type grown on a particular farm
*@internal 
*/
function frontlinesms(){
	$msg = $_REQUEST['msg'];
	$detail = explode(" ", $msg);
	$location = $detail[0];
	$crop = $detail[1];

}

/**
*@var array $url Contains 7 day weather forecast from openweathermap.org
*@api  
*/
$url = "http://api.openweathermap.org/data/2.5/forecast/daily?q={$location}&mode=xml&units=metric&cnt=7&APPID=YOUR_API_SECRET_ID_HERE";



/**
*@var string $xml Reads array into string
*@var integer $rand Radomly generated number appended to a filename 
*@var object $newXml Reads xml file into an object
*/
$xml =  file_get_contents($url);
$rand = rand();
file_put_contents("./xml_log/test{$rand}.xml", $xml);
$newXml = simplexml_load_file("./xml_log/test{$rand}.xml");



/**
*@var integer $results Total weekly surplus or deficit rainfall value
*@var double $maize Placeholder for rainfall requirements based on growth stage of crop
*@var double $yam Peak yam water need per day (in mm)
*@var double $ans Daily surplus or deficit rainfall value
*/
$results=0;
$maize = 0;
// $yam = 2.74;
$ans =0;

echo '<pre> ';

if ($growth_stage < 55){
	$maize = WATER_INTITIAL_STAGE;
	echo "Daily rainfall need for {$crop} at ".$growth_stage." days old= " .WATER_INTITIAL_STAGE."mm <br /><br />";
}
else if ($growth_stage > 55 && $growth_stage < 96){
	$maize = WATER_MID_STAGE;
	echo "Daily rainfall need for {$crop} at ".$growth_stage." days old= " .WATER_MID_STAGE."mm <br /><br />";
}
else{
	$maize = WATER_LATE_STAGE;
	echo "Daily rainfall need for {$crop} at ".$growth_stage." days old= " .WATER_LATE_STAGE."mm <br /><br />";
}


echo "7 day rain forecast and irrigation need for ".$location."<br />";

// if ($crop =="maize"){
// echo "Daily water need for {$crop} at ".$growth_stage." days= " .$maize."<br /><br />";
// }
/*else if($crop == "yam"){
	echo "Average day rainfall for {$crop} " .$yam."<br /><br />";
}*/

foreach($newXml->children() as $child) {
	$time = strtotime($child->time['day']);
	
	if( $time> date("m.d.y")){
        foreach($child->children() as $keys) {
			
			$newformat = date('D',$time);
			echo $newformat. ' ';

			if (empty($keys->precipitation['value'])){
				echo "null";
			}
			echo $keys->precipitation['value']. 'mm';
			if($crop == "maize"){
			$ans = $keys->precipitation['value'] - $maize;
			
			if($ans<0){
				$volume = 201.168*20.1168*abs($ans)*$land_area;
				$volume = round( $volume * 1000);
			}
			else
				$volume = 0;
			
			}
			else if ($crop == "yam"){
				$ans = $keys->precipitation['value'] - $yam;
			}

			echo '	';
			echo $ans." ". $volume.' Litres <br />';
			
			$time = strtotime("+1 day", $time);	
			if($ans < 0)		
			$results = $results + $ans;
			if ($results < 0){
				$totalVolume = 201.168*20.1168*abs($results)*$land_area;	//Calculate volume(cubic metre) of water needed for an acre
				$totalVolume = round( $totalVolume * 1000);	//Convert to litres
			}
        }
	

	}
}
echo "<br />";
echo 'The weeks rainfall deficit is ' . $totalVolume.' Litres per acre';

/**
*@var string $outAll Returns page content
*/
$outAll = ob_get_contents();


/**
*This method sends raindata and irrigation plan data via Smsgh api to client
*@return string 
*@api 
*/
function smsghsend(){
	$auth	=	new	 BasicAuth("YOUR_CLIENT_ID_HERE",	"CLIENT_SECRET_HERE");
	$apiHost = new ApiHost($auth);
	$messagingApi = new MessagingApi($apiHost);
	try{
		ob_start();									
	    $mesg	=	new	Message();	
	    $mesg->setContent($outAll);									
	    $mesg->setTo("DESTINATION_PHONE_NUMBER_HERE");									
	    $mesg->setFrom("SMS_SERVICE_NAME");									
	    $mesg->setRegisteredDelivery(true);									
	    $messageResponse	=	$messagingApi->sendMessage($mesg);	
	    ob_end_clean();	
	    if	($messageResponse instanceof MessageResponse)	
	    {
	    	echo	"</br></br>status...".$messageResponse->getStatus();									
	    }	 
	    elseif	($messageResponse instanceof HDpResponse)	
	    {													
		    echo	"\nServer	Response	Status	:	"	.	$messageResponse->getStatus();									
		}		
	}
	catch(ExcepWon	$ex)
	{									
		echo	$ex->getTraceAsString();					
	}

}


