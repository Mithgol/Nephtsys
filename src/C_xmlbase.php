<?php
/*\
  /  This is a part of PhFiTo (aka PHP Fido Tosser)
 //  Copyright (c) Alex Kocharin, 2:50/13
///
///  This program is distributed under GNU GPL v2
///  See docs/license for details
//
/    $Id: C_xmlbase.php,v 1.9 2011/01/08 12:21:09 kocharin Exp $
\*/

if (!function_exists('xinclude')) die();

class C_xmlbase
{
	var $_list;
	var $path;
	var $extended_syntax;
	var $allscan_present;

	function C_opusbase()
	{
		$this->extended_syntax = false;
		$this->allscan_present = false;
	}
	
	function OpenBase($path)
	{
		$array = array();
		$this->path = $path;
		if ($dir = @opendir($path)):
			while($file = readdir($dir)):
			if (is_file($file) && preg_match('/^[0-9]+\.xml$/i',$file)):
				@$array[(int)strtok($file,'.')] = $file;
			endif;
			endwhile;
			uksort($array,'_int_sort');
//			while (list($key,$v) = each($array)) print "$key => $v\n";
			$this->_list = array_values($array);
//			foreach($this->_list as $f) print "$f\n";
			return 1;
		else:
			return 0;
		endif;
	}

	function GetNumMsgs()
	{
		return sizeof($this->_list);
	}

	function ReadMsgHeader($msg)
	{
		$result = new C_msgheader;
		$result->From = $result->To = $result->Subj = '';
		$result->WDate = $result->ADate = $result->Attrs = 0;

		$file = $this->_list[$msg-1];
		$contents = file_get_contents($this->path.'/'.$file);
		$header = 0;
		$p = strpos($contents,"<");
		while($p !== false) {
			$p2 = strpos($contents,">",$p);
			if ($p2 !== false) {
				$tag = substr($contents,$p+1,$p2-$p-1);
				$word = strtok($tag,"/ ");
				if (!$header) {
					if (strcasecmp($word,'header')==0) {
						$header = 1;
					}
				} else if ($tag{0}!='/') {
					if ($tag{strlen($tag)-1} == '/') {
						$data = '';
					} else {
						$pe = strpos($contents,"</$word>",$p2);
						if ($pe === false) break;
						$data = substr($contents,$p2+1,$pe-$p2-1);
					}
					switch (strtolower($word)) {
						case 'from':
							$result->From = html_entity_decode($data);
							break;
						case 'to':
							$result->To = html_entity_decode($data);
							break;
						case 'fromaddr':
							$result->FromAddr = html_entity_decode($data);
							break;
						case 'toaddr':
							$result->ToAddr = html_entity_decode($data);
							break;
						case 'subj':
							$result->Subj = html_entity_decode($data);
							break;		
						case 'wdate':
							$result->WDate = Fts_date_to_unix($data);
							break;
						case 'adate':
							$result->ADate = Fts_date_to_unix($data);
							break;
						case 'attrs':
							$result->Attrs = $this->xml_to_flag($data);
							break;
					}
				} else if (strcasecmp($tag,'/header')==0) {
					break;
				}
			}
			$p = strpos($contents,"<",$p2+1);
		}		

		return $result;
	}

	function ReadMsgBody($msg)
	{
		$result = '';
		$file = $this->_list[$msg-1];
		$contents = file_get_contents($this->path.'/'.$file);
		$body = 0;
		$p = strpos($contents,"<");
		while($p !== false) {
			$p2 = strpos($contents,">",$p);
			if ($p2 !== false) {
				$tag = substr($contents,$p+1,$p2-$p-1);
				$word = strtok($tag,"/ ");
				if (!$body) {
					if (strcasecmp($word,'body')==0) {
						$body = 1;
					}
				} else if ($tag{0}!='/') {
					if ($tag{strlen($tag)-1} == '/') {
						$data = '';
					} else {
						$pe = strpos($contents,"</$word>",$p2);
						if ($pe === false) break;
						$data = substr($contents,$p2+1,$pe-$p2-1);
					}
					switch (strtolower($word)) {
						case 'quote':
						case 'line':
						case 'tagline':
						case 'tearline':
						case 'origin':
						case 'seenby':
							$result .= $data."\r";
							break;
						case 'kludge':
							$result .= chr(1).$data."\r";
							break;
					}
				} else if (strcasecmp($tag,'/body')==0) {
					break;
				}
			}
			$p = strpos($contents,"<",$p2+1);
		}		

		return rtrim(html_entity_decode($result));
	}
	
