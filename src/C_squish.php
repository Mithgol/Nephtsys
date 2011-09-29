<?
/*\
  /  This is a part of PhFiTo (aka PHP Fido Tosser)
 //  Copyright (c) Alex Kocharin, 2:50/13
///
///  This program is distributed under GNU GPL v2
///  See docs/license for details
//
/    $Id: C_squish.php,v 1.19 2011/01/08 12:21:09 kocharin Exp $
\*/

if (!function_exists('xinclude')) die();

xinclude('F_utils.php');

class C_squishbase
{
	var $_idx;
	var $_base;
	var $_lr;
	var $header;
	var $extended_syntax;
	var $allscan_present;
	var $path;

	function C_squishbase()
	{
		$this->extended_syntax = false;
		$this->allscan_present = false;
	}
	
	function OpenBase($path)
	{
		$this->path = $path;
		if (($this->_idx = @fopen($path.'.sqi','r+')) &&
		   ($this->_base = @fopen($path.'.sqd','r+'))):
			$this->_lr = @fopen($path.'.sql','r+');
			$this->header = new SqBaseHeader($this->_base);
			return 1;
		else:
			$this->CloseBase();
			return 0;
		endif;
	}

	function GetNumMsgs()
	{
		return $this->header->num_msg;
	}

	function SetAttr($msg,$attr)
	{
		$attr = $this->_flag_to_sq($attr);
		fseek($this->_idx,$msg*12-12);
		$arr = unpack("Vpos",fread($this->_idx,4));
		fseek($this->_base,$arr["pos"]+28);
		$towr = pack("V",$attr);
		fwrite($this->_base,$towr,4);
	}
	
	function ReadMsgHeader($msg)
	{
		fseek($this->_idx,$msg*12-12);
		$arr = unpack("Vpos", fread($this->_idx, 4));
		$pos = $arr["pos"];

		$result = new C_msgheader;
		fseek($this->_base,$pos+28);
		$arr = unpack("Vattr", fread($this->_base, 4));
		$rq = $arr["attr"];
		$result->Attrs = $this->_sq_to_flag($rq);
		$result->From = strtok(fread($this->_base, 36), chr(0));
		$result->To = strtok(fread($this->_base, 36), chr(0));
		$result->Subj = strtok(fread($this->_base, 72), chr(0));
		$data = fread($this->_base, 24);
		$arr = unpack("vfz/vfn/vff/vfp/vdz/vdn/vdf/vdp/Vwd/Vad", $data);
		$result->FromAddr = $arr["fz"].":".$arr["fn"]."/".$arr["ff"].($arr["fp"]?'.'.$arr["fp"]:'');
		$result->ToAddr = $arr["dz"].":".$arr["dn"]."/".$arr["df"].($arr["dp"]?'.'.$arr["dp"]:'');
		$result->WDate = SCOMBO_to_unix($arr["wd"]);
		$result->ADate = SCOMBO_to_unix($arr["ad"]);
		return $result;
	}

	function ReadMsgBody($msg)
	{
		fseek($this->_idx,$msg*12-12);
		$arr = unpack("Vpos", fread($this->_idx, 4));
		$pos = $arr["pos"];

		fseek($this->_base,$pos+16);
		$arr = unpack("Vl/Vs", fread($this->_base, 8));
		$len = $arr["l"];
		$speclen = $arr["s"];
		fseek($this->_base,$pos+28+238);
		if ($speclen > 0) {
			$spec = fread($this->_base,$speclen);
			if (ord($spec{$l = (strlen($spec)-1)})==0) $spec = substr($spec,0,$l);
			$msgspec = $speclen?(chr(1).str_replace(chr(1),chr(13).chr(1),substr($spec,1)).chr(13)):'';
		} else {
			$msgspec = '';
		}
		$message = fread($this->_base,$len-$speclen-238);
//		print str_replace("\r","\n",$msgspec.$message);
		return $msgspec.$message;
	}

