<?php
/*\
  /  This is a part of PhFiTo (aka PHP Fido Tosser)
 //  Copyright (c) Alex Kocharin, 2:50/13
///
///  This program is distributed under GNU GPL v2
///  See docs/license for details
//
/    $Id: L_netmail.php,v 1.17 2011/01/09 08:53:12 kocharin Exp $
\*/

if (!function_exists('xinclude')) die();

function DoNetSNAT($to_source)
{
	GLOBAL $CONFIG, $CurrentMessage, $CurrentMessageParsed;
	list($tz,$tn,$tf,$tp) = GetFidoAddr($to_source);
	$lines = explode("\r",$CurrentMessage->MsgText);
	$lines2 = array();
	if ($tp) $lines2[] = chr(1).'FMPT '.$tp;
	for($i=0,$s=sizeof($lines);$i<$s;$i++):
		if (strcmp(substr($lines[$i],0,6),chr(1).'INTL ')==0):
			$tonode = strtok(substr($lines[$i],6),' ');
			$lines2[] = chr(1).'INTL '.$tonode.' '.$tz.':'.$tn.'/'.$tf;
		elseif (strcmp(substr($lines[$i],0,6),chr(1).'TOPT ')==0):
			$lines2[] = $lines[$i];
		elseif (strcmp(substr($lines[$i],0,6),chr(1).'FMPT ')==0):
		elseif (strcmp(substr($lines[$i],0,10),' * Origin:')==0):
			$lines2[] = substr($lines[$i],0,strrpos($lines[$i],'(')).'('.$to_source.')';
		else:
			$lines2[] = $lines[$i];
		endif;
	endfor;
	$CurrentMessage->MsgText = join("\r",$lines2);
	return 1;
}

function DoNetDNAT($to_dest)
{
	GLOBAL $CONFIG, $CurrentMessage;
	list($tz,$tn,$tf,$tp) = GetFidoAddr($to_dest);
	$lines = explode("\r",$CurrentMessage->MsgText);
	$lines2 = array();
	if ($tp) $lines2[] = chr(1).'TOPT '.$tp;
	for($i=0,$s=sizeof($lines);$i<$s;$i++):
		if (strcmp(substr($lines[$i],0,6),chr(1).'INTL ')==0):
			$tonode = strtok(substr($lines[$i],6),' ');
			$fmnode = strtok('');
			$lines2[] = chr(1).'INTL '.$tz.':'.$tn.'/'.$tf.' '.$fmnode;
		elseif (strcmp(substr($lines[$i],0,6),chr(1).'TOPT ')==0):
		elseif (strcmp(substr($lines[$i],0,6),chr(1).'FMPT ')==0):
			$lines2[] = $lines[$i];
		else:
			$lines2[] = $lines[$i];
		endif;
	endfor;
	$CurrentMessage->MsgText = join("\r",$lines2);
	return 1;
}

function SendReply($aReply,$toaddr,$msgbody,$msgps,$attr,$touser,$subj,$area = false,$fromname = false)
{
	$s = GetStrFromFile_param('GREET');
	$text = ($s?$s:"Hello!")."\r\r";
	$text.= $msgbody;
	$s = GetStrFromFile_param('BYE');
	$text.= "\r\r".($s?$s:"Bye!");
	if ($msgps):
		$text.= "\r\rP.S.: ".$msgps."\r\r";
	endif;
	return PostMessage(
		$area,
		$fromname === false?'Phfito Tracker':$fromname,
		$touser,
		false,
		$toaddr,
		$subj,
		$text,
		$attr,
		$aReply
	);
}

function InitVsysTrack($to,$origto,$transto = false)
{
	GLOBAL $CONFIG, $CurrentMessage, $SRC_PATH;
	if ($CONFIG->GetVar('use_vsys')):
		xinclude("L_vsys.php");
		GLOBAL $VSYS;
		_init_vsys();
		$VSYS->user = $CurrentMessage->FromUser;
		$VSYS->sysop = 'Tracker of Phfito';
		$VSYS->station = $CONFIG->GetVar('system');
		$VSYS->Macro_arr_adv['TOUSER'] = $CurrentMessage->ToUser;
		$VSYS->Macro_arr_adv['FROMUSER'] = $CurrentMessage->FromUser;
		$VSYS->Macro_arr_adv['FROMADDR'] = $to;
		$VSYS->Macro_arr_adv['ORIGTO'] = $origto;
		$VSYS->Macro_arr_adv['TRANSTO'] = $transto;
	endif;
}

