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
    use sqf\files\base;

    class image extends base {

    /** @var boolean $virtual Virtual image mode */
        protected $virtual = false;

    /** @var integer $quality Image quality used for png and jpeg output */
        protected $quality = null;

    /** @var integer $pngFilter PNG filter setting for output */
        protected $pngFilter = PNG_NO_FILTER;

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
            // Add gettable properties
            $this->gettable['virtual']   = true;
            $this->gettable['quality']   = true;
            $this->gettable['pngFilter'] = true;

            // Add settable properties
            $this->settable['virtual']   = 'changeVirtual';
            $this->settable['quality']   = 'changeQuality';
            $this->settable['pngFilter'] = 'changePNGFilter';
            $this->settable['resource']  = 'changeResource';

            // Prevent init for virtual images not saved to disk
            if (fsh::getOption(fsh::O_IMAGE_VIRTUAL,$options)===true) {
                $this->virtual = true;
                $this->initRequired = false;
            }

            // Run parent constructor
            parent::__construct($path,$content,$options);
        }

    /**
     * Change virtual option
     *
     * @param string  $name
     * @param boolean $value
     *
     * @throws \sqf\files\exception
     *
     * @return void
     */
        protected function changeVirtual ($name,$value) {
            // Value type
            if (!is_bool($value)&&!is_int($value)) {
                throw new exception(fsh::error(fsh::E_PROPERTY_VALUE,['name'=>$name,'type'=>'boolean|integer']),fsh::E_PROPERTY_VALUE);
            }

            // Dont allow non virtual to virtual
            if (!$this->virtual&&$value) {
                throw new exception(fsh::error(fsh::E_PROPERTY_VALUE,['name'=>$name,'type'=>'boolean|integer false cannot make file virtual']),fsh::E_PROPERTY_VALUE);
            }

            // Write image to disk
            $this->virtual = $value;
            $this->write();
        }

    /**
     * Change image quality
     *
     * @param string $name
     * @param mixed  $value
     *
     * @throws \sqf\files\exception
     *
     * @return void
     */
        protected function changeQuality ($name,$value) {
            // Check value
            if (!is_int($value)) {
                throw new exception(fsh::error(fsh::E_PROPERTY_VALUE,['name'=>$name,'type'=>'integer']),fsh::E_PROPERTY_VALUE);
            }

            // Validate for mimetypes
            switch ($this->mimetype) {
                case 'image/png':
                    // Check png 0-9 compression
                    if ($value<0&&$value>9) {
                        throw new exception(fsh::error(fsh::E_PROPERTY_VALUE,['name'=>$name,'type'=>'integer png quality 0-9']),fsh::E_PROPERTY_VALUE);
                    }
                    $this->quality = $value;
                    break;
                case 'image/jpeg':
                    // Check jpeg 0-100 quality
                    if ($value<0&&$value>100) {
                        throw new exception(fsh::error(fsh::E_PROPERTY_VALUE,['name'=>$name,'type'=>'integer jpeg quality 0-100']),fsh::E_PROPERTY_VALUE);
                    }
                    $this->quality = $value;
                default:
                    throw new exception(fsh::error(fsh::E_PROPERTY_VALUE,['name'=>'mimetype','type'=>'image/jpeg|png']),fsh::E_PROPERTY_VALUE);
            }
        }


    /**
     * Change png filter
     *
     * @param string $name
     * @param mixed  $value
     *
     * @throws \sqf\files\exception
     *
     * @return void
     */
        protected function changePNGFilter ($name,$value) {
            // Check for valid constant
            $v = [PNG_NO_FILTER,PNG_FILTER_NONE,PNG_FILTER_SUB,PNG_FILTER_UP,PNG_FILTER_AVG,PNG_FILTER_PAETH,PNG_ALL_FILTERS];
            if (!in_array($value,$v)) {
                throw new exception(fsh::error(fsh::E_PROPERTY_VALUE,['name'=>$name,'type'=>'PNG_FILTER_ constant']),fsh::E_PROPERTY_VALUE);
            }

            // Set value
            $this->pngFilter = $value;
        }

    /**
     * Change image resource
     *
     * @param string $name
     * @param mixed  $value
     *
     * @throws \sqf\files\exception
     *
     * @return void
     */
        protected function changeResource ($name,$value) {
            // Validate and replace local resource
            $this->createImageFromResource($value);
        }

    /**
     * Create/replace actual file
     *
     * @param string $path
     * @param mixed  $content
     * @param array  $options
     *
     * @throws \sqf\files\exception
     *
     * @return boolean
     */
        protected function createFile ($path,$content=null,array $options=[]) {
            // Set path relevant info
            $this->setPathInfo($path,$options,false);

            // Replace
            $this->optionReplace($path,$options);

            // Get mimetype
            $this->setMimetype($options);

            // Create image from $content
            switch (($type = gettype($content))) {
                case 'string':
                    if (!$this->createImageFromPath($content,$options)) {
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

            // Write image if not virtual
            if (!$this->virtual) {
                $this->write(null,$options);
            }

            return true;
        }

    /**
     * Initialize
     *
     * @param string $path
     * @param string $content
     * @param array  $options
     *
     * @return void
     */
        protected function initialize ($path,$content=null,array $options=[]) {
            $this->setPathInfo($path,$options,true);
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
     * Create image from path
     *
     * @param string $content
     * @param array  $options
     *
     * @throws \sqf\files\exception
     *
     * @return boolean
     */
        protected function createImageFromPath ($content,array $options=[]) {
            // Validate path
            fsh::exists($content,true);

            // Get file
            $file = fsh::open($content,$options);

            // Invalid file type
            if (!fsh::isClass($file,'image')) {
                throw new exception(fsh::error(fsh::E_PARAMETER_TYPE,['param'=>'$content','type'=>'image/jpeg|png|gif']),fsh::E_PARAMETER_TYPE);
            }

            // Set resource
            $r = imagecreatetruecolor(($w = $file->gd_imagesx()),($h = $file->gd_imagesy()));
            if ($r) {
                $this->resource = $r;
            }

            // Failed resource
            if (!isset($this->resource)) {return false;}

            // Clone image
            if (!$this->gd_imagecopy($file->resource,0,0,0,0,$w,$h)) {
                throw new exception(fsh::error(fsh::E_FILE_COPY,['from'=>$content,'to'=>$this->getFullPath()]),fsh::E_FILE_COPY);
            }

            // Destroy source
            $file = null;
            return true;
        }

    /**
     * Create image from resource
     *
     * @param resource $content
     * @param array    $options
     *
     * @throws \sqf\files\exception
     *
     * @return boolean
     */
        
        protected function createImageFromResource ($content,array $options=[]) {
            // Validate resource
            if (!is_resource($content)||get_resource_type($content)!='gd') {
                throw new exception(fsh::error(fsh::E_PARAMETER_TYPE,['param'=>'$content','type'=>'gd resource']),fsh::E_PARAMETER_TYPE);
            }

            // Clone
            if (fsh::getOption(fsh::O_IMAGE_CLONE,$options)===true) {
                // Set resource
                $r = imagecreatetruecolor(($w = imagesx($content)),($h = imagesy($content)));
                if ($r) {
                    $this->resource = $r;
                }

                // Failed resource
                if (!isset($this->resource)) {return false;}

                // Clone image
                if (!$this->gd_imagecopy($content,0,0,0,0,$w,$h)) {
                    throw new exception(fsh::error(fsh::E_FILE_COPY,['from'=>'resource #'.intval($content),'to'=>$this->getFullPath()]),fsh::E_FILE_COPY);
                }
            } else {
                // Use supplied
                $this->resource = $content;
            }

            return true;
        }

    /**
     * Create image from array
     *
     * @param array $content
     * @param array $options
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
                $this->gd_imagefill(0,0,static::getColor($this->resource,$content['color']));
            }
            return true;
        }

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
     * @throws \sqf\files\exception
     *
     * @return boolean
     */
        protected function openResource ($target=null,array $options=[]) {
            // Set new path info and option
            if (is_string($target)) {
                $this->setPathInfo($target,$options);
                $this->setMimetype($options);
            }

            // Get reader
            $mime = explode('/',$this->mimetype);
            $fn = 'imagecreatefrom'.$mime[1];

            // Check reader
            if (!function_exists($fn)) {
                throw new exception(fsh::error(fsh::E_METHOD_ACCESS,['name'=>$fn]),fsh::E_METHOD_ACCESS);
            }

            // Make params
            $params = [$this->getFullPath()];

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
     * @throws \sqf\files\exception
     *
     * @return boolean
     */
        protected function writeResource ($target=null,array $options=[]) {
            // Set new path info and option
            if (is_string($target)) {
                $this->setPathInfo($target,$options);
                $this->setMimetype($options);
            }

            // Get writer
            $mime = explode('/',$this->mimetype);
            $fn = 'image'.$mime[1];

            // Check writer
            if (!function_exists($fn)) {
                throw new exception(fsh::error(fsh::E_METHOD_ACCESS,['name'=>$fn]),fsh::E_METHOD_ACCESS);
            }

            // Make params
            $params = [$this->resource,$this->getFullPath()];
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
     * @return \sqf\files\base
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
     * @return \sqf\files\base
     */
        public function write ($target=null,array $options=[]) {
            if (isset($this->resource)) {
                $this->writeResource($target,$options);
            }
            return $this;
        }

    /**
     * Close file resource
     *
     * @return \sqf\files\base
     */
        public function close () {
            if (isset($this->resource)) {
                $this->closeResource();
                $this->resource = null;
            }
            return $this;
        }

    /**
     * Calculate orientation from width and height
     *
     * @param integer $w
     * @param integer $h
     *
     * @return integer
     */
        public static function calc_orientation ($w,$h) {
            return ($w==$h?0:($w>$h?-1:1));
        }

    /**
     * Calculate relative size
     *
     * @param integer $src_val
     * @param integer $src_opposite
     * @param integer $target_val
     * @param integer $target_opposite
     *
     * @return integer
     */
        public static function calc_relative_size ($src_val,$src_opposite,$target_val,$target_opposite) {
            if (!$target_opposite) {return $target_val;}
            return round($src_val/($src_opposite/$target_opposite));
        }

    /**
     * Calculate resize dimensions
     * stretch: forces the size, ignores aspect ratio
     * max: sizes into the given box and maintains aspect ratio
     * min: sizes to a minimum of the given box and maintains aspect ratio
     *
     * @param integer $w
     * @param integer $h
     * @param integer $ow
     * @param integer $oh
     * @param string  $mode
     * @param boolean $enlarge
     *
     * @return array
     */
        public static function calc_resize ($w=0,$h=0,$ow=0,$oh=0,$mode='max',$enlarge=false) {
            $w = intval($w);
            $h = intval($h);
            if ($w==0&&$h==0) {return [$ow,$oh];}

            // Stretch mode
            if ($mode=='stretch') {
                return [($w?$w:$h),($h?$h:$w)];
            }

            // Min and Max mode
            $rw = static::calc_relative_size($ow,$oh,$w,$h);
            $rh = static::calc_relative_size($oh,$ow,$h,$w);
            $case = (!$h?'width':(!$w?'height':'both'));
            switch ($case) {
                case 'width':$rw = $w;break;
                case 'height':$rh = $h;break;
                default:
                    if (($mode=='max'&&$rw<$w)||($mode=='min'&&$rw>$w)) {
                        $rh = $h;
                    } else {
                        $rw = $w;
                    }

            }
            if (!$enlarge) {
                if ($rw>$ow||$rh>$oh) {
                    $rw = $ow;
                    $rh = $oh;
                }
            }
            return [$rw,$rh];
        }

    /**
     * Resize image
     *
     * @param integer $width
     * @param integer $height
     * @param array   $options
     *
     * @throws \sqf\files\exception
     *
     * @return boolean
     */
        public function resize ($width=null,$height=null,array $options=[]) {
            // Check width
            if (isset($width)&&!is_int($width)) {
                throw new exception(fsh::error(fsh::E_PARAMETER_TYPE,['param'=>'$width','type'=>'integer']),fsh::E_PARAMETER_TYPE);
            }

            // Check height
            if (isset($height)&&!is_int($height)) {
                throw new exception(fsh::error(fsh::E_PARAMETER_TYPE,['param'=>'$height','type'=>'integer']),fsh::E_PARAMETER_TYPE);
            }

            // Requires at least one sizing value larger than 0
            if (!(isset($width)&&isset($width))||(!$width&&!$height)) {
                throw new exception(fsh::error(fsh::E_PARAMETER_TYPE,['param'=>'$width or $height','type'=>'integer and larger than 0']),fsh::E_PARAMETER_TYPE);
            }

            // Default params
            if (!isset($width)) {$width = 0;}
            if (!isset($height)) {$height = 0;}

            // Get mode !!! mode to constant option values
            $modes = ['stretch','min','max'];
            $mode = fsh::getOption(fsh::O_IMAGE_RESIZE_MODE,$options);

            // Get enlarge
            $enlarge = fsh::getOption(fsh::O_IMAGE_RESIZE_ENLARGE,$options);

            // Get resize transparent color
            $alpha = static::validateColor(fsh::getOption(fsh::O_IMAGE_RESIZE_ALPHA,$options));

            // Get actual size
            $ow = $this->gd_imagesx();
            $oh = $this->gd_imagesy();

            // Get new size
            $new_size = static::calc_resize($width,$height,$ow,$oh,$mode,$enlarge);

            // Create resized
            $resized = imagecreatetruecolor($new_size[0],$new_size[1]);

            // Set alpha color index
            if ($alpha&&($alpha = static::getColor($this->resource,$alpha))>0) {
                imagecolortransparent($resized,$alpha);
                imagealphablending($resized,false);
                imagesavealpha($resized,true);
            }

            // Copy to new source
            if (!imagecopyresampled($resized,$this->resource,0,0,0,0,$new_size[0],$new_size[1],$ow,$oh)) {
                imagedestroy($resized);
                throw new exception(fsh::error(fsh::E_FILE_COPY,['from'=>'local resource','to'=>'local resource']),fsh::E_FILE_COPY);
            }

            // Unset and replace local resource
            imagedestroy($this->resource);
            $this->resource = $resized;

            return true;
        }

    /**
     * Crop by color
     */
        public function crop ($color=NULL) {
            // Color input
            if (isset($color)) {
                // String colors
                if (is_string($color)) {
                    switch ($color) {
                        case 'white':$color = array(255, 255, 255, 0);break;
                        case 'transparent':$color = array(0, 0, 0, 127);break;
                        default:trigger_error(self::e_invalid_image_color,E_USER_WARNING);return;
                    }
                }
                // Validate color
                if (!($color = $this->validateColor($color))) {trigger_error(self::e_invalid_image_color,E_USER_WARNING);}
                // Default color
            } else {$color = array(255,255,255,0);}
            // Limits
            $px = 0;
            $reference = $this->getColor($this->resource,$color);
            $width = $this->gd_imagesx();
            $height = $this->gd_imagesy();
            $top = 0;
            $left = 0;
            $right = 0;
            $bottom = 0;
            // Top
            for ($y=0;$y<$height;$y++) {
                for ($x=0;$x<$width;$x++) {
                    $px++;
                    if ($this->gd_imagecolorat($x,$y)!=$reference) {
                        $top = $y;
                        $left = $x;
                        break 2;
                    }
                }
            }
            // Bottom
            for ($y=($height-1);$y>$top;$y--) {
                for ($x=0;$x<$width;$x++) {
                    $px++;
                    if ($this->gd_imagecolorat($x,$y)!=$reference) {
                        $bottom = $y;
                        break 2;
                    }
                }
            }
            // Left
            for ($x=0;$x<$left;$x++) {
                for ($y=$top;$y<$bottom;$y++) {
                    $px++;
                    if ($this->gd_imagecolorat($x,$y)!=$reference) {
                        $left = $x;
                        break 2;
                    }
                }
            }
            // Right
            for ($x=($width-1);$x>$left;$x--) {
                for ($y=$top;$y<$bottom;$y++) {
                    $px++;
                    if ($this->gd_imagecolorat($x,$y)!=$reference) {
                        $right = $x;
                        break 2;
                    }
                }
            }
            // Replace source
            $new = imagecreatetruecolor($right-$left,$bottom-$top);
            imagealphablending($new,true);
            imagesavealpha($new,true);
            imagefill($new,0,0,self::getColor($new,array(0,0,0,127)));
            imagecopy($new,$this->resource(),0,0,$left,$top,$right-$left,$bottom-$top);
            $this->resource = $new;
            if ($this->debug_mode) {
                return array(
                    'checked'=>$px,
                    'original'=>($width*$height),
                    'cropped'=>(imagesx($new)*imagesy($new)),
                    'efficiency'=>((($width*$height)-($px+(imagesx($new)*imagesy($new))))/($width*$height))*100
                );
            }
        }

    /**
     * Multiply image with image
     *
     * @param string|resource|\sqf\file\base $with
     * @param array $options
     *
     * @throws \sqf\files\exception
     *
     * @return boolean
     */
        function multiply ($with,array $options=[]) {
            // Open if not already
            if (!isset($this->resource)) {
                $this->openResource(null,$options);
            }

            // Get file path
            if (is_string($with)) {
                $with = fsh::open($with);
            }

            // Get file resource
            if (is_resource($with)&&get_resource_type($with)=='gd') {
                //$with = fsh:create();
            }

            // Check resource or file object
            if (!is_object($with)&&!fsh::isClass($with,'image')) {
                throw new exception(fsh::error(fsh::E_PARAMETER_TYPE,['param'=>'$with','type'=>'string|resource|image']),fsh::E_PARAMETER_TYPE);
            }

            // Local widht and height
            $w = $this->gd_imagesx();
            $h = $this->gd_imagesy();

            // Autosize filter image option
            if (fsh::getOption(fsh::O_IMAGE_FILTER_SIZE,$options)==true&&($w!=$with->gd_imagesx()||$h!=$with->gd_imagesy())) {
                $with->resize($w,$h,$options);
            }

            // Get timelimit option
            $timelimit = fsh::getOption(fsh::O_TIME_LIMIT,$options);

            // Run filter calculation
            for ($x = 0; $x<$w; ++$x) {
                if ($timelimit) {set_time_limit($timelimit);}
                for ($y = 0; $y<$h; ++$y) {
                    $TabColorsFlag = $this->gd_imagecolorsforindex($this->gd_imagecolorat($x,$y));
                    $TabColorsPerso = $with->gd_imagecolorsforindex($with->gd_imagecolorat($x,$y));
                    $color_r = floor($TabColorsFlag['red']*$TabColorsPerso['red']/255);
                    $color_g = floor($TabColorsFlag['green']*$TabColorsPerso['green']/255);
                    $color_b = floor($TabColorsFlag['blue']*$TabColorsPerso['blue']/255);
                    $this->gd_imagesetpixel($x,$y,$this->gd_imagecolorallocate($color_r,$color_g,$color_b));
                }
            }

            return true;
        }

    /**
     * Get color index for self
     *
     * @param array    $color
     * @param boolean  $alpha
     *
     * @throws \sqf\files\exception
     *
     * @return integer
     */
        public function color (array $color,$alpha=true) {
            // Validate color
            if (!($color = static::validateColor($color))) {
                throw new exception(fsh::error(fsh::E_PARAMETER_TYPE,['param'=>'$color','type'=>'array containing at least 3 integers from 0-255']),fsh::E_PARAMETER_TYPE);
            }

            // Invalid resource
            if (!$this->resource) {
                throw new exception(static::error(static::E_PROPERTY_ACCESS,['name'=>'resource']),static::E_PROPERTY_ACCESS);
            }

            // Return color index
            return static::getColor($this->resource,$color,$alpha);
        }

    /**
     * Get color index for given resource
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
            'image2wbmp',
            'imageaffine',
            'imagealphablending',
            'imageantialias',
            'imagearc',
            'imagebmp',
            'imagechar',
            'imagecharup',
            'imagecolorallocate',
            'imagecolorallocatealpha',
            'imagecolorat',
            'imagecolorclosest',
            'imagecolorclosestalpha',
            'imagecolorclosesthwb',
            'imagecolordeallocate',
            'imagecolorexact',
            'imagecolorexactalpha',
            'imagecolormatch',
            'imagecolorresolve',
            'imagecolorresolvealpha',
            'imagecolorset',
            'imagecolorsforindex',
            'imagecolorstotal',
            'imagecolortransparent',
            'imageconvolution',
            'imagecopy',
            'imagecopymerge',
            'imagecopymergegray',
            'imagecopyresampled',
            'imagecopyresized',
            'imagecrop',
            'imagecropauto',
            'imagedashedline',
            'imagedestroy',
            'imageellipse',
            'imagefill',
            'imagefilledarc',
            'imagefilledellipse',
            'imagefilledpolygon',
            'imagefilledrectangle',
            'imagefilltoborder',
            'imagefilter',
            'imageflip',
            'imagefttext',
            'imagegammacorrect',
            'imagegd2',
            'imagegd',
            'imagegetclip',
            'imagegif',
            'imageinterlace',
            'imageistruecolor',
            'imagejpeg',
            'imagelayereffect',
            'imageline',
            'imageopenpolygon',
            'imagepalettecopy',
            'imagepalettetotruecolor',
            'imagepng',
            'imagepolygon',
            'imagepstext',
            'imagerectangle',
            'imageresolution',
            'imagerotate',
            'imagesavealpha',
            'imagescale',
            'imagesetbrush',
            'imagesetclip',
            'imagesetinterpolation',
            'imagesetpixel',
            'imagesetstyle',
            'imagesetthickness',
            'imagesettile',
            'imagestring',
            'imagestringup',
            'imagesx',
            'imagesy',
            'imagetruecolortopalette',
            'imagettftext',
            'imagewbmp',
            'imagewebp',
            'imagexbm',
        ];

    /** @var array $fn_replace_resource GD functions that replace the current resource */
        protected static $fn_replace_resource = [
            'imagecreate',
            'imagecreatefrombmp',
            'imagecreatefromgd2',
            'imagecreatefromgd2part',
            'imagecreatefromgd',
            'imagecreatefromgif',
            'imagecreatefromjpeg',
            'imagecreatefrompng',
            'imagecreatefromstring',
            'imagecreatefromwbmp',
            'imagecreatefromwebp',
            'imagecreatefromxbm',
            'imagecreatefromxpm',
            'imagecreatetruecolor',
            'imagecrop',
            'imagecropauto',
            'imagegrabscreen',
            'imagegrabwindow',
            'imagerotate',
            'imagescale',
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
