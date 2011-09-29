<?php
/*\
  /  This is a part of PhFiTo (aka PHP Fido Tosser)
 //  Copyright (c) Alex Kocharin, 2:50/13
///
///  This program is distributed under GNU GPL v2
///  See docs/license for details
//
/    $Id: A_common.php,v 1.10 2011/01/09 03:39:17 kocharin Exp $
\*/

if (!function_exists('xinclude')) die();

function A_StartSession($address = '', $client = 0, $is_d = 0)
{
	GLOBAL $InData;
	GLOBAL $OutData;
	GLOBAL $CONFIG;
	GLOBAL $STime;
	$STime = time();
	$OutData = new C_Out_Data;
	$OutData->is_data = $is_d;
	$OutData->SendEMSI();
	if ($client):
		$have_pwd = (strcmp(@$CONFIG->Links->Vals[$address]['password'],'')!=0);
		if ($have_pwd):
		$OutData->SetCrypt($CONFIG->Links->Vals[$address]['password']);
		endif;
		$InData->Stage_Init($have_pwd?2:1);
		tolog('~',"~P ".($have_pwd?'pwd':'none'));
	else:
		$InData->Stage_Init();
		if (($InData->Sec == 2) && ($pad = $InData->PwdAddress)):
		$OutData->SetCrypt($CONFIG->Links->Vals[$pad]['password']);
		endif;
		$s = $InData->Sec;
		tolog('~',time()."~S amfow_server ".((isset($pad) && $pad)?$pad:$InData->Addresses[0]));
		tolog('~',"~P ".($s==0?'err':($s==1?'none':'pwd')));
	endif;
	if ($InData->Error):
	tolog('E','Error: '.$InData->Error);
	else:
	$InData->Stage_Data();
	endif;
	$InData->End_Data();
	$OutData->Write('Q');
	if (!$client) tolog('~',time().'~D');
}

function TraceData($file)
{
	GLOBAL $DATA;
	$cnt = 0;
	if ($file):
		while (!feof($file)):
			$d = fread($file,40);
//			tolog('2',$d);
			$DATA.=$d;
//			if (strstr($d1.$d,'**DATA(')) { tolog('5',$d1.$d); };
			$d1 = $d;
		endwhile;
	endif;
	if ($str = strstr($DATA,'**DATA(')):
		$DATA = substr($str,7);
		list($len,$DATA) = explode(')',$DATA);
		$DATA = substr($DATA,1,$len);
	else:
		$DATA = '';
	endif;
}

class C_In_DATA
{
	var $Addresses;
	var $Password;
	var $Error;
	var $block;
	var $FILE;
	var $Sec;
	var $DATA;
	var $RemTime;
	var $PwdAddress;
	var $Rem_Opt;
	var $base64;
	var $Got;
	var $ansfile;
	var $ansto;

	function ChkSec()
	{
		if ($this->Error):
			$this->Sec = 0;
		endif;
	}

	function FromInteger($int)
	{
	$result=0;
	$len = strlen($int);
	for ($i=0;$i<$len;$i++):
		$result+=(ord(substr($int,$i,1)) << (($len-$i-1)*8));
	endfor;
	return $result;
	}

	function ProcAddress()
	{
		GLOBAL $CONFIG;
		$Addresses = explode(' ',$this->block);
		for ($i=0;$i<sizeof($Addresses);$i++):
			$Addresses[$i] = strtok($Addresses[$i],'@');
		endfor;
		$this->Sec = 1;
		foreach($Addresses as $addr):
			tolog('A',"Remote AKA: $addr");
			$this->Addresses[] = $addr;
			if (strcmp($this->Password,'')==0):
				if ($pwd = $CONFIG->GetLinkVar($addr,'password')):
				$this->Password = $pwd;
				$this->PwdAddress = $addr;
				$this->Sec = 0;
				endif;
			endif;
		endforeach;
	}

