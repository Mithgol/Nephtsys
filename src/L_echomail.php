<?
/*\
  /  This is a part of PhFiTo (aka PHP Fido Tosser)
 //  Copyright (c) Alex Kocharin, 2:50/13
///
///  This program is distributed under GNU GPL v2
///  See docs/license for details
//
/    $Id: L_echomail.php,v 1.20 2011/01/10 01:47:08 kocharin Exp $
\*/

if (!function_exists('xinclude')) die();

function DoEchoSNAT($to_source)
{
	GLOBAL $CONFIG, $CurrentMessage, $CurrentMessageParsed;
	$lines = explode("\r",$CurrentMessage->MsgText);
	for($i=$s=sizeof($lines)-1;$i>=0;$i--):
		if (!trim($lines[$i])):
		elseif (strcmp(substr($lines[$i],0,6),chr(1).'PATH:')==0):
		elseif (strcmp(substr($lines[$i],0,8),'SEEN-BY:')==0):
		elseif (strcmp(substr($lines[$i],0,10),' * Origin:')==0):
			$lines[$i] = substr($lines[$i],0,strrpos($lines[$i],'(')).'('.$to_source.')';
			$CurrentMessageParsed->origin = $lines[$i];
			break;
		else:
			for($ii=$s+1;$ii>$i+1;$ii--):
				$lines[$ii] = $lines[$ii-1];
			endfor;
			$lines[$i+1] = ' * Origin: <empty> ('.$to_source.')';
			$CurrentMessageParsed->origin = $lines[$i];
			break;
		endif;
	endfor;
	$CurrentMessage->MsgText = join("\r",$lines);
	$CurrentMessageParsed->FromAddr = $to_source;
	return 1;
}

function Sort_nodes($a,$b)
{
	list($n1,$f1) = explode('/',$a);
	list($n2,$f2) = explode('/',$b);
	$r = $n1 - $n2;
	if ($r == 0)
		$r = $f1 - $f2;
	return $r;
}

function DupeCheckOld($base, $area, $msgid, $crc, $date)
{
	if ((is_file($base.'/'.$area)) && ($f = fopen($base.'/'.$area,'r'))) {
		while(!feof($f)) {
			$nc = fread($f, 4);
			if ($nc === false || strlen($nc) == 0) break;
			$nc = unpack('V', $nc);
			if ($crc == $nc[1]) {
				fclose($f);
				return 1;
			}
		}
		fclose($f);
		return 0;
	} else {
		return 0;
	}
}

function DupeAddOld($base, $area, $msgid, $crc, $date)
{
	if ($f = @fopen($base.'/'.$area,'a')) {
		fwrite($f, pack("V", $crc));
		fclose($f);
		@chmod($base.'/'.$area,0666);
	} else {
		tolog('E', "Could not access dupebase $base/$area");
	}
}

function DupeCheckFiles($base, $area, $msgid, $crc, $date)
{
	if ((is_file($base.'/'.$area.'.crc')) && ($f = fopen($base.'/'.$area.'.crc','r'))) {
		while(!feof($f)) {
			$nc = fread($f, 8);
			if ($nc === false || strlen($nc) == 0) break;
			$nc = unpack('V2', $nc);
			if ($crc == $nc[1]) {
				if ($f2 = fopen($base.'/'.$area.'.mid','r')) {
					fseek($f2, $nc[2]);
					$f2date = unpack("V", fread($f2, 4));
					$f2mid = chop(fgets($f2));
					fclose($f2);
					if (abs($f2date[1] - $date) > 180*24*60*60) {
						// half of year
						tolog('E', "Duplicate msgid, but different dates: $msgid");
					} elseif ($f2mid == $msgid) {
						fclose($f);
						return 1;
					} else {
						tolog('E', "CRC collision: $f2mid vs $msgid");
					}
				}
			}
		}
		fclose($f);
		return 0;
	} else {
		return 0;
	}
}

function DupeAddFiles($base, $area, $msgid, $crc, $date)
{
	if ($f = @fopen($base.'/'.$area.'.crc', 'a')) {
		fwrite($f, pack("VV", $crc, @filesize($base.'/'.$area.'.mid')));
		fclose($f);
		@chmod($base.'/'.$area.'.crc', 0666);
	} else {
		tolog('E', "Could not access dupebase $base/$area.crc");
	}
	if ($f = @fopen($base.'/'.$area.'.mid', 'a')) {
		fwrite($f, pack("V", $date));
		fwrite($f, $msgid."\n");
		fclose($f);
		@chmod($base.'/'.$area.'.mid', 0666);
	} else {
		tolog('E', "Could not access dupebase $base/$area.mid");
	}
}

