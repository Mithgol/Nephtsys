<?php
/*\
  /  This is a part of PhFiTo (aka PHP Fido Tosser)
 //  Copyright (c) Alex Kocharin, 2:50/13
///
///  This program is distributed under GNU GPL v2
///  See docs/license for details
//
/    $Id: M_areafix.php,v 1.6 2011/01/08 12:21:09 kocharin Exp $
\*/

if (!function_exists('xinclude')) die();

class M_areafix
{
	function hook_netmail($params)
	{
		GLOBAL $CurrentMessage, $CurrentMessageParsed;
		list($forus,$attr,$islocal) = $params;
		if ($forus && (strcasecmp($CurrentMessage->ToUser,'areafix')==0)) {
			$to = $CurrentMessageParsed->FromAddr;
			$origto = $CurrentMessageParsed->ToAddr;
			$msgid = $CurrentMessageParsed->msgid;
			tolog('t',"Processing Areafix commands from $to");
			if (!class_exists('C_Areafix')) {
				include $SRC_PATH."C_areafix.php";
			}
			$msgbody = '';
			$msgps = '';
			InitVsysTrack($to,$origto);
			$user = $CurrentMessage->FromUser;
			$areafix = new C_Areafix;
			if (($err = $areafix->Init($to,$CurrentMessage->Subject,$msgid,$user)) === ''):
				$proc = 1;
			else:
				tolog('t',"Areafix: Security violation");
				$msgbody = $err."\r\r";
				$proc = 0;
			endif;
			$qp = ' '.get_initials($user).'> ';
			$lines = explode("\r",$CurrentMessageParsed->body);
			for ($i=0,$s=sizeof($lines);$i<$s;$i++):
				$line = $lines[$i];
				if ($line = trim($line)):
					if ($proc):
						tolog('t',"Afix command: $line");
						$msgbody.= "\r".$qp.$line."\r\r".$areafix->ProcCmd($line)."\r";
					else:
						$msgbody.= $qp.$line."\r";
					endif;
				endif;
			endfor;
			$lines = array();
			if ($proc) $msgps.= $areafix->Done();
			SendReply($msgid,$to,$msgbody,$msgps,MSG_PVT,$user,'Reply from Areafix');
		}
	}
}