function GetStrFromFile_param($param,$default = false,$macro_arr = array())
{
	GLOBAL $CONFIG;
	GLOBAL $VSYS;
	if (isset($VSYS)) {
		if ($f = @fopen($CONFIG->GetVar('maindir').'/'.$CONFIG->GetVar('ftrack_dict'),'r')):
			$newkeys = array();
			foreach($macro_arr as $key=>$value):
				$VSYS->Macro_arr_adv[$key] = $value;
				$newkeys[] = $key;
			endforeach;
			$s = $VSYS->_read_file_and_match($f,$param,100);
			$s = $VSYS->_format_reply($s);
			$s = $VSYS->_exp_macro($s);
			if ($s) $s{0} = $VSYS->_to_upper($s{0},!$VSYS->_updown_firstchar());
			$s = convert_cyr_string($s,'k','d');
			fclose($f);
			foreach($newkeys as $key) unset($VSYS->Macro_arr_adv[$key]);
			if ($s):
				return $s;
			else:
				return $default;
			endif;
		else:
			return $default;
		endif;
	} else {
		return $default;
	}
}

function FormForward($top = true,$skip = true,$bottom = true,$body = false)
{
	return "=-------------------------------------------------------------------------=\r" . 
	        Form_Header() .
		   "=-------------------------------------------------------------------------=\r" .
			Invalidate(Quote_Cludges('',$top,$skip,$bottom,$body)) . 
		   "=-------------------------------------------------------------------------=\r";
}

function Form_Header()
{
	GLOBAL $CurrentMessage;
	$wd = $CurrentMessage->getAsciiDate();
	$res = "  From: ".$CurrentMessage->FromUser;
	for($i=65-strlen($CurrentMessage->FromUser)-strlen($wd);$i>0;$i--) $res.=' ';
	$res.= $wd."\r";
	$res.= "  To:   ".$CurrentMessage->ToUser."\r";
//	for($i=68-strlen($CurrentMessage->FromUser);$i>0;$i--) $res.= print ' ';
	$res.= "  Subj: {$CurrentMessage->Subject}\r";
	return $res;
}

function Quote_Cludges($text,$top = true,$skip = true,$bottom = true,$body = false)
{
	GLOBAL $CONFIG, $CurrentMessageParsed;
	$topcl = str_replace(chr(1),'@',$CurrentMessageParsed->top);
	$botcl = str_replace(chr(1),'@',$CurrentMessageParsed->bottom);
	$res = '';
	if ($top) $res.= $topcl.($body?'':"\r");
	if ($skip):
		$s = GetStrFromFile_param('SKIP');
		$res.= ($s?$s:"[... skipped ...]")."\r\r";
	endif;
	if ($body) $res.= $CurrentMessageParsed->body.$CurrentMessageParsed->tearline.$CurrentMessageParsed->origin;
	if ($bottom) $res.= $botcl;
//	die(str_replace("\r","\n",$res));
	return $res;
}

function Invalidate($str)
{
	$str = "\r".$str;
	$str = str_replace("\r---","\r-+-",$str);
	$str = str_replace("\r * Origin:","\r + Origin:",$str);
	$str = str_replace("\rSEEN-BY:","\rSEEN+BY:",$str);
	$str = substr($str,1);
	return $str;
}

function SendARQ($to,$origto,$msgid,$transto)
{
	GLOBAL $CONFIG;
	GLOBAL $CurrentMessage;
	tolog('t',"Sending ARQ-receipt to $to");
	InitVsysTrack($to,$origto,$transto);
	$msgbody = '';
	$s = GetStrFromFile_param('ARQGET');
	$msgbody.= ($s?$s:"Your message to $origto arrived to my station.")."\r";
	$s = GetStrFromFile_param('ARQPACK');
	$msgbody.= ($s?$s:"It was packed to $transto")."\r";
	$s = GetStrFromFile_param('QUOTE');
	$msgps = ($s?$s:"Here is your message:")."\r".FormForward();
	return SendReply($msgid,$to,$msgbody,$msgps,MSG_PVT | MSG_RRC,$CurrentMessage->FromUser,'ARQ - receipt');
}

