<?
/*\
  /  This is a part of PhFiTo (aka PHP Fido Tosser)
 //  Copyright (c) Alex Kocharin, 2:50/13
///
///  This program is distributed under GNU GPL v2
///  See docs/license for details
//
/    $Id: M_dynroute.php,v 1.9 2011/01/08 12:21:09 kocharin Exp $
\*/

if (!function_exists('xinclude')) die();

class M_dynroute
{
	var $tree;
	var $cachefile;
	var $expire;

	function init()
	{
		// we are loading our tree only when netmail is received, 
		// so loading it at start is unnecessary 
		$this->tree = NULL;
	}

	function treeprobe()
	{
		// loading if it's needed
		if ($this->tree === NULL) {
			GLOBAL $CONFIG;
			$this->cachefile = $CONFIG->GetVar('routecache');
			$this->expire = $CONFIG->GetVar('routeexpire');
			$this->expire *= 24*3600;
			$lines = @file($this->cachefile);
			if ($lines === false) {
				$this->tree = array();
			} else {
				foreach($lines as $line) {
					$line = trim($line);
					if ($line != '') {
						list($addr, $upl, $time) = explode(' ', $line);
						if ($upl == '-') $upl = false;
						$this->tree[$addr] = array($upl, $time);
					}
				}
			}
		}
	}

	function hook_netmail($params)
	{
		GLOBAL $CurrentMessageParsed, $CONFIG;
		$this->treeprobe();
		$our_addrs = $CONFIG->GetArray('address');
		$lines = explode("\r",$CurrentMessageParsed->bottom);
		$branch = array();
		$sum = 0;
		foreach($lines as $line) {
			if (substr($line,0,5) == "\001Via ") {
				if (preg_match('/\s(\d+:\d+\/\d+(\.\d+)?)\s/',$line, $match)) {
					$branch[] = $match[1];
					$sum++;
				}
			}
		}
		$prev = false;
		$time = time();
		while($sum-- > 0) {
			if (in_array($branch[$sum], $our_addrs)) {
				$prev = false;
			} else {
				$this->tree[$branch[$sum]] = array($prev, $time);
				$prev = $branch[$sum];
			}
		}
	}

	function hook_route($params, $route)
	{
		$toaddr = $params[0];
		$this->treeprobe();
		$cr = $toaddr;
		$time = time();
		while(isset($this->tree[$cr])) {
			list($newcr, $exp) = $this->tree[$cr];
			if (abs($time-$exp) > $this->expire) {
				return $route;
			}
			if ($newcr === false) {
				return $cr;
			}
			$cr = $newcr;
		}
		return $route;
	}
	
	function done()
	{
		if ($f = @fopen($this->cachefile,'w')) {
			foreach($this->tree as $key => $arr) {
				fwrite($f, 
					$key.' '.($arr[0]===false?'-':$arr[0]).' '.$arr[1]."\n"
				);
			}
			fclose($f);
		}
		$this->tree = false;
	}
}

?>
