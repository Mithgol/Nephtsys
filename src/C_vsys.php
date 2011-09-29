<?php
/*\
  /  This is a part of PhFiTo (aka PHP Fido Tosser)
 //  Copyright (c) Alex Kocharin, 2:50/13
///
///  This program is distributed under GNU GPL v2
///  See docs/license for details
//
/    $Id: C_vsys.php,v 1.6 2011/01/08 12:21:09 kocharin Exp $
\*/

if (!function_exists('xinclude')) die();

class C_vsys
{
	var $nastr;
	var $sysop;
	var $station;
	var $user;
	var $_match;
	var $dontund;
	var $nastr_time;
	var $repeat;
	var $last_fr;
	var $chat_begin;
	var $users;
	var $user_date;
	var $Macro_arr_adv;

	function _get_username()
	{
		GLOBAL $CONFIG;
		$shuser = strtok($this->user,' ');
		$user = $this->_to_upper(strtoupper($shuser));
		if (!$user) $user = 'GUEST';
		if ($f = fopen($CONFIG->GetVar('maindir').'/'.$CONFIG->GetVar('namesfile'),'r')):
			$result = $this->_read_file_and_match($f,$user,1);
			if (!$result) $result = $shuser;
			fclose($f);
			return $result;
		else:
			return '';
		endif;
	}
	
	function Init()
	{
		GLOBAL $CONFIG;
		$this->Load_config();
		$this->sysop = $CONFIG->GetVar('sysopname');
		$this->station = $CONFIG->GetVar('sysopstation');
	}

	function Answer_File()
	{
		GLOBAL $CONFIG;
		$strs = array();
		if ($f = fopen($CONFIG->GetVar('chatfile'),'r')):
			while($l = fgets($f,1024)):
				if (strcmp($this->sysop,strtok($l,':'))==0):
					$strs = array();
				else:
					$strs[] = $l;
				endif;
			endwhile;
			fclose($f);
		endif;
		$ans = array();
		if (($sz = sizeof($strs)-1)>=0):
			list($user,$qu) = explode(':',$strs[$sz]);
			$this->user = $user;
			$ans[] = $this->Answer($qu);
		endif;
		if ($f = fopen($CONFIG->GetVar('chatfile'),'a')):
			foreach($ans as $s):
				fwrite($f,$this->sysop.": ".$s."\n");
			endforeach;
			fclose($f);
		endif;			
	}

	function GotoChat($user)
	{
		GLOBAL $CONFIG;
		if ($user):
	   		if (isset($this->users[$user])):
				$this->users[$user]++;
				$again = 1;
			else:
				$this->users[$user] = 1;
				$again = 0;
			endif;
		else:
			$again = 0;
		endif;
		$this->dontund = 0;
		$this->repeat = 0;
		$this->last_fr = '';
		$this->chat_begin = mktime();
		$this->user = $user;

		if (($this->nastr_time+60*$CONFIG->GetVar('nastrlong')) < mktime()):
			$this->nastr = rand(1,3);
			$this->nastr_time = mktime();
		endif;
		if ($again):
			$file = $CONFIG->GetVar('maindir').'/'.$CONFIG->GetVar('againfile');
		else:
			$file = $CONFIG->GetVar('maindir').'/'.$CONFIG->GetVar('greetfile');
		endif;
		$result = $this->GetStrFromFile($file);
		return $result;
	}

	function GetStrFromFile($file)
	{
		$result = $this->_get_rnd_str($file);
		$result = $this->_format_reply($result);
		$result = $this->_exp_macro($result);
		if ($result) $result{0} = $this->_to_upper($result{0},!$this->_updown_firstchar());
		return $result;
	}

	function Load_config()
	{
		GLOBAL $CONFIG;
		$conf = $CONFIG->GetVar('vsysdata');
		if ($f = @fopen($conf,'r')):
			$this->nastr = chop(fgets($f,1024));
			$this->nastr_time = chop(fgets($f,1024));
			$this->dontund = chop(fgets($f,1024));
			$this->repeat = chop(fgets($f,1024));
			$this->last_fr = chop(fgets($f,1024));
			$this->chat_begin = chop(fgets($f,1024));
			$this->user_date = date('dmy');
			if (strcmp($this->user_date,chop(fgets($f,1024)))==0):
				while ($l = fgets($f,1024)):
					$arr = explode(' ',chop($l));
					if (sizeof($arr) > 1):
						$key = $arr[0];
						$value = $arr[1];
						$this->users[$key] = $value;
					endif;
				endwhile;
			endif;
			fclose($f);
		else:
			$this->nastr = rand(1,3);
			$this->nastr_time = 0;
			$this->dontund = 0;
			$this->repeat = 0;
			$this->last_fr = '';
			$this->chat_begin = time();
			$this->user_date = date('dmy');
			$this->users = array();
		endif;
	}

