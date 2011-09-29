<?php
/*\
  /  This is a part of PhFiTo (aka PHP Fido Tosser)
 //  Copyright (c) Alex Kocharin, 2:50/13
///
///  This program is distributed under GNU GPL v2
///  See docs/license for details
//
/    $Id: P_phfito.php,v 1.25 2011/01/10 01:47:08 kocharin Exp $
\*/

if (!function_exists('xinclude')) die();

xinclude('S_config.php');
xinclude('S_confedit.php');
xinclude('F_utils.php');
xinclude('L_netmail.php');
xinclude('L_echomail.php');
xinclude('L_fidonet.php');
xinclude('C_queue.php');
xinclude('F_archives.php');
xinclude('C_msgbase.php');
xinclude('F_pkt.php');
xinclude('A_common.php');
xinclude('L_ftndns.php');
xinclude('C_stats.php');
xinclude('L_modules.php');
xinclude("L_sqlt.php");

define('MPF_MODIFIED',0x00000001);
define('MPF_NOSTORE',0x00000002);
define('MPF_NOBADCK',0x00000004);

class C_CurrentMessage_parsed
{
	var $area;
	
	var $msgid;
	var $reply;
	var $intl;
	var $fmpt;
	var $topt;
	var $seenby;
	var $path;

	var $top;
	var $body;
	var $tearline;
	var $origin;
	var $bottom;
	
	var $FromAddr;
	var $ToAddr;
}

class C_CurrentMessage_info
{
	var $area;
	var $from_link;
	var $pkt_name;
	var $state;
	var $packed_to;
}

function AddToFtnDns($user,$addr)
{
	GLOBAL $CONFIG, $FtnDns;
	if ($CONFIG->GetVar('updateftndns')) {
		if ($FtnDns !== false) {
			$FtnDns->Append(array($addr),array($user));
		}
	}
}

function AddrNAT($param,$addr)
{
	GLOBAL $CONFIG;
	if (strpos($addr,'.')===false) $addr.='.0';
	foreach($CONFIG->GetArray($param) as $route):
		$ifs = explode(' ',$route);
		$ifto = array_shift($ifs);
		foreach($ifs as $if):
			if ($if = trim($if)):
				if (is_match_mask($if,$addr)):
					return $ifto;
				endif;
			endif;
		endforeach;
	endforeach;
	return false;
}

function PostMessage($echo,$fromuser,$touser,$fromaddr,$toaddr,$subject,$body,$attrs = 0,$reply = false,$topkt = false)
{
	GLOBAL $CONFIG;
	if (!$fromuser) $fromuser = $CONFIG->GetVar('sysop');
	if (!$touser) $touser = $CONFIG->GetVar('sysop');
	if (!$fromaddr) $fromaddr = $CONFIG->GetOurAkaFor($toaddr);
	if (!$toaddr) $toaddr = $CONFIG->GetVar('address');
	if (!$subject) $subject = '<empty>';
	list($fz,$fn,$ff,$fp) = GetFidoAddr($fromaddr);
	list($tz,$tn,$tf,$tp) = GetFidoAddr($toaddr);
	if (!$echo || $echo == 'netmail'):
		$text = chr(1)."INTL ".$tz.':'.$tn.'/'.$tf." ".$fz.':'.$fn.'/'.$ff."\r";
		if ($tp) $text.= chr(1)."TOPT $tp\r";
		if ($fp) $text.= chr(1)."FMPT $fp\r";
		$text.= chr(1)."MSGID: $fromaddr ".getmsgid()."\r";
		if ($reply !== false)
			$text.= chr(1)."REPLY: $reply\r";
		$text.= chr(1)."CHRS: CP866 2\r";
		$text.= $body."\r";
		$text.= '--- Phfito Tracker on '.$CONFIG->GetVar('system');
	else:
		$text = 'AREA:'.strtoupper($echo)."\r";
		$text.= chr(1)."MSGID: $fromaddr ".getmsgid()."\r";
		$text.= chr(1).'TID: Phfito '.VERSION."\r";
		$text.= chr(1)."CHRS: CP866 2\r";
		if ($body{strlen($body)-1} != "\r") $body .= "\r";
		$text.= $body;
		$text.= '--- Phfito Tracker on '.$CONFIG->GetVar('system')."\r";
		$text.= ' * Origin: '.$CONFIG->GetVar('system').', '.$CONFIG->GetVar('location').' ('.$fromaddr.')';
	endif;

	ParseMessageText(1,$text,1);
	$MTexts = SplitMessage(1);
	$size = sizeof($MTexts);
	$r = 1;
	for ($i=0; $i<$size; $i++) {
		$Message = new Pkt_message;
		$Message->Version = 2;
		$Message->OrigNode = $ff;
		$Message->DestNode = $tf;
		$Message->OrigNet = $fn;
		$Message->DestNet = $tn;
        if ($i==0) {
			$Message->Attr = ZeroNFlags(Flag_to_FTS($attrs));
		} else {
			$Message->Attr = ZeroNFlags(Flag_to_FTS($attrs & ~MSG_ATT));
		}
		$Message->Cost = 30;
		$Message->Date = time();
		$Message->ToUser = $touser;
		$Message->FromUser = $fromuser;
		$Message->Subject = $subject;
		$Message->MsgText = $MTexts[$i];
		$r &= PackMessageLocal($Message,$topkt);
	}
	return $r;
}

