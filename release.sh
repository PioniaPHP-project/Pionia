#!/bin/bash -x

# Exit on error

# Handles any errors that occur during the script
# and reverts back to the previous state
handle_error() {
    local exit_code=$?
    echo "An error occurred with exit code $exit_code"
    exit $exit_code
}

trap 'handle_error' ERR

# collect the version number from the command line input
while getopts v: flag
do
    case "${flag}" in
        v) version=${OPTARG};;
    esac
done


echo "Removing files and tagging version $version"

git rm -r --cached example
git rm -r --cached docs
git rm -r --cached .phpdoc
git rm -r --cached tests

git tag -a $version -m "Releasing Version $version"

git push origin $version

# restore the files
git add .
