<?
/*\
  /  This is a part of PhFiTo (aka PHP Fido Tosser)
 //  Copyright (c) Alex Kocharin, 2:50/13
///
///  This program is distributed under GNU GPL v2
///  See docs/license for details
//
/    $Id: M_uueauto.php,v 1.5 2011/01/08 12:21:09 kocharin Exp $
\*/

if (!function_exists('xinclude')) die();

class M_uue_auto_decode
{
	function decode_lines(&$ls,$from,$to,$filename)
	{
		$decoded = '';
		for($i=$from; $i<=$to; $i++) {
			$line = rtrim($ls[$i]);
			if ($line !== '') {
				$len = ord($line{0}) == 0x60 ? 0 : ord($line{0}) - 0x20;
				if (($len >= 0) && ($len <= 60)) {
					$c = count($bytes = unpack('c*', substr($line, 1)));
					while ($c % 4) $bytes[++$c] = 0;

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
				} else {
					return 0;
				}
			}
		}

		if (!is_dir($bn = dirname($filename)) && !@mkdir($bn)) {
			tolog("E","Could not create directory '".$bn."'");
			return 0;
		} else if ($f = @fopen($filename,'w')) {
			fwrite($f,$decoded,$dec = strlen($decoded));
			fclose($f);
			return $dec;
		} else {
			return 0;
		}
	}

	function decode_to($decodeto)
	{
		GLOBAL $CurrentMessage, $CONFIG;
		$msg = &$CurrentMessage->MsgText;
		$lines = explode("\r",$msg);
		$begin = -1;
		for($i=0,$sl=sizeof($lines);$i<$sl;$i++) {
			if (preg_match('/^begin [0-7]+ (.*)$/',$lines[$i],$match)) {
				$filem = trim($match[1]);
				$begin = $i;
			} else if (($begin >= 0) && ($i-$begin > 1) && (rtrim($lines[$i]) == 'end')) {
				$filename = $decodeto.'/'.preg_replace('/[^a-z0-9\.\-_]/i','_',$filem);
				$idx = 0; $s = '';
				while(file_exists($filename.$s)) {
					$idx++; $s = ".".$idx;
				}
				$filename .= $s;
				if ($bytes = $this->decode_lines(&$lines, $begin+1, $i-1, $filename)) {
					$lines[$begin] = $lines[$i] = '';
					for($ii=$begin+1; $ii<$i; $ii++) {
						$lines[$ii] = false;
					}
					$lines[($begin+$i)/2-1] = "  /       \"$filem\" (".($i-$begin+1)." lines, contains ".$bytes." bytes)";
					$lines[($begin+$i)/2]   = " (   UUE  decoded to \"$filename\"";
					$lines[($begin+$i)/2+1] = "  \       and skipped by Phfito on ".$CONFIG->GetVar("address");
				}
				$begin = -1;
			}
		}
		$msg = '';
		for($i=0;$i<$sl;$i++) {
			if ($lines[$i] !== false) {
				$msg .= $lines[$i]."\r";
			}
		}
		$msg = rtrim($msg);
	}
	
	function hook_store($p)
	{
		GLOBAL $CONFIG;
		list($area,$origarea,$link) = $p;
		if (($num = $CONFIG->Areas->FindAreaNum($area))==-1) {
			return;
		}
		if (isset($CONFIG->Areas->Vals[$num]->Options['-uuedecode'])) {
			$decodeto = $CONFIG->Areas->Vals[$num]->Options['-uuedecode'];
			if (strlen($decodeto)) {
				$decodeto = rtrim(str_replace('$AREA$',$CONFIG->Areas->Vals[$num]->EchoTag,$decodeto),"/");
				$this->decode_to($decodeto);
			}
		}
	}
}

?>
