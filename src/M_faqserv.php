<?
/*\
  /  This is a part of PhFiTo (aka PHP Fido Tosser)
 //  Copyright (c) Alex Kocharin, 2:50/13
///
///  This program is distributed under GNU GPL v2
///  See docs/license for details
//
/    $Id: M_faqserv.php,v 1.6 2011/01/08 12:21:09 kocharin Exp $
\*/

if (!function_exists('xinclude')) die();

class M_faqserver
{
	var $msgid;
	var $to;
	var $user;
	var $cache;
	
	function hook_netmail($params)
	{
		GLOBAL $CurrentMessage, $CurrentMessageParsed;
		list($forus,$attr,$islocal) = $params;
		if ($forus && (strcasecmp($CurrentMessage->ToUser,'faqserver')==0)) {
			$this->to = $CurrentMessageParsed->FromAddr;
			$origto = $CurrentMessageParsed->ToAddr;
			$this->msgid = $CurrentMessageParsed->msgid;
			tolog('t',"Faqserver: Processing commands from $to");
			$msgbody = '';
			$msgps = '';
			InitVsysTrack($to,$origto);
			$this->user = $CurrentMessage->FromUser;
			$this->serv_init();
			
			$qp = ' '.get_initials($this->user).'> ';
			$lines = explode("\r",$CurrentMessageParsed->body);
			for ($i=0,$s=sizeof($lines);$i<$s;$i++):
				$line = $lines[$i];
				if ($line = trim($line)):
					tolog('t',"Faqserver command: $line");
					$msgbody.= "\r".$qp.$line."\r\r".$this->serv_cmd($line)."\r";
				endif;
			endfor;
			$lines = array();
			$msgps.= $this->serv_done();
			SendReply($this->msgid,$this->to,$msgbody,$msgps,MSG_PVT,$this->user,'Reply from FaqServer',false,'Phfito FAQ Server');
		}
	}

	function serv_init()
	{

	}

	function serv_cmd($cmd)
	{
		$cmd = trim($cmd," %\t\r\n");
		list($topic,$cmd) = preg_split('/[ \t]+/',$cmd,2);
		$cmd = ltrim($cmd);
		if ($cmd) {
			$params = preg_split("/[ ,\t]+/",$cmd);
		} else {
			$params = array();
		}
		
		$uue = false;
		$encoding = false;
		$i = 0;
		while(sizeof($params)) {
			$i++;
			if (($p = strtolower(array_shift($params))) == 'uue') {
				if ($uue) {
					return "Error in param $i (\"$p\"): You already specified UUEcoding";
				} else {
					$uue = true;
				}
			} else if ($p == 'cp866') {
				if ($encoding) {
					return "Error in param $i (\"$p\"): You already defined encoding";
				} else {
					$encoding = 'd';
				}
			} else if ($p == 'cp1251') {
				if ($encoding) {
					return "Error in param $i (\"$p\"): You already defined encoding";
				} else {
					$encoding = 'w';
				}
			} else if ($p == 'koi8-r' || $p == 'koi8r') {
				if ($encoding) {
					return "Error in param $i (\"$p\"): You already defined encoding";
				} else {
					$encoding = 'k';
				}
			} else {
				return "Error in param $i (\"$p\"): Unknown parameter";
			}
		}

		if (!$uue && $encoding) {
			return "You should define encoding with UUE format only";
		}

		return $this->find_and_send($topic,$uue,$encoding===false?'d':$encoding);
	}

	function find_and_send($reqfile,$uue,$enc)
	{
		if (!$this->cache) {
			GLOBAL $CONFIG;
			foreach($CONFIG->GetArray("faqtopic") as $topic) {
				@list($alias,$file,$file_enc,$descr) = preg_split("/\\s+/",$topic,4);
				if (!isset($file_enc)) {
					$file_enc = 'd';
				} else {
					$file_enc = get_encoding_by_name();
					if ($file_enc === false) $file_enc = 'd';
				}
				$this->cache[strtolower($alias)] = array($file,$file_enc,$descr);
			}
		}
		
		if (!isset($this->cache[$reqfile])) {
			return "This topic cannot be found";
		}
		list($file,$fenc,$descr) = $this->cache[$reqfile];
		return $this->sendfile($reqfile,$file,$uue,$enc,$fenc,$descr);
	}

	function sendfile($alias,$file,$in_uue,$toenc,$fenc,$descr)
	{
		if ($contents = file_get_contents($file)) {
			if ($fenc != $toenc) {
				$contents = convert_cyr_string($contents,$fenc,$toenc);
			}
			if ($in_uue) {
				$tosend = "begin 644 $alias.txt\r";
				$tosend .= str_replace("\n","\r",convert_uuencode($contents));
				$tosend .= "end\r";
			} else {
				$tosend = str_replace("\n","\r",str_replace("\r","",$contents));
			}
			$this->sendnetmail("\r".$tosend."\r", $descr?$descr:$alias);
			fclose($file);
			return "Ok.";
		} else {
			return "Error: could not read requested topic. Report it to SysOp, plz...";
		}
	}
	
	function sendnetmail($text,$subj)
	{
		PostMessage(
			false,
			'Phfito FAQ Server',
			$this->user,
			false,
			$this->to,
			$subj,
			$text,
			MSG_PVT,
			$this->msgid
		);
	}

	function serv_done()
	{
		$this->cache = array();
	}
}

?>
