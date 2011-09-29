<?php
/*\
  /  This is a part of PhFiTo (aka PHP Fido Tosser)
 //  Copyright (c) Alex Kocharin, 2:50/13
///
///  This program is distributed under GNU GPL v2
///  See docs/license for details
//
/    $Id: M_msgproc.php,v 1.9 2011/01/09 08:53:12 kocharin Exp $
\*/

if (!function_exists('xinclude')) die();

class M_msg_processor
{
	var $Procs;

	function init()
	{
		GLOBAL $CONFIG;
		if (!$this->Procs) {
			$cnt = 0;
			foreach($CONFIG->GetArray('script') as $s) {
				$this->Procs[$cnt] = new C_msg_processor;
				$this->Procs[$cnt]->LoadFile($s);
				$cnt++;
			}
		}
	}

	function hook_message($params)
	{
		GLOBAL $Process_message_Ptosser_flags;
		$res = 0;
		for($i=0,$s=sizeof($this->Procs);$i<$s;$i++) {
			$this->Procs[$i]->Flags = $Process_message_Ptosser_flags;
			$this->Procs[$i]->PktFrom = $params[0];
			$r = $this->Procs[$i]->ExecSub('main');
			$res |= $r;
		}
		$Process_message_Ptosser_flags = $res;
	}
}

class C_msg_processor
{
	var $Subs;
	var $Flags;
	var $modf_flags;
	var $SubDef;
	var $PktFrom;
	
	function ExecSub($name,$params = array())
	{
		GLOBAL $CurrentMessageParsed;
		$this->modf_flags = 0;
		$r = 0;
		$pn = isset($this->SubDef[$name])?$this->SubDef[$name]:array();
		$size = sizeof($this->Subs[$name]);
		$lastif = true;
		for($i=0;$i<$size;$i++):
			list($key,$value) = $this->Subs[$name][$i];
			for($i2=0,$s1=sizeof($pn); $i2<$s1; $i2++) {
				$value = str_replace($pn[$i2],isset($params[$i2])?$params[$i2]:'',$value);
			}
			switch($key):
				case(0):
					$r = $this->SetFlag($value);
					break;
				case(1):
					$r = $this->Carbon($value);
					break;
				case(2):
					$r = $this->LogMsg($value);
					break;
				case(3):
					$r = $this->NAT(1,$value);
					break;
				case(4):
					$r = $this->NAT(0,$value);
					break;
				case(5):
					$r = $this->Drop($value);
					break;
				case(6):
					$r = $this->Modify(0,$value);
					break;
				case(7):
					$r = $this->Modify(1,$value);
					break;
				case(8):
					$r = $this->Modify(2,$value);
					break;
				case(9):
					$r = $this->SetArea($value);
					break;
				case(10):
					$r = $this->SetKludge($value,false);
					break;
				case(11):
					$r = $this->SetKludge($value,true);
					break;
				case(12):
					$r = $this->NoStore($value);
					break;
				case(13):
					while (($z = strpos("  ",$value)) !== false) $value = str_replace("  "," ",$value);
					$p = strpos($value,' ');
					if ($p === false) $p = strlen($value);
					$r = $this->ExecSub(substr($value,0,$p),explode(' ',substr($value,$p+1)));
					break;
				case(14):
					$r = $this->Answer($value);
					break;
				case(15):
					if ($this->Ifflag($value)) { break 2; }
					break;
				case(16):
					$r = $this->NoBadCk($value);
					break;
				case(17):
					list($num,$v) = explode(' ',$value,2);
					$lastif = $this->Rule_Match($v);
					if ($lastif) {
						$r = $this->ExecSub('#'.$num,array());
					}
					break;
				case(18):
					list($num,$v) = explode(' ',$value,2);
					$lastif = $this->Ifflag($v);
					if ($lastif) {
						$r = $this->ExecSub('#'.$num,array());
					}
					break;
				case(19):
					if (!$lastif) {
						$r = $this->ExecSub('#'.$value,array());
					}
					break;
				case(20):
					$arr = explode(' ',$value,2);
					tolog($arr[0],$arr[1]);
					break;
			endswitch;
			if ($r) ParseMessage(!$CurrentMessageParsed->area);
		endfor;
		if ($r) $this->modf_flags |= MPF_MODIFIED;
		return $this->modf_flags;
	}

