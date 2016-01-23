<?  /*
Takes the information returned from letsencrypt and installs the certificates
Returns: [int] StatusCode (Error=1, Success=0)
*/
function InstallCerts(
	&$Config, //The current config
	$Params, //The list of config+user combined parameters
	$VirtualHostInfos, //See ReadApacheConf.php/ReadApacheConf()
	$LEResponses, //See CreateCerts.php/CreateCerts()
	$LEDocRootParams //List of document roots (by key) that were processed
) {
	//Transform the certificate path if parameter is given
	$CertPathOverride=&$Config['CertPathOverride'];
	if($Config['AllowConfigOverride'] && isset($Params['CertPathOvr']))
		$CertPathOverride=$Params['CertPathOvr'];
	if(!isset($CertPathOverride) || !strlen(trim($CertPathOverride)))
		$CertPathOverride=null;
	else
	{
		//Get the escape character
		$CPO=trim($CertPathOverride);
		$SplitChar=$CPO[0];
		$EscapeChar='\\';
		$SplitCharCode=ord($SplitChar);
		$EscapeCharCode=ord($EscapeChar);
		if($SplitCharCode>127 || $SplitCharCode==$EscapeCharCode)
			return OL('The CertPathOvr delimiter must be ord()<=127 and not “'.$EscapeChar.'”');

		//Get locations where the delimiter occurs
		$SplitLocations=Array();
		for($i=1;$i<strlen($CPO);$i++)
			if(($O=ord($CPO[$i]))==$SplitCharCode) //If the delimiter char, add to the split location list
				$SplitLocations[]=$i;
			else if($O==$EscapeCharCode) //If the escape char, skip the next character
				$i++;

		//Error for invalid PREG expression
		$CertPathErr=function($SpecificError) use($SplitChar, $EscapeChar)
		{
			return <<<END
CertPathOvr:
   Your given delimiter: “${SplitChar}”
   Must Be:
      A valid PREG regular expression containing your delimiter “${SplitChar}” exactly 3 times, not including when it is escaped with “${EscapeChar}”.
      The delimiter currently CANNOT be the escape character, “${EscapeChar}”
   Example: “/abc/def/iusD” will replace “AbC” with “def”.
   Error Reason: $SpecificError
END
			;
		};

		//Extract into parts
		if(count($SplitLocations)!=2)
			return OL($CertPathErr('Exactly 3 of your delimiter was not found'));
		$SplitLocations=array_merge(Array(0), $SplitLocations, Array(strlen($CPO)));
		$CertPathOverride=Array();
		for($i=0;$i<count($SplitLocations)-1;$i++)
			$CertPathOverride[]=substr($CPO, $SplitLocations[$i]+1, $SplitLocations[$i+1]-$SplitLocations[$i]-1);

		//Confirm regular expression
		if(!preg_match('/^\w*$/D', $CertPathOverride[2]))
			return OL($CertPathErr('PREG modifier characters can only be alphabetic (a-zA-Z)'));
		$CertPathOverride=Array("$SplitChar$CertPathOverride[0]$SplitChar$CertPathOverride[2]", stripcslashes($CertPathOverride[1]));
		if(FALSE===@preg_match($CertPathOverride[0], ''))
			return OL($CertPathErr('PREG Error: '.GetLastError()));
	}

	//Get the locations of the certificates
	require_once(__DIR__.'/cfile_get_contents.php');
	$CertDataPerDocRoot=Array(); //Array(DOC_ROOT=>Array([string]cert=>CERT_CRT_TEXT, [string] privkey=>CERT_PRIVATE_KEY_TEXT, [string] chain=>CERT_CA_CHAIN), ...)
	$Only1Cert=(strcasecmp($Config['DistributionType'], 'AllInOne')==0 || count($LEResponses)==1); //If only 1 certificate is being installed
	foreach($LEResponses as $DocRoot => $LEReturn)
		GetCertLoc($DocRoot, $LEReturn, $CertDataPerDocRoot, $CertPathOverride, $Only1Cert);

	//Determine if parameters need to be URL encoded
	$URLEncodeParams=&$Config['URLEncodeParams'];
	if(isset($Params['URLEncodePrms']))
		$URLEncodeParams=$Params['URLEncodePrms'];
	$URLEncodeParams=preg_match('/^\s*[ty1]/iD', $URLEncodeParams);
	$EncodeParam=function($P) use ($URLEncodeParams)
	{
		if($URLEncodeParams)
			$P=urlencode($P);
		return escapeshellarg($P);
	};

	//Execute the certificate install for each virtual host
	$SSLInstallCommand=&$Config['SSLInstallCommand'];
	if($Config['AllowConfigOverride'] && isset($Params['SSLInstallCmd']))
		$SSLInstallCommand=$Params['SSLInstallCmd'];
	if(!strlen(trim($SSLInstallCommand)))
		return OL('SSLInstallCommand is empty');
	foreach($VirtualHostInfos as $VH)
	{
		//If document root was not used, skip without warning
		if(!isset($LEDocRootParams[$VH['DocumentRoot']]))
		{
			OL("Warning: Skipping install of certificate for host $VH[ServerName] ; The docroot’s certificate was not created due to user parameters", 'Warning');
			continue;
		}

		//If certificate was not created, skip
		$CertData=&$CertDataPerDocRoot[$VH['DocumentRoot']];
		if(!isset($CertData))
		{
			OL("Skipping install of certificate for host $VH[ServerName] ; The certificate was not found for its vhost-document-root-path of $VH[DocumentRoot] ; See above errors for futher details");
			continue;
		}

		//Build the command to install the certificate
		OL('Installing certificate for host: '.$VH['ServerName'], 'Success');
		$ExecString=@sprintf(
			$SSLInstallCommand,
			$EncodeParam($VH['ServerName']),
			$EncodeParam($CertData['cert']),
			$EncodeParam($CertData['privkey']),
			$EncodeParam($CertData['chain'])
		);
		if(!$ExecString)
			return OL('Error while trying to compile SSLInstallCommand: '.($ExecString===FALSE ? GetLastError() : 'Command is empty'));

		//Install the certificate
		exec($ExecString, $Output);

		//If not a JSON return with expected cPanel output, just output the message
		$Output=implode("\n", $Output);
		if(NULL===($JSONResult=GetFromNestedArray($JSONData=json_decode($Output, true), 'metadata', 'result')))
			OL("Result:\n$Output", 'Success');
		else //If JSON message is available, output it
		{
			$ReasonMessage=GetFromNestedArray($JSONData, 'metadata', 'reason');
			OL(is_string($ReasonMessage) ? $ReasonMessage : "Result:\n$Output", $JSONResult==1 ? 'Success' : 'Error');
		}
	}
}

