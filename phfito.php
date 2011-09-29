#!/usr/bin/php
<?
/*\
  /  This is a part of PhFiTo (aka PHP Fido Tosser)
 //  Copyright (c) Alex Kocharin, 2:50/13
///
///  This program is distributed under GNU GPL v2
///  See docs/license for details
//
/    $Id: phfito.php,v 1.14 2011/01/09 03:39:53 kocharin Exp $
\*/

$SRC_PATH = 'src';
$CONFIG_FILE = false;
$DEBUG = false;
$BASE_DIR = '.';
$MODULE = 'Phfito';
$COLOUR = 'auto'; # auto; never; always

# Strict Standards: It is not safe to rely on the system's timezone 
# settings. Please use the date.timezone setting, the TZ environment 
# variable or the date_default_timezone_set() function. In case you 
# used any of those methods and you are still getting this warning, 
# you most likely misspelled the timezone identifier.
#
# Dirty fix this warning... It should be logged I think...

date_default_timezone_set(@date_default_timezone_get());

function run_phfito_run($argc, $argv)
{
	GLOBAL $SRC_PATH, $CONFIG_FILE, $DEBUG, $BASE_DIR, $MODULE, $COLOUR;
	
	$cmd = new cmdline($argc, $argv);
	if ($cmd->ok == false) return 1;

	$SRC_PATH_cp = $SRC_PATH;
	$CONFIG_FILE_cp = $CONFIG_FILE;
	$DEBUG_cp = $DEBUG;
	$BASE_DIR_cp = $BASE_DIR;
	$MODULE_cp = $MODULE;
	$COLOUR_cp = $COLOUR;

	$opt =& $cmd->options;
	$opt_e = isset($opt['e']);
	$opt_s = isset($opt['s']);
	$opt_t = isset($opt['t']);
	$opt_m = isset($opt['m']);
	$opt_p = isset($opt['p']);
	$opt_y = isset($opt['y']);
	$opt_X = isset($opt['X']);
	$opt_W = isset($opt['W']);
	$opt_P = isset($opt['P']);
	$opt_r = isset($opt['r']);
	$opt_R = isset($opt['R']);

	if (isset($opt['c'])) {
		if ($opt['c'][0] == '-') {
			$CONFIG_FILE = false;
		} else {
			$CONFIG_FILE = $opt['c'][0];
		}
	}

	if (isset($opt['i'])) $SRC_PATH = rtrim($opt['i'][0],"\\/").'/';
	if (isset($opt['d'])) $DEBUG = true;
	if (isset($opt['C'])) $BASE_DIR = rtrim($opt['C'][0],"\\/").'/';
	if (isset($opt['M'])) $MODULE = $opt['M'][0];
	if (isset($opt['u'])) {
		$COLOUR = $opt['u'][0];
	}
	if ($COLOUR == 'always' || $COLOUR == 'on') {
		$COLOUR = true;
	} else if ($COLOUR == 'never' || $COLOUR == 'off') {
		$COLOUR = false;
	} else if (function_exists('posix_isatty')) {
		$COLOUR = posix_isatty(1);
	} else {
		$COLOUR = false;
	}

	chdir($BASE_DIR);

	$lch = $opt_s || $opt_t || $opt_m || $opt_p || $opt_X || $opt_y || $opt_e
		|| $opt_W || $opt_P || $opt_R || $opt_r;
	$tch = $opt_t || $opt_y || $opt_s || $opt_p || $opt_X || $opt_e || $opt_P
        || $opt_r || $opt_R;

	if ($lch) xinclude('S_config.php');
	if (isset($opt['v'])) xinclude('S_version.php');
	if ($opt_e) xinclude('S_confedit.php');
	if ($opt_m) xinclude('A_disc.php');
	if ($tch) xinclude('P_phfito.php');
	if ($opt_s || $opt_p || $opt_X || $opt_R) xinclude('L_areas.php');
	if ($opt_R) xinclude('L_echomail.php');
	if ($opt_y) xinclude('C_queue.php');

	$overkeys = array();
	if (isset($opt['V'])) {
		foreach($opt['V'] as $k) {
			$arr = split('=',$k,2);
			if (sizeof($arr) == 2) {
				list($key, $val) = $arr;
				$overkeys[$key] = $val;
			}
		}
	}

	if ($lch) aks_init($MODULE, $CONFIG_FILE, array(), $overkeys);
	if ($opt_m) Receive_mail();

	if ($tch) Tosser_init();
	
	if ($opt_r) cron_start();
	if ($opt_t) Run_tosser();

	if ($opt_s) ScanAreasParam($opt['s']);
	if ($opt_p) PurgeAreasParam($opt['p']);
	if ($opt_R) RescanAreasParam($opt['R']);
	if ($opt_y) SyncSubscribe($opt['y']);
	if ($opt_e) EditConfigCmd($opt['e']);

	if ($opt_X) {
		foreach($opt['X'] as $include) {
			eval(file_get_contents($include));
		}
	}

	if ($tch) Tosser_done();
	if ($opt_m) Send_mail();
	if ($lch) aks_done();

	if (isset($opt['l'])) {
		xinclude('L_listcfg.php');
		ListConfig($MODULE,$CONFIG_FILE);
	}

	if (isset($opt['T'])) {
		$dirp = $opt['T'][0];
		$testnum = 0;
		$dirpcont = array();
		if ($dp = @opendir($dirp)) {
			while(($file = readdir($dp)) !== false) {
				if ($file{0} != '.' && is_dir($dirp.'/'.$file)) {
					$dirpcont[] = $file;
				}
			}
			sort($dirpcont);
			closedir($dp);
		} else {
			print "Could not open directory \"$dirp\"\n";
		}
		foreach($dirpcont as $dirpc) {
			$dircont = array();
			$dir = $dirp.'/'.$dirpc;
			if ($dp = @opendir($dir)) {
				while(($file = readdir($dp)) !== false) {
					if ($file{0} != '.' && is_dir($file2 = $dir.'/'.$file) &&
							file_exists($file2.'/cmdline') &&
							file_exists($file2.'/check')) {
						$dircont[] = $file2;
					}
				}
				closedir($dp);
				sort($dircont);
				foreach($dircont as $file) {
					$cwd = getcwd();
					chdir($file);
					$descr = chop(file_get_contents('descr'));
					if (!file_exists('skip')) {
						$args = split("\n",file_get_contents('cmdline'));
						array_unshift($args, '');
						if (file_exists('prepare')) {
							eval(file_get_contents('prepare'));
						}
						ob_start();
						run_phfito_run(sizeof($args), $args);
						$output = ob_get_contents();
						ob_clean();
						eval(file_get_contents('check'));
						$result = trim(ob_get_clean());
						if ($result == 'ok') {
							printf("%sPASS%s: (%s#%02d%s) %s\n", 
								colour_str(COLOR_GREEN), 
								colour_str(COLOR_NORMAL),
								colour_str(COLOR_MAGENTA),
								$testnum++, 
								colour_str(COLOR_NORMAL),
								$descr
							);
						} else {
							printf("%sFAIL%s: (%s#%02d%s) %s\n", 
								colour_str(COLOR_RED), 
								colour_str(COLOR_NORMAL),
								colour_str(COLOR_MAGENTA),
								$testnum++, 
								colour_str(COLOR_NORMAL),
								$descr
							);
							print "========================================\n";
							print " Output:\n";
							print $result."\n";
							print "========================================\n";
						}
					} else {
						printf("%sSKIP%s: (%s#%02d%s) %s\n", 
							colour_str(COLOR_CYAN), 
							colour_str(COLOR_NORMAL),
							colour_str(COLOR_MAGENTA),
							$testnum++, 
							colour_str(COLOR_NORMAL),
							$descr
						);
					}
					chdir($cwd);
				}
			}
		}
	}

	if (isset($opt['h'])) {
		$cmd->printhelp();
	}

	if (isset($opt['v'])) {
		$cmd->printver();
	}
	
	$SRC_PATH = $SRC_PATH_cp;
	$CONFIG_FILE = $CONFIG_FILE_cp;
	$DEBUG = $DEBUG_cp;
	$BASE_DIR = $BASE_DIR_cp;
	$MODULE = $MODULE_cp;
	$COLOUR = $COLOUR_cp;
	return 0;
}