// set value to db = $store=true, get value from db = $store=false
function DupeSeek($area, $msgid, $date, $store) 
{
	GLOBAL $CONFIG;
	STATIC $dbase;
	$area = strtolower($area);
	if (!$msgid) return 0;

	$crc = crc32($msgid);
	// patch for 32bit systems
	if (0 > $crc) { $crc += 0x100000000; }

	if (!is_array($dbase)) {
		$dbase = array();
		if ($base = $CONFIG->GetVar('dupebase')) {
			list($type, $path) = explode(' ', $base, 2);
			$type = strtolower($type);
			if ($type != 'old' && $type != 'files' && $type != 'sql') {
				tolog('E', "Wrong dupebase type: $type, check your config!");
			} else {
				$dbase = array($type, $path);
			}
		}
	}

	if (!$dbase) {
		return 0;
	} elseif ($dbase[0] == 'old') {
		if ($store) {
			return DupeAddOld($dbase[1], $area, $msgid, $crc, $date);
		} else {
			return DupeCheckOld($dbase[1], $area, $msgid, $crc, $date);
		}
	} elseif ($dbase[0] == 'files') {
		if ($store) {
			return DupeAddFiles($dbase[1], $area, $msgid, $crc, $date);
		} else {
			return DupeCheckFiles($dbase[1], $area, $msgid, $crc, $date);
		}
	} elseif ($dbase[0] == 'sql') {
		return SQL_dupechk($dbase[1], $area, $msgid, $crc, $date, $store);
	} else {
		return 0;
	}
}

function join_to_80_wpp($array,$prefix)
{
	$strs='';
	$item = array_shift($array);
	$pritem = '';
	while ($item):
		$line = $prefix.$item;
		$pritem = $item;
		$item = '';
		while ($array):
			$item = array_shift($array);
			$shitem = $item;
			if (strtok($pritem,'/') == strtok($shitem,'/'))
				$shitem = substr($shitem,strpos($shitem,'/')+1);
			if (strlen($line.' '.$shitem)>=80):
				break;
			else:
				$line.= ' '.$shitem;
				$pritem = $item;
				$item = '';
			endif;
		endwhile;
		$strs.= $line."\r";
	endwhile;
	return $strs;
}

function AddSeenByAndPath($area,$links,$ouraka,$from,$to)
{
	GLOBAL $CurrentMessageParsed, $CONFIG;
	$path = $CurrentMessageParsed->path;
	$seenby = $CurrentMessageParsed->seenby;

	$seens = $seenby?(explode(' ',$seenby)):array();
	$paths = $path?(explode(' ',$path)):array();
	$szs = sizeof($seens);
	$szp = sizeof($paths);
	$net = 0;
	for ($i=0; $i<$szs; $i++):
		$p = strpos($seens[$i],'/');
		if ($p === false):
			$seens[$i] = $net.'/'.$seens[$i];
		else:
			$net = substr($seens[$i],0,$p);
		endif;
	endfor;
	$zg = false;
	$tozone = false;
	if (!$CONFIG->GetVar('nozonegating')):
		$zg = true;
		$zone = false;
		$tozone = false;
		if (($p = strpos($from,':'))!==false):
			$zone = substr($from,0,$p);
		elseif (($p = strpos($ouraka,':'))!==false):
			$zone = substr($ouraka,0,$p);
		endif;
		if (($p = strpos($to,':'))!==false):
			$tozone = substr($to,0,$p);
		endif;
		$strip = ($zone && $tozone && ($zone != $tozone));
		if ($strip) $seens = array();
	endif;
	list($z,$n,$f,$p) = GetFidoAddr($to);
	if ($p == 0):
		foreach ($seens as $seen):
			if (strcmp($seen,$n.'/'.$f)==0) return false;
		endforeach;
	endif;
	$l2 = $links;
	if (!in_array($ouraka,$l2)) $l2[] = $ouraka;
	foreach($l2 as $l):
		$zone = strtok($l,':');
		$net = strtok('/');
		$node = strtok('.');
		$point = strtok('@');
		if (!$point):
			if (!$tozone || ($zone == $tozone) || !$zg):
				if (!in_array($addr2d = $net.'/'.$node,$seens)):
					$seens[] = $addr2d;
				endif;
			endif;
		endif;
	endforeach;
	usort($seens,'Sort_nodes');

	for ($i=0; $i<$szp; $i++):
		$p = strpos($paths[$i],'/');
		if ($p === false):
			$paths[$i] = $net.'/'.$paths[$i];
		else:
			$net = substr($paths[$i],0,$p);
		endif;
	endfor;
	$zone = strtok($ouraka,':');
	$net = strtok('/');
	$node = strtok('.');
	$point = strtok('@');
	if (!$point):
		if ((sizeof($paths)==0) || (strcmp($paths[$szp-1],$addr2d = $net.'/'.$node)!=0)):
			$paths[] = $addr2d;
		endif;
	endif;

	$seenby = join_to_80_wpp($seens,'SEEN-BY: ');
	$path = join_to_80_wpp($paths,chr(1).'PATH: ');
	$resmsg = $CurrentMessageParsed->top .
		$CurrentMessageParsed->body .
		$CurrentMessageParsed->tearline .
		$CurrentMessageParsed->origin;
	$result = 'AREA:'.$area."\r".$resmsg.$seenby.$path;
	#die(str_replace("\r","\n",$result));
	return $result;
}

