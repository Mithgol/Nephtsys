<?php
/*\
  /  This is a part of PhFiTo (aka PHP Fido Tosser)
 //  Copyright (c) Alex Kocharin, 2:50/13
///
///  This program is distributed under GNU GPL v2
///  See docs/license for details
//
/    $Id: S_config.php,v 1.23 2011/01/08 22:55:49 kocharin Exp $
\*/

if (!function_exists('xinclude')) die();

xinclude('F_utils.php');
xinclude('S_init.php');
xinclude('S_version.php');

class C_UserDef
{
	var $Vars;
	var $exists;
	
	function OpenCfg($Base,$UID)
	{
		$this->exists = 0;
		$reader = new C_configfile_reader($Base);
		while (($pair = $reader->ReadPair())!==false)
		{
			list($key,$value) = $pair;
			if ($key == 'user'):
				if ($loaduser = (strcmp($UID,$value)==0)):
					$this->Vars['uid'] = $value;
					$this->exists = 1;
				endif;
			elseif($loaduser):
				$i = '';
				while (@$this->Vars[$key.$i]) $i++;
				$this->Vars[$key.$i] = $value;
			endif;
		}
		$reader->Close();
	}
}

class C_Links
{
	var $_filename;
	var $_line;
	var $LinkDef;
	var $Vals;
	var $Vals_loc;
	var $Vals_defloc;
	var $Curr_ms;
	var $Curr_ms_loc;
	
	function OpenCfg($filename)
	{
		$linknew = 0;
		$this->_filename = $filename;
		$reader = new C_configfile_reader($filename);
		while (($pair = $reader->ReadPair())!==false)
		{
			@list($key,$value) = $pair;
			if ($key == 'linkdefaults'):
				$this->Addlink($linknew);
				$linknew=0;
			elseif ($key == 'link'):
				$this->_line = $reader->linenum;
				$this->Addlink($linknew);
				$linknew=1;
				$this->Curr_ms['sysop']=$value;
			else:
				$i = '';
				while (isset($this->Curr_ms[$key.$i])) $i++;
				$this->Curr_ms[$key.$i] = $value;
				$this->Curr_ms_loc[$key.$i] = array($filename,$reader->linenum,true);
			endif;
		}
		$this->_line = $reader->linenum;
		$this->AddLink($linknew);
		$reader->Close();
		unset($this->Curr_ms);
	}

	function AddLink($linknew)
	{
		if ($this->Curr_ms)
		{
			if ($linknew) {
				if ($this->LinkDef) {
					reset($this->LinkDef);
					while (list($key,$value)=each($this->LinkDef)) {
						if (!isset($this->Curr_ms[$key])) {
							$this->Curr_ms[$key] = $value;
						}
					}
				}
				$aka = $this->Curr_ms['aka'];
				$this->Vals[$aka]=$this->Curr_ms;
				$this->Vals_loc[$aka]=$this->Curr_ms_loc;
				$this->Vals_defloc[$aka] = array($this->_filename,$this->_line,false);
			} else {
				reset($this->Curr_ms);
				while (list($key,$value)=each($this->Curr_ms)) {
					$this->LinkDef[$key]=$value;
				}
			}
		}
		$this->Curr_ms = array();
	}
}

class C_AreaDef
{
	var $EchoTag;
	var $Path;
	var $Status;
	var $Linkattr;
	var $Defattr;
	var $Defattr_up;
	var $Defattr_orig;
	var $Linkattr_orig;
	var $Linkattr_up;
	var $Options;
	var $GroupDescr;
	var $l_file;
	var $l_line;
	var $lt_file;
	var $lt_line;
	var $DefProp;
	var $Property;

