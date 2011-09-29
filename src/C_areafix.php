<?
/*\
  /  This is a part of PhFiTo (aka PHP Fido Tosser)
 //  Copyright (c) Alex Kocharin, 2:50/13
///
///  This program is distributed under GNU GPL v2
///  See docs/license for details
//
/    $Id: C_areafix.php,v 1.11 2011/01/09 08:53:12 kocharin Exp $
\*/

if (!function_exists('xinclude')) die();

class C_Areafix
{
	var $Addr;
	var $aReply;
	var $user;
	
	function Init($fromaddr,$subj,$aReply,$user)
	{
		GLOBAL $CONFIG;
		$ourpass = $CONFIG->GetLinkVar($fromaddr,'password');
		if (strlen($ourpass)==0):
			return GetStrFromFile_param('AFIXUNKNOWN','Security violation: unknown link');
		elseif (strcmp($ourpass,$subj)==0):
			$this->Addr = $fromaddr;
			$this->user = $user;
			$this->aReply = $aReply;
			return '';
		else:
			return GetStrFromFile_param('AFIXPWDERR','Security violation: password error');
		endif;
	}

	function ProcCmd($command)
	{
		if ($command{0} == '%'):
			$cmd = substr($command,1);
			if (strcasecmp($cmd,'list')==0):
				$this->SendList();
				return GetStrFromFile_param('AFIXLIST','List of areas sent');
			elseif (strcasecmp($cmd,'query')==0):
				$this->SendQuery(false);
				return GetStrFromFile_param('AFIXQUERY','List of linked areas sent');	
			elseif (strcasecmp($cmd,'linked')==0):
				$this->SendQuery(false);
				return GetStrFromFile_param('AFIXQUERY','List of linked areas sent');
			elseif (strcasecmp($cmd,'unlinked')==0):
				$this->SendQuery(true);
				return GetStrFromFile_param('AFIXUNQUERY','List of unlinked areas sent');
			elseif (strcasecmp($cmd,'info')==0):
				$this->SendInfo();
				return GetStrFromFile_param('AFIXINFO','Info message sent');
			elseif (strcasecmp($cmd,'avail')==0):
				if ($this->SendAvail()):
					return GetStrFromFile_param('AFIXAVAIL','Avail-list sent');
				else:
					return GetStrFromFile_param('AFIXNOAVAIL','There are no any avail-list');
				endif;
			elseif (strcasecmp(substr($cmd,0,6),'rescan')==0):
				$num = $this->SendRescan(substr($cmd,7));
				if ($num == -1) {
					return GetStrFromFile_param('AFIXNORESCAN','No rescan possible');
				} else if ($num == -2) {
					return GetStrFromFile_param('AFIXNOAREAFR','This area doesn\'t exists on my system');
				} else {
					return GetStrFromFile_param('AFIXRESCANNED','Rescanned mails: '.$num);
				}
			elseif (strcasecmp($cmd,'help')==0):
				if ($this->SendHelp()):
					return GetStrFromFile_param('AFIXHELP','Help sent');
				else:
					return GetStrFromFile_param('AFIXNOHELP','There are no help, sorry. :-(');
				endif;
			elseif (strcasecmp($cmd,'pause')==0):
				if ($this->ChangeParam('pause',1)):
					return GetStrFromFile_param('AFIXPAUSED','Ok, exporting paused');
				else:
					return GetStrFromFile_param('AFIXERROR','Error. Please, report it to SysOp.');
				endif;
			elseif (strcasecmp($cmd,'resume')==0):
				if ($this->ChangeParam('pause',0)):
					return GetStrFromFile_param('AFIXRESUMED','Ok, exporting paused');
				else:
					return GetStrFromFile_param('AFIXERROR','Error. Please, report it to SysOp.');
				endif;
			elseif (strcasecmp(substr($cmd,0,8),'pushmsg ')==0):
				$param = substr($cmd,8);
				if ($param == '1' || strcasecmp($param,'on')==0) {
					if ($this->ChangeParam('sendpushmsg',1)):
						return GetStrFromFile_param('AFIXPUSHON','Ok, pushing messages enabled.');
					else:
						return GetStrFromFile_param('AFIXERROR','Error. Please, report it to SysOp.');
					endif;
				} else if ($param == '0' || strcasecmp($param,'off')==0) {
					if ($this->ChangeParam('sendpushmsg',0)):
						return GetStrFromFile_param('AFIXPUSHOFF','Ok, pushing messages disabled.');
					else:
						return GetStrFromFile_param('AFIXERROR','Error. Please, report it to SysOp.');
					endif;
				} else {
					return GetStrFromFile_param('AFIXBADBOOL','Value must be "on" or "off".');
				}
			else:
				return GetStrFromFile_param('AFIXBADCMD','Bad command');
			endif;
		elseif (strpos($command,',')!==false):
			return GetStrFromFile_param('AFIXBADCMD','Bad command');
		else:
			$uns = $delete = 0;
			if ($command{0} == '-'):
				$uns = 1;
				$command = substr($command,1);
			elseif ($command{0} == '+'):
				$command = substr($command,1);
			elseif ($command{0} == '~'):
				$command = substr($command,1);
				return $this->DeleteAreaReq($command);
			elseif ($command{0} == '&'):
				$command = substr($command,1);
				return $this->NewAreaReq($command);
			endif;
			if (($command{0} == '/') || (strpos($command,'*')!==false) || (strpos($command,'?')!==false)):
				return $this->MaskRequest($command,$uns);
			elseif (strpos($command,' ')!==false):
				return GetStrFromFile_param('AFIXBADCMD','Bad command');
			else:
				return $this->AreaRequest($command,$uns);
			endif;
		endif;
	}