	function LoadFile($filename)
	{
		$currsub = array('main');
		$this->Subs = array();
		$reader = new C_configfile_reader($filename);
		$ifnum = 0;
		while (($pair = $reader->ReadPair())!==false)
		{
			list($key,$value) = $pair;
			$int = -1;
			switch($key):
				case('set'):
					$int = 0;
					break;
				case('carbon'):
					$int = 1;
					break;
				case('log'):
					$int = 2;
					break;
				case('snat'):
					$int = 3;
					break;
				case('dnat'):
					$int = 4;
					break;
				case('drop'):
					$int = 5;
					break;
				case('setfrom'):
					$int = 6;
					break;
				case('setto'):
					$int = 7;
					break;
				case('setsubj'):
					$int = 8;
					break;
				case('setarea'):
					$int = 9;
					break;
				case('setkludge'):
					$int = 10;
					break;
				case('delkludge'):
					$int = 11;
					break;
				case('nostore'):
					$int = 12;
					break;
				case('sub'):
					array_unshift($currsub,strtok($value,' '));
					do {
						$param = strtok(' ');
						if (!$param) break;
						$this->SubDef[$currsub[0]][] = $param;
					} while(1);
					break;
				case('endsub'):
					array_shift($currsub);
					break;
				case('exec'):
					$int = 13;
					break;
				case('answer'):
					$int = 14;
					break;
				case('exit'):
					$int = 15;
					break;
				case('nobadck'):
					$int = 16;
					break;
				case('brif'):
					$value = $ifnum.' '.$value;
					$this->Subs[$currsub[0]][] = array(17,$value);
					array_unshift($currsub,'#'.$ifnum);
					$int = -1;
					$ifnum++;
					break;
				case('brifflag'):
					$value = $ifnum.' '.$value;
					$this->Subs[$currsub[0]][] = array(18,$value);
					array_unshift($currsub,'#'.$ifnum);
					$ifnum++;
					break;
				case('brelse'):
					array_shift($currsub);
					$this->Subs[$currsub[0]][] = array(19,$ifnum);
					array_unshift($currsub,'#'.$ifnum);
					$ifnum++;
					break;
				case('brend'):
					array_shift($currsub);
					break;
				case('tolog'):
					$int = 20;
					break;
			endswitch;
			if ($int >= 0):
				$this->Subs[$currsub[0]][] = array($int,$value);
			endif;
		}
		$reader->Close();
		unset($reader);
	}

	function SetFlag($line)
	{
		$arr = explode(' ',$line,2);
		$toflag = trim($arr[0]);
		$arr = explode(' ',trim($arr[1]),2);
		$ifflag = trim($arr[0]);
		$rule = trim($arr[1]);
		if ($this->Ifflag($ifflag) && $this->Rule_Match($rule)):
			$evals = explode(',',$toflag);
			foreach($evals as $e):
				if ($e{0}=='!'):
					$e = substr($e,1);
					for($i=$s=sizeof($this->Flags)-1;$i>=0;$i--):
						if ($this->Flags[$i] == $e):
							for($ii=$s-1;$ii>=$i;$ii--) $this->Flags[$i] = $this->Flags[$i+1];
							array_pop($this->Flags);
							break;
						endif;
					endfor;
				elseif ($e{0}=='^'):
					$xor = 1;
					$e = substr($e,1);
					if (in_array($e,$this->Flags)):
						for($i=$s=sizeof($this->Flags);$i>=0;$i--):
							if ($this->Flags[$i] == $e):
								for($ii=$s-1;$ii>=$i;$ii--) $this->Flags[$i] = $this->Flags[$i+1];
								array_pop($this->Flags);
								break;
							endif;
						endfor;
					else:
						$this->Flags[] = $e;
					endif;
				else:
					if (!in_array($e,$this->Flags)):
						$this->Flags[] = $e;
					endif;
				endif;
			endforeach;
		endif;
	}

