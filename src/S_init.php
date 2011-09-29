<?
/*\
  /  This is a part of PhFiTo (aka PHP Fido Tosser)
 //  Copyright (c) Alex Kocharin, 2:50/13
///
///  This program is distributed under GNU GPL v2
///  See docs/license for details
//
/    $Id: S_init.php,v 1.10 2011/01/08 12:21:09 kocharin Exp $
\*/

if (!function_exists('xinclude')) die();

function aks_init($module = false, $file = 'config', $overrides = array(), $oversets = array())
{
	GLOBAL $CONFIG, $Outbound, $SRC_PATH;
	ignore_user_abort(true);
	@set_time_limit(1200);
	umask(0);
	set_error_handler('Error_handler');
	$CONFIG = new C_CONFIG();
	if (($file === false) || is_file($file)) {
		$CONFIG->Open($file, $module, $oversets);
	} else {
		die("Config not found! Exitting...\n");
	}
	foreach($overrides as $key=>$var) {
		$i='';
		while(isset($CONFIG->Vars[$key.$i])) $i++;
		while($i > 0) {
			$CONFIG->Vars[$key.($i==0?'':$i)] = 
				$CONFIG->Vars[$key.(($i-1)==0?'':$i-1)];
			$i--;
		}
		$CONFIG->Vars[$key] = $var;
	}
	if ($log = $CONFIG->GetVar("lockfile")) {
		$ctime = time();
		$time = $CONFIG->GetVar("locktime");
		if ($time === '') $time = 300;
		if (is_file($log)) {
			if ($f = fopen($log,'r')) {
				$ptime = trim(fgets($f));
				fclose($f);
				if (abs(((int)$ptime) - $ctime)>$time) {
					if ($f = fopen($log,'w')) {
						fwrite($f,$ctime);
						fclose($f);
					} else {
						die("Can't create lockfile $log! Exitting...\n");
					}
				} else {
					die("Lockfile $log exists! Exitting...\n");
				}
			} else {
				die("Lockfile $log exists and not readable! Exitting...\n");
			}
		} else {
			if ($f = fopen($log,'w')) {
				fwrite($f,$ctime);
				fclose($f);
			}
		}
	}
	initlog();
	if (isset($CONFIG->Vars['outbound'])):
		xinclude('A_common.php');
		$Outbound = new C_BSO_Outbound($CONFIG->Vars['outbound']);
	endif;
}

function aks_done()
{
	GLOBAL $CONFIG, $Outbound;
	donelog();
	unset($Outbound);
	if (function_exists('MysqlConnMgr')) {
		MysqlConnMgr(1);
	}
	if ($log = $CONFIG->GetVar("lockfile")) {
		if (is_file($log)) {
			unlink($log);
		}
	}
	$CONFIG = false;
	unset($CONFIG);
}

function get_encoding_by_name($enc)
{
	$enc = strtolower($enc);
	if (in_array($enc,array('d','dos')) || (strpos($enc,'866') !== false)) {
		return 'd';
	} else if (in_array($enc,array('k','koi','koi8','koi-8','koi8-r','koi8r'))) {
		return 'k';
	} else if (in_array($enc,array('w','windows','win')) || (strpos($enc,'1251') !== false)) {
		return 'w';
	} else if (in_array($enc,array('m','mac'))) {
		return 'm';
	} else if (in_array($enc,array('i','iso'))) {
		return 'i';
	} else {
		return false;
	}
}

function param_split($line)
{
	$num = 0;
	$kav = 0;
	$emp = 1;
	$result = array('');
	$s = strlen($line);
	for($i=0;$i<$s;$i++):
		switch($line{$i}):
			case("\""):
				if ($kav):
					$kav = 0;
					$emp = 0;
				else:
					if (!$emp):
						$result[++$num] = '';
						$emp = 1;
					endif;
					$kav = 1;
				endif;
				break;
			case(" "):
				if ($kav):
					$result[$num].= ' ';
					$emp = 0;
				else:
					if (!$emp):
				   		$result[++$num] = '';
						$emp = 1;
					endif;
				endif;
				break;
			default:
				$result[$num].= $line{$i};
				$emp = 0;
		endswitch;
	endfor;
	return $result;
}

