<?php
//Freshdesk details
$f_url = "http://voyajoyinc.freshdesk.com//helpdesk/tickets.xml?filter_name=all_tickets&page=";
$f_user = 'ivan@voyajoy.com';
$f_pass = 'M1sos0up312';
//Zendesk details
$z_url = "https://voyajoyone.zendesk.com/api/v2/imports/tickets.json";
$z_user = 'ivan@voyajoy.com';
$z_pass = 'M1sos0up312';
//place holders
$sXML = "";
$results = "";

//function to retrieve Freshdesk tickets
function getFreshdeskTickets($path, $user, $pass) {
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $path);
curl_setopt($ch, CURLOPT_USERPWD, "$user:$pass");
curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
curl_setopt($ch, CURLOPT_FAILONERROR, 1);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_TIMEOUT, 100);
$retValue = curl_exec($ch);
curl_close($ch);
return $retValue;
}

//function to post single ticket to Zendesk api
function postTicketToZendesk($path, $user, $pass, $json) {

$ch = curl_init($path);
curl_setopt($ch, CURLOPT_USERPWD, "$user:$pass");
curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FAILONERROR, 1);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Content-Length: ' . strlen($json)));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); //should probably actually point Curl at the ssl certificate, but this is quicker...

$curl_result = curl_exec($ch);
if($curl_result === false)
{
$curl_result = 'Curl error: ' . curl_error($ch);
}
// else
// {
// $curl_result = 'Operation completed without any errors';
// }
//$curl_result = curl_exec($ch);

curl_close($ch);
return $curl_result;
}

//using paging for the requests (otherwise the Freshdesk API seems to fall over), currently there are 15 pages of tickets (449 tickets), so 15 loops
$page = 1;
while ($page <= 15) {
$t_url = $f_url . $page;
$page++;
$output = getFreshdeskTickets($t_url, $f_user, $f_pass);
//do some cleaning up to make sure it parses properly
$output = str_replace(array("\n", "\r", "\t"), '', $output);
//- remove line breaks, tabs, etc.
$output = trim(str_replace('"', "'", $output));
// - replace double quotes with single and trim

//remove the opening and closing xml tags
$output = str_replace("<?xml version='1.0' encoding='UTF-8'?><helpdesk-tickets type='array'>", "", $output);

$output = str_replace("</helpdesk-tickets>", "", $output);
$sXML = $sXML . $output;
//append this output.
}

//put the opening and closing xml tags back on
$sXML = '<?xml version="1.0" encoding="UTF-8"?>
<helpdesk-tickets type="array">' . $sXML;
$sXML = $sXML . '</helpdesk-tickets>';

//echo $sXML;
//convert to simple xml
$simpleXml = simplexml_load_string($sXML);
//print($simpleXml);

// loop the freshdesk tickets, building zendesk json
// looks like ZenDesk ticket importer might only take one ticket at a
// time, so going to import them as I loop through the output.
$result = "";
$count = 0;
foreach ($simpleXml->{'helpdesk-ticket'} as $ticket) {

$json = "{";
//open json

//some fields need a little pre-processing

//priority
$priority = "low";
switch ($ticket -> priority ) {
case '1' :
$priority = "low";
break;
case '2' :
$priority = "normal";
break;
case '3' :
$priority = "high";
break;
case '4' :
$priority = "urgent";
break;
default :
$priority = "low";
break;
}

//status
$status = "open";
switch ($ticket -> status ) {
case '1' :
$status = "new";
break;
case '2' :
$status = "open";
break;
case '3' :
$status = "pending";
break;
case '4' :
$status = "solved";
break;
case '5' :
$status = "closed";
break;
default :
$status = "open";
break;
}

$json = $json . '"ticket": {' ;
//start ticket

$json = $json . '"external_id": "' . $ticket -> {'display-id'} . '"' ;
$json = $json . ', "subject": "' . $ticket -> subject . '"' ;
$json = $json . ', "created_at": "' . $ticket -> {'created-at'} . '"' ;
$json = $json . ', "updated_at": "' . $ticket -> {'updated-at'} . '"' ;

if ($status == "solved" OR $status == "closed") {
$json = $json . ', "solved_at": "' . $ticket -> {'updated-at'} . '"' ;
}

$json = $json . ', "priority": "' . $priority . '"' ;
$json = $json . ', "status": "' . $status . '"' ;
$json = $json . ', "comments": [{ "author_id": 389931077, "value": "' . $ticket -> {'description'} . '"} ]' ;
//make all comments by Alan - too complicated to bother otherwise
$json = $json . ', "tags": ["' . $ticket -> {'ticket-type'} . '", "freshdesk_import"]' ;
//end ticket
$json = $json . '}';
//close json

$result = $result . postTicketToZendesk($z_url, $z_user, $z_pass, $json);

$count++;
//echo $count . "<br />\n";
}

echo "processed " . $count . " records: \n" . $result;
?>