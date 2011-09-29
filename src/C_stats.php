<?
/*\
  /  This is a part of PhFiTo (aka PHP Fido Tosser)
 //  Copyright (c) Alex Kocharin, 2:50/13
///
///  This program is distributed under GNU GPL v2
///  See docs/license for details
//
/    $Id: C_stats.php,v 1.7 2007/10/14 08:29:54 kocharin Exp $
\*/

if (!function_exists('xinclude')) die();

class C_Statistics
{
	var $Links;
	var $Areas;
	var $path;

	function Init($path,$atossed)
	{
		$this->path = $path;
		$this->atossed = $atossed;
		$this->Links = array();
		$this->Areas = array();
	}

	function write_file($file,$mark,$areas_or_links)
	{
		if (($f = @fopen($file,"r+")) || ($f = @fopen($file,"w+"))) {
			$arr = array();
			if ($areas_or_links) {
				foreach($this->Areas as $a=>$v) {
					$arr[strtolower($a)] = $v;
				}
			} else {
				foreach($this->Links as $a=>$v) {
					$arr[$a] = $v;
				}
			}
			while(!feof($f)) {
				$str = strtolower(fgetasciiz($f));
				$endnode = fgetint($f,2);
				if (feof($f)) break;
				if (isset($arr[$str])) {
					$pos = ftell($f);
					fseek($f,$endnode,1);
					$m = fgetint($f,2);
					if ($m == $mark) {
						$s1 = fgetint($f,4)+@$arr[$str][0];
						$s2 = fgetint($f,4)+@$arr[$str][1];
						$s3 = fgetint($f,4)+@$arr[$str][2];
						$s4 = fgetint($f,4)+@$arr[$str][3];
						fseek($f,-16,1);
						fputint($f,4,$s1);
						fputint($f,4,$s2);
						fputint($f,4,$s3);
						fputint($f,4,$s4);
					} else {
						$newenode = $endnode + 18;
						if ($newenode < 80*18) {
							fseek($f,16,1);
						} else {
							fseek($f,$pos,0);
							$newenode = 0;
						}
						fputint($f,2,$mark);
						fputint($f,4,@$arr[$str][0]);
						fputint($f,4,@$arr[$str][1]);
						fputint($f,4,@$arr[$str][2]);
						fputint($f,4,@$arr[$str][3]);
						fseek($f,$pos-2,0);
						fputint($f,2,$newenode);
					}
					unset($arr[$str]);
					fseek($f,$pos+80*18,0);
				} else {
					fseek($f,80*18,1);
				}
			}
			foreach($arr as $str=>$v) {
				fputasciiz($f,$str);
				fputint($f,2,0);
				fputint($f,2,$mark);
				fputint($f,4,isset($v[0])?$v[0]:0);
				fputint($f,4,isset($v[1])?$v[1]:0);
				fputint($f,4,isset($v[2])?$v[2]:0);
				fputint($f,4,isset($v[3])?$v[3]:0);
				for($i=0;$i<79;$i++) {
					fwrite($f,chr(0).chr(0));
					fwrite($f,chr(0).chr(0).chr(0).chr(0));
					fwrite($f,chr(0).chr(0).chr(0).chr(0));
					fwrite($f,chr(0).chr(0).chr(0).chr(0));
					fwrite($f,chr(0).chr(0).chr(0).chr(0));
				}
			}
			fclose($f);
		} else {
			tolog("E","Could not open $file for writing");
		}
	}

	function write_textdump($file)
	{
		if ($f = @fopen($file,"a")) {
			fwrite($f,"Areas:\n");
			foreach($this->Areas as $a=>$v) {
				fwrite($f,"$a: {$v[0]} | {$v[1]} | {$v[2]} | {$v[3]}\n");
			}
			fwrite($f,"Links:\n");
			foreach($this->Links as $a=>$v) {
				fwrite($f,"$a: {$v[0]} | {$v[1]} | {$v[2]} | {$v[3]}\n");
			}
			fwrite($f,"====================\n");
			fclose($f);
		}
	}
	
	function write_atossed($file)
	{
		if ($f = @fopen($file,"a")) {
			foreach($this->Areas as $a=>$v) {
				fwrite($f,"$a\n");
			}
			fclose($f);
		}
	}
	
	function Done()
	{
		if ($this->path) {
			$this->write_file($this->path."/areas_daily.stat",(int)(time()/3600/24)&65535,1);
			$this->write_file($this->path."/areas_weekly.stat",(int)(time()/3600/24/7)&65535,1);
			$this->write_file($this->path."/areas_monthly.stat",date('y')*12+date('m')-1,1);
			$this->write_file($this->path."/links_daily.stat",(int)(time()/3600/24)&65535,0);
			$this->write_file($this->path."/links_weekly.stat",(int)(time()/3600/24/7)&65535,0);
			$this->write_file($this->path."/links_monthly.stat",date('y')*12+date('m')-1,0);
//			$this->write_textdump($this->path."/dump.stat");
		}
		foreach ($this->atossed as $file) {
			$this->write_atossed($file);
		}
	}
}

?>
