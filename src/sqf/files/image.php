<?php
/**
 * Class sqf\files\image
 *
 * Image file class
 *
 * @version 2.0.0
 * @author siux <me@siux.info>
 */
    namespace sqf\files;
    use sqf\files\exception;
    use sqf\files\handler as fsh;
    use sqf\files\file;

    class image extends file {

    /** @var boolean $virtual Virtual image mode */
        protected $virtual = false;

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
            // Add gettable properties
            $this->gettable['virtual']   = true;
            $this->gettable['quality']   = true;
            $this->gettable['pngFilter'] = true;

            // Add settable properties
            $this->settable['virtual']   = 'changeVirtual';
            $this->settable['quality']   = 'changeQuality';
            $this->settable['pngFilter'] = 'changePNGFilter';

            // Prevent init for virtual images not saved to disk
            if (fsh::getOption(fsh::O_IMAGE_VIRTUAL,$options)===true) {
                $this->virtual = true;
                $this->initRequired = false;
            }

            // Run parent constructor
            parent::__construct($path,$content,$options);
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
            $this->setPathInfo($path);

            // Get mimetype
            $this->setMimetype($options);

            // Get alpha setting
            /*$alpha = fsh::getOption(fsh::O_IMAGE_ALPHA,$options);
            if (!isset($alpha)) {
                $alpha = (in_array($this->extension,['png','gif'])?true:false);
            }*/

            // Create image from $content
            switch (($type = gettype($content))) {
                case 'string':
                    if (!$this->createImageFrompath($content,$options)) {
                        return false;
                    }
                    break;
                case 'resource':
                    if (!$this->createImageFromResource($content,$options)) {
                        return false;
                    }
                    break;
                case 'array':
                    if (!$this->createImageFromArray($content,$options)) {
                        return false;
                    }
                    break;

                default:
                    throw new exception(fsh::error(fsh::E_PARAMETER_TYPE,['param'=>'$content','type'=>'string|array|resource']),fsh::E_PARAMETER_TYPE);
            }

            return true;
        }

    /**
     * Set mimetype from extension and options
     *
     * @param array $options
     *
     * @throws \sqf\files\exception
     *
     * @return void
     */
        protected function setMimetype (array $options=[]) {
            $emime = static::imageExt2mime($this->extension);
            $mime = fsh::getOption(fsh::O_IMAGE_MIME,$options);

            // Set mimetype
            if ($emime||$mime) {
                $this->mimetype = ($mime?$mime:$emime);
            }

            // Validate mimetype
            if (!$this->mimetype||!in_array($this->mimetype,array_unique(static::$imageExt2mime))) {
                throw new exception(fsh::error(fsh::E_PARAMETER_TYPE,['param'=>'O_IMAGE_MIME','type'=>'string image/png|jpeg|gif']),fsh::E_PARAMETER_TYPE);
            }
        }

    /**
     * Create image from array
     *
     * @param string $content
     * @param array  $options
     *
     * @throws \sqf\files\exception
     *
     * @return boolean
     */
        protected function createImageFromArray ($content,array $options=[]) {
            // Validate width
            if (!isset($content['width'])||!is_int($content['width'])||!$content['width']) {
                throw new exception(fsh::error(fsh::E_PARAMETER_TYPE,['param'=>'$content[\'width\']','type'=>'integer and larger than 0']),fsh::E_PARAMETER_TYPE);
            }

            // Validate height
            if (!isset($content['height'])||!is_int($content['height'])||!$content['height']) {
                throw new exception(fsh::error(fsh::E_PARAMETER_TYPE,['param'=>'$content[\'height\']','type'=>'integer and larger than 0']),fsh::E_PARAMETER_TYPE);
            }

            // Validate color
            if (isset($content['color'])&&!($content['color'] = static::validateColor($content['color']))) {
                throw new exception(fsh::error(fsh::E_PARAMETER_TYPE,['param'=>'$content[\'color\']','type'=>'array containing at least 3 integers from 0-255']),fsh::E_PARAMETER_TYPE);
            }

            // Create resource
            $r = imagecreatetruecolor($content['width'],$content['height']);
            if ($r) {
                $this->resource = $r;
            }

            // Failed resource
            if (!isset($this->resource)) {return false;}

            // Fill color
            if (isset($content['color'])) {
                imagefill($this->resource,0,0,static::getColor($this->resource,$content['color']));
            }

            // Make image virtual
            if (!$this->virtual) {
                $this->write(null,$options);
            }
            return true;
        }

    /** @var integer $quality Image quality used for png and jpeg output */
        protected $quality = null;

    /** @var integer $pngFilter PNG filter setting for output */
        protected $pngFilter = PNG_NO_FILTER;

    /** @var array $imageExt2mime Image extensions to mimetype */
        public static $imageExt2mime = [
            'png'  => 'image/png',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif'  => 'image/gif',
        ];

    /**
     * Get mimetype from extension
     *
     * @param string $ext
     *
     * @return string|null
     */
        public static function imageExt2mime ($ext) {
            if (is_string($ext)&&strlen($ext)&&isset(static::$imageExt2mime[$ext])) {
                return static::$imageExt2mime[$ext];
            }
            return null;
        }

    /**
     * Open local resource
     *
     * @param string $target
     * @param array  $options
     *
     * @return boolean
     */
        protected function openResource ($target=null,array $options=[]) {
            // Set new path info and option
            if (is_string($target)) {
                setPathInfo($target,$options);
                setMimetype($options);
            }

            // Get reader
            $mime = explode('/',$this->mimetype);
            $fn = 'imagecreatefrom'.$mime[1];

            // Check reader
            if (!function_exists($fn)) {
                throw new exception(fsh::error(fsh::E_METHOD_ACCESS,['name'=>$fn]),fsh::E_METHOD_ACCESS);
            }

            // Make params
            $params = [$this->getFullPath(true)];

            // Call reader
            $r = call_user_func_array($fn,$params);
            if (!$r) {
                throw new exception(fsh::error(fsh::E_FILE_READ,['path'=>$params[1]]),fsh::E_FILE_READ);
            }
            $this->resource = $r;
            return true;
        }

    /**
     * Write local resource
     *
     * @param string $target
     * @param array  $options
     *
     * @return boolean
     */
        protected function writeResource ($target=null,array $options=[]) {
            // Set new path info and option
            if (is_string($target)) {
                setPathInfo($target,$options);
                setMimetype($options);
            }

            // Get writer
            $mime = explode('/',$this->mimetype);
            $fn = 'image'.$mime[1];

            // Check writer
            if (!function_exists($fn)) {
                throw new exception(fsh::error(fsh::E_METHOD_ACCESS,['name'=>$fn]),fsh::E_METHOD_ACCESS);
            }

            // Make params
            $params = [$this->resource,$this->getFullPath(true)];
            switch ($mime[1]) {
                case 'gif':
                    break;
                case 'jpeg':
                    $params[] = $this->quality;
                    break;
                case 'png':
                    $params[] = $this->pngFilter;
            }

            // Call writer
            if (!call_user_func_array($fn,$params)) {
                throw new exception(fsh::error(fsh::E_FILE_CREATE,['path'=>$params[1]]),fsh::E_FILE_CREATE);
            }
            return true;
        }

    /**
     * Close local resource
     *
     * @return boolean
     */
        protected function closeResource () {
            if (isset($this->resource)) {
                $r = imagedestroy($this->resource);
                if ($r) {$this->resource = null;}
                return $r;
            }
            return false;
        }

    /**
     * Open file resource
     *
     * @param string $target
     * @param array  $options
     *
     * @return object|\sqf\files\file
     */
        public function open ($target=null,array $options=[]) {
            if (!isset($this->resource)) {
                $this->openResource($target,$options);
            }
            return $this;
        }

    /**
     * Write resource to file
     *
     * @param string $target
     * @param array  $options
     *
     * @return object|\sqf\files\file
     */
        public function write ($target=null,array $options=[]) {
            if (isset($this->resource)) {
                $this->writeResource($target,$options);
            }
            return $this;
        }

    /**
     * Get color index for given resource !!! create object related alias
     *
     * @param resource $resource
     * @param array    $color
     * @param boolean  $alpha
     *
     * @return integer
     */
        public static function getColor ($resource,array $color,$alpha=true) {
            $fn = 'imagecolorallocate'.($alpha?'alpha':'');
            array_unshift($color,$resource);
            return call_user_func_array($fn,$color);
        }

    /**
     * Validate color input
     *
     * @param array   $color
     * @param boolean $alpha
     *
     * @return array|false
     */
        public static function validateColor ($color,$alpha=true) {
            $parsed = [];
            if (is_array($color)&&count($color)>2) {
                if ($alpha&&count($color)==3) {$color[] = 0;}
                $i = 0;
                foreach ($color as $col) {
                    if ($i<3) {
                        if (!is_int($col)||$col>255) {
                            return false;
                        } else {
                            $parsed[] = $col;
                        }
                    } elseif ($i==3&&$alpha) {
                        if (!is_int($col)||$col>127) {
                            return false;
                        } else {
                            $parsed[] = $col;
                        }
                    } else {
                        break;
                    }
                    $i++;
                }
            } else {
                return false;
            }
            return $parsed;
        }

    /** @var array $first_param_resource GD functions that require the first param to be the resource */
        protected static $first_param_resource = [
            'imagesx',
            'imagesy',
            'imagedestroy',
            'imagesavealpha',
            'imagealphablending',
            'imagecopy',
            'imagecopymerge',
            'imagecolorallocate',
            'imagecolorallocatealpha',
            'imagerotate',
            'imagecolorat',
            'imagecrop',
            'imagecropauto',
            'imagecolorsforindex',
            'imagesetpixel',
        ];

    /** @var array $fn_replace_resource GD functions that replace the current resource */
        protected static $fn_replace_resource = [
            'imagerotate',
            'imagecrop',
            'imagecropauto',
        ];

    /**
     * Call GD functions and synthesize parameters
     *
     * @param string $name
     * @param array  $params
     *
     * @throws \sqf\files\exception
     *
     * @return mixed
     */
        public function __call ($name,$params) {
            // Match gd_ prefixed calls
            if (substr($name,0,3)=='gd_') {

                // Function exists
                if (function_exists(($gd_fn = substr($name,3)))) {

                    // Modify params array
                    if (in_array($gd_fn,static::$first_param_resource)) {
                        array_unshift($params,$this->resource);
                    }

                    // Custom param processing
                    if (method_exists($this,($method = 'params_'.$gd_fn))) {
                        call_user_func_array([$this,$method],[&$params]);
                    }

                    // Run function
                    if (in_array($gd_fn,static::$fn_replace_resource)) {
                        $this->resource = call_user_func_array($gd_fn,$params);
                        return $this;
                    } else {
                        return call_user_func_array($gd_fn,$params);
                    }
                }
            }
            throw new exception(fsh::error(fsh::E_METHOD_ACCESS,['name'=>$name]),fsh::E_METHOD_ACCESS);
        }

    // End class
    }
