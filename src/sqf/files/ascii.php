<?php
/**
 * Class sqf\files\ascii
 *
 * ASCII file class
 *
 * @version 1.9.0
 * @author siux <me@siux.info>
 */
    namespace sqf\files;
    use sqf\files\exception;
    use sqf\files\handler as fsh;
    use sqf\files\file;

    class ascii extends file {

    /** @var string $eol Default end of line character */
        protected static $eol = PHP_EOL;

    /**
     * Log string to file
     *
     * @param string  $content
     * @param boolean $date
     *
     * @throws \sqf\files\exception
     *
     * @return object|\sqf\files\file
     */
        public function log ($content,$date=true) {
            $content = ($date?date('[Y-m-d H:i:s] '):'').trim($content).static::$eol;
            return $this->append($content);
        }

    /** @var array $blockStyles Predefined styles used for blocks */
        public static $blockStyles = [
            'default'=>[
                'width'=>0,'padh'=>1,'padhbias'=>'right','padv'=>0,'align'=>'left',
                'tmpl'=>['ltc'=>'*','rtc'=>'*','lbc'=>'*','rbc'=>'*','tb'=>'-','bb'=>'-','lb'=>'|','rb'=>'|','space'=>' ']
            ],
            'doc'=>[
                'width'=>0,'padh'=>1,'padhbias'=>'right','padv'=>0,'align'=>'left',
                'tmpl'=>['ltc'=>'/**','rtc'=>'','lbc'=>' */','rbc'=>'','tb'=>'','bb'=>'','lb'=>' *','rb'=>'','space'=>' ']
            ],
            'modern'=>[
                'width'=>0,'padh'=>1,'padhbias'=>'right','padv'=>0,'align'=>'left',
                'tmpl'=>['ltc'=>'┌','rtc'=>'┐','lbc'=>'└','rbc'=>'┘','tb'=>'─','bb'=>'─','lb'=>'│','rb'=>'│','space'=>' ']
            ],
        ];

    /**
     * Draw an ascii block
     *
     * @param string       $str
     * @param string|array $style
     * @param array        $custom
     * @param boolean      $asArray
     * @param boolean      $autoFixWidth
     *
     * @throws \sqf\files\exception
     *
     * @return string|array
     */
        public static function drawBlock ($str,$style='default',$custom=null,$asArray=false,$autoFixWidth=true) {
            // Convert string input
            if (is_string($str)&&strlen($str)) {
                $str = explode(static::$eol,$str);
            }

            // Invalid input
            if (!is_array($str)||!count($str)) {
                throw new exception(fsh::error(fsh::E_PARAMETER_TYPE,['param'=>'$str','type'=>'string|array']),fsh::E_PARAMETER_TYPE);
            }

            // Styling rules
            if (is_string($style)&&isset(static::$blockStyles[$style])) {
                $style = static::$blockStyles[$style];
            }

            // Styling error
            if (!is_array($style)) {
                throw new exception(fsh::error(fsh::E_PARAMETER_TYPE,['param'=>'$style','type'=>'string|array']),fsh::E_PARAMETER_TYPE);
            }

            // Custom style overrides
            if (is_array($custom)) {
                $style = array_replace_recursive($style,$custom);
            }

            // Multibyte safe sorting helper
            $sort = function ($a,$b) {
                return mb_strlen($b)-mb_strlen($a);
            };

            // Find longest line
            uasort($str,$sort);
            $max_len = mb_strlen(current($str));
            ksort($str);

            // Basic variable preparation
            $lbw = mb_strlen($style['tmpl']['lb']);
            $rbw = mb_strlen($style['tmpl']['rb']);
            $bspace = ($style['padh']*2)+$lbw+$rbw;

            // Handle fixed width
            if ($style['width']&&($max_len+$bspace)>$style['width']) {
                if (!$autoFixWidth) {
                    throw new exception(fsh::error(fsh::E_PARAMETER_TYPE,['param'=>'$style[\'width\']','type'=>'integer and larger or equal to '.($max_len+$bspace)]),fsh::E_PARAMETER_TYPE);
                }
                $style['width'] = 0;
            }

            // Set required width
            if (!$style['width']) {
                $style['width'] = $bspace+$max_len;
            }

            // Add lr padding and border
            foreach ($str as $i => $line) {
                $len = mb_strlen($line);
                $tpad = $style['width']-$len-$lbw-$rbw;
                $lpad = $rpad = $style['padh'];
                switch ($style['align']) {
                    case 'left':
                        $rpad = $tpad-$lpad;
                        break;
                    case 'right':
                        $lpad = $tpad-$rpad;
                        break;
                    default:
                        if ($style['padhbias']=='right') {
                            $lpad = floor($tpad/2);
                            $rpad = ceil($tpad/2);
                        } else {
                            $lpad = ceil($tpad/2);
                            $rpad = floor($tpad/2);
                        }
                }
                $str[$i] = $style['tmpl']['lb'].str_repeat($style['tmpl']['space'],$lpad).$line.str_repeat($style['tmpl']['space'],$rpad).$style['tmpl']['rb'];
            }

            // Add tb padding and border
            if ($style['padv']) {
                $vpadline = $style['tmpl']['lb'].str_repeat($style['tmpl']['space'],($style['width']-(mb_strlen($style['tmpl']['lb'])+mb_strlen($style['tmpl']['rb'])))).$style['tmpl']['rb'];
                do {
                    array_unshift($str,$vpadline);
                    array_push($str,$vpadline);
                    $style['padv']--;
                } while ($style['padv']>0);
            }
            array_unshift($str,$style['tmpl']['ltc'].str_repeat($style['tmpl']['tb'],($style['width']-(mb_strlen($style['tmpl']['ltc'])+mb_strlen($style['tmpl']['rtc'])))).$style['tmpl']['rtc']);
            array_push($str,$style['tmpl']['lbc'].str_repeat($style['tmpl']['bb'],($style['width']-(mb_strlen($style['tmpl']['lbc'])+mb_strlen($style['tmpl']['rbc'])))).$style['tmpl']['rbc']);

            // Return rendered
            return ($asArray?$str:implode(static::$eol,$str).static::$eol);
        }

    /** @var array $tableStyles Predefined styles used for tables */
        public static $tableStyles = [
            'default'=>[
                'collabel'=>true,'rowlabel'=>true,'width'=>false,'height'=>false,'padh'=>1,'padhbias'=>'right','align'=>'auto','padv'=>0,'padvbias'=>'bottom','valign'=>'auto','config'=>[],'cols'=>[],'rows'=>[],
                'tmpl'=>['xbl'=>'*','xbr'=>'*','xblr'=>'*','xtbl'=>'*','xtbr'=>'*','xtblr'=>'*','xtl'=>'*','xtr'=>'*','xtlr'=>'*','ihb'=>'-','ivb'=>'|','otb'=>'-','obb'=>'-','olb'=>'|','orb'=>'|','space'=>' ']
            ],
            'modern'=>[
                'collabel'=>true,'rowlabel'=>true,'width'=>false,'height'=>false,'padh'=>1,'padhbias'=>'right','align'=>'auto','padv'=>0,'padvbias'=>'bottom','valign'=>'auto','config'=>[],'cols'=>[],'rows'=>[],
                'tmpl'=>['xbl'=>'┐','xbr'=>'┌','xblr'=>'┬','xtbl'=>'┤','xtbr'=>'├','xtblr'=>'┼','xtl'=>'┘','xtr'=>'└','xtlr'=>'┴','ihb'=>'─','ivb'=>'│','otb'=>'─','obb'=>'─','olb'=>'│','orb'=>'│','space'=>' ']
            ],
            'lines'=>[
                'collabel'=>true,'rowlabel'=>true,'width'=>false,'height'=>false,'padh'=>1,'padhbias'=>'right','align'=>'auto','padv'=>0,'padvbias'=>'bottom','valign'=>'auto','config'=>[],'cols'=>[],'rows'=>[],
                'tmpl'=>['xbl'=>'','xbr'=>'','xblr'=>'','xtbl'=>'-','xtbr'=>'-','xtblr'=>'-','xtl'=>'','xtr'=>'','xtlr'=>'','ihb'=>'-','ivb'=>'|','otb'=>'','obb'=>'','olb'=>'','orb'=>'','space'=>' ']
            ]
        ];

    /**
     * Draw an ascii table
     *
     * @param array        $data
     * @param string|array $style
     * @param array        $custom
     * @param boolean      $asArray
     *
     * @throws \sqf\files\exception
     *
     * @return string|array
     */
        public static function drawTable ($data,$style='default',$custom=null,$asArray=false) {
            // Invalid input
            if (!is_array($data)||!count($data)||count(array_filter($data,'is_array'))!=count($data)) {
                throw new exception(fsh::error(fsh::E_PARAMETER_TYPE,['param'=>'$data','type'=>'array containing arrays with field values']),fsh::E_PARAMETER_TYPE);
            }

            // Styling rules
            if (is_string($style)&&isset(static::$tableStyles[$style])) {
                $style = static::$tableStyles[$style];
            }

            // Styling error
            if (!is_array($style)) {
                throw new exception(fsh::error(fsh::E_PARAMETER_TYPE,['param'=>'$style','type'=>'string|array']),fsh::E_PARAMETER_TYPE);
            }

            // Custom style overrides
            if (is_array($custom)) {
                $style = array_replace_recursive($style,$custom);
            }

            // Column labels
            if ($style['collabel']) {
                foreach ($data as $i => $row) {
                    $labels = [];
                    foreach ($row as $col => $val) {
                        $labels[$col] = $col;
                    }
                    break;
                }
                array_unshift($data,$labels);
            }

            // Row labels
            if ($style['rowlabel']) {
                $labels = [];
                $x = 0;
                foreach ($data as $i => $row) {
                    if (!$x&&$style['collabel']) {
                        array_unshift($data[$i],'');
                    } else {array_unshift($data[$i],$i);}
                    $x++;
                }
            }

            // Analyze table
            $rcc = 0;
            foreach ($data as $i => $row) {
                $acc = 0;
                foreach ($row as $col => $val) {
                    $type = gettype($val);
                    $data[$i][$col] = $val = trim(static::tableValue($val,$i,$col));
                    $len = static::mbl_strlen($val,true);
                    // width
                    if (!isset($style['cols'][$col]['width'])) {$style['cols'][$col]['width'] = $style['width'];}
                    if ($style['cols'][$col]['width']<$len[0]) {$style['cols'][$col]['width'] = $len[0];}
                    // align
                    if (!isset($style['config'][$i][$col]['align'])) {$style['config'][$i][$col]['align'] = $style['align'];}
                    if ($style['config'][$i][$col]['align']=='auto') {$style['config'][$i][$col]['align'] = $type;}
                    // valign
                    if (!isset($style['config'][$i][$col]['valign'])) {$style['config'][$i][$col]['valign'] = $style['valign'];}
                    if ($style['config'][$i][$col]['valign']=='auto') {$style['config'][$i][$col]['valign'] = $type;}
                    // integer type
                    if ($type=='integer') {$style['config'][$i][$col]['align'] = $type;}
                    // float type
                    if ($type=='double'||$type=='float') {
                        $style['config'][$i][$col]['align'] = $type;
                        $fv = explode('.',(string)$val);
                        $fv[0] = strlen($fv[0]);
                        $fv[1] = strlen($fv[1]);
                        if (!isset($style['cols'][$col]['float']['left'])||$style['cols'][$col]['float']['left']<$fv[0]) {$style['cols'][$col]['float']['left'] = $fv[0];}
                        if (!isset($style['cols'][$col]['float']['right'])||$style['cols'][$col]['float']['right']<$fv[1]) {$style['cols'][$col]['float']['right'] = $fv[1];}
                        $fw = $style['cols'][$col]['float']['left']+$style['cols'][$col]['float']['right']+1;
                        if ($style['cols'][$col]['width']<$fw) {$style['cols'][$col]['width'] = $fw;}
                    }
                    // height
                    if (!isset($style['rows'][$i]['height'])) {$style['rows'][$i]['height'] = $style['height'];}
                    if ($style['rows'][$i]['height']<$len[1]) {$style['rows'][$i]['height'] = $len[1];}
                    $acc++;
                }
                $rcc++;
            }

            // Write output
            $output = [];
            $rc = 0;
            foreach ($data as $i => $row) {

                // top border
                if (!$rc) {
                    $tb = $style['tmpl']['xbr'];
                    $cc = 0;
                    foreach ($row as $col => $val) {
                        $w = $style['cols'][$col]['width'];
                        $c_padh = (isset($style['config'][$i][$col]['padh'])?$style['config'][$i][$col]['padh']:$style['padh']);
                        $tb .= str_repeat($style['tmpl']['otb'],$w+$c_padh*2);
                        $tb .= $style['tmpl'][($cc==($acc-1)?'xbl':'xblr')];
                        $cc++;
                    }
                    $output[] = $tb;
                }

                // top padding
                if (!isset($pb)&&$style['padv']) {
                    $pb = $style['tmpl']['olb'];
                    $pbcc = 0;
                    foreach ($row as $col => $val) {
                        $w = $style['cols'][$col]['width'];
                        $c_padh = (isset($style['config'][$i][$col]['padh'])?$style['config'][$i][$col]['padh']:$style['padh']);
                        $pb .= str_repeat($style['tmpl']['space'],$w+$c_padh*2);
                        $pb .= $style['tmpl'][($pbcc==($acc-1)?'orb':'ivb')];
                        $pbcc++;
                    }
                }
                if ($style['padv']) {
                    $pc = 1;
                    do {
                        $output[] = $pb;
                        $pc++;
                    } while ($pc<$style['padv']);
                }

                // content
                $c_rh = $style['rows'][$i]['height'];
                $srs = 0;
                do {

                    $cc = 0;
                    $orow = [];

                    foreach ($row as $col => $val) {
                        $w = $style['cols'][$col]['width'];
                        $c_padh = (isset($style['config'][$i][$col]['padh'])?$style['config'][$i][$col]['padh']:$style['padh']);
                        $c_padhbias = $style['padhbias'];
                        $c_padvbias = $style['padvbias'];
                        $c_align = (isset($style['config'][$i][$col]['align'])?$style['config'][$i][$col]['align']:$style['align']);
                        $c_valign = (isset($style['config'][$i][$col]['valign'])?$style['config'][$i][$col]['valign']:$style['valign']);

                        $clines = explode(static::$eol,$val);
                        $selected_line = $srs;
                        $len = static::mbl_strlen($val,true);
                        // vertical align
                        switch ($c_valign) {
                            case 'top':
                            case 'string':
                            case 'array':break;
                            case 'bottom':$selected_line = $len[1]-$c_rh+$srs;break;
                            case 'boolean':
                            case 'float':
                            case 'double':
                            case 'integer':
                            default:
                                if ($c_rh!=$len[1]) {
                                    if ($c_padvbias=='bottom') {
                                        $selected_line = -1*floor(($c_rh-$len[1])/2)+$srs;
                                    } else {$selected_line = -1*ceil(($c_rh-$len[1])/2)+$srs;}
                                }
                        }
                        $val = (isset($clines[$selected_line])?$clines[$selected_line]:str_repeat($style['tmpl']['space'],$w));
                        // Update length
                        $len = static::mbl_strlen($val,true);

                        $tpad = $w-$len[0]+2*$c_padh;
                        $lpad = $rpad = $c_padh;
                        switch ($c_align) {
                            case 'left':
                            case 'string':
                            case 'array':$rpad = $tpad-$lpad;break;
                            case 'float':
                            case 'double':
                                if (isset($clines[$selected_line])) {
                                    $fv = explode('.',(string)$val);
                                    $fv[0] = strlen($fv[0]);
                                    $fv[1] = strlen($fv[1]);
                                    if ($fv[0]<$style['cols'][$col]['float']['left']) {
                                        $lpad += $style['cols'][$col]['float']['left']-$fv[0];
                                    }
                                    if ($fv[1]<$style['cols'][$col]['float']['right']) {
                                        $rpad += $style['cols'][$col]['float']['right']-$fv[1];
                                    }
                                    if (($flpx = $style['cols'][$col]['float']['left']+$style['cols'][$col]['float']['right']+1)<$w) {
                                        $lpad += $w-$flpx;
                                    }
                                }
                            break;
                            case 'integer':
                            case 'right':
                                $lpad = $tpad-$rpad;
                            break;
                            case 'boolean':
                            default:
                                if ($c_padhbias=='right') {
                                    $lpad = floor($tpad/2);
                                    $rpad = ceil($tpad/2);
                                } else {
                                    $lpad = ceil($tpad/2);
                                    $rpad = floor($tpad/2);
                                }
                        }

                        $orow[] = ($cc?'':$style['tmpl']['olb']).
                            str_repeat($style['tmpl']['space'],$lpad).
                            $val.
                            str_repeat($style['tmpl']['space'],$rpad).
                            $style['tmpl'][($cc==($acc-1)?'orb':'ivb')];

                        $cc++;
                    }

                    $output[] = implode($orow);

                    $srs++;
                } while ($srs<$c_rh);

                // bottom padding
                if ($style['padv']) {
                    $pc = 1;
                    do {
                        $output[] = $pb;
                        $pc++;
                    } while ($pc<$style['padv']);
                }

                // spacer border
                if (!isset($ib)) {
                    $ib = $style['tmpl']['xtbr'];
                    $cc = 0;
                    foreach ($row as $col => $val) {
                        $w = $style['cols'][$col]['width'];
                        $c_padh = (isset($style['config'][$i][$col]['padh'])?$style['config'][$i][$col]['padh']:$style['padh']);
                        $ib .= str_repeat($style['tmpl']['ihb'],$w+$c_padh*2);
                        $ib .= $style['tmpl'][($cc==($acc-1)?'xtbl':'xtblr')];
                        $cc++;
                    }
                }
                if ($rc<$rcc&&$rc!=($rcc-1)) {$output[] = $ib;}

                // bottom border
                if ($rc<$rcc&&$rc==($rcc-1)) {
                    $bb = $style['tmpl']['xtr'];
                    $cc = 0;
                    foreach ($row as $col => $val) {
                        $w = $style['cols'][$col]['width'];
                        $c_padh = (isset($style['config'][$i][$col]['padh'])?$style['config'][$i][$col]['padh']:$style['padh']);
                        $bb .= str_repeat($style['tmpl']['obb'],$w+$c_padh*2);
                        $bb .= $style['tmpl'][($cc==($acc-1)?'xtl':'xtlr')];
                        $cc++;
                    }
                    $output[] = $bb;
                }
                $rc++;
            }
            return ($asArray?$output:implode(static::$eol,$output).static::$eol);
        }

    /**
     * Get max dimensions
     *
     * @param string  $str
     * @param boolean $height
     *
     * @throws \sqf\files\exception
     *
     * @return integer|array
     */
        protected static function mbl_strlen ($str,$height=false) {
            if (strstr($str,static::$eol)) {
                $longest = 0;
                $rows = 0;
                $lines = explode(static::$eol,$str);
                foreach ($lines as $line) {
                    if (($len = mb_strlen($line))>$longest) {$longest = $len;}
                    $rows++;
                }
                return ($height?[$longest,$rows]:$longest);
            } else {
                $len = mb_strlen($str);
                return ($height?[$len,1]:$len);
            }
        }

    /**
     * Get table cell value
     *
     * @param mixed   $val
     * @param integer $row
     * @param integer $col
     *
     * @throws \sqf\files\exception
     *
     * @return string
     */
        protected static function tableValue ($val,$row,$col) {
            switch (($type = gettype($val))) {
                case 'string':
                case 'integer':return $val;
                case 'float':
                case 'double':
                    if (strlen($val)==strlen((int)$val)) {return $val.'.0';}
                    return $val;
                case 'boolean':return ($val?'true':'false');
                case 'array':return static::table($val);
                default:
                    throw new exception(fsh::error(fsh::E_PARAMETER_TYPE,['param'=>'$val for row '.$row.' col '.$col,'type'=>'string|integer|float|boolean|array not '.$type]),fsh::E_PARAMETER_TYPE);
            }
        }

    // End class
    }