function ReportNewAreas($area = false,$descr = false,$link = false)
{
	xinclude('S_confedit.php');
	GLOBAL $CONFIG;
	STATIC $Data;
	if ($area === false):
	if (sizeof($Data)):
		$areas = '';
		for($i=0,$s=sizeof($Data);$i<$s;$i++) $areas.= ($areas?"\n":'').$Data[$i][0];
		foreach($CONFIG->GetArray('autoareacreateflag') as $newareas) {
			AppendLine($newareas,$areas);
		}
		$report = '	  *Echotag*                    *Description*               *Link*'."\r".'/============================\\ /============================\\ /==============\\'."\r";
		foreach($Data as $arr) {
			list($echo,$descr,$link) = $arr;
			$echo_d = $descr_d = $link_d = array();
			$se = $sd = $sl = 1;
			while (strlen($echo)>28) { 
				$echo_d[] = substr($echo,0,28); 
				$echo = substr($echo,28); 
				$se++; 
			};
			$echo_d[] = $echo;
			while (strlen($descr)>28) { 
				$descr_d[] = substr($descr,0,28); 
				$descr = substr($descr,28); 
				$sd++; 
			};
			$descr_d[] = $descr;
			while (strlen($link)>14) { 
				$link_d[] = substr($link,0,14); 
				$link = substr($link,14); 
				$sl++;
			};
			$link_d[] = $link;
			for($i=0;$i<$sl;$i++):
				$e = ($i<$se)?$echo_d[$i]:'';
				$d = ($i<$sd)?$descr_d[$i]:'';
				$report.= ' '.$e;
				for($ii=0,$c=(31-strlen($e));$ii<$c;$ii++) $report.=' ';
				$report.= $d;
				for($ii=0,$c=(31-strlen($d));$ii<$c;$ii++) $report.=' ';
				$report.= $link_d[$i];
				$report.= "\r";
			endfor;
			for(;$i<$sd;$i++):
				$e = ($i<$se)?$echo_d[$i]:'';
				$report.= ' '.$e;
				for($ii=0,$c=(31-strlen($e));$ii<$c;$ii++) $report.=' ';
				$report.= $descr_d[$i];
				$report.= "\r";
			endfor;
			for(;$i<$se;$i++):
				$report.= ' '.$echo_d[$i];
				$report.= "\r";
			endfor;
		}
		$report.= '\\============================/ \\============================/ \\==============/';
		$echo = $CONFIG->GetVar('reportto');
		PostMessage($echo,'Phfito Tracker',($echo?'All':''),'','','New areas created on '.$CONFIG->GetVar('system'),$report,'');
		$Data = array();
	endif;
	else:
		$Data[] = array($area,$descr,$link);
	endif;
}

function ParseMessage($net)
{
	GLOBAL $CONFIG, $CurrentMessage;
	ParseMessageText($net,$CurrentMessage->MsgText);
}