	function SetAttr($msg,$attr)
	{
		$attr = $this->flag_to_xml($attr);
		$file = $this->_list[$msg-1];
		$contents = file_get_contents($this->path.'/'.$file);
		$posb = strpos($contents,"<attrs>");
		$pose = strpos($contents,"</attrs>");
		if ($posb && $pose) {
			$contents = substr($contents,0,$posb).substr($contents,$pose+8);
		}
		$poseh = strpos($contents,"</header>");
		$contents = substr($contents,0,$poseh).
			'<attrs>'.$attr.'</attrs>'.
			substr($contents,$poseh);
		$f = fopen($this->path.'/'.$file,"w");
		fwrite($f,$contents);
		fclose($f);
	}
	
	function CloseBase()
	{
		$this->_list = array();
	}

	function flag_to_xml($rq)
	{
		$result = '';
		$array = array(MSG_PVT => 'Pvt', MSG_CRA => 'Cra', MSG_RCV => 'Rcv',
			MSG_SNT => 'Snt', MSG_ATT => 'Att', MSG_TRS => 'Trs',
			MSG_ORP => 'Orp', MSG_KFS => 'Kfs', MSG_LOC => 'Loc',
			MSG_HLD => 'Hld', MSG_FRQ => 'Frq', MSG_RRQ => 'Rrq',
			MSG_RRC => 'Rrc', MSG_ARQ => 'Arq', MSG_URQ => 'Urq',
			MSG_ARS => 'Ars', MSG_DIR => 'Dir', MSG_ZON => 'Zon', 
			MSG_HUB => 'Hub', MSG_IMM => 'Imm', MSG_XMA => 'Xma',
			MSG_LOK => 'Lok', MSG_CFM => 'Cfm', MSG_SCN => 'Scn',
			MSG_DEL => 'Del', MSG_TFS => 'Tfs', MSG_DFS => 'Dfs',
			MSG_CRY => 'Cry');
		foreach($array as $k=>$v) {
			if ($rq & $k) {$result .= $v.' ';}
		}
		return rtrim($result);
	}

	function xml_to_flag($rq)
	{
		$result = 0;
		$rq = strtolower($rq);
		$array = array('pvt' => MSG_PVT, 'cra' => MSG_CRA, 'rcv' => MSG_RCV,
			'snt' => MSG_SNT, 'att' => MSG_ATT, 'trs' => MSG_TRS,
			'orp' => MSG_ORP, 'kfs' => MSG_KFS, 'loc' => MSG_LOC,
			'hld' => MSG_HLD, 'frq' => MSG_FRQ, 'rrq' => MSG_RRQ,
			'rrc' => MSG_RRC, 'arq' => MSG_ARQ, 'urq' => MSG_URQ,
			'ars' => MSG_ARS, 'dir' => MSG_DIR, 'zon' => MSG_ZON, 
			'hub' => MSG_HUB, 'imm' => MSG_IMM, 'xma' => MSG_XMA,
			'lok' => MSG_LOK, 'cfm' => MSG_CFM, 'scn' => MSG_SCN,
			'del' => MSG_DEL, 'tfs' => MSG_TFS, 'dfs' => MSG_DFS,
			'cry' => MSG_CRY);
		$tok = strtok($rq,' ');
		while ($tok) {
			if (isset($array[$tok])) {
				$result |= $array[$tok];
			}
			$tok = strtok(' ');
		}
		return $result;
	}

