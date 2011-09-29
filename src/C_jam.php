<?php
/*\
  /  This is a part of PhFiTo (aka PHP Fido Tosser)
 //  Copyright (c) Alex Kocharin, 2:50/13
///
///  This program is distributed under GNU GPL v2
///  See docs/license for details
//
/    $Id: C_jam.php,v 1.8 2007/10/14 08:29:54 kocharin Exp $
\*/

if (!function_exists('xinclude')) die();

class C_jambase
{
	var $path;
	var $_jhr;
	var $_jdt;
	var $_jdx;
	var $_jlr;
	var $header;
	var $extended_syntax;
	var $allscan_present;

	function C_jambase()
	{
		$this->extended_syntax = false;
		$this->allscan_present = false;
	}
	
	function OpenBase($path)
	{
		$this->path = $path;
		if (($this->_jhr = @fopen($path.'.jhr','r+')) &&
		   ($this->_jdt = @fopen($path.'.jdt','r+')) &&
		   ($this->_jdx = @fopen($path.'.jdx','r+'))):
			$this->_jlr = @fopen($path.'.jlr','r+');
			$this->header = new JamBaseHdr($this->_jhr);
			return 1;
		else:
			$this->CloseBase();
			return 0;
		endif;
	}

	function GetNumMsgs()
	{
		fseek($this->_jdx,4);
		$num = 0;
		while(!feof($this->_jdx)) {
			$d = fread($this->_jdx,4);
			$arr = unpack("Vpos", $d);
			$pos = $arr['pos'];
			fread($this->_jdx,4);
			if ($pos != -1) $num++;
		}
		return $num;
	}

	function ReadMsgHeader($msg)
	{
		$pos = $this->_seek_message($msg);
		$result = new C_msgheader;
		fseek($this->_jhr,$pos+8);
		$subf_len = fgetint($this->_jhr,4);
		fseek($this->_jhr,$pos+36);
		$result->WDate = fgetint($this->_jhr,4);
		fread($this->_jhr,4);
		$result->ADate = fgetint($this->_jhr,4);
		fread($this->_jhr,4);
		$result->Attrs = $this->_jam_to_flag(fgetint($this->_jhr,4));
		$spos = $pos+76;
		$sk = 1;
		while($spos-$pos-76<$subf_len):
		if ($sk) fseek($this->_jhr,$spos);
		$sk = 1;
		$id = fgetint($this->_jhr,2);
		fread($this->_jhr,2);
		$len = fgetint($this->_jhr,4);
		switch ($id):
			case(0):
				$result->FromAddr = fread($this->_jhr,$len);
				$sk = 0;
				break;
			case(1):
				$result->ToAddr = fread($this->_jhr,$len);
				$sk = 0;
				break;
			case(2):
				$result->From = fread($this->_jhr,$len);
				$sk = 0;
				break;
			case(3):
				$result->To = fread($this->_jhr,$len);
				$sk = 0;
				break;
			case(6):
				$result->Subj = fread($this->_jhr,$len);
				$sk = 0;
				break;
		endswitch;
		$spos += 8 + $len;
		endwhile;
		return $result;
	}

	function ReadMsgBody($msg)
	{
		$pos = $this->_seek_message($msg);
		fseek($this->_jhr,$pos+8);
		$subf_len = fgetint($this->_jhr,4);
		fseek($this->_jhr,$pos+0x3c);
		$offset = fgetint($this->_jhr,4);
		$textlen = fgetint($this->_jhr,4);
		$spos = $pos+76;
		$sk = 1;
		$kludges = '';
		$seenby = '';
		$path = '';
		$msgid = '';
		$reply = '';
		$pid = '';
		while($spos-$pos-76<$subf_len):
		if ($sk) fseek($this->_jhr,$spos);
		$sk = 1;
		$id = fgetint($this->_jhr,2);
		fread($this->_jhr,2);
		$len = fgetint($this->_jhr,4);
		switch ($id):
			case(4):
				$msgid.= fread($this->_jhr,$len);
				$sk = 0;
				break;
			case(5):
				$reply.= fread($this->_jhr,$len);
				$sk = 0;
				break;
			case(7):
				$pid = fread($this->_jhr,$len);
				$sk = 0;
				break;
			case(2000):
				$kludges.= chr(1).fread($this->_jhr,$len)."\r";
				$sk = 0;
				break;
			case(2001):
				$seenby.= fread($this->_jhr,$len);
				$sk = 0;
				break;
			case(2002):
				$path.= fread($this->_jhr,$len);
				$sk = 0;
				break;
		endswitch;
		$spos += 8 + $len;
		endwhile;
		fseek($this->_jdt,$offset);
		$text = fread($this->_jdt,$textlen);
		return ($msgid?chr(1).'MSGID: '.$msgid."\r":'').
			($reply?chr(1).'REPLY: '.$reply."\r":'').
			($pid?chr(1).'PID: '.$pid."\r":'').
			$kludges.$text.
			($seenby?'SEEN-BY: '.$seenby."\r":'').
			($path?chr(1).'PATH: '.$path:'')."\r";
	}

