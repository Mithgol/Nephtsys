<?
/*\
  /  This is a part of PhFiTo (aka PHP Fido Tosser)
 //  Copyright (c) Alex Kocharin, 2:50/13
///
///  This program is distributed under GNU GPL v2
///  See docs/license for details
//
/    $Id: L_fidonet.php,v 1.10 2011/01/09 03:39:17 kocharin Exp $
\*/

if (!function_exists('xinclude')) die();

xinclude('F_utils.php');

function SplitMessage($alt = false)
{
	GLOBAL $CONFIG, $CurrentMessageParsed, $CurrentMessageAlt;
	if ($alt) {
		$cmsg = $CurrentMessageAlt;
	} else {
		$cmsg = $CurrentMessageParsed;
	}
	$maxlines = $CONFIG->GetVar('maxmsglines');
	if (!$maxlines) $maxlines = 0x7fffffff;
	$maxsize = $CONFIG->GetVar('maxmsgsize')*1024;
	if (!$maxsize) $maxsize = 0x7fffffff;
	$line = strtok($cmsg->top,"\r");
	$topkludge = $toppart = '';
	while ($line) {
		if ((strcasecmp(substr($line,0,5),chr(1).'INTL')==0) ||
			(strcasecmp(substr($line,0,5),chr(1).'FMPT')==0) ||
			(strcasecmp(substr($line,0,5),chr(1).'TOPT')==0) ||
			(strcasecmp(substr($line,0,5),chr(1).'CHRS')==0) ||
			(strcasecmp(substr($line,0,6),chr(1).'REPLY')==0)) {
				$toppart .= $line."\r";
		} else if (strcasecmp(substr($line,0,6),chr(1).'MSGID')!=0) {
			$topkludge .= $line."\r";
		}
		$line = strtok("\r");
	}
	$msgbody = $cmsg->body;
	$botpart = $cmsg->tearline.$cmsg->origin.$cmsg->bottom;
	
	$rfc = '';
	if (strcasecmp(substr($msgbody,0,3),'to:')==0) {
		$p = strpos($msgbody,"\r");
		$rfc .= substr($msgbody,0,$p+1);
		$msgbody = substr($msgbody,$p+1);
	}
	if (strcasecmp(substr($msgbody,0,5),'from:')==0) {
		$p = strpos($msgbody,"\r");
		$rfc .= substr($msgbody,0,$p+1);
		$msgbody = substr($msgbody,$p+1);
		if (strcasecmp(substr($msgbody,0,3),'to:')==0) {
			$p = strpos($msgbody,"\r");
			$rfc .= substr($msgbody,0,$p+1);
			$msgbody = substr($msgbody,$p+1);
		}
	}
	
	$result = array();
	$number = 0;
	while ($msgbody || ($number == 0)) {
		$number++;
		$pr = 0;
		$p = strpos($msgbody,"\r");
		$lines = 0;
		while (($p !== false) && ($p < $maxsize) && ($lines < $maxlines)) {
			$pr = $p;
			$p = strpos($msgbody,"\r",$pr+1);
			$lines++;
		}
		if ($pr > 10) {
			$parts[] = substr($msgbody,0,$pr+1);
			$msgbody = substr($msgbody,$pr+1);
		} else {
			$parts[] = $msgbody;
			$msgbody = '';
		}
	}

	$area = $cmsg->area;
	$time = date('d M y H:i:s');
	$orig = GetFidoAddr($cmsg->FromAddr);
	$orig_2d = $orig[1].'/'.$orig[2];
	$msg = rand(0,99999);
	$str = chr(1).'SPLIT: '.$time.' @'.$orig_2d.multi(' ',15-strlen($orig_2d)-strlen($msg)).$msg.'   ';
	for ($num = 0; $num < $number; $num++) {
		$msgid = chr(1).'MSGID: '.$cmsg->msgid;
		if ($num > 0) {
			$p = strrpos($msgid,' ');
			$msgid = substr($msgid,0,$p+1).getmsgid();
		}
		if ($number > 1) {
			$split = $str.(($num+1)>=10?'':'0').($num+1).'/'.($number>=10?'':'0').$number." ++++++++++++\r";
		} else {
			$split = '';
		}
		$result[] = ($area?'AREA:'.$area."\r":'').($num==0?$topkludge:'').$msgid."\r".$toppart.$split.$rfc.$parts[$num].$botpart;
	}
	
	return $result;
}