function param_split_esc($line)
{
	$num = 0;
	$kav = 0;
	$emp = 1;
	$esc = 0;
	$result = array('');
	$s = strlen($line);
	for($i=0;$i<$s;$i++):
		switch($line{$i}):
			case("\\"):
				if ($esc) {
					$result[$num] .= "\\";
					$esc = 0;
				} else {
					$esc = 1;
				}
				break;
			case("\""):
				if ($esc) {
					$result[$num] .= "\"";
					$esc = 0;
				} else {
					if ($kav):
						$kav = 0;
						$emp = 0;
					else:
						if (!$emp):
							$result[++$num] = '';
							$emp = 1;
						endif;
						$kav = 1;
					endif;
				}
				break;
			case(" "):
				if ($esc) {
					$result[$num] .= " ";
					$esc = 0;
				} else {
					if ($kav):
						$result[$num].= ' ';
						$emp = 0;
					else:
						if (!$emp):
					   		$result[++$num] = '';
							$emp = 1;
						endif;
					endif;
				}
				break;
			default:
				$result[$num].= $line{$i};
				$emp = 0;
		endswitch;
	endfor;
	return $result;
}

function join_attrs($old,$new)
{
	for($i=0;$i<strlen($new);$i++) {
		$inv = 0;
		if ($new{$i}=='!') {
			$inv = 1;
			if (++$i >= strlen($new)) break;
		}
		if ($inv) {
			if (strpos($old,$new{$i}) !== false) 
				$old = str_replace($new{$i},'',$old);
		} else {
			if (strpos($old,$new{$i}) === false) 
				$old .= $new{$i};
		}
	}
	return $old;
}
	
function GetConfigFileName($filename)
{
	GLOBAL $CONFIG;
	$result = array();
	$array = preg_split("/\\s+/",$filename);
	$fname = false;
	$cache = false;
	while($array) {
		$arrayi = array_shift($array);
		if (strlen($arrayi) && ($arrayi{0} == '-')) {
			if ($array) {
				$val = array_shift($array);
				$result[$arrayi] = $val;
			} else {
				return $result;
			}
		} else {
			if (!isset($result[0])) {
				$result[0] = $arrayi;
			} else {
				return $result;
			}
		}
	}
	return $result;
}

class C_configfile_reader
{
	var $file;
	var $cache;
	var $nofile;
	var $iflvl;
	var $Levels;
	var $LvlsPs;
	var $linenum;
	var $interpret;
	var $filename;
	var $updatecache;
	var $cachevalid;
	var $fromenc;

	function C_configfile_reader($filename = false, $arrsyntax = false)
	{
		GLOBAL $CONFIG;
		$this->fromenc = $CONFIG->encoding;
		if ($arrsyntax) {
			$accarr = &$filename;
			unset($filename);
		} else {
			$accarr = GetConfigFileName($filename);
		}
		$filename = $accarr[0];
		$cache = isset($accarr['-b'])?$accarr['-b']:false;
		$this->filename = $filename;
		if ($filename !== false):
			$this->file = fopen($filename,'r');
		else:
			$this->nofile = 1;
		endif;
		$this->cache = false;
		$this->updatecache = 0;
		$this->cachevalid = 0;
		if ($cache !== false) {
			$this->OpenCache($cache);
		}
		$this->iflvl = 0;
		$this->Levels[0] = 1;
		$this->LvlsPs[0] = 1;
		$this->interpret = 1;
	}

	function OpenCache($cache)
	{
		GLOBAL $CONFIG;
		if (!$this->file) return 0;
		if ($this->cache = @fopen($cache,"rb")) {
			$timeorig = filemtime($this->filename);
			$time = fgetasciiz($this->cache);
			$mod = fgetasciiz($this->cache);
			$ver = fgetasciiz($this->cache);
		} else {
			$timeorig = 1;
		}
		if ($timeorig) {
			if ((!$this->cache) || 
				(in_array(basename($this->filename),$CONFIG->NoCache)) || 
				($timeorig != $time) || 
				($CONFIG->_module != $mod) || 
				(VERSION != $ver) || 
				($CONFIG->config_modified)) 
			{
				$CONFIG->config_modified = true;
				$this->cache = fopen($cache,'wb');
				if ($this->cache) {
					unset($CONFIG->NoCache[basename($cache)]);
					$this->updatecache = 1;
					fputasciiz($this->cache,$timeorig);
					fputasciiz($this->cache,$CONFIG->_module);
					fputasciiz($this->cache,VERSION);
				} else {
					$this->updatecache = 0;
				}
			} else {
				$this->cachevalid = 1;
			}
		}
	}

