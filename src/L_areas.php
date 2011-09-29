<?php
/*\
  /  This is a part of PhFiTo (aka PHP Fido Tosser)
 //  Copyright (c) Alex Kocharin, 2:50/13
///
///  This program is distributed under GNU GPL v2
///  See docs/license for details
//
/    $Id: L_areas.php,v 1.9 2011/01/09 08:53:12 kocharin Exp $
\*/

if (!function_exists('xinclude')) die();

xinclude('P_phfito.php');

function PurgeAreasParam($arg)
{
	if (sizeof($arg) == 0) {
		PurgeAllAreas();
	} else {
		foreach($arg as $param) {
			if (strlen($param) && ($param{0} == '@')) {
				PurgeListedAreas($file = substr($param,1));
				unlink($file);
			} else {
				PurgeAreasMask($param);
			}
		}
	}
}

function ScanAreasParam($arg)
{
	if (sizeof($arg) == 0) {
		ScanAllAreas();
	} else {
		foreach($arg as $param) {
			if (strlen($param) && ($param{0} == '@')) {
				ScanListedAreas($file = substr($param,1));
				unlink($file);
			} else {
				ScanAreasMask($param);
			}
		}
	}
}

function RescanAreasParam($arg)
{
	foreach($arg as $param) {
		list($link, $areas, $msgs) = explode(',', $param);
		if (strlen($areas) && ($areas{0} == '@')) {
			RescanListedAreas($link, $file = substr($areas, 1), $msgs);
			unlink($file);
		} else {
			RescanAreasMask($link, $areas, $msgs);
		}
	}
}

function PurgeArea($area)
{
	GLOBAL $CONFIG;
	if (($areanum = $CONFIG->Areas->FindAreaNum($area))==-1) {
		tolog('E',"Purger: Area $area not found");
		return false;
	}
	$Area =& $CONFIG->Areas->Vals[$areanum];
	$maxmsgs = isset($Area->Options['-msgs'])?
		$Area->Options['-msgs']: 0x7fffffff;
	if ($maxmsgs == 0) $maxmsgs = 0x7fffffff;
	$maxage = isset($Area->Options['-age'])?
		$Area->Options['-age']: 0;
	$maxage *= 24*3600;
	if ($maxage == 0) $maxage = 0x7fffffff;
	tolog('2',"Purging $area.");
	$num = 0;
	$base = new C_msgbase;
	if ($base->Open($area)) {
		$ms = $base->GetNumMsgs();
		if ($ms > $maxmsgs) {
			while ($ms-- > $maxmsgs) {
				$base->DeleteMsg(1);
				$num++;
			}
		}
		$cdate = time();
		while ($ms-- > 0) {
			$header = $base->ReadMsgHeader(1);
			$date = $header->ADate;
			if ($date == 0) {
				$date = $header->WDate;
			}
			if ($date == 0) {
				break;
			}
			if (abs($cdate - $date) > $maxage) {
				$base->DeleteMsg(1);
				$num++;
			} else {
				break;
			}
		}
		if ($num > 0) {
			tolog('2',"$num msgs has been deleted. Packing base.");
			$base->PurgeBase();
		} else {
			tolog('2',"No msgs has been deleted. Packing is not necessary.");
		}
		$base->Close();
	} else {
		tolog('E',"Could not open area $area");
	}
}

function PurgeAreasMask($mask)
{
	GLOBAL $CONFIG;
	if (isset($CONFIG->Areas->Vals)) {
		foreach ($CONFIG->Areas->Vals as $area) {
			if (is_match_mask($mask, $area->EchoTag)) {
				PurgeArea($area->EchoTag);
			}
		}
	}
}

function PurgeListedAreas($file)
{
	GLOBAL $CONFIG;
	if ($f = fopen($file,'r')):
		while($line = fgets($f,1024)):
			$area = trim($line);
			if ($area):
				if (($areanum = $CONFIG->Areas->FindAreaNum($area))==-1):
					tolog('T',"Area $area not found");
				else:
					PurgeArea($area);
				endif;
			endif;
		endwhile;
		fclose($f);
	endif;
}

function ScanArea($area,$netarea)
{
	tolog('S',"Scanning area $area");
	$Area = new C_msgbase;
	if ($Area->Open($area)):
		if (($arr = $Area->AllScan())!==false) {
			foreach($arr as $num) {
				$header = $Area->ReadMsgHeader($num,1);
				$attr = $header->Attrs;
				if ($netarea):
					$scn = ScanNetMessage($area,$num,$Area,$header,1);
				else:
					$scn = ScanEchoMessage($area,$num,$Area,$header,1);
				endif;
				if ($scn) $Area->SetAttr($num,$attr | MSG_SNT | MSG_SCN,1);
			}
		} else {
			$ms = $Area->GetNumMsgs();
			for ($i=1;$i<=$ms;$i++):
				$header = $Area->ReadMsgHeader($i);
				$attr = $header->Attrs;
				if (($attr & MSG_LOC) && 
						!($attr & MSG_SNT) && 
						!($attr & MSG_LOK)):
					if ($netarea):
						$scn = ScanNetMessage($area,$i,$Area,$header);
					else:
						$scn = ScanEchoMessage($area,$i,$Area,$header);
					endif;
					if ($scn) $Area->SetAttr($i,$attr | MSG_SNT | MSG_SCN);
				endif;
			endfor;
		}
		$Area->Close();
	endif;
}