	function _flag_to_sq($rq)
	{
		$attrs = 0;
		if ($rq & MSG_PVT) $attrs |= 0x00000001;
		if ($rq & MSG_CRA) $attrs |= 0x00000002;
		if ($rq & MSG_RCV) $attrs |= 0x00000004;
		if ($rq & MSG_SNT) $attrs |= 0x00000008;
		if ($rq & MSG_ATT) $attrs |= 0x00000010;
		if ($rq & MSG_TRS) $attrs |= 0x00000020;
		if ($rq & MSG_ORP) $attrs |= 0x00000040;
		if ($rq & MSG_DEL) $attrs |= 0x00000080;
		if ($rq & MSG_LOC) $attrs |= 0x00000100;
		if ($rq & MSG_HLD) $attrs |= 0x00000200;
		if ($rq & MSG_XMA) $attrs |= 0x00000400;
		if ($rq & MSG_FRQ) $attrs |= 0x00000800;
		if ($rq & MSG_RRQ) $attrs |= 0x00001000;
		if ($rq & MSG_RRC) $attrs |= 0x00002000;
		if ($rq & MSG_ARQ) $attrs |= 0x00004000;
		if ($rq & MSG_URQ) $attrs |= 0x00008000;
		if ($rq & MSG_SCN) $attrs |= 0x00010000;
		if ($rq & MSG_LOK) $attrs |= 0x40000000;
		$attrs |= 0x00020000;
		return $attrs;
	}

	function _sq_to_flag($rq)
	{
		$attrs = 0;
		if ($rq & 0x00000001) $attrs |= MSG_PVT;
		if ($rq & 0x00000002) $attrs |= MSG_CRA;
		if ($rq & 0x00000004) $attrs |= MSG_RCV;
		if ($rq & 0x00000008) $attrs |= MSG_SNT;
		if ($rq & 0x00000010) $attrs |= MSG_ATT;
		if ($rq & 0x00000020) $attrs |= MSG_TRS;
		if ($rq & 0x00000040) $attrs |= MSG_ORP;
		if ($rq & 0x00000080) $attrs |= MSG_DEL;
		if ($rq & 0x00000100) $attrs |= MSG_LOC;
		if ($rq & 0x00000200) $attrs |= MSG_HLD;
		if ($rq & 0x00000400) $attrs |= MSG_XMA;
		if ($rq & 0x00000800) $attrs |= MSG_FRQ;
		if ($rq & 0x00001000) $attrs |= MSG_RRQ;
		if ($rq & 0x00002000) $attrs |= MSG_RRC;
		if ($rq & 0x00004000) $attrs |= MSG_ARQ;
		if ($rq & 0x00008000) $attrs |= MSG_URQ;
		if ($rq & 0x00010000) $attrs |= MSG_SCN;
		if ($rq & 0x40000000) $attrs |= MSG_LOK;
		return $attrs;
	}
	
