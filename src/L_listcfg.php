<?php
/*\
  /  This is a part of PhFiTo (aka PHP Fido Tosser)
 //  Copyright (c) Alex Kocharin, 2:50/13
///
///  This program is distributed under GNU GPL v2
///  See docs/license for details
//
/    $Id: L_listcfg.php,v 1.3 2011/01/08 12:21:09 kocharin Exp $
\*/

if (!function_exists('xinclude')) die();

xinclude("S_config.php");

class Fake_config
{
	var $Constants;
	var $encoding;
	var $modified;
	var $NoCache;
	
	function Fake_config($module)
	{
		$this->Constants = array('module' => $module);
		$this->modified = false;
		$this->encoding = false;
		$this->NoCache = array();
	}
}

function ChkConfig($filename,$main = true)
{
	print " =====  file: $filename  =====\n";
	$reader = new C_configfile_reader($filename);
	$encoding = false;
	while (($pair = $reader->ReadPair())!==false)
	{
		list($key,$value) = $pair;
		if ($main && (strcmp($key,'set')==0)) {
			list($c,$v) = explode('=',$value);
			$c = trim($c);
			$v = trim($v);
			if ($c && (strcmp($c{0},'"')==0) && (strcmp($c{strlen($c-1)},'"')==0)):
				$c = substr($c,1,strlen($c)-2);
			endif;
			if ((strcmp($v{0},'"')==0) && (strcmp($v{strlen($v)},'"')==0)) {
				$v = substr($v,1,strlen($c)-2);
			}
			GLOBAL $CONFIG;
			$CONFIG->Constants[$c] = $v;
		} else if ($main && (strcmp($key,'includelinks')==0)) {
			ChkConfig($value,0);
		} else if ($main && (strcmp($key,'includeareas')==0)) {
			ChkConfig($value,0);
		} else if ($main && (strcmp($key,'includefareas')==0)) {
			ChkConfig($value,0);
		} else if ($main && (strcmp($key,'include')==0)) {
			ChkConfig($value,1);
		} else {
			print $key.' '.$value."\n";
			if ($main) {
				if ($key == 'config_encoding') {
					$reader->fromenc = $encoding =
						get_encoding_by_name($value);
				}
			}
		}
	}
	$reader->Close();
	unset($reader);
	print " ===== end of: $filename =====\n";
}

function ListConfig($module,$config)
{
	GLOBAL $CONFIG;
	$CONFIG = new Fake_config($module);
	ChkConfig($config);
}

?>
