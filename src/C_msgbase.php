<?
/*\
  /  This is a part of PhFiTo (aka PHP Fido Tosser)
 //  Copyright (c) Alex Kocharin, 2:50/13
///
///  This program is distributed under GNU GPL v2
///  See docs/license for details
//
/    $Id: C_msgbase.php,v 1.12 2011/01/09 03:39:17 kocharin Exp $
\*/

if (!function_exists('xinclude')) die();

define('MSG_PVT',0x00000001);
define('MSG_CRA',0x00000002);
define('MSG_RCV',0x00000004);
define('MSG_SNT',0x00000008);
define('MSG_ATT',0x00000010);
define('MSG_TRS',0x00000020);
define('MSG_ORP',0x00000040);
define('MSG_KFS',0x00000080);
define('MSG_LOC',0x00000100);
define('MSG_HLD',0x00000200);
define('MSG_FRQ',0x00000400);
define('MSG_RRQ',0x00000800);
define('MSG_RRC',0x00001000);
define('MSG_ARQ',0x00002000);
define('MSG_URQ',0x00004000);
define('MSG_ARS',0x00008000);
define('MSG_DIR',0x00010000);
define('MSG_ZON',0x00020000);
define('MSG_HUB',0x00040000);
define('MSG_IMM',0x00080000);
define('MSG_XMA',0x00100000);
define('MSG_LOK',0x00200000);
define('MSG_CFM',0x00400000);
define('MSG_SCN',0x00800000);
define('MSG_DEL',0x01000000);
define('MSG_TFS',0x02000000);
define('MSG_DFS',0x04000000);
define('MSG_CRY',0x08000000);

GLOBAL $Bases_reference;
$Bases_reference = array(
	'opus' => array("C_opus.php", 'FTS-0001 base'),
	'squish' => array("C_squish.php", 'Squish base'),
	'jam' => array("C_jam.php", 'JAM base'),
	'fips' => array("C_fips.php", 'Base used in FIPS'),
	'xml' => array("C_xmlbase.php", 'A set of XML files'),
	'phpbb2' => array("C_phpbb2.php", 'phpBB v2.* (deprecated)'),
	'phpbb3' => array("C_phpbb3.php", 'phpBB v3.*'),
	'mysql' => array("C_mysql.php", 'MySQL format like NewED'),
	'sqlt' => array("C_sqlt.php", 'One-table SQL format'),
	'pass' => array(false, 'Passthrough like /dev/null')
);

class C_htmlreader
{
	var $Base;
	
	function Open($area)
	{
		$this->Base = new C_msgbase;
		return $this->Base->Open($area);
	}

	function Close()
	{
		$this->Base->Close();
		unset($this->Base);
	}

	function ReadMsgHeader($num)
	{
		$result = $this->Base->ReadMsgHeader($num);
		$result->From = htmlspecialchars(convert_cyr_string($result->From,'d','k'));	
		$result->To = htmlspecialchars(convert_cyr_string($result->To,'d','k'));
		$result->Subj = htmlspecialchars(convert_cyr_string($result->Subj,'d','k'));
		return $result;
	}

	function ReadMsgBody($num)
	{
		return htmlspecialchars(convert_cyr_string($this->Base->ReadMsgBody($num),'d','k'));
	}
	
	function GetNumMsgs()
	{
		return $this->Base->GetNumMsgs();
	}
}

function get_base_prop($type)
{
	GLOBAL $CONFIG;
	STATIC $types = false;
	if ($types === false) {
		$types = array(
			'squish'	=> array('C_squish.php', 'C_squishbase', array()),
			'jam'		=> array('C_jam.php', 'C_jambase', array()),
			'opus'		=> array('C_opus.php', 'C_opusbase', array()),
			'fts1'		=> array('C_opus.php', 'C_opusbase', array()),
			'xml'		=> array('C_xmlbase.php', 'C_xmlbase', array()),
			'fips'		=> array('C_fips.php', 'C_fipsbase', array()),
			'phpbb2'	=> array('C_phpbb2.php', 'C_phpbb2base', array()),
			'phpbb3'	=> array('C_phpbb3.php', 'C_phpbb3base', 
				array('setdescr', 'setcateg')
			),
			'mysql'		=> array('C_mysql.php', 'C_mysqlbase', array()),
			'pass'		=> array('C_msgbase.php', 'C_passbase', array()),
		);
		foreach($CONFIG->GetArray("areatype") as $line) {
			$arr = preg_split("/\\s+/", $line, 4);
			if (sizeof($arr) < 3) continue;
			$id = $arr[0];
			$file = $arr[1];
			$class = $arr[2];
			$params = array();
			if (isset($arr[3])) {
				$params = preg_split('/\s*,\s*/', $arr[3]);
			}
			$types[$id] = array($file, $class, $params);
		}
	}
	if (isset($types[$type])) {
		return $types[$type];
	} else {
		return false;
	}
}

