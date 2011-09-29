#!/usr/bin/php
<?
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
/    $Id: L_genstat.php,v 1.2 2007/10/14 08:29:54 kocharin Exp $
\*/

xinclude('F_utils.php');

function L_genstat_is_not_hidden($isareas,$str)
{
	GLOBAL $CONFIG;
	if ($isareas) {
		if (($num = $CONFIG->Areas->FindAreaNum($str)) == -1) return 0;
		return (!$CONFIG->Areas->Vals[$num]->IssetAttr(
			$CONFIG->GetVar('address'),'h'));
	} else {
		return (!$CONFIG->GetLinkVar($str,'hidden'));
	}
}

function GenStatLoadStats($file,$period,$isareas = 0)
{
	GLOBAL $Stat_items, $Stat_sum, $Stat_max, $CONFIG;
	switch($period) {
		case 0:
			$mark = (int)(time()/3600/24-1)&65535;
			break;
		case 1:
			$mark = (int)(time()/3600/24/7-1)&65535;
			break;
		case 2:
			$mark = date('y')*12+date('m')-2;
			break;
	}
	$Stat_items = array();
	$Stat_sum = $Stat_max = array(0,0,0,0,0);
	if ($f = @fopen($file,"r")) {
		while(!feof($f)) {
			$str = fgetasciiz($f);
			$endnode = fgetint($f,2);
			if (feof($f)) break;
			$pos = ftell($f);
			if (L_genstat_is_not_hidden($isareas,$str)) {
				fseek($f,$pos+$endnode,0);
				$m = fgetint($f,2);
				if ($m != $mark) {
					if ($endnode) {
						$endnode -= 18;
					} else {
						$endnode = 79*18;
					}
					fseek($f,$pos+$endnode,0);
					$m = fgetint($f,2);
				}
				if ($m == $mark) {
					$v = array();
					$v[0] = fgetint($f,4);
					$v[1] = fgetint($f,4);
					$v[2] = fgetint($f,4);
					$v[3] = fgetint($f,4);
					$Stat_sum[0] += $v[0];
					$Stat_sum[1] += $v[1];
					$Stat_sum[2] += $v[2];
					$Stat_sum[3] += $v[3];
					if ($v[0]>$Stat_max[0]) $Stat_max[0] = $v[0];
					if ($v[1]>$Stat_max[1]) $Stat_max[1] = $v[1];
					if ($v[2]>$Stat_max[2]) $Stat_max[2] = $v[2];
					if ($v[3]>$Stat_max[3]) $Stat_max[3] = $v[3];
					if ($v[2]+$v[3]>$Stat_max[5]) $Stat_max[5] = $v[2] + $v[3];
					$Stat_items[$str] = $v;
				}
			}
			fseek($f,$pos+80*18,0);
//			print dechex(ftell($f))."\n";
		}
	} else {
		tolog("E","Could not open $file for reading");
	}
}

function PlainLinksStat($file,$period)
{
	GLOBAL $Stat_items, $Stat_sum, $Stat_max;
	GenStatLoadStats($file,$period);
	uksort($Stat_items,'L_genstat_Links_cmp');
	$result = '';
	$result.= multi(' ',24-$period/2).'\   Links '.
		($period==0?'daily':($period==1?'weekly':'monthly')).' statistics   /'.
		"\n .".multi('-',21-$period/2)."'^`\\".multi('_',24+$period)."/'^`".
		multi('-',22-($period+1)/2).".\n\n  --* Link *--  --* Rcvd from *--  --* Packed to *--  -------* Graph *-------\n                 msgs  size  pers   msgs  size  pers\n\n";
	foreach($Stat_items as $link=>$v) {
		$p1 = ((int)($v[2]*1000/$Stat_sum[2])/10);
		if (substr($p1,strlen($p1)-2,1) != '.') $p1 .= '.0';
		$p1 .= "%";
		$p2 = ((int)($v[3]*1000/$Stat_sum[3])/10);
		if (substr($p2,strlen($p2)-2,1) != '.') $p2 .= '.0';
		$p2 .= "%";
		$sv[0] = tosize($v[0]);
		$sv[1] = tosize($v[1]);
		$sv[2] = tosize($v[2]);
		$sv[3] = tosize($v[3]);
		$result.= "  ".$link.multi(' ',19-strlen($link)-strlen($sv[0])).$sv[0];
		$result.= multi(' ',6-strlen($sv[2])).$sv[2];
		$result.= multi(' ',6-strlen($p1)).$p1;
		$result.= multi(' ',7-strlen($sv[1])).$sv[1];
		$result.= multi(' ',6-strlen($sv[3])).$sv[3];
		$result.= multi(' ',6-strlen($p2)).$p2;
		$k1 = $v[2]*22/($Stat_max[5]);
		$k2 = $v[3]*22/($Stat_max[5]);
		$result.= multi(' ',2+(22-$k1-$k2)/2);
		$result.= multi('*',$k1);
		$result.= multi('=',$k2);
		$result.= "\n";
	}
	$result.= "\n".' `---------------------------------------------------------------------------\''."\n";
	return $result;
}

