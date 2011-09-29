<?
/*\
  /  This is a part of PhFiTo (aka PHP Fido Tosser)
 //  Copyright (c) Alex Kocharin, 2:50/13
///
///  This program is distributed under GNU GPL v2
///  See docs/license for details
//
/    $Id: L_postmsg.php,v 1.3 2007/10/14 08:29:54 kocharin Exp $
\*/

if (!function_exists('xinclude')) die();

class C_Param
{
	var $echo;
	var $from;
	var $to;
	var $fromaddr;
	var $toaddr;
	var $subj;
	var $repl;
	var $encode;
	var $file;
}

function postmsg_parsecmd($argc, $argv)
{
	$Param = new C_Param;
	$Param->file = false;
	for ($i=0; $i < $argc; $i++) {
		if ($argv[$i]{0} == '-') {
			$param = $argv[$i];
			if (strcmp($param,"-")==0) {
				$Param->file = '-';
			} else if (strcmp($param,"-fu")==0) {
				if (++$i == $argc) {
					return 0;
				} else {
					$Param->from = $argv[$i];
				}
			} else if (strcmp($param,"-tu")==0) {
				if (++$i == $argc) {
					return 0;
				} else {
					$Param->to = $argv[$i];
				}
			} else if (strcmp($param,"-fa")==0) {
				if (++$i == $argc) {
					return 0;
				} else {
					$Param->fromaddr = $argv[$i];
				}
			} else if (strcmp($param,"-ta")==0) {
				if (++$i == $argc) {
					return 0;
				} else {
					$Param->toaddr = $argv[$i];
				}
			} else if (strcmp($param,"-e")==0) {
				if (++$i == $argc) {
					return 0;
				} else {
					$Param->echo = $argv[$i];
				}
			} else if (strcmp($param,"-s")==0) {
				if (++$i == $argc) {
					return 0;
				} else {
					$Param->subj = $argv[$i];
				}
			} else if (strcmp($param,"-c")==0) {
				if (++$i == $argc) {
					return 0;
				} else {
					$from = $argv[$i];
				}
				if (++$i == $argc) {
					return 0;
				} else {
					$to = $argv[$i];
				}
				$Param->encode = array($from,$to);
			} else if (strcmp($param,"-r")==0) {
				$Param->repl = true;
			} else if (strcmp($param,"-h")==0 || strcmp($param,"--help")==0) {
				return 1;
			} else {
				return 0;
			}
		} else {
			if (strlen($Param->file)==0) {
				$Param->file = $argv[$i];
			} else {
				return 0;
			}
		}
	}
	if ($Param->file === false) {
		return 0;
	} else {
		return $Param;
	}
}

function postmsg_execute($params)
{
	$ps = postmsg_parsecmd(sizeof($params), $params);

	if ($ps === 0 || $ps === 1) {
		print "Usage: 
E_post.php [-fu fromuser] [-tu touser] [-e echo] [-s subject] [-fa fromaddr]
 [-ta toaddr] [-r] [-c d|w|m|k|i d|w|m|k|i] filename|\"-\"
";
	} else {
		$body = file_get_contents($ps->file=='-'?"php://stdin":$ps->file);
		if ($ps->repl) {
			$body = str_replace("\r","",$body);
			$body = str_replace("\n","\r",$body);
		}
		if ($ps->encode) {
			$body = convert_cyr_string($body,$ps->encode[0],$ps->encode[1]);
		}
		PostMessage($ps->echo, $ps->from, $ps->to, $ps->fromaddr, $ps->toaddr,
		  $ps->subj, $body, 0);
	}
}

?>