function SendRRQ($to,$origto,$msgid)
{
	GLOBAL $CONFIG;
	GLOBAL $CurrentMessage;
	tolog('t',"Sending RRQ-receipt to $to");
	InitVsysTrack($to,$origto);
	$msgbody = '';
	$s = GetStrFromFile_param('RRQ');
	$msgbody.= ($s?$s:"Your message to me delivered to my station.")."\r";
	$s = GetStrFromFile_param('QUOTE');
	$msgps = ($s?$s:"Here is your message:")."\r".FormForward();	
	return SendReply($msgid,$to,$msgbody,$msgps,MSG_PVT | MSG_RRC,$CurrentMessage->FromUser,'RRQ - receipt');
}

function SendNoroute($to,$origto,$msgid)
{
	GLOBAL $CONFIG;
	GLOBAL $CurrentMessage;
	InitVsysTrack($to,$origto);
	$msgbody = '';
	$s = GetStrFromFile_param('NOROUTE');
	$msgbody.= ($s?$s:"Routing to destination is not defined.")."\r";
	$s = GetStrFromFile_param('RETURNMSG');
	$msgbody.= ($s?$s:"So, delivery was stopped, message was bounced.")."\r";
	$s = GetStrFromFile_param('QUOTE');
	$msgps = ($s?$s:"Here is your message:")."\r".FormForward();	
	return SendReply($msgid,$to,$msgbody,$msgps,MSG_PVT,$CurrentMessage->FromUser,'Message could not be delivered');
}

function SendLoop($to,$origto,$msgid)
{
	GLOBAL $CONFIG;
	GLOBAL $CurrentMessage;
	InitVsysTrack($to,$origto);
	$msgbody = '';
	$s = GetStrFromFile_param('LOOP');
	$msgbody.= ($s?$s:"Loop detected. Please, contact sysop to solve this problem.")."\r";
	$s = GetStrFromFile_param('RETURNMSG');
	$msgbody.= ($s?$s:"So, delivery was stopped, message was bounced.")."\r";
	$s = GetStrFromFile_param('QUOTE');
	$msgps = ($s?$s:"Here is your message:")."\r".FormForward();	
	return SendReply($msgid,$to,$msgbody,$msgps,MSG_PVT,$CurrentMessage->FromUser,'Message could not be delivered');
}

function SendPING($to,$origto,$msgid)
{
	GLOBAL $CurrentMessage;
	tolog('t',"Sending PONG to $to");
	InitVsysTrack($to,$origto);
	$msgbody = '';
	$s = GetStrFromFile_param('PONG');
	$msgbody.= ($s?$s:"Your message arrived to my station.")."\r";
	$s = GetStrFromFile_param('QUOTE');
	$msgps = ($s?$s:"Here is your message:")."\r".FormForward();	
	return SendReply($msgid,$to,$msgbody,$msgps,MSG_PVT,$CurrentMessage->FromUser,'Answer to your PING');
}

function NetMsgForUs($orig,$dest,$msgid,$islocal)
{
	GLOBAL $CONFIG;
	GLOBAL $CurrentMessage;
	tolog('t',"New netmail message");
	$Attrs = FTS_to_Flag($CurrentMessage->Attr);
	if (!$islocal):
		if ($Attrs & MSG_RRQ):
			SendRRQ($orig,$dest,$msgid);
		endif;
		if (strcasecmp($CurrentMessage->ToUser,'ping')==0):
			SendPING($orig,$dest,$msgid);
		endif;
		if ($CONFIG->Areas->Vals):
			foreach ($CONFIG->Areas->Vals as $area):
				if (strcmp($area->Status,'netmailarea')==0):
					$areaname = $area->EchoTag;
					break;
				endif;
			endforeach;
		endif;
		if (!isset($areaname)):
			$areaname = 'Netmail';
			if (AutoCreateArea('NetMailArea','Netmail',false,'Autocreated netmail area')):
				tolog('T',"Created netmail area");
			else: 
				tolog('T',"Error creating netmail area");
				return 0;
			endif;
		endif;
		$Area = new C_msgbase;
		$Area->Open($areaname,1);
		$header = new C_msgheader;
		$header->Attrs = FTS_to_Flag(ZeroNFlags($CurrentMessage->Attr));
		$header->From = $CurrentMessage->FromUser;
		$header->To = $CurrentMessage->ToUser;
		$header->Subj = $CurrentMessage->Subject;
		$header->FromAddr = $orig;
		$header->ToAddr = $dest;
		$header->WDate = $CurrentMessage->Date;
		$header->ADate = time();
		$Area->WriteMessage($header,$CurrentMessage->MsgText);
		$Area->Close();
	endif;
	tolog('t','Tossing message finished');
	return 1;
}