	function Edit_ChangeMode($link,$mode,$uns)
	{
		GLOBAL $CONFIG;
		$file = $this->lt_file;
		if (!$file || (is_file($file) && !is_writable($file)))
			$file = $this->l_file;
		if (!$file || (is_file($file) && !is_writable($file)))
			$file = $CONFIG->GetVar('autoareacreatetable');
		if (!$file || (is_file($file) && !is_writable($file))) 
			$file = $CONFIG->GetVar('autoareacreatefile');
		$line = $this->lt_line;
		if (is_file($file) && !is_writable($file)) return 0;
		if ($f = @fopen($file,'r')):
			$l = 0;
			$lines = array();
			while ($l = fgets($f,1024)) $lines[] = chop($l);
			fclose($f);
			$cl = ($line?$lines[$line-1]:
				'EchoMode '.$this->EchoTag);

			$current = isset($this->Linkattr[$link])?
				$this->Linkattr[$link]:
				$this->Defattr;
			$uplevel = isset($this->Linkattr_up[$link])?
				$this->Linkattr_up[$link]:
				$this->Defattr;
			$origin = isset($this->Linkattr_orig[$link])?
				$this->Linkattr_orig[$link]:'';
			if ($uns) {
				$mustbe = str_replace($mode,'',$current);
			} else if (strpos($current,$mode)===false) {
				$mustbe = $current.$mode;
			} else {
				$mustbe = $current;
			}
			$newattr = '';
			$eq = 1;

			for($i=strlen($mustbe)-1;$i>=0;$i--) {
				if (strpos($uplevel,$mustbe{$i}) === false) {
					$eq = 0;
					break;
				}
			}
			if ($eq) {
				for($i=strlen($uplevel)-1;$i>=0;$i--) {
					if (strpos($mustbe,$uplevel{$i}) === false) {
						$eq = 0;
						break;
					}
				}
			}
			if ($eq) {
				$newattr = '';
			} else {
				$newattr = str_replace('!'.$mode,'',$origin);
				$newattr = str_replace($mode,'',$newattr);
				if ($uns) {
					if (strpos($uplevel,$mode)!==false) {
						$newattr .= '!'.$mode;
					}
				} else {
					if (strpos($uplevel,$mode)===false) {
						$newattr = $newattr.$mode;
					}
				}
			}
			
			$sts = explode(' ',$cl);
			$w = 0;
			for ($i=sizeof($sts)-1;$i>=0;$i--):
				$p = strrpos($sts[$i],':');
				if (strcmp(substr($sts[$i],0,$p),$link)==0):
					if ($newattr == '') {
						$sts[$i] = chr(0);
					} else {
						$sts[$i] = substr($sts[$i],0,$p+1).$newattr;
					}
					$w = 1;
				endif;
			endfor;
			if (!$w && ($newattr !== '')) {
				$sts[] = $link.':'.$newattr;
			}
			$cl = '';
			if ((($s = sizeof($sts)) != 2) && 
				(strcasecmp($this->EchoTag,$sts[1])==0)) {
					for($i=0;$i<$s;$i++):
						if ($sts[$i]!=chr(0)):
							$cl.=$sts[$i].' ';
						endif;
					endfor;
					$cl = substr($cl,0,strlen($cl)-1);
			} else {
				$cl = chr(0);
			}

			$lines[$line-1] = $cl;
			if ($f = @fopen($file,'w')):
				foreach($lines as $l) fwrite($f,$l."\n");
				fclose($f);
				$this->Linkattr[$link] = $mustbe;
				$this->Linkattr_orig[$link] = $newattr;
				return 1;
			else:
				return 0;
			endif;
		else:
			return 0;
		endif;
	}

	function C_AreaDef($line=false,$group=false)
	{
		if ($line !== false) {
			$this->Init($line,$group);
		}
	}

	function InitBin(&$binary)
	{
		$this->Options = array();
		$this->Linkattr = array();
		$this->DefProp = array();
		$this->Property = array();

		$array = explode(chr(1),$binary);
		unset($binary);
		$this->l_file = array_shift($array);
		$this->l_line = array_shift($array);
		$this->GroupDescr = array_shift($array);
		$this->Status = array_shift($array);
		$this->EchoTag = array_shift($array);
		$this->Path = array_shift($array);
		while($array && ($k = array_shift($array)) &&
				$array && ($v = array_shift($array))) {
			$this->Options[$k] = $v;
		}
	}
	
