<?
/*\
  /  This is a part of PhFiTo (aka PHP Fido Tosser)
 //  Copyright (c) Alex Kocharin, 2:50/13
///
///  This program is distributed under GNU GPL v2
///  See docs/license for details
//
/    $Id: C_fips.php,v 1.8 2007/10/14 08:29:54 kocharin Exp $
\*/

if (!function_exists('xinclude')) die();

class C_fipsbase
{
	var $msgb;
	var $_hdr;
	var $_msg;
	var $extended_syntax;
	var $allscan_present;

	function C_fipsbase()
	{
		$this->extended_syntax = false;
		$this->allscan_present = false;
	}
	
	function OpenBase($path)
	{
		$this->msgb = $path;
		if (($this->_hdr = @fopen($path.'.hdr','r+')) &&
		   ($this->_msg = @fopen($path.'.mes','r+'))):
			return 1;
		else:
			$this->CloseBase();
			return 0;
		endif;
	}

	function GetNumMsgs()
	{
		return (int)(filesize($this->msgb.'.hdr')/238);
	}

	function SetAttr($msg,$attr)
	{
		list($status,$attrs) = $this->_flag_to_fips($attr);
		$pos = 238*$msg - 238;
		fseek($this->_hdr,$pos+168);
		fputint($this->_hdr,4,$status);
		fseek($this->_hdr,$pos+0xc2);
		fputint($this->_hdr,2,$attrs);
	}

	function ReadMsgHeader($msg)
	{
		$result = new C_msgheader;
		$pos = 238*$msg - 238;
		fseek($this->_hdr,$pos);
		$result->Subj = fgetasciiz($this->_hdr);
		fseek($this->_hdr,$pos+92);
		$result->To = fgetasciiz($this->_hdr);
		fseek($this->_hdr,$pos+128);
		$result->From = fgetasciiz($this->_hdr);
		fseek($this->_hdr,$pos+168);
		$status = fgetint($this->_hdr,4);
		fseek($this->_hdr,$pos+176);
		$result->ADate = fgetint($this->_hdr,4);
		fseek($this->_hdr,$pos+0xc2);
		$attrs = fgetint($this->_hdr,2);
		$result->Attrs = $this->_fips_to_flag(array($attrs,$status));
		fread($this->_hdr,2);
		$z = fgetint($this->_hdr,2);
		$n = fgetint($this->_hdr,2);
		$f = fgetint($this->_hdr,2);
		$p = fgetint($this->_hdr,2);
		$result->FromAddr = $z.":".$n."/".$f.($p?'.'.$p:'');
		$z = fgetint($this->_hdr,2);
		$n = fgetint($this->_hdr,2);
		$f = fgetint($this->_hdr,2);
		$p = fgetint($this->_hdr,2);
		$result->ToAddr = $z.":".$n."/".$f.($p?'.'.$p:'');
		fseek($this->_hdr,$pos+0xde);
		$result->WDate = fgetint($this->_hdr,4);
		return $result;
	}
	
	function CloseBase()
	{
		if ($this->_hdr) fclose($this->_hdr);
		if ($this->_msg) fclose($this->_msg);
	}

	function ReadMsgBody($msg)
	{
		fseek($this->_hdr,$msg*238-58);
		$pos = fgetint($this->_hdr,4);
		fseek($this->_msg,$pos+224);
		$len = fgetint($this->_msg,4);
		fseek($this->_msg,$pos+0x116);
		return fread($this->_msg,$len);
	}