function Process_netmail_message($islocal)
{
	GLOBAL $CONFIG, $CurrentMessage, $CurrentMessageParsed, $CurrentMessageInfo;
	$Attr = FTS_to_Flag($CurrentMessage->Attr);
	ParseMessage(1);
	$MsgText = $CurrentMessage->MsgText;
	$orig = $CurrentMessageParsed->FromAddr;
	$dest = $CurrentMessageParsed->ToAddr;
	$msgid = $CurrentMessageParsed->msgid;
	$forus = 0;
	foreach($CONFIG->GetArray('address') as $addr):
		if (strcmp($addr,$dest)==0):
			$forus = 1;
			break;
		endif;
	endforeach;

	$addr = false;
	if (!$forus) {
		$addr = RouteMsg($dest);
		if ($addr == 'msgtous') {
			$forus = 1;
		}
	}

	modules_hook('netmail',array($forus,$Attr,$islocal));
	if ($forus):
		$res = NetMsgForUs($orig,$dest,$msgid,$islocal);
		$CurrentMessageInfo->state = 'saved';
	else:
		$res = 1;
		if ($addr):
			if (!$islocal && (FTS_to_Flag($CurrentMessage->Attr) & MSG_ARQ)):
				SendARQ($orig,$dest,$msgid,$addr);
			endif;
			if (($MessageText = AddVia($CurrentMessage->MsgText,$CONFIG->GetVar('address')))!==false) {
				$CurrentMessageInfo->state = 'routed';
				$CurrentMessageInfo->packed_to[] = $addr;
				$Omessage = new Pkt_message;
				$Omessage->Version = 2;
				$Omessage->OrigNode = $CurrentMessage->OrigNode;
				$Omessage->DestNode = $CurrentMessage->DestNode;
				$Omessage->OrigNet = $CurrentMessage->OrigNet;
				$Omessage->DestNet = $CurrentMessage->DestNet;
				$Omessage->Attr = ZeroNFlags($CurrentMessage->Attr);
				$Omessage->Cost = $CurrentMessage->Cost;
				$Omessage->Date = $CurrentMessage->Date;
				$Omessage->ToUser = $CurrentMessage->ToUser;
				$Omessage->FromUser = $CurrentMessage->FromUser;
				$Omessage->Subject = $CurrentMessage->Subject;
				$Omessage->MsgText = $MessageText;
				PackMessageTo($Omessage,$addr);
			} else {
				$CurrentMessageInfo->state = 'loop';
				tolog('T',"Loop message	detected");
				SendLoop($orig,$dest,$msgid,$addr);
			}
		else:
			$CurrentMessageInfo->state = 'noroute';
			tolog('T',"Routing message to $dest not defined");
			SendNoroute($orig,$dest,$msgid,$addr);
		endif;
	endif;
	return $res;
}

function RouteMsg($toaddr)
{
	GLOBAL $CONFIG;
	$toaddr2 = $toaddr;
	if (strpos($toaddr2,'.')===false) $toaddr2.='.0';
	$routedest = false;
	foreach($CONFIG->GetArray('route') as $route) {
		$ifs = explode(' ',$route);
		$ifto = array_shift($ifs);
		foreach($ifs as $if) {
			if ($if = trim($if)) {
				if (is_match_mask($if,$toaddr) || is_match_mask($if,$toaddr2)) {
					$routedest = $ifto;
					break;
				}
			}
		}
		if ($routedest !== false) {
			break;
		}
	}
	if ($routedest === false) $routedest = 'unknown';
	$routedest = strtolower($routedest);
	$routedest = modules_hook_over('route', array($toaddr), $routedest);

	if ($routedest == 'host') {
		return substr($toaddr,0,strpos($toaddr,'/')+1).'0';
	} else if ($routedest == 'direct') {
		return $toaddr;
	} else if ($routedest == 'msgtous') {
		return 'msgtous';
	} else if ($routedest == 'unknown') {
		return false;
	} else if ($routedest == 'boss') {
		return substr($toaddr2,0,strpos($toaddr2,'.'));
	} else if (preg_match('/^\d+:\d+\/\d+(\.\d+)?$/',$routedest)) {
		return $routedest;
	} else {
		tolog('E',"Wrong route destination: $routedest, check your config file!");
		return false;
	}
}