	function Carbon($line)
	{
		GLOBAL $CurrentMessage;
		$arr = explode(' ',$line,2);
		$flag = trim($arr[0]);
		$toarea = trim($arr[1]);
		if ($this->Ifflag($flag)):
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
			$Omessage->MsgText = "AREA:$toarea\r".$CurrentMessage->MsgText;
			PackMessageLocal($Omessage);
		endif;
	}

	function NoStore($flag)
	{
		if ($this->Ifflag($flag)):
			$this->modf_flags |= MPF_NOSTORE;
		endif;
	}

	function NoBadCk($flag)
	{
		if ($this->Ifflag($flag)):
			$this->modf_flags |= MPF_NOBADCK;
		endif;
	}

	function LogMsg($line)
	{
		GLOBAL $CurrentMessage, $CurrentMessageParsed;
		$arr = explode(' ',$line,2);
		$flag = trim($arr[0]);
		$arr = explode(' ',trim($arr[1]),2);
		$lvl = trim($arr[0]);
		$prefix = isset($arr[1])?trim($arr[1]):'';
		if ($this->Ifflag($flag)):
			tolog($lvl,$prefix." \"{$CurrentMessageParsed->area}\"; \"".
				$CurrentMessage->FromUser."\" (".
				$CurrentMessageParsed->FromAddr.") => \"".
				$CurrentMessage->ToUser."\" (".
				$CurrentMessageParsed->ToAddr.") | \"".
				$CurrentMessage->Subject."\"");
		endif;
	}

	function Modify($what,$line)
	{
		GLOBAL $CurrentMessage;
		$arr = explode(' ',$line,2);
		$flag = trim($arr[0]);
		$tofield = trim($arr[1]);
		if ($this->Ifflag($flag)):
			if ($what == 0):
				$CurrentMessage->FromUser = $tofield;
			elseif($what == 1):
				$CurrentMessage->ToUser = $tofield;
			elseif($what == 2):
				$CurrentMessage->Subject = $tofield;
			endif;
		endif;
	}

	function SetArea($line)
	{
		GLOBAL $CurrentMessage, $CurrentMessageParsed;
		$arr = explode(' ',$line,2);
		$flag = trim($arr[0]);
		$toarea = trim($arr[1]);
		if ($this->Ifflag($flag)):
			if ($CurrentMessageParsed->area):
				$p = strpos($CurrentMessage->MsgText,"\r");
			else:
				$p = -1;
			endif;
			$CurrentMessage->MsgText = 'AREA:'.$toarea."\r".substr($CurrentMessage->MsgText,$p+1);
			$CurrentMessageParsed->area = $toarea;
			return 1;
		endif;
	}

	function NAT($S,$line)
	{
		GLOBAL $CurrentMessage, $CurrentMessageParsed;
		$arr = explode(' ',$line,2);
		$flag = trim($arr[0]);
		$toaddr = trim($arr[1]);
		if ($this->Ifflag($flag)):
			if ($CurrentMessageParsed->area):
				if ($S):
					return DoEchoSNAT($toaddr);
				else:
					return false;
				endif;
			else:
				if ($S):
					return DoNetSNAT($toaddr);
				else:
					return DoNetDNAT($toaddr);
				endif;
			endif;
		endif;
	}

	function Drop($line)
	{
		$flag = trim($line);
		if ($this->Ifflag($flag)):
			GLOBAL $CurrentMessage;
			$CurrentMessage = false;
			return 1;
		endif;
	}

	function Ifflag($arg)
	{
		if ($arg == '-') return true;
		$evals = explode(',',$arg);
		foreach($evals as $e):
			$not = 0;
			if ($e{0}=='!'):
				$not = 1;
				$e = substr($e,1);
			endif;
			$r = in_array($e,$this->Flags);
			$r ^= $not;
			if (!$r) return false;
		endforeach;
		return true;
	}
	
