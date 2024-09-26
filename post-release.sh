#!/bin/bash -x

# Exit on error

# Handles any errors that occur during the script
# and reverts back to the previous state
handle_error() {
    local exit_code=$?
    echo "An error occurred with exit code $exit_code"
    git add .
    git commit -m "Reverting pre-release actions for version $version"
    exit $exit_code
}

trap 'handle_error' ERR

git add .

git commit -m "Releasing version $version"

git push origin $version