function Process_echomail_message($addr,$area,$nostore,$nobadck)
{
	GLOBAL $CONFIG, $CurrentMessage, $CurrentMessageParsed, $CurrentMessageInfo, $Queue, $Stats;
	ParseMessage(0);
	$size = strlen($CurrentMessageParsed->top) +
			strlen($CurrentMessageParsed->body) +
			strlen($CurrentMessageParsed->tearline) +
			strlen($CurrentMessageParsed->origin);
//	if (($ts = AddrNAT('echosnat',$CurrentMessageParsed->FromAddr))!==false)
//		EchoSNAT($ts);
	$dest = $CurrentMessageParsed->ToAddr;
	$msgid = $CurrentMessageParsed->msgid;
	if (($queue_kn = $Queue->KillMessage($area,$addr))==1) return 1;
	tolog('t',"New message in area $area");
	if (($areanum = $CONFIG->Areas->FindAreaNum($area))==-1):
		if (AutoCreateArea('EchoArea',$area,$addr)):
			tolog('T',"Created area $area");
		else:
			tolog('T',"Could not create area $area");
			$CurrentMessageInfo->state = 'noarea';
			return 0;
		endif;
	endif;
	
	if (isset($CONFIG->Areas->Vals)):
		$num = $CONFIG->Areas->FindAreaNum($area);
		if ($num == -1) return 0;
		if (($queue_kn == 0)||($CONFIG->Areas->Vals[$num]->IssetAttr($addr,'wl'))||(strcmp($addr,'local')==0)||($nobadck)):
			if (DupeSeek($area, $msgid, $CurrentMessage->Date, false)):
				$CurrentMessageInfo->state = 'dupe';
				tolog('t',"Dupe message in area $area from $addr");
				if (!$CONFIG->GetVar("dupekill")) {
					if ($CONFIG->Areas->Vals):
						foreach ($CONFIG->Areas->Vals as $Area):
							if (strcmp($Area->Status,'dupearea')==0):
								$areaname = $Area->EchoTag;
								break;
							endif;
						endforeach;
					endif;
					if (!isset($areaname)):
						$areaname = 'DupeArea';
						if (AutoCreateArea('DupeArea','DupeArea',false,'Autocreated dupe area')):
							tolog('T',"Created dupe area");
						else: 
							tolog('T',"Error creating dupe area");
							return ($CONFIG->GetVar("nodupesave"))?1:-3;
						endif;
					endif;
					SaveToArea($areaname,$area,$addr);
				}
				return ($CONFIG->GetVar("nodupesave"))?1:-3;
			endif;

			@$Stats->Areas[$area][0]++;
			@$Stats->Areas[$area][2] += $size;
			$our = 0;
			if ($addr == 'local') {
				$our = 1;
			} else {
				foreach($CONFIG->GetArray('address') as $a) {
					if ($a == $addr) {
						$our = 1;
						break;
					}
					$a .= '.';
					if ($a == substr($addr,0,strlen($a))) {
						$our = 1;
						break;
					}
				}
			}
			if ($our) {
				@$Stats->Areas[$area][1]++;
				@$Stats->Areas[$area][3] += $size;
			}
			$CurrentMessageInfo->state = 'ok';
			modules_hook('echomail',array($area,$addr,$our,$size));
			
			$echo_links = array();
			$fragm_links = array();
			$from_fragm = $CONFIG->Areas->Vals[$num]->GetProperty($addr, 'F');
			foreach (array_keys($CONFIG->Areas->Vals[$num]->Linkattr) as $packto) {
				if (strcmp($addr,$packto)!=0) {
					if ($CONFIG->Areas->Vals[$num]->IssetAttr($packto,'lr')) {
						if ($CONFIG->GetLinkVar($packto,'pause')):
							tolog('k',"Link $packto paused");
						else:
							$fragm = $CONFIG->Areas->Vals[$num]->GetProperty($packto, 'F');
							if (($from_fragm === false) || ($from_fragm !== $fragm)) {
								if ($fragm === false) {
									$echo_links[] = $packto;
								} else {
									$fragm_links[$fragm][] = $packto;
								}
							}
						endif;
					} else {
						tolog('k',"Link $packto doesn't have permissions to read");
					}
				}
			}

			foreach ($fragm_links as $fragm=>$array) {
				$num = sizeof($array);
				$echo_links[] = $array[($CONFIG->GetVar('packrandlink'))?(rand(0,$num-1)):0];
			}
		
			foreach ($echo_links as $packto) {
				$CurrentMessageInfo->packed_to[] = $packto;
				$Text = AddSeenByAndPath($area,$echo_links,$CONFIG->GetVar('address'),$addr,$packto);
				if ($Text) {
					tolog('k',"Packing message for $packto");
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
					$Omessage->MsgText = $Text;
					PackMessageTo($Omessage,$packto);
					tolog('k','Packing finished');
				} else {
					tolog('k',"Don't packing message for $packto - exists in seen-by's");
				}
			}
		else:
			$CurrentMessageInfo->state = 'bad';
			tolog('t','Bad message in '.$area.' from '.$addr);
			if (!$CONFIG->GetVar("badkill")) {
				if ($CONFIG->Areas->Vals):
					foreach ($CONFIG->Areas->Vals as $Area):
						if (strcmp($Area->Status,'badarea')==0):
							$areaname = $Area->EchoTag;
							break;
						endif;
					endforeach;
				endif;
				if (!isset($areaname)):
					$areaname = 'BadArea';
					if (AutoCreateArea('BadArea','BadArea',false,'Autocreated bad area')):
						tolog('T',"Created bad area");
					else: 
						tolog('T',"Error creating bad area");
						return ($CONFIG->GetVar("nobadsave"))?1:-2;
					endif;
				endif;
				SaveToArea($areaname,$area,$addr);
			}
			return ($CONFIG->GetVar("nobadsave"))?1:-2;
		endif;
	endif;

/*	$in_echoes = array($area);
	foreach($CONFIG->GetArray('carbon') as $line):
		$arr = explode(' ',$line,2);
		$toecho = trim($arr[0]);
		$arr = explode(' ',trim($arr[1]),2);
		$fromecho = trim($arr[0]);
		$rule = trim($arr[1]);
		$match = 0;
		foreach($in_echoes as $echo):
			if (is_match_mask($fromecho,$echo)):
				$match = 1;
				break;
			endif;
		endforeach;
		if ($match && Carbon_Match($rule) && !in_array($toecho,$in_echoes)):
			SaveToArea($toecho,$area);
			$in_echoes[] = $toecho;
		endif;
	endforeach;
*/
	if ($nostore || SaveToArea($area)):
		DupeSeek($area, $msgid, $CurrentMessage->Date, true);
		tolog('t','Tossing message finished');
		return 1;
	else:
		tolog('T',"Could not save message to area $area!");
		return 0;
	endif;
}