	function ProcHuman()
	{
		tolog('H','   '.$this->block);
	}

	function ProcOpt()
	{
		$this->Rem_Opt = $this->block;
	}

	function ProcList()
	{
		GLOBAL $OutData;
		if ($this->Sec):
		list($md5,$fsize,$fname) = explode(',',$this->block,3);
		tolog('G',"Getting {$this->block}");
		$OutData->is_data = 1;
		$OutData->Write('G',$this->block);
		endif;
	}

	function ProcGet()
	{
		tolog('S','Sending '.$this->block);
		GLOBAL $OutData;
		GLOBAL $Outbound;
		if ($this->Sec):
		list($crc,$fsize,$offset,$fname) = explode(',',$this->block);
		$this->Got[] = $fname;
		if ($this->Addresses):
		reset($this->Addresses);
		foreach ($this->Addresses as $addr):
			if ($mail = $Outbound->GetMailTo($addr)):
			foreach ($mail as $file):
				if (strcasecmp($fname,basename($file))==0):
					$OutData->SendFile($file);
					$OutData->is_data = 1;
				endif;
			endforeach;
			endif;
		endforeach;
		endif;
		endif;
	}

	function ProcFAttr()
	{
		list($crc,$size,$offset,$name) = explode(',',$this->block);
		$this->File[0] = $name;
		$this->File[1] = $crc;
		$this->File[2] = $size;
		$this->File[3] = $offset;
	}

	function ProcTime()
	{
		$this->RemTime = $this->block;
	}

	function ProcCrypt($data)
	{
		tolog('Y',$md5 = md5($this->RemTime.$this->Password));
		$cnt = 0;
		$result = '';
		$this->Sec = 2;
		for ($i=0; $i<strlen($data); $i++):
		$result.=chr(ord(substr($data,$i,1)) ^ ord(substr($md5,$cnt,1)));
		if ((++$cnt) >= strlen($md5)) 
		 { $cnt = 0; };
		endfor;
		return $result;
	}

	function ProcFile()
	{
	GLOBAL $OutData;
	GLOBAL $CONFIG;
	if ($this->Sec):
		$fname = $this->File[0];
		if (preg_match('/[0-9a-f]{8}\.[odhic]ut/i',$fname)) 
			$fname = getrand8().'.pkt';
		tolog("C","Received file $fname");
		tolog('~',"~R ".$this->File[2]);
		if (SaveFileToInbound($fname,$this->block,$this->Sec==2)):
			$OutData->Write('R',$this->File[1].','.$this->File[2].','.$this->File[3].','.$this->File[0]);
			$OutData->is_data = 1;
		endif;
	endif;
	$this->File = array();
	}

	function ProcRcvd()
	{
		GLOBAL $Outbound;
		GLOBAL $OutData;
		list($crc,$fsize,$offset,$fname) = explode(',',$this->block);
		$this->Got[] = $fname;
		foreach($this->Addresses as $addr):
	       	$Outbound->DeleteSnt($addr,$fname);
		endforeach;
		tolog('D',"Sent ".$fname);
		tolog('~',"~T ".$fsize);
		$OutData->is_data = 1;
		$OutData->Write('C',$this->block);
	}

	function ProcRvC()
	{

	}
	
	function C_In_DATA($DATA)
	{
		GLOBAL $CONFIG;
		$s = 20;
		if (($ss=strlen($DATA)-2)<$s) $s = $ss;
		$this->base64 = preg_match('/^[A-Za-z0-9\/\+\=]*$/',substr($DATA,0,$s));
		if ($this->base64):
			tolog('O',"Using base64 encoding");
			$DATA = base64_decode($DATA);
		endif;
		if ($ansto = $CONFIG->GetVar('answertofile')):
			$this->ansfile = fopen($ansto,'a');
			$this->ansto = $ansto;
		endif;
		$this->DATA = strstr($DATA,'!');
	}

