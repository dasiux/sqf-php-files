<?php
/**
 * Class sqf\files\fsh
 *
 * File helper class
 *
 * @version 1.9.2
 * @author siux <me@siux.info>
 */
    namespace sqf\files;
    use finfo;
    use sqf\files\exception;
    use sqf\files\base;

    class handler {

    /** @var string $wd Working directory */
        public static $wd = null;

    /**
     * Get full path
     *
     * @param string $path
     *
     * @throws \sqf\files\exception
     *
     * @return string
     */
        public static function wdpath ($path) {
            // Invalid path input
            if (!is_string($path)||!strlen($path)) {
                throw new exception(static::error(static::E_PATH_INVALID,['pathtype'=>gettype($path)]),static::E_PATH_INVALID);
            }

            if ($path[0]!=DIRECTORY_SEPARATOR) {
                if ($path[0]=='.') {$path = substr($path,(1+strlen(DIRECTORY_SEPARATOR)));}
                return static::$wd.$path;
            }
            return $path;
        }

    /** @var callable|null $createDetect Custom file creation detection */
        public static $createDetect = null;

    /**
     * Create file
     *
     * @param string $path
     * @param string $content
     * @param array  $options
     *
     * @throws \sqf\files\exception
     *
     * @return \sqf\files\base
     */
        public static function create ($path,$content=null,array $options=[]) {
            // Check path
            $exists = static::exists($path);

            // Check replace path
            if ($exists&&static::getOption(static::O_FILE_REPLACE,$options)!==true) {
                throw new exception(static::error(static::E_FILE_REPLACE,['file'=>$path]),static::E_FILE_REPLACE);
            }

            // Path doesnt exist
            $dirname = dirname($path);
            if (!is_dir($dirname)) {
                if (static::getOption(static::O_PATH_CREATE,$options)===true) {
                    if (!mkdir($dirname,static::getOption(static::O_PATH_CREATE_MODE,$options),true)) {
                        throw new exception(static::error(static::E_PATH_CREATE,['dirname'=>$dirname]),static::E_PATH_CREATE);
                    }
                } else {
                    throw new exception(static::error(static::E_PATH_EXISTS,['path'=>$dirname]),static::E_PATH_EXISTS);
                }
            }

            // Path is not writable
            if (!is_writable($dirname)) {
                throw new exception(static::error(static::E_PATH_WRITABLE,['path'=>$dirname]),static::E_PATH_WRITABLE);
            }

            // Get create type
            $type = $opttype = static::getOption(static::O_FILE_TYPE,$options);

            // Detect type from content
            if (empty($type)&&static::getOption(static::O_DETECT_PATH,$options)===true) {
                $type = static::path2index($path);
            }

            // Detect type from content
            if (empty($type)&&static::getOption(static::O_DETECT_CONTENT,$options)===true) {
                $type = static::content2index($content);
            }

            // Custom detection
            if (isset(static::$createDetect)) {
                call_user_func_array(static::$createDetect,[&$type,&$path,&$content,&$options]);
            }

            // Detection error
            if (empty($type)) {
                $ot = gettype($opttype);
                throw new exception(static::error(static::E_FILE_TYPE,['path'=>$path,'option'=>($ot=='string'?$opttype:$ot),'content'=>gettype($content)]),static::E_FILE_TYPE);
            }

            // Get actual class
            $class = static::getClass($type);
            return new $class($path,$content,$options);
        }

    /** @var callable|null $loadDetect Custom file loading detection */
        public static $loadDetect = null;

    /**
     * Open an existing file
     *
     * @param string $path
     * @param array  $options
     *
     * @throws \sqf\files\exception
     *
     * @return \sqf\files\base
     */
        public static function open ($path,array $options=[]) {
            // Get file info
            $info = static::info($path);

            // Select file object class
            $class = static::getClass(static::mime2index($info));

            // Run custom class detection
            if (isset(static::$loadDetect)) {
                call_user_func_array(static::$loadDetect,[&$info,&$class]);
            }

            // Return new file object
            return new $class($path,null,$options);
        }

    /**
     * Create or open file
     *
     * @param string $path
     * @param string $content
     * @param array  $options
     *
     * @throws \sqf\files\exception
     *
     * @return \sqf\files\base
     */
        public static function createOrOpen ($path,$content=null,array $options=[]) {
            // Check source path
            if (static::exists($path)) {

                // Run open
                return static::open($path,$options);
            } else {

                // Run create
                return static::create($path,$content,$options);
            }
        }

    /** @var string $temp Local temporary directory */
        public static $temp = 'temp'.DIRECTORY_SEPARATOR;

    /**
     * Get uploaded file
     *
     * @param array  $uploaded
     * @param string $path
     * @param array  $options
     *
     * @throws \sqf\files\exception
     *
     * @return \sqf\files\base
     */
        public static function upload (array $uploaded,$path=null,array $options=[]) {
            // Validate upload data
            $validate = [
                'name'     => 'is_string',
                'type'     => 'is_string',
                'tmp_name' => 'is_string',
                'error'    => 'is_int',
                'size'     => 'is_int',
            ];
            foreach ($validate as $k => $type) {
                if (!isset($uploaded[$k])) {
                    // exception
                }
                if (!call_user_func($type,$uploaded[$k])) {
                    // exception
                }
            }

            // Upload error
            if ($uploaded['error']!=UPLOAD_ERR_OK) {
                // exception
            }

            // Possible hack
            if (!is_uploaded_file($uploaded['tmp_name'])) {
                // exception
            }

            // Get reliable file info
            $info = static::info($uploaded['tmp_name']);

            // Make the file temp unless specified
            if (!isset($options[static::O_FILE_TEMP])) {
                $options[static::O_FILE_TEMP] = true;
            }

            // Create file
            if (!move_uploaded_file($uploaded['tmp_name'],$path)) {
                // exception
            }
        }

    /** @var array $ext2index Extensions to class index reference */
        public static $ext2index = [
            'csv'  => 'csv',
            'xml'  => 'xml',
            'html' => 'html',
            'text' => 'ascii',
            'txt'  => 'ascii',
            'log'  => 'ascii',
            'md'   => 'ascii',
            'json' => 'json',
            'jpeg' => 'image',
            'jpg'  => 'image',
            'png'  => 'image',
            'gif'  => 'image',
        ];

    /**
     * Detect class index from path
     *
     * @param string $path
     *
     * @return string|null
     */
        public static function path2index ($path) {
            $info = pathinfo($path);
            if (isset(static::$ext2index[$info['extension']])) {
                return static::$ext2index[$info['extension']];
            }
            return null;
        }

    /**
     * Detect class index from content
     *
     * @param mixed $content
     *
     * @throws \sqf\files\exception
     *
     * @return string|null
     */
        public static function content2index (&$content) {
            $ct = gettype($content);
            switch ($ct) {
                case 'string':
                    $type = 'text';
                    break;
                case 'object':
                    if (method_exists($content,'__toArray')) {
                        // with __toArray > ^^ array > skip to next
                        $content = $content->__toArray();
                    } else {
                        if (method_exists($content,'__toString')) {
                            // with __toString > text
                            $content = $content->__toString();
                            $type = 'text';
                            break;
                        } else {
                            // xml object
                            // dom object
                            throw new exception(static::error(static::E_PARAMETER_TYPE,['param'=>'$content','type'=>'string|__toString|__toArray|array|resource']),static::E_PARAMETER_TYPE);
                        }
                    }
                case 'array':
                    // assoc > image
                    if (count(array_filter(array_keys($content),'is_string'))>0) {
                        $type = 'image';
                        break;
                    } else {
                        // numeric > csv
                        if (count(array_filter(array_keys($content),'is_array'))==count($content)) {
                            $type = 'csv';
                            break;
                        } else {
                            throw new exception(static::error(static::E_PARAMETER_TYPE,['param'=>'$content','type'=>'string|__toString|__toArray|array|resource']),static::E_PARAMETER_TYPE);
                        }
                    }
                case 'resource':
                    // resource type
                    // gd > image
                    // stream > ?
                case 'boolean':
                case 'integer':
                case 'double':
                case 'NULL':
                default:
                    $type = null;
            }
            return $type;
        }

    /** @var array $mime2class Mimetype to class index */
        public static $mime2class = [
            'text/csv'         => 'csv',
            'text/xml'         => 'xml',
            'text/html'        => 'html',
            'text/*'           => 'ascii',
            'application/json' => 'json',
            'application/xml'  => 'xml',
            'image/*'          => 'image',
        ];

    /**
     * Detect class index from mimetype
     *
     * @param array $info
     *
     * @throws \sqf\files\exception
     *
     * @return string
     */
        public static function mime2index (array $info) {
            // Check info input
            if (!isset($info['mimegroup'])||!isset($info['mimefile'])) {
                throw new exception(static::error(static::E_PARAMETER_TYPE,['param'=>'$info','type'=>'array and contain a mimegroup and mimefile index']),static::E_PARAMETER_TYPE);
            }

            // Check mimetype index
            $class = '';
            foreach (static::$mime2class as $mime => $mclass) {
                $mx = explode('/',$mime);
                if ($mx[0]==$info['mimegroup']&&($mx[1]=='*'||$mx[1]==$info['mimefile'])) {
                    $class = $mclass;
                    break;
                }
            }

            // Return class from index
            return $class;
        }

    /** @var string $defaultClassIndex Default class index */
        public static $defaultClassIndex = 'file';

    /** @var array $classIndex Classes reference */
        public static $classIndex = [
            'file'  => 'sqf\\files\\file',
            'ascii' => 'sqf\\files\\ascii',
            'csv'   => 'sqf\\files\\csv',
            'xml'   => 'sqf\\files\\xml',
            'html'  => 'sqf\\files\\html',
            'json'  => 'sqf\\files\\json',
            'image' => 'sqf\\files\\image',
        ];

    /**
     * Get classname from index
     *
     * @param string  $index
     * @param boolean $returnIndex
     *
     * @throws \sqf\files\exception
     *
     * @return string
     */
        public static function getClass ($index='',$returnIndex=false) {
            // Invalid returnIndex option
            if (!is_bool($returnIndex)&&!is_int($returnIndex)) {
                throw new exception(static::error(static::E_PARAMETER_TYPE,['param'=>'$returnIndex','type'=>'boolean|integer']),static::E_PARAMETER_TYPE);
            }

            // Invalid index
            if (!is_string($index)) {
                throw new exception(static::error(static::E_PARAMETER_TYPE,['param'=>'$index','type'=>'string']),static::E_PARAMETER_TYPE);
            }

            // Get specific index
            if (strlen($index)) {
                if (!isset(static::$classIndex[$index])) {
                    if ($returnIndex) {return $index;}
                    throw new exception(static::error(static::E_CLASS_INDEX,['index'=>$index]),static::E_CLASS_INDEX);
                }
                return static::$classIndex[$index];
            }

            // Default index
            return static::getClass(static::$defaultClassIndex);
        }

    /**
     * Check for a file class by index or classname
     *
     * @param \sqf\files\base $subject
     * @param string $class
     *
     * @throws \sqf\files\exception
     *
     * @return boolean
     */
        public static function isClass (base $subject,$class='') {
            $class = static::getClass($class,true);
            return ($subject instanceof $class);
        }

    /** @var object|\finfo|null $finfo Local finfo object */
        protected static $finfo = null;

    /**
     * Init finfo object
     *
     * @return void
     */
        protected static function setfinfo () {
            if (!isset(static::$finfo)) {static::$finfo = new finfo();}
        }

    /**
     * Use local finfo object
     *
     * @return \finfo
     */
        public static function finfo () {
            self::setfinfo();
            return static::$finfo;
        }

    /** @var array $textPlainExtentions Plain text extensions for detecting x-empty files */
        public static $textPlainExtentions = ['text','txt','log','md'];

    /** @var array $textPlainExt2mime Plain text to real mimetype associtations */
        public static $textPlainExt2mime = [
            'csv'  => 'text/csv',
            'xml'  => 'text/xml',
            'js'   => 'application/javascript',
            'json' => 'application/json',
        ];

    /**
     * File exists alias including exceptions
     *
     * @param string  $path
     * @param boolean $throw
     *
     * @throws \sqf\files\exception
     *
     * @return boolean
     */
        public static function exists ($path,$throw=false) {
            // Invalid throw option
            if (!is_bool($throw)&&!is_int($throw)) {
                throw new exception(static::error(static::E_PARAMETER_TYPE,['param'=>'$throw','type'=>'boolean|integer']),static::E_PARAMETER_TYPE);
            }

            // Invalid path input
            if (!is_string($path)||!strlen($path)) {
                throw new exception(static::error(static::E_PATH_INVALID,['pathtype'=>gettype($path)]),static::E_PATH_INVALID);
            }

            // Check source path
            if (!($exists = file_exists($path))&&$throw) {
                throw new exception(static::error(static::E_PATH_EXISTS,['path'=>$path]),static::E_PATH_EXISTS);
            }
            return $exists;
        }

    /**
     * Move file, alias for rename
     *
     * @param string $from
     * @param string $to
     * @param array  $options
     *
     * @throws \sqf\files\exception
     *
     * @return \sqf\files\base
     */
        public static function move ($from,$to,array $options=[]) {
            return static::rename($from,$to,$options);
        }

    /**
     * Rename file
     *
     * @param string $from
     * @param string $to
     * @param array  $options
     *
     * @throws \sqf\files\exception
     *
     * @return \sqf\files\base
     */
        public static function rename ($from,$to,array $options=[]) {
            // Check from
            static::exists($from,true);

            // Check replace path
            if (static::exists($to)) {
                if (static::getOption(static::O_FILE_REPLACE,$options)!==true) {
                    throw new exception(static::error(static::E_FILE_REPLACE,['file'=>$to]),static::E_FILE_REPLACE);
                } else {
                    unlink($to);
                }
            }

            // Move file
            if (!rename($from,$to)) {
                throw new exception(static::error(static::E_FILE_MOVE,['from'=>$from,'to'=>$to]),static::E_FILE_MOVE);
            }
            return static::open($to);
        }

    /**
     * Copy file
     *
     * @param string $from
     * @param string $to
     * @param array  $options
     *
     * @throws \sqf\files\exception
     *
     * @return \sqf\files\base
     */
        public static function copy ($from,$to,array $options=[]) {
            // Check from
            static::exists($from,true);

            // Check replace path
            if (static::exists($to)) {
                if (static::getOption(static::O_FILE_REPLACE,$options)!==true) {
                    throw new exception(static::error(static::E_FILE_REPLACE,['file'=>$to]),static::E_FILE_REPLACE);
                } else {
                    unlink($to);
                }
            }

            // Copy file
            if (!copy($from,$to)) {
                throw new exception(static::error(static::E_FILE_COPY,['from'=>$from,'to'=>$to]),static::E_FILE_COPY);
            }
            return static::open($to);
        }

    /**
     * Delete file
     *
     * @param string  $path
     * @param boolean $throw
     *
     * @throws \sqf\files\exception
     *
     * @return boolean
     */
        public static function delete ($path,$throw=false) {
            $exists = static::exists($path,$throw);
            if ($exists) {
                return unlink($path);
            }
            return false;
        }

    /**
     * Get file information array
     *
     * @param string  $path
     * @param boolean $guess
     *
     * @throws \sqf\files\exception
     *
     * @return array
     */
        public static function info ($path,$guess=true) {
            // Check if file/path exists
            static::exists($path,true);

            // File path type
            if (($filetype=filetype($path))!=='file') {
                throw new exception(static::error(static::E_PATH_TYPE,['pathtype'=>$filetype]),static::E_PATH_TYPE);
            }

            // Get basic info
            static::setfinfo();
            $info = pathinfo($path);
            $info['mimetype'] = static::$finfo->file($path,FILEINFO_MIME_TYPE);
            $info['encoding'] = static::$finfo->file($path,FILEINFO_MIME_ENCODING);

            // Qualified guessing for mimetypes
            if ($guess) {

                // Handling text files for x-empty mimetype
                if ($info['mimetype']=='inode/x-empty'&&in_array($info['extension'],static::$textPlainExtentions)) {
                    $info['mimetype'] = 'text/plain';
                    $info['encoding'] = 'utf-8';
                }
                if ($info['mimetype']=='text/plain'&&isset(static::$textPlainExt2mime[$info['extension']])) {
                    $info['mimetype'] = static::$textPlainExt2mime[$info['extension']];
                }
            }

            // Make mimetype parts available
            $mx = explode('/',$info['mimetype']);
            $info['mimegroup'] = $mx[0];
            $info['mimefile']  = $mx[1];

            // Return detected file information
            return $info;
        }

    /** @const O_DETECT_PATH      Detect type from path */
        const O_DETECT_PATH      = 0;

    /** @const O_DETECT_CONTENT   Detect type from content */
        const O_DETECT_CONTENT   = 1;

    /** @const O_PATH_CREATE      Create path if it does not exist */
        const O_PATH_CREATE      = 2;

    /** @const O_PATH_CREATE_MODE The mode used for creating the path */
        const O_PATH_CREATE_MODE = 3;

    /** @const O_FILE_REPLACE     Replace existing file */
        const O_FILE_REPLACE     = 4;

    /** @const O_FILE_TYPE        Define file type for creation */
        const O_FILE_TYPE        = 5;

    /** @const O_FILE_TEMP        Define file as temporary */
        const O_FILE_TEMP        = 6;

    /** @const O_FILE_CREATE_MODE The mode used for creating the file */
        const O_FILE_CREATE_MODE = 7;

    /** @const O_IMAGE_ALPHA      Enable alpha channel options */
        #const O_IMAGE_ALPHA     = 8;

    /** @const O_IMAGE_MIME       Set image mimetype for creating */
        const O_IMAGE_MIME       = 9;

    /** @const O_IMAGE_VIRTUAL    Make image virtual, dont save to disk */
        const O_IMAGE_VIRTUAL    = 10;

    /** @const O_IMAGE_FILTER_SIZE Set multiply filter resize */
        const O_IMAGE_FILTER_SIZE = 11;

    /** @const O_IMAGE_FILTER_SIZE_MODE Set multiply filter resize type */
        const O_IMAGE_FILTER_SIZE_MODE = 12;

    /** @const O_IMAGE_CLONE       Clone source image */
        const O_IMAGE_CLONE       = 13;

    /** @var array $optionDefaults Option default values */
        public static $optionDefaults = [
            0 => true,
            1 => true,
            2 => true,
            3 => 0777,
            4 => false,
            5 => null,
            6 => false,
            7 => 'w',
            8 => null,
            9 => null,
            10 => false,
            11 => true,
            12 => 'stretch',
            13 => true,
        ];

    /**
     * Get parameter option value or default
     *
     * @param integer $select
     * @param array   $options
     *
     * @throws \sqf\files\exception
     *
     * @return mixed
     */
        public static function getOption ($select,array $options=[]) {
            // Options is defined
            if (isset($options[$select])) {
                return $options[$select];
            }

            // Option default value
            if (static::$optionDefaults[$select]===null||isset(static::$optionDefaults[$select])) {
                return static::$optionDefaults[$select];
            } else {
                throw new exception(static::error(static::E_OPTION_DEFAULT,['option'=>$select]),static::E_OPTION_DEFAULT);
            }
        }

    /** @const E_UNKNOWN         Unknown error code */
        const E_UNKNOWN         = 0;

    /** @const E_PARAMETER_TYPE  Invalid parameter supplied */
        const E_PARAMETER_TYPE  = 1;

    /** @const E_OPTION_DEFAULT  Invalid option default */
        const E_OPTION_DEFAULT  = 2;

    /** @const E_CLASS_INDEX     Undefined class index */
        const E_CLASS_INDEX     = 3;

    /** @const E_PATH_INVALID    Invalid parameter path*/
        const E_PATH_INVALID    = 4;

    /** @const E_PATH_TYPE       Invalid path type */
        const E_PATH_TYPE       = 5;

    /** @const E_PATH_CREATE     Error creating path */
        const E_PATH_CREATE     = 6;

    /** @const E_PATH_WRITABLE   Path not writable */
        const E_PATH_WRITABLE   = 7;

    /** @const E_PATH_EXISTS     Path not found */
        const E_PATH_EXISTS     = 8;

    /** @const E_FILE_EXISTS     File not found */
        const E_FILE_EXISTS     = 8;

    /** @const E_FILE_REPLACE    File exists cannot replace */
        const E_FILE_REPLACE    = 9;

    /** @const E_FILE_TYPE       File type to create */
        const E_FILE_TYPE       = 10;

    /** @const E_FILE_CREATE     Error creating file */
        const E_FILE_CREATE     = 11;

    /** @const E_FILE_MOVE       Error moving file */
        const E_FILE_MOVE       = 12;

    /** @const E_FILE_COPY       Error copying file */
        const E_FILE_COPY       = 13;

    /** @const E_PROPERTY_ACCESS Property access error */
        const E_PROPERTY_ACCESS = 14;

    /** @const E_PROPERTY_VALUE  Property value error */
        const E_PROPERTY_VALUE  = 15;

    /** @const E_METHOD_ACCESS   Method access error */
        const E_METHOD_ACCESS   = 16;

    /** @const E_FILE_READ       File read error */
        const E_FILE_READ       = 17;

    /** @var array $errors Verbose error messsages */
        protected static $errors = [
             0 => 'Unknown error {code}, invalid or code not found',
             1 => 'Parameter {param} must be of type {type}',
             2 => 'Option {option} has no defined default value',
             3 => 'Class for index {index} not defined',
             4 => 'Parameter $path of type {pathtype} is invalid or empty',
             5 => 'Path type {type} is invalid',
             6 => 'Could not create directory {dirname}',
             7 => 'Path {path} is not writable',
             8 => 'Path {path} not found',
             9 => 'The file {file} already exists',
            10 => 'Could not detect type from option {option}, path {path} or content {content}',
            11 => 'Could not create new file at {path}',
            12 => 'Error moving file from {from} to {to}',
            13 => 'Error copying file from {from} to {to}',
            14 => 'Error accessing property {name}',
            15 => 'Property {name} value must be of type {type}',
            16 => 'Error accessing method {name}',
            17 => 'Error reading file {path}',
        ];

    /**
     * Get verbose error from code
     *
     * @param integer    $code
     * @param array|null $vars
     *
     * @throws \sqf\files\exception
     *
     * @return string
     */
        public static function error ($code,$vars=null) {
            // Throw exception if code can not be handled, this prevents throwing exceptions with unknown codes
            $code = intval($code);
            if (!isset(static::$errors[$code])) {
                throw new exception(static::error(static::E_UNKNOWN,['code'=>$code]),static::E_UNKNOWN);
            }

            // Handle any vars inside the error string
            $m=[];
            $v=[];
            if (is_array($vars)&&!empty($vars)) {

                // Prepare error variables
                if (!empty($vars)) {
                    foreach ($vars as $key => $value) {
                        if (is_string($value)||is_numeric($value)||is_bool($value)||(is_object($value)&&method_exists($value,'__toString'))) {
                            $m[] = "{{".trim($key)."}}";
                            $v[] = (string)$value;
                        }
                    }
                }
            }

            // Return verbose error string
            if (!empty($m)) {
                return preg_replace($m,$v,static::$errors[$code]);
            }
            return static::$errors[$code];
        }

    // End class
    }