function SaveToArea($area,$origarea = false,$link = false)
{
	GLOBAL $CurrentMessage, $CurrentMessageParsed;
	$Area = new C_msgbase;
	if ($Area->Open($area,1)):
		modules_hook('store',array($area,$origarea,$link));
		$header = new C_msgheader;
		$header->Attrs = FTS_to_Flag(ZeroNFlags($CurrentMessage->Attr));
		$header->From = $CurrentMessage->FromUser;
		$header->To = $CurrentMessage->ToUser;
		$header->Subj = $CurrentMessage->Subject;
		$header->FromAddr = $CurrentMessageParsed->FromAddr;
		$header->ToAddr = 0;
		$header->WDate = $CurrentMessage->Date;
		$header->ADate = time();
		$Area->WriteMessage($header,($origarea===false?'':'AREA:'.strtoupper($origarea)."\r").(($link===false)?'':(chr(1).'PKTFROM: '.$link."\r")).$CurrentMessage->MsgText);
		$Area->Close();
		return 1;
	else:
		return 0;
	endif;
}

function ScanEchoMessage($area,$msg,$Area = false,$header = false,$ext = false)
{
	GLOBAL $CONFIG;
	$openb = $Area === false;
	$res = 1;
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
	$MessageText = "AREA:".strtoupper($area)."\r".chr(1).'TID: Phfito '.VERSION."\r".$MessageText;
	if ($header->FromAddr && $header->ToAddr):
		$FromAddr = $header->FromAddr;
		$ToAddr = $header->ToAddr;
	else:
		list($FromAddr,$ToAddr) = GetEchoMsgOrigDest($MessageText);
	endif;
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

?>