	function Stage_Init($seclvl = -1)
	{
		tolog('z','Init stage started');
		$DATA = $this->DATA;
		while ($DATA || strlen($DATA)):
		if (strcmp(substr($DATA,0,1),'!')==0):
		$cmd = substr($DATA,1,1);
		if (in_array($cmd,array('A','T','H','Y','O'))):
			$size = substr($DATA,2,2);
			$size_n = $this->FromInteger($size);
			$this->block = substr($DATA,4,$size_n);
			$DATA = substr($DATA,4+$size_n);
			if (isset($this->ansfile)) fwrite($this->ansfile,'!'.$cmd.$size.$this->block);
		switch ($cmd):
			case ('A'):
				$this->ProcAddress();
				break;
			case ('Y'):
				$DATA = $this->ProcCrypt($DATA);
				break;
			case ('T'):
				$this->ProcTime();
				break;
			case ('H'):
				$this->ProcHuman();
				break;
			case ('O'):
				$this->ProcOpt();
				break;
		endswitch;
		else:
			break;
		endif;
		else:
			$this->Error = 'Bad request';
			break;
		endif;
		endwhile;
		if ($seclvl != -1):
			$this->Sec = $seclvl;
		else:
			$this->ChkSec();
		endif;
		tolog('P',"Set ".(int)$this->Sec." secure level");
		$this->DATA = $DATA;
	}

	function Stage_Data()
	{
		GLOBAL $OutData;
		$quit = 0;
		tolog('z','Data stage started');
		$DATA = $this->DATA;
		while ($DATA || strlen($DATA) || !$quit):
		if ((strcmp(substr($DATA,0,1),'!')==0) && (in_array(substr($DATA,1,1),array('E','G','R','C','L','S','F','Q')))):
		$cmd = substr($DATA,1,1);
			if (strcmp($cmd,'F')==0):
				$size = substr($DATA,2,4);
				$size_n = $this->FromInteger($size);
				$this->block = substr($DATA,6,$size_n);
				$DATA = substr($DATA,6+$size_n);
				if (isset($this->ansfile)) { fwrite($this->ansfile,'!'.$cmd.$size.$this->block); };
			else:
				$size = substr($DATA,2,2);
				$size_n = $this->FromInteger($size);
				$this->block = substr($DATA,4,$size_n);
				$DATA = substr($DATA,4+$size_n);
				if (isset($this->ansfile)) fwrite($this->ansfile,'!'.$cmd.$size.$this->block);
			endif;
			switch ($cmd):
			case ('L'):
				$this->ProcList();
				break;
			case ('S'):
				$this->ProcFAttr();
				break;
			case ('F'):
				$this->ProcFile();
				break;
			case ('R'):
				$this->ProcRcvd();
				break;
			case ('G'):
				$this->ProcGet();
				break;
			case ('C'):
				$this->ProcRvC();
				break;
			case ('E'):
				$quit = 1;
				break;
			case ('Q'):
				$quit = 1;
				break;
			endswitch;
		else:
			$this->Error = 'Bad request';
			break;
		endif;
		endwhile;
		if (!$quit) { $OutData->is_data = 1; }
		if ($this->Sec):
		$this->SendList();
		endif;
	}

	function End_Data()
	{
		if (isset($this->ansfile)):
	   		fwrite($this->ansfile,"\n\n");
			fclose($this->ansfile);
			@chmod($this->ansto,0666);
		endif;
	}
	
	function SendList()
	{
		GLOBAL $OutData;
		GLOBAL $Outbound;
		if ($this->Addresses):
		reset($this->Addresses);
		foreach ($this->Addresses as $addr):
			if ($mail = $Outbound->GetMailTo($addr)):
			foreach ($mail as $file):
				$list = true;
				if ($this->Got):
				$bs = basename($file);
					foreach($this->Got as $got):
						if (strcmp($bs,$got)==0):
							$list = false;
							break;
						endif;
					endforeach;
				endif;
				if ($list):
					$OutData->ListFile($file);
					$OutData->is_data = 1;
				endif;
			endforeach;
			endif;
		endforeach;
		endif;
	}
}

