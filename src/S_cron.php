<?
/*\
  /  This is a part of PhFiTo (aka PHP Fido Tosser)
 //  Copyright (c) Alex Kocharin, 2:50/13
///
///  This program is distributed under GNU GPL v2
///  See docs/license for details
//
/    $Id: S_cron.php,v 1.6 2011/01/08 12:21:09 kocharin Exp $
\*/

if (!function_exists('xinclude')) die();

function cron_start()
{
	$cron = new C_cron;
	$cron->go();
}

class C_cron
{
	var $file;
	var $data;
	
	function go()
	{
		GLOBAL $CONFIG;
		$this->file = $CONFIG->GetVar('crondata');
		if ($this->file === '') return;
		$this->read_data();
		$exec = array();
		$ctime = time();
		$mdf = false;
		
		foreach($CONFIG->GetArray('pcron') as $rec) {
			$time = strtok($rec,' ');
			$mark = strtok(' ');
			$cmd = strtok(chr(0));

			if (isset($this->data[$mark])) {
				if ($this->is_expperiod($ctime,$time,$this->data[$mark])) {
					$this->data[$mark] = $ctime;
					$exec[] = array($cmd,$ctime);
					$mdf = true;
				}
			} else {
				$this->data[$mark] = $ctime;
				$exec[] = array($cmd,$ctime);
				$mdf = true;
			}
		}

		foreach($CONFIG->GetArray('acron') as $rec) {
			$time = strtok($rec,' ');
			$mark = strtok(' ');
			$cmd = strtok(chr(0));

			if (isset($this->data[$mark])) {
				if ($this->is_pasttime($ctime,$time,$this->data[$mark]) > 0) {
					$this->data[$mark] = $ctime;
					$exec[] = array($cmd,$ctime);
					$mdf = true;
				}
			} else {
				$this->data[$mark] = $ctime;
				$exec[] = array($cmd,$ctime);
				$mdf = true;
			}
		}

		foreach($CONFIG->GetArray('rcron') as $rec) {
			$time = strtok($rec,' ');
			$mark = strtok(' ');
			$cmd = strtok(chr(0));

			if (isset($this->data[$mark])) {
				for($i=0,$s=$this->is_pasttime($ctime,$time,$this->data[$mark]);$i<$s;$i++) {
					$this->data[$mark] = $ctime;
					$exec[] = array($cmd,$ctime);
					$mdf = true;
				}
			} else {
				$this->data[$mark] = $ctime;
				$exec[] = array($cmd,$ctime);
				$mdf = true;
			}
		}

		if ($mdf) {
			if ($this->write_data()) {
				foreach($exec as $ls) {
					$this->exec_cmd($ls[0],$ls[1]);
				}
			}
		}
	}

	function is_expperiod($now,$peri,$last)
	{
		$peri = $this->get_period($peri);
		//print date("d M y  H:i:s\n",$last);
		$last = strtotime(
			"+{$peri[0]} years +{$peri[1]} months +{$peri[2]} weeks ".
			"+{$peri[3]} days +{$peri[4]} hours +{$peri[5]} minutes ".
			"+{$peri[6]} seconds",$last);
		return $now >= $last;
	}

	function is_pasttime($now,$peri,$last)
	{
		$fm = $this->is_pasttime_get_last_matched($last,$peri);
		$to = $this->is_pasttime_get_last_matched($now,$peri);
		return min($to - $fm, 100);
	}

	// Input like 1w/2d (represents every 1w offset 2d)
	function is_pasttime_get_last_matched($now,$mask)
	{
		$pa = explode('/',$mask,2);
		$first = $this->get_period($pa[0]);
		if (isset($pa[1])) {
			$pa1 = $this->get_period($pa[1]);
			$begin_time = $this->add_date(mktime(0,0,0,1,1,1970),
				$pa1[0], $pa1[1], $pa1[2], $pa1[3],
				$pa1[4], $pa1[5], $pa1[6]);
		}
		$diff = $now - $begin_time;
		$iter = $first[0] * 32140800 // 12*31*24*60*60
			  + $first[1] *  2678400 // 31*24*60*60
			  + $first[2] *   604800 // 7*24*60*60
			  + $first[3] *    86400 // 24*60*60
			  + $first[4] *     3600 // 60*60
			  + $first[5] *       60
			  + $first[6];
		$num = floor($diff/$iter);
		$iter_time = $this->add_date($begin_time,
			$first[0]*$num, $first[1]*$num, $first[2]*$num, $first[3]*$num,
			$first[4]*$num, $first[5]*$num, $first[6]*$num);
		while($iter_time < $now && $iter_time > 0 && $num > 0) {
			$iter_time = $this->add_date($iter_time,
				$first[0], $first[1], $first[2], $first[3], 
				$first[4], $first[5], $first[6]);
			$num++;
		}
		return $num;
	}

	function add_date($date,$y,$m,$w,$d,$h,$i,$s,$inv = false)
	{
		$str = '';
		$zn = $inv?'-':'+';
		if ($y > 0) $str .= "$zn$y years ";
		if ($m > 0) $str .= "$zn$m months ";
		if ($w > 0) $str .= "$zn$w weeks ";
		if ($d > 0) $str .= "$zn$d days ";
		if ($h > 0) $str .= "$zn$h hours ";
		if ($i > 0) $str .= "$zn$i minutes ";
		if ($s > 0) $str .= "$zn$s seconds ";
		return strtotime($str,$date);
	}

	function get_period($th)
	{
		$ret = array(0,0,0,0,0,0,0);
		$cur = 0;
		$th = strtolower($th);
		for($i=0,$s=strlen($th);$i<$s;$i++) {
			$o = ord($th{$i});
			if ($o >= ord('0') && $o <= ord('9')) {
				$cur *= 10;
				$cur += $th{$i};
			} else {
				switch($th{$i}) {
					case 'y':
						$ret[0] += $cur;
						break;
					case 'm':
						$ret[1] += $cur;
						break;
					case 'w':
						$ret[2] += $cur;
						break;
					case 'd':
						$ret[3] += $cur;
						break;
					case 'h':
						$ret[4] += $cur;
						break;
					case 'i':
						$ret[5] += $cur;
						break;
					case 's':
						$ret[6] += $cur;
						break;
				}
				$cur = 0;
			}
		}
		$ret[6] += $cur;
		return $ret;
	}

	function read_data()
	{
		$this->data = array();
		if ($f = fopen($this->file,"r")) {
			while($line = fgets($f,10240)) {
				if ($line = rtrim($line)) {
					$mark = strtok($line,' ');
					$time = strtok(' ');
					$this->data[$mark] = $time;
				}
			}
			fclose($f);
		}
	}

	function write_data()
	{
		if ($f = fopen($this->file,"w")) {
			foreach($this->data as $mark=>$time) {
				$towr = $mark.' '.$time."\n";
				$num2 = fwrite($f,$towr,$num = strlen($towr));
				if ($num2 !== $num) {
					fclose($f);
					return false;
				}
			}
			fclose($f);
			return true;
		} else {
			return false;
		}
	}

	function exec_cmd($command,$time)
	{
		list($what,$exec) = preg_split("/\\s+/",$command,2);
		switch($what) {
			case 'system':
				system($exec);
			case 'include':
				include $exec;
		}
	}
}

?>