function ParseMessageText($net,$message,$alt = false)
{
	GLOBAL $CONFIG;
	if ($alt) {
		GLOBAL $CurrentMessageAlt;
		$prs = &$CurrentMessageAlt;
	} else {
		GLOBAL $CurrentMessageParsed;
		$prs = &$CurrentMessageParsed;
	}
	if (!$prs) { $prs = new C_CurrentMessage_parsed; };
	$prs->area = false;
	$prs->msgid = $prs->reply = $prs->intl = $prs->fmpt = $prs->topt = '';
	$prs->tearline = $prs->origin = '';	

	$lines = explode("\r",str_replace("\n","",$message));
	$wascl = false;
	$prs->top = $prs->seenby = $prs->path = '';
	while(sizeof($lines)) {
		$line = array_shift($lines);
		if ($line == '') {
			break;
		} else if (!$wascl && substr($line,0,5) == 'AREA:') {
			if ($prs->area === false) {
				$prs->area = trim(substr($line,5));
			}
		} else if ($line{0} == chr(1)) {
			$wascl = true;
			$prs->top .= $line."\r";
			if (substr($line,1,7) == 'MSGID: ') {
				$prs->msgid = trim(substr($line,8));
			} else if (substr($line,1,7) == 'REPLY: ') {
				$prs->reply = trim(substr($line,8));
			} else if (substr($line,1,5) == 'INTL ') {
				$prs->intl = trim(substr($line,6));
			} else if (substr($line,1,5) == 'TOPT ') {
				$prs->topt = trim(substr($line,6));
			} else if (substr($line,1,5) == 'FMPT ') {
				$prs->fmpt = trim(substr($line,6));
			}
		} else {
			break;
		}
	}
	array_unshift($lines,$line);

	$lines_size = sizeof($lines);
	$v = $p = $s = $n = $o = $t = 0x7fffffff;
	for($i=$lines_size-1;$i>=0;$i--) {
		$line = $lines[$i];
		if (substr($line,0,9) == 'SEEN-BY: ') {
			$s = $i;
		} else if (substr($line,0,4) == chr(1).'Via') {
			$v = $i;
		} else if (substr($line,0,7) == chr(1).'PATH: ') {
			if ($s == 0x7fffffff && $p == 0x7fffffff) {
				$p = $i;
			}
		} else if (substr($line,0,11) == ' * Origin: ') {
			$o = $i;
			if ($i>0 && (substr($lines[$i-1],0,3) == '---')) {
				$t = $i-1;
			}
			break;
		} else if (substr($line,0,3) == '---') {
			$t = $i;
			break;
		} else if (trim($line)) {
			break;
		}
	}
	
	$prs->bottom = '';
	for($i=min($t,$o,$p,$s,$v);$i<$lines_size;$i++) {
		$line = &$lines[$i];
		if (substr($line,0,9) == 'SEEN-BY: ') {
			$prs->seenby .= ($prs->seenby?' ':'').trim(substr($line,9));
			$prs->bottom .= $line."\r";
		} else if (substr($line,0,7) == chr(1).'PATH: ') {
			$prs->path = trim(substr($line,7));
			$prs->bottom .= $line."\r";
		} else if (substr($line,0,4) == chr(1).'Via') {
			$prs->bottom .= $line."\r";
		} else if (substr($line,0,11) == ' * Origin: ') {
			$prs->origin = $line."\r";
		} else if (substr($line,0,3) == '---') {
			$prs->tearline = $line."\r";
		} 
		$line = false;
	}

	$prs->body = '';
	for($i=0,$s2=min($p,$s,$o,$v,$t,$lines_size);$i<$s2;$i++) {
		$prs->body .= $lines[$i]."\r";
	}
	
	if (!$net):
		if ($origin = $prs->origin):
			$rp = strrpos($origin,'(');
			$origin = substr($origin,$rp+1);
			$prs->FromAddr = JoinFidoAddr(GetFidoAddr($origin));
		endif;
		if (!$prs->FromAddr):
			if ($prs->msgid) $prs->FromAddr = JoinFidoAddr(GetFidoAddr($prs->msgid));
		endif;
		if (!$prs->FromAddr):
		$prs->FromAddr = $CONFIG->GetVar('address');
		endif;
		$prs->ToAddr = $CONFIG->GetVar('address');
	else:
		$orig = false;
		$dest = false;
		if ($prs->intl):
			list($dest,$orig) = explode(' ',$prs->intl);
			if ($prs->fmpt) { $orig.= '.'.$prs->fmpt; };
			if ($prs->topt) { $dest.= '.'.$prs->topt; };
		else:
			if ($prs->fmpt) { $orig = strtok($CONFIG->GetVar('address'),'.').'.'.$prs->fmpt; };
			if ($prs->topt) { $dest = strtok($CONFIG->GetVar('address'),'.').'.'.$prs->topt; };
		endif;
		if (!$orig):
			if ($prs->msgid) $orig = JoinFidoAddr(GetFidoAddr($prs->msgid));
		endif;
		if (!$orig):
			if ($prs->origin):
				$rp = strrpos($prs->origin,'(');
				$prs->origin = substr($prs->origin,$rp+1);
				$orig = JoinFidoAddr(GetFidoAddr($prs->origin));
			endif;
		endif;
		if ($orig === false) $orig = $CONFIG->GetVar('address');
		if ($dest === false) $dest = $CONFIG->GetVar('address');
		$prs->FromAddr = $orig;
		$prs->ToAddr = $dest;
	endif;
	if ($alt) {
		$CurrentMessageAlt = &$prs;
	} else {
		$CurrentMessageParsed = &$prs;
	}
}