	function SetAttr($msg,$attr)
	{
		$attr = $this->_flag_to_jam($attr);
		$pos = $this->_seek_message($msg);
		fseek($this->_jhr,$pos+52);
		fputint($this->_jhr,4,$attr);
	}
	
	function WriteMessage($header,$message)
	{
		$this->header->activemsgs++;
		$this->header->modcounter++;
		if (filesize($this->path.'.jdx')>4):
			fseek($this->_jdx,-4,2);
			$offspr = fgetint($this->_jdx,4);
			fseek($this->_jhr,$offspr+48);
			$num = fgetint($this->_jhr,4)+1;
		else:
			$num = 1;
		endif;
		$fs = filesize($this->path.'.jhr');
		$dtfs = filesize($this->path.'.jdt');	
		fputint($this->_jdx,4,crc32(strtolower($header->To)));
		fputint($this->_jdx,4,$fs);
		fseek($this->_jhr,$fs);
		for ($i=1;$i<=76;$i++) fwrite($this->_jhr,chr(0));
		$msgid = '';
		$reply = '';
		$path = '';
		$seenby = '';
		$subflen = 0;
		$resmsg = '';
		$sepmsg = '';
		
		$message = str_replace("\r","\r".chr(0),$message);
		$line = strtok($message,"\r");

		while($line):
			if (strcmp(substr($line,0,1),chr(0))==0) $line = substr($line,1);
			if (strcmp(substr($line,0,1),chr(1))==0):
				if (!$msgid && (strcasecmp(substr($line,1,7),'MSGID: ')==0)):
					$msgid = trim(substr($line,8));
					$subflen += $this->_write_subfield($this->_jhr,4,$msgid);
				elseif (!$reply && (strcasecmp(substr($line,1,7),'REPLY: ')==0)):
					$reply = trim(substr($line,8));
					$subflen += $this->_write_subfield($this->_jhr,5,$reply);
				elseif (strcasecmp(substr($line,1,4),'PID:')==0):
					$subflen += $this->_write_subfield($this->_jhr,7,trim(substr($line,5)));
				elseif (strcasecmp(substr($line,1,6),'PATH: ')==0):
					break;
				else:
					$subflen += $this->_write_subfield($this->_jhr,2000,trim(substr($line,1)));
				endif;
			else:
				break;
			endif;
			$line = strtok("\r");
		endwhile;
		$line = chr(0).$line;
		while($line):
			if (strcmp(substr($line,0,1),chr(0))==0) $line = substr($line,1);
			if (strcasecmp(substr($line,0,7),chr(1).'PATH: ')==0):
				$path = trim(substr($line,7));
				break;
			elseif (strcmp(substr($line,0,9),'SEEN-BY: ')==0):
				$sepmsg.= $line."\r";
				$seenby.= ($seenby?' ':'').trim(substr($line,9));
			else:
				$resmsg.= $sepmsg.$line."\r";
				$sepmsg = '';
				$seenby = '';
				$path = '';
			endif;
			$line = strtok("\r");
		endwhile;
		if ($f = $header->FromAddr) $subflen += $this->_write_subfield($this->_jhr,0,$f);
		if ($f = $header->ToAddr) $subflen += $this->_write_subfield($this->_jhr,1,$f);
		if ($f = $header->From) $subflen += $this->_write_subfield($this->_jhr,2,$f);
		if ($f = $header->To) $subflen += $this->_write_subfield($this->_jhr,3,$f);
		if ($f = $header->Subj) $subflen += $this->_write_subfield($this->_jhr,6,$f);
		if ($seenby) $subflen += $this->_write_subfield($this->_jhr,2001,$seenby);
		if ($path) $subflen += $this->_write_subfield($this->_jhr,2002,$path);

		fseek($this->_jhr,$fs);
		fwrite($this->_jhr,'JAM'.chr(0));
		fputint($this->_jhr,2,1);
		fwrite($this->_jhr,chr(0).chr(0));
		fputint($this->_jhr,4,$subflen);
		fputint($this->_jhr,4,0);
		fputint($this->_jhr,4,$msgid?crc32($msgid):-1);
		fputint($this->_jhr,4,$reply?crc32($reply):-1);
		fwrite($this->_jhr,chr(0).chr(0).chr(0).chr(0));
		fwrite($this->_jhr,chr(0).chr(0).chr(0).chr(0));
		fwrite($this->_jhr,chr(0).chr(0).chr(0).chr(0));
		fputint($this->_jhr,4,$header->WDate?$header->WDate:time());
		fwrite($this->_jhr,chr(0).chr(0).chr(0).chr(0));
		fputint($this->_jhr,4,$header->ADate?$header->ADate:0);
		fputint($this->_jhr,4,$num);
		$rq = $header->Attrs;
		$attrs = $this->_flag_to_jam($rq);
		fputint($this->_jhr,4,$attrs);
		fwrite($this->_jhr,chr(0).chr(0).chr(0).chr(0));
		fputint($this->_jhr,4,$dtfs);
		fputint($this->_jhr,4,strlen($resmsg));
		fwrite($this->_jhr,chr(0xff).chr(0xff).chr(0xff).chr(0xff));
		
		$this->header->Update($this->_jhr);
		fseek($this->_jdt,0,2);
		fwrite($this->_jdt,$resmsg);
	}

