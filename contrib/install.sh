#!/usr/bin/env bash
#
#  This script installs this program to your linux system
#  $Id: install.sh,v 1.4 2011/01/10 02:16:03 kocharin Exp $
#

# path to install configuration - it is a directory
CONFIGS=/etc/phfito

# destination of main script - phfito.php
EXECUTABLE=/usr/bin/phfito

# path to install files needed for work
SHARE=/usr/share/phfito

# owner of all installed files
USER=sysop
GROUP=fido


# ----------------------------------------------- #
# do not edit last part of this file if you don't #
# really know what are you doing                  #

# path to you php executable
PHP=`which php`

if [ -z $PHP ]; then
	echo "You don't have php-cli installed!"
	exit 1
fi

if [ ! -x $PHP ]; then
	echo "You don't have php-cli installed or have permission problems with $PHP"
	exit 1
fi

# path to phfito distribution
FROM=`pwd | sed 's/^\(.*phfito.*\)\/.*$/\1/'`

# you may write a custom script to override defaults
if [ -x ~/.phfito-install ]; then
	. ~/.phfito-install
fi

CHOWN=
if [ -n "$USER" ]; then
	id $USER &> /dev/null
	if [ $? -eq 0 ]; then
		CHOWN=$USER
		if [ -n "$GROUP" ]; then
			CHOWN=$CHOWN:$GROUP
		fi
	fi
fi

# checking whether we are in distributive directory
if [ -z "$FROM" ]; then
	echo 'Error: you must execute it from phfito installation directory'
	exit 1
fi

if [ ! -d $FROM/src ]; then
	echo 'Error: you must execute it from phfito installation directory'
	exit 1
fi

if [ ! -d $FROM/docs ]; then
	echo 'Error: you must execute it from phfito installation directory'
	exit 1
fi

if [ ! -d $FROM/conf ]; then
	echo 'Error: you must execute it from phfito installation directory'
	exit 1
fi

if [ ! -d $FROM/contrib ]; then
	echo 'Error: you must execute it from phfito installation directory'
	exit 1
fi

# install configs into specified folder
if [ -n "$CONFIGS" ]; then
	if [ ! -e $CONFIGS ]; then
		CONFINST=1
		echo 'Installing configs into '$CONFIGS
		install -d $CONFIGS
		install $FROM/conf/* $CONFIGS
		if [ -n "$CHOWN" ]; then
			chown -R $CHOWN $CONFIGS
		fi
	fi
fi

# install share
if [ -n "$SHARE" ]; then
	if [ -e $SHARE ]; then
		echo 'Removing old files in '$SHARE
		rm $SHARE/src/*
		rmdir $SHARE/src
		rm $SHARE/docs/msgb/* $SHARE/docs/old/*
		rmdir $SHARE/docs/msgb $SHARE/docs/old
		rm $SHARE/docs/*
		rmdir $SHARE/docs
		rm $SHARE/contrib/*
		rm $SHARE/conf/*
		rmdir $SHARE/conf
		rmdir $SHARE/contrib
		rmdir $SHARE
	fi
	echo 'Installing common files into '$SHARE
	install -d $SHARE
	install -d $SHARE/conf
	install -d $SHARE/docs
	install -d $SHARE/contrib
	install -d $SHARE/docs/old
	install -d $SHARE/docs/msgb
	install -d $SHARE/src
	find $FROM/conf -maxdepth 1 -type f -exec install {} $SHARE/conf \;
	find $FROM/docs -maxdepth 1 -type f -exec install {} $SHARE/docs \;
	find $FROM/docs/msgb -maxdepth 1 -type f -exec install {} $SHARE/docs/msgb \;
	find $FROM/docs/old -maxdepth 1 -type f -exec install {} $SHARE/docs/old \;
	find $FROM/src -maxdepth 1 -type f -exec install {} $SHARE/src \;
	find $FROM/contrib -maxdepth 1 -type f -exec install {} $SHARE/contrib \;
	if [ -n "$CHOWN" ]; then
		chown -R $CHOWN $SHARE
	fi
fi

# install executable
if [ -n "$EXECUTABLE" ]; then
	if [ -e $EXECUTABLE ]; then
		echo 'Removing old executable - '$EXECUTABLE
		rm $EXECUTABLE
	fi
	echo 'Installing phfito.php into '$SHARE
	echo '<?php
$file = file("'$FROM'/phfito.php");
$src_path = "'$SHARE'";
$config_file = "'$CONFIGS'";
if ($src_path == "") {
	$src_path = "false";
} else {
	$src_path = "\x27$src_path/src\x27";
}
if ($config_file == "") {
	$config_file = "false";
} else {
	$config_file = "\x27$config_file/config\x27";
}
$file[0] = "#!'$PHP'"."\n";
$i = 0;
while($file[++$i]{0} != "$" && $i < sizeof($file));
while($i < sizeof($file)) {
    switch(strtok($file[$i], " ")) {
        case "\$SRC_PATH":
            $file[$i] = preg_replace("/(=\\s*)\\S+(\\s*;)/", "\\1$src_path\\2", $file[$i]);
            break;
        case "\$CONFIG_FILE":
            $file[$i] = preg_replace("/(=\\s*)\\S+(\\s*;)/", "\\1$config_file\\2", $file[$i]);
            break;
        case "function":
            $i = 0x7ffffffe;
            break;
    }
    $i++;
}
print(join("", $file));
?>' | $PHP > $EXECUTABLE
	chmod +x $EXECUTABLE
	if [ -n "$CHOWN" ]; then
		chown $CHOWN $EXECUTABLE
	fi
fi

echo 'Installation completed'
if [ -n "$CONFINST" ]; then
	echo ''
	echo 'Now you must edit your configuration in '$CONFIGS' directory'
fi
exit 0