function AreaToCase($area,$type)
{
	if (strcasecmp($type,'lower')==0):
		return strtolower($area);
	elseif (strcasecmp($type,'upper')==0):
		return strtoupper($area);
	else:
		$l = strlen($area);
		$u = 1;
		for ($i=0;$i<$l;$i++):
			if (preg_match('/[a-z0-9]/i',$area{$i})):
				if ($u):
					$area{$i} = strtoupper($area{$i});
					$u = 0;
				else:
					$area{$i} = strtolower($area{$i});
				endif;
			else:
				$u = 1;
			endif;
		endfor;
		return $area;
	endif;
}

function NewMsgbaseName($msgbase, $area)
{
	GLOBAL $CONFIG;
	$basename = AreaToCase($area,$CONFIG->GetVar('areasfilenamecase'));
	$basename = preg_replace("/[^\_a-zA-Z0-9]/","_",$basename);
	return $msgbase . '/' . $basename;
}

function AutoCreateArea($type,$area,$link = false,$descr = false)
{
	GLOBAL $CONFIG, $Queue;
	if (($arrlinks = $Queue->GotEcho($area,$link))===false) return 0;
	if (strcmp($link,'local')==0) $link = false;
	if ($link):
		if (!$CONFIG->GetLinkVar($link,'autoareacreate')) { return 0; };
   		$tofile = $CONFIG->GetLinkVar($link,'autoareacreatefile');
   		$totable = $CONFIG->GetLinkVar($link,'autoareacreatetable');
   		$grp = $CONFIG->GetLinkVar($link,'autoareacreategroup');
		if (!$totable) $totable = $tofile;
		$options = $CONFIG->GetLinkVar($link,'autoareacreatedefaults');
	endif;
	if (!isset($tofile) || $tofile === '') 
		$tofile = $CONFIG->GetVar('autoareacreatefile');
	if (!isset($totable) || $totable === '') 
		$totable = $CONFIG->GetVar('autoareacreatetable');
	if ($totable === '') 
		$totable = $tofile;
	if (!isset($options) || $options === '') 
		$options = $CONFIG->GetVar('autoareacreatedefaults');
	if (!isset($grp) || $grp === '') 
		$grp = $CONFIG->GetVar('autoareacreategroup');
	if (($tofile === '') || !is_writable($tofile)) return 0;
	if (!is_writable($totable)) return 0;
	$echotag = AreaToCase($area,$CONFIG->GetVar('createareascase'));
	$path = NewMsgbaseName($CONFIG->GetVar('msgbase'), $area);
	$line = $type.' '.$echotag.' '.$path;
	if ($descr === false) $descr = GetEchoDescrFromEcholist($area);
	if (($descr !== '') && ($descr !== false)) { 
		$line.= " -d \"$descr\""; 
	}

	foreach($CONFIG->GetArray('creategroupmask') as $grp_mask) {
		$arr = explode(' ',$grp_mask,2);
		if (sizeof($arr)==2):
			list($grp,$mask) = $arr;
			$grp = trim($grp);
			$mask = trim($mask);
			if (is_match_mask($mask,$echotag)):
				break;
			endif;
		endif;
	}
	if ($grp !== '') $line.= ' -g '.$grp;

	$line.= ' '.$options;
	$tline = 'EchoMode '.$echotag;

	$linkarr = ($arrlinks?$arrlinks:array());
	if ($link) array_unshift($arrlinks,$link);
	$pt = 0;
	for($i=sizeof($arrlinks)-1;$i>=0;$i--) {
		if ($grp==='' || !$CONFIG->Areas->Groups[$grp]->IssetAttr($link,'l')) {
				$arrlinks[$i].= ':l';
				$tline .= ' '.$arrlinks[$i];
				$pt = 1;
		}
	}

	if (($linenum = AppendLine($tofile,$line))===false) return 0;
	if ($pt && (($linenum = AppendLine($totable,$tline))===false)) return 0;
	$CONFIG->Reload(array($tofile,$totable));
	ReportNewAreas($echotag,$descr,$link);
	return 1;
}

