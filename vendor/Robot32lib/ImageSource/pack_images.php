<?php

// Function to recursively find .jpg files
function findJpgFiles($dir) {
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'jpg') {
            $files[] = $file->getPathname();
        }
    }
    return $files;
}

// Get all .jpg files
$jpgFiles = findJpgFiles('.');

// Open archive file and locations file
$archiveFile = fopen('pictures.archive', 'wb');
$locationsFile = fopen('pictures.locations.txt', 'w');

$currentPosition = 0;

foreach ($jpgFiles as $file) {
    $fileContent = file_get_contents($file);
    $fileSize = strlen($fileContent);
    
    // Write file content to archive
    fwrite($archiveFile, $fileContent);
    
    // Write file information to locations file
    $relativePath = str_replace(realpath('.') . DIRECTORY_SEPARATOR, '', $file);
    fwrite($locationsFile, "$relativePath|$fileSize|$currentPosition\n");
    
    // Update current position for next file
    $currentPosition += $fileSize;
}

// Close files
fclose($archiveFile);
fclose($locationsFile);

echo "Process completed. Check pictures.archive and pictures.locations.txt files.";

?>