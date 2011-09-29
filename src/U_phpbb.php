<?php
//  This file is deprecated!!!!!
//  Should be removed in future release
//
//  Comment it if you're really know what it is.

die();

/*\
  /  This is a part of PhFiTo (aka PHP Fido Tosser)
 //  Copyright (c) Alex Kocharin, 2:50/13
///
///  This program is distributed under GNU GPL v2
///  See docs/license for details
//
/    $Id: U_phpbb.php,v 1.6 2011/01/10 01:47:08 kocharin Exp $
\*/

xinclude('S_config.php');

function UpdateArea_phpbb($area,$id)
{
	GLOBAL $CONFIG;
	if (($num = $CONFIG->Areas->FindAreaNum($area))==-1):
		tolog('T',"Area $area not found");
		return 0;
	endif;
	$area = $CONFIG->Areas->Vals[$num]->EchoTag;
	$descr = (isset($CONFIG->Areas->Vals[$num]->Options['-d'])?($CONFIG->Areas->Vals[$num]->Options['-d']):'');
	$descr = convert_cyr_string($descr,'k','w');
	$descr = mysql_real_escape_string($descr);
	$cat_num = (isset($CONFIG->Areas->Vals[$num]->Options['-category'])?($CONFIG->Areas->Vals[$num]->Options['-category']):1);
	$addr = $CONFIG->Areas->Vals[$num]->Path;
	if (strcmp(substr($addr,0,1),'/')==0) $addr = substr($addr,1);
	$db = strtok($addr,'/');
	$prefix = strtok('/');
	$echo = strtok('/');
	mysql_select_db($db,$id);
	
	if ($res = mysql_query("SELECT cat_id FROM {$prefix}categories ORDER BY cat_id",$id)):
		$rows = mysql_num_rows($res);
		for ($i=0;$i<$rows;$i++) $cats[] = mysql_result($res,$i);
		mysql_free_result($res);
	endif;
	$cat = isset($cats[$cat_num-1])?$cats[$cat_num-1]:(isset($cats[0])?$cats[0]:1);

	mysql_query("UPDATE {$prefix}forums SET cat_id = $cat,forum_desc = '$descr' where forum_name = '$echo'",$id);
	tolog('T',"Updating area $area ok");
	return 1;
}

function UpdateAreas_phpbb($list)
{
	GLOBAL $CONFIG;
	$host = isset($CONFIG->Vars['mysqlhost'])?$CONFIG->Vars['mysqlhost']:'localhost';
	$user = isset($CONFIG->Vars['mysqluser'])?$CONFIG->Vars['mysqluser']:'';
	$pass = isset($CONFIG->Vars['mysqlpass'])?$CONFIG->Vars['mysqlpass']:'';
	if ($pass):
		$id = mysql_connect($host,$user,$pass);
	elseif ($user):
		$id = mysql_connect($host,$user);
	else:
		$id = mysql_connect($host);
	endif;

	foreach($list as $area) UpdateArea_phpbb($area,$id);
	
	mysql_close($id);
}

function SortAreas_phpbb()
{
	GLOBAL $CONFIG;
	$host = isset($CONFIG->Vars['mysqlhost'])?$CONFIG->Vars['mysqlhost']:'localhost';
	$user = isset($CONFIG->Vars['mysqluser'])?$CONFIG->Vars['mysqluser']:'';
	$pass = isset($CONFIG->Vars['mysqlpass'])?$CONFIG->Vars['mysqlpass']:'';
	if ($pass):
		$id = mysql_connect($host,$user,$pass);
	elseif ($user):
		$id = mysql_connect($host,$user);
	else:
		$id = mysql_connect($host);
	endif;

	foreach($CONFIG->GetArray('phpbb_sort') as $addr):
		if (strcmp(substr($addr,0,1),'/')==0) $addr = substr($addr,1);
		$db = strtok($addr,'/');
		$prefix = strtok('/');
		mysql_select_db($db,$id);
		$forums = array();
		if ($res = mysql_query("SELECT forum_name from {$prefix}forums ORDER BY forum_order",$id)):
			$rows = mysql_num_rows($res);
			for ($i=0;$i<$rows;$i++) $forums[] = mysql_result($res,$i);
			sort($forums);
			$n = sizeof($forums);
			for ($i=0;$i<$n;$i++) mysql_query("UPDATE {$prefix}forums SET forum_order = $i WHERE forum_name = '{$forums[$i]}'",$id);
		endif;
		tolog('T',"Sorting $addr...");
	endforeach;
	
	mysql_close($id);
}

aks_init();
$Areas = '';
foreach($CONFIG->GetArray('autoareacreateflag') as $file):
	if (is_file($file)):
		if ($f = @fopen($file,'r')):
			while($line = fgets($f,1024)):
				if ($line = trim($line)) $Areas[] = trim($line);
			endwhile;
			fclose($f);
			@unlink($file);
		endif;
	endif;
endforeach;

if ($Areas):
	UpdateAreas_phpbb($Areas);
	SortAreas_phpbb();
endif;
aks_done();