	function ReadPair($line = false)
	{
		if ($line !== false) return $this->_readstr($line);
		if ($this->cachevalid) {
			return $this->_readbin();
		}
		while(1) {
			$line = $this->_readstr();
			if ($line === false) return false;
			if ($line = trim($line)) {
				$arr = preg_split("/\\s+/",$line,2);
				$arr[0] = strtolower(rtrim($arr[0]));
				$arr[1] = isset($arr[1])?ltrim($arr[1]):'';
				if ($this->updatecache) {
					$this->_writebin($arr[0],$arr[1]);
				}
				return $arr;
			}
		}
	}

	function _writebin($key,$val)
	{
		fwrite($this->cache,$key.chr(0).$val.chr(0));
	}

	function _readbin()
	{
		if (feof($this->cache)) return false;
		$arr[] = fgetasciiz($this->cache);
		if (feof($this->cache)) return false;
		$arr[] = fgetasciiz($this->cache);
		$this->linenum++;
		return $arr;
	}

	function _readstr($line = false)
	{
		if (!$this->nofile):
			if (!$this->file) return false;
			$line = fgets($this->file,10240);
			if ($this->fromenc !== false) {
				$line = convert_cyr_string($line,$this->fromenc,'d');
			}
			$this->linenum++;
		endif;
		if (!$line) return false; 
		$line = trim($line);
		if ($this->interpret) {
			$line = $this->read_and_translate($line);
		}

		if (strcmp(substr($line,0,3),'if ')==0):
			if ($this->Levels[$this->iflvl++]):
				$this->LvlsPs[$this->iflvl] = 
					$this->Levels[$this->iflvl] = 
					$this->is_equal(substr($line,3));
			else:
				$this->Levels[$this->iflvl] = 0;
				$this->LvlsPs[$this->iflvl] = 1;
			endif;
			return '';
		elseif (strcmp(substr($line,0,7),'elseif ')==0):
			if (!$this->LvlsPs[$this->iflvl]):
				$this->LvlsPs[$this->iflvl] = $this->Levels[$this->iflvl] = 
					$this->is_equal(substr($line,7));
			else:
#				$this->Levels[$this->iflvl] = $this->LvlsPs[$this->iflvl] = 0;
				$this->Levels[$this->iflvl] = 0;
			endif;
			return '';
		elseif (strcmp(substr($line,0,4),'else')==0):
			if (!$this->LvlsPs[$this->iflvl]):
				$this->LvlsPs[$this->iflvl] = $this->Levels[$this->iflvl] = 1;
			else:
#				$this->Levels[$this->iflvl] = $this->LvlsPs[$this->iflvl] = 0;
				$this->Levels[$this->iflvl] = 0;
			endif;
			return '';
		elseif (strcmp(strtok($line,' '),'endif')==0):
			$this->iflvl--;
			return '';
		elseif (strcasecmp(strtok($line,' '),'interpret')==0):
			$this->interpret = strtok(' ');
			return '';
		elseif ($this->Levels[$this->iflvl]):
			return $line;
		else:
			return '';
		endif;
	}

	function is_equal($str)
	{
		if (($p = strpos($str,'=='))===false) return 1;
		$a = substr($str,0,$p);
		$b = substr($str,$p+2);
		return (strcasecmp($a,$b)==0);
	}
	
	function insert_constant($spec,$chars,$b,$e)
	{
		GLOBAL $CONFIG;
		$const = substr($chars,$b+1,$e-$b-1);
		$value = isset($CONFIG->Constants[$const])?
			$CONFIG->Constants[$const]:'';
		$spec_n = substr($spec,0,$b);
		$s = strlen($value);
		for ($i=0;$i<$s;$i++) $spec_n.= chr(0);
		$spec_n.= substr($spec,$e+1);
		$chars = substr($chars,0,$b).$value.substr($chars,$e+1);
		return array($spec_n,$chars);
	}

