<?php
/*\
  /  This is a part of PhFiTo (aka PHP Fido Tosser)
 //  Copyright (c) Alex Kocharin, 2:50/13
///
///  This program is distributed under GNU GPL v2
///  See docs/license for details
//
/    $Id: L_mysql.php,v 1.5 2011/01/10 01:47:08 kocharin Exp $
\*/

if (!function_exists('xinclude')) die();

function MysqlConnMgr($cmd,$optional = array())
{
	// 0 - close
	// 1 - closeall
	// 2 - new connection
	// 3 - log
	STATIC $ids;
	STATIC $log;

	switch ($cmd) {
		case 0:
			break;
		case 1:
			foreach($ids as $key=>$id) {
				mysql_close($id);
				if ($log) fwrite($log,"======== Connection closed\n\n\n\n");
			}
			if ($log) fclose($log);
			break;
		case 2:
			if (!$log) {
				GLOBAL $CONFIG;
				if ($a = $CONFIG->GetVar('mysqllogfile')) { 
					$log = @fopen($a,"a");
				}
			}
			list($host,$user,$pass) = $optional;
			$key = strlen($host).' '.strlen($user).' '.strlen($pass).' '.$host.$user.$pass;
			if (isset($ids[$key])) {
				return $ids[$key];
			} else {
				if ($log) {
					fwrite($log,"======== Connecting to $host\n\n");
				}
				if ($pass !== false) {
					$ids[$key] = mysql_connect($host,$user,$pass);
				} else if ($user !== false) {
					$ids[$key] = mysql_connect($host,$user);
				} else {
					$ids[$key] = mysql_connect($host);
				}
				return $ids[$key];
			}
		case 3:
			if ($log) {
				fwrite($log,"======== {$optional[0]} {$optional[1]}:\n{$optional[2]}\n\n");
			}
			break;
	}
}

class C_mysql
{
	var $id;
	var $log;

	function result($res,$num,$field = false)
	{
		if ($field !== false) {
			return mysql_result($res,$num,$field);
		} else {
			return mysql_result($res,$num); 
		}
	}

	function numrows($res)
	{
		return mysql_num_rows($res);
	}

	function free($res)
	{
		return mysql_free_result($res);
	}

	function query($string)
	{
		$res = mysql_query($string,$this->id);
		MysqlConnMgr(3,array('Query',($res?'ok':'failed'),$string));
		return $res;
	}

	function open($host,$user = false,$pass = false)
	{
		$this->id = MysqlConnMgr(2,array($host,$user,$pass));
	}

	function close()
	{
		MysqlConnMgr(0);
	}

	function opendb($db)
	{
		return mysql_select_db($db,$this->id);
	}

	function fetchrow($res)
	{
		return mysql_fetch_row($res);
	}

	function escape($str)
	{
		return mysql_real_escape_string($str);
	}
}