function PackToLink($pkt,$addr)
{
	GLOBAL $CONFIG, $Outbound;
	tolog('K',"Packing $pkt to $addr");
	$OutMail = $Outbound->GetMailTo($addr);
	$defsize = $CONFIG->GetLinkVar($addr,'defarcmailsize');
	if (!$defsize) $defsize = $CONFIG->GetVar('defarcmailsize');
	if ($defsize) { $defsize = $defsize * 1024; } else { $defsize = 524288; };
	foreach($OutMail as $fname):
		if (preg_match('/^[0-9a-z]{8}.(su|mo|tu|we|th|fr|sa)[0-9a-z]$/i',basename($fname)) && (filesize($fname)<$defsize)):
	   		$ArchName = $fname; 
			break;
		endif;
	endforeach;
	list($path,$file) = $Outbound->get_link_filename($addr);
	pc_mkdir_parents($path,0777);
	if (!isset($ArchName)) { 
		$LoName = "$path/$file.flo";
		$ArchName = get_uniq_name($path,'.'.strtolower(substr(date('D'),0,2)).'0');
		$loshka = fopen($LoName,'a');
		if (!$outbase = $CONFIG->GetLinkVar($addr,'outbundlebase'))
			$outbase = $CONFIG->GetVar('outbundlebase');
		$what = '';
		if (strcmp($outbase,'full')==0):
			$what = $ArchName;
			$ss = $ArchName{0};
			if (($ss != "/") && ($ss != "\\") && (strpos($what,':')==false)) { 
				$what = getcwd().'/'.$ArchName; 
			};
		elseif (strcmp($outbase,'current')==0):
			$what = $ArchName;
		else:
			$what = basename($ArchName);
		endif;
		if (!$outact = $CONFIG->GetLinkVar($addr,'outbundleact'))
			$outact = $CONFIG->GetVar('outbundleact');
		if (strcmp($outact,'keep')==0):
			$act = '';
		elseif (strcmp($outact,'trunc')==0):
			$act = '#';
		else:
			$act = '^';
		endif;
		fwrite($loshka,$act.$what."\n");
		fclose($loshka);
		chmod($LoName,0666);
	}
#	AddToArchivePck($ArchName,$pkt,$CONFIG->GetVar('tempoutbound'),$CONFIG->GetLinkVar($addr));
	AddToArchive($ArchName,$pkt,$CONFIG->GetVar('tempoutbound'));
	unlink($pkt);
	tolog('k',"Finished. Deleting $pkt");
}