	function read_and_translate($line)
	{
		$ls = $rs = false;
		$l_chars = '';
		$l_spec = '';
		$spec = 0;
		$splen = 0;

		$s = strlen($line);
		for ($i=0;$i<$s;$i++):
			$c = $line{$i};
			if ($c === '\\'):
				if ($spec):
					$spec = 0;
					$l_chars.= '\\';
					$l_spec.= chr(0);
					$splen++;
				else:
					$spec = 1;
				endif;
			elseif(($b = ($c === '[')) || ($c === ']')):
				if ($spec):
					$l_chars.= $c;
					$l_spec.= chr(0);
					$splen++;
					$spec = 0;
				else:
					if ($b):
						if ($ls === false){ $ls = $splen; };
					else:
						if ($rs === false){ $rs = $splen; };
					endif;
					$l_chars.= chr(0);
					$l_spec.= $c;
					$splen++;
				endif;
			elseif($c === '#'):
				if ($spec):
					$l_chars.= $c;
					$l_spec.= chr(0);
					$splen++;
					$spec = 0;
				else:
					break;
				endif;
			else:
				if ($spec):
					$l_chars.='\\';
					$l_spec.= chr(0);
					$splen++;
					$spec = 0;
				endif;
				$l_chars.= $c;
				$l_spec.= chr(0);
				$splen++;
			endif;
		endfor;
		if ($spec):
			$l_chars.='\\';
			$l_spec.= chr(0);
			$splen++;
			$spec = 0;
		endif;
		$s = $splen;
		$bt = -1;
		$et = -1;
#print $line."\n";
#print str_replace(chr(0),'*',$l_spec)."\n";
#print str_replace(chr(0),'*',$l_chars)."\n";
		if ($ls === false) $ls = 0x7fffffff;
		if ($rs === false) $rs = 0x7fffffff;
		while (true):
			if ($ls == $rs):
				break;
			elseif ($ls < $rs):
				$bt = $ls;
				$et = -1;
				$ls = strpos($l_spec,'[',$ls+1);
				if ($ls === false) { $ls = 0x7fffffff; };
			else:
				$et = $rs;
				if ($bt==-1):
					$rs = strpos($l_spec,']',$rs+1);
					if ($rs === false) { $rs = 0x7fffffff; };
				else:
					list($l_spec,$l_chars) = 
						$this->insert_constant($l_spec,$l_chars,$bt,$et);
					$ls = strpos($l_spec,'[');
					$rs = strpos($l_spec,']');
					if ($ls === false) $ls = 0x7fffffff;
					if ($rs === false) $rs = 0x7fffffff;
					$bt = -1;
				endif;
			endif;
		endwhile;
		$result = '';
		$o = false;
		$l = strpos($l_chars,chr(0));
		while ($l!==false):
			$result.= substr($l_chars,0,$l).$l_spec{$l};
			$o = $l;
			$l = strpos($l_chars,chr(0),$l+1);
		endwhile;
		if ($o === false) $o = 0;
		$result.= substr($l_chars,$o);
	//	$line=trim(strcmp(substr($line,0,1),'#')==0?'':strtok($line,'#'));
		return $result;
	}

	function Close()
	{
		if ($this->file) fclose($this->file);
	}
}

function Error_handler($code, $msg, $errfile, $errline, $vars)
{	
	GLOBAL $CONFIG;
#	print "$errfile:$errline:$msg\n";
	if (isset($CONFIG) && isset($CONFIG->Vars['errlogfile'])): 
		$arr = explode(' ',$CONFIG->Vars['errlogfile'],2);
		$file = $arr[0];
		$param = isset($arr[1])?$arr[1]:'';
		$writelog = 1;
		$errcrc = dechex(crc32("$code $msg $errfile $errline"));
		if ($writelog && (strstr($param,'-h')) && (error_reporting()==0)) 
			$writelog = 0;
		if ($writelog && (strstr($param,'-q')) && is_readable($file)):
			if ($f = @fopen($file,'r')):
				while($line = fgets($f,1024)):
					if (strcasecmp('+'.$errcrc,trim($line))==0):
						$writelog = 0;
						break;
					endif;
				endwhile;
				fclose($f);
			endif;
		endif;
		if ($writelog && ($f = fopen($file,'a'))):
			fwrite($f,"+$errcrc\n");
			fwrite($f,"Error code: $code\n");
			fwrite($f,"Error msg: $msg\n");
			fwrite($f,"Error file: $errfile\n");
			fwrite($f,"Error line: $errline\n");
			fwrite($f,"=================\n");
			fclose($f);
			@chmod($file,0666);
		endif;
	endif;
	$msg = "(code $code) Error in $errfile:$errline message:\"$msg\"";
	tolog('-',$msg);
}

