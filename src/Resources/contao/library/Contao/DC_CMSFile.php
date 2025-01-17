<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2005-2018 Leo Feyer
 *
 * @package   Contao Marketing Suite
 * @author    Benny Born <benny.born@numero2.de>
 * @author    Michael Bösherz <michael.boesherz@numero2.de>
 * @license   Commercial
 * @copyright 2018 numero2 - Agentur für digitales Marketing
 */


namespace Contao;

use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBagInterface;


class DC_CMSFile extends \DataContainer implements \editable {


    /**
     * Initialize the object
     *
     * @param string $strTable
     */
    public function __construct( $strTable ) {

        parent::__construct();

        $this->intId = \Input::get('id');

        // Check whether the table is defined
        if( $strTable == '' || !isset($GLOBALS['TL_DCA'][$strTable]) ) {

            $this->log('Could not load data container configuration for "' . $strTable . '"', __METHOD__, TL_ERROR);
            trigger_error('Could not load data container configuration', E_USER_ERROR);
        }

        // Build object from global configuration array
        $this->strTable = $strTable;

        // Call onload_callback (e.g. to check permissions)
        if( \is_array($GLOBALS['TL_DCA'][$this->strTable]['config']['onload_callback']) ) {

            foreach( $GLOBALS['TL_DCA'][$this->strTable]['config']['onload_callback'] as $callback ) {

                if( \is_array($callback) ) {

                    $this->import($callback[0]);
                    $this->{$callback[0]}->{$callback[1]}($this);

                } else if (\is_callable($callback)) {

                    $callback($this);
                }
            }
        }
    }


    /**
     * Automatically switch to edit mode
     *
     * @return string
     */
    public function create() {
        return $this->edit();
    }


    /**
     * Automatically switch to edit mode
     *
     * @return string
     */
    public function cut() {
        return $this->edit();
    }


    /**
     * Automatically switch to edit mode
     *
     * @return string
     */
    public function copy() {
        return $this->edit();
    }


    /**
     * Automatically switch to edit mode
     *
     * @return string
     */
    public function move() {
        return $this->edit();
    }