	function SendRescan($cmdline)
	{
		xinclude('L_areas.php');
		GLOBAL $CONFIG;
		$arr = explode(' ',$cmdline,2);
		if (sizeof($arr) == 1) {
			$arr[] = 10;
		}
		$arr[0] = trim($arr[0]);
		$arr[1] = trim($arr[1]);
		list($area,$msgs) = $arr;
		$num = $CONFIG->Areas->FindAreaNum($area);
		if ($num == -1) {
			return -2;
		}
		if (!$this->Allowed($num) || !$CONFIG->Areas->Vals[$num]->IssetAttr($this->Addr,'r')):
			return -1;
		endif;
		return RescanArea($this->Addr,$area,$msgs,true);
	}

	function MaskRequest($cmd,$uns)
	{
		GLOBAL $CONFIG;
		$okechoes = array();
		$errechoes = array();
		$alrechoes = array();
		for($num=0,$sts=sizeof($CONFIG->Areas->Vals);$num<$sts;$num++):
			$echo = $CONFIG->Areas->Vals[$num]->EchoTag;
			if (is_match_mask($cmd,$echo) && $this->Allowed($num) && 
					!$CONFIG->Areas->Vals[$num]->IssetAttr($this->Addr,'m')):
				$subs = $CONFIG->Areas->Vals[$num]->IssetAttr($this->Addr,'l');
				if ($uns ^ $subs):
					$alrechoes[] = $echo;
				else:
					if (SubscribeArea($echo,$num,$uns,$this->Addr)):
						$okechoes[] = $echo;
						if (!$uns) { 
							SendPushMsg($this->Addr,$echo,$this->user);
						};
					else:
						$errechoes[] = $echo;
					endif;
				endif;
			endif;
		endfor;
		$result = '';
		$w = 0;
		if (sizeof($okechoes)>0):
			if ($w) $result.="\r\r";
			$w = 1;
			sort($okechoes);
			$result.= GetStrFromFile_param('AFIX'.($uns?'UN':'LI').'OKMASK','Areas '.($uns?'un':'').'linked:');
			$cnt = 0;
			foreach($okechoes as $echo) $result.="\r".($cnt++).': '.$echo;
		endif;	
		if (sizeof($alrechoes)>0):
			if ($w) $result.="\r\r";
			$w = 1;
			sort($alrechoes);
			$result.= GetStrFromFile_param('AFIXAL'.($uns?'UN':'LI').'MASK','You are already '.($uns?'un':'').'linked to:');
			$cnt = 0;
			foreach($alrechoes as $echo) $result.="\r".($cnt++).': '.$echo;
		endif;
		if (sizeof($errechoes)>0):
			if ($w) $result.="\r\r";
			$w = 1;
			sort($errechoes);
			$result.= GetStrFromFile_param('AFIXERR'.($uns?'UN':'LI').'MASK','Error during '.($uns?'un':'').'linking echoes:');
			$cnt = 0;
			foreach($errechoes as $echo) $result.="\r".($cnt++).': '.$echo;
		endif;
		if (!$w) $result.= GetStrFromFile_param('AFIXNOMASK','No such echoes here');
		return $result;
	}

