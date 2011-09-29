<?
/*\
  /  This is a part of PhFiTo (aka PHP Fido Tosser)
 //  Copyright (c) Alex Kocharin, 2:50/13
///
///  This program is distributed under GNU GPL v2
///  See docs/license for details
//
/    $Id: C_mysql.php,v 1.10 2011/01/18 05:16:51 kocharin Exp $
\*/

if (!function_exists('xinclude')) die();

class C_mysqlbase
{
	var $db;
	var $table;
	var $echo;
	var $id;
	var $extended_syntax;
	var $allscan_present;

	function C_mysqlbase()
	{
		$this->extended_syntax = false;
		$this->allscan_present = false;
	}

	function OpenBase($addr)
	{
		$this->_decodeaddr($addr);
		$this->_connectdb();
		if (!$this->id) return 0;
		if (!mysql_select_db($this->db,$this->id)) {
			$this->CloseBase();
			return 0;
		}
		$res = mysql_query('SELECT NUMBER FROM '.$this->table.' LIMIT 1;',$this->id);
		if ($res):
			mysql_free_result($res);
			return 1;
		else:
			$this->CloseBase();
			return 0;
		endif;
	}

	function CreateBase($addr)
	{
		$this->_decodeaddr($addr);
		$this->_connectdb();
		mysql_select_db($this->db,$this->id);
		$res = mysql_query('
CREATE TABLE IF NOT EXISTS '.$this->table.' (
AREA       VARCHAR(80),
MSGID      VARCHAR(50),
REPLYID    VARCHAR(50),
SUBJ       VARCHAR(72),
FROMNAME   VARCHAR(36),
TONAME     VARCHAR(36),
FROMADDR   VARCHAR(50),
TOADDR     VARCHAR(50),
NUMBER     INTEGER UNSIGNED,
BODY       LONGTEXT,
RAZMER     INTEGER UNSIGNED,
DATETIME   DATETIME,
RECIVDATE  DATETIME,
ATTRIB     INTEGER
);
',$this->id);
		$this->_disconnectdb();
		return $res;
	}

	function _decodeaddr($addr)
	{
		if (strcmp(substr($addr,0,1),'/')==0) $addr = substr($addr,1);
		$this->db = strtok($addr,'/');
		$this->table = strtok('/');
		$this->echo = strtok('/');
		$this->table = preg_replace('/[^a-z0-9_]/i','_',$this->table);
	}

	function _connectdb()
	{
		GLOBAL $CONFIG;
		$host = isset($CONFIG->Vars['mysqlhost'])?$CONFIG->Vars['mysqlhost']:'localhost';
		$user = isset($CONFIG->Vars['mysqluser'])?$CONFIG->Vars['mysqluser']:'';
		$pass = isset($CONFIG->Vars['mysqlpass'])?$CONFIG->Vars['mysqlpass']:'';
		if ($pass):
			$this->id = mysql_connect($host,$user,$pass,true);
		elseif ($user):
			$this->id = mysql_connect($host,$user,ini_get("mysql.default_password"),true);
		else:
			$this->id = mysql_connect($host,ini_get("mysql.default_user"),ini_get("mysql.default_password"),true);
		endif;
	}

	function _disconnectdb()
	{
		mysql_close($this->id);
	}
	
	function GetNumMsgs()
	{
		$q = mysql_query('SELECT NUMBER FROM '.$this->table.
			(strcmp($this->echo,'')==0?'':' WHERE AREA = "'.$this->echo.'"'),
			$this->id);
		$result = mysql_num_rows($q);
		mysql_free_result($q);
		return $result;
	}

	function ReadMsgHeader($msg)
	{
		$q = mysql_query('SELECT DATETIME,RECIVDATE,FROMADDR,TOADDR,FROMNAME,TONAME,SUBJ,ATTRIB'.
			' FROM '.$this->table.
			(strcmp($this->echo,'')==0?'':' WHERE AREA = "'.$this->echo.'"').
			' ORDER BY NUMBER LIMIT 1'.($msg==1?'':' OFFSET '.($msg-1))
			,$this->id);
		$arr = mysql_fetch_row($q);
		mysql_free_result($q);
		$result = new C_msgheader;
		$result->WDate = Mysql_date_to_unix($arr[0]);
		$result->ADate = Mysql_date_to_unix($arr[1]);
		$result->FromAddr = $arr[2];
		$result->ToAddr = $arr[3];
		$result->From = $arr[4];
		$result->To = $arr[5];
		$result->Subj = $arr[6];
		$result->Attrs = $arr[7];
		return $result;
	}

	function SetAttr($msg,$attrs)
	{
		$q = mysql_query("SELECT NUMBER FROM {$this->table}".
			(strcmp($this->echo,'')==0?'':' WHERE AREA = "'.$this->echo.'"').
			' ORDER BY NUMBER LIMIT 1'.($msg==1?'':' OFFSET '.($msg-1))
			,$this->id);
		if (!$q) return 0;
		$num = mysql_result($q,0);
		mysql_free_result($q);

		if (mysql_query("UPDATE {$this->table} SET ATTRIB = $attrs WHERE".
			(strcmp($this->echo,'')==0?'':' AREA = "'.$this->echo.'" AND')." NUMBER = $num",$this->id)):
				return 1;
		else:
			return 0;
		endif;
	}

	function ReadMsgBody($msg)
	{
		$q = mysql_query('SELECT BODY FROM '.$this->table.
			(strcmp($this->echo,'')==0?'':' WHERE AREA = "'.$this->echo.'"').
			' ORDER BY NUMBER LIMIT 1'.($msg==1?'':' OFFSET '.($msg-1))
			,$this->id);
		$result = mysql_fetch_row($q);
		mysql_free_result($q);
		return $result[0];
	}

	function CloseBase()
	{
		$this->_disconnectdb();
	}

	function DeleteMsg($num)
	{
		return false;
	}

	function PurgeBase()
	{
		return false;
	}

	function DeleteBase($path)
	{
		return false;
	}

	function WriteMessage($header,$message)
	{
		$msgid = '';
		$reply = '';
		$line = strtok($message,"\r");
		while($line):
			if (strcmp(substr($line,0,1),chr(1))==0):
				if (!$msgid && (strcasecmp(substr($line,1,7),'MSGID: ')==0)):
					$msgid = trim(substr($line,8));
				elseif (!$reply && (strcasecmp(substr($line,1,7),'REPLY: ')==0)):
					$reply = trim(substr($line,8));
				endif;
			else:
				break;
			endif;
			$line = strtok("\r");
		endwhile;

		if ($res = mysql_query("SELECT MAX(NUMBER) AS max_id FROM ".$this->table.(strcmp($this->echo,'')==0?'':' WHERE AREA = "'.$this->echo.'"'),$this->id)):
			if (mysql_num_rows($res)):
				$num = mysql_result($res,0) + 1;
			else:
				$num = 1;
			endif;
			mysql_free_result($res);
		else:
			$num = 1;
		endif;

		mysql_query('
INSERT INTO '.$this->table.' (AREA, MSGID, REPLYID, SUBJ, FROMNAME, TONAME, FROMADDR, TOADDR, NUMBER, BODY, RAZMER, DATETIME, RECIVDATE, ATTRIB)
 VALUES ('.
'\''.mysql_real_escape_string($this->echo).'\','. // AREA
'\''.mysql_real_escape_string($msgid).'\','. // MSGID
'\''.mysql_real_escape_string($reply).'\','. // REPLYID
'\''.mysql_real_escape_string($header->Subj).'\','. // SUBJ
'\''.mysql_real_escape_string($header->From).'\','. // FROMNAME
'\''.mysql_real_escape_string($header->To).'\','. // TONAME
'\''.mysql_real_escape_string($header->FromAddr).'\','. // FROMADDR
'\''.mysql_real_escape_string($header->ToAddr).'\','. // TOADDR
'\''.mysql_real_escape_string($num).'\','. // NUMBER
'\''.mysql_real_escape_string($message).'\','. // BODY
'\''.strlen($message).'\','. // RAZMER
'\''.mysql_real_escape_string(date('Y-m-d H:i:s',($header->WDate?$header->WDate:time()))).'\','. // DATETIME
'\''.mysql_real_escape_string($header->ADate?(date('Y-m-d H:i:s',$header->ADate)):'0000-00-00 00:00:00').'\','. // RECIVDATE
'\''.mysql_real_escape_string($header->Attrs).'\''. // ATTRIB
');',$this->id);
	}
}

?>