class C_msgbase
{
	var $Areatag;
	var $Descr;
	var $Base;
	var $Type;
	var $Options;
	
	function Open($areaname, $create_if_not_exists = 0, $override_info = false)
	{
		GLOBAL $SRC_PATH;
		if ($override_info === false) {
			GLOBAL $CONFIG;
			if (($num = $CONFIG->Areas->FindAreaNum($areaname))==-1)
			{
				tolog('Q',"Area $areaname not found");
				return 0;
			}
			$this->Areatag = $CONFIG->Areas->Vals[$num]->EchoTag;
			$this->Descr = (isset($CONFIG->Areas->Vals[$num]->Options['-d'])?($CONFIG->Areas->Vals[$num]->Options['-d']):($CONFIG->Areas->Vals[$num]->EchoTag));
			$this->Type = ((@$CONFIG->Areas->Vals[$num]->Options['-b'])?(strtolower($CONFIG->Areas->Vals[$num]->Options['-b'])):'opus');
			$Path = $CONFIG->Areas->Vals[$num]->Path;
			$this->Options = $CONFIG->Areas->Vals[$num]->Options;
		} else {
			$this->Areatag = isset($override_info["areatag"])?$override_info["areatag"]:'';
			$this->Descr = isset($override_info["descr"])?$override_info["descr"]:'';
			$this->Type = isset($override_info["type"])?$override_info["type"]:'opus';
			$Path = $override_info["path"];
			$this->Options = isset($override_info["options"])?$override_info["options"]:array();
		}

		if (($arr = get_base_prop($this->Type)) === false) return 0;
		list($incfile, $classname, $params) = $arr;

		if (!class_exists($classname)) {
			xinclude($incfile);
		}
		$this->Base = new $classname;

		$p_arr = array();
		foreach($params as $p) {
			if ($p == 'setdescr') {
				$this->Base->Descr = $this->Descr;
			} else if ($p == 'setcateg') {
				$this->Base->Categ = 
					($this->Options['-category'])?
						($this->Options['-category']):
						false;
			} else {
				$p_arr[] = $p;
			}
		}
		$this->Base->Params =& $p_arr;
		
		$res = $this->Base->OpenBase($Path);
		if (!$res && $create_if_not_exists):
			if ($this->Base->CreateBase($Path)):
				$res = $this->Base->OpenBase($Path);
			else:
				$res = 0;
			endif;
		endif;
		if ($res):
			return 1;
		else:
			unset($this->Base);
			return 0;
		endif;
	}
	
	function WriteMessage($hdr,$msg)
	{
		return $this->Base->WriteMessage($hdr,$msg);
	}

	function AllScan()
	{
		if ($this->Base->allscan_present) {
			return $this->Base->AllScan();
		} else {
			return false;
		}
	}

	function SetAttr($msg,$attr,$ext = 0)
	{
		if ($this->Base->extended_syntax) {
			return $this->Base->SetAttr($msg,$attr,$ext);
		} else {
			return $this->Base->SetAttr($msg,$attr);
		}
	}

	function ReadMsgHeader($num,$ext = 0)
	{
		if ($this->Base->extended_syntax) {
			return $this->Base->ReadMsgHeader($num,$ext);
		} else {
			return $this->Base->ReadMsgHeader($num);
		}
	}

	function ReadMsgBody($num,$ext = 0)
	{
		if ($this->Base->extended_syntax) {
			return $this->Base->ReadMsgBody($num,$ext);
		} else {
			return $this->Base->ReadMsgBody($num);
		}
	}

