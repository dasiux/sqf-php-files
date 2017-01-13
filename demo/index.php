<style type="text/css">
    pre {
        margin:1em 0;
        padding:1em;
        border-bottom:1px solid black;
    }
    small {
        display:block;
    }
</style>
<form action="" method="post" enctype="multipart/form-data">
    <input type="file" name="upload">
    <input type="submit" name="submit">
</form>
<img src="temp.jpg" />
<?php
var_dump($_FILES);
$basedir = dirname(__DIR__).DIRECTORY_SEPARATOR;
require($basedir.'src/sqf/files/exception.php');
require($basedir.'src/sqf/files/handler.php');
require($basedir.'src/sqf/files/base.php');
require($basedir.'src/sqf/files/file.php');
require($basedir.'src/sqf/files/ascii.php');
require($basedir.'src/sqf/files/image.php');

use sqf\files\exception;
use sqf\files\handler as fsh;
use sqf\files\base;
use sqf\files\file;
use sqf\files\ascii;
use sqf\files\image;

/**
 * Set your working directory to ensure clean shutdown procedures
 */
fsh::$wd = __DIR__.DIRECTORY_SEPARATOR;

/**
 * This will throw an exception
 *
 * Code: E_PARAMETER_TYPE:1
 * Message: 'Parameter $throw must be of type boolean|integer'
 */
#var_dump(fsh::exists('ascii.log',''));

/**
 * This will throw an exception
 *
 * Code: E_PATH_INVALID:2
 * Message: 'Parameter $path of type string is invalid or empty'
 */
#var_dump(fsh::exists(''));

/**
 * This file exists and returns true
 */
#var_dump(fsh::exists('ascii.log'));

/**
 * This file does not exist and returns false
 */
#var_dump(fsh::exists('does_not_exist.file'));


/**
 * This will throw and exception
 *
 * Code: E_PATH_EXISTS|E_FILE_EXISTS:3
 * Message: 'File does_not_exist.file not found'
 */
#var_dump(fsh::exists('does_not_exist.file',true));

/**
 * Getting file information
 *
 * This will throw all of the previously mentioned exceptions
 */
var_dump(fsh::info('ascii.log'));

/**
 * Open an existing log file
 *
 * This will throw all of the previously mentioned exceptions
 * and additionally the following
 *
 * Code:
 * Message:
 */
$file = fsh::open('ascii.log');

// Empty the file
$file->put('');

// Append some default content
$file->append("#begin\n");

// Log something to file
$file->log('first log');
$file->log('second log');

// Put file contents into a block and replace self
$file->put($file::drawBlock($file->content));

// Append a table of data
$file->append($file::drawTable([
    ['id'=>1,'name'=>'siux','email'=>'me@siux.info'],
    ['id'=>2,'name'=>'email','email'=>'email@example.com'],
    ['id'=>3,'name'=>'test','email'=>'test@test.com'],
]));

/**
 * Create a new temporary log file
 *
 * This will throw all of the previously mentioned exceptions
 * and additionally the following
 *
 * Code:
 * Message:
 */
$file = fsh::create('temp.log',null,[
    fsh::O_FILE_REPLACE=>true,
    fsh::O_FILE_TYPE=>'ascii',
    fsh::O_FILE_TEMP=>true,
]);

/**
 * Create a new image
 *
 * This will throw all of the previously mentioned exceptions
 * and additionally the following
 *
 * Code:
 * Message:
 */
$image = fsh::create('temp.jpg',[
    'width'=>100,
    'height'=>100,
    'color'=>[255,0,255]
],[
    fsh::O_FILE_REPLACE=>true,
    fsh::O_FILE_TYPE=>'image',
]);