function L_genstat_Areas_cmp($a,$b)
{
	return ($b[0]-$a[0]);
}

function L_genstat_Links_cmp($a,$b)
{
	$zone1 = strtok($a,':');
	$net1 = strtok('/');
	$node1 = strtok('.');
	$pnt1 = strtok('@');
	$zone2 = strtok($b,':');
	$net2 = strtok('/');
	$node2 = strtok('.');
	$pnt2 = strtok('@');
	if ($zone1 > $zone2) {
		return 1;
	} else if ($zone1 < $zone2) {
		return -1;
	} else if ($net1 > $net2) {
		return 1;
	} else if ($net1 < $net2) {
		return -1;
	} else if ($node1 > $node2) {
		return 1;
	} else if ($node1 < $node2) {
		return -1;
	} else if ($pnt1 > $pnt2) {
		return 1;
	} else if ($pnt1 < $pnt2) {
		return -1;
	} else {
		return 0;
	}
}

function PlainAreasStat($file,$period,$topnum)
{
	GLOBAL $Stat_items, $Stat_sum, $Stat_max;
	GenStatLoadStats($file,$period,1);
	uasort($Stat_items,'L_genstat_Areas_cmp');
	$result = '';
	$result.= multi(' ',24-$period/2).'\   Areas '.
		($period==0?'daily':($period==1?'weekly':'monthly')).' statistics   /'.
		"\n .".multi('-',21-$period/2)."'^`\\".multi('_',24+$period)."/'^`".
		multi('-',22-($period+1)/2).".\n\n  --------* Area *--------  -----* Traffic *------  --------* Graph *--------\n                            msgs  pers  size  pers\n\n";
	$cnt = 0;
	foreach($Stat_items as $area=>$v) {
		if ($cnt >= $topnum) break;
		$p1 = ((int)($v[0]*1000/$Stat_sum[0])/10);
		if (substr($p1,strlen($p1)-2,1) != '.') $p1 .= '.0';
		$p1 .= "%";
		$p2 = ((int)($v[2]*1000/$Stat_sum[2])/10);
		if (substr($p2,strlen($p2)-2,1) != '.') $p2 .= '.0';
		$p2 .= "%";
		$sv[0] = tosize($v[0]);
		$sv[2] = tosize($v[2]);
		$usarea = (strlen($area)>20?substr($area,0,18).'..':$area);
		$result.= multi(' ',4-strlen($cnt)).$cnt.": ".$usarea.multi(' ',26-strlen($usarea)-strlen($sv[0])).$sv[0];
		$result.= multi(' ',6-strlen($p1)).$p1;
		$result.= multi(' ',6-strlen($sv[2])).$sv[2];
		$result.= multi(' ',6-strlen($p2)).$p2;
#		$result.= multi(' ',7-strlen($sv[1])).$sv[1];
#		$result.= multi(' ',6-strlen($sv[3])).$sv[3];
#		$result.= multi(' ',6-strlen($p2)).$p2;
		$k1 = ($v[0]*11)/($Stat_max[0]);
		$k2 = ($v[2]*12)/($Stat_max[2]);
		$result.= multi(' ',13-(int)($k1));
		$result.= multi('*',(int)($k1)+1);
		$result.= multi('=',(int)($k2)+1);
		$result.= "\n";
		$cnt++;
	}
	$sv0 = tosize($Stat_sum[0]);
	$sv1 = tosize($Stat_sum[2]);
	$result .= "   -  -  -  -  -  -  -  -  -  -  -  -  -  -  -  -  -  -  -  -  -  -  -  -  -\n";
	$result .= "  Total: ".($n = sizeof($Stat_items))." areas".
		multi(' ',17-strlen($n)-strlen($sv0)).$sv0.'  100%'.
		multi(' ',6-strlen($sv1)).$sv1."  100%  ************=============";
	$result.= "\n".' `---------------------------------------------------------------------------\''."\n";
	return $result;
}

if ($_SERVER['argc'] == 2) {
    switch($_SERVER['argv'][1]) {
	case 0:
	    print PlainLinksStat('/fido/cfg/stat/links_daily.stat',0);
	    break;
	case 1:
	    print PlainLinksStat('/fido/cfg/stat/links_weekly.stat',1);
	    break;
	case 2:
	    print PlainLinksStat('/fido/cfg/stat/links_monthly.stat',2);
	    break;
	case 3:
	    print PlainAreasStat('/fido/cfg/stat/areas_daily.stat',0,20);
	    break;
	case 4:
	    print PlainAreasStat('/fido/cfg/stat/areas_weekly.stat',1,20);
	    break;
	case 5:
	    print PlainAreasStat('/fido/cfg/stat/areas_monthly.stat',2,1000);
	    break;
	default:
	    print "Usage: L_genstat.php 0|1|2|3|4|5\n";
    }
} else {
    print "Usage: L_genstat.php 0|1|2|3|4|5\n";
}

?>
