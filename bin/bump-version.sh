#!/bin/bash

PACKAGE_FILE=src/Helper.php
COMPOSER_FILE=composer.json

# Allow passing a custom version string - defaults to "next"
SMOOTH_BUMP=false
VERSION=''

print_usage() {
  printf "./bump-version.sh -s [version_string]"
}

while getopts 'sv:' flag; do
  case "${flag}" in
    s) SMOOTH_BUMP=true ;;
    v) VERSION="${OPTARG}" ;;
    *) print_usage
       exit 1 ;;
  esac
done

LAST_PACKAGE='1.0.0'
LAST_COMPOSER=$LAST_PACKAGE

if test -f "$PACKAGE_FILE"; then
    LAST_PACKAGE=$(perl -ne 'print $1 while /\s*const\sVERSION\s=\s'\''(\d+\.\d+\.\d+)/sg' $PACKAGE_FILE)
fi

if test -f "$COMPOSER_FILE"; then
    LAST_COMPOSER=$(perl -ne 'print $1 while /\s*"version":\s\"(\d+\.\d+\.\d+)/sg' $COMPOSER_FILE)
fi

# Store the latest version detected in the actual files
LATEST=$(printf "$LAST_PACKAGE\n$LAST_COMPOSER" | sort -V -r | head -1)

NEXT_VERSION=$(echo ${LATEST} | awk -F. -v OFS=. '{$NF += 1 ; print}')

# Set the version to next version in case no version has been passed
if [ "$VERSION" == "" ]; then
    VERSION=$NEXT_VERSION
else
    TMP_LAST=$(printf "$LATEST\n$VERSION" | sort -V -r | head -1)
    # Do not bump the version in smooth mode in case the version has already been bumped.
    if [ "$SMOOTH_BUMP" == "true" ] && [ "$TMP_LAST" == "$LATEST" ]; then
        NEXT_VERSION=$LATEST
    fi
fi

# Use the latest version: Either detected in files or from custom argument
NEW_VERSION=$(printf "$NEXT_VERSION\n$VERSION" | sort -V -r | head -1)

export NEW_VERSION

if test -f "$PACKAGE_FILE"; then
    perl -pe '/^\s*const\sVERSION\s=\s/ and s/(\d+\.\d+\.\d+)/$2 . ("$ENV{'NEW_VERSION'}")/e' -i $PACKAGE_FILE
fi

if test -f "$COMPOSER_FILE"; then
    perl -pe '/^\s*"version":/ and s/(\d+\.\d+\.\d+)/$2 . ("$ENV{'NEW_VERSION'}")/e' -i $COMPOSER_FILE
fi

# Output the current version including appendices
echo $(perl -ne 'print $1 while /\s*const\sVERSION\s=\s'\''(\d+\.\d+\.\d+(-(beta|alpha|dev))?)/sg' $PACKAGE_FILE)