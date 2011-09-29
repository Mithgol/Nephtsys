<?
/*\
  /  This is a part of PhFiTo (aka PHP Fido Tosser)
 //  Copyright (c) Alex Kocharin, 2:50/13
///
///  This program is distributed under GNU GPL v2
///  See docs/license for details
//
/    $Id: F_pkt.php,v 1.12 2011/01/09 08:53:12 kocharin Exp $
\*/

if (!function_exists('xinclude')) die();

xinclude('F_utils.php');

function Pkt_Report($tofile,$fromaddr,$fromname,$toaddr,$toname,$subject,$message_text)
{
	GLOBAL $CONFIG;
	$message = new Pkt_message();
	$toaddr = strtok($toaddr,'@');
	$fromaddr = strtok($fromaddr,'@');
	@list($ozone,$onet,$onode,$opoint) = preg_split("/[\:\/\.]/",$fromaddr);
	@list($dzone,$dnet,$dnode,$dpoint) = preg_split("/[\:\/\.]/",$toaddr);

	$cludge_top = chr(1)."INTL $dzone:$dnet/$dnode $ozone:$onet/$onode\r".
		chr(1)."MSGID: $fromaddr ".getmsgid()."\r".
		($opoint?(chr(1)."FMPT $opoint\r"):'').
		($dpoint?(chr(1)."TOPT $dpoint\r"):'').
		chr(1)."TID: Pkt_Report function (c)Alex Kocharin";
	$cludge_bot = chr(1)."Via $fromaddr Pkt_Report ".date('d M Y H:i:s');
	
	$message->Version = 2;
	$message->OrigNode = $onode;
	$message->DestNode = $dnode;
	$message->OrigNet = $onet;
	$message->DestNet = $dnet;
	$message->Attr = 0;
	$message->Cost = 0;
	$message->Date = time();
	$message->ToUser = $toname;
	$message->FromUser = $fromname;
	$message->Subject = $subject;
	$message->MsgText = $cludge_top."\r".$message_text."\r".$cludge_bot;
	$pkt = new FidoPacket;
	if ($pkt->Open($tofile)):
		$pkt->Unclose();
	else:
		$pkt->NewPacket($tofile,$fromaddr,$toaddr,'');
	endif;
	$pkt->message = $message;
	$pkt->AppendMessage();
	$pkt->FinishPacket();
	unset($message);
	unset($pkt);
}

class Pkt_hat
{
	var $OrigNode;
	var $DestNode;
	var $Year;
	var $Month;
	var $Day;
	var $Hour;
	var $Minute;
	var $Second;
	var $rate;
	var $Version;
	var $OrigNet;
	var $DestNet;
	var $PCodeLo;
	var $PRevMajor;
	var $Password;
	var $QMOrigZone;
	var $QMDestZone;
	var $AuxNet;
	var $CWValidate;
	var $PCodeHi;
	var $PRevMinor;
	var $CWCapWord;
	var $OrigZone;
	var $DestZone;
	var $OrigPoint;
	var $DestPoint;
	var $LongData;
	
	function Open($file)
	{
		$header = fread($file, 26);
		$arr = unpack(
			"vorig/vdest/vy/vm/vd/vh/vi/vs/vrate/vver/vonet/vdnet/Cclo/Cmaj", 
			$header);
		$this->OrigNode = $arr["orig"];
		$this->DestNode = $arr["dest"];
		$this->Year = $arr["y"];
		$this->Month = $arr["m"];
		$this->Day = $arr["d"];
		$this->Hour = $arr["h"];
		$this->Minute = $arr["i"];
		$this->Second = $arr["s"];
		$this->rate = $arr["rate"];
		$this->Version = $arr["ver"];
		$this->OrigNet = $arr["onet"];
		$this->DestNet = $arr["dnet"];
		$this->PCodeLo = $arr["clo"];
		$this->PRevMajor = $arr["maj"];
		$this->Password = fread($file,8);
		if (($p = strpos($this->Password,chr(0)))!==false)
			$this->Password = substr($this->Password,0,$p);
		$header = fread($file, 24);
		$arr = unpack(
			"vqmoz/vqmdz/vaux/vcwv/Cpch/Crev/vcap/voz/vdz/vop/vdp/Vlong", 
			$header);
		$this->QMOrigZone = $arr["qmoz"];
		$this->QMDestZone = $arr["qmdz"];
		$this->AuxNet = $arr["aux"];
		$this->CWValidate = $arr["cwv"];
		$this->PCodeHi = $arr["pch"];
		$this->PRevMinor = $arr["rev"];
		$this->CWCapWord = $arr["cap"];
		$this->OrigZone = $arr["oz"];
		$this->DestZone = $arr["dz"];
		$this->OrigPoint = $arr["op"];
		$this->DestPoint = $arr["dp"];
		$this->LongData = $arr["long"];
	}