	function DeleteAreaReq($cmd)
	{
		GLOBAL $CONFIG;
		$num = $CONFIG->Areas->FindAreaNum($cmd);
		if ($num == -1):
			return GetStrFromFile_param('AFIXNOECHO','Error: echo not found');
		endif;
		if (!$this->Allowed($num) || !$CONFIG->Areas->Vals[$num]->IssetAttr($this->Addr,'D')):
			return GetStrFromFile_param('AFIXACCERR','Error: access denied');
		endif;
		if ($CONFIG->Areas->DeleteAreaNum($num)):
			return GetStrFromFile_param('AFIXAREADEL','Area deleted');
		else:
			return GetStrFromFile_param('AFIXERROR','Error. Please, report it to SysOp.');
		endif;
	}

	function NewAreaReq($cmd)
	{
		GLOBAL $CONFIG;
		$num = $CONFIG->Areas->FindAreaNum($cmd);
		if ($num != -1) {
			return GetStrFromFile_param('AFIXECHOEXISTS','Error: echo already exists');
		}
		if (AutoCreateArea('EchoArea',$cmd,$this->Addr)) {
			return GetStrFromFile_param('AFIXAREACREATED','Area created');
		} else {
			return GetStrFromFile_param('AFIXACCERR','Error: access denied');
		}
	}

	function AreaRequest($cmd,$uns)
	{
		GLOBAL $CONFIG, $Queue;
		$num = $CONFIG->Areas->FindAreaNum($cmd);
		if ($num == -1):
			if ((!$uns) && (($fwdto = $Queue->ForwardRequest($cmd,$this->Addr))!==false)):
				return GetStrFromFile_param('AFIXFWDRQ',"Request forwarded to $fwdto",array('FWDTO' => $fwdto));
			else:
				return GetStrFromFile_param('AFIXNOECHO','Error: echo not found');
			endif;
		endif;
		if (!$this->Allowed($num) || $CONFIG->Areas->Vals[$num]->IssetAttr($this->Addr,'m')):
			return GetStrFromFile_param('AFIXACCERR','Error: access denied');
		endif;
		$subs = $CONFIG->Areas->Vals[$num]->IssetAttr($this->Addr,'l');
		if ($uns):
			if ($subs):
				if (SubscribeArea($cmd,$num,1,$this->Addr)):
					return GetStrFromFile_param('AFIXUNOK','Area unlinked');
				else:
					return GetStrFromFile_param('AFIXUNERR','Error: cannot unlink you to this area');
				endif;
			else:
				return GetStrFromFile_param('AFIXALUN','Error: you not linked to this echo');
			endif;
		else:
			if (!$subs):
				if (SubscribeArea($cmd,$num,0,$this->Addr)):
					SendPushMsg($this->Addr,$cmd,$this->user);
					return GetStrFromFile_param('AFIXLIOK','Area linked');
				else:
					return GetStrFromFile_param('AFIXLIERR','Error: cannot link you to this area');
				endif;
			else:
				return GetStrFromFile_param('AFIXALLI','Error: you already linked to this echo');
			endif;
		endif;
	}