	function Save_config()
	{
		GLOBAL $CONFIG;
		$conf = $CONFIG->GetVar('vsysdata');
		if ($f = @fopen($conf,'w')):
			fwrite($f,$this->nastr."\n");
			fwrite($f,$this->nastr_time."\n");
			fwrite($f,$this->dontund."\n");
			fwrite($f,$this->repeat."\n");
			fwrite($f,$this->last_fr."\n");
			fwrite($f,$this->chat_begin."\n");
			fwrite($f,$this->user_date."\n");
			if ($this->users)
				foreach($this->users as $key=>$value)
					fwrite($f,$key." ".$value."\n");
			fclose($f);
		endif;
	}

	function _is_match($str,$match,$no_add_m = false)
	{
		GLOBAL $CONFIG;
		if ($no_add_m == 100) return ($str === $match);
		$match = str_replace('+','',$match);
		$evals = explode('\|',$match);
		$s = sizeof($evals);
		$result = true;
		for($i=0;$i<$s;$i++):
			if (strcmp($evals[$i],'')!=0):
				if (!$t = strstr($str,$evals[$i])):
					$result = false;
					break;
				elseif (!$no_add_m):
					$l = strlen($evals[$i]);
					$this->_match = substr($t,$l);
					$this->_match = strtok($this->_match,$CONFIG->GetVar('endclause'));
				endif;
			endif;
		endfor;
		return $result;
	}

	function _to_upper($str,$rev = false)
	{
		GLOBAL $CONFIG;
		$result = '';
		if (!$rev):
			$from = $CONFIG->GetVar('chardn');
			$to = $CONFIG->GetVar('charup');
		else:
			$to = $CONFIG->GetVar('chardn');
			$from = $CONFIG->GetVar('charup');
		endif;
		$l = strlen($str);
		$f = strlen($from);
		for($i=0;$i<$l;$i++):
			$s = $str{$i};
			for($j=0;$j<$f;$j++):
				if (strcmp($from{$j},$s)==0):
					$s = $to{$j};
					break;
				endif;
			endfor;
			$result.= $s;
		endfor;
		return $result;
	}

	function _is_nastr($l)
	{
		$n = 0;
		$not = 0;
		$s = strlen($l);
		for($i=0;$i<$s;$i++):
			switch($l{$i}):
				case('1'):
					$n = 1;
					break;
				case('2'):
					$n = 2;
					break;
				case('3');
					$n = 3;
					break;
				case('-'):
					$not = 1;
					break;
				case(':'):
					$l = substr($l,$i+1);
					$i = $s;
					break;
				default:
					return $l;
			endswitch;
		endfor;
		if (!$not ^ ($n==$this->nastr)):
			return false;
		else:
			return trim($l);
		endif;
	}

	function _read_file_and_match($f,$str,$no_add_m = false)
	{
		fseek($f,0);
		$strings = array();
		$our = 0;
		$pr = 0;
		$result = '';
		while($line = fgets($f,1024)):
			if (trim($line)):
				switch($line{0}):
					case(';'):
						$pr = 1;
						break;
					case(' '):
						$pr = 1;
						if ($our):
							$l = trim($line);
							if ($l):
								if (($l = $this->_is_nastr($l))!==false)
									$strings[] = $l;
							endif;
						endif;
						break;
					default:
						if ((!$pr && !$our) || $pr)
							$our = $this->_is_match($str,trim($line),$no_add_m);
						$pr = 0;
				endswitch;
			endif;
		endwhile;
		if (($s = sizeof($strings))!=0):
			$result = $strings[rand(0,$s-1)];
		endif;
		return $result;
	}
	
	function _read_match($str)
	{
		GLOBAL $CONFIG;
		if ($f = fopen($CONFIG->GetVar('maindir').'/'.$CONFIG->GetVar('answerfile'),'r')):
			$result = $this->_read_file_and_match($f,$str);
			fclose($f);
			return $result;
		else:
			return '';
		endif;
	}

	function _get_rnd_str($file)
	{
		GLOBAL $CONFIG;
		$result = '';
		$strings = array();
		if ($f = fopen($file,'r')):
			while ($line = fgets($f,1024)):
				if ((strcmp($line{0},';')!=0) && ($l = trim($line))):
					if (($l = $this->_is_nastr($l))!==false):
						$strings[] = $l;
					endif;
				endif;
			endwhile;
			fclose($f);
			if (($s = sizeof($strings))!=0):
				$result = $strings[rand(0,$s-1)];
			endif;
			return $result;
		else:
			return '';
		endif;
	}