	function InitBinMode(&$binary)
	{
		$array = explode(chr(1),$binary);
		unset($binary);
		$this->lt_file = array_shift($array);
		$this->lt_line = array_shift($array);
		$this->Linkattr = unserialize(array_shift($array));
		$this->Defattr = unserialize(array_shift($array));
		$this->Defattr_up = unserialize(array_shift($array));
		$this->Defattr_orig = unserialize(array_shift($array));
		$this->Linkattr_orig = unserialize(array_shift($array));
		$this->Linkattr_up = unserialize(array_shift($array));
		$this->DefProp = unserialize(array_shift($array));
		$this->Property = unserialize(array_shift($array));
	}

	function Init($line,$group=false)
	{
		$vkh = $dou = false;
		$line2 = '';
		if ($line) {
			for ($i=0,$s=strlen($line); $i<$s; $i++) {
				$c = $line{$i};
				if ($c == '"') {
					$vkh = !$vkh;
					$c = '';
					$dou = false;
				} elseif (($c == " ") || ($c == "\t")) {
					if ($vkh) {
						$c = ($c == " "?chr(1):chr(2));
					} else if ($dou) {
						$c = '';
					} else {
						$dou = true;
					}
				} else {
					$dou = false;
				}
				$line2.=$c;
			}
			$msv = explode(' ',$line2);
			$msv2 = array();
			$s = sizeof($msv);
			for ($i=0;$i<$s;$i++) {
				$msv2[] = str_replace(chr(2),"\t",str_replace(chr(1),' ',$msv[$i]));
			}
			$msv = $msv2;
			unset($msv2);
			if ($group) {
				if (isset($msv[0])) {
					if ((strlen($msv[0])<2)||('-' != $msv[0]{0})) {
						$this->GroupDescr = array_shift($msv);
					}
				}
			} else {
				$this->Status = array_shift($msv);
				$this->EchoTag = array_shift($msv);
				$this->Path = array_shift($msv);
			}
			$this->Options = array();
			$this->Linkattr = array();
			$this->DefProp = array();
			$this->Property = array();
			for ($i=0,$s=sizeof($msv); $i<$s; $i++) {
				$msvn = &$msv[$i];
				if ($msvn{0} == '-') {
					$i++;
					$this->Options[$msvn] = $msv[$i];
				}
				unset($msvn);
			}
		}
	}

	function GetBinProp()
	{
		$data = '';
		$data .= $this->l_file.chr(1);
		$data .= $this->l_line.chr(1);
		$data .= $this->GroupDescr.chr(1);
		$data .= $this->Status.chr(1);
		$data .= $this->EchoTag.chr(1);
		$data .= $this->Path.chr(1);
		foreach($this->Options as $k=>$v) {
			$data .= $k.chr(1).$v.chr(1);
		}
		return $data;
	}	
	
	function GetBinMode() //($binary)
	{
		$data = '';
		$data .= $this->lt_file.chr(1);
		$data .= $this->lt_line.chr(1);
		$data .= serialize($this->Linkattr).chr(1);
		$data .= serialize($this->Defattr).chr(1);
		$data .= serialize($this->Defattr_up).chr(1);
		$data .= serialize($this->Defattr_orig).chr(1);
		$data .= serialize($this->Linkattr_orig).chr(1);
		$data .= serialize($this->Linkattr_up).chr(1);
		$data .= serialize($this->DefProp).chr(1);
		$data .= serialize($this->Property).chr(1);
		return $data;
	}

	function SplitModeProp($omode)
	{
		$known_props = array('F', 'W');
		$modelen = strlen($omode);
		$newmode = '';
		$props = array();
		$curr = false;
		$qua = false;
		for($i=0; $i<$modelen; $i++) {
			if ((ord('0') <= ord($omode{$i})) &&
			            (ord($omode{$i}) <= ord('9'))) {
				if ($curr !== false) {
					if (isset($props[$curr])) {
						$props[$curr] *= 10;
						$props[$curr] += $omode{$i};
					} else {
						$props[$curr] = $omode{$i};
					}
				}
			} else if (in_array($omode{$i}, $known_props)) {
				$curr = $omode{$i};
				$props[$curr] = false;
				$qua = false;
			} else if ($omode{$i} == '!') {
				$qua = true;
			} else {
				if ($qua) {
					$qua = false;
					$newmode .= '!';
				}
				$curr = false;
				$newmode .= $omode{$i};
			}
		}
		return array($newmode, $props);
	}

