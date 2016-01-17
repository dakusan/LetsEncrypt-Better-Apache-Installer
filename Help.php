<?
//Program description is as follows
//Make sure to check configuration variables in Config.php
//All php files are set to tab sizes of 8 spaces and assumes utf-8 for all strings
//**IMPORTANT SECURITY NOTE** Make sure to read the information about the “AllowConfigOverride” configuration variable in Config.php
$ProgramDescription=<<<EndText
* Installs SSL/HTTPS certificates via letsencrypt for all domains
* It takes a single VirtualHost domain and will install certificates for all VirtualHosts on the same IP (or if requested, just the given VirtualHost)
* While this script was originally designed for cPanel, it should work with any apache configuration, given the correct parameters
    * The cPanel specific defaults are:
        * Only looks in a single apache config file, which includes all VirtualHost configurations
        * Using a cPanel script called “whmapi1” to install the certificates
* UTF-8 is assumed for all strings (this is generally the default anyways)

Basic Example:
On a cPanel system, this will add SSL certificates for all domains on the same IP as example.com, with each DocRoot/VHost having a different certificate:
   php Main.php -dexample.com -eyour@email.com
Remote Example:
   php Main.php -dexample.com -eyour@email.com \
     -a 'ssh2.sftp://USER:PASSWORD@example.com//etc/httpd/conf/httpd.conf' \
     -c '/^(.*)$/ssh2.sftp:\/\/USER:PASSWORD@example.com\/$1/D' \
     -s 'ssh USER:PASSWORD@example.com "whmapi1 --output=json installssl domain=%s crt=%s key=%s cab=%s"' \
     -l 'ssh USER:PASSWORD@example.com "letsencrypt ?Params?"'

Requirements:
1) letsencrypt must already be installed. If it is not part of the path, make sure to pass the “LECmd” parameter
2) If using cPanel scripts, cPanel must already be installed on the system
3) By default, domain VirtualHosts can only be located in a single apache configuration file. This is easily fixable with an example below for the “ApacheConfPath” parameter

This works by:
1) Finds the IP of a given domain in the apache conf file(s)
2) Finds all VirtualHosts bound to that IP in the apache conf(s) and extracts all of their ServerName and ServerAlias domains
3) Runs all these found domains through letsencrypt to create a master certificate for the IP
4) Installs the certificate for each VirtualHost on the IP

IMPORTANT PARAMETER WARNING:
* Any parameter marked as “UnsafeVar” will only be usable if the “AllowConfigOverride” PHP configuration variable is set as true/On. It is currently set as “?AllowConfigOverride?”
    If the user is not completely trusted, this needs to be set to false; as otherwise, they can read any file or run any system command as the current user

Other parameter info:
* Any parameter marked as “File” uses file_get_contents(), so it can contain PHP fopen wrappers available on your system like SSH (https://secure.php.net/manual/en/wrappers.php)
    Example (remote file via SSH): ssh2.sftp://user:pass@example.com:22/etc/httpd.conf
    Example (multiple remote apache config files via SSH): ssh2.exec://user:pass@example.com/find /etc/httpd/conf* -type f -print0 | xargs -0 cat
    	Warning: ssh2.exec is currently broken on the php.ssh2 library (v<=0.12), but I have submitted a patch for it
* Any parameter marked as “Exec” will be run with exactly what is given through the command line via php.exec()
    Paths and parameters that you give are NOT ESCAPED, so you must do so yourself
    This generally just entails putting quotes around any path or parameter that includes a space
    Example: ls -l 'my file.conf'
* For “BOOL” parameters, only the first case-insensitive character is looked at, and only the following characters evaluate to true: 1, y, t

Parameters:
EndText;

//Get the last PHP error for user output
//This is required b/c PHP ~<5.3 does not allow direct array access from a function return
function GetLastError()
{
	$E=error_get_last();
	return $E['message'];
}

//Confirm PHP version
if(version_compare(PHP_VERSION, '5.3.0', '<'))
	return OL('Requires PHP>=5.2.4 for the \h PREG character set; php>=5.3.0 for anonymous functions');

//Output the help if requested or required
global $IsCLI;
if($IsCLI) //Process command line arguments to check for “help” request
{
	global $argc;
	require_once(__DIR__.'/CustomGetOpt.php');
}
if(
	($IsCLI && ($argc<2 || count(cgetopt('h', Array('help'))))) || //If CLI: “h”, “help”, or no arguments
	(!$IsCLI && (isset($_REQUEST['Help']) || !count(array_merge($_GET, $_POST)))) //If HTML “Help” parameter is passed, or there are no parameters
) {
	//Output the program description
	global $ConfigVars;
	OL(str_ireplace('?AllowConfigOverride?', $ConfigVars['AllowConfigOverride'] ? 'On' : 'Off', $ProgramDescription), 'Normal', false);

	//Output the parameters
	require_once(__DIR__.'/Parameters.php');
	foreach(GetParametersInfo($ConfigVars) as $PD)
		OL(
			'* '.($IsCLI ? "-$PD[0] --$PD[1]" : $PD[2])."$PD[3]:". //The name and extra info on one line
			implode("\n    ", array_merge(Array(''), !is_array($PD[4]) ? Array($PD[4]) : $PD[4])), //Add each parameter description part on a new line starting with 4 spaces
			'Normal', //ColoringType
			false //Do not output timestamp
		);

	//Nothing left to do after the help prompt
	return 1;
}

return 0;
?>