function CheckSecure($packet)
{
	GLOBAL $CONFIG;
	$fz = $packet->pkt_hat->OrigZone;
	$fn = $packet->pkt_hat->OrigNet;
	$ff = $packet->pkt_hat->OrigNode;
	$fp = $packet->pkt_hat->OrigPoint;
	$dz = $packet->pkt_hat->DestZone;
	$dn = $packet->pkt_hat->DestNet;
	$df = $packet->pkt_hat->DestNode;
	$dp = $packet->pkt_hat->DestPoint;
	$fromaddr = $fz.':'.$fn.'/'.$ff.($fp?'.'.$fp:'');
	$toaddr = $dz.':'.$dn.'/'.$df.($dp?'.'.$dp:'');
	if (!in_array($toaddr,$CONFIG->GetArray('address'))) return -2;
	$pwd = $packet->pkt_hat->Password;
	if (!isset($CONFIG->Links->Vals[$fromaddr])) return 0;
	$linkpwd = $CONFIG->GetLinkVar($fromaddr,'password');
	if ($pwd === '') {
		if ($CONFIG->GetLinkVar($fromaddr,'allowemptypktpwd')):
			return 1;
		else:
			return -3;
		endif;
	} else {
		if (strcasecmp($linkpwd,$pwd)==0):
			return 1;
		else:
			return -3;
		endif;
	}
	return 1;
}

function Toss_Pkt($name,$local=false)
{
	GLOBAL $CurrentMessage, $CurrentMessageInfo;
	$result = true;
	tolog('T',"Tossing file $name");
	$packet = new FidoPacket;
	if ($packet->Open($name)):
		$sec = ($local?1:CheckSecure($packet));
		if ($sec >= 0):
			$sav = ($sec == 0)?1:0;
			if ($local):
				$from_pkt = 'local';
			else:
				$from_pkt = JoinFidoAddr(array(
					$packet->pkt_hat->OrigZone,
					$packet->pkt_hat->OrigNet,
					$packet->pkt_hat->OrigNode,
					$packet->pkt_hat->OrigPoint
				));
			endif;
			$CurrentMessageInfo->from_link = $from_pkt;
			$CurrentMessageInfo->pkt_name = $name;
			$CurrentMessage = $packet->ReadMessage();
			while ($CurrentMessage->Version != 0) {
				$res = Process_message_Ptosser($from_pkt,$sec==0);
				if ($res <= 0) {
					$st = 1-$res;
					if ($sav == 0 || $sav > $st) {
						$sav = $st;
					}
				} 
				$CurrentMessage = $packet->ReadMessage();
			}
			$packet->Close();
			$CurrentMessageInfo->from_link = false;
			$CurrentMessageInfo->pkt_name = false;
			switch ($sav):	
				case 4:
					tolog('T','Dupes detected - saving pkt');
					rename($name,$name.'.dup');
					$result = false;
					break;
				case 3:
					tolog('T','Bads detected - saving pkt');
					rename($name,$name.'.bad');
					$result = false;
					break;
				case 1:
					tolog('T','Problems during tossing');
					rename($name,$name.'.sav');
					$result = false;
					break;
				case 0:
					tolog('t','Tossing finished');
					unlink($name);
					break;
				default:
					tolog('T','Unknown error');
					rename($name,$name.'.ier');
					$result = false;
			endswitch;
		else:
			$packet->Close();
			switch($sec):
				case(-1):
					tolog('T',"Error tossing: Security violation");
					rename($name,$name.'.sec');
					break;
				case(-2):
					tolog('T',"Error tossing: Not to us");
					rename($name,$name.'.ntu');
					break;
				case(-3):
					tolog('T',"Error tossing: Password error");
					rename($name,$name.'.sec');
					break;
				default:
					tolog('T','Unknown error');
					rename($name,$name.'.ier');
			endswitch;
			$result = false;
		endif;
	else:
		tolog('T','Error during opening packet');
		rename($name,$name.'.err');
		$result = false;
	endif;
	return $result;
}

function Begin_Pack()
{
	GLOBAL $OutPkts, $OutPktNames, $Packing;
	if (!isset($Packing) || !$Packing):
		$OutPkts = array();
		$OutPktNames = array();
		$Packing = true;
		return true;
	else:
		return false;
	endif;
}