function SaveFileToInbound($fname,$data,$Sec)
{
	GLOBAL $CONFIG;
	$dir = ($Sec?$CONFIG->Vars['inbound']:$CONFIG->Vars['unsecinbound']).'/';
	$i='';
	while(file_exists($fnm=($dir.$fname.$i))) {$i++;}
	if ($rsv_file = fopen($fnm,'w')):
		fwrite($rsv_file,$data);
		fclose($rsv_file);
		tolog("W","Saving $fname to $fnm");
		@chmod($fnm,0666);
		foreach($CONFIG->GetArray('execonfile') as $exof):
			list($preg,$exec) = param_split($exof);
			$exec = str_replace('@file',$fnm,$exec);
			if (preg_match($preg,$fname)):
				tolog('W',"Executing $exec");
				`$exec`;
			endif;
		endforeach;
		foreach($CONFIG->GetArray('includeonfile') as $exof):
			list($preg,$exec) = param_split($exof);
			$exec = str_replace('@file',$fnm,$exec);
			if (preg_match($preg,$fname)):
				tolog('W',"Including $exec");
				include $exec;
			endif;
		endforeach;
		return 1;
	else:
		tolog("E","Error writing $fnm");
		return 0;
	endif;
}

class C_Out_DATA
{
	var $DATA;
	var $is_data;
	var $Y_md5;
	var $Y_cnt;

	function C_Out_Data()
	{
		$this->Y_md5 = '';
	}
	
	function SendEMSI()
	{
	GLOBAL $CONFIG;
	GLOBAL $Prot_session;
	GLOBAL $STime;
	$addrstr = '';
	$this->Write('A',join(' ',$CONFIG->GetArray('address')));
	$this->Write('T',$STime);
	$this->Write('H','System: '.$CONFIG->Vars['system']);
	$this->Write('H','Sysop: '.$CONFIG->Vars['sysop']);
	$this->Write('H','Place: '.$CONFIG->Vars['location']);
	$this->Write('H',"Mailer: Amfow/".VERSION."/".($CONFIG->Vars['servermode']?'Server':'Client'));
#	$this->Write('H',"Time: ".date("r, t"));
	}

	function SetCrypt($pwd)
	{
		GLOBAL $STime;
		$this->Write('Y');
		$this->Y_md5 = md5($STime.$pwd);
		tolog('Y',md5($STime.$pwd));
		$this->Y_cnt = 0;
	}

	function ListFile($filename,$as_file='')
	{
		if (strcmp($as_file,'')==0) { $as_file = basename($filename); }
		$File_data = file_get_contents($filename);
		$this->Write('L',GetCRC($File_data).','.filesize($filename).',0,'.$as_file);
	}

	function SendFile($filename,$as_file='')
	{
		if (strcmp($as_file,'')==0) { $as_file = basename($filename); }
//		$this->Write('(','');
//		$this->Write('N',$as_file);
		$File_data = file_get_contents($filename);
		$this->Write('S',GetCRC($File_data).','.filesize($filename).',0,'.$as_file);
		$this->Write('F',$File_data,4);
//		$this->Write(')','');
	}

	function ToInteger($data,$len)
	{
	$result = '';
	for ($i=$len-1;$i>=0;$i--):
		$result.=chr(($data >> ($i*8)) & 255);
	endfor;
	return $result;
	}

	function Write($param,$value = '',$len = 2)
	{
	$adata = '';
	$newdata = '!'.$param.$this->ToInteger(strlen($value),$len).$value;
	if ($this->Y_md5):
		for ($i=0; $i<strlen($newdata); $i++):
		$adata.=chr(ord(substr($newdata,$i,1)) ^ ord(substr($this->Y_md5,$this->Y_cnt,1)));
		if ((++$this->Y_cnt) >= strlen($this->Y_md5)) 
		 { $this->Y_cnt = 0; };
		endfor;
	else:
		$adata = $newdata;
	endif;
	$this->DATA.=$adata;
	}
}

