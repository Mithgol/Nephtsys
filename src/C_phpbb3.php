<?php
/*\
  /  This is a part of PhFiTo (aka PHP Fido Tosser)
 //  Copyright (c) Alex Kocharin, 2:50/13
///
///  This program is distributed under GNU GPL v2
///  See docs/license for details
//
/    $Id: C_phpbb3.php,v 1.10 2011/01/09 03:39:17 kocharin Exp $
\*/

if (!function_exists('xinclude')) die();

xinclude('L_fidonet.php');
xinclude('L_mysql.php');

function utfconvert($str,$type='u')
{
   static $conv='';
   if (!is_array ( $conv ))
   {
	   $conv=array();
	   for($x=192;$x<=239;$x++)
		   $conv['u'][chr($x)]=chr(208).chr($x-48);
	   for($x=240;$x<=255;$x++)
		   $conv['u'][chr($x)]=chr(209).chr($x-112);
	   $conv['u'][chr(168)]=chr(208).chr(129);
	   $conv['u'][chr(184)]=chr(209).chr(209);
	   $conv['w']=array_flip($conv['u']);
   }
   if($type=='w' || $type=='u')
       return strtr($str,$conv[$type]);
   else
       return $str;
}

class C_phpbb3base
{
	var $base;
	var $db;
	var $prefix;
	var $echo;
	var $echoid;
	var $extended_syntax;
	var $allscan_present;
	var $Descr;
	var $Categ;

	function C_phpbb3base()
	{
		$this->extended_syntax = true;
		$this->allscan_present = true;
	}
	