function GetEchoMsgOrigDest($message)
{
	$message = str_replace("\r","\r".chr(0),$message);
	$dest = '0:0/0.0';
	$orig = false;
	$msgid = '';
	$origin = '';
	$line = strtok($message,"\r");

	while($line):
		if (strcmp(substr($line,0,1),chr(0))==0) $line = substr($line,1);
		if (strcmp(substr($line,0,1),chr(1))==0):
			if (!$msgid && (strcasecmp(substr($line,1,7),'MSGID: ')==0)):
				$msgid = trim(substr($line,8));
			endif;
		else:
			break;
		endif;
		$line = strtok("\r");
	endwhile;
	$line = chr(0).$line;
	while($line):
		if (strcmp(substr($line,0,1),chr(0))==0) $line = substr($line,1);
		if (strcmp(substr($line,0,11),' * Origin: ')==0):
			$origin = trim(substr($line,11));
		endif;
		$line = strtok("\r");
	endwhile;
	if ($origin):
		$rp = strrpos($origin,'(');
		$origin = substr($origin,$rp+1);
		$orig = JoinFidoAddr(GetFidoAddr($origin));
	endif;
	if ($orig === false):
		if ($msgid) $orig = JoinFidoAddr(GetFidoAddr($msgid));
	endif;
	if ($orig === false):
		$orig = '0:0/0.0';
	endif;
	return array($orig,$dest,$msgid);
}

function GetNetMsgOrigDest($message)
{
	GLOBAL $CONFIG;
	$message = str_replace("\r","\r".chr(0),$message);
	$intl = '';
	$topt = '';
	$fmpt = '';
	$origin = '';
	$msgid = '';
	
	$line = strtok($message,"\r");
	while($line):
		if (strcmp(substr($line,0,1),chr(0))==0) $line = substr($line,1);
		if (strcmp(substr($line,0,1),chr(1))==0):
			if (!$msgid && (strcasecmp(substr($line,1,7),'MSGID: ')==0)):
				$msgid = trim(substr($line,8));
			elseif (!$intl && (strcasecmp(substr($line,1,5),'INTL ')==0)):
				$intl = trim(substr($line,6));
			elseif (!$topt && (strcasecmp(substr($line,1,5),'TOPT ')==0)):
				$topt = trim(substr($line,6));
			elseif (!$fmpt && (strcasecmp(substr($line,1,5),'FMPT ')==0)):
				$fmpt = trim(substr($line,6));
			endif;
		else:
			break;
		endif;
		$line = strtok("\r");
	endwhile;
	$line = chr(0).$line;
	while($line):
		if (strcmp(substr($line,0,1),chr(0))==0) $line = substr($line,1);
		if (strcmp(substr($line,0,11),' * Origin: ')==0):
			$origin = trim(substr($line,11));
		endif;
		$line = strtok("\r");
	endwhile;
	$orig = false;
	$dest = false;
	if ($intl):
		list($dest,$orig) = explode(' ',$intl);
		if ($fmpt) $orig.= '.'.$fmpt;
		if ($topt) $dest.= '.'.$topt;
	endif;
	if ($orig === false):
		if ($msgid) $orig = JoinFidoAddr(GetFidoAddr($msgid));
	endif;
	if ($orig === false):
		if ($origin):
			$rp = strrpos($origin,'(');
			$origin = substr($origin,$rp+1);
			$orig = JoinFidoAddr(GetFidoAddr($origin));
		endif;
	endif;
	return array($orig,$dest,$msgid);
}

function JoinFidoAddr($addr)
{
	return $addr[0].':'.$addr[1].'/'.$addr[2].($addr[3]?'.'.$addr[3]:'');
}

function GetFidoAddr($string)
{
	if (preg_match('/(\d+)\:(\d+)\/(\d+)(\.(\d+))?/',$string,$msv)):
		$res[0] = (int)$msv[1];
		$res[1] = (int)$msv[2];
		$res[2] = (int)$msv[3];
		$res[3] = (isset($msv[5])?(int)$msv[5]:0);
		return $res;
	else:
		return false;
	endif;
}

function get_initials($name)
{
	if (strlen($name)<4) return $name;
	$result = '';
	$word = strtok($name,' _');
	while ($word):
		$result.= substr($word,0,1);
		$word = strtok(' _');
	endwhile;
	if (strlen($result)>3) return substr($result,0,3);
	return $result;
}