function PurgeAllAreas()
{
	GLOBAL $CONFIG;
	if (isset($CONFIG->Areas->Vals)) {
		foreach ($CONFIG->Areas->Vals as $area) {
			PurgeArea($area->EchoTag);
		}
	}
}

function ScanAllAreas()
{
	GLOBAL $CONFIG;
	if (isset($CONFIG->Areas->Vals)) {
		foreach ($CONFIG->Areas->Vals as $area) {
			ScanArea($area->EchoTag,strcmp($area->Status,'netmailarea')==0);
		}
	}
}

function ScanAreasMask($mask)
{
	GLOBAL $CONFIG;
	if (isset($CONFIG->Areas->Vals)) {
		foreach ($CONFIG->Areas->Vals as $area) {
			if (is_match_mask($mask, $area->EchoTag)) {
				ScanArea($area->EchoTag,strcmp($area->Status,'netmailarea')==0);
			}
		}
	}
}

function ScanListedAreas($file)
{
	GLOBAL $CONFIG;
	if ($f = fopen($file,'r')):
		while($line = fgets($f,1024)):
			$area = trim($line);
			if ($area):
				if (($areanum = $CONFIG->Areas->FindAreaNum($area))==-1):
					tolog('T',"Area $area not found");
				else:
					ScanArea($area,strcmp($CONFIG->Areas->Vals[$areanum]->Status,'netmailarea')==0);
				endif;
			endif;
		endwhile;
		fclose($f);
	endif;
}

function RescanAreasMask($link, $mask, $msgs)
{
	GLOBAL $CONFIG;
	if (isset($CONFIG->Areas->Vals)) {
		foreach ($CONFIG->Areas->Vals as $area) {
			if ($area->Status == 'echoarea') {
				if ($area->IssetAttr($link,'r')) {
					if (is_match_mask($mask, $area->EchoTag)) {
						RescanArea($link, $area->EchoTag, $msgs, true);
					}
				}
			}
		}
	}
}

function RescanListedAreas($link, $file, $msgs)
{
	GLOBAL $CONFIG;
	if ($f = fopen($file,'r')):
		while($line = fgets($f,1024)):
			$area = trim($line);
			if ($area):
				if (($areanum = $CONFIG->Areas->FindAreaNum($area))==-1):
					tolog('T',"Area $area not found");
				else:
					RescanArea($link, $area, $msgs, true);
				endif;
			endif;
		endwhile;
		fclose($f);
	endif;
}

function RescanEchoMessage($forlink,$area,$msg,$Area = false,$header = false,$addresd = true)
{
	GLOBAL $CONFIG;
	$openb = $Area === false;
	$res = 1;
	$areanum = $CONFIG->Areas->FindAreaNum($area);
	if ($openb):
		$Area = new C_msgbase;
		$Area->Open($area);
	endif;
	if ($header === false) $header = $Area->ReadMsgHeader($msg);
	$MessageText = $Area->ReadMsgBody($msg);
	$p = strlen($MessageText);
	while(strcmp($MessageText{$p-1},"\r")==0) $p--;
	while(strcmp($MessageText{$p-1},chr(0))==0) $p--;
	while(strcmp($MessageText{$p-1},"\r")==0) $p--;
	$MessageText = substr($MessageText,0,$p)."\r";
	if ($MessageText{0} == "\r") $MessageText = substr($MessageText,1);
	$MessageText = "AREA: ".strtoupper($area)."\r".
		($addresd?chr(1).'RESCANNED: '.$CONFIG->GetOurAkaFor($forlink)."\r":'').$MessageText;
	if ($header->FromAddr && $header->ToAddr):
		$FromAddr = $header->FromAddr;
		$ToAddr = $header->ToAddr;
	else:
		list($FromAddr,$ToAddr) = GetEchoMsgOrigDest($MessageText);
	endif;
	preg_match('/:([0-9]+)\/([0-9]+)/',$FromAddr,$m);
	preg_match('/:([0-9]+)\/([0-9]+)/',$ToAddr,$t);
	$Omessage = new Pkt_message;
	$Omessage->Version = 2;
	$Omessage->OrigNode = $m[2];
	$Omessage->DestNode = $t[2];
	$Omessage->OrigNet = $m[1];
	$Omessage->DestNet = $t[1];
	$Omessage->Attr = ZeroNFlags(Flag_to_FTS($header->Attrs));
	$Omessage->Cost = 0;
	$Omessage->Date = $header->WDate;
	$Omessage->ToUser = $header->To;
	$Omessage->FromUser = $header->From;
	$Omessage->Subject = $header->Subj;
	$Omessage->MsgText = $MessageText;
	$r = PackMessageTo($Omessage,$forlink);
	if ($res) $res = $r;
	if ($openb):
		$Area->Close();
	endif;
	return $res;
}

function RescanArea($link,$area,$msgs,$addresd = true)
{
	tolog('S',"Rescanning area $area for $link ($msgs msgs requested)");
	$Area = new C_msgbase;
	if ($Area->Open($area)):
		$ms = $Area->GetNumMsgs();
		$numscn = 0;
		$from = $ms-$msgs+1;
		if ($from<1) $from = 1;
		for ($i=$from;$i<=$ms;$i++):
			$header = $Area->ReadMsgHeader($i);
			$numscn += (RescanEchoMessage($link,$area,$i,$Area,$header,$addresd)?1:0);
		endfor;
		$Area->Close();
	else:
		return -1;
	endif;
	return $numscn;
}