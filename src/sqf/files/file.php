<?php
/**
 * Class sqf\files\file
 *
 * File class
 *
 * @version 1.9.0
 * @author siux <me@siux.info>
 */
    namespace sqf\files;
    use sqf\files\exception;
    use sqf\files\handler as fsh;
    use sqf\files\base;

    class file extends base {

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
            $this->setPathInfo($path,$options,false);

            // Replace
            $this->optionReplace($path,$options);

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

    // End class
    }
