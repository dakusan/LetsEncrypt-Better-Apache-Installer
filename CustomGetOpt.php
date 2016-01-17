<? /*
I overwrote the functionality of getopt, as it was not working very well (It completely broke when it received something it didn’t like)
"optional::" parameters are not included
If an argument is found (starts with a - or --), its next value is recorded (set to TRUE if there are no arguments after it)
Every argument is processed as a potential parameter, even if it had been recorded as a value. This means it is possible, but very very unlikely, that a value could overwrite a parameter if it starts with a dash
The return value for an argument is set to TRUE if it is a parameter without a value (no colon after it), or there is no argument after it at all
A short option can be immediately followed by its value, without a space
Both short and long options can have an equal sign as their value separator

Parsing command line examples:
-e -labc             #e=-labc, l=abc
-e 5 -l abc          #e=5, l=abc
-e 5 -l=abc          #e=5, l=abc
-e                   #e=TRUE
-e 5 --letters abc   #e=5, letters=abc
-e 5 --letters=abc   #e=5, letters=abc
-e 5 --letters       #e=5, letters=TRUE
-e 5 --letters=      #e=5, letters=[empty string]

Parsing getopt option names:
Same rules as for getopt, but optional :: is not valid
*/

function cgetopt($ShortOptions, $LongOptions)
{
	//Check current arguments
	global $argc, $argv;
	static $Args=Array();
	if($Args===null) //If there were no arguments, exit now
		return Array();

	//Only process the command line the first time
	if(!count($Args))
	{
		//Parse the command line
		function NF($V) { return ($V===FALSE ? '' : $V); } //Turns false into an empty string
		for($i=1;$i<$argc;$i++)
			if(strlen($Str=$argv[$i])<=1 || $Str[0]!='-') //Ignore non-option parameters and check for not enough characters
				;
			else if($Str[1]!='-') //If a single dash, get the first letter as the name
				if(strlen($Str)<=2) //Value is next argument (if given)
					$Args[substr($Str, 1, 1)]=($i==$argc-1 ? TRUE : $argv[$i+1]);
				else //Value is within self
					$Args[substr($Str, 1, 1)]=NF(substr($Str, $Str[2]=='=' ? 3 : 2)); //Also handle equal sign (instead of space)
			else if(strlen($Str)<=2) //Not enough characters for double dash
				;
			else if(($EqualSignPos=stripos($Str, '=', 3))!==FALSE) //Handle equal sign with double dash
				$Args[substr($Str, 2, $EqualSignPos-2)]=NF(substr($Str, $EqualSignPos+1));
			else //Double dash with next argument as value (if given)
				$Args[substr($Str, 2)]=($i==$argc-1 ? TRUE : $argv[$i+1]);

		//Exit here if there are no variables
		if(!count($Args))
		{
			$Args=null; //Flag for later runs of this function that there are no parameters
			return Array();
		}
	}

	//Get the requested variables
	$Params=Array(); $Len=strlen($ShortOptions);
	for($i=0;$i<$Len;$i++)
	{
		$HasValue=($i<$Len-1 && $ShortOptions[$i+1]==':') ? 1 : 0;
		$MyName=$ShortOptions[$i];
		if(isset($Args[$MyName]))
			$Params[$MyName]=($HasValue ? $Args[$MyName] : TRUE);
		if($HasValue)
			$i++;
	}
	foreach($LongOptions as $OptionName)
	{
		$HasValue=(substr($OptionName, -1)==':');
		$MyName=($HasValue ? substr($OptionName, 0, -1) : $OptionName);
		if(isset($Args[$MyName]))
			$Params[$MyName]=($HasValue ? $Args[$MyName] : TRUE);
	}

	//Return the results
	return $Params;
}
?>