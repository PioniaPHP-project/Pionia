#!/bin/bash -x

# Exit on error

handle_error() {
    local exit_code=$?
    git add .
    git commit -m "Reverting pre-release actions for version $version"
    echo "An error occurred with exit code $exit_code"
    # Additional error handling logic can be added here
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

git tag -a $version -m "Release version $version"

git push origin $version

git add .

git commit -m "Running post-release events for version $version"

