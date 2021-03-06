<?php

namespace Sunnysideup\PerfectCmsImages\Model\File;

use SilverStripe\Assets\Image;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Convert;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\SiteConfig\SiteConfig;

/**
 * defines the image sizes
 * and default upload folder.
 */

class PerfectCmsImageDataExtension extends DataExtension
{
    /**
     * background image for padded images...
     *
     * @var string
     */
    private static $perfect_cms_images_background_padding_color = '#cccccc';

    /***
     * sizes of the images
     *     width: 3200
     *     height: 3200
     *     folder: "myfolder"
     *     filetype: "try jpg"
     *
     * @var array
     *
     */
    private static $perfect_cms_images_image_definitions = [];

    /***
     *  Images Titles will be appended to the links only
     *  if the ClassName of the Image is in this array
     * @var array
     *
     */
    private static $perfect_cms_images_append_title_to_image_links_classes = [];

    /**
     * @var string name of Image Field template
     * @return string (link)
     */
    public function PerfectCmsImageLinkNonRetina(string $name): string
    {
        return $this->PerfectCmsImageLink($name, null, '', false);
    }

    /**
     * @param string $name of Image Field template
     * @return string
     */
    public function PerfectCmsImageLinkRetina(string $name): string
    {
        return $this->PerfectCmsImageLink($name, null, '', true);
    }

    /**
     * @var string name of Image Field template
     * @return string (link)
     */
    public function PerfectCmsImageAbsoluteLink(string $name): string
    {
        return Director::absoluteURL($this->PerfectCmsImageLink($name, null, '', true));
    }

    /**
     * @param       string $name
     * @param       bool $inline Add only the attributes src, srcset, width, height (for use inside an existing img tag)
     * @param       string $alt alt tag for image
     *
     * @return string (HTML)
     */
    public function PerfectCmsImageTag(string $name, ?bool $inline = false, ?string $alt = null)
    {
        $nonRetina = $this->PerfectCmsImageLinkNonRetina($name);
        $retina = $this->PerfectCmsImageLinkRetina($name);
        $width = self::get_width($name, true);
        $widthAtt = '';
        if ($width) {
            $widthAtt = ' width="' . $width . '"';
        }
        $heightAtt = '';
        $height = self::get_height($name, true);
        if ($height) {
            $heightAtt = ' height="' . $height . '"';
        }
        if (! $alt) {
            $alt = $this->owner->Title;
        }
        $imgStart = '';
        $imgEnd = '';
        $altAtt = '';
        $srcAtt = 'src="' . $nonRetina . '"';
        $srcSetAtt = ' srcset="' . $nonRetina . ' 1x, ' . $retina . ' 2x" ';
        if ($inline === false) {
            $imgStart = '<img ';
            $imgEnd = ' />';
            $altAtt = ' alt="' . Convert::raw2att($alt) . '"';
        }
        return DBHTMLText::create_field(
            'HTMLText',
            $imgStart .
            $altAtt .
            $srcAtt .
            $srcSetAtt .
            $widthAtt .
            $heightAtt .
            $imgEnd
        );
    }

    public function PerfectCmsImageLink(string $name, $backupObject = null, ?string $backupField = '', ?bool $useRetina = null): string
    {
        /** @var Image|null */
        $image = $this->owner;
        if (! ($image && $image->exists())) {
            $image = $this->backupImageForPerfectCmsImages($name, $backupObject, $backupField);
        }

        $perfectWidth = self::get_width($name, true);
        $perfectHeight = self::get_height($name, true);

        if ($image) {
            if ($image instanceof Image) {
                if ($image->exists()) {
                    // $backEndString = Image::get_backend();
                    // $backend = Injector::inst()->get($backEndString);
                    $link = $this->createImageForPerfectCmsImages($image, $name, $useRetina, $perfectWidth, $perfectHeight);

                    $path_parts = pathinfo($link);

                    $imageClasses = Config::inst()->get(PerfectCmsImageDataExtension::class, 'perfect_cms_images_append_title_to_image_links_classes');
                    if (in_array($image->ClassName, $imageClasses, true) && $image->Title) {
                        $link = $this->replaceLastInstance(
                            '.' . $path_parts['extension'],
                            '.pci/' . $image->Title . '.' . $path_parts['extension'],
                            $link
                        );
                    }

                    return $link;
                }
            }
        }

        return $this->backupImageForPerfectCmsImages($perfectWidth, $perfectHeight);
    }

    /**
     * @param string           $name
     *
     * @return boolean
     */
    public static function image_info_available($name)
    {
        $sizes = self::get_all_values_for_images();
        //print_r($sizes);die();
        return isset($sizes[$name]) ? true : false;
    }

    /**
     * @param string           $name
     *
     * @return bool
     */
    public static function use_retina($name)
    {
        return self::get_one_value_for_image($name, 'use_retina', true);
    }

    /**
     * @param string           $name
     *
     * @return boolean
     */
    public static function crop($name)
    {
        return self::get_one_value_for_image($name, 'crop', false);
    }

    /**
     * @param string           $name
     * @param bool             $forceInteger
     *
     * @return int
     */
    public static function get_width($name, $forceInteger = false)
    {
        $v = self::get_one_value_for_image($name, 'width', 0);
        if ($forceInteger) {
            $v = intval($v) - 0;
        }

        return $v;
    }

    /**
     * @param string           $name
     * @param bool             $forceInteger
     *
     * @return int
     */
    public static function get_height($name, $forceInteger)
    {
        $v = self::get_one_value_for_image($name, 'height', 0);
        if ($forceInteger) {
            $v = intval($v) - 0;
        }

        return $v;
    }