	function _seek_message($num)
	{
		fseek($this->_jdx,0);
		$i = 0;
		while(!feof($this->_jdx)) {
			$d = fread($this->_jdx,8);
			$arr = unpack("Vcrc/Vpos", $d);
			$pos = $arr['pos'];
			if ($pos != -1) $i++;
			if ($i == $num) return $pos;
		}
	}

	function _flag_to_jam($rq)
	{
		$attrs = 0;
		if ($rq & MSG_LOC) $attrs |= 0x00000001;
		if ($rq & MSG_TRS) $attrs |= 0x00000002;
		if ($rq & MSG_PVT) $attrs |= 0x00000004;
		if ($rq & MSG_RCV) $attrs |= 0x00000008;
		if ($rq & MSG_SNT) $attrs |= 0x00000010;
		if ($rq & MSG_KFS) $attrs |= 0x00000020;
		if ($rq & MSG_ARS) $attrs |= 0x00000040;
		if ($rq & MSG_HLD) $attrs |= 0x00000080;
		if ($rq & MSG_CRA) $attrs |= 0x00000100;
		if ($rq & MSG_IMM) $attrs |= 0x00000200;
		if ($rq & MSG_DIR) $attrs |= 0x00000400;
		if ($rq & MSG_ZON) $attrs |= 0x00000800;
		if ($rq & MSG_FRQ) $attrs |= 0x00001000;
		if ($rq & MSG_ATT) $attrs |= 0x00002000;
		if ($rq & MSG_TFS) $attrs |= 0x00004000;
		if ($rq & MSG_DFS) $attrs |= 0x00008000;
		if ($rq & MSG_RRQ) $attrs |= 0x00010000;
		if ($rq & MSG_CFM) $attrs |= 0x00020000;
		if ($rq & MSG_ORP) $attrs |= 0x00040000;
		if ($rq & MSG_CRY) $attrs |= 0x00080000;
		if ($rq & MSG_LOK) $attrs |= 0x40000000;
		if ($rq & MSG_DEL) $attrs |= 0x80000000;
		return $attrs;
	}

