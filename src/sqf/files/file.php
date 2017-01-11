<?php
/**
 * Class sqf\files\file
 *
 * File base class
 *
 * @version 1.9.0
 * @author siux <me@siux.info>
 */
    namespace sqf\files;
    use sqf\files\exception;
    use sqf\files\handler as fsh;

    class file {

    /** @var string $mimetype  Mimetype */
        protected $mimetype  = null;

    /** @var string $encoding  File encoding */
        protected $encoding  = null;

    /** @var string $dirname   Directory name */
        protected $dirname   = null;

    /** @var string $basename  Base name */
        protected $basename  = null;

    /** @var string $filename  File name */
        protected $filename  = null;

    /** @var string $extension File extension */
        protected $extension = null;

    /** @var boolean $temp     Mark file as temporary */
        protected $temp      = false;

    /**
     * Constructor
     *
     * @param string $path
     * @param string $content
     *
     * @throws \sqf\files\exception
     *
     * @return object|\sqf\files\file
     */
        public function __construct ($path,$content=null,array $options=[]) {
            // Check path
            $exists = fsh::exists($path);
            if (!$exists||fsh::getOption(fsh::O_FILE_REPLACE,$options)===true) {
                if (!$this->createFile($path,$content,$options)) {
                    throw new exception(fsh::error(fsh::E_FILE_CREATE,['path'=>$path]),fsh::E_FILE_CREATE);
                }
            }

            // Set temp option
            if (fsh::getOption(fsh::O_FILE_TEMP,$options)===true) {
                $this->temp = true;
            }

            // Initialize class data
            if ($this->initRequired&&!$this->preventInit) {
                $this->initialize($path,$content,$options);
            }
        }

    /**
     * Destructor
     *
     * Closes the resource handler if required
     * and deletes the actual file of set as temp
     *
     * @return null
     */
        public function __destruct () {
            $this->close();
            $p = $this->getFullPath(true);
            if ($this->temp&&file_exists($p)) {
                unlink($p);
            }
        }

    /**
     * Create/replace actual file
     *
     * @param string $path
     * @param string $content
     * @param array  $options
     *
     * @throws \sqf\files\exception
     *
     * @return boolean
     */
        protected function createFile ($path,$content=null,array $options=[]) {
            // Set path relevant info
            $this->setPathInfo($path,$options);

            // Prepare resource
            $this->openResource(fsh::getOption(fsh::O_FILE_CREATE_MODE,$options));

            // Failed resource
            if (!isset($this->resource)) {return false;}

            // Write content
            $this->writeResource($content);

            // Close resource
            $this->closeResource();

            return true;
        }

    /**
     * Set path info
     *
     * @param string $path
     * @param array  $options
     *
     * @return array
     */
        protected function setPathInfo ($path,array $options=[]) {
            $info = pathinfo($path);
            $this->dirname = $info['dirname'];
            $this->basename = $info['basename'];
            $this->filename = $info['filename'];
            $this->extension = $info['extension'];

            // Replace existing file
            if (file_exists($path)&&fsh::getOption(fsh::O_FILE_REPLACE,$options)===true) {
                fsh::delete($this->getFullPath());
            }

            // Return info
            return $info;
        }

    /** @var boolean $initRequired Require init to be called */
        protected $initRequired = true;

    /** @var boolean $preventInit Require init to be called */
        protected $preventInit = false;

    /**
     * Initialize
     *
     * @param string $path
     * @param string $content
     * @param array  $options
     *
     * @return void
     */
        protected function initialize ($path,$content,$options) {
            $info = fsh::info($path);
            $this->mimetype = $info['mimetype'];
            $this->encoding = $info['encoding'];
            $this->dirname = $info['dirname'];
            $this->basename = $info['basename'];
            $this->filename = $info['filename'];
            $this->extension = $info['extension'];
        }

    /**
     * Clear stat cache
     *
     * @return void
     */
        public function clearstat () {
            clearstatcache(true,$this->getFullPath());
        }

    /** @var array $gettable Handler properties for magic __get */
        protected $gettable = [
            'temp'      => true,
            'mimetype'  => true,
            'encoding'  => true,
            'dirname'   => true,
            'filename'  => true,
            'basename'  => true,
            'extension' => true,
            'size'      => 'getSize'
        ];

    /**
     * Magic __get method
     *
     * @param string $name
     *
     * @throws \sqf\files\exception
     *
     * @return mixed
     */
        public function __get ($name) {
            // Is gettable value
            if (isset($this->gettable[$name])) {

                // Get getter method
                $method = $this->gettable[$name];

                // Unfiltered getter
                if ($method===true) {
                    return $this->$name;
                } else {

                    // Get value by method or function
                    $target = $method;
                    if (is_string($method)&&method_exists($this,$method)) {
                        $target = [$this,$method];
                    }

                    // Call getter
                    return call_user_func($target,$name,$this);
                }
            }

            // Property not gettable
            throw new exception(fsh::error(fsh::E_PROPERTY_ACCESS,['name'=>$name]),fsh::E_PROPERTY_ACCESS);
        }

    /**
     * Get file size in bytes
     * used for returning size property value
     *
     * @return integer
     */
        public function getSize () {
            $this->clearstat();
            return intval(filesize($this->getFullPath()));
        }

    /** @var array $settable Handler properties for magic __set */
        protected $settable = [
            'temp'      => 'is_bool',
            'dirname'   => 'changePath',
            'filename'  => 'changePath',
            'basename'  => 'changePath',
            'extension' => 'changePath',
        ];

    /**
     * Magic __set method
     *
     * @param string $name
     * @param mixed  $value
     *
     * @throws \sqf\files\exception
     *
     * @return void
     */
        public function __set ($name,$value) {
            // Is settable value
            if (isset($this->settable[$name])) {

                // Skip further execution, value has not changed
                if ($this->$name===$value) {return;}

                // Get setter method
                $method = $this->settable[$name];

                // Unsafe replace value
                if ($method===true) {
                    return $this->$name = $value;
                } else {

                    // Set value by method or function
                    $target = $method;
                    if (is_string($method)&&method_exists($this,$method)) {
                        $target = [$this,$method];
                    }

                    // Call setter
                    $set = call_user_func($target,$value,$name,$this);

                    // Setter used as validator
                    if (is_bool($set)&&$set) {
                        $this->$name = $value;
                    }

                    // End
                    return;
                }
            }

            // Property not settable
            throw new exception(fsh::error(fsh::E_PROPERTY_ACCESS,['name'=>$name]),fsh::E_PROPERTY_ACCESS);
        }

    /**
     * Change file path
     * used for setting path related properties
     *
     * @param mixed  $value
     * @param string $name
     *
     * @throws \sqf\files\exception
     *
     * @return void
     */
        protected function changePath ($value,$name) {
            // Close resource before moving
            $this->close();

            // Current path
            $current = $this->getFullPath();
            $ext = $fname = $value;

            switch ($name) {

                // Change directory
                case 'dirname':
                    if (!is_dir($value)) {
                        throw new exception(fsh::error(fsh::E_PROPERTY_VALUE,['name'=>$name,'type'=>'string and a valid local path']),fsh::E_PROPERTY_VALUE);
                    }
                    $this->dirname = $value;
                    break;

                // Change full name
                case 'basename':
                    $info = pathinfo($value);
                    $ext = $info['extension'];
                    $fname = $info['filename'];

                // Validate extension
                case 'extension':
                    if (!is_string($ext)||(strlen($ext)&&!ctype_alnum($ext))) {
                        throw new exception(fsh::error(fsh::E_PROPERTY_VALUE,['name'=>$name,'type'=>'string containing only alphanumeric characters']),fsh::E_PROPERTY_VALUE);
                    }
                    $this->extension = $ext;
                    if ($name=='extension') {break;}

                // Validate filename
                case 'filename':
                    if (!is_string($fname)||!strlen($fname)) {
                        throw new exception(fsh::error(fsh::E_PROPERTY_VALUE,['name'=>$name,'type'=>'string']),fsh::E_PROPERTY_VALUE);
                    }
                    $this->filename = $fname;
                    if ($name=='filename') {break;}

                default:
                    // Set basename after validation
                    if ($name=='basename') {
                        $this->basename = $value;
                    } else if ($name=='extension'||$name=='filename') {
                        $this->basename = $this->filename.(is_string($this->extension)&&strlen($this->extension)?'.'.$this->extension:'');
                    }
            }

            // Apply path changes
            fsh::rename($current,$this->getFullPath());
        }

    /** @var resource|null $resource Local resource */
        protected $resource = null;

    /**
     * Open local resource
     *
     * @param string $mode
     *
     * @return boolean
     */
        protected function openResource ($mode='w') {
            $this->closeResource();
            $r = fopen($this->getFullPath(),$mode);
            if ($r) {
                $this->resource = $r;
            }
            return ($this->resource?true:false);
        }

    /**
     * Write to local resource
     *
     * @param string $content
     *
     * @return boolean
     */
        protected function writeResource ($content) {
            if (isset($this->resource)) {
                return fwrite($this->resource,$content);
            }
            return false;
        }

    /**
     * Close local resource
     *
     * @return boolean
     */
        protected function closeResource () {
            if (isset($this->resource)) {
                $r = fclose($this->resource);
                if ($r) {$this->resource = null;}
                return $r;
            }
            return false;
        }

    /**
     * Open file resource
     *
     * @param string $mode
     *
     * @return object|\sqf\files\file
     */
        public function open ($mode='w') {
            if (!isset($this->resource)) {
                $this->openResource($mode);
            }
            return $this;
        }

    /**
     * Write to file resource
     *
     * @param string $content
     *
     * @return object|\sqf\files\file
     */
        public function write ($content=null) {
            if (isset($this->resource)) {
                $this->writeResource($content);
            }
            return $this;
        }

    /**
     * Close file resource
     *
     * @return object|\sqf\files\file
     */
        public function close () {
            if (isset($this->resource)) {
                $this->closeResource();
                $this->resource = null;
            }
            return $this;
        }

    /**
     * Put file content
     *
     * @param string $content
     *
     * @return object|\sqf\files\file
     */
        public function put ($content) {
            return $this->open('w')->write($content)->close();
        }

    /**
     * Append to file content
     *
     * @param string $content
     *
     * @return object|\sqf\files\file
     */
        public function append ($content) {
            return $this->open('a')->write($content)->close();
        }

    /**
     * Get file content string
     *
     * @return string
     */
        public function content () {
            return file_get_contents($this->getFullPath());
        }

    /**
     * Get full path
     *
     * @param boolean $wdsafe
     *
     * @return string
     */
        public function getFullPath ($wdsafe=false) {
            $p = (strlen($this->dirname)&&$this->dirname!='.'?$this->dirname.DIRECTORY_SEPARATOR:'').$this->basename;
            if ($wdsafe) {
                return fsh::wdpath($p);
            }
            return $p;
        }

    /**
     * Convert to string
     *
     * @return string
     */
        public function __toString () {
            return $this->getFullPath(true);
        }

    // End class
    }
