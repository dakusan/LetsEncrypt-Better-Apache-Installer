<?
//Output a line to either HTML or plain text. Also add the timestamp to the beginning
//LineType can be “Normal”, “Success”, “Complete”, “Warning”, or “Error”
require_once(__DIR__.'/Config.php');
function OL($String, $LineType='Error', $ShowTimestamp=true)
{
	//Get output parameters
	global $IsCLI, $ConfigVars;
	static $LastDate='';
	extract(array_intersect_key($ConfigVars, Array('TimestampType'=>1, 'ColoringType'=>1)));

	//Output the timestamp
	if($ShowTimestamp)
	{
		//Only show the date when it has changed
		$CurDate=date('Y-m-d');
		if(isset($TimestampType['DailyDate']) && $LastDate!=$CurDate)
			print 'Date '.($LastDate=$CurDate)."\n";

		//Output the date and/or time on the current log line
		if(isset($TimestampType['Date']))
			print "$CurDate ";
		if(isset($TimestampType['Time']))
			print date('H:i:s ');
	}

	//Define the colors and ColorTypes
	$ColorTypes=Array(//      HTMLColor       XTermColor
		'Success' =>Array('LimeGreen',			'[1;32m'),
		'Warning' =>Array('#FF8080',			'[31m'  ),
		'Error'   =>Array('red',			'[1;31m'),
		'Complete'=>Array('darkgreen;font-weight:bold;','[32m'  )
		//'Normal'=>  //Do nothing
	);
	$ColorIndexes=Array('html'=>0, 'xterm'=>1);

	//If requested, add coloring on non-normal line type
	$IsColoring=(isset($ColorIndexes[$ColoringType]) && isset($ColorTypes[$LineType])); //If ColoringType has not been processed yet, it should always be “Normal” anyways
	if($IsColoring)
	{
		//Define color formatting information
		$ColorIndex=$ColorIndexes[$ColoringType];
		$ClearColor=Array('</span>',      '[0m' );
		$XTermEscape="\033";
		$HTMLEscape='<span style="color:%s">';

		//Output the color modifier
		$MyColor=$ColorTypes[$LineType][$ColorIndex];
		print ($ColoringType=='html' ? sprintf($HTMLEscape, $MyColor) : $XTermEscape.$MyColor);
	}

	//Output the string followed by a line break
	print ($IsCLI ? $String : htmlspecialchars($String, ENT_QUOTES, 'UTF-8'))."\n";

	//End coloring
	if($IsColoring)
		print ($ColoringType=='html' ? $ClearColor[$ColorIndex] : $XTermEscape.$ClearColor[$ColorIndex]);

	return 1; //Used for [chain] returning errors
}

//If non CLI, output standard HTML header and footer
global $IsCLI;
function ShutdownHTML() { print '</div></body></html>'; }
if(!$IsCLI)
{
	header('Content-Type: text/html; charset=utf-8');
	print '<!DOCTYPE html><html><head><title>Let’s Encrypt for cPanel</title><meta charset="UTF-8"></head><body><div style="white-space:pre-wrap;font-family:monospace;">';
	register_shutdown_function('ShutdownHTML');
}

//Process output parameters (TimestampType, ColorType)
function ProcessOutputParameters(&$Config, $Params, $IsCLI)
{
	//Process the timestamp type
	$TimestampType=&$Config['TimestampType'];
	if(isset($Params['TimestampType']))
		$TimestampType=$Params['TimestampType'];
	$TimestampTypeInput=preg_split('/[\s,]+/us', mb_strtolower(trim($TimestampType))); //Make types lowercase and split around whitespace
	$TimestampType=Array();
	$TSTypes=Array('DailyDate', 'Date', 'Time');
	foreach($TimestampTypeInput as $GivenType) //Check each type given by the user for validity
		foreach($TSTypes as $TSType) //Check each real type against the type given by the user
			if(strcasecmp($GivenType, $TSType)==0) //Allow case insensitivity
				$TimestampType[$TSType]=1; //Store valid type as a key

	//Process the color type
	$ColoringType=&$Config['ColoringType'];
	if(isset($Params['ColoringType']))
		$ColoringType=$Params['ColoringType'];
	if(strcasecmp($ColoringType, 'UseInterfaceType')==0)
		$ColoringType=($IsCLI ? 'xterm' : 'html');
	$ColoringType=(preg_match('/^(?:html|xterm)$/iuD', $ColoringType) ? mb_strtolower($ColoringType) : 'none'); //If invalid, set as “none”
}

//If non-zero, send the given status code as the exit code
function ExitStatusCodeFromVal($StatusCode)
{
	if($StatusCode)
		exit($StatusCode);
}
?>