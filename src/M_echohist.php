<?
/*\
  /  This is a part of PhFiTo (aka PHP Fido Tosser)
 //  Copyright (c) Alex Kocharin, 2:50/13
///
///  This program is distributed under GNU GPL v2
///  See docs/license for details
//
/    $Id: M_echohist.php,v 1.6 2011/01/09 08:53:12 kocharin Exp $
\*/

if (!function_exists('xinclude')) die();

class M_echohist
{
	var $file;

	function init()
	{
		GLOBAL $CONFIG;
		if ($name = $CONFIG->GetVar('echohistfile')) {
			$name .= '.' . str_pad(date('z'),3,'0',STR_PAD_LEFT);
			$this->file = fopen($name,'a');
		}
	}

	function escape($str)
	{
		$str = str_replace('%','%25',$str);
		$str = str_replace('|','%7C',$str);
		$str = str_replace("\n",'%0a',$str);
		$str = str_replace("\r",'%0d',$str);
		$str = str_replace("\t",'%09',$str);
		return $str;
	}

	function hook_msglog($params)
	{
		GLOBAL $CurrentMessage, $CurrentMessageParsed, $CurrentMessageInfo;
		if (($this->file) && 
				($params[1] !== false) && 
				($CurrentMessageInfo->state == 'ok')) {
			$echo = $this->escape($CurrentMessageInfo->area);
			$msgid = $this->escape($CurrentMessageParsed->msgid);
			$path = $this->escape($CurrentMessageParsed->path);
			$seenby = $this->escape($CurrentMessageParsed->seenby);
			$subj = $this->escape($CurrentMessage->Subject);
			$from = $this->escape($CurrentMessage->FromUser);
			$fromaddr = $this->escape($CurrentMessageParsed->FromAddr);
			$to = $this->escape($CurrentMessage->ToUser);
			$origdate = $this->escape($CurrentMessage->getAsciiDate());
			$line = $echo.'|'.$msgid."\t".time().'| '.$path.'| '.$seenby.'|'.
				$subj.'|'.$from.'|'.$fromaddr.'|'.$to.'|'.$origdate."\n";
			fwrite($this->file,$line,strlen($line));
		}
	}

	function done()
	{
		if ($this->file) {
			fclose($this->file);
			$this->file = false;
		}
	}
}

class M_msglog
{
	var $file_handle;  // file -> number_of_handle
	var $handles;      // handles of files (maybe falses)
	var $net_files;    // number of handle (n-th line of netlog)
	var $net_lines;    // line contents (n-th line of netlog)
	var $echo_files;   // number of handle (n-th line of echolog)
	var $echo_lines;   // line contents (n-th line of echolog)

	function init()
	{
		GLOBAL $CONFIG;
		foreach ($CONFIG->GetArray('echomaillog') as $log_str) {
			$arr = explode(' ',$log_str,2);
			if (sizeof($arr) == 1) $arr[] = '';
			list($file,$log) = $arr;
			if (isset($this->file_handle[$file])) {
				$num = $this->file_handle[$file];
			} else {
				$this->handles[] = fopen($file,'a');
				$this->file_handle[$file] = $num = sizeof($this->handles)-1;
			}
			$this->echo_files[] = $num;
			$this->echo_lines[] = $log;
		}
		foreach ($CONFIG->GetArray('netmaillog') as $log_str) {
			$arr = explode(' ',$log_str,2);
			if (sizeof($arr) == 1) $arr[] = '';
			list($file,$log) = $arr;
			if (isset($this->file_handle[$file])) {
				$num = $this->file_handle[$file];
			} else {
				$this->handles[] = fopen($file,'a');
				$this->file_handle[$file] = $num = sizeof($this->handles)-1;
			}
			$this->net_files[] = $num;
			$this->net_lines[] = $log;
		}
	}

