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
*/
$location = $_REQUEST['loc'];
$crop = $_REQUEST['crop'];


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
*@var double $maize Peak maize water need per day (in mm)
*@var double $yam Peak yam water need per day (in mm)
*@var double $ans Daily surplus or deficit rainfall value
*/
$results=0;
$array[] = '';
$maize = 7.62;
$yam = 2.74;
$ans ='';

echo '<pre> ';
echo "7 day rain forecast for ".$location."<br />";

if ($crop =="maize"){
echo "Average day rainfall for {$crop} " .$maize."<br /><br />";
}
else if($crop == "yam"){
	echo "Average day rainfall for {$crop} " .$yam."<br /><br />";
}

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
			}
			else if ($crop == "yam"){
				$ans = $keys->precipitation['value'] - $yam;
			}
			echo '	';
			echo $ans. '<br />';

			// array_push ($array, $newformat, $keys->precipitation['value']);			
			$time = strtotime("+1 day", $time);			
			$results = $results + $ans;
        }
	

	}
}
echo "<br />";
echo 'weekly surplus or deficit ' . $results.'mm';

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