	function WriteMessage($header,$message)
	{
		$off = $this->header->end_frame;
		$last = $this->header->last_frame;
		$uid = $this->header->uid;
		fseek($this->_idx,0,2);
		fwrite($this->_idx, pack("VVV", 
			$off, $uid, SquishHash(strtolower($header->From))
		));
		if ($last != 0) {
			fseek($this->_base,$last+4);
			fwrite($this->_base, pack("V", $off));
		}
		fseek($this->_base,$off);
		fwrite($this->_base, pack("VVV", 0xAFAE4453, 0, $last));
		$resmsg = '';
		while($message):
			if ($message{0} == chr(1)):
				if ((strcmp(substr($message,1,5),'PATH:')==0) ||
				(strcasecmp(substr($message,1,4),'Via:')==0))
					{ break; };
			else:
				break;
			endif;
			$pos = strpos($message,"\r");
			$resmsg.= substr($message,0,$pos);
			$message = substr($message,$pos+1);
		endwhile;
		$resmsg = chop($resmsg);
		$clen = strlen($resmsg);
		$resmsg.= $message;
		$rq = $header->Attrs;
		$attrs = $this->_flag_to_sq($rq);
/*		$cnt = 0;
		while($line):
		if (strcmp(substr($line,0,1),chr(1))==0):
			$clen+=strlen($line);
		else:
			break;
		endif;
		$line = strtok("\r");
		endwhile;*/
		$lenfr = 238 + strlen($resmsg);
		fwrite($this->_base, pack("VVVxxxxV", 
			$lenfr, $lenfr, $clen, $attrs
		));
		fputstr($this->_base,$header->From,36);
		fputstr($this->_base,$header->To,36);
		fputstr($this->_base,$header->Subj,72);
		$fromaddr = strtok($header->FromAddr,'@');
		$fz = strtok($header->FromAddr,':');
		$fn = strtok('/');
		$ff = strtok('.');
		$fp = strtok('@');
		if (!$fp) $fp = 0;
		$toaddr = strtok($header->ToAddr,'@');
		$tz = strtok($header->ToAddr,':');
		$tn = strtok('/');
		$tf = strtok('.');
		$tp = strtok('@');
		if (!$tp) $tp = 0;
		fwrite($this->_base,pack("vvvvvvvvVVxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxV", 
			$fz, $fn, $ff, $fp, $tz, $tn, $tf, $tp, 
			($header->WDate?
				unix_to_SCOMBO($header->WDate):
				unix_to_SCOMBO(time())
			),
			($header->ADate?unix_to_SCOMBO($header->ADate):0),
			$uid
		));
		fputstr($this->_base,$header->WDate?date('d M y  H:i:s',$header->WDate):date('d M y  H:i:s'),20);
		
		fwrite($this->_base,$resmsg);
		
		$this->header->num_msg++;
		$this->header->high_msg++;
		$this->header->uid++;
		if ($this->header->begin_frame == 0) {
			$this->header->begin_frame = $off;
		}
		$this->header->last_frame = $off;
		$this->header->end_frame = $off + 28 + $lenfr;
		$this->header->WriteHdr($this->_base);
	}

	function CloseBase()
	{
		unset($this->header);
		if ($this->_idx) fclose($this->_idx);
		if ($this->_base) fclose($this->_base);
		if ($this->_lr) fclose($this->_lr);
	}

	function DeleteMsg($msg)
	{
		# reading frames position
		fseek($this->_idx,$msg*12-12);
		$arr = unpack("Vpos",fread($this->_idx,4));
		$frpos = $arr["pos"];

		# setting frame_type = free
		fseek($this->_base,$frpos+24);
		fwrite($this->_base,chr(1).chr(0),2);

		# changing index file
		fclose($this->_idx);
		$contents = file_get_contents($p = $this->path.'.sqi');
		if ($fw = fopen($p,'w')) {
			if ($msg <= 1) {
				fwrite($fw, 
					substr($contents, $msg*12)
				);
			} else if ($msg >= $this->header->num_msg) {
				fwrite($fw, 
					substr($contents, 0, $msg*12-12)
				);
			} else {
				fwrite($fw, 
					substr($contents, 0, $msg*12-12).
					substr($contents, $msg*12)
				);
			}
			fclose($fw);
		}
		$this->_idx = @fopen($this->path.'.sqi','r+');

		# modifying header
		$this->header->num_msg--;
		$this->header->high_msg--;
		$this->header->free_frame = min($frpos, $this->header->free_frame);
		$this->header->last_free_frame = 
			max($frpos, $this->header->last_free_frame);
		$this->header->WriteHdr($this->_base);

		return true;
	}