	function transform_line($line)
	{
		GLOBAL $CurrentMessage, $CurrentMessageParsed, $CurrentMessageInfo;
		$p = strpos($line,'@');
		while($p !== false) {
			if (substr($line,$p+1,8) == 'fromname') {
				$zat = $CurrentMessage->FromUser;
				$line = substr($line,0,$p).str_replace(" ","_",$zat).substr($line,$p+9);
				$p += strlen($zat)-1;
			} else if (substr($line,$p+1,6) == 'toname') {
				$zat = $CurrentMessage->ToUser;
				$line = substr($line,0,$p).str_replace(" ","_",$zat).substr($line,$p+7);
				$p += strlen($zat)-1;
			} else if (substr($line,$p+1,8) == 'fromaddr') {
				$zat = $CurrentMessageParsed->FromAddr;
				$line = substr($line,0,$p).$zat.substr($line,$p+9);
				$p += strlen($zat)-1;
			} else if (substr($line,$p+1,6) == 'toaddr') {
				$zat = $CurrentMessageParsed->ToAddr;
				$line = substr($line,0,$p).$zat.substr($line,$p+7);
				$p += strlen($zat)-1;
			} else if (substr($line,$p+1,7) == 'curtime') {
				$zat = date('H:i:s');
				$line = substr($line,0,$p).$zat.substr($line,$p+8);
				$p += strlen($zat)-1;
			} else if (substr($line,$p+1,7) == 'curdate') {
				$zat = date('d_M_y');
				$line = substr($line,0,$p).$zat.substr($line,$p+8);
				$p += strlen($zat)-1;
			} else if (substr($line,$p+1,4) == 'area') {
				$zat = $CurrentMessageInfo->area;
				$line = substr($line,0,$p).$zat.substr($line,$p+5);
				$p += strlen($zat)-1;
			} else if (substr($line,$p+1,4) == 'size') {
				$zat = strlen($CurrentMessageParsed->body);
				$line = substr($line,0,$p).$zat.substr($line,$p+5);
				$p += strlen($zat)-1;
			} else if (substr($line,$p+1,7) == 'subject') {
				$zat = $CurrentMessage->Subject;
				$line = substr($line,0,$p).str_replace(" ","_",$zat).substr($line,$p+8);
				$p += strlen($zat)-1;
			} else if (substr($line,$p+1,5) == 'msgid') {
				$zat = $CurrentMessageParsed->msgid;
				$line = substr($line,0,$p).str_replace(" ","_",$zat).substr($line,$p+6);
				$p += strlen($zat)-1;
			} else if (substr($line,$p+1,5) == 'reply') {
				$zat = $CurrentMessageParsed->reply;
				$line = substr($line,0,$p).str_replace(" ","_",$zat).substr($line,$p+6);
				$p += strlen($zat)-1;
			} else if (substr($line,$p+1,7) == 'pktfrom') {
				$zat = $CurrentMessageInfo->from_link;
				$line = substr($line,0,$p).$zat.substr($line,$p+8);
				$p += strlen($zat)-1;
			} else if (substr($line,$p+1,7) == 'pktname') {
				$zat = $CurrentMessageInfo->pkt_name;
				$line = substr($line,0,$p).$zat.substr($line,$p+8);
				$p += strlen($zat)-1;
			} else if (substr($line,$p+1,5) == 'state') {
				$zat = $CurrentMessageInfo->state;
				$line = substr($line,0,$p).$zat.substr($line,$p+6);
				$p += strlen($zat)-1;
			} else if (substr($line,$p+1,8) == 'packedto') {
				$zat = '';
				if ($CurrentMessageInfo->packed_to) {
					$zat = join(",",$CurrentMessageInfo->packed_to);
				}
				$line = substr($line,0,$p).$zat.substr($line,$p+9);
				$p += strlen($zat)-1;
			}
			$p = strpos($line,"@",$p+1);
		}
		return $line;
	}

	function hook_msglog($params)
	{
		if ($params[1] == false) {
			for($i=0,$s=sizeof($this->net_files); $i<$s; $i++) {
				$line = $this->transform_line($this->net_lines[$i]);
				fwrite($this->handles[$this->net_files[$i]],$line."\n");
			}
		} else {
			for($i=0,$s=sizeof($this->echo_files); $i<$s; $i++) {
				$line = $this->transform_line($this->echo_lines[$i]);
				fwrite($this->handles[$this->echo_files[$i]],$line."\n");
			}
		}
	}

	function done()
	{
		for ($i=0,$s=sizeof($this->handles); $i<$s; $i++) {
			if ($this->handles[$i]) {
				fclose($this->handles[$i]);
				$this->handles[$i] = false;
			}
		}
	}
}

?>