	function _flag_to_fips($rq)
	{
		$attrs = 0;
		if ($rq & MSG_PVT) $attrs |= 0x0001;
		if ($rq & MSG_CRA) $attrs |= 0x0002;
		if ($rq & MSG_RCV) $attrs |= 0x0004;
		if ($rq & MSG_SNT) $attrs |= 0x0008;
		if ($rq & MSG_ATT) $attrs |= 0x0010;
		if ($rq & MSG_TRS) $attrs |= 0x0020;
		if ($rq & MSG_ORP) $attrs |= 0x0040;
		if ($rq & MSG_KFS) $attrs |= 0x0080;
		if ($rq & MSG_LOC) $attrs |= 0x0100;
		if ($rq & MSG_FRQ) $attrs |= 0x0800;
		if ($rq & MSG_RRQ) $attrs |= 0x1000;
		if ($rq & MSG_RRC) $attrs |= 0x2000;
		if ($rq & MSG_ARQ) $attrs |= 0x4000;
		if ($rq & MSG_RRQ) $attrs |= 0x8000;
		$status = 0;
		if ($rq & MSG_DEL) $status |= 0x0001;
		if ($rq & MSG_DEL) $status |= 0x0002;
		if ($rq & MSG_LOC) $status |= 0x0020;
		if ($rq & MSG_SCN) $status |= 0x0040;
		if ($rq & MSG_DIR) $status |= 0x0100;
		if ($rq & MSG_LOK) $status |= 0x0400;
		return array($attrs,$status);
	}

	function _fips_to_flag($arr)
	{
		$rq = $arr[0];
		$attrs = 0;
		if ($rq & 0x0001) $attrs |= MSG_PVT;
		if ($rq & 0x0002) $attrs |= MSG_CRA;
		if ($rq & 0x0004) $attrs |= MSG_RCV;
		if ($rq & 0x0008) $attrs |= MSG_SNT;
		if ($rq & 0x0010) $attrs |= MSG_ATT;
		if ($rq & 0x0020) $attrs |= MSG_TRS;
		if ($rq & 0x0040) $attrs |= MSG_ORP;
		if ($rq & 0x0080) $attrs |= MSG_KFS;
		if ($rq & 0x0100) $attrs |= MSG_LOC;
		if ($rq & 0x0800) $attrs |= MSG_FRQ;
		if ($rq & 0x1000) $attrs |= MSG_RRQ;
		if ($rq & 0x2000) $attrs |= MSG_RRC;
		if ($rq & 0x4000) $attrs |= MSG_ARQ;
		if ($rq & 0x8000) $attrs |= MSG_RRQ;
		$rq = $arr[1];
		if ($rq & 0x0001) $attrs |= MSG_DEL;
		if ($rq & 0x0020) $attrs |= MSG_LOC;
		if ($rq & 0x0040) $attrs |= MSG_SCN;
		if ($rq & 0x0100) $attrs |= MSG_DIR;
		if ($rq & 0x0400) $attrs |= MSG_LOK;
		return $attrs; 
	}