	function _chk_repeat($s1,$s2)
	{
		return strcmp($s1,$s2) == 0;
	}

	function Answer($str)
	{
		GLOBAL $CONFIG;
		if (($this->nastr_time+60*$CONFIG->GetVar('nastrlong')) < mktime()):
			$this->nastr = rand(1,3);
			$this->nastr_time = mktime();
		endif;
		$str = trim($str);
		$str = $this->_to_upper($str);
		if ($this->_chk_repeat($str,$this->last_fr)):
			$this->repeat++;
		else:
			$this->repeat = 0;
		endif;
		$this->last_fr = $str;
		if ($this->repeat > $CONFIG->GetVar('repeatcount')):
			$result = $this->_get_rnd_str($CONFIG->GetVar('maindir').'/'.$CONFIG->GetVar('repeatfile'));
		elseif ($result = $this->_read_match($str)):
			$this->dontund = 0;
		else:
			$this->dontund++;
			if ($this->dontund >= $CONFIG->GetVar('undcount')):
				$result = $this->_get_rnd_str($CONFIG->GetVar('maindir').'/'.$CONFIG->GetVar('dontundfile'));
			else:
				$result = $this->_get_rnd_str($CONFIG->GetVar('maindir').'/'.$CONFIG->GetVar('dialogfile'));
			endif;
		endif;
		$result = $this->_format_reply($result);
		$result = $this->_exp_macro($result);
		$result{0} = $this->_to_upper($result{0},!$this->_updown_firstchar());
		return $result;
	}

	function _updown_firstchar()
	{
		GLOBAL $CONFIG;
		if ($s = $CONFIG->GetVar('firstup')):
			list($a,$b) = explode(':',$s);
			return (rand(1,$b)>$a);
		else:
			return rand(0,1);
		endif;
	}
	
	function _get_macro($str)
	{
		$result = '';
		switch($str):
			case('s'):
				$result = strtok($this->sysop,' ');
				break;
			case('y'):
				$p = strpos($this->sysop,' ');
				if ($p===false) $p = -1;
				$result = substr($this->sysop,$p+1);
				break;
			case('m'):
				$result = $this->sysop;
				break;
			case('b'):
				$result = $this->station;
				break;
			case('u'):
				$result = $this->_get_username();
				break;
			case('l'):
				if ($this->user):
					$user = $this->user;
				else:
			   		$user = 'GUEST';
				endif;
				$p = strpos($user,' ');
				if ($p===false) $p = -1;
				$result = substr($user,$p+1);
				break;
			case('f'):
				if ($this->user):
					$user = $this->user;
				else:
			   		$user = 'GUEST';
				endif;
				$result = $user;
				break;
		endswitch;
		return $result;
	}

	function _exp_macro($str)
	{
		GLOBAL $CONFIG;
		$result = '';
		$f = fopen($CONFIG->GetVar('maindir').'/'.$CONFIG->GetVar('macrofile'),'r');
		while (($p = strpos($str,'%'))!==false):
			$result.= substr($str,0,$p);
			$str = substr($str,$p+1);
			$sz = strlen($str);
			for($i=0;$i<$sz;$i++):
				if (!in_array($str{$i},array('Q','W','E','R','T','Y','U','I','O','P','A','S','D','F','G','H','J','K','L','Z','X','C','V','B','N','M'))):
					break;
				endif;
			endfor;
			$macro = substr($str,0,$i);
			if (isset($this->Macro_arr_adv[$macro])):
				$macro = $this->Macro_arr_adv[$macro];
			elseif($f):
				$macro = $this->_read_file_and_match($f,$macro,1);
			endif;
			$result.= $this->_format_reply($macro);
			$str = substr($str,$i);
		endwhile;
		$result.= $str;
		if ($f) fclose($f);
		return $result;
	}

	function _mk_change($str)
	{
		GLOBAL $CONFIG;
		if ($f = fopen($CONFIG->GetVar('maindir').'/'.$CONFIG->GetVar('changefile'),'r')):
			$s = strlen($str);
			$word = '';
			$result = '';
			for($i=0;$i<$s;$i++):
				if (strpos('QWERTYUIOPASDFGHJKLZXCVBNMêãõëåîçûýúèÿæù÷áðòïìäñþóíéôøâàüöqwertyuiopasdfghjklzxcvbnm',$str{$i})===false):
					if ($word):
						$ns = $this->_read_file_and_match($f,$word,1);
						$result.= $ns?$ns:$word;
						$word = '';
					endif;
					$result.= $str{$i};
				else:
					$word.= $str{$i};
				endif;
			endfor;
			if ($word):
				$ns = $this->_read_file_and_match($f,$word,1);
				$result.= $ns?$ns:$word;
				$word = '';
			endif;
			fclose($f);
			return $result;
		else:
			return $str;
		endif;
	}