	function ChangeParam($key,$newvalue)
	{
		GLOBAL $CONFIG;
		$arr = isset($CONFIG->Links->Vals_loc[$this->Addr][$key])?$CONFIG->Links->Vals_loc[$this->Addr][$key]:$CONFIG->Links->Vals_defloc[$this->Addr];
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
				$CONFIG->Links->Vals[$this->Addr][$k=strtolower($key)] = $newvalue;
				$CONFIG->Links->Vals_loc[$this->Addr][$k] = array($file,$line,true);
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

	function SendText($text,$subj)
	{
		PostMessage(false,'Phfito Tracker',$this->user,false,$this->Addr,$subj,$text,MSG_PVT,$this->aReply);
	}

	function SendHelp()
	{
		GLOBAL $CONFIG;
		$file = $CONFIG->GetVar('areafixhelp');
		if (!$file) return 0;
		if (is_readable($file)):
			$text = file_get_contents($file);
			$text = str_replace("\n","\r",str_replace("\r","",$text));
			if ($text = convert_cyr_string($text,'k','d')):
				$this->SendText($text,'Help message');
				return 1;
			else:
				return 0;
			endif;
		else:
			return 0;
		endif;
	}

	function SendAvail()
	{
		GLOBAL $CONFIG;
		$file = $CONFIG->GetVar('areafixavail');
		if (!$file) return 0;
		if (is_readable($file)):
			$text = file_get_contents($file);
			$text = str_replace("\n","\r",str_replace("\r","",$text));
			if ($text = convert_cyr_string($text,'k','d')):
				$this->SendText($text,'List of available areas');
				return 1;
			else:
				return 0;
			endif;
		else:
			return 0;
		endif;
	}

	function _print_area($cnt,$num)
	{
		GLOBAL $CONFIG;
		$result = '';
		for($i=0,$n=4-strlen($cnt);$i<$n;$i++) $result.= ' ';
		$result.= $cnt.($CONFIG->Areas->Vals[$num]->IssetAttr($this->Addr,'l')?"  Linked  ":"   ----   ");
		$echo = $CONFIG->Areas->Vals[$num]->EchoTag;
		$descr = isset($CONFIG->Areas->Vals[$num]->Options['-d'])?$CONFIG->Areas->Vals[$num]->Options['-d']:'';
		$se = $sd = 1;
		while (strlen($echo)>30) { $echo_d[] = substr($echo,0,30); $echo = substr($echo,30); $se++; };
		$echo_d[] = $echo;
		while (strlen($descr)>30) { $descr_d[] = substr($descr,0,30); $descr = substr($descr,30); $sd++; };
		$descr_d[] = $descr;
		$sm = max($se,$sd);
		for($i=0;$i<$sm;$i++):
			if ($i!=0) $result.= '              ';
			$result.= $e = isset($echo_d[$i])?$echo_d[$i]:'';
			if (isset($descr_d[$i]) && strlen($d = $descr_d[$i])):
				for($ii=0,$s=33-strlen($e);$ii<$s;$ii++) $result.= ' ';
				$result.= $d;
			endif;
			$result.= "\r";
		endfor;
		return $result;
	}

	function SendList()
	{
		$this->SendText($this->FormatList(false)."\r\r",'List of available areas');
	}

	function SendQuery($inv)
	{
		$this->SendText($this->FormatList(true,$inv)."\r\r",'List of '.($inv?'un':'').'linked areas');
	}

	function SendInfo()
	{
		GLOBAL $CONFIG;
		$addr = $this->Addr;
		$aka = $CONFIG->GetOurAkaFor($addr);
		$is_pass = $CONFIG->GetLinkVar($addr,'paused');
		$pwd = $CONFIG->GetLinkVar($addr,'password');
		$push = ($CONFIG->GetLinkVar($addr,'sendpushmsg'))?'on':'off';
		$mbs = $CONFIG->GetVar('defarcmailsize');
		if (!$mbs) $mbs = 512;
		$mbs .= 'k';
		$areas = 0;
		$s = sizeof($CONFIG->Areas->Vals);
		for($i=0;$i<$s;$i++):
			if ($this->Allowed($i) && $CONFIG->Areas->Vals[$i]->IssetAttr($this->Addr,'l') && !$CONFIG->Areas->Vals[$i]->IssetAttr($this->Addr,'h')):
				$areas++;
			endif;
		endfor;
		$text = "\r                  Here is some information about our link:\r\r";
		$text.= "                               link is ".($is_pass?'paused':'active')."\r";
		for($i=0;$i<23-strlen($addr);$i++) $text.=' ';
		$text.= $addr."  <------------------------>  ".$aka."\r";
		$text.= "                              zip  compression\r\r";
		$text.= "                           password:";
   		for($i=0;$i<13-strlen($pwd);$i++) $text.= ' ';
		$text.= $pwd."\r";
		$text.= "                           pushing messages:";
   		for($i=0;$i<5-strlen($push);$i++) $text.= ' ';
		$text.= $push."\r";
		$text.= "                           max bundle size:";
		for($i=0;$i<6-strlen($mbs);$i++) $text.= ' ';
		$text.= $mbs."\r\r";
		for ($i=0;$i<19-strlen($areas);$i++) $text.=' ';
		$text.= $areas." areas linked (use %query to see a list)";
		$this->SendText($text."\r\r",'Link information');
	}

	function FormatList($is_query,$inv)
	{
		GLOBAL $CONFIG;
		$result = '';
		$s = sizeof($CONFIG->Areas->Vals);
		$AreasGrp = array();
		$AreasNoGrp = array();
		for($i=0;$i<$s;$i++):
			if ($this->Allowed($i) && !$CONFIG->Areas->Vals[$i]->IssetAttr($this->Addr,'h') && ($inv ^ (!$is_query || $CONFIG->Areas->Vals[$i]->IssetAttr($this->Addr,'l')))):
				if (isset($CONFIG->Areas->Vals[$i]->Options['-g']) && (isset($CONFIG->Areas->Groups[$group = $CONFIG->Areas->Vals[$i]->Options['-g']]))):
					$AreasGrp[$group][] = $i;
				else:
					$AreasNoGrp[] = $i;
				endif;
			endif;
		endfor;
		ksort($AreasGrp);
		$result.= ' *N*  *Mode*            *Echotag*                      *Description*
		/===\ /====\ /==============================\ /==============================\\'."\r";
		$cnt = 0;
		usort($AreasNoGrp,'_echo_sort');
		foreach($AreasNoGrp as $num):
			$result.= $this->_print_area($cnt++,$num);
		endforeach;
		foreach($AreasGrp as $group=>$num_arr):
			$descr = $CONFIG->Areas->Groups[$group]->GroupDescr;
			$str = "Group $group: \"$descr\"";
			$l = strlen($str);
			$result.= "\r =";
			for ($i=0,$k=(int)((70-$l)/2);$i<$k;$i++) $result.= '-';
			$result.= '= '.$str.' =';
			for ($i=0,$k=(int)((71-$l)/2);$i<$k;$i++) $result.= '-';
			$result.= "= \r";
			usort($num_arr,'_echo_sort');
			foreach ($num_arr as $num) $result.=$this->_print_area($cnt++,$num);
		endforeach;

		$result.= '\===/ \====/ \==============================/ \==============================/';
		return $result;
	}

	function Done()
	{

	}

	function Allowed($num_of_echo)
	{
		GLOBAL $CONFIG;
		return strcasecmp($CONFIG->Areas->Vals[$num_of_echo]->Status,'echoarea')==0;
	}
}

function _echo_sort($a,$b)
{
	GLOBAL $CONFIG;
	return strcasecmp($CONFIG->Areas->Vals[$a]->EchoTag,$CONFIG->Areas->Vals[$b]->EchoTag);
}

function SubscribeArea($area,$num,$uns,$link)
{
	GLOBAL $CONFIG;
	return $CONFIG->Areas->Vals[$num]->Edit_ChangeMode($link,'l',$uns);
}

function SendPushMsg($forlink,$area,$foruser)
{
	GLOBAL $CONFIG;
	if ($CONFIG->GetLinkVar($forlink,'sendpushmsg')) {
		$ouraka = $CONFIG->GetOurAkaFor($forlink);
		InitVsysTrack($forlink,$ouraka);
		$Text = 'AREA:'.strtoupper($area)."\r";
		$Text.= chr(1)."MSGID: $ouraka ".getrand8()."\r";
		$Text.= chr(1).'TID: Phfito '.VERSION."\r";
		$Text.= chr(1)."CHRS: CP866 2\r";
		$s = GetStrFromFile_param('GREET');
		$Text.= ($s?$s:"Hello!")."\r\r";
		$s = GetStrFromFile_param('PMSGWHAT');
		$Text.= ($s?$s:"This is test message which helps you to create this area.");
		$s = GetStrFromFile_param('PMSGDONTANS');
		$Text.= ($s?$s:"This message is visible only for you. Please, do not answer here.")."\r\r";
		
		$ruldir = $CONFIG->GetVar('rulesdir');
		$filename = str_replace('.','_',$area).'.rul';
		if (is_readable($ruldir.'/'.$filename)) {
			$s = GetStrFromFile_param('PMSGRULES');
			$Text.= ($s?$s:"This is rules of this echo. FYI:")."\r";
			$Text.='=---------------------------------------------------------------------------='."\r";
			$Text.= str_replace("\n","\r",str_replace("\r","",file_get_contents($ruldir.'/'.$filename)))."\r";
			$Text.='=---------------------------------------------------------------------------='."\r";
		} else {
			$s = GetStrFromFile_param('PMSGNORULES');
			$Text.= ($s?$s:"There are no available rules of this area, I cannot send it to you.")."\r";
		}
		
		$s = GetStrFromFile_param('BYE');
		$Text.= "\r\r".($s?$s:"Bye!")."\r";
		$Text.= '--- Phfito Tracker on '.$CONFIG->GetVar('system')."\r";
		$Text.= ' * Origin: '.$CONFIG->GetVar('system').', '.$CONFIG->GetVar('location').' ('.$ouraka.')';

		preg_match('/:([0-9]+)\/([0-9]+)/',$ouraka,$m);
		preg_match('/:([0-9]+)\/([0-9]+)/',$forlink,$t);
		$Omessage = new Pkt_message;
		$Omessage->Version = 2;
		$Omessage->OrigNode = $m[2];
		$Omessage->DestNode = $t[2];
		$Omessage->OrigNet = $m[1];
		$Omessage->DestNet = $t[1];
		$Omessage->Attr = 0;
		$Omessage->Cost = 0;
		$Omessage->Date = time();
		$Omessage->ToUser = $foruser;
		$Omessage->FromUser = 'Phfito Tracker';
		$Omessage->Subject = 'Rules and test';
		$Omessage->MsgText = $Text;
		PackMessageTo($Omessage,$forlink);
	}
}

?>
