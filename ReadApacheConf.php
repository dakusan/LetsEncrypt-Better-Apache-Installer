<? /*
Reads in all the necessary virtual host information from the apache configuration files
Vhosts are included if their IP matches the IP for the user-supplied virtual host
Returns on error: [int] StatusCode
Returns on success:
A list of virtual hosts with the item format:
	Array(						//All DOMAIN values inside the array are set to lower case
		[string] ServerName=>,			//The primary domain name. The apache “ServerName” variable
		[Array(string,...)] ServerAliases=>,	//All alias domains. The apache “ServerAliases” variable. A warning is thrown if aliases are not found for a vhost
		[Array(string,...)] AllDomains=>,	//A combination of ServerName and ServerAliases. This is primarily used to speed up other parts of the code
		[string] DocumentRoot=>,		//The document root of the vhost. The apache “DocumentRoot” variable
		[int] Port=>,				//The port number for the virtualhost. This is only used for error messages
	)
*/
function ReadApacheConf(
	&$Config, //The current config
	$Params //The list of config+user combined parameters
) {
	//Read in the apache config file
	$AllowConfigOverride=$Config['AllowConfigOverride'];
	$ApacheConfPath=&$Config['ApacheConfPath'];
	if($AllowConfigOverride && isset($Params['ApacheConf'])) //Overwrite from parameters
		$ApacheConfPath=$Params['ApacheConf'];
	$ApacheConfData=@file_get_contents($ApacheConfPath);
	if(!$ApacheConfData)
		return OL('Cannot read the apache configuration file at: '.$ApacheConfPath.' : '.GetLastError());

	//Find the IP of the domain’s VirtualHost. The domain cannot be an alias
	if(!preg_match('/<\h*VirtualHost\h+([\d\.:]+?):\d+\h*>\s*ServerName\h+'.preg_quote($Params['Domain']).'\h*$/iusmD', $ApacheConfData, $Matches))
		return OL('Cannot find the domain in the apache config as a primary virtual host domain. Make sure the domain is actually a virtual host primary domain, which will generally not include the www');
	$HostIP=$Matches[1];
	OL('Found domain on IP: '.$HostIP, 'Success');

	//Read in all the relevant VirtualHosts, determined by the found IP from above
	$Success=1;
	$VirtualHostInfos=Array();
	if(!preg_match_all('/<\h*VirtualHost\h+'.$HostIP.':(\d+)\h*>.*?<\h*\/VirtualHost\h*>/ius', $ApacheConfData, $VirtualHostConfs, PREG_SET_ORDER))
		return OL('Cannot find any matching virtual hosts'); //While this should never happen, it is possible if the apache conf has errors in it
	foreach($VirtualHostConfs as $VHConf) //Extract information from the found VirtualHosts
		$Success&=(ProcessVirtualHostConf($VHConf[0], $VHConf[1], $VirtualHostInfos)^1);

	//Return the result
	if(!$Success) //Status code if error occurred
		return 1;
	return $VirtualHostInfos;
}

//Extract VirtualHost information
//Returns 0 on success and 1 on failure
function ProcessVirtualHostConf(
	$VH,				//The VirtualHost text from the apache conf
	$VHPort,			//The VirtualHost port. Only used for error messages
	&$ReturnArray			//A VirtualHost’s info is added to this on success
) {
	//Find the ServerName
	$VHInfo=Array('Port'=>$VHPort); //Save for messages
	if(!preg_match('/^\h*ServerName\h+([-\w\.]+)\h*$/iumD', $VH, $Matches)) //Only allow the domain name on this line to guarantee we found a proper domain
		return OL('ServerName match not found for VirtualHost. The ServerName line may only contain the domain name'."\n".str_repeat('-', 80)."\n$VH\n".str_repeat('-', 80));
	$VHInfo['AllDomains']=Array($ServerName=$VHInfo['ServerName']=mb_strtolower($Matches[1])); //Set AllDomains and ServerName

	//Find the ServerAlias
	if(!preg_match('/^\h*ServerAlias\b/im', $VH))
		OL("Warning: ServerAlias not given for $ServerName:$VHPort", 'Warning');
	else if(!preg_match('/^\h*ServerAlias\h+((?:[-\w\.]+\h+)*[-\w\.]+)\h*$/iumD', $VH, $Matches)) //Only allow domains on this apache config line
		return OL("ServerAlias is invalid for VirtualHost $ServerName:$VHPort. The ServerAlias line may only contain a whitespace separated list of domain names");
	else //Update AllDomains and set ServerAliases
		$VHInfo['AllDomains']=array_merge($VHInfo['AllDomains'], $VHInfo['ServerAliases']=preg_split('/\h+/u', mb_strtolower($Matches[1])));

	//Find the Document Root
	if(!preg_match('/^\h*DocumentRoot\h+(\S.*?)\h*$/iumD', $VH, $Matches))
		return OL("DocumentRoot not found for VirtualHost $ServerName:$VHPort");
	$VHInfo['DocumentRoot']=$Matches[1];

	//Output the matches
	OL(
		"Found $ServerName:$VHPort at $VHInfo[DocumentRoot]".
		(!isset($VHInfo['ServerAliases']) ? '' : ' with aliases '.implode(',', $VHInfo['ServerAliases'])),
		'Success' //Is not error
	);

	//Add to the list of virtual host’s infos
	$ReturnArray[]=$VHInfo;
	return 0;
}
?>