class C_LOG
{
	var $file;
	var $lvls;
	var $br;
	var $stdout;
	var $filename;
	var $encoding;

	function C_LOG($fname,$lvls,$br)
	{
		GLOBAL $CONFIG;
		$this->encoding = get_encoding_by_name($CONFIG->GetVar('log_encoding'));
		$this->stdout = (strcasecmp($fname,'stdout')==0);
		$this->lvls = $lvls;
		$br = str_replace('\r',"\r",$br);
		$br = str_replace('\n',"\n",$br);
		$this->br = $br;
		$this->filename = $fname;
		if (!$this->stdout && (!$this->file = fopen($fname,'a')))
			{ die("Open log file \"$fname\" failed!"); }

			$Time=date("r");
			$this->write('1'," *** Started: $Time ({$CONFIG->Constants['module']}/".VERSION.")");
	}
	
	function write($lvl,$message)
	{
		if ($this->encoding !== false)
			$message = convert_cyr_string($message,'d',$this->encoding);
		if (strpos($this->lvls,$lvl)!==false):
			$date = date("d M, H:i:s");
			if ($this->stdout):
				print("$lvl $date $message".$this->br);
			else:
				fwrite($this->file,"$lvl $date $message".$this->br);
			endif;	
		endif;
	}

	function done()
	{
		$Time = date("r, t");
		$this->write('1'," *** End: $Time");
		if (!$this->stdout) { 
			fclose($this->file); 
			@chmod($this->filename,0666);
		}
	}

	function refseek()
	{
		if (!$this->stdout):
		fseek($this->file,0,SEEK_END);
		endif;
	}
}

function initlog()
{
	GLOBAL $CONFIG, $LOGS, $LOGS_active;
	$LOGS = array();
	if (isset($CONFIG) && ($arr = $CONFIG->GetArray('logfile'))):
		foreach ($arr as $logfile):
			$logfarr = param_split($logfile);
			$fname = $logfarr[0];
			$lvls = isset($logfarr[1])?$logfarr[1]:'';
			$br = isset($logfarr[2])?$logfarr[2]:'\n';
			$LOGS[] = new C_LOG($fname,$lvls,$br);
		endforeach;
	endif;
	$LOGS_active = true;
}

function tolog($lvl,$message)
{
	GLOBAL $LOGS, $LOGS_active;
	if ($LOGS_active):
		for ($i=0;$i<sizeof($LOGS);$i++):
			if ($LOGS[$i] !== false) {
				$LOGS[$i]->write($lvl,$message);
			}
		endfor;
	endif;
}

function donelog()
{
	GLOBAL $LOGS, $LOGS_active;
	if ($LOGS_active):
		for($i=0;$i<sizeof($LOGS);$i++):
			$LOGS[$i]->done();
			$LOGS[$i] = false;
		endfor;
		$LOGS_active = false;
	endif;
	$LOGS = array();
}

function refseeklog()
{
	GLOBAL $LOGS;
	for($i=0;$i<sizeof($LOGS);$i++) { 
		$LOGS[$i]->refseek();
	}
}

function stopwatch($act,$id='0')
{
	STATIC $data;
	STATIC $sec;
	if ($act == 0) { // stop
		$cur = (float)microtime();
		$past = $sec[$id];
		if ($past>=$cur) $cur+=1;
		$data[$id] += $cur-$past;
		return;
	} else if ($act == 1) { // start
		$sec[$id] = (float)microtime();
		return;
	} else { // print
		if ($data) {
			foreach($data as $a=>$b) { 
				print "stopwatch $a: $b\n";
			}
			return;
		}
	}
}

?>
