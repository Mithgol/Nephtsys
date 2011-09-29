<?
/*\
  /  This is a part of PhFiTo (aka PHP Fido Tosser)
 //  Copyright (c) Alex Kocharin, 2:50/13
///
///  This program is distributed under GNU GPL v2
///  See docs/license for details
//
/    $Id: S_confedit.php,v 1.5 2011/01/08 12:21:09 kocharin Exp $
\*/

if (!function_exists('xinclude')) die();

function EditConfigCmd($cmds)
{
	foreach($cmds as $cmd) {
		if ($cmd !== '' && $cmd{0} == '+') {
			$cmd = substr($cmd,1);
			$arr = explode("=", $cmd, 2);
			if (sizeof($arr) == 2) {
				list($key, $value) = $arr;
			} else {
				$key = $arr[0];
				$value = '';
			}
			Config_AddItem($key, $value);
		} else if ($cmd !== '' && $cmd{0} == '-') {
			$cmd = substr($cmd,1);
			$arr = explode("=", $cmd, 2);
			if (sizeof($arr) == 2) {
				list($key, $value) = $arr;
			} else {
				$key = $arr[0];
				$value = '';
			}
			$len = strlen($key)-1;
			while($len > 0 && 
				ord($key{$len}) >= ord('0') && ord($key{$len}) <= ord('9')
			) $len--;
			if ($len != strlen($key)-1) {
				$num = substr($key, $len+1);
			} else {
				$num = false;
			}
			$key = substr($key, 0, $len+1);
			if ($key === '') continue;
			if ($num === false) {
				Config_DelItem($key);
			} else {
				Config_DelItem($key, $num);
			}
		} else if ($cmd !== '' && $cmd{0} == '[') {
			$cmd = substr($cmd,1);
			$arr = explode("]", $cmd, 2);
			if (sizeof($arr) == 2) {
				list($link, $param) = $arr;
			} else {
				continue;
			}
			if ($link === '') continue;
			$arr = explode("=", $param, 2);
			if (sizeof($arr) == 2) {
				list($key, $value) = $arr;
			} else {
				$key = $arr[0];
				$value = '';
			}
			if ($key === '') continue;
			LinkSetVar($link, $key, $value);
		} else {
			$arr = explode("=", $cmd, 2);
			if (sizeof($arr) == 2) {
				list($key, $value) = $arr;
			} else {
				$key = $arr[0];
				$value = '';
			}
			$len = strlen($key)-1;
			while($len > 0 && 
				ord($key{$len}) >= ord('0') && ord($key{$len}) <= ord('9')
			) $len--;
			if ($len != strlen($key)-1) {
				$num = substr($key, $len+1);
			} else {
				$num = 0;
			}
			$key = substr($key, 0, $len+1);
			if ($key === '') continue;
			Config_ModItem($key, $value, $num);
		}
	}
}

function Config_AddItem($key,$value)
{
	GLOBAL $CONFIG;
	$lokey = strtolower($key);
	if (isset($CONFIG->markers[$lokey])) {
		list($filenum,$line) = $CONFIG->markers[$lokey];
		$file = $CONFIG->_state_files[$filenum];
		$line--;
	} elseif (isset($CONFIG->markers['all'])) {
		list($filenum,$line) = $CONFIG->markers['all'];
		$file = $CONFIG->_state_files[$filenum];
		$line--;
	} else if (isset($CONFIG->Vars[$lokey])) {
		$num='';
		while (isset($CONFIG->Vars[$lokey.($num+1)])) $num++;
		list($filenum,$line) = $CONFIG->_state_cache[$lokey.$num];
		$file = $CONFIG->_state_files[$filenum];
	} else {
		$file = $CONFIG->_mainfile;
		$line = -1;
	}
	$num='';
	while (isset($CONFIG->Vars[$lokey.$num])) $num++;

	if (!isset($filenum)) {
		$filenum = $CONFIG->_get_state_files_num($file);
	}

	if ($line == -1) {
		$res = AppendLine($file,$key.' '.$value);
		$line = $res;
	} else {
		$res = InsertLine($file,$key.' '.$value,$line);
	}
	if ($res !== false) {
		$s=sizeof($CONFIG->_state_cache);
		foreach(array_keys($CONFIG->_state_cache) as $k) {
			$state =& $CONFIG->_state_cache[$k];
			if ($state[0] == $filenum) {
				if ($state[1] > $line) {
					$state[1]++;
				}
			}
		}
		foreach(array_keys($CONFIG->markers) as $k) {
			$state =& $CONFIG->markers[$k];
			if ($state[0] == $filenum) {
				if ($state[1] > $line) {
					$state[1]++;
				}
			}
		}
		$CONFIG->Vars[$lokey.$num] = $value;
		$CONFIG->_state_cache[$lokey.$num] = array($filenum, $line);
	}
}

function Config_DelItem($key,$num = 0)
{
	GLOBAL $CONFIG;
	$key = strtolower($key);
	if ($num == 0) $num = '';
	if (isset($CONFIG->_state_cache[$key.$num])) {
		list($filenum,$line) = $CONFIG->_state_cache[$key.$num];
		$s=sizeof($CONFIG->_state_cache);
		foreach(array_keys($CONFIG->_state_cache) as $k) {
			$state =& $CONFIG->_state_cache[$k];
			if ($state[0] == $filenum) {
				if ($state[1] > $line) {
					$state[1]--;
				}
			}
		}
		foreach(array_keys($CONFIG->markers) as $k) {
			$state =& $CONFIG->markers[$k];
			if ($state[0] == $filenum) {
				if ($state[1] > $line) {
					$state[1]--;
				}
			}
		}
		$file = $CONFIG->_state_files[$filenum];
		while(isset($CONFIG->Vars[$key.($num+1)])) {
			$CONFIG->Vars[$key.$num] = $CONFIG->Vars[$key.($num+1)];
			$CONFIG->_state_cache[$key.$num] = $CONFIG->_state_cache[$key.($num+1)];
			$num++;
		}
		unset($CONFIG->Vars[$key.$num]);
		unset($CONFIG->_state_cache[$key.$num]);
		return RemoveLine($file,$line-1);
	} else {
		return false;
	}
}