	function WriteMessage($header,$message)
	{
		if (strlen($header->From)>36) $header->From = substr($header->From,0,36);
		if (strlen($header->To)>36) $header->To = substr($header->To,0,36);
		if (strlen($header->Subj)>72) $header->Subj = substr($header->Subj,0,72);
		if (filesize($this->msgb.'.hdr')>50):
			fseek($this->_hdr,-50,2);
			$num = fgetint($this->_hdr,4)+1;
		else:
			$num = 0;
		endif;
		$From = $header->From;
		$To = $header->To;
		$Subj = $header->Subj;
		$wtime = $header->WDate;
		if (!$wtime) $wtime = time();
		$atime = (int)$header->ADate;
		$asciidate = date('d M y  H:i:s',$wtime);
		$txtlen = strlen($message)+1;
		list($attrs,$status) = $this->_flag_to_fips($header->Attrs);
		$id = rand(0,0x7fffffff);
		$offset = filesize($this->msgb.'.mes');
		
		fseek($this->_hdr,0,2);
		fputstr($this->_hdr,$Subj,72);
		fputstr($this->_hdr,$asciidate,20);
		fputstr($this->_hdr,$To,36);
		fputstr($this->_hdr,$From,36);
		fputint($this->_hdr,4,238);
		fputint($this->_hdr,4,$status);
		fputint($this->_hdr,4,$id);
		fputint($this->_hdr,4,$atime);
		fputint($this->_hdr,4,$offset);
		fputint($this->_hdr,4,$txtlen);
		fputint($this->_hdr,4,$num);
		fwrite($this->_hdr,chr(0).chr(0));
		fputint($this->_hdr,2,$attrs);
		fwrite($this->_hdr,chr(0).chr(0));
		$fromaddr = strtok($header->FromAddr,'@');
		$toaddr = strtok($header->ToAddr,'@');
		$fz = strtok($header->FromAddr,':');
		$fn = strtok('/');
		$ff = strtok('.');
		$fp = strtok(chr(0));
		if (!$fp) $fp = 0;
		$tz = strtok($header->ToAddr,':');
		$tn = strtok('/');
		$tf = strtok('.');
		$tp = strtok(chr(0));
		if (!$tp) $tp = 0;
		fputint($this->_hdr,2,$fz);
		fputint($this->_hdr,2,$fn);
		fputint($this->_hdr,2,$ff);
		fputint($this->_hdr,2,$fp);
		fputint($this->_hdr,2,$tz);
		fputint($this->_hdr,2,$tn);
		fputint($this->_hdr,2,$tf);
		fputint($this->_hdr,2,$tp);
		fwrite($this->_hdr,chr(0).chr(0).chr(0).chr(0));
		fwrite($this->_hdr,chr(0).chr(0).chr(0).chr(0));
		fputint($this->_hdr,4,$wtime);
		fputint($this->_hdr,2,$tn);
		fputint($this->_hdr,2,$tz);
		fputint($this->_hdr,4,$tf);
		fputint($this->_hdr,4,0);

		fseek($this->_msg,$offset);
		fwrite($this->_msg,chr(0xfe).chr(0xaf).chr(0xfe).chr(0xaf));
		fwrite($this->_msg,chr(0xfe).chr(0xaf).chr(0xfe).chr(0xaf));
		fwrite($this->_msg,chr(0x04).chr(0x03).chr(0x02).chr(0x01));
		fwrite($this->_msg,chr(0x01).chr(0x02).chr(0x03).chr(0x04));
		fputint($this->_msg,4,1);
		for ($i=0;$i<20;$i++) fwrite($this->_msg,chr(0));
		fputstr($this->_msg,$Subj,72);
		fputstr($this->_msg,$asciidate,20);
		fputstr($this->_msg,$To,36);
		fputstr($this->_msg,$From,36);
		fputint($this->_msg,4,238);
		fputint($this->_msg,4,$status);
		fputint($this->_msg,4,$id);
		fputint($this->_msg,4,$atime);
		fputint($this->_msg,4,$offset);
		fputint($this->_msg,4,$txtlen);
		fputint($this->_msg,4,$num);
		fwrite($this->_msg,chr(0).chr(0));
		fputint($this->_msg,2,$attrs);
		fwrite($this->_msg,chr(0).chr(0));
		fputint($this->_msg,2,$fz);
		fputint($this->_msg,2,$fn);
		fputint($this->_msg,2,$ff);
		fputint($this->_msg,2,$fp);
		fputint($this->_msg,2,$tz);
		fputint($this->_msg,2,$tn);
		fputint($this->_msg,2,$tf);
		fputint($this->_msg,2,$tp);
		fwrite($this->_msg,chr(0).chr(0).chr(0).chr(0));
		fwrite($this->_msg,chr(0).chr(0).chr(0).chr(0));
		fputint($this->_msg,4,$wtime);
		fputint($this->_msg,2,$tn);
		fputint($this->_msg,2,$tz);
		fputint($this->_msg,4,$tf);
		fputint($this->_msg,4,0);
		fwrite($this->_msg,$message.chr(0));
	}

	function DeleteMsg($num)
	{
		return false;
	}

	function PurgeBase()
	{
		return false;
	}

	function DeleteBase($path)
	{
		return false;
	}

	function CreateBase($path)
	{
		if (($hdr = fopen($path.'.hdr','w')) &&
		    ($mes = fopen($path.'.mes','w'))):
			fclose($hdr);
			fclose($mes);
			@chmod($path.'.hdr',0666);
			@chmod($path.'.mes',0666);
			return 1;
		else:
			if ($hdr) fclose($hdr);
			if ($mes) fclose($mes);
			return 0;
		endif;
	}
}

?>
