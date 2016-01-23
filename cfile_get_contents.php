<?
//Make ssh.exec compatible w/ file_get_contents
function cfile_get_contents($S)
{
	//Normal file_get_contents
	if(!preg_match('/^\s*ssh2\.exec:/i', $S))
		return @file_get_contents($S);

	//ssh2.exec wrapper to get all contents
	$Parts=Array();
	$Stream=@fopen($S, 'r');
	$EmptyCount=0;
	if($Stream===FALSE)
		return FALSE;
	while(!feof($Stream))
		if(FALSE===($NewStr=fread($Stream, 65535)))
			break;
		else if(strlen($NewStr))
		{
			$Parts[]=$NewStr;
			$EmptyCount=0;
		}
		else if($EmptyCount++==100000) //Check for hung connection - unfortunately, there is no real good solution for this
			return FALSE;
	return implode('', $Parts);
}
?>