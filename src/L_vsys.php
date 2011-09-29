<?php
/*\
  /  This is a part of PhFiTo (aka PHP Fido Tosser)
 //  Copyright (c) Alex Kocharin, 2:50/13
///
///  This program is distributed under GNU GPL v2
///  See docs/license for details
//
/    $Id: L_vsys.php,v 1.2 2007/10/14 08:29:54 kocharin Exp $
\*/

if (!function_exists('xinclude')) die();

xinclude('S_config.php');
xinclude('C_vsys.php');

function _init_vsys()
{
	GLOBAL $VSYS;
	if (!isset($VSYS)):
		$VSYS = new C_vsys;
		$VSYS->Init();
	endif;
}

function GetString($str)
{
	GLOBAL $VSYS;
	_init_vsys();
	$str = convert_cyr_string($str,'k','k');
	$result = convert_cyr_string($VSYS->Answer($str),'k','k');
	$VSYS->Save_config();
	return $result;
}

function GetStrFromFile($file)
{
	GLOBAL $VSYS;
	_init_vsys();
	$result = $VSYS->GetStrFromFile($file);
	return $result;
}

function AnswerFile()
{
	GLOBAL $VSYS;
	_init_vsys();
	$VSYS->Answer_File();
	$VSYS->Save_config();
}

function AnswerFido($area,$msg)
{
	GLOBAL $VSYS;
	_init_vsys();
	$VSYS->Answer_Fido($area,$msg);
	$VSYS->Save_config();
}

function GotoChat($str)
{
	GLOBAL $VSYS;
	_init_vsys();
	$str = convert_cyr_string($str,'k','k');
	$result = convert_cyr_string($VSYS->GotoChat($str),'k','k');
	$VSYS->Save_config();
	return $result;
}