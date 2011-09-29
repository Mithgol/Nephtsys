<?
/*\
  /  This is a part of PhFiTo (aka PHP Fido Tosser)
 //  Copyright (c) Alex Kocharin, 2:50/13
///
///  This program is distributed under GNU GPL v2
///  See docs/license for details
//
/    $Id: C_opus.php,v 1.8 2007/10/14 08:29:54 kocharin Exp $
\*/

if (!function_exists('xinclude')) die();

class C_opusbase
{
	var $_list;
	var $path;
	var $extended_syntax;
	var $allscan_present;

	function C_opusbase()
	{
		$this->extended_syntax = false;
		$this->allscan_present = false;
	}
	
	function OpenBase($path)
	{
		$array = array();
		$this->path = $path;
		if ($dir = @opendir($path)):
			while($file = readdir($dir)):
			if (is_file($file) && preg_match('/^[0-9]+\.msg$/i',$file)):
				@$array[(int)strtok($file,'.')] = $file;
			endif;
			endwhile;
			uksort($array,'_int_sort');
//			while (list($key,$v) = each($array)) print "$key => $v\n";
			$this->_list = array_values($array);
//			foreach($this->_list as $f) print "$f\n";
			return 1;
		else:
			return 0;
		endif;
	}

	function GetNumMsgs()
	{
		return sizeof($this->_list);
	}

	function ReadMsgHeader($msg)
	{
		$file = $this->_list[$msg-1];
		$f = fopen($this->path.'/'.$file,'r');
		$result = new C_msgheader;
		$result->From = strtok(fread($f, 36), chr(0));
		$result->To = strtok(fread($f, 36), chr(0));
		$result->Subj = strtok(fread($f, 72), chr(0));
		$result->WDate = Fts_date_to_unix(fgetasciiz($f));
		$result->ADate = 0;
		fseek($f,186);
		$arr = unpack("vattrs", fread($f,2));
		$result->Attrs = FTS_to_Flag($arr["attrs"]);
		fclose($f);
		return $result;
	}

	function ReadMsgBody($msg)
	{
		$file = $this->_list[$msg-1];
		$size = filesize($this->path.'/'.$file);
		$f = fopen($this->path.'/'.$file,'r');
		fseek($f,0xbe);
		$result = strtok(fread($f,$size-0xbe), chr(0));
		fclose($f);
		return $result;
	}
	
	function SetAttr($msg,$attr)
	{
		$attr = Flag_to_FTS($attr);
		$file = $this->_list[$msg-1];
		$f = fopen($this->path.'/'.$file,'r+');
		fseek($f,0xba);
		fwrite($f, pack("v",$attr));
		fclose($f);
	}
	
	function CloseBase()
	{
		$this->_list = array();
	}

	function DeleteMsg($num)
	{
		if (@unlink($this->path.'/'.$this->_list[$num])) {
			$num--;
			$s = sizeof($this->_list);
			$newarr = array();
			for($i=0; $i<$num; $i++) $newarr[] = $this->_list[$i];
			for($i=$num+1; $i<$s; $i++) $newarr[] = $this->_list[$i];
			$this->_list =& $newarr;
			return true;
		} else {
			return false;
		}
	}

	function PurgeBase()
	{
		return true;
	}

	function DeleteBase($path)
	{
		if ($dir = @opendir($path)) {
			while($file = readdir($dir)) {
				if (is_file($path.'/'.$file) && preg_match('/^[0-9]+\.msg$/i',$file)) {
					@unlink($path.'/'.$file);
				}
			}
			closedir($path);
		}
		return @rmdir($path);
	}

	function WriteMessage($header,$message)
	{
		if ($sz = sizeof($this->_list))
			{ $num = (int)strtok($this->_list[$sz-1],'.'); }
		else { $num = 1; }
		while (file_exists($file = $this->path.'/'.$num.'.msg')) $num++;
		$f = fopen($file,'w');
		fputstr($f,$header->From,36);
		fputstr($f,$header->To,36);
		fputstr($f,$header->Subj,72);
		fputstr($f,$header->WDate?date('d M y  H:i:s',$header->WDate):date('d M y  H:i:s'),20);
		fwrite($f,chr(0).chr(0));
		$fromaddr = strtok($header->FromAddr,'@');
		$fz = strtok($header->FromAddr,':');
		$fn = strtok('/');
		$ff = strtok('.');
		$fp = strtok(chr(0));
		if (!$fp) $fp = 0;
		$toaddr = strtok($header->ToAddr,'@');
		$tz = strtok($header->ToAddr,':');
		$tn = strtok('/');
		$tf = strtok('.');
		$tp = strtok(chr(0));
		if (!$tp) $tp = 0;
		fwrite($f, pack("vvxxvvvvvvxxvxx",
			$tf, $ff, $fn, $tn, $tz, $fz, $tp, $fp, 
			Flag_to_FTS($header->Attrs)
		));
		fwrite($f,$message.chr(0));
		fclose($f);
		@chmod($file,0666);
	}

	function CreateBase($path)
	{
		mkdir($path);
		@chmod($path,0777);
		return 1;
	}
}

function _int_sort($a,$b)
{
	if ($a<$b) return -1;
	if ($a>$b) return 1;
	return 0;
}

?>