function AddVia($text,$addr)
{
	GLOBAL $CONFIG;
	$p = strlen($text);
	while(strcmp($text{$p-1},chr(0))==0) $p--;
	while(strcmp($text{$p-1},"\r")==0) $p--;
	$text = substr($text,0,$p)."\r";

	$pr = 0;
	$p = strpos($text,"\r");
	$paths = array();
	while($p !== false) {
		$line = substr($text,$pr+1,$p-$pr);
		if (strcasecmp(substr($line,0,4),chr(1).'Via')==0) {
			if (preg_match('/[0-9]+\:[0-9]+\/[0-9]+(\.[0-9]+)?/',$line,$msv)) {
				if (isset($paths[$msv[0]])) {
					$paths[$msv[0]]++;
				} else {
					$paths[$msv[0]] = 1;
				}
			}
		}
		$pr = $p;
		$p = strpos($text,"\r",$pr+1);
	}
	
	foreach($paths as $link=>$num) {
		if (($num > 2) && in_array($link,$CONFIG->GetArray('address'))) {
			return false;
		}
	}
	
	$text.= chr(1)."Via $addr @".date('Ymd.His').' Phfito/'.VERSION."\r";
	return $text;
}

function ScanNetMessage($area,$msg,$Area = false,$header = false,$ext = false)
{
	GLOBAL $CONFIG;
	$res = 1;
	$openb = $Area === false;
	$areanum = $CONFIG->Areas->FindAreaNum($area);
	if ($openb):
		$Area = new C_msgbase;
		$Area->Open($area);
	endif;
	if ($header === false) $header = $Area->ReadMsgHeader($msg,$ext);
	$MessageText = $Area->ReadMsgBody($msg,$ext);
	$p = strlen($MessageText);
	while(strcmp($MessageText{$p-1},"\r")==0) $p--;
	while(strcmp($MessageText{$p-1},chr(0))==0) $p--;
	while(strcmp($MessageText{$p-1},"\r")==0) $p--;
	$MessageText = substr($MessageText,0,$p)."\r";
	if ($MessageText{0} == "\r") $MessageText = substr($MessageText,1);
	list($FromAddr,$ToAddr) = GetNetMsgOrigDest($MessageText);
	preg_match('/:([0-9]+)\/([0-9]+)/',$FromAddr,$m);
	preg_match('/:([0-9]+)\/([0-9]+)/',$ToAddr,$t);

	ParseMessageText(1,$MessageText,1);
	$MTexts = SplitMessage(1);
	$size = sizeof($MTexts);
	for ($i=0; $i<$size; $i++) {
		$Omessage = new Pkt_message;
		$Omessage->Version = 2;
		$Omessage->OrigNode = $m[2];
		$Omessage->DestNode = $t[2];
		$Omessage->OrigNet = $m[1];
		$Omessage->DestNet = $t[1];
		if ($i==0) {
			$Omessage->Attr = ZeroNFlags(Flag_to_FTS($header->Attrs));
		} else {
			$Omessage->Attr = ZeroNFlags(Flag_to_FTS($header->Attrs & ~MSG_ATT));
		}
		$Omessage->Cost = 0;
		$Omessage->Date = $header->WDate;
		$Omessage->ToUser = $header->To;
		$Omessage->FromUser = $header->From;
		$Omessage->Subject = $header->Subj;
		$Omessage->MsgText = "ISLOCAL\r".$MTexts[$i];
		$res &= PackMessageLocal($Omessage);
	}
	if ($openb):
		$Area->Close();
	endif;
	return $res;
}