$SRC_PATH = rtrim($SRC_PATH,"\\/").'/';

return run_phfito_run($_SERVER['argc'], $_SERVER['argv']);

/***************************************************************************/
//                                 Resources                               //
/***************************************************************************/

function xinclude($file, $once = true)
{
	GLOBAL $SRC_PATH, $DEBUG;
	STATIC $already = array();
	if (isset($SRC_PATH)) {
		$filename = $SRC_PATH.$file;
	} else {
		$filename = $file;
	}
	if (isset($already[$filename])) {
		return;
	}
	$already[$filename] = true;
	if (isset($DEBUG) && $DEBUG) {
		$await = 0;
		$code = '';
		$contents = file_get_contents($filename);
		foreach(token_get_all($contents) as $token) {
			if (is_array($token)) {
				list ($token, $text) = $token;
				if ($token == T_FUNCTION) {
					$await = 1;
				}
			} else if (is_string($token)) {
				$text = $token;
				if ($await && ($token == '{')) {
					$await = 0;
					$text .= 
						"\$FUNCT_ARGS=func_get_args();".
						"tracefc(__CLASS__,__FUNCTION__, 0, \$FUNCT_ARGS);".
						"unset(\$FUNCT_ARGS);";
				}
			}
			$code .= $text;
		}
		eval('?>'.$code);
	} else {
		include $filename;
	}
	return;
}