    /**
     * Auto-generate a form to edit the local configuration file
     *
     * @return string
     */
    public function edit() {

        $return = '';
        $ajaxId = null;

        if( \Environment::get('isAjaxRequest') ) {
            $ajaxId = func_get_arg(1);
        }

        // Build an array from boxes and rows
        $this->strPalette = $this->getPalette();
        $boxes = \StringUtil::trimsplit(';', $this->strPalette);
        $legends = [];

        if( !empty($boxes) ) {

            foreach( $boxes as $k=>$v ) {

                $boxes[$k] = \StringUtil::trimsplit(',', $v);

                foreach( $boxes[$k] as $kk=>$vv ) {

                    if( preg_match('/^\[.*\]$/', $vv) ) {
                        continue;
                    }

                    if( preg_match('/^\{.*\}$/', $vv) ) {

                        $legends[$k] = substr($vv, 1, -1);
                        unset($boxes[$k][$kk]);

                    } else if( $GLOBALS['TL_DCA'][$this->strTable]['fields'][$vv]['exclude'] || !\is_array($GLOBALS['TL_DCA'][$this->strTable]['fields'][$vv]) ) {

                        unset($boxes[$k][$kk]);
                    }
                }

                // Unset a box if it does not contain any fields
                if( empty($boxes[$k]) ) {

                    unset($boxes[$k]);
                }
            }

            /** @var AttributeBagInterface $objSessionBag */
            $objSessionBag = \System::getContainer()->get('session')->getBag('contao_backend');

            // Render boxes
            $class = 'tl_tbox';
            $fs = $objSessionBag->get('fieldset_states');

            foreach( $boxes as $k=>$v ) {

                $strAjax = '';
                $blnAjax = false;
                $key = '';
                $cls = '';
                $legend = '';

                if( isset($legends[$k]) ) {

                    list($key, $cls) = explode(':', $legends[$k]);
                    $legend = "\n" . '<legend onclick="AjaxRequest.toggleFieldset(this, \'' . $key . '\', \'' . $this->strTable . '\')">' . (isset($GLOBALS['TL_LANG'][$this->strTable][$key]) ? $GLOBALS['TL_LANG'][$this->strTable][$key] : $key) . '</legend>';
                }

                if( isset($fs[$this->strTable][$key]) ) {

                    $class .= ($fs[$this->strTable][$key] ? '' : ' collapsed');

                } else {

                    $class .= (($cls && $legend) ? ' ' . $cls : '');
                }

                $return .= "\n\n" . '<fieldset' . ($key ? ' id="pal_'.$key.'"' : '') . ' class="' . $class . ($legend ? '' : ' nolegend') . '">' . $legend;

                // Build rows of the current box
                foreach( $v as $vv ) {

                    if( $vv == '[EOF]' ) {

                        if( $blnAjax && \Environment::get('isAjaxRequest') ) {

                            return $strAjax . '<input type="hidden" name="FORM_FIELDS[]" value="'.\StringUtil::specialchars($this->strPalette).'">';
                        }

                        $blnAjax = false;
                        $return .= "\n  " . '</div>';

                        continue;
                    }

                    if( preg_match('/^\[.*\]$/', $vv) ) {

                        $thisId = 'sub_' . substr($vv, 1, -1);
                        $blnAjax = ($ajaxId == $thisId && \Environment::get('isAjaxRequest')) ? true : false;
                        $return .= "\n  " . '<div id="'.$thisId.'" class="subpal cf">';

                        continue;
                    }

                    $this->strField = $vv;
                    $this->strInputName = $vv;
                    $this->varValue = \CMSConfig::get($this->strField);

                    // Handle entities
                    if( $GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['inputType'] == 'text' || $GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['inputType'] == 'textarea' ) {

                        if( $GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['eval']['multiple'] ) {

                            $this->varValue = \StringUtil::deserialize($this->varValue);
                        }

                        if( !\is_array($this->varValue) ) {

                            $this->varValue = htmlspecialchars($this->varValue);

                        } else {

                            foreach( $this->varValue as $key=>$val ) {

                                $this->varValue[$key] = htmlspecialchars($val);
                            }
                        }
                    }

                    // Call load_callback
                    if( \is_array($GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['load_callback']) ) {

                        foreach( $GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['load_callback'] as $callback ) {

                            if( \is_array($callback) ) {

                                $this->import($callback[0]);
                                $this->varValue = $this->{$callback[0]}->{$callback[1]}($this->varValue, $this);

                            } else if( \is_callable($callback) ) {

                                $this->varValue = $callback($this->varValue, $this);
                            }
                        }
                    }

                    // Build row
                    $blnAjax ? $strAjax .= $this->row() : $return .= $this->row();
                }

                $class = 'tl_box';
                $return .= "\n" . '</fieldset>';
            }
        }

        $this->import('Files');

        // Check whether the target file is writeable
        if( !$this->Files->is_writeable('system/config/cmsconfig.php') ) {
            \Message::addError(sprintf($GLOBALS['TL_LANG']['ERR']['notWriteable'], 'system/config/cmsconfig.php'));
        }

        // Submit buttons
        $arrButtons = [];
        $arrButtons['save'] = '<button type="submit" name="save" id="save" class="tl_submit" accesskey="s">'.$GLOBALS['TL_LANG']['MSC']['save'].'</button>';
        $arrButtons['saveNclose'] = '<button type="submit" name="saveNclose" id="saveNclose" class="tl_submit" accesskey="c">'.$GLOBALS['TL_LANG']['MSC']['saveNclose'].'</button>';

        // Call the buttons_callback (see #4691)
        if( \is_array($GLOBALS['TL_DCA'][$this->strTable]['edit']['buttons_callback']) ) {

            foreach( $GLOBALS['TL_DCA'][$this->strTable]['edit']['buttons_callback'] as $callback ) {

                if( \is_array($callback) ) {

                    $this->import($callback[0]);
                    $arrButtons = $this->{$callback[0]}->{$callback[1]}($arrButtons, $this);

                } else if( \is_callable($callback) ) {
                    $arrButtons = $callback($arrButtons, $this);
                }
            }
        }

        // Add the buttons and end the form
        $return .= '
</div>
<div class="tl_formbody_submit">
<div class="tl_submit_container">
  ' . implode(' ', $arrButtons) . '
</div>
</div>
</form>';

        // Begin the form (-> DO NOT CHANGE THIS ORDER -> this way the onsubmit attribute of the form can be changed by a field)
        $return = \Message::generate() . ($this->noReload ? '
<p class="tl_error">'.$GLOBALS['TL_LANG']['ERR']['general'].'</p>' : '') . '
<div id="tl_buttons">
<a href="'.$this->getReferer(true).'" class="header_back" title="'.\StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['backBTTitle']).'" accesskey="b" onclick="Backend.getScrollOffset()">'.$GLOBALS['TL_LANG']['MSC']['backBT'].'</a>
</div>
<form action="'.ampersand(\Environment::get('request'), true).'" id="'.$this->strTable.'" class="tl_form tl_edit_form" method="post"'.(!empty($this->onsubmit) ? ' onsubmit="'.implode(' ', $this->onsubmit).'"' : '').'>
<div class="tl_formbody_edit">
<input type="hidden" name="FORM_SUBMIT" value="'.$this->strTable.'">
<input type="hidden" name="REQUEST_TOKEN" value="'.REQUEST_TOKEN.'">
<input type="hidden" name="FORM_FIELDS[]" value="'.\StringUtil::specialchars($this->strPalette).'">' . $return;

        // Reload the page to prevent _POST variables from being sent twice
        if( \Input::post('FORM_SUBMIT') == $this->strTable && !$this->noReload ) {
            // Call onsubmit_callback
            if( \is_array($GLOBALS['TL_DCA'][$this->strTable]['config']['onsubmit_callback']) ) {

                foreach( $GLOBALS['TL_DCA'][$this->strTable]['config']['onsubmit_callback'] as $callback ) {

                    if( \is_array($callback) ) {

                        $this->import($callback[0]);
                        $this->{$callback[0]}->{$callback[1]}($this);

                    } else if( \is_callable($callback) ) {

                        $callback($this);
                    }
                }
            }

            // Reload
            if( isset($_POST['saveNclose']) ) {

                \Message::reset();
                \System::setCookie('BE_PAGE_OFFSET', 0, 0);
                $this->redirect($this->getReferer());
            }

            $this->reload();
        }

        // Set the focus if there is an error
        if( $this->noReload ) {

            $return .= '
<script>
  window.addEvent(\'domready\', function() {
    Backend.vScrollTo(($(\'' . $this->strTable . '\').getElement(\'label.error\').getPosition().y - 20));
  });
</script>';
        }

        return $return;
    }


    /**
     * Save the current value
     *
     * @param mixed $varValue
     */
    protected function save( $varValue ) {

        if( \Input::post('FORM_SUBMIT') != $this->strTable ) {
            return;
        }

        $arrData = $GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField];

        // Make sure that checkbox values are boolean
        if( $arrData['inputType'] == 'checkbox' && !$arrData['eval']['multiple'] ) {
            $varValue = $varValue ? true : false;
        }

        if( $varValue != '' ) {

            // Convert binary UUIDs (see #6893)
            if( $arrData['inputType'] == 'fileTree' ) {

                $varValue = \StringUtil::deserialize($varValue);

                if( !\is_array($varValue) ) {
                    $varValue = \StringUtil::binToUuid($varValue);
                } else {
                    $varValue = serialize(array_map('StringUtil::binToUuid', $varValue));
                }
            }

            // Convert date formats into timestamps
            if( $varValue != '' && \in_array($arrData['eval']['rgxp'], array('date', 'time', 'datim')) ) {

                $objDate = new \Date($varValue, \Date::getFormatFromRgxp($arrData['eval']['rgxp']));
                $varValue = $objDate->tstamp;
            }

            // Handle entities
            if( $arrData['inputType'] == 'text' || $arrData['inputType'] == 'textarea' ) {

                $varValue = \StringUtil::deserialize($varValue);

                if( !\is_array($varValue) ) {

                    $varValue = \StringUtil::restoreBasicEntities($varValue);

                } else {

                    $varValue = serialize(array_map('StringUtil::restoreBasicEntities', $varValue));
                }
            }
        }

        // Trigger the save_callback
        if( \is_array($arrData['save_callback']) ) {

            foreach( $arrData['save_callback'] as $callback ) {

                if( \is_array($callback) ) {

                    $this->import($callback[0]);
                    $varValue = $this->{$callback[0]}->{$callback[1]}($varValue, $this);

                } else if( \is_callable($callback) ) {

                    $varValue = $callback($varValue, $this);
                }
            }
        }

        $strCurrent = $this->varValue;

        // Handle arrays and strings
        if( \is_array($strCurrent) ) {

            $strCurrent = serialize($strCurrent);

        } else if( \is_string($strCurrent) ) {

            $strCurrent = html_entity_decode($this->varValue, ENT_QUOTES, \Config::get('characterSet'));
        }

        // Save the value if there was no error
        if( (\strlen($varValue) || !$arrData['eval']['doNotSaveEmpty']) && $strCurrent != $varValue ) {

            \CMSConfig::persist($this->strField, $varValue);

            $deserialize = \StringUtil::deserialize($varValue);
            $prior = \is_bool(\CMSConfig::get($this->strField)) ? (\CMSConfig::get($this->strField) ? 'true' : 'false') : \CMSConfig::get($this->strField);

            // Add a log entry
            if( !\is_array(\StringUtil::deserialize($prior)) && !\is_array($deserialize) ) {

                if( $arrData['inputType'] == 'password' || $arrData['inputType'] == 'textStore' ) {

                    $this->log('The global configuration variable "'.$this->strField.'" has been changed', __METHOD__, TL_CONFIGURATION);
                } else {

                    $this->log('The global configuration variable "'.$this->strField.'" has been changed from "'.$prior.'" to "'.$varValue.'"', __METHOD__, TL_CONFIGURATION);
                }
            }

            // Set the new value so the input field can show it
            $this->varValue = $deserialize;
            \CMSConfig::set($this->strField, $deserialize);
        }
    }


    /**
     * Return the name of the current palette
     *
     * @return string
     */
    public function getPalette() {

        $palette = 'default';
        $strPalette = $GLOBALS['TL_DCA'][$this->strTable]['palettes'][$palette];

        // Check whether there are selector fields
        if( !empty($GLOBALS['TL_DCA'][$this->strTable]['palettes']['__selector__']) ) {

            $sValues = [];
            $subpalettes = [];

            foreach( $GLOBALS['TL_DCA'][$this->strTable]['palettes']['__selector__'] as $name ) {

                $trigger = \CMSConfig::get($name);

                // Overwrite the trigger if the page is not reloaded
                if( \Input::post('FORM_SUBMIT') == $this->strTable ) {

                    $key = (\Input::get('act') == 'editAll') ? $name.'_'.$this->intId : $name;

                    if( !$GLOBALS['TL_DCA'][$this->strTable]['fields'][$name]['eval']['submitOnChange'] ) {

                        $trigger = \Input::post($key);
                    }
                }

                if( $trigger != '' ) {

                    if( $GLOBALS['TL_DCA'][$this->strTable]['fields'][$name]['inputType'] == 'checkbox' && !$GLOBALS['TL_DCA'][$this->strTable]['fields'][$name]['eval']['multiple'] ) {

                        $sValues[] = $name;

                        // Look for a subpalette
                        if( \strlen($GLOBALS['TL_DCA'][$this->strTable]['subpalettes'][$name]) ) {

                            $subpalettes[$name] = $GLOBALS['TL_DCA'][$this->strTable]['subpalettes'][$name];
                        }

                    } else {

                        $sValues[] = $trigger;
                        $key = $name .'_'. $trigger;

                        // Look for a subpalette
                        if( \strlen($GLOBALS['TL_DCA'][$this->strTable]['subpalettes'][$key]) ) {

                            $subpalettes[$name] = $GLOBALS['TL_DCA'][$this->strTable]['subpalettes'][$key];
                        }
                    }
                }
            }

            // Build possible palette names from the selector values
            if( empty($sValues) ) {

                $names = ['default'];

            } else if( \count($sValues) > 1 ) {

                $names = $this->combiner($sValues);

            } else {

                $names = [$sValues[0]];
            }

            // Get an existing palette
            foreach( $names as $paletteName ) {

                if( \strlen($GLOBALS['TL_DCA'][$this->strTable]['palettes'][$paletteName]) ) {

                    $strPalette = $GLOBALS['TL_DCA'][$this->strTable]['palettes'][$paletteName];
                    break;
                }
            }

            // Include subpalettes
            foreach( $subpalettes as $k=>$v ) {

                $strPalette = preg_replace('/\b'. preg_quote($k, '/').'\b/i', $k.',['.$k.'],'.$v.',[EOF]', $strPalette);
            }
        }

        return $strPalette;
    }
}