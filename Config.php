<?
//Configuration variables
//For more information on these variables, See Parameters.php

function Init() { global $ConfigVars; $ConfigVars=Array(

//*** START OF CONFIGURATION ***

//Paths’ manipulation
'ApacheConfPath'     =>'/etc/httpd/conf/httpd.conf',
'LetsEncryptCommand' =>'letsencrypt ?Params?',
'LetsEncryptParams'  =>'certonly --webroot -t --renew-by-default --agree-tos --duplicate', //--expand is broken for me atm
'SSLInstallCommand'  =>'whmapi1 --output=json installssl domain=%s crt=%s key=%s cab=%s',
'CertPathOverride'   =>'',

//Allow overwriting the above path manipulation variables
//If the user is not completely trusted, this needs to be set to false, as otherwise, they can read any file or run any system command as the current user
//Will be directly evalutated to a boolean below
'AllowConfigOverride'=>true,

//Other variables
'TimestampType'      =>'DailyDate,Time',
'ColoringType'       =>'UseInterfaceType',
'DistributionType'   =>'SeparateVHosts',
'URLEncodeParams'    =>'true',
'InstallAnyways'     =>'true',

//*** END OF CONFIGURATION ***

	);

	//Variable sanitization
	$ConfigVars['AllowConfigOverride']=(bool)$ConfigVars['AllowConfigOverride'];
}
Init();
?>