<?
/*\
  /  This is a part of PhFiTo (aka PHP Fido Tosser)
 //  Copyright (c) Alex Kocharin, 2:50/13
///
///  This program is distributed under GNU GPL v2
///  See docs/license for details
//
/    $Id: C_phpbb2.php,v 1.10 2011/01/09 03:39:17 kocharin Exp $
\*/

if (!function_exists('xinclude')) die();

xinclude('L_fidonet.php');
xinclude('L_mysql.php');

class C_phpbb2base
{
	var $base;
	var $db;
	var $prefix;
	var $echo;
	var $echoid;
	var $extended_syntax;
	var $allscan_present;

	function C_phpbb2base()
	{
		$this->extended_syntax = true;
		$this->allscan_present = true;
	}

	function AllScan()
	{
		GLOBAL $CONFIG;
		$q = $this->base->query("SELECT post_id,poster_ip FROM {$this->prefix}posts WHERE forum_id = {$this->echoid} AND CAST(poster_ip as UNSIGNED) != 8000");
		$result = array();
		while($arr = $this->base->fetchrow($q)) {
			if ($this->_phpbb_to_flag($arr[1]) == MSG_LOC) {
				$result[] = $arr[0];
			}
		}
		$this->base->free($q);
		return $result;
	}

	function OpenBase($path)
	{
		$this->_decodeaddr($path);
		$this->_connectdb();
		if (!$this->base->id) return 0;
		if (!$this->base->opendb($this->db)) {
			$this->CloseBase();
			return 0;
		}
		$res = $this->base->query("SELECT forum_id FROM {$this->prefix}forums WHERE forum_name LIKE '{$this->echo}'");
		if (!$res):
			$this->prefix.= '_';
			$res = $this->base->query("SELECT forum_id FROM {$this->prefix}forums WHERE forum_name LIKE '{$this->echo}'");
		endif;
		if ($res):
			$r = $this->base->numrows($res);
			if ($r) { 
				$this->echoid = $this->base->result($res,0);
				$this->base->free($res);
				return 1;
			} else {
				$this->CloseBase();
				return 0;
			}
		else:
			$this->CloseBase();
			return 0;
		endif;
	}

