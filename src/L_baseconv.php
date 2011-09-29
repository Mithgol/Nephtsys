<?php
/*\
  /  This is a part of PhFiTo (aka PHP Fido Tosser)
 //  Copyright (c) Alex Kocharin, 2:50/13
///
///  This program is distributed under GNU GPL v2
///  See docs/license for details
//
/    $Id: L_baseconv.php,v 1.3 2007/10/14 08:29:54 kocharin Exp $
\*/

if (!function_exists('xinclude')) die();

function baseconv_parsecmd($argc, $argv)
{
	$Param = array();
	for ($i=0; $i < $argc; $i++) {
		if ($argv[$i]{0} == '-') {
			$param = $argv[$i];
			if (strcmp($param,"-sp")==0) {
				if (++$i == $argc) {
					return 0;
				} else {
					$Param[0] = $argv[$i];
				}
			} else if (strcmp($param,"-st")==0) {
				if (++$i == $argc) {
					return 0;
				} else {
					$Param[1] = $argv[$i];
				}
			} else if (strcmp($param,"-dp")==0) {
				if (++$i == $argc) {
					return 0;
				} else {
					$Param[2] = $argv[$i];
				}
			} else if (strcmp($param,"-dt")==0) {
				if (++$i == $argc) {
					return 0;
				} else {
					$Param[3] = $argv[$i];
				}
			} else if (strcmp($param,"-c")==0) {
				if (++$i == $argc) {
					return 0;
				} else {
					$Param[4] = $argv[$i];
				}
			} else if (strcmp($param,"-h")==0 || strcmp($param,"--help")==0) {
				return 1;
			} else {
				return 0;
			}
		} else {
			return 0;
		}
	}
	if (!isset($Param[0]) || !isset($Param[1]) || !isset($Param[2]) 
		|| !isset($Param[3])) return 0;
	return $Param;
}

function baseconv_execute($params)
{
	$ps = baseconv_parsecmd(sizeof($params), $params);

	if ($ps === 0) {
		print " Wrong cmdline. Try 'E_convbase.php --help' for more information
	";
	} else if ($ps === 1) {
		print "Usage:
E_convbase.php [options]
Where options is:
  -sp source_path
  -st source_type
  -dp destination_path
  -dt destination_type

Examples:
  # convert r50.sysop.sq* to r50.sysop/*.xml
  phfito -W -sp r50.sysop -st squish -dp r50.sysop -dp xml

  # convert mysql base from database msgb and table r50.bone to r50.bone/*.xml
  phfito -c config -W -sp /msgb/r50.bone -st mysql -dp r50.bone -dp xml

  # convert ic.j* to phpbb forum base located in db phpbb with prefix phpbb_
  phfito -c config -W -sp ic -st jam -dp /phpbb/phpbb_/IC -dp phpbb3

  # a strange method of creating a directory my_dir :-)
  phfito -W -sp 123 -st pass -dp my_dir -dt opus

Valid msgbase types:
";
		GLOBAL $Bases_reference;
		$key_max = 0;
		foreach($Bases_reference as $key=>$arr) {
			$s = strlen($key);
			if ($key_max < $s) $key_max = $s;
		}
		foreach($Bases_reference as $key=>$arr) {
			print '  '.$key.multi(' ',$key_max-strlen($key)+3).'('.$arr[1].')'."\n";
		}
	} else {
		list($sp,$st,$dp,$dt) = $ps;
		ConvertBases($sp,$st,$dp,$dt);
	}
}

function ConvertBases($spath,$stype,$dpath,$dtype)
{
	tolog('S',"Converting base $spath ($stype) ...");
	tolog('S'," ... to $dpath ($dtype)");

	$SArea = new C_msgbase;
	if ($SArea->Open('',0,array("type" => $stype, "path" => $spath))) {
		$DArea = new C_msgbase;
		if ($DArea->Open('',1,array("type" => $dtype, "path" => $dpath))) {
			$ms = $SArea->GetNumMsgs();
			for ($i=1;$i<=$ms;$i++):
				$header = $SArea->ReadMsgHeader($i);
				$body = $SArea->ReadMsgBody($i);
				$DArea->WriteMessage($header,$body);
			endfor;
			$SArea->Close();
			$DArea->Close();
			tolog('S',"Ok.");
		} else {
			$SArea->Close();
			tolog('E',"Error during opening destination");
		}
	} else {
		tolog('E',"Error during opening source");
	}
}