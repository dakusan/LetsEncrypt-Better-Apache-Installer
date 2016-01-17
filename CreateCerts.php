<? /*
Takes the vhost information and creates the certificates through letsencrypt
Returns on error: [int] StatusCode
Returns on success: Array(
	'LEResponses'=>Array(DOC_ROOT=>LETS_ENCRYPT_RETURN_DATA, ...), //The list of successful responses of the certificate creation commands, keyed by their web roots
	'LEDocRootParams'=>Array(DOC_ROOT=>Array(...STUFF...),...) //List of document roots (by key) that were processed
)
*/
function CreateCerts(
	&$Config, //The current config
	$Params, //The list of config+user combined parameters
	$VirtualHostInfos //See ReadApacheConf.php/ReadApacheConf()
) {
	//Get the list of domains to ignore (Its keys are the domains)
	$IgnoreList=Array();
	if(isset($Params['Ignore']) && strlen(trim($Params['Ignore'])))
	{
		$IgnoreList=preg_split('/\h+/u', mb_strtolower(trim($Params['Ignore'])));
		$IgnoreList=array_combine($IgnoreList, array_fill(0, count($IgnoreList), 1));
	}

	//Combine domains into a list by the document root
	$PrimaryDomain=mb_strtolower($Params['Domain']); $PrimaryDomainDocumentRoot=null; //The primary domain (lowercase) and its document root
	$DocRoots=Array(); //Array(DOCROOT=>Array(DOMAIN1=>, DOMAIN2=>, ...))
	$IgnoreListCheck=$IgnoreList; //Domains are removed from this list as they are found. If any domains are not found, issue a warning
	foreach($VirtualHostInfos as $VHI)
		foreach($VHI['AllDomains'] as $Domain)
		{
			if($Domain==$PrimaryDomain) //If primary domain, remember document root
				$PrimaryDomainDocumentRoot=$VHI['DocumentRoot'];
			if(!isset($IgnoreList[$Domain])) //If not on the ignore list, add to the DocRoots list to process
				$DocRoots[$VHI['DocumentRoot']][$Domain]=1;
			else //If on the ignore list, mark as found
				unset($IgnoreListCheck[$Domain]);
		}
	if(count($IgnoreListCheck)) //If any requested ignored domains are found, issue a warning
		OL('Warning: The following domains on the domain ignore list were not used: '.implode(', ', array_keys($IgnoreListCheck)), 'Warning');

	//Get the letsencrypt executable command and parameters
	$AllowConfigOverride=$Config['AllowConfigOverride'];
	$LetsEncryptCommand=&$Config['LetsEncryptCommand'];
	$LetsEncryptParams=&$Config['LetsEncryptParams'];
	if($AllowConfigOverride && isset($Params['LECmd']))
		$LetsEncryptCommand=$Params['LECmd'];
	if(!strlen(trim($LetsEncryptCommand)))
		return OL('LetsEncryptCommand is empty');
	if($AllowConfigOverride && isset($Params['LEParams']))
		$LetsEncryptParams=$Params['LEParams'];

	//Get the DistributionType
	$DistributionType=&$Config['DistributionType'];
	if(isset($Params['Distribution']))
		$DistributionType=$Params['Distribution'];
	if(!preg_match('/^(?:GivenDomainOnly|AllInOne|SeparateVHosts)$/iD', $DistributionType))
		return OL('Invalid Distribution parameter: '.$DistributionType);
	$DistributionType=mb_strtolower($DistributionType);
	if(strcasecmp($DistributionType, 'GivenDomainOnly')==0) //If DistributionType=GivenDomainOnly, remove all the other document roots
	{
		if(!isset($DocRoots[$PrimaryDomainDocumentRoot]))
			return OL('Document root not found for primary vhost');
		$DocRoots=Array($PrimaryDomainDocumentRoot=>$DocRoots[$PrimaryDomainDocumentRoot]);
		OL('Removed all doc roots except: '.$PrimaryDomainDocumentRoot, 'Success');
	}

	//Gather the per-document-root params for the letsencrypt call
	$LEDocRootParams=Array();
	if(!stripos($LetsEncryptCommand, '?Params?'))
		return OL('The letsencrypt command (LECmd) requires “?Params?” to be somewhere within it');
	foreach($DocRoots as $DocRoot => $Domains)
	{
		//Confirm this document-root has domains
		if(!count($Domains))
		{
			OL('Warning: No domains found for vhost-document-root-path: '.$DocRoot, 'Warning');
			continue;
		}

		//Store the parameters for the docroot
		$CurDRParams='-w '.escapeshellarg($DocRoot);
		foreach($Domains as $Domain => $Dummy)
			$CurDRParams.=' -d '.escapeshellarg($Domain);
		$LEDocRootParams[$DocRoot]=$CurDRParams;
	}
	if(!count($LEDocRootParams))
		return OL('No valid vhosts found! This probably mean that all found domains were set in the “ignore” parameter');

	//Add the email parameter to the letsencrypt parameters
	$LetsEncryptParams.=' --email '.escapeshellarg($Params['Email']);

	//If DistributionType=AllInOne, combine all LEDocRootParams into 1 item
	if(strcasecmp($DistributionType, 'AllInOne')==0)
	{
		$RemLEDocRootParams=$LEDocRootParams;
		$LEDocRootParams=Array('AllDomains'=>implode(' ', $LEDocRootParams));
		OL('Combined all doc roots into one', 'Success');
	}
	$Only1Cert=(count($LEDocRootParams)==1);

	//Execute each command and check their returns
	$LEResponses=Array();
	foreach($LEDocRootParams as $DocRoot => $DRParams)
	{
		//Execute the letsencrypt call
		$LECall=str_ireplace('?Params?', "$LetsEncryptParams $DRParams", $LetsEncryptCommand).' 2>&1';
		OL('Executing: '.$LECall, 'Success');
		exec($LECall, $LEReturn, $LEStatusCode);

		//Check/store the response
		if($LEStatusCode)
			OL('Letsencrypt call failed'.($Only1Cert ? '' : ' for '.$DocRoot).':'."\n".implode("\n", $LEReturn));
		else
			$LEResponses[$DocRoot]=$LEReturn;
		unset($LEReturn);
	}

	//If there was an error, exit here (if requested)
	if(count($LEResponses)!=count($LEDocRootParams))
	{
		//Can only continue if distribution type is SeparateVHosts
		if(strcasecmp($DistributionType, 'SeparateVHosts')!=0)
			return OL('Required single domain not found');

		//Determine if the user wants to exit early on an error
		$InstallAnyways=&$Config['InstallAnyways'];
		if(isset($Params['InstallAnyways']))
			$InstallAnyways=$Params['InstallAnyways'];
		$InstallAnyways=preg_match('/^\s*[ty1]/iD', $InstallAnyways);
		if(!$InstallAnyways)
			return OL('Not all domains got a valid return (turn on InstallAnyways to bypass this error)');
	}

	//If AllInOne, redistribute the certificate to all document roots
	if(strcasecmp($DistributionType, 'AllInOne')==0)
	{
		$LEDocRootParams=$RemLEDocRootParams;
		foreach($LEDocRootParams as $DocRoot => $_)
			$LEResponses[$DocRoot]=$LEResponses['AllDomains'];
		unset($LEResponses['AllDomains']);
	}

	//Output success
	OL('Certificate(s) successfully created', 'Success');
	return compact('LEResponses', 'LEDocRootParams');
}
?>