	function WriteMessage($header,$message)
	{
		if ($sz = sizeof($this->_list))
			{ $num = (int)strtok($this->_list[$sz-1],'.'); }
		else { $num = 1; }
		while (file_exists($file = $this->path.'/'.$num.'.xml')) $num++;
		$f = fopen($file,'w');

		fwrite($f,'<?xml version="1.0" encoding="cp866"?><message><header>');
		fwrite($f,'<from>'.htmlspecialchars($header->From).'</from>');
		fwrite($f,'<to>'.htmlspecialchars($header->To).'</to>');
		if ($header->FromAddr) fwrite($f,'<fromaddr>'.htmlspecialchars($header->FromAddr).'</fromaddr>');
		if ($header->ToAddr) fwrite($f,'<toaddr>'.htmlspecialchars($header->ToAddr).'</toaddr>');
		if ($header->Subj) fwrite($f,'<subj>'.htmlspecialchars($header->Subj).'</subj>');
		if ($header->WDate) fwrite($f,'<wdate>'.($header->WDate?date('d M y  H:i:s',$header->WDate):date('d M y  H:i:s')).'</wdate>');
		if ($header->ADate) fwrite($f,'<adate>'.($header->ADate?date('d M y  H:i:s',$header->ADate):date('d M y  H:i:s')).'</adate>');
	
		$attrs = $this->flag_to_xml($header->Attrs);
		if ($attrs) fwrite($f,'<attrs>'.$attrs.'</attrs>');
		fwrite($f,'</header><body>');
		$msg_converted = htmlspecialchars($message);
		
		$_plines = explode("\r",$msg_converted);
		$lcnt = sizeof($_plines);
		$plines = array();
		for($i=0;$i<$lcnt;$i++) $plines[] = array('line',$_plines[$i]);
		$origin_prc = $tear_prc = false;
		for($i=$lcnt-1;$i>=0;$i--) {
			$line = $plines[$i][1];
			if ((substr($line,0,10) == ' * Origin:') && (!$origin_prc)) {
				$plines[$i][0] = 'origin';
				$origin_prc = $i;
			} else if ((substr($line,0,3) == '---') && (!$origin_prc || ($origin_prc==$i+1)) && !$tear_prc) {
				$plines[$i][0] = 'tearline';
				$tear_prc = $i;
			} else if (($tear_prc==$i+1) && ($line{0} == $line{1}) && ($line{1} == $line{2})) {
				$plines[$i][0] = 'tagline';
			} else if ((strcasecmp(substr($line,0,8),'seen-by:')==0) && !$tear_prc && !$origin_prc) {
				$plines[$i][0] = 'seenby';
			} else if ($line{0} === chr(1)) {
				$plines[$i][0] = 'kludge';
				$plines[$i][1] = substr($line,1);
			} else if (($t=strpos($line,'&gt;'))!==false) {
				$quotelvl=0;
				for ($j=$t;substr($line,$j,4)=='&gt;';$j+=4) $quotelvl++;
				if ($t == 0 || (preg_match('/^([0-9 A-Za-z]*)$/',substr($line,0,$t-1)))) {
					$plines[$i][0] = "quote";
					$plines[$i][2] = "level=\"$quotelvl\"";
				}
			}
		}
		
		for($i=0;$i<$lcnt;$i++) {
			$towr = '<';
			$towr .= $plines[$i][0];
			if (isset($plines[$i][2])) {
				$towr .= ' '.$plines[$i][2];
			}
			if (trim($plines[$i][1]) === '') {
				$towr .= '/>';
			} else {
				$towr .= '>'.$plines[$i][1].'</'.$plines[$i][0].'>';
			}
			fwrite($f,$towr);
		}
		fwrite($f,'</body></message>');
		fclose($f);
		@chmod($file,0666);
	}

	function DeleteMsg($num)
	{
		if (@unlink($this->path.'/'.$this->_list[$num])) {
			$num--;
			$s = sizeof($this->_list);
			$newarr = array();
			for($i=0; $i<$num; $i++) $newarr[] = $this->_list[$i];
			for($i=$num+1; $i<$s; $i++) $newarr[] = $this->_list[$i];
			$this->_list =& $newarr;
			return true;
		} else {
			return false;
		}
	}

	function PurgeBase()
	{
		return true;
	}

	function DeleteBase($path)
	{
		if ($dir = @opendir($path)) {
			while($file = readdir($dir)) {
				if (is_file($path.'/'.$file) && 
						preg_match('/^[0-9]+\.xml$/i',$file)) {
					@unlink($path.'/'.$file);
				}
			}
			closedir($path);
		}
		return @rmdir($path);
	}

	function CreateBase($path)
	{
		mkdir($path);
		@chmod($path,0777);
		return 1;
	}
}

function _int_sort($a,$b)
{
	if ($a<$b) return -1;
	if ($a>$b) return 1;
	return 0;
}