<?
/*\
  /  This is a part of PhFiTo (aka PHP Fido Tosser)
 //  Copyright (c) Alex Kocharin, 2:50/13
///
///  This program is distributed under GNU GPL v2
///  See docs/license for details
//
/    $Id: C_queue.php,v 1.8 2011/01/08 12:21:09 kocharin Exp $
\*/

if (!function_exists('xinclude')) die();

xinclude('F_utils.php');

function SyncSubscribe($links)
{
	GLOBAL $CONFIG, $Queue;
	foreach($CONFIG->Areas->Vals as $Area) {
		foreach($links as $link) {
			if ($Area->IssetAttr($link,'l')) {
				$Queue->SendToAfix($link,'+'.$Area->EchoTag);
			}
		}
	}
}

class C_queue
{
	var $file;
	var $lastrep;
	var $Records;
	var $modified;
	var $cachefreq;
	var $killfrom;
	var $idlefrom;

	function Init()
	{
		GLOBAL $CONFIG;
		$this->Records = false;
		$this->lastrep = time();
		$this->killfrom = array();
		$this->idlefrom = array();
		if ($this->file = $CONFIG->GetVar('queuefile')):
			if (is_readable($this->file) && ($f = fopen($this->file,'r'))):
				$this->lastrep = chop(fgets($f,64));
				$this->Records = array();
				while($line = fgets($f,10240)):
					$line = chop($line);
					if ($line):
						list($act,$echo,$time,$links_line,$fromrq_line) = explode(',',$line);
						$act = strtoupper($act);
						$links = explode(' ',$links_line);
						$fromrq = array();
						if ($fromrq_line) $fromrq = explode(' ',$fromrq_line);
						for($i=sizeof($links)-1;$i>=0;$i--):
							$links[$i] = explode('=',$links[$i]);
						endfor;
						switch($act):
							case('FREQ'):
								$this->cachefreq[strtolower($echo)] = sizeof($this->Records);
								break;
							case('IDLE'):
								foreach($links as $l) $this->idlefrom[strtolower($echo)][] = $l[0];
								break;
							case('KILL'):
								foreach($links as $l) $this->killfrom[strtolower($echo)][] = $l[0];
								break;
						endswitch;
						$this->Records[] = array($act,$echo,$time,$links,$fromrq);
					endif;
				endwhile;
				fclose($f);
			endif;
		endif;
	}

	function Done()
	{
		if ((time()-$this->lastrep)>=24*3600):
			$this->modified = 1;
		endif;
		if ($this->modified):
			usort($this->Records,'Rec_cmp');
			if ($this->Records):
				if ($f = fopen($this->file,'w')):
					fwrite($f,time()."\n");
					for($i=0,$s=sizeof($this->Records);$i<$s;$i++):
						if ($this->Records[$i]):
							fwrite($f,$this->FormLine($this->Records[$i][0],$this->Records[$i][1],$this->Records[$i][3],$this->Records[$i][4],$this->Records[$i][2])."\n");
						endif;
					endfor;
					fclose($f);
				endif;
			else:
				@unlink($this->file);
			endif;
			$this->Report();
		endif;
		$this->SendToAfix();
	}

	function Process()
	{
		if ($this->Records!==false):
			for($i=0;isset($this->Records[$i]);$i++):
				if ($this->Records[$i]):
					if ((time()-$this->Records[$i][2])>=($this->Records[$i][3][0][1]*24*3600)):
						$this->Records[$i] = $this->UpdateLine($this->Records[$i]);
						$this->modified = 1;
					endif;
				endif;
			endfor;
		endif;
	}

	function GotEcho($echo,$fromlink)
	{
		$stecho = strtolower($echo);
		if (isset($this->cachefreq[$stecho])):
			$i = $this->cachefreq[$stecho];
			if (strcmp($this->Records[$i][3][0][0],$fromlink)==0):
				$result = $this->Records[$i][4];
				$this->Records[$i] = false;
				unset($this->cachefreq[$stecho]);
				$this->modified = 1;
				return $result;
			else:
				return false;
			endif;
			break;
		else:
			return array();
		endif;
	}

