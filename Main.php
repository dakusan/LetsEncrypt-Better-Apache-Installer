<? /*
For more information, See Help.php
****IMPORTANT SECURITY NOTE**** Make sure to read the information about the “AllowConfigOverride” configuration variable in Config.php
*/

//Set up for CLI
global $IsCLI, $ConfigVars;
$IsCLI=preg_match('/^cli/i', php_sapi_name());
if($IsCLI && !function_exists('ExitStatusCodeFromVal')) //If not running through CLI, reroute file through it
	return require_once(__DIR__.'/CLI.php');

//Initalize external files
require_once(__DIR__.'/Output.php'); //Set up output for CLI/HTML
require_once(__DIR__.'/Config.php'); //Get configuration
ExitStatusCodeFromVal(require_once(__DIR__.'/Help.php')); //Output help if needed
require_once(__DIR__.'/Parameters.php'); //Get the parameters

//Process parameters
$Params=GetParams($ConfigVars, $IsCLI);
ProcessOutputParameters($ConfigVars, $Params, $IsCLI); //Process output parameters (TimestampType, ColorType)

//Confirm the domain and email to use
if(!isset($Params['Domain']))
	return OL('Domain not given. '.($IsCLI ? 'Add --help to see the help screen' : 'Add &help to see the help screen'));
if(!isset($Params['Email']))
{
	//LEEmailHelp (help information for the user’s email) is directly from the letsencrypt cli.py source
	$LEEmailHelp='Not using an email is strongly discouraged, because in the event of key loss or account compromise you will irrevocably lose access to your account. You will also be unable to receive notice about impending expiration or revocation of your certificates';
	return OL('Email is required. '.$LEEmailHelp);
}

//Read the apache conf
require_once(__DIR__.'/ReadApacheConf.php');
$VirtualHostInfos=ReadApacheConf($ConfigVars, $Params);
if(!is_array($VirtualHostInfos)) //If there was an error, exit here
	return $VirtualHostInfos;

//Create the certs
require_once(__DIR__.'/CreateCerts.php');
$FinalCertsInfo=CreateCerts($ConfigVars, $Params, $VirtualHostInfos);
if(!is_array($FinalCertsInfo)) //If there was an error, exit here
	return $FinalCertsInfo;

//Install the certs
require_once(__DIR__.'/InstallCerts.php');
$Ret=InstallCerts($ConfigVars, $Params, $VirtualHostInfos, $FinalCertsInfo['LEResponses'], $FinalCertsInfo['LEDocRootParams']);
if($Ret) //If there was an error, exit here
	return $Ret;

//Script has completed
OL('Script has finished successfully', 'Complete');
return 0;
?>