function PackMessageLocal($message,$topkt = false)
{
	GLOBAL $CONFIG;
	GLOBAL $LocalPkt;
	$localin = $CONFIG->GetVar('localinbound', '.');
	if (!isset($LocalPkt) || !$LocalPkt):
		$pktname = get_uniq_name($localin, $topkt?'.pkt':'.tpk');
		$LocalPkt[0] = $pktname;
		$LocalPkt[1] = new FidoPacket;
		$LocalPkt[1]->NewPacket($pktname,$CONFIG->GetOurAkaFor($a=$CONFIG->GetVar('address')),$a,'');
	endif;
	$LocalPkt[1]->message = $message;
	$LocalPkt[1]->AppendMessage();
	return 1;
}

function PackMessageTo($message,$packto)
{
	GLOBAL $CONFIG, $OutPkts, $OutPktNames, $Stats;
	@$Stats->Links[$packto][1]++;
	@$Stats->Links[$packto][3] += strlen($message->MsgText);
	if (!isset($OutPkts[$packto])):
		$pktname = get_uniq_name($CONFIG->GetVar('tempoutbound'),'.pkt');
		$OutPktNames[$packto] = $pktname;
		$OutPkts[$packto] = new FidoPacket;
		$pwd = $CONFIG->GetLinkVar($packto,'password');
		$OutPkts[$packto]->NewPacket($pktname,$CONFIG->GetOurAkaFor($packto),$packto,$pwd);
	endif;
	tolog('k',"Packing message for $packto");
	$OutPkts[$packto]->message = $message;
	$OutPkts[$packto]->AppendMessage();
	return 1;
}

function LocalToss()
{
	GLOBAL $LocalPkt;
	ReportNewAreas();
	while(isset($LocalPkt) && $LocalPkt):
		$name = $LocalPkt[0];
		$pkt = $LocalPkt[1];
		$pkt->FinishPacket();
		$LocalPkt = false;
		Toss_Pkt($name,1);
		ReportNewAreas();
	endwhile;
}

function End_Pack()
{
	GLOBAL $OutPkts;
	GLOBAL $OutPktNames;
	GLOBAL $Packing;
	if ($Packing):
		$Packing = false;
		if ($OutPkts):
			$links = array_keys($OutPkts);
			foreach($links as $addr):
				$OutPkts[$addr]->FinishPacket();
				PackToLink($OutPktNames[$addr],$addr);
			endforeach;
		endif;
	endif;
}

function Process_message_Ptosser($addr,$onlynetmail = false,$update_stats = true)
{
	GLOBAL $CONFIG, $CurrentMessage, $CurrentMessageParsed, $CurrentMessageInfo, $Stats, $CurrentMessageInfo, $Process_message_Ptosser_flags;
	$islocal = false;
	if (strtok($CurrentMessage->MsgText,"\r") == 'ISLOCAL'):
		$islocal = true;
		$CurrentMessage->MsgText = substr($CurrentMessage->MsgText,8);
	endif;
	$is_netmail = strcasecmp(substr($CurrentMessage->MsgText,0,5),'area:')!=0;
	ParseMessage($is_netmail);
	$shaddr = ($addr == 'local')?$CONFIG->GetVar("address"):$addr;
	if ($update_stats) {
		@$Stats->Links[$shaddr][0]++;
		@$Stats->Links[$shaddr][2] += strlen($CurrentMessage->MsgText);
		AddToFtnDns($CurrentMessage->FromUser,$CurrentMessageParsed->FromAddr);
	}
	$Process_message_Ptosser_flags = array();
	if ($is_netmail) $Process_message_Ptosser_flags[] = 'Netmail';
	if ($islocal) $Process_message_Ptosser_flags[] = 'Local';
	modules_hook('message',array($addr));
	$nostore = $islocal || ($Process_message_Ptosser_flags & MPF_NOSTORE);
	$nobadck = $Process_message_Ptosser_flags & MPF_NOBADCK;
	if ($CurrentMessage===false):
		return 1;
	elseif (!$is_netmail):
		$p = strpos($CurrentMessage->MsgText,"\r");
		$area = trim(substr($CurrentMessage->MsgText,5,$p-5));
		$CurrentMessageInfo->area = $area;
		$CurrentMessage->MsgText = substr($CurrentMessage->MsgText,$p+1);
		if ($onlynetmail):
			return 0;
		else:
			$CurrentMessageInfo->state = 'error';
			$CurrentMessageInfo->packed_to = array();
			$res = Process_echomail_message($addr,$area,$nostore,$nobadck);
			modules_hook('msglog',array($addr,$area,$res));
			return $res;
		endif;
	else:
		$CurrentMessageInfo->area = '';
		$res = Process_netmail_message($nostore);
		modules_hook('msglog',array($addr,false,$res));
		return $res;
	endif;
}

