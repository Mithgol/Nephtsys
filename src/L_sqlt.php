<?
/*\
  /  This is a part of PhFiTo (aka PHP Fido Tosser)
 //  Copyright (c) Alex Kocharin, 2:50/13
///
///  This program is distributed under GNU GPL v2
///  See docs/license for details
//
/    $Id: L_sqlt.php,v 1.4 2011/01/18 05:16:51 kocharin Exp $
\*/

if (!function_exists('xinclude')) die();

xinclude('L_areas.php');

function SQL_start()
{
	GLOBAL $CONFIG, $SQLCONN;
	$SQLCONN = array();
	tolog('q', 'Starting sql subsystem');
	foreach ($CONFIG->GetArray('sqlbase') as $sqlbase) {
		$params = explode(' ', $sqlbase);
		$SQLCONN[$params[0]] = array(false, 'SQL_connectdb', $params);
	}
}

function SQL_gethandle($id)
{
	GLOBAL $SQLCONN;
	if (!is_array($SQLCONN[$id])) {
		tolog('E', "Wrong handle: $id");
		return false;
	}
	if (!$SQLCONN[$id][0]) {
		call_user_func($SQLCONN[$id][1], $SQLCONN[$id][2]);
	}
	if ($SQLCONN[$id][0]) {
		return $SQLCONN[$id][1];
	} else {
		return false;
	}
}

function SQL_connectdb($params)
{
	GLOBAL $SQLCONN;
	$user = false;
	$pass = false;
	$host = false;
	$db = false;
	$base = $params[0];
	for ($i=1; $i<sizeof($params); $i++) {
		list($key, $value) = explode('=', $params[$i], 2);
		$key = strtolower($key);
		if ($key == 'user') {
			$user = $value;
		} elseif ($key == 'pass') {
			$pass = $value;
		} elseif ($key == 'host') {
			$host = $value;
		} elseif ($key == 'db') {
			$db = $value;
		} 
	}

	tolog('q', 'opening connection to '.$base);
	$id = false;
	if ($pass === false) {
		$pass = ini_get("mysql.default_password");
	}
	if ($user === false) {
		$user = ini_get("mysql.default_user");
	}
	if ($host === false) {
		$host = ini_get("mysql.default_host");
	}
	$id = mysql_connect($host, $user, $pass, true);
	if ($id === false) {
		tolog('E', "Could not connect to database $user@$host");
		return false;
	}
	if ($db !== false) {
		mysql_select_db($db, $id);
	} else {
		tolog('E', "Database is not specified for $base");
	}
	$SQLCONN[$base] = array(true, $id);
	return $id;
}

function SQL_dupechk($base, $area, $msgid, $crc, $date, $store)
{
	$sbase = explode('/', trim($base, '/'));
	if (sizeof($sbase) != 2) {
		tolog('E', "Wrong sql dupebase: $base");
		return 0;
	}
	list($handle, $table) = $sbase;
	$mh = SQL_gethandle($handle);
	if ($mh === false) return 0;
	if ($store) {
		return SQL_dupestore($mh, $table, $area, $msgid, $crc, $date);
	} else {
		return SQL_duperealchk($mh, $table, $area, $msgid, $crc, $date);
	}
}

function SQL_dupe_trycreate($table, $mh)
{
	mysql_query('
		CREATE TABLE IF NOT EXISTS `'.mysql_real_escape_string($table, $mh).'` (
			area       VARCHAR(80) NOT NULL,
			msgid      VARCHAR(255) NOT NULL,
			crc        INT UNSIGNED NOT NULL,
			date       TIMESTAMP NOT NULL
		);
	',$mh);
	mysql_query('ALTER TABLE `'.mysql_real_escape_string($table, $mh).'` ADD INDEX (`area`)', $mh);
	mysql_query('ALTER TABLE `'.mysql_real_escape_string($table, $mh).'` ADD INDEX (`crc`)', $mh);
}

function SQL_dupestore($mh, $table, $area, $msgid, $crc, $date)
{
	$query = 'INSERT INTO '.$table.' (area, msgid, crc, date)
		VALUES ('.
			'\''.mysql_real_escape_string($area, $mh).'\','.
			'\''.mysql_real_escape_string($msgid, $mh).'\','.
			'\''.mysql_real_escape_string($crc, $mh).'\','.
			'FROM_UNIXTIME('.mysql_real_escape_string($date, $mh).')'.
		');';
	if (!mysql_query($query, $mh)) {
		SQL_dupe_trycreate($table, $mh);
		if (!mysql_query($query, $mh)) {
			tolog('E', "Could not write to dupebase table $table");
		}
	}
}

function SQL_duperealchk($mh, $table, $area, $msgid, $crc, $date)
{
	$res = mysql_query('SELECT msgid, UNIX_TIMESTAMP(date) as date FROM `'.mysql_real_escape_string($table, $mh).'` 
		WHERE crc=\''.mysql_real_escape_string($crc, $mh).'\' AND area=\''.mysql_real_escape_string($area, $mh).'\';', $mh);
	if (!$res) return 0;

	while ($row = mysql_fetch_assoc($res)) {
		if ($row['msgid'] == $msgid) {
			if (abs($row['date'] - $date) > 180*24*60*60) {
				tolog('E', "Duplicate msgid, but different dates: $msgid");
			} else {
				mysql_free_result($res);
				return 1;
			}
		} else {
			tolog('E', "CRC collision: ".$row['msgid']." vs $msgid");
		}
	}
	mysql_free_result($res);
	return 0;
}

function SQL_stop()
{
	GLOBAL $CONFIG, $SQLCONN;
	foreach($SQLCONN as $base=>$arr) {
		if ($arr[0]) {
			mysql_close($arr[1]);
			tolog('q', 'closing connection to '.$base);
		}
	}
}

function SQL_scan()
{
}

?>
