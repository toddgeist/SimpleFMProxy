<?php
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
/**
 *  SimpleFMProxy
 *
 *  A simple php proxy that returns data from FileMaker queries in several useful formats
 *  
 *  use the normal FileMaker query syntax
 *  you can add an additional query parameter "format"
 *  format=let returns just the meta data formated as a Let function that can be evaluated in FM and turned into $vars;
 *         Using this from FM GO results in the text being wrapped in an HTML page when it comes back
 *         so the function does this by default
 *         The Let fucntion will be in the <body> tag.
 *  format=xml returns the query as a normailzed XML suitable for consumption by AJAX applications
 *  format=txt returns pretty printed xml as text.
 *  format=json returns normalized json suitable for consunption by ajax applications
 *
 *  Authentication is done via Basic HTTP through the url
 *  like so
 *  http://user:password@server/SimpleFMProxy.php
 *
 *  Edit the "Config" section below to macth your server
 *
 */
 
 /*
 
LICENSE
 
(The MIT License)

Copyright (c) 2011 Todd Geist <todd@geistinterctive.com>

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the 'Software'), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED 'AS IS', WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/



//=========[ CONFIG ]=======================================



// the address of the server, it is almost certainly localhost or 127.0.0.1

$serverAddress = 'localhost';




//==========================================================

$auth = auth();

function auth()
{
    if (!isset($_SERVER['PHP_AUTH_USER'])) {
        header('WWW-Authenticate: Basic realm="My Realm"');
        header('HTTP/1.0 401 Unauthorized');
        return "error";
    } else {
        return array( $_SERVER['PHP_AUTH_USER'] , $_SERVER['PHP_AUTH_PW'] );
    }
}

if($auth != 'error'){
	$userName = $auth[0];
	$password = $auth[1];
}


	$format = "xml";
   	if( isset($_GET['format'])  ){
   	   $format = $_GET['format'];
   	}
   	
   	
   	
//   	if( isset($_GET['userName'])  ){
//   	   $userName = $_GET['userName'];
//   	}
//   	if( isset($_GET['password'])  ){
//   	   $password = $_GET['password'];
//   	}
   	
	
   	
   	

 // rebuild the query string without any extra parameters
   $keys = (array_keys($_GET));
  
  
   $fmQueryString = '';
   foreach($keys as $key){
   	  if ($key[0]=='-'){
   	  	$value = $_GET[$key];
   	  	if($value != ''){
   	  	   $value = "=". $value;
   	  	}
   	  	$key = str_ireplace('_', '.', $key);
   		$fmQueryString .= $key .  $value . "&";
   	  }
   }
   $fmQueryString = substr($fmQueryString, 0 , strlen($fmQueryString)-1);
   
   
   $url = (string)"http://$userName:$password@$serverAddress/fmi/xml/fmresultset.xml?$fmQueryString";

   $xml = simplexml_load_file($url);
//   print_r($xml);
//   die();
  
   
	if($xml == null){
		$error = -2;
		$newXML = new SimpleXMLElement("<result></result>");
		$errorXML = $newXML->addChild('error');
		$errorXML->addAttribute('code', $error);
		$newXML->addChild('errorMsg', errorToEnglish($error));
		$newXML->addChild('success', $error==0 );
		$newXML->addChild('count', (string)$xml->resultset['count'] );
		$newXML->addChild('fetchsize', (string)$xml->resultset['fetch-size']);
		$xml = $newXML; 
	}else{
	 	$error = (int)$xml->error['code'];
	}
   

   
   	$result['$error']= $error;
   	$result['$errortext'] = errorToEnglish($error);
   	$result['$count'] = (string)$xml->resultset['count'];
   	$result['$fetchsize'] = (string)$xml->resultset['fetch-size'];
   	

if($format == 'let'){
	$fmResult = '';
	$resultKeys = (array_keys($result));
	foreach($resultKeys as $resultKey){
		$fmResult .= "\n" . $resultKey . '="' . $result[$resultKey] . '";';
	}
	$fmResult = substr($fmResult, 0 , strlen($fmResult)-1);
	header('Content-type: text/html');
	die("<html><head></head><body>Let(\n[$fmResult\n];\n0\n)\n//END</body></html>");
	
	// not sure what else to put here???
}elseif($format == 'txt2'){
	$fmResult = '';
	$resultKeys = (array_keys($result));
	foreach($resultKeys as $resultKey){
		$fmResult .= "\n" . $resultKey . '="' . $result[$resultKey] . '";';
	}
	$fmResult = substr($fmResult, 0 , strlen($fmResult)-1);
	header('Content-type: text/txt');
	die("Let(\n[$fmResult\n];\n0\n)\n//END");

}elseif($format == 'txt'){
	$xml = rationalizeXML($xml);
	$dom = new DOMDocument('1.0');
	$dom->preserveWhiteSpace = false;
	$dom->formatOutput = true;
	$dom->loadXML($xml->asXML());
	$xml = $dom->saveXML();

	header('Content-type: text/txt');
	die($xml);
}elseif($format == 'xml'){
	$xml = rationalizeXML($xml);
	header('Content-type: application/xml');
	die($xml->asXML());
}elseif($format == 'json'){
	header('Content-type: application/json');
	die(convertToJson($xml));
}

   
function rationalizeXML($xml){

	$error = (int)$xml->error['code'];

	$newXML = new SimpleXMLElement("<result></result>");
	$newXML->addChild('error', $error);
	$newXML->addChild('errorMsg', errorToEnglish($error));
	$newXML->addChild('success', $error==0 );
	$newXML->addChild('count', (string)$xml->resultset['count'] );
	$newXML->addChild('fetchsize', (string)$xml->resultset['fetch-size']);
	$recordsXML=$newXML->addChild('records');
	foreach ($xml->resultset[0]->record as $this_record){
		$recordXML = $recordsXML->addChild('record');
		$recordXML->addChild('metaRECID', (string) $this_record['record-id']);
		$recordXML->addChild('metaMODID', (string) $this_record['mod-id']);
		foreach ($this_record->field as $field ){
			$fieldName = (string) $field['name'];
			$fieldName = str_ireplace(' ', '_', $fieldName);
			$data = htmlentities( (string) $field->data );
			$recordXML->addChild((string) $field['name'], $data);
		}
	}
	if (!empty($xml->resultset[0]->record[0]->relatedset)){
		$newXML->addChild('relatedRecords');
	}

	return $newXML;
}

function convertToJson($xml){
	$arr = rationalizeXML($xml);
	return json_encode($arr);
}







// returns english text for an error
// from Jerimiah Small simpleFM.php - http://www.jsmall.us/downloads/
function errorToEnglish($errornum="-1"){

	$error = array(
	-2=>"Couldn't connect to the FileMaker Server. Check you user name and password, and check you server configuration", 
	-1=>"Unknown error",
	0=>"No error", 
	1=>"User canceled action", 
	2=>"Memory error", 
	3=>"Command is unavailable (for example, wrong operating system, wrong mode, etc.)", 
	4=>"Command is unknown", 
	5=>"Command is invalid (for example, a Set Field script step does not have a calculation specified)", 
	6=>"File is read-only", 
	7=>"Running out of memory", 
	8=>"Empty result", 
	9=>"Insufficient privileges", 
	10=>"Requested data is missing", 
	11=>"Name is not valid", 
	12=>"Name already exists", 
	13=>"File or object is in use", 
	14=>"Out of range", 
	15=>"Can't divide by zero", 
	16=>"Operation failed, request retry (for example, a user query)", 
	17=>"Attempt to convert foreign character set to UTF-16 failed", 
	18=>"Client must provide account information to proceed", 
	19=>"String contains characters other than A-Z, a-z, 0-9 (ASCII)", 
	100=>"File is missing", 
	101=>"Record is missing", 
	102=>"Field is missing", 
	103=>"Relationship is missing", 
	104=>"Script is missing", 
	105=>"Layout is missing", 
	106=>"Table is missing", 
	107=>"Index is missing", 
	108=>"Value list is missing", 
	109=>"Privilege set is missing", 
	110=>"Related tables are missing", 
	111=>"Field repetition is invalid", 
	112=>"Window is missing", 
	113=>"Function is missing", 
	114=>"File reference is missing", 
	130=>"Files are damaged or missing and must be reinstalled", 
	131=>"Language pack files are missing (such as template files)", 
	200=>"Record access is denied", 
	201=>"Field cannot be modified", 
	202=>"Field access is denied", 
	203=>"No records in file to print, or password doesn't allow print access", 
	204=>"No access to field(s) in sort order", 
	205=>"User does not have access privileges to create new records; import will overwrite existing data", 
	206=>"User does not have password change privileges, or file is not modifiable", 
	207=>"User does not have sufficient privileges to change database schema, or file is not modifiable", 
	208=>"Password does not contain enough characters", 
	209=>"New password must be different from existing one", 
	210=>"User account is inactive", 
	211=>"Password has expired", 
	212=>"Invalid user account and/or password. Please try again", 
	213=>"User account and/or password does not exist", 
	214=>"Too many login attempts", 
	215=>"Administrator privileges cannot be duplicated", 
	216=>"Guest account cannot be duplicated", 
	217=>"User does not have sufficient privileges to modify administrator account", 
	300=>"File is locked or in use", 
	301=>"Record is in use by another user", 
	302=>"Table is in use by another user", 
	303=>"Database schema is in use by another user", 
	304=>"Layout is in use by another user", 
	306=>"Record modification ID does not match", 
	400=>"Find criteria are empty", 
	401=>"No records match the request", 
	402=>"Selected field is not a match field for a lookup", 
	403=>"Exceeding maximum record limit for trial version of FileMaker Pro", 
	404=>"Sort order is invalid", 
	405=>"Number of records specified exceeds number of records that can be omitted", 
	406=>"Replace/Reserialize criteria are invalid", 
	407=>"One or both match fields are missing (invalid relationship)", 
	408=>"Specified field has inappropriate data type for this operation", 
	409=>"Import order is invalid", 
	410=>"Export order is invalid", 
	412=>"Wrong version of FileMaker Pro used to recover file", 
	413=>"Specified field has inappropriate field type", 
	414=>"Layout cannot display the result", 
	500=>"Date value does not meet validation entry options", 
	501=>"Time value does not meet validation entry options", 
	502=>"Number value does not meet validation entry options", 
	503=>"Value in field is not within the range specified in validation entry options", 
	504=>"Value in field is not unique as required in validation entry options", 
	505=>"Value in field is not an existing value in the database file as required in validation entry options", 
	506=>"Value in field is not listed on the value list specified in validation entry option", 
	507=>"Value in field failed calculation test of validation entry option", 
	508=>"Invalid value entered in Find mode", 
	509=>"Field requires a valid value", 
	510=>"Related value is empty or unavailable", 
	511=>"Value in field exceeds maximum number of allowed characters", 
	600=>"Print error has occurred", 
	601=>"Combined header and footer exceed one page", 
	602=>"Body doesn't fit on a page for current column setup", 
	603=>"Print connection lost", 
	700=>"File is of the wrong file type for import", 
	706=>"EPSF file has no preview image", 
	707=>"Graphic translator cannot be found", 
	708=>"Can't import the file or need color monitor support to import file", 
	709=>"QuickTime movie import failed", 
	710=>"Unable to update QuickTime file reference because the database file is read-only", 
	711=>"Import translator cannot be found", 
	714=>"Password privileges do not allow the operation", 
	715=>"Specified Excel worksheet or named range is missing", 
	716=>"A SQL query using DELETE, INSERT, or UPDATE is not allowed for ODBC import", 
	717=>"There is not enough XML/XSL information to proceed with the import or export", 
	718=>"Error in parsing XML file (from Xerces)", 
	719=>"Error in transforming XML using XSL (from Xalan)", 
	720=>"Error when exporting; intended format does not support repeating fields", 
	721=>"Unknown error occurred in the parser or the transformer", 
	722=>"Cannot import data into a file that has no fields", 
	723=>"You do not have permission to add records to or modify records in the target table", 
	724=>"You do not have permission to add records to the target table", 
	725=>"You do not have permission to modify records in the target table", 
	726=>"There are more records in the import file than in the target table. Not all records were imported", 
	727=>"There are more records in the target table than in the import file. Not all records were updated", 
	729=>"Errors occurred during import. Records could not be imported", 
	730=>"Unsupported Excel version. (Convert file to Excel 7.0 (Excel 95), Excel 97, 2000, or XP format and try again)", 
	731=>"The file you are importing from contains no data", 
	732=>"This file cannot be inserted because it contains other files", 
	733=>"A table cannot be imported into itself", 
	734=>"This file type cannot be displayed as a picture", 
	735=>"This file type cannot be displayed as a picture. It will be inserted and displayed as a file", 
	800=>"Unable to create file on disk", 
	801=>"Unable to create temporary file on System disk", 
	802=>"Unable to open file", 
	803=>"File is single user or host cannot be found", 
	804=>"File cannot be opened as read-only in its current state", 
	805=>"File is damaged; use Recover command", 
	806=>"File cannot be opened with this version of FileMaker Pro", 
	807=>"File is not a FileMaker Pro file or is severely damaged", 
	808=>"Cannot open file because access privileges are damaged", 
	809=>"Disk/volume is full", 
	810=>"Disk/volume is locked", 
	811=>"Temporary file cannot be opened as FileMaker Pro file", 
	813=>"Record Synchronization error on network", 
	814=>"File(s) cannot be opened because maximum number is open", 
	815=>"Couldn't open lookup file", 
	816=>"Unable to convert file", 
	817=>"Unable to open file because it does not belong to this solution", 
	819=>"Cannot save a local copy of a remote file", 
	820=>"File is in the process of being closed", 
	821=>"Host forced a disconnect", 
	822=>"FMI files not found; reinstall missing files", 
	823=>"Cannot set file to single-user, guests are connected", 
	824=>"File is damaged or not a FileMaker file", 
	900=>"General spelling engine error", 
	901=>"Main spelling dictionary not installed", 
	902=>"Could not launch the Help system", 
	903=>"Command cannot be used in a shared file", 
	904=>"Command can only be used in a file hosted under FileMaker Server", 
	905=>"No active field selected; command can only be used if there is an active field", 
	920=>"Can't initialize the spelling engine", 
	921=>"User dictionary cannot be loaded for editing", 
	922=>"User dictionary cannot be found", 
	923=>"User dictionary is read-only", 
	951=>"An unexpected error occurred (*)", 
	954=>"Unsupported XML grammar (*)", 
	955=>"No database name (*)", 
	956=>"Maximum number of database sessions exceeded (*)", 
	957=>"Conflicting commands (*)", 
	958=>"Parameter missing (*)", 
	1200=>"Generic calculation error", 
	1201=>"Too few parameters in the function", 
	1202=>"Too many parameters in the function", 
	1203=>"Unexpected end of calculation", 
	1204=>"Number, text constant, field name or \"(\" expected", 
	1205=>"Comment is not terminated with \"*\/\"", 
	1206=>"Text constant must end with a quotation mark", 
	1207=>"Unbalanced parenthesis", 
	1208=>"Operator missing, function not found or \"(\" not expected", 
	1209=>"Name (such as field name or layout name) is missing", 
	1210=>"Plug-in function has already been registered", 
	1211=>"List usage is not allowed in this function", 
	1212=>"An operator (for example, +, -, *) is expected here", 
	1213=>"This variable has already been defined in the Let function", 
	1214=>"AVERAGE, COUNT, EXTEND, GETREPETITION, MAX, MIN, NPV, STDEV, SUM and GETSUMMARY: expression found where a field alone is needed", 
	1215=>"This parameter is an invalid Get function parameter", 
	1216=>"Only Summary fields allowed as first argument in GETSUMMARY", 
	1217=>"Break field is invalid", 
	1218=>"Cannot evaluate the number", 
	1219=>"A field cannot be used in its own formula", 
	1220=>"Field type must be normal or calculated", 
	1221=>"Data type must be number, date, time, or timestamp", 
	1222=>"Calculation cannot be stored", 
	1223=>"The function referred to does not exist", 
	1400=>"ODBC driver initialization failed; make sure the ODBC drivers are properly installed", 
	1401=>"Failed to allocate environment (ODBC)", 
	1402=>"Failed to free environment (ODBC)", 
	1403=>"Failed to disconnect (ODBC)", 
	1404=>"Failed to allocate connection (ODBC)", 
	1405=>"Failed to free connection (ODBC)", 
	1406=>"Failed check for SQL API (ODBC)", 
	1407=>"Failed to allocate statement (ODBC)", 
	1408=>"Extended error (ODBC)"
	);
	
	return $error[$errornum];

}


?>