	function KillMessage($echo,$fromlink)
	{
		$lcecho = strtolower($echo);
		if (isset($this->idlefrom[$lcecho]) &&
			in_array($fromlink,$this->idlefrom[$lcecho])) return 0;
		if (isset($this->killfrom[$lcecho]) &&
			in_array($fromlink,$this->killfrom[$lcecho])) return 1;
		return -1;
	}

	function ForwardRequest($area,$addr)
	{
		GLOBAL $CONFIG;
		if (in_array($addr,$CONFIG->GetArray('address'))) $addr = '';
		$tolinks = array();
		foreach ($CONFIG->GetArray('forward') as $fwd):
			$arr = explode(' ',$fwd,2);
			$tonode = trim($arr[0]);
			$arr = explode(' ',trim($arr[1]),2);
			$days = trim($arr[0]);
			$rule = trim($arr[1]);
			if ($this->Fwd_match($area,$rule)):
				$tolinks[] = array($tonode,$days);
			endif;
		endforeach;
		if ($tolinks):
			if ($this->AppendLink($area,$addr,$tolinks)):
				$this->modified = 1;
				return $tolinks[0][0];
			else:
				return false;
			endif;
		else:
			return false;
		endif;
	}

	function AddRec($act,$echo,$time,$links,$fromrq)
	{
		$this->Records[] = array($act,$echo,$time,$links,$fromrq);
	}

	function AppendLink($area,$link,$tolinks)
	{
		if (!$this->Records) $this->Records = array();
		$starea = strtolower($area);
		if (isset($this->cachefreq[$starea])):
			$i = $this->cachefreq[$starea];
			if (!in_array($link,$this->Records[$i][4])):
				$this->Records[$i][4][] = $link;
			endif;
		elseif (isset($this->idlefrom[$starea]) || isset($this->idlefrom[$starea])):
			for($i=0,$s=sizeof($this->Records);$i<$s;$i++):
				if ($this->Records[$i]):
					if (strcasecmp($this->Records[$i][1],$area)==0):
						list($link,$days) = array_shift($this->Records[$i][3]);
						if (!$this->Records[$i][3]):
							$this->Records[$i] = false;
						endif;
						if ($this->Records[$i][0] == 'IDLE'):
							for($ii=0,$s=strlen($this->idlefrom[$starea]);$ii<$s;$i++)
								if (strcmp($this->idlefrom[$starea][$ii],$link)==0)
									{ $this->idlefrom[$starea][$ii] = false; };
						else:
							for($ii=0,$s=strlen($this->killfrom[$starea]);$ii<$s;$i++)
								if (strcmp($this->killfrom[$starea][$ii],$link)==0)
									{ $this->killfrom[$starea][$ii] = false; };
						endif;
						break;
					endif;
				endif;
			endfor;
			$this->Records[] = array('FREQ',$area,0,$tolinks,array($link));
			$this->cachefreq[$starea] = sizeof($this->Records);
		else:
			$this->Records[] = array('FREQ',$area,0,$tolinks,array($link));
			$this->cachefreq[$starea] = sizeof($this->Records);
		endif;
		return 1;
	}

	function Fwd_match($area,$rule)
	{
		$inv = 0;
		if ($rule{0} == '!'):
			$rule = substr($rule,1);
			$inv = 1;
		endif;
		$arr = explode(':',$rule,2);
		if (sizeof($arr)<2):
			$result = true;
		else:
			$what = strtolower(trim($arr[0]));
			$argv = trim($arr[1]);
			switch($what):
				case('list'):
					$result = 0;
					if ($marr = @file($argv)):
						foreach($marr as $mask):
							if ($mask = trim($mask)):
								if (is_match_mask($mask,$area)):
									$result = 1;
									break;
								endif;
							endif;
						endforeach;
					endif;
					break;
				case('mask'):
					$result = is_match_mask($argv,$area);
					break;
			endswitch;
		endif;
		return ($inv?!$result:$result);
	}

