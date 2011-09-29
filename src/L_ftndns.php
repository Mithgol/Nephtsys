<?
/*\
  /  This is a part of PhFiTo (aka PHP Fido Tosser)
 //  Copyright (c) Alex Kocharin, 2:50/13
///
///  This program is distributed under GNU GPL v2
///  See docs/license for details
//
/    $Id: L_ftndns.php,v 1.5 2011/01/08 12:21:09 kocharin Exp $
\*/

if (!function_exists('xinclude')) die();

class C_FtnDns
{
	var $path;
	var $Addrs;
	var $Names;
	var $mdf;

	function C_FtnDns()
	{
		GLOBAL $CONFIG;
		$this->path = $CONFIG->GetVar('ftndnsbase');
		$this->mdf = false;
	}
	
	function Load()
	{
		$this->Addrs = $this->Names = array();
		$ba = $this->path.'.addrs';
		if ($fa = @fopen($ba,'r')) {
			while ($line = fgets($fa,10240)) {
				if ($line = trim($line)) {
					$arr = explode(',',$line);
					$aa = array_shift($arr);
					$this->Addrs[$aa] = $arr;
				}
			}
		}
		$bn = $this->path.'.names';
		if ($fn = @fopen($bn,'r')) {
			while ($line = fgets($fn,10240)) {
				if ($line = trim($line)) {
					$arr = explode(',',$line);
					$nn = array_shift($arr);
					$this->Names[$nn] = $arr;
				}
			}
		}
	}
	
	function GetAddrByName($name)
	{
		if (isset($this->Names[$name])) {
			return $this->Names[$name];
		} else {
			return false;
		}
	}

	function GetNameByAddr($addr)
	{
		if (isset($this->Addrs[$addr])) {
			return $this->Addrs[$addr];
		} else {
			return false;
		}
	}

	function Append($addrs,$names)
	{
		foreach($addrs as $addr) {
			foreach($names as $name) {
				if (!isset($this->Addrs[$addr]) || !in_array($name,$this->Addrs[$addr])) {
					$this->mdf = true;
					$this->Addrs[$addr][] = $name;
				}
				if (!isset($this->Names[$name]) || !in_array($addr,$this->Names[$name]))
					$this->mdf = true;
					$this->Names[$name][] = $addr;
			}
		}
	}

	function Save()
	{
		if ($this->mdf) {
			$ba = $this->path.'.addrs';
			if ($fa = @fopen($ba,'w')) {
				foreach($this->Addrs as $addr=>$arr) {
					array_unshift($arr,$addr);
					fwrite($fa,join(',',$arr)."\n");
				}
			}
			$bn = $this->path.'.names';
			if ($fn = @fopen($bn,'w')) {
				foreach($this->Names as $name=>$arr) {
					array_unshift($arr,$name);
					fwrite($fn,join(',',$arr)."\n");
				}
			}
			$this->mdf = false;
		}
	}

	function Unload()
	{
		$this->Addrs = $this->Names = array();
	}
}

?>
