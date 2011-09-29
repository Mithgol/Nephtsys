<?
/*\
  /  This is a part of PhFiTo (aka PHP Fido Tosser)
 //  Copyright (c) Alex Kocharin, 2:50/13
///
///  This program is distributed under GNU GPL v2
///  See docs/license for details
//
/    $Id: F_archives.php,v 1.7 2011/01/09 03:39:17 kocharin Exp $
\*/

if (!function_exists('xinclude')) die();

xinclude('L_ziplib.php');
xinclude('F_utils.php');

function ExtractArchive($name, $to, $mk_subdirs = 0)
{
	tolog('b',"Extracting $name to $to");
	if (is_file($name)):
		$Archive = new ZipReader($name);
		$file_data = $Archive->ReadFile();
		while($file_data[0]):
			if ($mk_subdirs) pc_mkdir_parents($to.'/'.dirname($file_data[0]));
			if ($file_data[1] || !$mk_subdirs):
				$outf = fopen("$to/{$file_data[0]}",'w');
				fwrite($outf, $file_data[1]);
				fclose($outf);
				$result[] = $file_data[0];
			endif;
			$file_data = $Archive->ReadFile();
		endwhile;
		$Archive->done();
	endif;
	return $result;
}

function AddToArchive($arch,$fn,$tmp)
{
	tolog('b',"Adding $fn to $arch");
	$p_files = array();
	if (is_file($arch)):
		$p_files = ExtractArchive($arch,$tmp);
	endif;
	$Archive = new ZipWriter('',$arch);
	foreach($p_files as $fname):
	$Archive->addRegularFile(basename("$tmp/$fname"),file_get_contents("$tmp/$fname"));
	unlink("$tmp/$fname");
	endforeach;
	$Archive->addRegularFile(basename($fn),file_get_contents($fn));
	$Archive->finish();
	chmod($arch,0666);
}

function Get_Content_from_zip($fname)
{
	$Archive = new ZipReader($fname);
	$file_data = $Archive->ReadFile();
	$Archive->done();
	return $file_data[1];
}

function ExtractArchiveCmd($cmd,$file,$to)
{
	if (strcasecmp($cmd,"zipinternal")==0) {
		ExtractArchive($file,$to);
		return 1;
	} else {
		$p = strpos($cmd,'$');
		while (($p !== false) && ($p < (strlen($cmd)-1))) {
			if (($p >= 1) && ($cmd{$p-1}=='\\')) {
				$cmd = substr($cmd,0,$p-1).substr($cmd,$p);
			} else {
				if ($cmd{$p+1} == 'a') {
					$cmd = substr($cmd,0,$p).$file.substr($cmd,$p+2);
					$p += strlen($file);
				} else if ($cmd{$p+1} == 'p') {
					$cmd = substr($cmd,0,$p).$to.substr($cmd,$p+2);
					$p += strlen($to);
				} else {
					$p++;
				}
			}
			$p = strpos($cmd,'$',$p);
		}
		$res = system($cmd,$ret);
		if (($res === false) || ($ret != 0)) {
			tolog("E","Error during executing \"$cmd\"");
			return 0;
		} else {
			return 1;
		}
	}
}

function AddToArchivePck($arch,$fn,$tmp,$packer)
{
	GLOBAL $CONFIG;
	$pack_arr = $CONFIG->GetArray("pack");
	if ($pack_arr) {
		foreach($pack_arr as $str) {
			$pr = strtok($str,' ');
			if (strcasecmp($packer,$pr)==0) {
				$temp = $tmp.'/'.getrand8();
				mkdir($temp);
				if (!ExtractArchivePck($arch,$temp)) return 0;
				$files = '';
				if ($dir = opendir($temp)) {
					while ($f = readdir($dir)) {
						if (($f != '.') && ($f != '..')) {
							$files[] = $f;
						}
					}
					closedir($dir);
					copy($fn,$temp.'/'.basename($fn));
					$files[] = basename($fn);
					
					
					foreach($files as $f) {
						unlink($temp.'/'.$f);
					}
					rmdir($temp);
					return 1;
				} else {
					return 0;
				}
			}
/*			$arr = param_split_esc($str);
			if (sizeof($arr) == 3) {
				list($cmd,$off,$match) = $arr;
				if ($f = fopen($file,'r')) {
					fseek($f,$off,0);
					$match_code = '';
					for($i=0;$i<strlen($match);$i+=2) {
						$match_code .= chr(hexdec(substr($match,$i,2)) & 0xff);
					}
					$readed = fread($f,strlen($match_code));
					fclose($f);
					if ($readed == $match_code) {
						return ExtractArchiveCmd($cmd,$file,$to);
					}
				} else {
					return 0;
				}
			} else {
				tolog("E","Illegal unpack statement: $str");
			}*/
		}
		return 0;
	} else {
		AddToArchive($arch,$fn,$tmp);
		return 1;
	}
}

function ExtractArchivePck($file,$to)
{
	GLOBAL $CONFIG;
	$unpack_arr = $CONFIG->GetArray("unpack");
	if ($unpack_arr) {
		foreach($unpack_arr as $str) {
			$arr = param_split_esc($str);
			if (sizeof($arr) == 3) {
				list($cmd,$off,$match) = $arr;
				if ($f = fopen($file,'r')) {
					fseek($f,$off,0);
					$match_code = '';
					for($i=0;$i<strlen($match);$i+=2) {
						$match_code .= chr(hexdec(substr($match,$i,2)) & 0xff);
					}
					$readed = fread($f,strlen($match_code));
					fclose($f);
					if ($readed == $match_code) {
						return ExtractArchiveCmd($cmd,$file,$to);
					}
				} else {
					return 0;
				}
			} else {
				tolog("E","Illegal unpack statement: $str");
			}
		}
	} else {
		if ($f = fopen($file,'r')) {
			$match_code = chr(0x50).chr(0x4b).chr(0x03).chr(0x04);
			$readed = fread($f,4);
			fclose($f);
			if ($readed == $match_code) {
				return ExtractArchiveCmd("zipinternal",$file,$to);
			}
		} else {
			return 0;
		}
	}
	return 0;
}

function ExtractBundles($inbound,$temp)
{
	$resdirs = array();
	tolog('b',"Finding bundles in $inbound");
	$in_dir = opendir($inbound);
	while ($fname = readdir($in_dir)):
		tolog('b',"Found file $fname");
		if (preg_match('/^[0-9a-z]{8}\.(su|mo|tu|we|th|fr|sa)[0-9a-z]$/i',$fname)) {
			tolog('B',"Unpacking bundle $fname");
			$tmpdir = get_uniq_name($temp,'');
			if (mkdir($tmpdir) && ExtractArchivePck("$inbound/$fname","$tmpdir")) {
				unlink($inbound.'/'.$fname);
				tolog('d',"Deleting file $fname");
				$resdirs[] = $tmpdir;
			} else {
				tolog('B',"Can't read contents of $fname");
				rename($inbound.'/'.$fname,$inbound.'/'.$fname.".bad");
				@rmdir($tmpdir);
			}
		}
	endwhile;
	return $resdirs;
}

?>
