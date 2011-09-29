<?
/*\
  /  This is a part of PhFiTo (aka PHP Fido Tosser)
 //  Copyright (c) Alex Kocharin, 2:50/13
///
///  This program is distributed under GNU GPL v2
///  See docs/license for details
//
/    $Id: L_modules.php,v 1.6 2007/10/14 08:29:54 kocharin Exp $
\*/

if (!function_exists('xinclude')) die();

function modules_init()
{
	GLOBAL $CONFIG, $MODULES;
	foreach($CONFIG->GetArray('module') as $module) {
		$class = rtrim(strtok($module,' '));
		$file = ltrim(strtok(' '));
		if (!class_exists($class)) {
			@include $file;
		}
		if (class_exists($class)) {
			$new = new $class;
			if (method_exists($new,'init')) {
				$new->init();
			}
			$MODULES[] = &$new;
			unset($new);
		} else {
			tolog('E','Could not load module '.$class);
		}
	}
}

function modules_hook($hook,$params)
{
	GLOBAL $MODULES;
	for($i=0,$s=sizeof($MODULES); $i<$s; $i++) {
		if (method_exists($MODULES[$i],'hook_'.$hook)) {
			$MODULES[$i]->{'hook_'.$hook}($params);
		}
	}
}

function modules_hook_over($hook, $params, $value)
{
	GLOBAL $MODULES;
	for($i=0,$s=sizeof($MODULES); $i<$s; $i++) {
		if (method_exists($MODULES[$i],'hook_'.$hook)) {
			$value = $MODULES[$i]->{'hook_'.$hook}($params, $value);
		}
	}
	return $value;
}

function modules_done()
{
	GLOBAL $MODULES;
	for($i=0,$s=sizeof($MODULES); $i<$s; $i++) {
		if (method_exists($MODULES[$i],'done')) {
			$MODULES[$i]->done();
		}
		unset($MODULES[$i]);
	}
}

?>