	function PurgeBase()
	{
		$i = 0;
		$b = dirname($this->path);
		$a = basename($this->path);
		$path = $b.'/.tmp-'.$a;
		while (
			(file_exists($path.'.sqi') ||
			file_exists($path.'.sqd')) && $i<1000
		) {
			$path = $b.'/.tmp'.($i++).'-'.$a;
		}
		$newheader = new SqBaseHeader;
		$newheader->len = 256;
		$newheader->reserved = $this->header->reserved;
		$newheader->num_msg = $this->header->num_msg;
		$newheader->high_msg = $this->header->high_msg;
		$newheader->skip_msg = $this->header->skip_msg;
		$newheader->high_water = $this->header->high_water;
		$newheader->uid = $this->header->uid;
		$newheader->base = $this->header->base;
		$newheader->begin_frame = 0;
		$newheader->last_frame = 0;
		$newheader->free_frame = 0;
		$newheader->last_free_frame = 0;
		$newheader->end_frame = 256;
		$newheader->max_msg = $this->header->max_msg;
		$newheader->keep_days = $this->header->keep_days;
		$newheader->sz_sqhdr = $this->header->sz_sqhdr;
		if (($sqd = fopen($path.'.sqd','w+')) &&
			($sqi = fopen($path.'.sqi','w+'))) {
				fwrite($sqd, str_repeat(chr(0),256), 256);
		} else {
			if (isset($sqd) && $sqd) fclose($sqd);
			if (isset($sqi) && $sqi) fclose($sqi);
			return false;
		}
		fseek($this->_idx,0);
		$msgs = $this->header->num_msg;
		$prev = $off = 0;
		$next = 256;
		if ($msgs != 0) {
			$newheader->begin_frame = 256;
			for($i=1; $i<=$msgs; $i++) {
				$prev = $off;
				$off = $next;
				$data = fread($this->_idx,12);
				$arr = unpack("Vpos/Vuid/Vhash", $data);
				$uid = $arr["uid"];
				$hash = $arr["hash"];

				# reading frame control
				fseek($this->_base, $arr["pos"]+12);
				$data = fread($this->_base, 16);
				$oldframe = unpack("Vsz/Vmsg/Vcl/vtype/vres", $data);
				$sz = $oldframe["sz"];
				$type = $arr["type"];
				if ($type == 1) continue;
				if ($type == 3) $type == 0;

				$next += $sz+28;

				# sqi
				fwrite($sqi, pack("VVV", $off, $uid, $hash));

				# frame header
				fwrite($sqd, pack("VVVVVVvv", 
					0xAFAE4453, $i==$msgs?0:$next, $prev, $sz, 
					$oldframe["msg"], $oldframe["cl"], $frame, $oldframe["res"]
				), 28);

				# frame contents
				$data = fread($this->_base, $sz);
				fwrite($sqd, $data, $sz);
			}
			$newheader->last_frame = $off;
			$newheader->end_frame = $next;
		}
		$newheader->WriteHdr($sqd);

		$this->CloseBase();
		@chmod($path.'.sqd',0666);
		@chmod($path.'.sqi',0666);
		unlink($this->path.'.sqd');
		unlink($this->path.'.sqi');
		rename($path.'.sqd', $this->path.'.sqd');
		rename($path.'.sqi', $this->path.'.sqi');
		$this->OpenBase($this->base);

		return true;
	}

	function DeleteBase($path)
	{
		$ui = unlink($path.".sqi");
		$ud = unlink($path.".sqd");
		$ul = unlink($path.".sql");
		return $ui && $ud && $ul;
	}

	function CreateBase($path, $header = false)
	{
		if ($header === false) {
			$header = new SqBaseHeader(0);
			$header->len = 256;
			$header->reserved = 0;
			$header->num_msg = 0;
			$header->high_msg = 0;
			$header->skip_msg = 0;
			$header->high_water = 0;
			$header->uid = 1;
			$header->base = $path;
			$header->begin_frame = 0;
			$header->last_frame = 0;
			$header->free_frame = 0;
			$header->last_free_frame = 0;
			$header->end_frame = 256;
			$header->max_msg = 0;
			$header->keep_days = 0;
			$header->sz_sqhdr = 256;
		}
		if (($sqd = fopen($path.'.sqd','w')) &&
			($sql = fopen($path.'.sql','w')) &&
			($sqi = fopen($path.'.sqi','w'))) {
				$header->WriteHdr($sqd);
				fwrite($sqd, str_repeat(chr(0),124), 124);
				fclose($sqd);
				fclose($sqi);
				fclose($sql);
				@chmod($path.'.sqd',0666);
				@chmod($path.'.sqi',0666);
				@chmod($path.'.sql',0666);
				return 1;
		} else {
			if (isset($sqd) && $sqd) fclose($sqd);
			if (isset($sqi) && $sqi) fclose($sqi);
			return 0;
		}
	}
}