	function Rule_Match($rule)
	{
		GLOBAL $CurrentMessageParsed,$CurrentMessage;
		$inv = 0;
		if ($rule{0} == '!'):
			$rule = substr($rule,1);
			$inv = 1;
		endif;
		$arr = explode(':',$rule,2);
		if (sizeof($arr)<2):
			$result = $arr[0] == '*';
		else:
			$what = strtolower(trim($arr[0]));
			$mask = trim($arr[1]);
			$text = '';
			switch($what):
				case('area'):
					$text = $CurrentMessageParsed->area;
					break;
				case('to'):
					$text = $CurrentMessage->ToUser;
					break;
				case('from'):
					$text = $CurrentMessage->FromUser;
					break;
				case('subject'):
					$text = $CurrentMessage->Subject;
					break;
				case('fromaddr'):
					$text = $CurrentMessageParsed->FromAddr;
					break;
				case('toaddr'):
					$text = $CurrentMessageParsed->ToAddr;
					break;
				case('text'):
					$text = $CurrentMessage->MsgText;
					break;
				case('kludge'):
					$text = $CurrentMessageParsed->top;
					break;
				case('msgid'):
					$text = $CurrentMessageParsed->msgid;
					break;
				case('reply'):
					$text = $CurrentMessageParsed->reply;
					break;
				case('seenby'):
					$text = $CurrentMessageParsed->seenby;
					break;
				case('path'):
					$text = $CurrentMessageParsed->path;
					break;
				case('body'):
					$text = $CurrentMessageParsed->body;
					break;
				case('botkludge'):
					$text = $CurrentMessageParsed->bottom;
					break;
				case('tearline'):
					$text = $CurrentMessageParsed->tearline;
					break;
				case('origin'):
					$text = $CurrentMessageParsed->origin;
					break;
				case('date'):
					$text = $CurrentMessage->getAsciiDate();
					break;
				case('pktfrom'):
					$text = $this->PktFrom;
					break;
			endswitch;
			$result = is_match_mask($mask,$text);
		endif;
		return ($inv?!$result:$result);
	}

	function SetKludge($argv,$del_notset)
	{
		GLOBAL $CONFIG, $CurrentMessage;
		$arr = explode(' ',$argv,2);
		$flag = trim($arr[0]);
		$kludge = trim($arr[1]);
		$kludge_key = strtok($kludge,' :');
		if ($this->Ifflag($flag)):
			$lines = explode("\r",$CurrentMessage->MsgText);
			$lines2 = array();
			$was = 0;
			for($i=0,$s=sizeof($lines);$i<$s;$i++):
				if (strcasecmp(strtok($lines[$i],':'),chr(1).$kludge_key)==0):
					$was = 1;
					if (!$del_notset):
						$lines2[] = chr(1).$kludge;
					endif;
				else:
					$lines2[] = $lines[$i];
				endif;
			endfor;
			if (!$del_notset && !$was) array_unshift($lines2,chr(1).$kludge);
			$CurrentMessage->MsgText = join("\r",$lines2);
			return 1;
		endif;
	}
	
	function Answer($line)
	{
		GLOBAL $CurrentMessage;
		GLOBAL $CurrentMessageParsed;
		$arr = explode(' ',$line,2);
		$flag = trim($arr[0]);
		$arr = explode(' ',trim($arr[1]),2);
		$echo = trim($arr[0]);
		$msg = convert_cyr_string(trim($arr[1]),"k","d");
		if ($this->Ifflag($flag)):
			$to = $CurrentMessageParsed->FromAddr;
			$origto = $CurrentMessageParsed->ToAddr;
			InitVsysTrack($to,$origto);
			$msgbody = '';
			if ($msg{0} == '@') {
				$s = GetStrFromFile_param(substr($msg,1));
				$msgbody.= ($s?$s:"Error!")."\r";
			} else {
				$msgbody.= $msg;
			}			
			$s = GetStrFromFile_param('QUOTE');
			$msgps = ($s?$s:"Here is your message:")."\r".FormForward(1,0,1,1);
			SendReply($CurrentMessageParsed->msgid,$to,$msgbody,$msgps,0,$CurrentMessage->FromUser,$CurrentMessage->Subject,$echo);
		endif;
	}
}