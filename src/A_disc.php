<?php
/*\
  /  This is a part of PhFiTo (aka PHP Fido Tosser)
 //  Copyright (c) Alex Kocharin, 2:50/13
///
///  This program is distributed under GNU GPL v2
///  See docs/license for details
//
/    $Id: A_disc.php,v 1.6 2011/01/09 03:39:17 kocharin Exp $
\*/

if (!function_exists('xinclude')) die();

function Receive_mail()
{
	GLOBAL $CONFIG;
	if ($CONFIG->Links->Vals) {
		reset($CONFIG->Links->Vals);
		while(list($aka,$link) = each($CONFIG->Links->Vals)):
			if ($dir = @$link['movefrom']):
				$d = opendir($dir);
				while($file = readdir($d)):
					if (is_file($dir.'/'.$file)):
						if (SaveFileToInbound($file,file_get_contents($dir.'/'.$file),1)):
							@unlink($dir.'/'.$file);
						endif;
					endif;
				endwhile;
				closedir($d);
			endif;
		endwhile;
	}
}

function Send_mail()
{
	GLOBAL $CONFIG;
	GLOBAL $Outbound;
	if (!isset($Outbound)) return;
	foreach($Outbound->GetLinks() as $link):
		if ($dest = $CONFIG->GetLinkVar($link,'moveto')):
			foreach($Outbound->GetMailTo($link) as $file):
				$fbase = basename($file);
				$i = '';
				if (preg_match('/[0-9a-f]{8}\.[odhic]ut/i',$fbase)) :
					$fas = getrand8().'.pkt';
				else:
					$fas = $fbase;
				endif;
				while (file_exists($fnm = $dest.'/'.$fas.$i)) $i++;
				if ($f = fopen($fnm,'w')):
					fwrite($f,file_get_contents($file));
					fclose($f);
					$Outbound->DeleteSnt($link,$fbase);
					tolog('S',"Sent file $file as $fas$i");
				else:
					tolog('E',"Could not open file \"$fnm\"");
				endif;
			endforeach;
		endif;
	endforeach;
}