	function GetMode($arr,$emode)
	{
		list($emode, $prop) = $this->SplitModeProp($emode);
		$this->Defattr_orig = $emode;
		foreach($prop as $key=>$val) {
			$this->DefProp[$key] = $val;
		}
		$this->Defattr = join_attrs($this->Defattr,$emode);
		$modes = array();
		for($i=0,$s=sizeof($arr);$i<$s;$i++) {
			$a = explode(':',$arr[$i]);
			if (sizeof($a)>1) {
				$m = array_pop($a);
				if (preg_match('/^[a-z!][a-z0-9!]*$/i',$m)) {
					$modes[] = array(join(':',$a),$m);
				}
			}
		}
		if ($modes) {
			foreach($modes as $mode) {
				list($mode[1],$prop) = $this->SplitModeProp($mode[1]);
				foreach($prop as $key=>$val) {
					$this->Property[$mode[0]][$key] = $val;
				}
				$this->Linkattr_orig[$mode[0]] = $mode[1];
				if (isset($this->Linkattr[$mode[0]])) {
					$this->Linkattr_up[$mode[0]] = $this->Linkattr[$mode[0]];
				} else {
					$this->Linkattr_up[$mode[0]] = $this->Defattr;
				}
				$this->Linkattr[$mode[0]] = join_attrs($this->Linkattr_up[$mode[0]],$mode[1]);
			}
		}
	}

	function IssetAttr($link,$attr)
	{
		if (!isset($this->Linkattr[$link])) {
			$mode = $this->Defattr;
		} else {
			$mode = $this->Linkattr[$link];
		}
		for($i=strlen($attr)-1;$i>=0;$i--) {
			$c = $attr{$i};
			if (strpos($mode,$c)===false) {
				return false;
			}
		}
		return true;
	}
	
	function GetProperty($link, $prop)
	{
		if (!isset($this->Property[$link][$prop])) {
			if (!isset($this->DefProp[$prop])) {
				return false;
			} else {
				$mode = $this->DefProp[$prop];
			}
		} else {
			$mode = $this->Property[$link][$prop];
		}
		return $mode;
	}
}

class C_Areas
{
	var $Vals;
	var $Groups;
	var $_find_cache;

	function OpenCfg($filename)
	{
		GLOBAL $CONFIG;
		$accarr = GetConfigFileName($filename);
		$file = $accarr[0];
		$cache = isset($accarr['-a'])?$accarr['-a']:false;
		if (!$this->ReadACache($file,$cache)) {
			$CONFIG->config_modified = true;
			$this->WriteACache(0,array($file,$cache));
			$this->ReadPlainCfg($accarr);
			$this->WriteACache(1,false);
		}
	}
		
	function ReadACache($file,$cache)
	{
		GLOBAL $CONFIG;
		if ($cache === false) return false;
		if (!($ch = @fopen($cache,"rb"))) 
			return false;
		if ($CONFIG->config_modified)
			return false;
		if (in_array(basename($file),$CONFIG->NoCache))
			return false;
		if (!($timeorig = filemtime($file))) 
			return false;
		if (fgetasciiz($ch) != $timeorig) 
			return false;
		if (fgetasciiz($ch) != $CONFIG->_module)
			return false;
		if (fgetasciiz($ch) != VERSION)
			return false;
		while ($item = fgetc($ch))
		{
			if ($item == 'G') {
				$name = fgetasciiz($ch);
				$this->Groups[$name] = new C_AreaDef();
				$len = fgetasciiz($ch);
				$data = fread($ch,$len);
				$this->Groups[$name]->InitBin($data);
			} else if ($item == 'e' || $item == 'g') {
				$name = fgetasciiz($ch);
				$len = fgetasciiz($ch);
				$data = fread($ch,$len);
				if ($item == 'g') {
					if (isset($this->Groups[$name])) {
						$this->Groups[$name]->InitBinMode($data);
					}
				} else {
					if (($num = $this->FindAreaNum($name)) != -1) {
						$this->Vals[$num]->InitBinMode($data);
					}
				}
			} else { // localarea, echoarea, etc.
				$len = fgetasciiz($ch);
				$data = fread($ch,$len);
				$num = sizeof($this->Vals);
				$this->Vals[$num] = new C_AreaDef();
				$this->Vals[$num]->InitBin($data);
				if (isset($this->Vals[$num]->Options['-g'])) {
					$grp = $this->Vals[$num]->Options['-g'];
					if (isset($this->Groups[$grp])) {
						foreach($this->Groups[$grp]->Linkattr as $link=>$attr) {
							$this->Vals[$num]->Linkattr[$link] = $attr;
							$this->Vals[$num]->Linkattr_up[$link] = $attr;
						}
						$this->Vals[$num]->Defattr = $this->Groups[$grp]->Defattr;
						$this->Vals[$num]->Defattr_up = $this->Groups[$grp]->Defattr;
					}
				}
				$this->_find_cache[strtolower($this->Vals[$num]->EchoTag)] = $num;
			}
		}
		return true;
	}