function tracefc($class, $funct, $ret, $argarr)
{
	$s = sizeof($argarr);
	for($i=0;$i<$s;$i++) {
		$arg =& $argarr[$i];
		$arg = (string)$arg;
		if (strlen($arg) > 20) {
			$arg = substr($arg, 0, 17)."...";
		}
	}
	$args = join('", "',$argarr);
	if ($args) {
		$args = '("'.$args.'")';
	} else {
		$args = '()';
	}
	print $ret?"done":"called";
	print '  ';
	if ($class != '') print $class.'::';
	print $funct." ";
	print $args."\n";
	return;
}

define('CMD_CANDUP', 1);
define('CMD_PARAM_EXISTS', 2);
define('CMD_PARAM_OPTIONAL', 4);


class cmdline
{
	var $options;
	var $ok;

	var $lex;
	var $argc;
	var $argv;

	var $longs; // aliases
	var $shorts;
	var $stopon;
	var $addition;

	function set_params()
	{
		$this->longs = array(
			'toss' => 't',
			'list' => 'l',
			'purge' => 'p',
			'scan' => 's',
			'sqlscan' => 'Q',
			'move' => 'm',
			'test' => 'T',
			'exec' => 'X',
			'debug' => 'd',
			'config' => 'c',
			'colour' => 'u',
			'color' => 'u',
			'help' => 'h',
			'version' => 'v',
			'include' => 'i',
			'sync' => 'y',
			'edit' => 'e',
			'module' => 'M',
			'setvar' => 'V',
			'chdir'	=> 'C',
			'post' => 'P',
			'convert' => 'W',
			'rescan' => 'R',
			'cron' => 'r',
		);

		$this->shorts = array_values($this->longs);
		$this->stopon = array('P', 'W');
		$this->addition = array();
	}

	function cmdline($argc, $argv)
	{
		$this->lex = array();
		$this->argc =& $argc;
		$this->argv =& $argv;
		$this->set_params();
		$this->ok = true;

		if ($argc == 1) {
			$this->options['h'] = array();
		} else {
			$this->parselexem();
			if ($this->lex == NULL) {
				$this->ok = false;
			} else if (!$this->lex2array()) {
				$this->ok = false;
			} else if (!$this->checkparams()) {
				$this->ok = false;
			}
		}
	}