class C_BSO_Outbound
{
	var $path;
	
	function C_BSO_Outbound($path)
	{
		$this->path = $path;
	}

	function GetLinks()
	{
	function rescan_for_nodes($indir)
	{
		$result = array();
		$dir=opendir($indir);
		while ($file=readdir($dir)):
			if (preg_match('/^[0-9a-f]{8}\.(([i,c,f,h,d]lo)|([i,c,o,h,d]ut)|(req)|(pnt))$/i',$file))	
			{
				$nn=todec(substr($file,0,4)).'/'.
					todec(substr($file,4,4));
				if (is_dir($f=$indir.'/'.$file)):
					$arr=rescan_for_points($f);
					if ($arr) 
					{
						foreach($arr as $fl)
						{
							$result[]=$nn.'.'.$fl;
						}
					};
				else:
					$result[]=$nn;
				endif;
			}
		endwhile;
		closedir($dir);
		return $result;
	}

	function rescan_for_points($indir)
	{
		$result = array();	
		$dir=opendir($indir);
		while ($file=readdir($dir)):
		if (preg_match('/^[0-9a-f]{8}\.(([i,c,f,h,d]lo)|([i,c,o,h,d]ut)|(req))$/i',
			$file))
			{
				$result[]=todec(substr($file,0,8));
			}
		endwhile;
		return $result;
		closedir($dir);
	}

	GLOBAL $CONFIG;
	$outpath = $this->path;
	tolog('n','Rescanning outbound');
	$outpath = str_replace("\\","/",$outpath);
	if (strpos($outpath,'/')===false) {$outpath='./'.$outpath; }
	$p=strrpos($outpath,'/');
	$updirname=substr($outpath,0,$p);
	$outdirname=substr($outpath,$p+1);
	$dir=opendir($updirname);
	$result = array();
	while ($file=readdir($dir)):
		if ((strcasecmp($outdirname,$file)==0) || (strcasecmp($outdirname.'.',substr($file,0,strlen($file)-3))==0))
		{
			if (strrpos($file,'.')):
				$at=substr($file,strrpos($file,'.')+1);
				$zone=todec($at);
			else:
				$zone=strtok($CONFIG->GetVar('address'),':');
			endif;
			$arr = rescan_for_nodes($updirname.'/'.$file);
			if ($arr) {
				foreach($arr as $fl)
				{
					$addr=$zone.':'.$fl;
					if (!$result || !in_array($addr,$result)):
					$result[]=$addr;
					tolog('n',$addr);
					endif;
				}
			}
		}
	endwhile;
	closedir($dir);
	return $result;
	}

	function get_link_filename($nodeaddr)
{
	GLOBAL $CONFIG;
	$path=$this->path;
	$nodeaddr=strtok($nodeaddr,'@');
	$zone = strtok($nodeaddr,':');
	$net = strtok('/');
	$node = strtok('.');
	$point = strtok('!');
	if ($zone!=strtok($CONFIG->GetVar('address'),':')) 
	{
		$path.='.'.sprintf("%03x", $zone);
	}
	$path.='/';
	$file=sprintf("%04x%04x", $net, $node);
	if ($point)
	{
		$path.=$file.'.pnt/';
		$file=sprintf("%08x", $point);
	}
	return array($path,$file);
}

	function GetMailTo($link)
	{
		$result = $this->get_plain_to($link);
		if ($flos = $this->get_flos_to($link)):
		foreach ($flos as $flo_list):
			if ($flls = $this->Process_flo($flo_list)):
			foreach ($flls as $fn) {$result[]=$fn;}
			endif;
		endforeach;
		endif;
		if ($result):
		foreach ($result as $r)
		{
			tolog("o","Found file $r for $link"); 
		}
		else:
			tolog("o","No files found for node $link");
		endif;
		return $result;
	}