	function CreateBase($addr)
	{
		$this->_decodeaddr($addr);
		$this->_connectdb();
		$this->base->opendb($this->db);

		$res = $this->base->query("SELECT MAX(forum_order) AS max_order FROM {$this->prefix}forums WHERE cat_id = 1");
		if (!$res):
			$this->prefix.='_';
			$res = $this->base->query("SELECT MAX(forum_order) AS max_order FROM {$this->prefix}forums WHERE cat_id = 1");
		endif;
		if (!$res):
	   		$this->_disconnectdb();
			return 0;
		endif;
		$f_order = $this->base->result($res,0) + 1;
		$this->base->free($res);

		$res = $this->base->query("SELECT MAX(forum_id) AS max_id FROM {$this->prefix}forums");
		$f_id = $this->base->result($res,0) + 1;
		$this->base->free($res);

		if ($res = $this->base->query("INSERT INTO {$this->prefix}forums (forum_id, forum_name, cat_id, forum_desc, forum_order, forum_status, prune_enable, auth_view, auth_read, auth_post, auth_reply, auth_edit, auth_delete, auth_sticky, auth_announce, auth_vote, auth_pollcreate)
		VALUES ($f_id, '".$this->base->escape($this->echo)."', 1, '', $f_order, 0, 0, 0, 0, 0, 0, 1, 1, 1, 3, 1, 1)")):
			$r = 1;
		else:
			$r = 0;
		endif;
		$this->_disconnectdb();
		return $r;
	}
	
	function _connectdb()
	{
		GLOBAL $CONFIG;
		if (!($host = $CONFIG->GetVar('mysqlhost'))) $host = 'localhost';
		$user = $CONFIG->GetVar('mysqluser');
		$pass = $CONFIG->GetVar('mysqlpass');
		$this->base = new C_mysql;
		$this->base->open($host,$user,$pass);
	}

	function _user_getid($username)
	{
		GLOBAL $CONFIG;
		$table = $this->prefix."users";
		if ($a = $CONFIG->GetVar('smallnuke_override')) $table = $a;
		$res = $this->base->query("SELECT user_id FROM $table WHERE username LIKE '$username'");
		if ($res):
			if ($this->base->numrows($res)):
				$r = $this->base->result($res,0);
			else:
				$r = -1;
			endif;
			$this->base->free($res);
			return $r;
		else:
			return -1;
		endif;
	}

	function _create_fidouser($username)
	{
		GLOBAL $CONFIG;
		$table = $this->prefix."users";
		if ($a = $CONFIG->GetVar('smallnuke_override')) $table = $a;
		$res = $this->base->query("SELECT MAX(user_id) AS max_id FROM $table");
		$u_id = $this->base->result($res,0) + 1;
		$this->base->free($res);
	
		$res = $this->base->query("SELECT MAX(group_id) AS max_id FROM {$this->prefix}groups");
		$g_id = $this->base->result($res,0) + 1;
		$this->base->free($res);
	
		$this->base->query("BEGIN");
		$this->base->query("INSERT INTO $table (user_id, username, user_regdate, user_password, user_email, user_icq, user_website, user_occ, user_from, user_interests, user_sig, user_sig_bbcode_uid, user_avatar, user_viewemail, user_aim, user_yim, user_msnm, user_attachsig, user_allowsmile, user_allowhtml, user_allowbbcode, user_allow_viewonline, user_notify, user_notify_pm, user_popup_pm, user_timezone, user_dateformat, user_lang, user_style, user_level, user_allow_pm, user_active, user_actkey)
		VALUES ($u_id, '$username', ".time().", '".md5(rand(10000,20000000))."', '', '', '', '', '', '', '', '', '', 0, '', '', '', 0, 0, 0, 0, 0, 0, 0, 0, 0, 'D M d, Y g:i a', 'english', 1, 0, 1, 1, '')");
		$this->base->query("INSERT INTO {$this->prefix}groups (group_id, group_name, group_description, group_single_user, group_moderator)
		VALUES ($g_id, '', 'Personal User', 1, 0)");
		$this->base->query("INSERT INTO {$this->prefix}user_group (user_id, group_id, user_pending) VALUES ($u_id, $g_id, 0)");
		$this->base->query("COMMIT");

		return $u_id;
	}
	
	function _disconnectdb()
	{
		$this->base->close();
	}
	
	function _decodeaddr($addr)
	{
		if (strcmp(substr($addr,0,1),'/')==0) $addr = substr($addr,1);
		$this->db = strtok($addr,'/');
		$this->prefix = strtok('/');
		$this->echo = strtok('/');
	}
	
	function CloseBase()
	{
		$this->_disconnectdb();
	}

	function DeleteMsg($num, $ext)
	{
		return false;
	}

	function PurgeBase()
	{
		return false;
	}

	function DeleteBase($path)
	{
		return false;
	}

	function _convert_message($message)
	{
		$message = str_replace("\r","\r".chr(0),$message);
		$line = strtok($message,"\r");
		$msgid = '';
		
		while($line):
			if (strcmp(substr($line,0,1),chr(0))==0) $line = substr($line,1);
			if (strcmp(substr($line,0,1),chr(1))==0):
				if (!$msgid && (strcmp(substr($line,1,6),'MSGID:')==0)):
					$msgid = trim(substr($line,7));
				endif;
			else:
				break;
			endif;
			$line = strtok("\r");
		endwhile;
		$bbcode = rand(1000000000,9999999999);
		$line = chr(0).$line;
		$sepmsg = '';
		$resmsg = '';
		while($line):
			if (strcmp(substr($line,0,1),chr(0))==0) $line = substr($line,1);
			if (strcasecmp(substr($line,0,7),chr(1).'PATH: ')==0):
				break;
			elseif ((strcmp(substr($line,0,3),'---')==0) ||
				(strcmp(substr($line,0,11),' * Origin: ')==0) ||
				(strcmp(substr($line,0,9),'SEEN-BY: ')==0)):
				$sepmsg.= $line."\r";
			else:
				$resmsg.= $sepmsg.$line."\r";
				$sepmsg = '';
			endif;
			$line = strtok("\r");
		endwhile;
		$len = strlen($resmsg);
		if (strcmp(substr($resmsg,$len-1,1),"\r")==0)
			$resmsg = substr($resmsg,0,$len-1);
		$resmsg = $this->_add_quote($resmsg,$bbcode);
		$resmsg = str_replace("\r","\r\n",$resmsg);
//		$resmsg = htmlspecialchars($resmsg);
		$result = '';
		while ($resmsg):
			$n = strpos($resmsg,"[quote:$bbcode");
			$m = strpos($resmsg,"[/quote:$bbcode]");
			if ($n===false) $n = 0x7ffffffe;
			if ($m===false) $m = 0x7ffffffe;
			if ($m == $n) break;
			$p = min($n,$m);
			$z = strpos($resmsg,']');
			if ($z === false) break;
			$result.= htmlspecialchars(substr($resmsg,0,$p)).substr($resmsg,$p,$z-$p+1);
			$resmsg = substr($resmsg,$z+1);
		endwhile;
		$result.= htmlspecialchars($resmsg);
		return array($result,$bbcode);
	}

	function _add_quote($text,$bbcode)
	{
		$text = explode("\r",$text);
		$quotes = array();
		$quotes_pos = array();
		$s = sizeof($text);
		$quote_cur = '';
		$quote_lvl = 0;
		for ($counter=0; $counter<$s; $counter++):
			$line = $text[$counter];
			if (trim($line)):
				if (preg_match('/^([ A-Za-z]*)(>+)(.*)$/',$line,$match)):
					$lvl = strlen($match[2]);
					$newcur = trim($match[1]);
					if ((strcmp($newcur,$quote_cur)!=0) || ($quote_lvl != $lvl)):
						if ($quote_lvl == $lvl):
							$quotes[$counter][] = chr(0);
							$quotes[$counter][] = $match[1];
							$quotes_pos[$lvl] = array($counter,sizeof($quotes[$counter])-1);
						elseif ($quote_lvl > $lvl):
							$ql = $quote_lvl;
							for($i=$lvl;$i<$ql;$i++):
								$quote_lvl--;
								$quotes[$counter][] = chr(0);
							endfor;
							$ar = $quotes_pos[$lvl];
							if (strcmp($quotes[$ar[0]][$ar[1]],chr(1))==0):
								$quotes[$ar[0]][$ar[1]] = $newcur;
							else:
								$quotes[$counter][] = chr(0);
								$quotes[$counter][] = $newcur?$newcur:chr(1);
								$quotes_pos[$lvl] = array($counter,sizeof($quotes[$counter])-1);
							endif;
						else:
							for($i=$quote_lvl+1;$i<$lvl;$i++):
								$quote_lvl++;
								$quotes[$counter][] = chr(1);
								$quotes_pos[$i] = array($counter,sizeof($quotes[$counter])-1);
							endfor;
							$quotes[$counter][] = $match[1];
							$quotes_pos[$lvl] = array($counter,sizeof($quotes[$counter])-1);
							$quote_lvl++;
						endif;
						$quote_cur = $newcur;
				endif;
					$line = trim($match[3]);
				else:
					if ($quote_lvl):
						for ($i = $quote_lvl; $i; $i--) $quotes[$counter][]=chr(0);
						$quote_lvl = 0;
					endif;
				endif;
			endif;
			$text[$counter] = $line;
		endfor;
		for ($i = $quote_lvl; $i; $i--) $quotes[$s][] = chr(0);

		$result = '';
		for ($i=0; $i<=$s; $i++):
			if (isset($quotes[$i])):
			foreach($quotes[$i] as $q):
				$l = strlen($q);
				while ($l && ($q{0} === ' ')) { $q = substr($q,1); $l--; }
				while ($l && ($q{$l-1} === ' ')) { $q = substr($q,0,$l-1); $l--; }
				switch($q):
					case(chr(0)):
						$result.= "[/quote:$bbcode]";
						break;
					case(chr(1)):
						$result.= "[quote:$bbcode]";
						break;
					default:
						$result.= "[quote:$bbcode".($q?"=\"$q\"":'')."]";
				endswitch;
			endforeach;
			endif;
			if ($i<$s) $result.= $text[$i]."\r";
		endfor;
		return $result;
	}
	
	function WriteMessage($header,$message)
	{
		if (strlen($header->From)>25):	
			$username = substr($header->From,0,25);
		else:
			$username = $header->From;
		endif;
		$username = $this->base->escape($username);
		$ip = $this->_flag_to_phpbb($header->Attrs);

		list($message,$bbcode) = $this->_convert_message($message);
		$message = convert_cyr_string($message,"d","w");
		$username = convert_cyr_string($username,"d","w");

		$uid = $this->_user_getid($username);
		if ($uid == -1)
			$uid = $this->_create_fidouser($username);
		$subj = $header->Subj;
		$orig_subj = $header->Subj;
		$subj = preg_replace('/Re(\^[0-9]+)?(\[\^?[0-9]+\])?\: ?/i','',$subj);
		$subj = trim($subj);
		$subj = convert_cyr_string($subj,"d","w");
		if (strcmp(trim($subj),'')==0) $subj = '<empty>';
		$subj = $this->base->escape(htmlspecialchars($subj));
		$orig_subj = $this->base->escape($orig_subj);
		$orig_subj = htmlspecialchars(convert_cyr_string($orig_subj,"d","w"));
		$Date = $header->ADate?$header->ADate:time();
		
		$topic = $this->_get_topic_of($subj);
		$this->_post_message($topic,$uid,$subj,$orig_subj,$Date,$message,$bbcode,$ip);
	}

	function _get_topic_of($subj)
	{
		$res = $this->base->query("SELECT topic_id FROM {$this->prefix}topics
WHERE forum_id = {$this->echoid} AND topic_title LIKE '$subj' LIMIT 1");
		if ($this->base->numrows($res)):
			$t_id = $this->base->result($res,0);
		else:
			$t_id = -1;
		endif;
		$this->base->free($res);
		return $t_id;
	}

	function SetAttr($msg,$attr,$ext)
	{
		$attr = $this->_flag_to_phpbb($attr);
		if ($ext) {
			$this->base->query("UPDATE {$this->prefix}posts SET poster_ip = $attr WHERE post_id = $msg");
		} else {
			$res = $this->base->query("SELECT post_id FROM {$this->prefix}posts WHERE forum_id = {$this->echoid} ORDER BY post_id LIMIT 1".($msg==1?'':' OFFSET '.($msg-1)));
			if ($res):
				if ($this->base->numrows($res)):
					$post_id = $this->base->result($res,0);
				else:
					$post_id = false;
				endif;
				$this->base->free($res);
			else:
				$post_id = false;
			endif;
			if ($post_id !== false):
				$this->base->query("UPDATE {$this->prefix}posts SET poster_ip = $attr WHERE post_id = $post_id");
			endif;
		}
	}

	function _flag_to_phpbb($rq)
	{
		$res = 0;
		if ($rq & MSG_LOC) $res |= 0x01;
		if ($rq & MSG_SNT) $res |= 0x02;
		$res |= 0x80;
		return '0000'.dechex($res).'00';
	}

	function _phpbb_to_flag($attr)
	{
		if (preg_match('/^0*([0-9a-f]{2})00$/i',$attr,$m)):
			$attrs = hexdec($m[1]);
			if ($attrs & 0x80):
				$res = 0;
				if ($attrs & 0x01) $res |= MSG_LOC;
		   		if ($attrs & 0x02) $res |= MSG_SNT;
				return $res;
			else:
				return MSG_LOC;
			endif;
		else:
			return MSG_LOC;
		endif;
	}
	
	function _post_message($t_id,$uid,$subj,$orig_subj,$Date,$message,$bbcode,$ip)
	{
		GLOBAL $CONFIG;
		$table = $this->prefix."users";
		if ($a = $CONFIG->GetVar('smallnuke_override')) $table = $a;
		$new = $t_id == -1;
		$message = $this->base->escape($message);
		$this->base->query('BEGIN');
		if ($new):
			$this->base->query("INSERT INTO {$this->prefix}topics (topic_title, topic_poster, topic_time, forum_id, topic_status, topic_type, topic_vote)
			VALUES ('$subj', $uid, {$Date}, {$this->echoid}, 0, 0, 0)");
			if ($res = $this->base->query("SELECT topic_id FROM {$this->prefix}topics WHERE topic_title = '$subj' AND topic_time = $Date AND forum_id = {$this->echoid} ORDER BY topic_id DESC")):
				if ($this->base->numrows($res)):
					$t_id = $this->base->result($res,0);
				endif;
				$this->base->free($res);
			endif;
		endif;
		$this->base->query("INSERT INTO {$this->prefix}posts (topic_id, forum_id, poster_id, post_username, post_time, poster_ip, enable_bbcode, enable_html, enable_smilies, enable_sig)
		VALUES ({$t_id}, {$this->echoid}, $uid, '', {$Date}, '$ip', 1, 0, 1, 0)");
		if ($res = $this->base->query("SELECT post_id FROM {$this->prefix}posts WHERE topic_id = $t_id AND post_time = $Date ORDER BY post_id DESC")):
			if ($this->base->numrows($res)):
				$p_id = $this->base->result($res,0);
			endif;
			$this->base->free($res);
		endif;
		$this->base->query("INSERT INTO {$this->prefix}posts_text (post_id, post_subject, bbcode_uid, post_text)
		VALUES ($p_id, '".$this->base->escape($orig_subj)."', '$bbcode', '$message')");
		$this->base->query("UPDATE {$this->prefix}topics SET topic_last_post_id = $p_id".($new?"":", topic_replies = topic_replies + 1")." WHERE topic_id = $t_id");
		if ($new) $this->base->query("UPDATE {$this->prefix}topics SET topic_first_post_id = $p_id WHERE topic_id = $t_id");
		$this->base->query("UPDATE {$this->prefix}forums SET forum_last_post_id = $p_id, forum_posts = forum_posts + 1".($new?", forum_topics = forum_topics + 1":"")." WHERE forum_id = {$this->echoid}");
		$this->base->query("UPDATE $table SET user_posts = user_posts + 1 WHERE user_id = $uid");
		$this->base->query("COMMIT");
	}
	
	function GetNumMsgs()
	{
		$res = $this->base->query("SELECT forum_posts FROM {$this->prefix}forums WHERE forum_id = {$this->echoid}");
		$r = $this->base->result($res,0);
		$this->base->free($res);
		return $r;
	}

	function ReadMsgHeader($msg,$ext)
	{
		GLOBAL $CONFIG;
		if ($ext) {
			$q = $this->base->query("SELECT post_id,poster_id,post_time,poster_ip,post_username,topic_id FROM {$this->prefix}posts WHERE post_id = $msg");
		} else {
			$q = $this->base->query("SELECT post_id,poster_id,post_time,poster_ip,post_username,topic_id FROM {$this->prefix}posts WHERE forum_id = {$this->echoid} ORDER BY post_id LIMIT 1".($msg==1?'':' OFFSET '.($msg-1)));
		}
		$arr = $this->base->fetchrow($q);
		$this->base->free($q);
		list($post_id,$poster_id,$time,$attrs,$username,$topicid) = $arr;
	
		$q = $this->base->query("SELECT bbcode_uid,post_subject,post_text FROM {$this->prefix}posts_text WHERE post_id = $post_id");
		$arr = $this->base->fetchrow($q);
		$this->base->free($q);
		list($bbcode,$subj,$text) = $arr;
		if ($subj == '') {
			if ($res = $this->base->query("SELECT topic_title FROM {$this->prefix}topics WHERE topic_id = $topicid")):
				if ($this->base->numrows($res)):
					$subj = $this->base->result($res,0);
				endif;
				$this->base->free($res);
			endif;
		}

		if (preg_match("/\[quote:$bbcode=\"?([^\]\"]*)\"?]/i",$text,$res)):
			$to = $res[1];
		else:
			$to = 'All';
		endif;

		if ($poster_id == -1):
			$from = $username;
		else:
			GLOBAL $CONFIG;
			$table = $this->prefix."users";
			if ($a = $CONFIG->GetVar('smallnuke_override')) $table = $a;
			if ($res = $this->base->query("SELECT username FROM $table WHERE user_id = $poster_id")):
				if ($this->base->numrows($res)):
					$from = $this->base->result($res,0);
				else:
					$from = $username;
				endif;
				$this->base->free($res);
			else:
				$from = $username;
			endif;
		endif;
		
		$result = new C_msgheader;
		$result->WDate = $time;
		$result->ADate = $time;
		$result->FromAddr = $CONFIG->GetVar('address');
		$result->ToAddr = $CONFIG->GetVar('address');
		$result->From = convert_cyr_string($from,'w','d');
		$result->To = convert_cyr_string($to,'w','d');
		$result->Subj = convert_cyr_string($subj,'w','d');
		$result->Attrs = $this->_phpbb_to_flag($attrs);
		return $result;
	}

	function ReadMsgBody($msg,$ext)
	{
		GLOBAL $CONFIG;
		if ($ext) {
			$post_id = $msg;
		} else {
			if ($res = $this->base->query("SELECT post_id FROM {$this->prefix}posts WHERE forum_id = {$this->echoid} ORDER BY post_id LIMIT 1".($msg==1?'':' OFFSET '.($msg-1)))):
				if ($this->base->numrows($res)):
					$post_id = $this->base->result($res,0);
				else:
					$post_id = false;
				endif;
				$this->base->free($res);
			else:
				$post_id = false;
			endif;
		}
		if ($post_id!==false):
			if ($res = $this->base->query("SELECT bbcode_uid,post_text FROM {$this->prefix}posts_text WHERE post_id = $post_id")):
				if ($this->base->numrows($res)):
	 				$bbcode = $this->base->result($res,0,'bbcode_uid');
					$result = $this->base->result($res,0,'post_text');
				else:
					$result = false;
				endif;
				$this->base->free($res);
			else:
				$result = false;
			endif;
		else:
			$result = false;
		endif;
		if ($result !== false):
			$pid = $CONFIG->GetVar('phpbb_version');
			if (!$pid) $pid = 'phpBB';
			$origin = $CONFIG->GetVar('phpbb_origin');
			if (!$origin) $origin = 'Fidonet rulezz forever!!!';
			$tear = $CONFIG->GetVar('phpbb_tearline');
			if (!$tear) $tear = "Powered by $pid";
			$addr = $CONFIG->GetVar('address');
			$result = str_replace("\r\n","\n",$result);
			$result = str_replace("\r","",$result);
			$result = $this->_phpbb_to_fidomessage($bbcode,0,$result."\n");
			$result = convert_cyr_string($result,'w','d');
			$result = chr(1)."MSGID: ".$addr.' '.getmsgid()."\n".chr(1)."PID: $pid\n".$result."\n--- $tear\n * Origin: $origin ($addr)";
			$result = str_replace("\n","\r",$result);
		endif;
		return $result;
	}

	function _phpbb_to_fidomessage($bbcode,$lvl,$text_init = '')
	{
		STATIC $text;
		STATIC $result;
		$init = '';
		if ($lvl):
			$init = ' ';
			$stttr = "[quote:$bbcode";
			if (strpos($text,$stttr)===0):
				$l = strlen($stttr);
				$pz = strpos($text,']');
				if ($pz!==false):
					$n = $pz-$l+1;
					if ($n > 1):
						$name = substr($text,$l+1,$n-2);
						$init.= get_initials(str_replace("\"",'',$name));
					endif;
					$text = substr($text,$pz+1);
				endif;
			endif;
			for ($i=0;$i<$lvl;$i++) $init.='>';
			$init.=' ';
		else:
			$result = '';
			$text = $text_init;
		endif;
		while (true):
			if (($p = strpos($text,"\n"))!==false):
				$str = substr($text,0,$p);
				$n = strpos($str,"[quote:$bbcode");
				$m = strpos($str,"[/quote:$bbcode]");
				if ($n===false) $n = 0x7ffffffe;
				if ($m===false) $m = 0x7ffffffe;
				if ($n < $m):
					$add = substr($str,0,$n);
					if (trim($add)):
	//					if (strcmp($result{strlen($result)-1},"\n")!=0) $result.="\n";
						$result.= $init.$add;
					elseif ($lvl==0):
	//					if (strcmp($result{strlen($result)-1},"\n")!=0) $result.="\n";
						$result.= $add;
					endif;
					if (strcmp($str{strlen($str)-1},"\n")!=0) $result.="\n";
					$text = substr($str,$n).substr($text,$p);
					$this->_phpbb_to_fidomessage($bbcode,$lvl+1);
				elseif ($n > $m):
					$add = substr($str,0,$m);
					if (trim($add)):
	//					if (strcmp($result{strlen($result)-1},"\n")!=0) $result.="\n";
						$result.= $init.$add;
					elseif ($lvl==0):
	//					if (strcmp($result{strlen($result)-1},"\n")!=0) $result.="\n";
						$result.= $add;
					endif;

					$str = substr($str,$m+strlen("[/quote:$bbcode]"));
					if (strcmp($str{strlen($str)-1},"\n")!=0) $result.="\n";
					$text = $str.substr($text,$p+1);
					break;
				else:
					$add = $str;
					if (trim($add)):
	//					if (strcmp($result{strlen($result)-1},"\n")!=0) $result.="\n";
						$result.= $init.$add."\n";
					elseif ($lvl==0):
	//					if (strcmp($result{strlen($result)-1},"\n")!=0) $result.="\n";
						$result.= $add."\n";
					endif;
					$text = substr($text,$p+1);
				endif;
			else:
				$result.= $text;
				$text = '';
				break;
			endif;
		endwhile;
		return $result;
	}
}

?>
