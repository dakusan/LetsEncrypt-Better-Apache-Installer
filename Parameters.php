<?
//Set up parameters
function GetParametersInfo($Config)
{
	//Get the configuration to show the defaults in the help text
	extract($Config);

	//Parameter descriptions: Array(Array([string] CLIShortName, [string] CLILongName, [string] HTMLName, [string] ExtraInfo, [string OR array[string,...]] Description, [Optional bool]NoParameter=false ), ...)
	//The Description can be an array to make it have multiple lines
	return Array(
Array('d', 'domain'	    , 'Domain'	     , ' (Required)'	   ,		'The domain to process that is the ServerName on the VirtualHost (not a ServerAlias)'),
Array('e', 'email'	    , 'Email'        , ' (Required)'	   ,		'Your email'),
Array('i', 'ignore'	    , 'Ignore'       , null		   , Array(	'A space delineated list of domains [single parameter] to NOT send to letsencrypt',
										'Example: "www.example.com skip.example.com"')),
Array('h', 'help'	    , 'Help'	     , null		   ,		'This help screen', true),
Array('a', 'apache-conf'    , 'ApacheConf'   , ' (UnsafeVar, File)', Array(	'Override the location of the apache config file',
										"Default: $ApacheConfPath")),
Array('l', 'le-cmd'	    , 'LECmd'	     , ' (UnsafeVar, Exec)', Array(	'The command to run to execute letsencrypt',
										'?Params? in the string is replaced with all the parameters, including the below LEParams parameter. It is required, and must not be enclosed in quotes',
										"Default: $LetsEncryptCommand",
										'Example: ssh USER:PASSWORD@example.com "/home/my user/letsencrypt" ?Params?')),
Array('p', 'le-params'      , 'LEParams'     , ' (UnsafeVar, Exec)', Array(	'The parameters to pass to the letsencrypt command (LECmd)',
										'Default: '.$LetsEncryptParams)),
Array('s', 'ssl-install-cmd', 'SSLInstallCmd', ' (UnsafeVar, Exec)', Array(	'The command to run to install HTTPS/SSL certificates',
										'It is a PHP sprintf format string which receives 4 variables, which are, in order:',
										'1) The Domain',
										'2) The Certificate Data',
										'3) The Private Key Data',
										'4) The Certificate Authority Chain (CA) Data',
										'When passed to sprintf, all of these variables are already escaped with quotes',
										'Default: '.$SSLInstallCommand)),
Array('c', 'cert-path-ovr'  , 'CertPathOvr'  , ' (UnsafeVar, File)', Array(	'A PHP PREG-format regular expression that the certificate path returned from letsencrypt will be ran through',
										'Format: /searching_string/replace_string/modifiers',
										'The PCRE delimiters must be ord()<=127',
										'Default is'.($CertPathOverride=='' ? ' blank (to ignore this parameter)' : ': '.$CertPathOverride),
										'Example: \'/^(.*)$/ssh2.sftp:\/\/USER:PASSWORD@example.com:22$1/D\'')),
Array('t', 'timestamp-type' , 'TimestampType', null		   , Array(	'What current time information to show for each log line',
										'Must be a single parameter with flags separated by a space or comma',
										'Can be a combination of the following flags:',
										'1) DailyDate: Show the date before an info log line when the day has rolled over',
										'2) Date: Show the date on every info log line',
										'3) Time: Show the time on every info log line',
										'The timestamp is never given when the help screen is invoked',
										'Default: '.$TimestampType)),
Array('o', 'coloring-type'  , 'ColoringType' , null		   , Array(	'Whether to color output using: xterm, html, none',
										'If set to “UseInterfaceType”, it is set to either html or xterm, depending on the interface you are using',
										'If an invalid value is given, “none” is assumed',
										'Default: '.$ColoringType)),
Array('b', 'distribution'   , 'Distribution' , null		   , Array(	'The combination of domains for created certificates. The values can be:',
										'1) GivenDomainOnly: Only create a certificate for the domains whose vhost-document-root-path matches that of the given domain',
										'For all virtual host domains, including aliases, that are on the IP of the given domain:',
										'2) AllInOne: Include all domains in a single certificate via SAN (Subject Alternative Names)',
										'3) SeparateVHosts: Create a separate certificate for each vhost-document-root-path (Also uses SAN)',
										'Default: '.$DistributionType)),
Array('u', 'url-encode-prms', 'URLEncodePrms', ' (BOOL)'	   , Array(	'If true, the parameters passed to “SSLInstallCmd” need to be URL encoded',
										'Default: '.$URLEncodeParams)),
Array('f', 'install-anyways','InstallAnyways', ' (BOOL)'	   , Array(	'If true, even if an error occured while creating one of the SSL certificates, the remaining certificates are installed',
										'Only valid for Distrubtion=SeparateVHosts',
										'Default: '.$InstallAnyways)),
	);
}

//Combine the static config and user-passed parameters for easier lookup
function GetParams(&$Config, $IsCLI)
{
	//Get CLI arguments
	$ARGs=Array();
	$ParameterDescriptions=GetParametersInfo($Config);
	if($IsCLI)
	{
		$LongOptions=$ShortOptions=Array();
		foreach($ParameterDescriptions as $PD)
		{
			$OptionalColon=(isset($PD[5]) && $PD[5] ? '' : ':'); //Only add colons for required parameters
			$ShortOptions[]=$PD[0].$OptionalColon;
			$LongOptions[]=$PD[1].$OptionalColon;
		}
		$ARGs=cgetopt(implode('', $ShortOptions), $LongOptions);
	}

	//Combine arguments
	$Params=Array(); //Final parameters, all named by the HTMLName
	foreach($ParameterDescriptions as $PD)
		if($IsCLI && (isset($ARGs[$PD[0]]) || isset($ARGs[$PD[1]])))
			$Params[$PD[2]]=$ARGs[$PD[isset($ARGs[$PD[0]]) ? 0 : 1]];
		else if(!$IsCLI && isset($_REQUEST[$PD[2]]))
			$Params[$PD[2]]=$_REQUEST[$PD[2]];

	return $Params;
}
?>