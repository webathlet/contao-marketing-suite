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


namespace numero2\MarketingSuite;

use Contao\StyleSheets;
use numero2\MarketingSuite\Helper\styleable;
use numero2\MarketingSuite\Helper\ContentElementStyleable as Helper;


class ContentButton extends ContentHyperlink implements styleable {


    /**
     * Template
     * @var string
     */
    protected $strTemplate = 'ce_cms_button';

    /**
     * Marker if style preview is enabled
     * @var boolean
     */
    protected $isStylePreview;


    /**
     * Generate the content element
     */
    protected function compile() {

        // set default values for styling preview
        if( $this->isStylePreview ) {

            if( !$this->url && !$this->linkTitle ) {

                $this->url = '#';
                $this->linkTitle = 'Button';
            }

            $this->titleText = $this->titleText?:'Tooltip';
        }

        parent::compile();

        $this->Template->unique = Helper::getUniqueID($this);

        $strStyle = $this->generateStylesheet();

        if( strlen($strStyle) ) {
            $GLOBALS['TL_HEAD'][] = '<style>'.$strStyle.'</style>';
        }
    }


    /**
     * @inheritdoc
     */
    public function generateStylesheet() {

        if( !$this->cms_element_style ) {
            return;
        }

        // get default styling
        $strStyle = NULL;
        $strStyle = Helper::getDefaultStylesheet($this);

        if( $this->cms_style ) {

            $aStyle = [];
            $aStyle = deserialize($this->cms_style);

            $oStyleSheet = NULL;
            $oStyleSheet = new StyleSheets();

            $uniqueID = Helper::getUniqueID($this);

            // split in normal styling and hover stylings
            $aStyleHover = [];

            if( $aStyle && count($aStyle) ) {

                foreach( $aStyle as $key => $value ) {

                    // text-align won't work, we need justify-content
                    if( $key == 'textalign' ) {

                        if( $value == 'left' ) {
                            $aStyle['own'] .= 'justify-content: flex-start;';
                        } else if( $value == 'right' ) {
                            $aStyle['own'] .= 'justify-content: flex-end;';
                        }

                        continue;
                    }

                    if( in_array($key, ['bgcolor', 'bordercolor', 'fontcolor', 'hover_bgcolor', 'hover_bordercolor', 'hover_fontcolor']) ) {
                        $aStyle[$key] = (string)$value;
                    }

                    if( strpos($key, 'hover_') === 0 ) {
                        $aStyleHover[substr($key, 6)] = $value;
                        unset($aStyle[$key]);
                    }
                }
            }

            if( count($aStyle) ) {

                $aStyle += [
                    'size' => 1,
                    'positioning' => 1,
                    'alignment' => 1,
                    'padding' => 1,
                    'background' => 1,
                    'border' => 1,
                    'font' => 1,
                ];

                $styleDef = $oStyleSheet->compileDefinition($aStyle, false, [], [], true);

                $strStyle .= '[data-cms-unique="'.$uniqueID.'"]'. $styleDef;
            }

            if( count($aStyleHover) ) {

                $aStyleHover += [
                    'background' => 1,
                    'border' => 1,
                    'font' => 1,
                ];

                $styleHoverDef = $oStyleSheet->compileDefinition($aStyleHover, false, [], [], true);

                $strStyle .= '[data-cms-unique="'.$uniqueID.'"]:hover'. $styleHoverDef;
            }

            if( !empty($aStyle['cms_element_style_custom']) ) {
                $strStyle .= $aStyle['cms_element_style_custom'];
            }
        }

        return $strStyle;
    }


    /**
     * @inheritdoc
     */
    public function setStylePreview( $active=true ) {

        $this->isStylePreview = $active;
    }

}