#!/bin/bash
#
# V1 11.12.2024
#
# Script to turn your Laravel codebase into a TXT file
# with the following structure:
#
#   <File Start: ./path/filename.extension>
#     Content of file
#   <End File: ./path/filename.extension>
#
# This was created with the help of Claude, because I
# encountered some issues along the way. Seems to be
# working fine now.
#
# Check if correct number of arguments provided
#
if [ "$#" -lt 1 ] || [ "$#" -gt 2 ]; then
    echo "Usage: promptgen <directory|laravel-app> [output-filename]"
    exit 1
fi
#
# Determine the directories to process:
# - if the first argument is laravel-app it will automatically
#   get the ./app, ./config, ./routes and ./bootstrap folders
# - otherwise it will only get the path you specify
#
if [ "$1" == "laravel-app" ]; then
    DIRS="./app ./config ./routes ./bootstrap"
else
    # Get the directory path (convert to absolute path if relative)
    DIRS=$(realpath "$1")
fi
#
# Set output filename (default is prompt.txt)
#
OUTPUT_NAME=${2:-"prompt.txt"}
OUTPUT_DIR=~/Prompts
OUTPUT_PATH="$OUTPUT_DIR/$OUTPUT_NAME"
#
# Creates a Prompts directory if it doesn't exist in your home directory
#
mkdir -p "$OUTPUT_DIR"
#
# Process all files in the directory and add them to the .txt file
#
echo "Processing PHP files from: $DIRS"
for dir in $DIRS; do
    find "$dir" -name "*.php" -type f | while read -r file; do
        echo "<File Start: $file>"
        cat "$file"
        echo "<End File: $file>"
    done
done > "$OUTPUT_PATH"
#
# Display a success message - you did it!
#
echo "Files have been compiled to: $OUTPUT_PATH"