<?php
/**
 * Class sqf\files\base
 *
 * File base class
 *
 * @version 1.9.0
 * @author siux <me@siux.info>
 */
    namespace sqf\files;
    use sqf\files\exception;
    use sqf\files\handler as fsh;

    abstract class base {

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
     * @param array  $options
     *
     * @throws \sqf\files\exception
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
            if ($this->initRequired) {
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
            if ($this->temp) {
                fsh::delete($this->getFullPath(),true);
            }
        }

    /**
     * Create/replace actual file
     *
     * @param string $path
     * @param string $content
     * @param array  $options
     *
     * @return boolean
     */
        abstract protected function createFile ($path,$content=null,array $options=[]);

    /**
     * Set path info
     *
     * @param string  $path
     * @param array   $options
     * @param boolean $full
     *
     * @return array
     */
        protected function setPathInfo ($path,array $options=[],$full=true) {
            $info = ($full?fsh::info($path):pathinfo($path));
            if ($full) {
                $this->mimetype = $info['mimetype'];
                $this->encoding = $info['encoding'];
            }
            $this->dirname = $info['dirname'];
            $this->basename = $info['basename'];
            $this->filename = $info['filename'];
            $this->extension = $info['extension'];

            // Return info
            return $info;
        }

    /**
     * Handle replace option
     *
     * @param string $path
     * @param array  $options
     *
     * @throws \sqf\files\exception
     *
     * @return array
     */
        protected function optionReplace ($path,array $options=[]) {
            // Replace existing file
            if (file_exists($path)&&fsh::getOption(fsh::O_FILE_REPLACE,$options)===true) {
                fsh::delete($this->getFullPath(),true);
            }
        }

    /** @var boolean $initRequired Require init to be called */
        protected $initRequired = true;

    /**
     * Initialize
     *
     * @param string $path
     * @param string $content
     * @param array  $options
     *
     * @return void
     */
        abstract protected function initialize ($path,$content=null,array $options=[]);

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
            'wdsafemode' => true,
            'temp'       => true,
            'mimetype'   => true,
            'encoding'   => true,
            'dirname'    => true,
            'filename'   => true,
            'basename'   => true,
            'extension'  => true,
            'size'       => 'getSize',
            'content'    => 'changeContent',
            'resource'   => true,
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
            'wdsafemode' => 'is_bool',
            'temp'       => 'is_bool',
            'dirname'    => 'changePath',
            'filename'   => 'changePath',
            'basename'   => 'changePath',
            'extension'  => 'changePath',
            'content'    => 'changeContent',
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
                    $this->$name = $value;
                    return;
                } else {

                    // Set value by method or function
                    $target = $method;
                    if (is_string($method)&&method_exists($this,$method)) {
                        $target = [$this,$method];
                    }

                    // Call setter
                    $set = call_user_func($target,$name,$value,$this);

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
     * @param string $name
     * @param mixed  $value
     *
     * @throws \sqf\files\exception
     *
     * @return void
     */
        protected function changePath ($name,$value) {
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

    /**
     * Get file content string
     *
     * @param string $name
     * @param mixed  $value
     *
     * @return string
     */
        protected function changeContent ($name,$value) {
            if ($value!==null) {
                return file_put_contents($this->getFullPath(),$value);
            }
            return file_get_contents($this->getFullPath());
        }

    /** @var resource|null $resource Local resource */
        protected $resource = null;

    /**
     * Open file resource
     *
     * @return object|\sqf\files\file
     */
        abstract public function open ();

    /**
     * Write to file resource
     *
     * @param string $content
     *
     * @return object|\sqf\files\file
     */
        abstract public function write ($content=null);

    /**
     * Close file resource
     *
     * @return object|\sqf\files\file
     */
        abstract public function close ();

    /** @var boolean $wdsafemode Working directory safe mode */
        protected $wdsafemode = true;

    /**
     * Get full path
     *
     * @param boolean $wdsafe
     *
     * @return string
     */
        public function getFullPath ($wdsafe=null) {
            if (!is_bool($wdsafe)) {$wdsafe = $this->$wdsafemode;}
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
            return $this->getFullPath();
        }

    // End class
    }
