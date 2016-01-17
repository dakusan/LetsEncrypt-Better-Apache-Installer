<?
//Make sure IsCLI is set
global $IsCLI;
if(!isset($IsCLI))
	$IsCLI=true;

//Return the exit code from Main.php
require_once(__DIR__.'/Output.php');
ExitStatusCodeFromVal(require(__DIR__.'/Main.php'));
?>