	function DeleteMsg($num,$ext = 0)
	{
		if ($this->Base->extended_syntax) {
			return $this->Base->DeleteMsg($num,$ext);
		} else {
			return $this->Base->DeleteMsg($num);
		}
	}

	function PurgeBase()
	{
		$this->Base->PurgeBase();
	}

	function DeleteBase($path)
	{
		$this->Base->DeleteBase($path);
	}

	function Close()
	{
		$this->Base->CloseBase();
		unset($this->Base);
	}
		
	function GetNumMsgs()
	{
		return $this->Base->GetNumMsgs();
	}
}

class C_msgheader
{
	var $From;
	var $FromAddr;
	var $To;
	var $ToAddr;
	var $Subj;
	var $WDate;
	var $ADate;
	var $Attrs;
}

function ZeroNFlags($rq)
{
	return ($rq & (0x7413));
}

function Flag_to_FTS($rq)
{
	$attrs = 0;
	if ($rq & MSG_PVT) $attrs |= 0x0001;
	if ($rq & MSG_CRA) $attrs |= 0x0002;
	if ($rq & MSG_RCV) $attrs |= 0x0004;
	if ($rq & MSG_SNT) $attrs |= 0x0008;
	if ($rq & MSG_ATT) $attrs |= 0x0010;
	if ($rq & MSG_TRS) $attrs |= 0x0020;
	if ($rq & MSG_ORP) $attrs |= 0x0040;
	if ($rq & MSG_DEL) $attrs |= 0x0080;
	if ($rq & MSG_LOC) $attrs |= 0x0100;
	if ($rq & MSG_HLD) $attrs |= 0x0200;
	if ($rq & MSG_XMA) $attrs |= 0x0400;
	if ($rq & MSG_FRQ) $attrs |= 0x0800;
	if ($rq & MSG_RRQ) $attrs |= 0x1000;
	if ($rq & MSG_RRC) $attrs |= 0x2000;
	if ($rq & MSG_ARQ) $attrs |= 0x4000;
	if ($rq & MSG_URQ) $attrs |= 0x8000;
	return $attrs;
}

function FTS_to_Flag($rq)
{
	$attrs = 0;
	if ($rq & 0x0001) $attrs |= MSG_PVT;
	if ($rq & 0x0002) $attrs |= MSG_CRA;
	if ($rq & 0x0004) $attrs |= MSG_RCV;
	if ($rq & 0x0008) $attrs |= MSG_SNT;
	if ($rq & 0x0010) $attrs |= MSG_ATT;
	if ($rq & 0x0020) $attrs |= MSG_TRS;
	if ($rq & 0x0040) $attrs |= MSG_ORP;
	if ($rq & 0x0080) $attrs |= MSG_DEL;
	if ($rq & 0x0100) $attrs |= MSG_LOC;
	if ($rq & 0x0200) $attrs |= MSG_HLD;
	if ($rq & 0x0400) $attrs |= MSG_XMA;
	if ($rq & 0x0800) $attrs |= MSG_FRQ;
	if ($rq & 0x1000) $attrs |= MSG_RRQ;
	if ($rq & 0x2000) $attrs |= MSG_RRC;
	if ($rq & 0x4000) $attrs |= MSG_ARQ;
	if ($rq & 0x8000) $attrs |= MSG_URQ;
	return $attrs;
}

class C_passbase
{
	var $extended_syntax;
	var $allscan_present;

	function C_passbase()
	{
		$this->extended_syntax = false;
		$this->allscan_present = true;
	}
	
	function AllScan()
	{
		return array();
	}
	
	function OpenBase($path)
	{
		return 1;
	}

	function GetNumMsgs()
	{
		return 0;
	}

	function ReadMsgHeader($msg)
	{
		return false;
	}

	function ReadMsgBody($msg)
	{
		return false;
	}
	
	function SetAttr($msg,$attr)
	{
		return false;
	}
	
	function CloseBase()
	{
		return true;
	}

	function WriteMessage($header,$message)
	{
		return true;
	}
	
	function DeleteMsg($num)
	{
		return false;
	}

	function PurgeBase()
	{
		return true;
	}

	function DeleteBase($path)
	{
		return true;
	}

	function CreateBase($path)
	{
		return 1;
	}
}

?>
