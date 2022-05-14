#!/bin/bash

# Extract files that we need from the node_modules folder
# The extracted files are integrated to the repository, so production server don't need to
# have node installed

# AlpineJs

mkdir -p javascripts/vendor/alpinejs &&
  cp node_modules/alpinejs/dist/cdn.min.js javascripts/vendor/alpinejs