	function WriteACache($action,$what)
	{
		STATIC $cache;
		switch($action) {
			case 0: // open cache
				list($file,$cachefile) = $what;
				if ($cachefile === false) break;
				if (!($cache = @fopen($cachefile,"wb"))) break;
				
				GLOBAL $CONFIG;
				$timeorig = @filemtime($file);
				fputasciiz($cache,$timeorig);
				fputasciiz($cache,$CONFIG->_module);
				fputasciiz($cache,VERSION);
				break;
			case 1: // close cache
				if ($cache) {
					fclose($cache);
				}
				break;
			case 2: // EchoGroup
				if ($cache) {
					fwrite($cache,
						'G'.$what[0].chr(0).strlen($what[1]).chr(0).$what[1]);
				}
				break;
			case 3: // GroupMode
				if ($cache) {
					fwrite($cache,
						'g'.$what[0].chr(0).strlen($what[1]).chr(0).$what[1]);
				}
				break;
			case 4: // EchoArea|LocalArea|etc.
				if ($cache) {
					fwrite($cache,'E'.strlen($what).chr(0).$what);
				}
				break;
			case 5: // EchoMode
				if ($cache) {
					fwrite($cache,
						'e'.$what[0].chr(0).strlen($what[1]).chr(0).$what[1]);
				}
				break;
		}
	}

	function ReadPlainCfg($accarr)
	{
		$reader = new C_configfile_reader($accarr,1);
		while (($pair = $reader->ReadPair())!==false)
		{
			list($key,$val) = $pair;
			if ($key == 'echogroup') {
				$arr = explode(' ',$val,2);
				$name = $arr[0];
				$descr = isset($arr[1])?$arr[1]:'';
				$this->Groups[$name] = new C_AreaDef($descr,true);
				$this->WriteACache(2,array($name,$this->Groups[$name]->GetBinProp()));
			} else if ($key == 'groupmode' || $key == 'echomode') {
				$arr = explode(' ',$val);
				$name = array_shift($arr);
				$a = explode(':',$name);
				$emode = '';
				if (sizeof($a)>1) {
					$emode = array_pop($a);
					$name = join(':',$a);
				}
				if ($key == 'groupmode') {
					if (isset($this->Groups[$name])) {
						$this->Groups[$name]->GetMode($arr,$emode);
						$this->WriteACache(3,array($name,$this->Groups[$name]->GetBinMode()));
					}
				} else { // echomode
					if (($num = $this->FindAreaNum($name)) != -1) {
						$this->Vals[$num]->GetMode($arr,$emode);
						$this->Vals[$num]->lt_file = $reader->filename;
						$this->Vals[$num]->lt_line = $reader->linenum;
						$this->WriteACache(5,array($name,$this->Vals[$num]->GetBinMode()));
					}
				}
			} else { // localarea, echoarea, etc.
				$num = $this->AppendAreaToConf($key.' '.$val,array($reader->filename,$reader->linenum));
				$this->WriteACache(4,$this->Vals[$num]->GetBinProp());
				$this->_find_cache[strtolower($this->Vals[$num]->EchoTag)] = $num;
			}
		}
		$reader->Close();
	}