	function _jam_to_flag($rq)
	{
		$attrs = 0;
		if ($rq & 0x00000001) $attrs |= MSG_LOC;
		if ($rq & 0x00000002) $attrs |= MSG_TRS;
		if ($rq & 0x00000004) $attrs |= MSG_PVT;
		if ($rq & 0x00000008) $attrs |= MSG_RCV;
		if ($rq & 0x00000010) $attrs |= MSG_SNT;
		if ($rq & 0x00000020) $attrs |= MSG_KFS;
		if ($rq & 0x00000040) $attrs |= MSG_ARS;
		if ($rq & 0x00000080) $attrs |= MSG_HLD;
		if ($rq & 0x00000100) $attrs |= MSG_CRA;
		if ($rq & 0x00000200) $attrs |= MSG_IMM;
		if ($rq & 0x00000400) $attrs |= MSG_DIR;
		if ($rq & 0x00000800) $attrs |= MSG_ZON;
		if ($rq & 0x00001000) $attrs |= MSG_FRQ;
		if ($rq & 0x00002000) $attrs |= MSG_ATT;
		if ($rq & 0x00004000) $attrs |= MSG_TFS;
		if ($rq & 0x00008000) $attrs |= MSG_DFS;
		if ($rq & 0x00010000) $attrs |= MSG_RRQ;
		if ($rq & 0x00020000) $attrs |= MSG_CFM;
		if ($rq & 0x00040000) $attrs |= MSG_ORP;
		if ($rq & 0x00080000) $attrs |= MSG_CRY;
		if ($rq & 0x40000000) $attrs |= MSG_LOK;
		if ($rq & 0x80000000) $attrs |= MSG_DEL;
		return $attrs;
	}

	function _write_subfield($f,$id,$data)
	{
		$hdr = pack("vxxV", $id, $len = strlen($data));
		fwrite($f,$hdr.$data);
		return $len + 8;
	}

	function DeleteMsg($num)
	{
		fseek($this->_jdx,0);
		$i = 0;
		while(!feof($this->_jdx)) {
			$d = fread($this->_jdx,8);
			$arr = unpack("Vcrc/Vpos",$d);
			$pos = $arr['pos'];
			if ($pos != -1) $i++;
			if ($i == $num) {
				fseek($this->_jdx,-8,1);
				fwrite($this->_jdx,pack("VV",-1,-1));
				break;
			}
		}
		return true;
	}

	function PurgeBase()
	{
		$i = 0;
		$b = dirname($this->path);
		$a = basename($this->path);
		$path = $b.'/.tmp-'.$a;
		while (
			(file_exists($path.'.jhr') ||
			file_exists($path.'.jdx') ||
			file_exists($path.'.jdt')) && $i<1000
		) {
			$path = $b.'/.tmp'.($i++).'-'.$a;
		}
		$newheader = new JamBaseHdr;
		$newheader->Signature = 'JAM'.chr(0);
		$newheader->datecreated = $this->header->datecreated;
		$newheader->modcounter = $this->header->modcounter+1;
		$newheader->activemsgs = $this->header->activemsgs;
		$newheader->passwordcrc = $this->header->passwordcrc;
		$newheader->basemsgnum = $this->header->basemsgnum;
   		if (($jhr = fopen($path.'.jhr','w+')) &&
			($jdt = fopen($path.'.jdt','w+')) &&
			($jdx = fopen($path.'.jdx','w+'))) {
				fwrite($jhr,'JAM'.str_repeat(chr(0),1021),1024);
		} else {
			if (isset($jhr) && $jhr) fclose($jhr);
			if (isset($jdt) && $jdt) fclose($jdt);
			if (isset($jdx) && $jdx) fclose($jdx);
			return false;
		}
		fseek($this->_jdx,0);
		$jdtoff = 0;
		$jhroff = 1024;
		while(strlen($data = fread($this->_jdx, 8)) == 8) {
			$arr = unpack("Vcrc/Vpos", $data);
			$pos = $arr['pos'];
			if ($pos == -1) continue;

			fseek($this->_jhr, $pos);
			$fhead = fread($this->_jhr, 76);
			$fh = unpack("a4sign/vrev/vres/Vsub/Vtimr/Vmsgid/Vreply/".
				"Vreto/Vref/Vren/Vdatew/Vdateq/Vdatep/Vmsgnum/Vattr/Vattr2/".
				"Voff/Vlen/Vcrc/Vcost", $fhead);
			if ($fh['sign'] != 'JAM') continue;
			
			fwrite($jdx, pack("VV", $arr['crc'], $jhroff));

			$subflen = $fh['sub'];
			$textoff = $fh['off'];
			$textlen = $fh['len'];

			$fhead = pack("a4vvVVVVVVVVVVVVVVVVV",
				'JAM', $fh['rev'], $fh['res'], $subflen, $fh['timr'],
				$fh['msgid'], $fh['reply'], $fh['reto'], $fh['ref'],
				$fh['ren'], $fh['datew'], $fh['dateq'], $fh['datep'],
				$fh['msgnum'], $fh['attr'], $fh['attr2'], $jdtoff,
				$textlen, $fh['crc'], $fh['cost']
			);
			fwrite($jhr, $fhead);
			$fhead = '';
			fwrite($jhr, fread($this->_jhr, $subflen), $subflen);
			$jhroff += 76 + $subflen;
			
			fseek($this->_jdt, $textoff);
			$text = fread($this->_jdt, $textlen);
			fwrite($jdt, $text);
			$jdtoff += $textlen;
			$text = '';
		}
		$newheader->Update($jhr);
		$this->CloseBase();
		@chmod($path.'.jdx',0666);
		@chmod($path.'.jdt',0666);
		@chmod($path.'.jhr',0666);
		unlink($this->path.'.jdx');
		unlink($this->path.'.jdt');
		unlink($this->path.'.jhr');
		rename($path.'.jdx', $this->path.'.jdx');
		rename($path.'.jdt', $this->path.'.jdt');
		rename($path.'.jhr', $this->path.'.jhr');
		$this->OpenBase($this->path);
		return true;
	}