	function parselexem()
	{
		$nominus = false;
		for ($i=1; $i < $this->argc; $i++) {
			$arg =& $this->argv[$i];
			if (!$nominus && (strlen($arg) > 1) && ($arg{0} == '-') &&
				((ord($arg{1}) < ord('0')) || (ord($arg{1}) > ord('9')))) {
				if ($arg{1} == '-') {
					/* checking long options */
					$word = substr($arg,2);
					$out = false;
					if (strlen($word) == 0) {
						$nominus = true;
					} else if (isset($this->longs[$word])) {
						$out = $this->longs[$word];
						if (in_array($out, $this->stopon)) {
							for($i++; $i < $this->argc; $i++) {
								$this->addition[] = $this->argv[$i];
							}
						}
					} else {
						printf("Unknown parameter: --%s\n",$word);
						$this->lex = NULL;
					}
					if ($out !== false) {
						$this->lex[] = array($out, false);
					}
				} else {
					/* checking short options */
					$s = strlen($arg);
					$fr = '';
					for($j=1; $j<$s; $j++) {
						if (ord($arg{$j}) >= ord('0') && 
								ord($arg{$j}) <= ord('9')) {
							$fr .= $arg{$j};
						} else {
							if (strlen($fr)) {
								$this->lex[] = array(false, $fr);
								$fr = '';
							}
							$this->lex[] = array($arg{$j}, false);
							if (in_array($arg{$j}, $this->stopon)) {
								for($i++; $i < $this->argc; $i++) {
									$this->addition[] = $this->argv[$i];
								}
							}
						}
					}
					if (strlen($fr)) {
						$this->lex[] = array(false, $fr);
						$fr = '';
					}
				}
			} else {
				/* checking free options */
				$this->lex[] = array(false, $arg);
			}
		}
	}

	function lex2array()
	{
		$s = sizeof($this->lex);
		$curr = false;
		for($cnt=0; $cnt<$s; $cnt++) {
			$lex =& $this->lex[$cnt];
			if ($lex[1] === false) {
				$lexem = $lex[0];
				if (isset($this->options[$lexem])) {
					printf("Duplicated parameter -%s | --%s\n",
						$lexem, $this->getparamstrbychar($lexem)
					);
					return 0;
				}
				if (in_array($lexem,$this->shorts)) {
					$this->options[$lexem] = array();
					$curr = $lexem;
				} else {
					$this->options = array();
					printf("Unknown parameter: -%s\n", $lex[0]);
					return 0;
				}
			} else {
				if ($curr === false) {
					printf("Unknown option: %s\n", $lex[1]);
					return 0;
				} else {
					switch($curr) {
						case 't':
						case 'm':
						case 'l':
						case 'd':
						case 'h':
						case 'v':
						case 'r':
							printf("Parameter -%s | --%s cannot be followed by an argument\n",
								$curr, $this->getparamstrbychar($curr)
							);
							return 0;
						case 'c':
						case 'u':
						case 'i':
						case 'T':
						case 'M':
						case 'C':
							if ($this->options[$curr] !== array()) {
								printf("Parameter -%s | --%s followed by too many arguments\n",
									$curr, $this->getparamstrbychar($curr)
								);
								return 0;
							}
						case 'p':
						case 's':
						case 'Q':
						case 'y':
						case 'X':
						case 'e':
						case 'V':
						case 'R':
							$this->options[$curr][] = $lex[1];
							break;
						default:
							printf("Internal error\n");
							return 0;
					}
				}
			}
		}
		return 1;
	}

	function getparamstrbychar($c)
	{
		foreach($this->longs as $long=>$short) {
			if ($short == $c) {
				return $long;
			}
		}
		return "ERROR";
	}

