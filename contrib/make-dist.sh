#!/usr/bin/env bash
#
#  This script makes clean tarball with phfito inside
#  $Id: make-dist.sh,v 1.4 2011/01/10 02:01:48 kocharin Exp $
#

TEMP=/tmp

# path to phfito
PHFITO=`pwd | sed 's/^\(.*phfito\).*$/\1/'`

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
	sed "s/^.*'\(.*\)'.*$/\1/" | sed 's/\\//-/'`

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

mv phfito phfito-$VERSION
tar -czf $PHFITO/../phfito-$VERSION.tar.gz phfito-$VERSION
popd

rm -rf $TEMP/mkphfito-$$

echo 'All done'