class SqBaseHeader
{
	var $len;
	var $reserved;
	var $num_msg;
	var $high_msg;
	var $skip_msg;
	var $high_water;
	var $uid;
	var $base;
	var $begin_frame;
	var $last_frame;
	var $free_frame;
	var $last_free_frame;
	var $end_frame;
	var $max_msg;
	var $keep_days;
	var $sz_sqhdr;
	
	function SqBaseHeader($file)
	{
		if ($file !== 0) {
			$data1 = fread($file, 24);
			$this->base = fread($file,80);
			$data2 = fread($file, 28);
			$arr1 = unpack("vlen/vres/Vnum/Vhigh/Vskip/Vhw/Vuid", $data1);
			$arr2 = unpack("Vbg/Vlast/Vfr/Vlfr/Vend/Vmax/vkeep/vsz", $data2);

			$this->len = $arr1["len"];
			$this->reserved = $arr1["res"];
			$this->num_msg = $arr1["num"];
			$this->high_msg = $arr1["high"];
			$this->skip_msg = $arr1["skip"];
			$this->high_water = $arr1["hw"];
			$this->uid = $arr1["uid"];

			$this->begin_frame = $arr2["bg"];
			$this->last_frame = $arr2["last"];
			$this->free_frame = $arr2["fr"];
			$this->last_free_frame = $arr2["lfr"];
			$this->end_frame = $arr2["end"];
			$this->max_msg = $arr2["max"];
			$this->keep_days = $arr2["keep"];
			$this->sz_sqhdr = $arr2["sz"];
		}
	}

	function WriteHdr($file)
	{
		fseek($file,0);
		$data1 = pack("vvVVVVV",
			$this->len,
			$this->reserved,
			$this->num_msg,
			$this->high_msg,
			$this->skip_msg,
			$this->high_water,
			$this->uid
		);
		$data2 = pack("VVVVVVvv",
			$this->begin_frame,
			$this->last_frame,
			$this->free_frame,
			$this->last_free_frame,
			$this->end_frame,
			$this->max_msg,
			$this->keep_days,
			$this->sz_sqhdr
		);
		if (fwrite($file, $data1, 24) != 24) return false;
		if (!fputstr($file,$this->base,80)) return false;
		if (fwrite($file, $data2, 28) != 28) return false;
	}
}

function SCOMBO_to_unix($scombo)
{
	$hour=(($scombo >> 27) & 31);
	$min=(($scombo >> 21) & 63);
	$sec=(($scombo >> 16) & 31)*2;
	$year=(($scombo >> 9) & 127)+1980;
	$month=(($scombo >> 5) & 15);
	$day=($scombo & 31);
	$res = 0;
	for ($i=16;$i;$i--) { $res.=($scombo >> $i) & 1;}
	$time_p = mktime($hour, $min, $sec, $month, $day, $year);
	return $time_p;
}

function unix_to_SCOMBO($stamp)
{
	$date_msv = explode(' ',date('d m y H i s',$stamp));
	$result = ($date_msv[0]);
	$result+= ($date_msv[1] << 5);
	$result+= (($date_msv[2]+($date_msv[2]>79?1900:2000)-1980) << 9);
	$result+= ($date_msv[3] << 27);
	$result+= ($date_msv[4] << 21);
	$result+= (($date_msv[5]/2 & 31) << 16);
	return $result;
}

function SquishHash($f)
{
	$hash = $g = 0;

	for ($p=0; $p<strlen($f); $p++)
	{
		 $hash=($hash << 4) + ord($f{$p});

		 if (($g=($hash & 0xf0000000)) != 0)
		 {
			  $hash |= $g >> 24;
			  $hash |= $g;
		 }
	}

	return ($hash & 0x7fffffff);
}
?>