	function AppendAreaToConf($line,$loc)
	{
		$num = sizeof($this->Vals);
		$this->Vals[$num] = new C_AreaDef($line);
		$this->Vals[$num]->l_file = $loc[0];
		$this->Vals[$num]->l_line = $loc[1];
		if (isset($this->Vals[$num]->Options['-g'])) {
 			$grp = $this->Vals[$num]->Options['-g'];
			if (isset($this->Groups[$grp])) {
				if ($this->Groups[$grp]->Options) {
					foreach($this->Groups[$grp]->Options as $opt=>$val) {
						if (!isset($this->Vals[$num]->Options[$opt]))
							$this->Vals[$num]->Options[$opt] = $val;
					}
				}
				foreach($this->Groups[$grp]->Linkattr as $link=>$attr) {
					$this->Vals[$num]->Linkattr[$link] = $attr;
					$this->Vals[$num]->Linkattr_up[$link] = $attr;
				}
				$this->Vals[$num]->Defattr = $this->Groups[$grp]->Defattr;
				$this->Vals[$num]->Defattr_up = $this->Groups[$grp]->Defattr;
				$this->Vals[$num]->DefProp = $this->Groups[$grp]->DefProp;
				$this->Vals[$num]->Property = $this->Groups[$grp]->Property;
			}
		}
		return $num;
	}

	function DeleteAreaNum($num)
	{
		xinclude("S_confedit.php");
		GLOBAL $CONFIG;
		RemoveLine(
			$this->Vals[$num]->l_file,
			$this->Vals[$num]->l_line-1
		);
		$arr = array($this->Vals[$num]->l_file);
		if (!is_null($this->Vals[$num]->lt_file)) {
			RemoveLine(
				$this->Vals[$num]->lt_file,
				$this->Vals[$num]->lt_line-1
			);
			$arr[] = $this->Vals[$num]->lt_file;
		}
		$CONFIG->Reload($arr);
		return 1;
	}

	function FindAreaNum($name)
	{
		return isset($this->_find_cache[$name = strtolower($name)])?
			$this->_find_cache[$name]:
			-1;
	}
}

class C_CONFIG
{
	var $_module;
	var $_mainfile;
	var $Links;
	var $Vars;
	var $Areas;
	var $FAreas;
	var $Constants;
	var $NoCache;
	var $_state_files;
	var $_state_cache;
	var $markers;
	var $config_modified;
	var $encoding;
	 // Array of hashes:
	 //  parsed key => (filenum (in $_state_files), linenum)

	function Open($filename, $module = false, $oversets = array())
	{
		GLOBAL $temp_dir;
		$this->encoding = false;
		$this->_mainfile = $filename;
		$this->_module = substr($module,0,255);
		$this->Links = new C_Links;
		$this->Areas = new C_Areas;
		$this->FAreas = new C_Areas;
		$this->config_modified = false;
		$this->NoCache = array();
		if ($module):
			$this->Constants['module'] = $module;
		else:
			$this->Constants['module'] = 'Unknown';
		endif;
		$this->Constants['version'] = VERSION;
		foreach($oversets as $key=>$val) {
			$this->Constants[$key] = $val;
		}
		if ($filename === false) {
			$this->load_defaults();
		} else {
			$this->ReadCfgFile($filename);
		}
		$temp_dir = $this->GetVar('tempdir', '.');
	}

	function load_defaults()
	{
		$this->Vars['logfile'] = 
			'stdout 1234567890QWERTYUIOPLKJHGFDSAZXCVBNM';
		$this->Vars['sysop'] = 'SysOp';
		$this->Vars['address'] = '2:2/9999';
		$this->Vars['system'] = 'Unknown system';
		$this->Vars['location'] = 'Unknown place';
		$this->Vars['tempdir'] = '.';
		$this->Vars['outbound'] = '.';
		$this->Vars['tempoutbound'] = '.';
		$this->Vars['localoutbound'] = '.';
	}

	function Reload($modified = array())
	{
		GLOBAL $temp_dir;
		$this->encoding = false;
		$this->config_modified = false;
		$this->NoCache = array();
		foreach($modified as $item) $this->NoCache[] = basename($item);
		$filename = $this->_mainfile;
		$module = $this->_module;
		unset($this->Links);
		$this->Links = new C_Links;
		unset($this->Areas);
		$this->Areas = new C_Areas;
		unset($this->FAreas);
		$this->FAreas = new C_Areas;
		$this->Constants = array();
		if ($module):
			$this->Constants['module'] = $module;
		else:
			$this->Constants['module'] = 'Unknown';
		endif;
		if ($filename === false) {
			$this->load_defaults();
		} else {
			$this->ReadCfgFile($filename);
		}
		$temp_dir = $this->GetVar('tempdir', '.');
	}