	function WriteHdr($file,$fromaddr,$toaddr,$password)
	{
		$from_msv = preg_split('/[\:\/\.]/',strtok($fromaddr,'@'));
		$to_msv = preg_split('/[\:\/\.]/',strtok($toaddr,'@'));
		$cur_date = getdate();
		$data = pack("vvvvvvvvvvvvCC",
			$from_msv[2], # OrigNode
			$to_msv[2], # DestNode
			$cur_date['year'], # Year
			$cur_date['mon']-1, # Month
			$cur_date['mday'], # Day
			$cur_date['hours'], # Hour
			$cur_date['minutes'], # Minute
			$cur_date['seconds'], # Second
			0, # rate
			2, # Version
			$from_msv[1], # OrigNet
			$to_msv[1], # DestNet
			0, # PCodeLo
			0 # PRevMajor
		);
		fwrite($file,$data,26);
		fputstr($file,$password,8); # Password
		$data = pack("vvvvCCvvvvvV",
			isset($from_msv[0])?$from_msv[0]:0, # QMOrigZone
			isset($to_msv[0])?$to_msv[0]:0, # QMDestZone
			0, # AuxNet
			0x0100, # CWValidate
			0, # PCodeHi
			2, # PRevMinor
			0x0001, # CWCapWord
			isset($from_msv[0])?$from_msv[0]:0, # OrigZone
			isset($to_msv[0])?$to_msv[0]:0, # DestZone
			isset($from_msv[3])?$from_msv[3]:0, # OrigPoint
			isset($to_msv[3])?$to_msv[3]:0, # DestPoint
			0 # LongData
		);
		fwrite($file,$data,24);
	}
}

class Pkt_message
{
	var $Version;
	var $OrigNode;
	var $DestNode;
	var $OrigNet;
	var $DestNet;
	var $Attr;
	var $Cost;
	var $Date;
	var $ToUser;
	var $FromUser;
	var $Subject;
	var $MsgText;

	function Read_message($file)
	{
		$this->Version = fgetint($file,2);
		if ($this->Version != 0):
		$this->OrigNode = fgetint($file,2);
		$this->DestNode = fgetint($file,2);
		$this->OrigNet = fgetint($file,2);
		$this->DestNet = fgetint($file,2);
		$this->Attr = fgetint($file,2);
		$this->Cost = fgetint($file,2);
		$this->Date = $this->fts2unix(fgetasciiz($file));
		$this->ToUser = fgetasciiz($file);
		$this->FromUser = fgetasciiz($file);
		$this->Subject = fgetasciiz($file);
		$this->MsgText = fgetasciiz($file);
		endif;
	}

	function getAsciiDate()
	{
		return $this->unix2fts($this->Date);
	}
	
	function fts2unix($fts)
	{
		$months = array('Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec');
		$msv = preg_split('/[ \:]/',$fts);
		for($i=0;$i<12;$i++){if (strcasecmp($months[$i],$msv[1])==0){$msv[1] = ($i+1); }}
		return mktime($msv[4],$msv[5],$msv[6],$msv[1],$msv[0],$msv[2]);
	}

	function unix2fts($unix)
	{
		return date('d M y  H:i:s', $unix);
	}

	function Write_message($file)
	{
		fputint($file,2,$this->Version);
		fputint($file,2,$this->OrigNode);
		fputint($file,2,$this->DestNode);
		fputint($file,2,$this->OrigNet);
		fputint($file,2,$this->DestNet);
		fputint($file,2,$this->Attr);
		fputint($file,2,$this->Cost);
		fputasciiz($file,$this->unix2fts($this->Date));
		fputasciiz($file,$this->ToUser);
		fputasciiz($file,$this->FromUser);
		fputasciiz($file,$this->Subject);
		fputasciiz($file,$this->MsgText);
	}
}

class FidoPacket
{
	var $pkt_hat;
	var $file;
	var $filename;
	var $message;
	
	function Open($filename)
	{
		tolog('c',"Opening packet $filename");
		$this->filename = $filename;
		if ($this->file = @fopen($filename,'r+')):
			$this->pkt_hat = new Pkt_hat;
			$this->pkt_hat->Open($this->file);
			return 1;
		else:
			return 0;
		endif;
	}

	function Close()
	{
		tolog('c','Closing packet');
		fclose($this->file);
	}

	function ReadMessage()
	{
		tolog('m','Reading next message');
		$message = new Pkt_message;
		$message->Read_message($this->file);
		return $message;
	}

	function NewPacket($filename,$fromaddr,$toaddr,$password)
	{
		tolog('c',"Writing packet $filename");
		$this->filename = $filename;
		$this->file = fopen($filename,'w') or die('Could not open file "'.$filename.'"');
		$pkt_hat = new Pkt_hat;
		$pkt_hat->WriteHdr($this->file,$fromaddr,$toaddr,$password);
		$this->message = new Pkt_message;
	}

	function AppendMessage()
	{
		$this->message->Write_message($this->file);
	}

	function FinishPacket()
	{
		fwrite($this->file,chr(0).chr(0));
		$this->Close();
		chmod($this->filename,0666);
	}

	function Unclose()
	{
		$p = 0;
		do {
			$p--;
			fseek($this->file,$p,2);
			$c = fread($this->file,1);
		} while (ord($c)==0);
		fseek($this->file,$p+2,2);
	}
}
	
?>
