<?php
/*\
  /  This is a part of PhFiTo (aka PHP Fido Tosser)
 //  Copyright (c) Alex Kocharin, 2:50/13
///
///  This program is distributed under GNU GPL v2
///  See docs/license for details
//
/    $Id: F_utils.php,v 1.14 2011/01/09 08:53:12 kocharin Exp $
\*/

if (!function_exists('xinclude')) die();

xinclude('A_common.php');

function Get_address($addr_msv)
{
$res=$addr_msv[0].':'.$addr_msv[1].'/'.$addr_msv[2].($addr_msv[3]?'.'.$addr_msv[3]:'');
return $res;
}

function getrand8()
{
	return sprintf("%08x", rand(0,0xFFFF)|(rand(0,0xFFFF)<<16));
}

function getmsgid()
{
	return sprintf("%08x", rand(0,0xFFFF)|(rand(0,0xFFFF)<<16));
}

function get_uniq_name($dir,$ext,$timestamp = false)
{
	if ($timestamp) {
		$t = time();
	} else {
		$t = rand(0,0xFFFF)|(rand(0,0xFFFF)<<16);
	}
	$d = sprintf("%08x", $t);
	while(file_exists($dir.'/'.$d.$ext)) {
		$t++;
		$d = sprintf("%08x", $t);
	}
	return $dir.'/'.$d.$ext;
}

function tosize($v)
{
 if ($v>=(10*(1<<30))) {
  return ($v>>30).'g';
 }
 elseif ($v>=(10*(1<<20))) {
  return ($v>>20).'m';
 }
 elseif ($v>=(10*(1<<10))) {
  return ($v>>10).'k';
 }
 else {
  return $v;
 }
}

function multi($str,$c)
{
	return str_repeat($str,$c);
}

function Mysql_date_to_unix($date)
{
	$msv = preg_split('/[-\: ]/',$date);
	return mktime($msv[3],$msv[4],$msv[5],$msv[1],$msv[2],$msv[0]);
}

function send_file_to_brw($name)
{
	GLOBAL $CONFIG, $Nosend_Tobrw;
	$Nosend_Tobrw = 1;
	$zipname = addslashes(basename($name));
//	print "Content-Type: application/zip; name=\"$zipname\"\r\nContent-Disposition: attachment; filename=\"$zipname\"\r\n\r\n";
	if ($CONFIG->GetVar('manual_headers')) {
		print("Content-Type: application/zip; name=\"$zipname\"\r\n");
		print("Content-Disposition: attachment; filename=\"$zipname\"\r\n");
		print("\r\n");
	} else {
		header("Content-Type: application/zip; name=\"$zipname\"");
		header("Content-Disposition: attachment; filename=\"$zipname\"");
	}
	readfile($name);
}

class C_Matrix
{
	var $Value;
	var $size;

	function set($y,$x,$v)
	{
//		for ($i=$this->size-10;$i<$y;$i++) $this->Value[] = '';
		if (!isset($this->Value[$y])) $this->Value[$y] = '';
		$s = strlen($this->Value[$y]);
		for ($i=$s;$i<=$x-1;$i++) $this->Value[$y].=' ';
		$this->Value[$y]{$x} = $v;
		if ($y>$this->size-1) { $this->size = $y+1; }
	}

	function get($y,$x)
	{
		return $this->Value[$y]{$x};
	}

	function addstr($str)
	{
		$num = sizeof($this->Value);
		$this->Value[] = $str;
		if ($num>$this->size-1) { $this->size = $num+1; }
		return $num;
	}
	
	function write($y,$x,$val)
	{
//		for ($i=$this->size-1;$i<$y;$i++) $this->Value[] = '';
		$len = strlen($val);
		$s = strlen($this->Value[$y]);
		for ($i=$s;$i<$x-1;$i++) $this->Value[$y].=' ';
		$this->Value[$y] = substr($this->Value[$y],0,$x).$val.substr($this->Value[$y],$x+$len);
		if ($y>$this->size-1) { $this->size = $y+1; }
	}

	function join()
	{
	for ($i=0;$i<=$this->size;$i++):
		$result.= $this->Value[$i]."\n";
	endfor;
	return $result;
	}
}