	function _get_state_files_num($fnp)
	{
		$fnh = -1;
		for($i=0,$s=sizeof($this->_state_files);$i<$s;$i++) {
			if ($this->_state_files[$i] === $fnp) {
				$fnh = $i;
				break;
			}
		}
		if ($fnh === -1) {
			$fnh = $s;
			$this->_state_files[] = $fnp;
		}
		return $fnh;
	}

	function ReadCfgFile($filename,$cache = false)
	{
		$reader = new C_configfile_reader($filename);
		$fnp = $reader->filename;
		$fnh = $this->_get_state_files_num($reader->filename);
		while (($pair = $reader->ReadPair())!==false)
		{
			list($key,$value) = $pair;
			if ($key == '*') {
				$this->markers[($value !== '')?$value:'all'] = array($fnh,$reader->linenum);
			} else if ($key == 'set') {
				list($c,$v) = explode('=',$value);
				$c = trim($c);
				$v = trim($v);
				if ($c && (strcmp($c{0},'"')==0) && (strcmp($c{strlen($c-1)},'"')==0)):
					$c = substr($c,1,strlen($c)-2);
				endif;
				if ((strcmp($v{0},'"')==0) && (strcmp($v{strlen($v)},'"')==0)) {
					$v = substr($v,1,strlen($c)-2);
				}
				$this->Constants[$c] = $v;
				$i = '';
				while (isset($this->Vars[$key.$i])) $i++;
				$this->Vars[$key.$i] = $value;
				if ($reader->filename !== $fnp) {
					$fnh = $this->_get_state_files_num($reader->filename);
				}
				$this->_state_cache[$key.$i] = array($fnh,$reader->linenum);
			} else if ($key == 'includelinks') {
				$this->Links->OpenCfg($value);
			} else if ($key == 'includeareas') {
				$this->Areas->OpenCfg($value);
			} else if ($key == 'includefareas') {
				$this->FAreas->OpenCfg($value);
			} else if ($key == 'include') {
				$this->ReadCfgFile($value);
			} else {
				$i = '';
				while (isset($this->Vars[$key.$i])) $i++;
				$this->Vars[$key.$i] = $value;
				if ($reader->filename !== $fnp) {
					list($fnp,$fnh) = $this->_get_state_files_num($reader->filename);
				}
				$this->_state_cache[$key.$i] = array($fnh,$reader->linenum);
				if ($key == 'config_encoding') {
					$reader->fromenc = $this->encoding = 
						get_encoding_by_name($value);
				}
			}
		}
		$reader->Close();
		unset($reader);
	}

	function SaveCfgFileBin($filename)
	{
		if ($f = @fopen($filename,"w")) {
			fputint($f,strlen(VERSION),1);
			fwrite($f,VERSION);
			fputint($f,strlen($this->_module),1);
			fwrite($f,$this->_module);
			foreach($this->Vars as $key=>$val) {
				$towr = $key.chr(0).$val.chr(0);
				fputint($f,strlen($key)+2+strlen($val),2);
				fwrite($f,$towr);
			}
		}
	}

	function GetVar($param, $default = '')
	{
		if (isset($this->Vars[$param])) {
			return $this->Vars[$param];
		} else {
			return $default;
		}
	}

	function GetLinkVar($addr,$param)
	{
		if (isset($this->Links->Vals[$addr][$param])) {
			return $this->Links->Vals[$addr][$param];
		} else {
			return '';
		}
	}

	function GetArray($param)
	{
		$cnt = '';
		$result = array();
		while(isset($this->Vars[$param.$cnt])) {
			$result[]=$this->Vars[$param.$cnt];
			$cnt++;
		}
		return $result;
	}

	function GetOurAkaFor($link)
	{
		if (isset($this->Links->Vals[$link]['ouraka'])) return $this->Links->Vals[$link]['ouraka'];
		return $this->GetVar('address');
	}
}