    /**
     * @param string           $name
     *
     * @return string
     */
    public static function get_folder($name)
    {
        return self::get_one_value_for_image($name, 'folder', 'other-images');
    }

    /**
     * @param string           $name
     *
     * @return int
     */
    public static function max_size_in_kilobytes($name)
    {
        return self::get_one_value_for_image($name, 'max_size_in_kilobytes', 0);
    }

    /**
     * @param string           $name
     *
     * @return string
     */
    public static function get_file_type($name)
    {
        return self::get_one_value_for_image($name, 'filetype', 'jpg');
    }

    /**
     * @param string           $name
     *
     * @return boolean
     */
    public static function get_enforce_size($name)
    {
        return self::get_one_value_for_image($name, 'enforce_size', false);
    }

    /**
     * @param string           $name
     *
     * @return boolean
     */
    public static function get_padding_bg_colour($name)
    {
        return self::get_one_value_for_image(
            $name,
            'padding_bg_colour',
            Config::inst()->get(PerfectCmsImageDataExtension::class, 'perfect_cms_images_background_padding_color')
        );
    }

    /**
     * @param string $name
     * @param int    $key
     * @param mixed  $default
     *
     * @return mixed
     */
    private static function get_one_value_for_image($name, $key, $default = '')
    {
        $sizes = self::get_all_values_for_images();
        //print_r($sizes);die();
        if (isset($sizes[$name])) {
            if (isset($sizes[$name][$key])) {
                return $sizes[$name][$key];
            }
        } else {
            user_error('no information for image with name: ' . $name);
        }

        return $default;
    }

    /**
     * @return array
     */
    private static function get_all_values_for_images()
    {
        return Config::inst()->get(PerfectCmsImageDataExtension::class, 'perfect_cms_images_image_definitions');
    }

    /**
     * replace the last instance of a string occurence.
     *
     * @param  string $search  needle
     * @param  string $replace new needle
     * @param  string $subject haystack
     *
     * @return string
     */
    private function replaceLastInstance($search, $replace, $subject)
    {
        $pos = strrpos($subject, $search);

        if ($pos !== false) {
            $subject = substr_replace($subject, $replace, $pos, strlen($search));
        }

        return $subject;
    }

    private function backupImageForPerfectCmsImages(string $name, $backupObject = null, ?string $backupField = '')
    {
        $image = null;
        if (! $backupObject) {
            $backupObject = SiteConfig::current_site_config();
        }
        if (! $backupField) {
            $backupField = $name;
        }
        if ($backupObject->hasMethod($backupField)) {
            $image = $backupObject->{$backupField}();
        }
        return $image;
    }

    private function backupImageLinkForPerfectCmsImages(?int $perfectWidth = 0, ?int $perfectHeight = 0): string
    {
        // no image -> provide placeholder if in DEV MODE only!!!
        $string = '';
        if (! Director::isLive()) {
            if ($perfectWidth || $perfectHeight) {
                if (! $perfectWidth) {
                    $perfectWidth = $perfectHeight;
                }
                if (! $perfectHeight) {
                    $perfectHeight = $perfectWidth;
                }
                $text = "${perfectWidth} x ${perfectHeight} /2 = " . round($perfectWidth / 2) . ' x ' . round($perfectHeight / 2) . '';

                $string = 'https://placehold.it/' . $perfectWidth . 'x' . $perfectHeight . '?text=' . urlencode($text);
            } else {
                $string = 'https://placehold.it/500x500?text=' . urlencode('no size set');
            }
        }
        return $string;
    }

    private function createImageForPerfectCmsImages($image, string $name, ?bool $useRetina = null, ?int $perfectWidth = 0, ?int $perfectHeight = 0): string
    {
        $link = '';
        //work out perfect width and height
        if ($useRetina === null) {
            $useRetina = PerfectCmsImageDataExtension::use_retina($name);
        }
        $crop = PerfectCmsImageDataExtension::crop($name);
        $multiplier = 1;
        if ($useRetina) {
            $multiplier = 2;
        }
        $perfectWidth *= $multiplier;
        $perfectHeight *= $multiplier;

        //get current width and height
        $myWidth = $image->getWidth();
        $myHeight = $image->getHeight();
        if ($perfectWidth && $perfectHeight) {
            //if the height or the width are already perfect then we can not do anything about it.
            if ($myWidth === $perfectWidth && $myHeight === $perfectHeight) {
                $link = $image->Link();
            } elseif ($crop) {
                $link = $image->Fill($perfectWidth, $perfectHeight)->Link();
            } elseif ($myWidth < $perfectWidth || $myHeight < $perfectHeight) {
                $link = $image->Pad(
                    $perfectWidth,
                    $perfectHeight,
                    PerfectCmsImageDataExtension::get_padding_bg_colour($name)
                )->Link();
            } else {
                $link = $image->FitMax($perfectWidth, $perfectHeight)->Link();
            }
        } elseif ($perfectWidth) {
            if ($myWidth === $perfectWidth) {
                $link = $image->Link();
            } elseif ($crop) {
                $link = $image->Fill($perfectWidth, $myHeight)->Link();
            } else {
                $link = $image->ScaleWidth($perfectWidth)->Link();
            }
        } elseif ($perfectHeight) {
            if ($myHeight === $perfectHeight) {
                $link = $image->Link();
            } elseif ($crop) {
                $link = $image->Fill($myWidth, $perfectHeight)->Link();
            } else {
                $link = $image->ScaleHeight($perfectHeight)->Link();
            }
        } else {
            $link = $image->ScaleWidth($myWidth)->Link();
        }
        return $link;
    }
}
