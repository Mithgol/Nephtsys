#!/usr/bin/env bash
#
#  This script makes deb-package of phfito
#  $Id: make-deb.sh,v 1.5 2011/01/10 02:01:48 kocharin Exp $
#

TEMP=/tmp

# path to phfito
PHFITO=`pwd | sed 's/^\(.*phfito\).*$/\1/'`

# our php-cli executable
OURPHP=`which php`

if [ -z $OURPHP ]; then
	echo "You don't have php-cli installed!"
	exit 1
fi

if [ ! -x $OURPHP ]; then
	echo "You don't have php-cli installed or have permission problems with $OURPHP"
	exit 1
fi


# checking whether we are in distributive directory
if [ -z "$PHFITO" ]; then
	echo 'Error: you must execute it from phfito directory'
	exit 1
fi

if [ ! -d $PHFITO/tests ]; then
	echo 'Error: you must put tests into distribution'
	exit 2
fi

VERSION=`grep VERSION $PHFITO/src/S_version.php | sed "s/.VERSION.//" | \
	sed "s/^.*'\(.*\)'.*$/\1/" | sed 's/\\//_/'`

if [ -z "$VERSION" ]; then
	echo "Error: couldn't extract phfito version"
	exit 3
fi

if [ -z "$TEMP" ]; then
	echo "Error: you must specify temp directory"
	exit 4
fi

mkdir $TEMP/mkphfito-$$ 
cp -R $PHFITO $TEMP/mkphfito-$$
pushd $TEMP/mkphfito-$$

find phfito -name CVS | xargs rm -rf

pushd phfito
tar czf contrib/tests.tar.gz tests
rm -rf tests
popd

mkdir -p usr/share/phfito
cp -R phfito/conf usr/share/phfito
cp -R phfito/src usr/share/phfito
cp -R phfito/contrib usr/share/phfito
cp -R phfito/docs usr/share/phfito

mkdir -p etc/phfito
cp phfito/conf/* etc/phfito

mkdir -p usr/bin

FROM=$PHFITO
SHARE=/usr/share/phfito
CONFIGS=/etc/phfito
PHP=/usr/bin/php
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
?>' | $OURPHP > usr/bin/phfito
	chmod +x usr/bin/phfito

mkdir DEBIAN
find etc usr -type f -exec md5sum {} \; > DEBIAN/md5sums
find etc -type f | sed s/^/\\// > DEBIAN/conffiles

rm -rf phfito

SIZE=`du $TEMP/mkphfito-$$ -sB1024 | cut -f 1`
DATE=`date -R`

echo "Package: phfito
Maintainer: Alex Kocharin <alex@kocharin.pp.ru>
Section: comm
Priority: extra
Architecture: all
Depends: php-cli | php4-cli | php5-cli
Version: $VERSION
Date: $DATE
Installed-Size: $SIZE
Description: Fidonet Technology tosser" > DEBIAN/control

#tar -czf $PHFITO/../phfito-$VERSION.tar.gz phfito
popd

sudo chown -R root:root $TEMP/mkphfito-$$
dpkg --build $TEMP/mkphfito-$$ $PHFITO/../phfito_${VERSION}.deb
sudo rm -rf $TEMP/mkphfito-$$

echo 'All done'