function Tosser_init()
{
	GLOBAL $Queue, $FtnDns, $CONFIG, $Stats, $CurrentMessageInfo;
	modules_init();
	SQL_start();
	$Stats = new C_Statistics;
	$Stats->Init($CONFIG->GetVar('statisticsdir'),$CONFIG->GetArray('areastossed'));
	$CurrentMessageInfo = new C_CurrentMessage_info;
	Begin_Pack();
	$Queue = new C_queue;
	$Queue->Init();
	if ($CONFIG->GetVar('ftndnsbase')) {
		$FtnDns = new C_FtnDns();
		$FtnDns->Load();
	} else {
		$FtnDns = false;
	}
}

function Tosser_done()
{
	GLOBAL $CurrentMessage, $CurrentMessageParsed, $LocalPkt, $OutPkts;
	GLOBAL $OutPktNames, $Queue, $FtnDns, $Stats, $CurrentMessageInfo;
	$Queue->Process();
	$Queue->Done();
	LocalToss();
	End_Pack();
	if ($FtnDns !== false) {
		$FtnDns->Save();
		$FtnDns->Unload();
	}
	$Stats->Done();
	$CurrentMessage = $CurrentMessageParsed = $CurrentMessageInfo = 
		$LocalPkt = $OutPkts = $OutPktNames = $FtnDns = $Stats = false;
	SQL_stop();
	modules_done();
}

function Run_tosser()
{
	GLOBAL $CONFIG, $LocalPkt;
	$inbound = $CONFIG->GetVar('inbound');
	$tempin = $CONFIG->GetVar('tempinbound');
	$localin = $CONFIG->GetVar('localinbound');
	$dirs = ExtractBundles($inbound,$tempin);
	if ($dir = opendir($inbound)) {
		while ($fname = readdir($dir))
		{ 
			if (preg_match('/\.pkt$/i',$fname)):
				Toss_Pkt("$inbound/$fname"); 
			endif;
		}
		closedir($dir);
	}
	foreach($dirs as $dn) {
		$falt = 0;
		if ($dir = opendir($dn)) {
			while ($fname = readdir($dir)) { 
				if (preg_match('/\.pkt$/i',$fname)) {
					if (!Toss_Pkt("$dn/$fname")) {
						$falt = 1;
					}
				} else if ($fname{0} != '.') {
					$falt = 1;
				}
			}
			closedir($dir);
			if (!$falt) {
				@rmdir($dn);
			}
		}
	}
	if ($dir = opendir($localin)) {
		while ($fname = readdir($dir))
		{ 
			if (preg_match('/\.pkt$/i',$fname)):
				Toss_Pkt("$localin/$fname",1);
			endif;
		}
		closedir($dir);
	}
}

function ScanMessage($area,$msg)
{
	GLOBAL $CONFIG;
	tolog('S',"Scanning area $area");
	if (($areanum = $CONFIG->Areas->FindAreaNum($area))==-1):
		tolog('T',"Area $area not found");
		return 0;
	else:
		$netarea = (strcmp($CONFIG->Areas->Vals[$areanum]->Status,'netmailarea')==0);
	endif;
	
	$scn = 0;
	$Area = new C_msgbase;
	if ($Area->Open($area)):
		$header = $Area->ReadMsgHeader($msg);
		$attr = $header->Attrs;
		if ($netarea):
			$scn = ScanNetMessage($area,$msg,$Area,$header);
		else:
			$scn = ScanEchoMessage($area,$msg,$Area,$header);
		endif;
		if ($scn) $Area->SetAttr($msg,$attr | MSG_SNT | MSG_SCN);
		$Area->Close();
	endif;
	return $scn;
}