	function get_flos_to($link)
	{
		$result = array();
		list($path,$file) = $this->get_link_filename($link);
		if (file_exists($path) && ($directory=opendir($path)))
		{
		while ($cf=readdir($directory)):
			if (preg_match('/^'.$file.'\.[i,c,f,h,d]lo$/i',$cf) && !is_dir($path.$cf))
			{
				$result[]=$path.$cf;
			}
		endwhile;
		}
		return $result;	
	}

	function get_plain_to($link)
	{
		$result = array();
		list($path,$file) = $this->get_link_filename($link);
		if (file_exists($path) && ($directory=opendir($path)))
		{
			while ($cf=readdir($directory)):
				if (preg_match('/^'.$file.'\.(([i,c,o,h,d]ut)|req)$/i',$cf) && !is_dir($path.$cf))
				{
					$result[]=$path.$cf;
				}
			endwhile;
		}
		return $result;	
	}

	function DeleteSnt($link,$fname)
	{
		foreach($this->get_plain_to($link) as $file):
			if (strcasecmp($fname,basename($file))==0) { tolog('x','Xeped');unlink($file); }
		endforeach;
		foreach($this->get_flos_to($link) as $flo):
			$this->DelFromFlo($flo,$fname);
		endforeach;
	}

	function DelFromFlo($floname,$snt_file)
	{
		$file = fopen($floname,'r');
		$flo_files = '';
		while($line = fgets($file,1024)):
			$line = trim($line);
			if ($line):
			$fs = substr($line,0,1);
			$fir_upr = 0;
			if ($fs == '^' || $fs == '#' || $fs == '~' || $fs == '!') { $fir_upr = 1; $line = substr($line,1); }
			if (strcasecmp(basename($line),$snt_file)==0):
				$ss = substr($line,0,1);
				if ($ss != "/" && $ss != "\\") { $line = dirname($floname).'/'.$line; }
				if (strcmp($fs,'#')==0):
					tolog('d',"Cutting file $line");
					$f=fopen($line,'w');
					fclose($f);
				elseif(strcmp($fs,'^')==0):
					tolog('d',"Deleting file $line");
					unlink($line);
				endif;
			else:
				$flo_files[] = ($fir_upr?$fs:'').$line;
			endif;
			endif;
		endwhile;
		fclose($file);
		
		if ($flo_files):
			$file = fopen($floname,'w');
			foreach($flo_files as $line):
				fwrite($file,"$line\n");
			endforeach;
			fclose($file);
		else:
			tolog('d',"Flow $floname is empty - deleting it");
			unlink($floname);
		endif;
	}

	function Process_flo($floname)
	{
	$result = array();
	$file = fopen($floname,'r');
	while($line = fgets($file,1024)):
		$line = trim($line);
		if ($line):
			$fs = $line{0};
			if ($fs == '^' || $fs == '#' || $fs == '!' || $fs == ' ' || $fs == '~') { 
				$line = substr($line,1); 
			}
			$ss = $line{0};
			if (($ss != "/") && ($ss != "\\") && (strpos($line,':')==false)) { 
				$line = dirname($floname).'/'.$line; 
			}
			if ($line && is_file($line) && ($fs != '~') && ($fs != '!')) {$result[] = $line; }
		endif;
	endwhile;
	fclose($file);
	return $result;
	}
}

function todec($value)
{
	$result=0;
	$len=strlen($value=strtoupper($value));
	for ($i=0;$i<$len;$i++)
	{
		$v=ord($value[$i])-ord('0');
		if ($v>9) { $v=ord($value[$i])-ord('A')+10; }
		$result+=($v<<(($len-$i-1)*4));
	}
	return $result;
}