	function Report()
	{
		GLOBAL $CONFIG;
		$report = "              *Area*                  *From link*    *Act/by link*    *Days*\r/=================================\\ /=============\\ /=============\\ /========\\\r";
		if ($s = sizeof($this->Records)):
			for($i=0,$s=sizeof($this->Records);$i<$s;$i++):
				if ($this->Records[$i]):
					$report.= ' '.$this->Records[$i][1];
					for($ii=36-strlen($this->Records[$i][1]);$ii>0;$ii--) $report.=' ';
					$report.= $this->Records[$i][3][0][0];
					for($ii=16-strlen($this->Records[$i][3][0][0]);$ii>0;$ii--) $report.=' ';
					if ($this->Records[$i][0] == 'FREQ') {
						$lnk = $this->Records[$i][4][0];
					} else {
						$lnk = '<< '.$this->Records[$i][0].' >>';
					}
					$report .= $lnk;
					$avtime = (int)(((time()-($this->Records[$i][2]))/(24*3600))+1);
					for($ii=18-strlen($avtime)-strlen($lnk);$ii;$ii--) $report.=' ';
					$report.= $avtime.' of '.$this->Records[$i][3][0][1];
					$report.= "\r";
				endif;
			endfor;
		else:
			$report.="\r          ------              No areas in queue               ------\r\r";
		endif;
		$report.= "\\=================================/ \\=============/ \\=============/ \\========/\r";
		$echo = $CONFIG->GetVar('reportto');
		PostMessage($echo,'Phfito Tracker',($echo?'All':''),'','','Queue report',$report,'');
	}

	function SendToAfix($link = false,$string = false)
	{
		STATIC $Lines;
		GLOBAL $CONFIG;
		if ($link === false):
			if ($Lines):
				foreach($Lines as $l=>$sts)
					PostMessage(false,'Phfito Tracker','Areafix',false,$l,$CONFIG->GetLinkVar($l,'password'),join("\r",$sts),MSG_PVT);
			endif;
			$Lines = array();
		else:
			$Lines[$link][] = $string;
		endif;
	}

	function UpdateLine($array)
	{
		GLOBAL $CONFIG;
		switch($array[0]):
			case('FREQ'):
				$oldlink = false;
				if ($array[2] != 0):
					list($oldlink,$days) = array_shift($array[3]);
				endif;
				if (sizeof($array[3])):
					$newlink = $array[3][0][0];
					if ($oldlink && $oldlink != $newlink) {
						$this->SendToAfix($oldlink,'-'.$array[1]);
						$this->Records[] = array('KILL',$array[1],time(),array(array($oldlink,$CONFIG->GetVar('killtimeout'))),array());
					}
					$this->SendToAfix($newlink,'+'.$array[1]);
					$array[2] = time();
					return $array;
				else:
					if ($oldlink) {
						$this->SendToAfix($oldlink,'-'.$array[1]);
						$this->Records[] = array('KILL',$array[1],time(),array(array($oldlink,$CONFIG->GetVar('killtimeout'))),array());
					}
					return false;
				endif;
				break;
			case('IDLE'):
				if ($array[2] != 0) {
					for($i=0,$s=sizeof($array[3]);$i<$s;$i++):
						$this->SendToAfix($array[3][$i][0],'-'.$array[1]);
						$array[3][$i][1] = $CONFIG->GetVar('killtimeout');
					endfor;
					$array[0] = 'KILL';
				}
				$array[2] = time();
				return $array;
				break;
			case('KILL'):
				if ($array[2] == 0) {
					$this->SendToAfix($array[3][$i][0],'-'.$array[1]);
					$array[2] = time();
					return $array;
				} else {
					return false;
				}
				break;
			default:
				return false;
		endswitch;
	}

	function FormLine($act, $echo, $links, $fromrq, $ctime = false)
	{
		GLOBAL $CONFIG;
		$line = $act.','.$echo.','.($ctime!==false?$ctime:time()).',';
		$f = 0;
		foreach($links as $l):
			list($link,$time) = $l;
			$line.= ($f?' ':'').$link.'='.$time;
			$f = 1;
		endforeach;
		$line.= ','.join(' ',$fromrq);
		return $line;
	}
}

function Rec_cmp($a,$b)
{
	return strcasecmp($a[1],$b[1]);
}

?>