	function DeleteBase($path)
	{
		$ux = unlink($path.".jdx");
		$ut = unlink($path.".jdt");
		$uh = unlink($path.".jhr");
		$ul = unlink($path.".jlr");
		return $ux & $ut & $uh & $ul;
	}

	function CloseBase()
	{
		if ($this->_jhr) fclose($this->_jhr);
		if ($this->_jdt) fclose($this->_jdt);
		if ($this->_jdx) fclose($this->_jdx);
		if ($this->_jlr) fclose($this->_jlr);
	}

	function CreateBase($path)
	{
		if (($jhr = fopen($path.'.jhr','w')) &&
			($jdt = fopen($path.'.jdt','w')) &&
			($jdx = fopen($path.'.jdx','w')) &&
			($jlr = fopen($path.'.jlr','w'))):
				$data = pack('a4VVVVV','JAM',time(),0,0,0xffffffff,1);
				fwrite($jhr,$data.str_repeat(chr(0),1000));
				fclose($jhr);
				fclose($jdt);
				fclose($jdx);
				fclose($jlr);
				@chmod($path.'.jhr',0666);
				@chmod($path.'.jdt',0666);
				@chmod($path.'.jdx',0666);
				@chmod($path.'.jlr',0666);
				return 1;
		else:
			if ($jhr) fclose($jhr);
			if ($jdt) fclose($jdt);
			if ($jdx) fclose($jdx);
			if ($jlr) fclose($jlr);
			return 0;
		endif;
	}
}

class JamBaseHdr
{
	var $Signature;
	var $datecreated;
	var $modcounter;
	var $activemsgs;
	var $passwordcrc;
	var $basemsgnum;

	function JamBaseHdr($file = false)
	{
		if ($file !== false) {
			fseek($file,0);
			$header = fread($file,24);
			$arr = unpack("a4sign/Vdc/Vmc/Vmsg/Vpwd/Vbs", $header);
			$this->Signature = $arr['sign'];
			$this->datecreated = $arr['dc'];
			$this->modcounter = $arr['mc'];
			$this->activemsgs = $arr['msg'];
			$this->passwordcrc = $arr['pwd'];
			$this->basemsgnum = $arr['bs'];
		}
	}

	function Update($file)
	{
		fseek($file,4);
		fwrite($file, pack("VVVVV",
			$this->datecreated,
			$this->modcounter,
			$this->activemsgs,
			$this->passwordcrc,
			$this->basemsgnum
		));
	}
}