function prints($arg)
{
	GLOBAL $To_browser;
	$To_browser.= $arg;
}

function get_h_size($size)
{
	if ($size > 10*(1 << 30)) { $size = ($size >> 30).'G'; }
	elseif ($size > 10*(1 << 20)) { $size = ($size >> 20).'M'; }
	elseif ($size > 10*(1 << 10)) { $size = ($size >> 10).'K'; }
	return $size;
}

// this function is DEPRECATED and SHOULD be replaced by unpack(.., fread())
function fgetint($file,$len)
{
	if ($len == 1) {
		return ord(fgetc($file));
	} else if ($len == 2) {
		$a = unpack("v", fread($file, 2));
		return $a[1];
	} else if ($len == 4) {
		$a = unpack("V", fread($file, 4));
		return $a[1];
	} else {
		$result=0;
		for ($i=0;$i<$len;$i++) {
			$result+=(ord(fgetc($file)) << ($i*8));
		}
		return $result;
	}
}

// this function is DEPRECATED and SHOULD be replaced by fwrite(..., pack())
function fputint($file,$len,$data)
{
	$result='';
	if ($len == 1) {
		$result = chr($data);
	} else if ($len == 2) {
		$result = pack("v", $data);
	} else if ($len == 4) {
		$result = pack("V", $data);
	} else {
		for ($i=0;$i<$len;$i++) {
			$result.=chr(($data >> ($i*8)) & 255);
		}
	}
	fwrite($file,$result);
}

function fgetasciiz($file)
{
	$result='';
	$char = fgetc($file);
	while (ord($char)):
		$result.=$char;
		$char = fgetc($file);
	endwhile;
	return $result;
}

function fputasciiz($file,$data)
{
	fwrite($file,$data);
	fwrite($file,chr(0));
}

function fputstr($file,$str,$len)
{
	if (strlen($str)>$len) $str = substr($str,0,$len);
	$str2 = $str;
	$str2 .= str_repeat(chr(0), $len - strlen($str));
	return fwrite($file,$str2);
}

function getfirstline($message)
{
 $txt=strtok($message,chr(13));
 while (strcmp(substr($txt,0,1),chr(1))==0):
  $txt=strtok(chr(13));
 endwhile;
 return $txt;
}

function GetEchoDescrFromEcholist($area)
{
	GLOBAL $CONFIG;
	$result = '';
	if (is_readable($CONFIG->GetVar('echolist'))):
		if ($file = @fopen($CONFIG->Vars['echolist'],'r')):
			while ($line = fgets($file,1024)):
				$arr = explode(',',$line);
				if (sizeof($arr)>=5):
					list($status,$echotag,$descr,$moderator,$address) = $arr;
					if (strcasecmp($echotag,$area)==0):
						$result = $descr;
						break;
					endif;
				endif;
			endwhile;
			fclose($file);
		endif;
	endif;
	return $result;
}

function is_match_mask($mask,$what)
{
	if ($mask{0} == '/'):
		$preg = $mask;
		if (strcasecmp(substr($mask,strlen($mask)-1,1),'i')!=0):
			$preg.='i';
		endif;
	else:
		$mask = str_replace('/','\\/',str_replace('\\','\\\\',$mask));
		$mask = str_replace('.','\\.',$mask);
		$mask = str_replace('?','.',str_replace('*','.*',$mask));
		$preg = '/^'.$mask.'$/i';
	endif;
	return preg_match($preg,$what);
}

function pc_mkdir_parents($d,$umask = 0777) {
    $dirs = array($d);
    $d = dirname($d);
    $last_dirname = '';
    while($last_dirname != $d) { 
        array_unshift($dirs,$d);
        $last_dirname = $d;
        $d = dirname($d);
    }

    foreach ($dirs as $dir) {
        if (! file_exists($dir)) {
            if (! mkdir($dir,$umask)) {
                error_log("Can't make directory: $dir");
                return false;
            }
        } elseif (! is_dir($dir)) {
            error_log("$dir is not a directory");
            return false;
        }
    }
    return true;
}