	function checkparams()
	{
		foreach(array('i','u','c','T','y','e','M','V','C','R') as $i) {
			if (isset($this->options[$i]) && (sizeof($this->options[$i]) == 0)) {
				printf("Parameter -%s | --%s requires a parameter\n",
					$i, $this->getparamstrbychar($i)
				);
				return 0;
			}
		}
		if (isset($this->options['u'])) {
			$u = $this->options['u'][0];
			if (!($u == 'on' || $u == 'always' ||
				$u == 'off' || $u == 'never' || $u == 'auto')) {
				printf("Parameter -%s | --%s should be never, always or auto\n",
					'u', $this->getparamstrbychar('u')
				);
				return 0;
			}
		}
		return 1;
	}

	function printhelp()
	{
		print '
  Usage: phfito [options] [commands]

Commands:
  --toss, -t        toss incoming mail
  --scan, -s        scan areas
  --sqlscan, -Q     scan SQL areas
  --purge, -p       purge and pack areas
  --move, -m        move mail from/to filebox
  --sync, -y        send +linked_areas to remote areafix
  --rescan, -R      rescan some mail to link
  --test, -T        self-test
  --edit, -e        edit configuration files
  --stat, -S   (!)  generate some statistics
  --convert, -W     convert message bases
  --post, -P        post new message in area
  --cron, -r        execute embedded (ana)cron
  --list, -l        print parsed config to stdout
  --help, -h        write this help to stdout
  --version, -v     write program version to stdout

"--scan" and "--purge" commands can be followed by some optional arguments:
mask of affected areas or filename (prefixed by "@") with these areas.
(by default all areas would be affected)

"--post" and "--convert" require parameters to execute accordant functions.
Use `phfito --post -h` and `phfito --convert -h` to get more information.

"--test" requires one argument: a directory contains set of tests.

"--sync" requires one or many arguments: addresses of affected links.

Options:
  --debug, -d       debug mode
  --config, -c      specify config file
  --colour, -u      enables or disables colouring (auto | always | never)
  --include, -i     specify directory with phfito sources
  --module, -M      set module name
  --setvar, -V      set config variable
  --chdir, -C       set base dir

Examples:

# typical usage: tossing messages, scanning areas in ./export.log and
# moving mail to uplinks\' fileboxes.
  phfito -tms @./export.log
  
';
	}

	function printver()
	{
		if (preg_match("/\s+(\S+)\s+/", '$Revision: 1.14 $', $match)) {
			$revision = $match[1];
		} else {
			$revision = 'unknown';
		}
		print '
PhFiTo aka PHP Fido Tosser version '.VERSION.'
Copyright (c) Alex Kocharin, 2:50/13

This program is distributed under GNU GPL v2
See docs/license for details

Phfito.php revision '.$revision.'
PHP version '.phpversion().'
';
	}
}

define(COLOR_BLACK, 0);
define(COLOR_RED, 1);
define(COLOR_GREEN, 2);
define(COLOR_YELLOW, 3);
define(COLOR_BLUE, 4);
define(COLOR_MAGENTA, 5);
define(COLOR_CYAN, 6);
define(COLOR_WHITE, 7);
define(COLOR_NORMAL, 9);

function colour_str($num)
{
	GLOBAL $COLOUR;
	if ($COLOUR === false) {
		return "";
	} else {
		switch($num) {
			case COLOR_BLACK:
				return "\x1b\x5b\x33\x30\x6d";
			case COLOR_RED:
				return "\x1b\x5b\x33\x31\x6d";
			case COLOR_GREEN:
				return "\x1b\x5b\x33\x32\x6d";
			case COLOR_YELLOW:
				return "\x1b\x5b\x33\x33\x6d";
			case COLOR_BLUE:
				return "\x1b\x5b\x33\x34\x6d";
			case COLOR_MAGENTA:
				return "\x1b\x5b\x33\x35\x6d";
			case COLOR_CYAN:
				return "\x1b\x5b\x33\x36\x6d";
			case COLOR_WHITE:
				return "\x1b\x5b\x33\x37\x6d";
			case COLOR_NORMAL:
				return "\x1b\x5b\x33\x39\x6d";
		}
	}
	return "";
}

?>