//From the text returned from letsencrypt, get the locations of the certificates for a DOC_ROOT
//Returns: [int] StatusCode (Error=1, Success=0)
function GetCertLoc(
	$DocRoot,		//The document root for the certificate
	$LEReturn,		//The text returned from letsencrypt
	&$CertDataPerDocRoot,	//A certificates Certificate, PrivateKey, and CAChain are added to this on success
	$CertPathOverride,	//A PHP PREG-format regular expression that the certificate path returned from letsencrypt will be ran through
	$Only1Cert		//If only 1 certificate is being installed
) {
	//Get the path to the “fullchain” file, which shares the path with the other certificates
	$FullChainPath=null;
	$ForDocRootStr=($Only1Cert ? '' : ' for '.$DocRoot); //Add the document root to error messages
	$LEReturn=implode("\n", $LEReturn);
	if(!preg_match('/Congratulations.*?saved\s+at\s+(.*?)\w+\.pem\s*\.\s+Your\s+cert/ius', $LEReturn, $Matches)) //Extract via a regex
		return OL("Cannot find path to certificate in return from letsencrypt$ForDocRootStr (They changed their return string):\n$LEReturn\n"); //If not found, throw error
	$FullChainPath=$Matches[1];

	//Transform the certificate path if parameter is given
	if(isset($CertPathOverride) && !($FullChainPath=@preg_replace($CertPathOverride[0], $CertPathOverride[1], $FullChainPath))) //Transform occurs here
		return OL("Error while transforming certiciate path$ForDocRootStr: ".($FullChainPath===NULL ? 'PREG Error: '.GetLastError() : 'Result string is empty'));

	//Load the certificates’ data
	$CertData=Array();
	foreach(Array('cert', 'privkey', 'chain') as $CertType)
		if(!($CertData[$CertType]=cfile_get_contents("$FullChainPath$CertType.pem")))
			return OL(
				"Cannot read $CertType certificate$ForDocRootStr at: $FullChainPath$CertType.pem : ".
				($CertData[$CertType]===FALSE ? GetLastError() : 'File is empty')
			);
	$CertDataPerDocRoot[$DocRoot]=$CertData;

	return 0;
}

//Confirm nested array names
function GetFromNestedArray($Arr, $KeyList) //KeyList is variadic
{
	foreach(array_slice(func_get_args(), 1) as $ArrKey)
		if(!is_array($Arr) || !isset($Arr[$ArrKey]))
			return NULL;
		else
			$Arr=$Arr[$ArrKey];
	return $Arr;
}
?>