	function _create_fidouser($username)
	{
		$prefix = $this->prefix;
		$res = $this->base->query("SELECT group_id FROM {$prefix}groups WHERE group_name = 'REGISTERED'");
		$gid = $this->base->result($res,0);
		$this->base->free($res);
	
		$res = $this->base->query("SELECT config_value from {$prefix}config where config_name = 'default_lang'");
		$lang = $this->base->result($res,0);
		$this->base->free($res);
		$time = time();
	
		$this->base->query("INSERT INTO {$prefix}users  (username, username_clean, user_password, user_pass_convert, user_email, user_email_hash, group_id, user_type, user_permissions, user_timezone, user_dateformat, user_lang, user_style, user_allow_pm, user_actkey, user_ip, user_regdate, user_passchg, user_inactive_reason, user_inactive_time, user_lastmark, user_lastvisit, user_lastpost_time, user_lastpage, user_posts, user_dst, user_colour, user_occ, user_interests, user_avatar, user_avatar_type, user_avatar_width, user_avatar_height, user_new_privmsg, user_unread_privmsg, user_last_privmsg, user_message_rules, user_full_folder, user_emailtime, user_notify, user_notify_pm, user_notify_type, user_allow_viewonline, user_allow_viewemail, user_allow_massemail, user_sig, user_sig_bbcode_uid, user_sig_bbcode_bitfield) 
		VALUES ('".$username."', '".strtolower($username)."', '".md5(rand(10000,20000000))."', 0, '', '0', $gid, 0, '', 0, 'D M d, Y g:i a', '$lang', '1', 1, '', '0.0.0.0', $time, $time, 0, 0, $time, 0, 0, '', 0, '0', '', '', '', '', 0, 0, 0, 0, 0, 0, 0, -3, 0, 0, 1, 0, 1, 1, 1, '', '', '')");
		$res = $this->base->query("SELECT user_id from {$prefix}users WHERE username = '$username'");
		$uid = $this->base->result($res,0);
		$this->base->free($res);
		$this->base->query("INSERT INTO {$prefix}user_group  (user_id, group_id, user_pending) VALUES ($uid, $gid, 0)");
		
		if ($res = $this->base->query("SELECT group_colour, group_rank FROM {$prefix}groups WHERE group_id = $gid")):
			if ($this->base->numrows($res)):
				$gcolour = $this->base->result($res,0,'group_colour');
				$rank = $this->base->result($res,0,'group_rank');

				$this->base->query("UPDATE {$prefix}users SET group_id = $gid, user_colour = '$gcolour', user_rank = $rank WHERE user_id = $uid");
			endif;
			$this->base->free($res);
		endif;

		$this->base->query("UPDATE {$prefix}config SET config_value = '$uid' WHERE config_name = 'newest_user_id'");
		$this->base->query("UPDATE {$prefix}config SET config_value = '$username' WHERE config_name = 'newest_username'");
		$this->base->query("UPDATE {$prefix}config SET config_value = config_value + 1 WHERE config_name = 'num_users'");

		return $uid;
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

	function _get_topic_of($subj)
	{
		$res = $this->base->query("SELECT topic_id FROM {$this->prefix}topics
WHERE forum_id = {$this->echoid} AND topic_title = '$subj' LIMIT 1");
		if ($this->base->numrows($res)):
			$t_id = $this->base->result($res,0);
		else:
			$t_id = -1;
		endif;
		$this->base->free($res);
		return $t_id;
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
		$bbcode = dechex(rand(65536,1048575));
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
		$resmsg = htmlspecialchars($resmsg);
/*		$result = '';
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
		$result.= htmlspecialchars($resmsg);*/
		return array($resmsg,$bbcode);
	}

	function CreateBase($addr)
	{
		$this->_decodeaddr($addr);
		$this->_connectdb();
		$this->base->opendb($this->db);

		if ($this->Categ === false) {
			if ($res = $this->base->query("SELECT forum_id FROM {$prefix}forums ORDER BY forum_id LIMIT 1",$id)) {
				if ($this->base->numrows($res)) {
					$categ = $this->base->result($res,0);
					$this->base->free($res);
				}
			} else {
				$categ = 1;
			}
		} else {
			$categ = $this->Categ;
		}
		$descr = utfconvert(convert_cyr_string($message,"k","w"),'u');

		if ($res = $this->base->query("INSERT INTO {$this->prefix}forums  (parent_id, forum_type, forum_status, forum_parents, forum_name, forum_link, forum_desc, forum_desc_uid, forum_desc_options, forum_desc_bitfield, forum_rules, forum_rules_uid, forum_rules_options, forum_rules_bitfield, forum_rules_link, forum_image, forum_style, display_on_index, forum_topics_per_page, enable_indexing, enable_icons, enable_prune, prune_days, prune_viewed, prune_freq, forum_password, forum_flags, left_id, right_id) VALUES 
		($categ, 1, 0, '', '".($name_esc = $this->base->escape($this->echo))."', '', '".$this->base->escape($desc)."', '', 7, '', '', '', 7, '', '', '', 0, 0, 0, 1, 0, 0, 30, 7, 1, '', 32, '0', 0)")):
			$res = $this->base->query("SELECT forum_id from {$this->prefix}forums where forum_name = '$name_esc'");
			$eid = $this->base->result($res,0);
			$this->base->free($res);
			$this->base->query("INSERT INTO {$this->prefix}acl_groups  (group_id, forum_id, auth_option_id, auth_setting) VALUES (5, $eid, 11, 1), (5, $eid, 14, 1), (5, $eid, 17, 1), (5, $eid, 20, 1), (5, $eid, 21, 1), (5, $eid, 4, 1), (5, $eid, 7, 1), (5, $eid, 24, 1), (5, $eid, 25, 1), (5, $eid, 9, 1), (5, $eid, 19, 1), (5, $eid, 22, 1), (5, $eid, 27, 1), (5, $eid, 15, 1), (5, $eid, 18, 1), (5, $eid, 23, 1), (5, $eid, 29, 1), (5, $eid, 1, 1), (6, $eid, 11, 1), (6, $eid, 14, 1), (6, $eid, 17, 1), (6, $eid, 20, 1), (6, $eid, 21, 1), (6, $eid, 4, 1), (6, $eid, 7, 1), (6, $eid, 24, 1), (6, $eid, 25, 1), (6, $eid, 9, 1), (6, $eid, 19, 1), (6, $eid, 22, 1), (6, $eid, 27, 1), (6, $eid, 15, 1), (6, $eid, 18, 1), (6, $eid, 23, 1), (6, $eid, 29, 1), (6, $eid, 1, 1), (4, $eid, 11, 1), (4, $eid, 14, 1), (4, $eid, 17, 1), (4, $eid, 20, 1), (4, $eid, 21, 1), (4, $eid, 4, 1), (4, $eid, 7, 1), (4, $eid, 24, 1), (4, $eid, 25, 1), (4, $eid, 9, 1), (4, $eid, 19, 1), (4, $eid, 22, 1), (4, $eid, 27, 1), (4, $eid, 15, 1),	(4, $eid, 18, 1), (4, $eid, 23, 1), (4, $eid, 29, 1), (4, $eid, 1, 1), (2, $eid, 11, 1), (2, $eid, 14, 1), (2, $eid, 17, 1), (2, $eid, 20, 1), (2, $eid, 21, 1), (2, $eid, 4, 1), (2, $eid, 7, 1), (2, $eid, 24, 1), (2, $eid, 25, 1), (2, $eid, 9, 1), (2, $eid, 19, 1), (2, $eid, 22, 1), (2, $eid, 27, 1), (2, $eid, 15, 1), (2, $eid, 18, 1), (2, $eid, 23, 1), (2, $eid, 29, 1), (2, $eid, 1, 1), (1, $eid, 14, 1), (1, $eid, 20, 1), (1, $eid, 7, 1), (1, $eid, 27, 1), (1, $eid, 23, 1), (1, $eid, 1, 1)");
			$this->base->query("UPDATE 12_users SET user_permissions = '', user_perm_from = 0");
			$r = 1;
		else:
			$r = 0;
		endif;
		$this->_disconnectdb();
		return $r;
	}

	function _user_getid($username)
	{
		GLOBAL $CONFIG;
		$prefix = $this->prefix;
		$res = $this->base->query("SELECT user_id FROM {$prefix}users WHERE username = '$username'");
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
						$result.= "[/quote:$bbcode]\r";
						break;
					case(chr(1)):
						$result.= "[quote:$bbcode]\r";
						break;
					default:
						$result.= "[quote".($q?"=\"{$q}\"":'').":$bbcode]\r";
				endswitch;
			endforeach;
			endif;
			if ($i<$s) $result.= $text[$i]."\r";
		endfor;
		return $result;
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
	
	function _phpbb_to_fidomessage($bbcode,$lvl,$text_init = '')
	{
		STATIC $text;
		STATIC $result;
		$init = '';
		if ($lvl):
			$init = ' ';
			$stttr = ":$bbcode]";
			$bp = $ep = false;
			if (substr($text,0,6) == "[quote") {
				$ep = strpos($text,$stttr);
				$bp = strpos($text,"[quote",1);
			}
			if (($ep !== false) && (($ep < $bp) || ($bp === false))):
				$l = strlen($stttr);
				if ($ep>6):
					$name = substr($text,7,$ep-7);
					$init.= get_initials(str_replace("\"",'',str_replace("&quot;","",$name)));
				endif;
				$text = substr($text,$ep+$l);
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
				$n = $m = strpos($str,":$bbcode]");
				if ($m !== false) while($str{--$m} != '[');
				if (($m !== false) && (substr($str,$m,6) == '[quote')):
					$add = substr($str,0,$m);
					if (trim($add)):
						if (strcmp($result{strlen($result)-1},"\n")!=0) $result.="\n";
						$result.= $init.$add;
					elseif ($lvl==0):
						if (strcmp($result{strlen($result)-1},"\n")!=0) $result.="\n";
						$result.= $add;
					endif;
	//				if (strcmp($str{strlen($str)-1},"\n")!=0) $result.="\n";
					$text = substr($str,$m).substr($text,$p);
					$this->_phpbb_to_fidomessage($bbcode,$lvl+1);
				elseif (($m !== false) && (substr($str,$m,7) == '[/quote')):
					$add = substr($str,0,$m);
					if (trim($add)):
						if (strcmp($result{strlen($result)-1},"\n")!=0) $result.="\n";
						$result.= $init.$add;
					elseif ($lvl==0):
						if (strcmp($result{strlen($result)-1},"\n")!=0) $result.="\n";
						$result.= $add;
					endif;

					$str = substr($str,$m+strlen("[/quote:$bbcode]"));
//					if (strcmp($str{strlen($str)-1},"\n")!=0) $result.="\n";
					$text = ltrim($str).substr($text,$p+1);
					break;
				else:
					$add = $str;
					if (trim($add)):
						if (strcmp($result{strlen($result)-1},"\n")!=0) $result.="\n";
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
		if (strcmp($result{strlen($result)-1},"\n")!=0) $result.="\n";
		return $result;
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

	function WriteMessage($header,$message)
	{
		if (strlen($header->From)>255):
			$username = substr($header->From,0,255);
		else:
			$username = $header->From;
		endif;
		$username = $this->base->escape($username);
		$ip = $this->_flag_to_phpbb($header->Attrs);

		list($message,$bbcode) = $this->_convert_message($message);
		$message = utfconvert(convert_cyr_string($message,"d","w"),'u');
		$username = utfconvert(convert_cyr_string($username,"d","w"),'u');

		$uid = $this->_user_getid($username);
		if ($uid == -1)
			$uid = $this->_create_fidouser($username);
		$subj = $header->Subj;
		$orig_subj = $header->Subj;
		$subj = preg_replace('/Re(\^[0-9]+)?(\[\^?[0-9]+\])?\: ?/i','',$subj);
		$subj = trim($subj);
		$subj = utfconvert(convert_cyr_string($subj,"d","w"),'u');
		if (strcmp(trim($subj),'')==0) $subj = '<empty>';
		$subj = $this->base->escape(htmlspecialchars($subj));
		$orig_subj = $this->base->escape($orig_subj);
		$orig_subj = htmlspecialchars(utfconvert(convert_cyr_string($orig_subj,"d","w"),'u'));
		$Date = $header->ADate?$header->ADate:time();
		
		$topic = $this->_get_topic_of($subj);
		$this->_post_message($topic,$uid,$subj,$orig_subj,$Date,$message,$bbcode,$ip,$username);
	}
	
	function _post_message($t_id,$uid,$subj,$orig_subj,$Date,$message,$bbcode,$ip,$username)
	{
		$prefix = $this->prefix;
		$new = $t_id == -1;
		$message = $this->base->escape($message);
		$this->base->query('BEGIN');
		if ($new):
			$this->base->query("INSERT INTO {$prefix}topics  (topic_poster, topic_time, forum_id, icon_id, topic_approved, topic_title, topic_first_poster_name, topic_first_poster_colour, topic_type, topic_time_limit, topic_attachment) VALUES ($uid, $Date, {$this->echoid}, 0, 1, '$subj', '$username', '', 0, 0, 0)");
			if ($res = $this->base->query("SELECT topic_id FROM {$prefix}topics WHERE topic_title = '$subj' AND topic_poster = $uid AND topic_time = $Date AND forum_id = {$this->echoid} ORDER BY topic_id DESC")):
				if ($this->base->numrows($res)):
					$t_id = $this->base->result($res,0);
				endif;
				$this->base->free($res);
			endif;
		endif;
		
		$this->base->query("INSERT INTO {$prefix}posts  (forum_id, poster_id, icon_id, poster_ip, post_time, post_approved, enable_bbcode, enable_smilies, enable_magic_url, enable_sig, post_username, post_subject, post_text, post_checksum, post_attachment, bbcode_bitfield, bbcode_uid, post_postcount, post_edit_locked, topic_id) VALUES ({$this->echoid}, $uid, 0, '$ip', $Date, 1, 1, 1, 1, 0, '$username', '$orig_subj', '$message', '".($md5 = md5($message))."', 0, 'gA==', '$bbcode', 1, 0, $t_id)");
		
		if ($res = $this->base->query("SELECT post_id FROM {$this->prefix}posts WHERE topic_id = $t_id AND post_checksum = '$md5' ORDER BY post_id DESC")):
			if ($this->base->numrows($res)):
				$p_id = $this->base->result($res,0);
			endif;
			$this->base->free($res);
		endif;

		if ($new) {
			$this->base->query("UPDATE {$prefix}topics SET topic_first_post_id = $p_id, topic_last_post_id = $p_id, topic_last_post_time = $Date, topic_last_poster_id = $uid, topic_last_poster_name = '$username', topic_last_poster_colour = '' WHERE topic_id = $t_id");

			$this->base->query("UPDATE {$prefix}config SET config_value = config_value + 1 WHERE config_name = 'num_topics'");
		}
		$this->base->query("UPDATE {$prefix}config SET config_value = config_value + 1 WHERE config_name = 'num_posts'");

		$this->base->query("COMMIT");
		$this->base->query("BEGIN");
		if ($new) {
			$this->base->query("UPDATE {$prefix}topics SET topic_last_post_id = $p_id, topic_last_post_subject = '$subj', topic_last_post_time = $Date, topic_last_poster_id = $uid, topic_last_poster_colour = '', topic_last_poster_name = '$username' WHERE topic_id = $t_id");
			$this->base->query("UPDATE {$prefix}forums SET forum_posts = forum_posts + 1, forum_topics_real = forum_topics_real + 1, forum_topics = forum_topics + 1, forum_last_post_id = $p_id, forum_last_post_subject = '$subj', forum_last_post_time = $Date, forum_last_poster_id = $uid, forum_last_poster_colour = '', forum_last_poster_name = '$username' WHERE forum_id = {$this->echoid}");
		} else {
			$this->base->query("UPDATE {$prefix}topics SET topic_replies_real = topic_replies_real + 1, topic_bumped = 0, topic_bumper = 0, topic_replies = topic_replies + 1, topic_last_post_id = $p_id, topic_last_post_subject = '$subj', topic_last_post_time = $Date, topic_last_poster_id = $uid, topic_last_poster_colour = '', topic_last_poster_name = '$username' WHERE topic_id = $t_id");
			$this->base->query("UPDATE {$prefix}forums SET forum_posts = forum_posts + 1, forum_last_post_id = $p_id, forum_last_post_subject = '$subj', forum_last_post_time = $Date, forum_last_poster_id = $uid, forum_last_poster_colour = '', forum_last_poster_name = '$username' WHERE forum_id = {$this->echoid}");
		}

		$this->base->query("UPDATE {$prefix}users SET user_lastpost_time = $Date, user_posts = user_posts + 1 WHERE user_id = $uid");
		$this->base->query("COMMIT");

		$this->base->query("INSERT INTO {$prefix}topics_posted  (user_id, topic_id, topic_posted) VALUES ('$uid', $t_id, 1)");
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
		$prefix = $this->prefix;
		if ($ext) {
			$q = $this->base->query("SELECT poster_id,post_time,poster_ip,post_username,topic_id FROM {$prefix}posts WHERE post_id = $msg");
		} else {
			$q = $this->base->query("SELECT poster_id,post_time,poster_ip,post_username,topic_id,bbcode_uid,post_subject,post_text FROM {$prefix}posts WHERE forum_id = {$this->echoid} ORDER BY post_id LIMIT 1".($msg==1?'':' OFFSET '.($msg-1)));
		}
		$arr = $this->base->fetchrow($q);
		$this->base->free($q);
		list($poster_id,$time,$attrs,$username,$topicid,$bbcode,$subj,$text) = $arr;
	
		if ($subj == '') {
			if ($res = $this->base->query("SELECT topic_title FROM {$prefix}topics WHERE topic_id = $topicid")):
				if ($this->base->numrows($res)):
					$subj = $this->base->result($res,0);
				endif;
				$this->base->free($res);
			endif;
		}

		if (preg_match("/\[quote=(\&quot;)?\"?([^\]\"\&]*)\"?(\&quot;)?:$bbcode]/i",$text,$res)):
			$to = $res[2];
		else:
			$to = 'All';
		endif;

		if ($username):
			$from = $username;
		else:
			if ($res = $this->base->query("SELECT username FROM {$prefix}users WHERE user_id = $poster_id")):
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
		$result->From = convert_cyr_string(utfconvert($from,'w'),'w','d');
		$result->To = convert_cyr_string(utfconvert($to,'w'),'w','d');
		$result->Subj = convert_cyr_string(utfconvert($subj,'w'),'w','d');
		$result->Attrs = $this->_phpbb_to_flag($attrs);
		return $result;
	}

	function ReadMsgBody($msg,$ext)
	{
		GLOBAL $CONFIG;
		if ($ext) {
			$res = $this->base->query("SELECT bbcode_uid,post_text FROM {$this->prefix}posts WHERE post_id = $msg");
		} else {
			$res = $this->base->query("SELECT bbcode_uid,post_text FROM {$this->prefix}posts WHERE forum_id = {$this->echoid} ORDER BY post_id LIMIT 1".($msg==1?'':' OFFSET '.($msg-1)));
		}
		if ($res):
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
			$result = convert_cyr_string(utfconvert($result,'w'),'w','d');
			$result = chr(1)."MSGID: ".$addr.' '.getmsgid()."\n".chr(1)."PID: $pid\n".$result."\n--- $tear\n * Origin: $origin ($addr)";
			$result = str_replace("\n","\r",$result);
		endif;
		return $result;
	}
}