function format_reply($message,$inits,$delim = '>',$viewcl = 0)
{
 $message=str_replace(chr(13),chr(13).chr(0),$message);
 $txt=strtok($message,chr(13));
 while ($txt):
  $text_ms[]=str_replace(chr(0),'',$txt);
  $txt=strtok(chr(13));
 endwhile;
for ($i=sizeof($text_ms)-1;$i>=0;$i--):
  if (strcmp(substr($text_ms[$i],0,1),chr(1))==0):
   if ($viewcl) :
    $text_ms[$i][0]="@";
   else :
    $text_ms=del_elem($text_ms,$i);
   endif;
  elseif (strcasecmp(substr($text_ms[$i],0,8),'seen-by:')==0):
   if (!$viewcl) :
    $text_ms=del_elem($text_ms,$i);
   endif;
  endif;
 endfor;
 
   $s = sizeof($text_ms);
   for ($i=0; $i<$s; $i++) 
   {   
	   
   $prefix = '';	   
   $lend = strlen($delim);
   if ($t=strpos($text_ms[$i],$delim)):
   $quotelvl=0;
   for ($j=$t;strcasecmp(substr($text_ms[$i],$j,$lend),$delim)==0;$j+=$lend) {$quotelvl++;}
   if (preg_match('/^([0-9 A-Za-z]*)$/',substr($text_ms[$i],0,$t-1))):
  
    $prefix = substr($text_ms[$i],0,$t+$quotelvl*$lend);
    $text_ms[$i] = substr($text_ms[$i],$t+$quotelvl*$lend);
   endif;
   endif;

	$msv=explode("\n",cut_to_80($text_ms[$i],77-strlen($inits))); 
	foreach ($msv as $item):
   		$text_ms1[] = $prefix.$item;
	endforeach;
    }
//    $text_ms = '';
    $text_ms = $text_ms1;
    unset($text_ms1);

 $s = sizeof($text_ms);
 for ($i=0; $i<$s; $i++): if (trim($text_ms[$i])) {   
 
if (($t=strpos($text_ms[$i],$delim))!==false):
   $quotelvl=0;
   for ($j=$t;strcasecmp(substr($text_ms[$i],$j,$lend),$delim)==0;$j+=$lend) {$quotelvl++;}
   
  if (preg_match('/^([0-9 A-Za-z]*)$/',substr($text_ms[$i],0,$t))):
    $text_ms[$i]=substr($text_ms[$i],0,$t).$delim.substr($text_ms[$i],$t);
   else:
    $text_ms[$i]=' '.$inits.$delim.' '.$text_ms[$i];
   endif;
else:
   $text_ms[$i]=' '.$inits.$delim.' '.$text_ms[$i];
endif;
   
}
endfor;

 $message=implode("\n",$text_ms);
 return $message;
}

function del_elem($arr,$num)
{
 for ($i=0;$i<sizeof($arr);$i++):
  if ($i!=$num) {$rez[]=$arr[$i];}
 endfor;
 return $rez;
}

function cut_to_80($item,$charcnt)
{
	$result='';
	while (strlen($item)>$charcnt):
		$str80=substr($item,0,$charcnt);
		if (($pos=strrpos($str80,' '))>0):
			$result.=substr($item,0,$pos)."\n";
			$item=substr($item,$pos+1);
		else:
			$result.=$str80."\n";
			$item=substr($item,$charcnt);
		endif;
	endwhile;
	return $result.$item;
}

if (!function_exists('convert_uudecode')) {
    function convert_uudecode($string)
    {
        // Sanity check
        if (!is_scalar($string)) {
            user_error('convert_uuencode() expects parameter 1 to be string, ' .
                gettype($string) . ' given', E_USER_WARNING);
            return false;
        }

        if (strlen($string) < 8) {
            user_error('convert_uuencode() The given parameter is not a valid uuencoded string', E_USER_WARNING);
            return false;
        }

        $decoded = '';
        foreach (explode("\n", $string) as $line) {

            $c = count($bytes = unpack('c*', substr(trim($line), 1)));

            while ($c % 4) {
                $bytes[++$c] = 0;
            }

            foreach (array_chunk($bytes, 4) as $b) {
                $b0 = $b[0] == 0x60 ? 0 : $b[0] - 0x20;
                $b1 = $b[1] == 0x60 ? 0 : $b[1] - 0x20;
                $b2 = $b[2] == 0x60 ? 0 : $b[2] - 0x20;
                $b3 = $b[3] == 0x60 ? 0 : $b[3] - 0x20;
                
                $b0 <<= 2;
                $b0 |= ($b1 >> 4) & 0x03;
                $b1 <<= 4;
                $b1 |= ($b2 >> 2) & 0x0F;
                $b2 <<= 6;
                $b2 |= $b3 & 0x3F;
                
                $decoded .= pack('c*', $b0, $b1, $b2);
            }
        }

        return rtrim($decoded, "\0");
    }
}

if (!function_exists('convert_uuencode')) {
    function convert_uuencode($string)
    {
        // Sanity check
        if (!is_scalar($string)) {
            user_error('convert_uuencode() expects parameter 1 to be string, ' .
                gettype($string) . ' given', E_USER_WARNING);
            return false;
        }

        $u = 0;
        $encoded = '';
        
        while ($c = count($bytes = unpack('c*', substr($string, $u, 45)))) {
            $u += 45;
            $encoded .= pack('c', $c + 0x20);

            while ($c % 3) {
                $bytes[++$c] = 0;
            }

            foreach (array_chunk($bytes, 3) as $b) {
                $b0 = ($b[0] & 0xFC) >> 2;
                $b1 = (($b[0] & 0x03) << 4) + (($b[1] & 0xF0) >> 4);
                $b2 = (($b[1] & 0x0F) << 2) + (($b[2] & 0xC0) >> 6);
                $b3 = $b[2] & 0x3F;
                
                $b0 = $b0 ? $b0 + 0x20 : 0x60;
                $b1 = $b1 ? $b1 + 0x20 : 0x60;
                $b2 = $b2 ? $b2 + 0x20 : 0x60;
                $b3 = $b3 ? $b3 + 0x20 : 0x60;
                
                $encoded .= pack('c*', $b0, $b1, $b2, $b3);
            }

            $encoded .= "\n";
        }
        
        // Add termination characters
        $encoded .= "\x60\n";

        return $encoded;
    }
}

?>