	function _convert_message($message)
	{
		$message = str_replace("\r","\r".chr(0),$message);
		$line = strtok($message,"\r");

		while($line):
			if (strcmp(substr($line,0,1),chr(0))==0) $line = substr($line,1);
			if (strcmp(substr($line,0,1),chr(1))!=0):
				break;
			endif;
		$line = strtok("\r");
		endwhile;
		$line = chr(0).$line;
		$sepmsg = '';
		$resmsg = '';
		while($line):
			if (strcmp(substr($line,0,1),chr(0))==0) $line = substr($line,1);
			if (strcasecmp(substr($line,0,7),chr(1).'PATH: ')==0):
				break;
			elseif ((strcmp(substr($line,0,3),'---')==0) ||
				(strcmp(substr($line,0,11),' * Origin: ')==0) ||
				(strcmp(substr($line,0,9),'SEEN-BY: ')==0)):
				$sepmsg.= $line."\r";
			else:
				$resmsg.= $sepmsg.$line."\r";
				$sepmsg = '';
			endif;
			$line = strtok("\r");
		endwhile;
		$len = strlen($resmsg);
		if (strcmp(substr($resmsg,$len-1,1),"\r")==0)
			$resmsg = substr($resmsg,0,$len-1);
		return $resmsg;
	}

	function Answer_Fido($area,$msg)
	{
		include_once "C_msgbase.php";
		include_once "L_fidonet.php";

		GLOBAL $CONFIG;
		$Area = new C_msgbase;
		if ($Area->Open($area)):
			$header = $Area->ReadMsgHeader($msg);
			$this->user = $header->From;
			$body = $this->_convert_message($Area->ReadMsgBody($msg));
			$body = convert_cyr_string($body,'d','k');

			$body = format_reply($body,get_initials($header->From));
			$cnt = 0;
			$result = '';
			$endcl = $CONFIG->GetVar('endclause');
			$sz = strlen($body);
			$p = 0;
			for ($i=0;$i<=$sz;$i++):
				if ((strstr($endcl,$body{$i})) || ($i == $sz)):
					if ($al):
						$str = substr($body,$p+1,$i-$p);
						$str = trim($str);
						$str = $this->_to_upper($str);
						$ans = $this->_read_match($str);
						if ($ans):
							$cnt++;
							$ans = $this->_format_reply($ans);
							$ans = $this->_exp_macro($ans);
							$ans{0} = $this->_to_upper($ans{0},!$this->_updown_firstchar());
							$body = substr($body,0,$i+1)."\n\n".$ans."\n\n".substr($body,$i+1);
							$sz = strlen($body);
							$i+=strlen($ans)+3;
						endif;
					endif;
					$p = $i;
				elseif (strcmp($body{$i},"\n")==0):
					$t = strpos($body,'>>',$i+1);
					$e = strpos($body,"\n",$i+1);
					if ($t === false):
						$al = 1;
					elseif ($e === false):
						$al = 0;
					else:
						$al = $t > $e;
					endif;
					if (!$al) {$p = $i;};
				endif;
			endfor;
			print $body;
			$Area->Close();
		endif;
	}

	function _format_reply($str)
	{
		$result = '';
		$first_s = 1;
		$print = 1;
		$s = strlen($str);
		for ($i=0;$i<$s;$i++):
			switch($str{$i}):
				case('~'):
					if ($first_s == 1):
						$print = rand(0,1);
					else:
						$print = 1;
					endif;
					$first_s = 1 - $first_s;
					break;
				default:
					if ($print) $result.= $str{$i};
			endswitch;
		endfor;
		$str = $result;
		$result = '';
		$s = strlen($str);
		for ($i=0;$i<$s;$i++):
			switch($str{$i}):
				case('&'):
					if ($i!=$s-1):
						$n = $str{++$i};
						if (in_array($n,array('1','2','3'))):
							$this->nastr = $n;
						else:
							$result.= '&'.$n;
						endif;
					endif;
					break;
				case('@'):
					if ($i!=$s-1):
						$n = strtolower($str{++$i});
						if (in_array($n,array('s','y','m','b','u','l','f'))):
							$result.= $this->_get_macro($n);
						else:
							$result.= '@'.$n;
						endif;
					endif;
					break;
				case('*'):
					$ns = $this->_to_upper($this->_mk_change(trim($this->_match)),true);
					$result.= $ns;
					break;
				case('#'):
					break;
				case('^'):
					break;
				default:
					$result.= $str{$i};
			endswitch;
		endfor;
		$result = trim($result);
		return $result;
	}
}