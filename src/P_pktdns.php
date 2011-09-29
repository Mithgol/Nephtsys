<?
/*\
  /  This is a part of PhFiTo (aka PHP Fido Tosser)
 //  Copyright (c) Alex Kocharin, 2:50/13
///
///  This program is distributed under GNU GPL v2
///  See docs/license for details
//
/    $Id: P_pktdns.php,v 1.7 2011/01/09 08:53:12 kocharin Exp $
\*/

if (!function_exists('xinclude')) die();

xinclude('F_archives.php');
xinclude('L_ftndns.php');
xinclude('F_pkt.php');
xinclude('L_netmail.php');
xinclude('P_phfito.php');

function PackMessage_pktdns($to)
{
	if ($addr = RouteMsg($to)):
		GLOBAL $CurrentMessage, $CurrentMessageParsed, $CONFIG;
		list($tz,$tn,$tf,$tp) = GetFidoAddr($to);
		$lines = explode("\r",$CurrentMessage->MsgText);
		$lines2 = array();
		if ($tp) $lines2[] = chr(1).'TOPT '.$tp;
		for($i=0,$s=sizeof($lines);$i<$s;$i++):
			if (strcmp(substr($lines[$i],0,6),chr(1).'INTL ')==0):
				$tonode = strtok(substr($lines[$i],6),' ');
				$fmnode = strtok('');
				$lines2[] = chr(1).'INTL '.$tz.':'.$tn.'/'.$tf.' '.$fmnode;
			elseif (strcmp(substr($lines[$i],0,6),chr(1).'TOPT ')==0):
			elseif (strcmp(substr($lines[$i],0,6),chr(1).'FMPT ')==0):
				$lines2[] = $lines[$i];
			else:
				$lines2[] = $lines[$i];
			endif;
		endfor;
		while(!trim($lines2[sizeof($lines2)-1])) array_pop($lines2);
		$Text = join("\r",$lines2);
		$Text.= "\r".chr(1)."Via ".$CONFIG->GetVar('address')." @".date('Ymd.His')." FidoNet DNS System\r";

		preg_match('/:([0-9]+)\/([0-9]+)/',$CurrentMessageParsed->FromAddr,$m);
		preg_match('/:([0-9]+)\/([0-9]+)/',$to,$t);
		$Omessage = new Pkt_message;
		$Omessage->Version = 2;
		$Omessage->OrigNode = $m[2];
		$Omessage->DestNode = $t[2];
		$Omessage->OrigNet = $m[1];
		$Omessage->DestNet = $t[1];
		$Omessage->Attr = ZeroNFlags($CurrentMessage->Attr);
		$Omessage->Cost = $CurrentMessage->Cost;
		$Omessage->Date = $CurrentMessage->Date;
		$Omessage->ToUser = $CurrentMessage->ToUser;
		$Omessage->FromUser = $CurrentMessage->FromUser;
		$Omessage->Subject = $CurrentMessage->Subject;
		$Omessage->MsgText = $Text;
		PackMessageTo($Omessage,$addr);
	else:
		tolog('T',"Routing message to $to not defined");
	endif;
}

function SendReceipt_pktdns($to,$origto,$addrs)
{
	GLOBAL $CONFIG;
	GLOBAL $CurrentMessage;
	GLOBAL $CurrentMessageParsed;
	tolog('t',"Sending receipt to $to");
	InitVsysTrack($to,$origto);
	$msgbody = '';
	$s = GetStrFromFile_param('FTNDNSGET');
	$msgbody.= ($s?$s:"Your message to $origto was arrived to FidoNet DNS System.")."\r";
	if (!$addrs) {
		$s = GetStrFromFile_param('FTNDNSNONAME');
		$msgbody.= ($s?$s:"Recipient not found on our database, sorry. :-(").".\r";
	} else {
		$s = GetStrFromFile_param('FTNDNSPACK');
		$msgbody.= ($s?$s:"It was sent to ").join(', ',$addrs).".\r";
	}
	$s = GetStrFromFile_param('QUOTE');
	$msgps = ($s?$s:"Here is your message:")."\r".FormHeader();
	return SendReply($CurrentMessageParsed->msgid,$to,$msgbody,$msgps,MSG_PVT,$CurrentMessage->FromUser,'Receipt from FTNDNS');
}

function Process_message_pktdns()
{
	GLOBAL $CONFIG, $CurrentMessage, $FtnDns, $CurrentMessageParsed;
	$is_netmail = strcasecmp(substr($CurrentMessage->MsgText,0,5),'area:')!=0;
	if ($is_netmail):
		ParseMessage(1);
		if (!in_array($CurrentMessageParsed->FromAddr,$CONFIG->GetArray('address'))) {
			$addrs = $FtnDns->GetAddrByName($CurrentMessage->ToUser);
			SendReceipt_pktdns($CurrentMessageParsed->FromAddr,$CurrentMessageParsed->ToAddr,$addrs);
			if ($addrs) {
				foreach($addrs as $a) {
					PackMessage_pktdns($a);
				}
			}
		}
	endif;
}

function Toss_Pkt_pktdns($file)
{
	GLOBAL $CurrentMessage;
	tolog('T',"Processing $file...");
	$packet = new FidoPacket;
	if ($packet->Open($file)):
		$CurrentMessage = $packet->ReadMessage();
		while ($CurrentMessage->Version!=0):
			Process_message_pktdns();
			$CurrentMessage = $packet->ReadMessage();
		endwhile;
		$packet->Close();
		tolog('t','Tossing finished');
		unlink($file);
	else:
		tolog('T','Error during opening packet');
		rename($file,$file.'.err');
	endif;
}

function Run_pktdns()
{
	GLOBAL $CONFIG, $LocalPkt, $FtnDns;
	$FtnDns = new C_FtnDns();
	$FtnDns->Load();
	$inbound = $CONFIG->GetVar('inbound');
	$tempin = $CONFIG->GetVar('tempinbound');
	ExtractBundles($inbound,$tempin);
	if ($dir = opendir($inbound)) {
		while ($fname = readdir($dir))
		{ 
			if (preg_match('/\.pkt$/i',$fname)):
				Toss_Pkt_pktdns("$inbound/$fname"); 
			endif;
		}
		closedir($dir);
	}
	if ($dir = opendir($tempin)) {
		while ($fname = readdir($dir))
		{ 
			if (preg_match('/\.pkt$/i',$fname)):
				Toss_Pkt_pktdns("$tempin/$fname"); 
			endif;
		}
		closedir($dir);
	}
	$FtnDns->Unload();
}

?>