function Config_ModItem($key,$newvalue,$num)
{
	GLOBAL $CONFIG;
	$key = strtolower($key);
	if ($num == 0) $num = '';
	if (isset($CONFIG->_state_cache[$key.$num])) {
		list($filenum,$line) = $CONFIG->_state_cache[$key.$num];
		$file = $CONFIG->_state_files[$filenum];
		$line--;
		$lines = @file($file);
		if (!isset($lines[$line])) return false;
		$s = $lines[$line];
		$p = 0;
		$sa = array(" ","\t");
		while(isset($s{$p}) && in_array($s{$p},$sa)) $p++;
		while(isset($s{$p}) && !in_array($s{$p},$sa)) $p++;
		while(isset($s{$p}) && in_array($s{$p},$sa)) $p++;
		$s = substr($s,0,$p).$newvalue."\n";
		$lines[$line] = $s;
		if ($f = fopen($file,'w')) {
			fwrite($f,join("",$lines));
			fclose($f);
			$CONFIG->Vars[$key.$num] = $newvalue;
			return true;
		} else {
			return false;
		}
	} else {
		return false;
	}
}

function LinkSetVar($link,$key,$newvalue)
{
#	print "------------- $link $key $newvalue\n";
	GLOBAL $CONFIG;
	if (!isset($CONFIG->Links->Vals[$link])) return 0;
	$arr = isset($CONFIG->Links->Vals_loc[$link][$key])?$CONFIG->Links->Vals_loc[$link][$key]:$CONFIG->Links->Vals_defloc[$link];
	list($file,$line,$exists) = $arr;
	if (!is_writable($file)) return 0;
	if ($f = @fopen($file,'r')):
		$l = 0;
		$lines = array();
		while ($l = fgets($f,1024)) $lines[] = chop($l);
		fclose($f);
		if ($exists):
			$cl = $lines[$line-1];
			$key = strtok($cl,' ');
			$cl = $key.' '.$newvalue;
			$lines[$line-1] = $cl;
		endif;
		while((trim($lines[$line-2])=='') && ($line>1)) $line--;
		if ($f = @fopen($file,'w')):
			for($i=0,$s=sizeof($lines);$i<$s;$i++):
				if (!$exists && ($i==$line-1)) fwrite($f,$key.' '.$newvalue."\n");
				fwrite($f,$lines[$i]."\n");
			endfor;
			fclose($f);
			$CONFIG->Links->Vals[$link][$k=strtolower($key)] = $newvalue;
			$CONFIG->Links->Vals_loc[$link][$k] = array($file,$line,true);
			if (!$exists):
				reset($CONFIG->Links->Vals);
				while(list($link,$def) = each($CONFIG->Links->Vals_loc)):
					reset($def);
					$chg = 0;
					while(list($key,$arr) = each($def)):
						if ((strcmp($arr[0],$file)==0) && ($arr[1]>$line)):
							$def[$key] = array($arr[0],$arr[1]+1,$arr[2]);
							$chg = 1;
						endif;
					endwhile;
					if ($chg) $CONFIG->Links->Vals_loc[$link] = $def;
				endwhile;
				reset($CONFIG->Links->Vals_defloc);
				while(list($link,$arr) = each($CONFIG->Links->Vals_defloc)):
					if ((strcmp($arr[0],$file)==0) && ($arr[1]>=$line)):
						$arr[1]++;
						$CONFIG->Links->Vals_defloc[$link] = $arr;
					endif;
				endwhile;
			endif;
			return 1;
		else:
			return 0;
		endif;
	else:
		return 0;
	endif;
}

function AppendLine($tofile,$line)
{
	$result = 1;
	if (is_file($tofile) && ($f = @fopen($tofile,'r+'))):
		while(fgets($f,10240)) $result++;
		fseek($f,-1,2);
		$last = fread($f,1);
		if (ord($last) != 10) fwrite($f,"\n");
		fwrite($f,$line."\n");
		fclose($f);
		return $result;
	elseif ($f = @fopen($tofile,'a')):
		fwrite($f,$line."\n");
		fclose($f);
		return $result;
	else:
		return false;
	endif;
}

function InsertLine($tofile,$line,$num)
{
	$lines = @file($tofile);
	$s = sizeof($lines);
	$lines[$s-1] = rtrim($lines[$s-1],"\n")."\n";
	for($i=$s;$i>$num;$i--) {
		$lines[$i] = $lines[$i-1];
	}
	$lines[$num] = $line."\n";
	if ($f = fopen($tofile,'w')) {
		fwrite($f,join("",$lines));
		fclose($f);
	} else {
		return false;
	}
}

function RemoveLine($tofile,$num)
{
	$lines = @file($tofile);
	$s = sizeof($lines);
	if ($num>$s) $num = $s;
	for($i=$num;$i<$s-1;$i++) {
		$lines[$i] = $lines[$i+1];
	}
	unset($lines[$s-1]);
	if ($f = fopen($tofile,'w')) {
		fwrite($f,join("",$lines));
		fclose($f);
	} else {
		return false;
	}
}

function ReplaceLine($tofile,$line,$num)
{
	$lines = @file($tofile);
	$lines[$num] = $line;
	if ($f = fopen($tofile,'w')) {
		fwrite($f,join("",$lines));
		fclose($f);
	} else {
		return false;
	}
}

?>
