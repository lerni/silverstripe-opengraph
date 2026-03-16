<?php

namespace TractorCow\OpenGraph\ObjectBuilders;

use SilverStripe\Assets\File;
use SilverStripe\Control\HTTP;
use SilverStripe\Core\Extensible;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Model\List\SS_List;
use SilverStripe\Assets\Storage\DBFile;
use TractorCow\OpenGraph\InspectionTrait;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\FieldType\DBDatetime;
use TractorCow\OpenGraph\Interfaces\IOGApplication;
use TractorCow\OpenGraph\Interfaces\ObjectTypes\IOGObject;
use TractorCow\OpenGraph\Interfaces\IOpenGraphObjectBuilder;
use TractorCow\OpenGraph\Interfaces\ObjectTypes\Other\IOGProfile;
use TractorCow\OpenGraph\Interfaces\ObjectTypes\IOGObjectExplicit;
use TractorCow\OpenGraph\Interfaces\ObjectTypes\Other\Relations\IMediaFile;

/**
 * @author Damian Mooyman
 */
class OpenGraphBuilder implements IOpenGraphObjectBuilder
{
    use Injectable;
    use Extensible;
    use InspectionTrait;

    protected $mimeTypes = null;

    protected function isValueIterable($value)
    {
        return is_array($value) || $value instanceof SS_List;
    }

    protected function isValueLinkable($value)
    {
        return $this->implementsType($value, IOGObject::class)
            || $value instanceof SiteTree;
    }

    /**
     * Provides better fallbackfor {@link HTTP::getMimeType}
     * @param string $file File name or path
     * @return string|null Mime type of the passed in file, if known
     */
    protected function getMimeType($file)
    {
        return HTTP::get_mime_type($file);
    }

    /**
     * Returns a unique array key for a tag name, appending .N suffix for duplicates
     */
    protected function getUniqueKey(array &$tags, string $name): string
    {
        if (!isset($tags[$name])) {
            return $name;
        }

        $i = 1;
        while (isset($tags["$name.$i"])) {
            $i++;
        }

        return "$name.$i";
    }

    public function AppendTag(&$tags, $name, $content)
    {
        if (empty($content)) {
            return null;
        }

        // Handle repeated elements
        if ($this->isValueIterable($content)) {
            foreach ($content as $item) {
                $this->AppendTag($tags, $name, $item);
            }

            return null;
        }

        // Handle links to resources (either IOGObject or basic SiteTree)
        if ($this->isValueLinkable($content)) {
            $this->AppendTag($tags, $name, $content->AbsoluteLink());

            return null;
        }

        // Build MetaComponents array entry
        if (is_scalar($content)) {
            $key = $this->getUniqueKey($tags, $name);
            $tags[$key] = [
                'tag' => 'meta',
                'attributes' => [
                    'property' => $name,
                    'content' => (string) $content,
                ],
            ];

            return null;
        }

        trigger_error('Invalid tag type: ' . gettype($content), E_USER_ERROR);
    }

    /**
     * Append a list of tags, which may be either an array, or a comma-separated string
     * @param string       $tags  The current tag string to append these to
     * @param string       $name  Meta name attribute value
     * @param array|string $value Tag list
     */
    protected function appendRelatedTags(&$tags, $name, $value)
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        $this->AppendTag($tags, $name, $value);
    }

    /**
     * Appends a <link /> element to a MetaComponents array
     * @param array  $tags The MetaComponents array to add the tag to
     * @param string $rel  The rel attribute value
     * @param string $link URL to the linked resource
     * @param string $type Mime type of the resource, if known
     */
    protected function appendLink(&$tags, $rel, $link, $type = null)
    {
        if (empty($rel) || empty($link)) {
            return;
        }

        $key = $this->getUniqueKey($tags, "link:$rel");
        $attributes = [
            'rel' => $rel,
            'href' => $link,
        ];

        $mimeType = $type ?: $this->getMimeType($link);
        if ($mimeType) {
            $attributes['type'] = $mimeType;
        }

        $tags[$key] = [
            'tag' => 'link',
            'attributes' => $attributes,
        ];
    }

    /**
     * Builds a list of profile links
     * @param string                                  $tags      The current tag string to append these two
     * @param string                                  $namespace The namespace to use for this element
     * @param IOGProfile[]|IOGProfile|string[]|string $value     A single, or list of profiles
     * @return null|string
     */
    protected function appendRelatedProfileTags(&$tags, $namespace, $value)
    {
        // Treat profiles as generic objects
        return $this->AppendTag($tags, $namespace, $value);
    }

    /**
     * Build a list of linked file tags for the specified value and append them to a string
     * @param string                                              $tags      The current tag string to append these to
     * @param string                                              $namespace The namespace to use for this element
     * @param IMediaFile[]|IMediaFile|File[]|File|string[]|string $value     Either an File object, string to the (non https) image url, or a list of the former
     * @param string                                              $https     The HTTPS url if available
     * @param string                                              $mimeType  type to use, or null to auto detect
     */
    protected function appendMediaMetaTags(&$tags, $namespace, $value, $https = null, $mimeType = null)
    {
        if (empty($value)) {
            return;
        }

        // Handle situation where multiple items are presented
        if ($this->isValueIterable($value)) {
            foreach ($value as $file) {
                $this->appendMediaMetaTags($tags, $namespace, $file, null, $mimeType);
            }

            return;
        }

        // Handle File objects
        /** @var DBFile|File $value */
        if ($value instanceof File || $value instanceof DBFile) {
            if (!$value->exists()) {
                return;
            }

            $this->appendMediaMetaTags($tags, $namespace, $value->getAbsoluteURL(), $https, $mimeType);
            /**
             * If you have the mediadata extension installed, this should correctly populate video width/height elements
             * @link https://github.com/tractorcow/silverstripe-mediadata
             */
            $this->AppendTag($tags, "$namespace:width", $value->getWidth());
            $this->AppendTag($tags, "$namespace:height", $value->getHeight());

            // Add alt text for images using Title field
            if ($namespace === 'og:image' && $value->Title) {
                $this->AppendTag($tags, "$namespace:alt", $value->Title);
            }

            return;
        }

        // Handle IMediaFile objects
        if ($this->implementsType($value, IMediaFile::class)) {
            $this->appendMediaMetaTags($tags, $namespace, $value->getAbsoluteURL(), $value->getSecureURL(), $value->getType());
            $this->AppendTag($tags, "$namespace:width", $value->getWidth());
            $this->AppendTag($tags, "$namespace:height", $value->getHeight());

            return;
        }

        // Handle image URL being given
        if (is_string($value)) {
            // Attempt to auto-detect mime type if missing
            if (empty($mimeType)) {
                $mimeType = $this->getMimeType($value);
            }

            $this->AppendTag($tags, $namespace, $value);
            $this->AppendTag($tags, "$namespace:type", $mimeType);

            return;
        }

        // Fail if could not determine presented value type
        trigger_error('Invalid file type: ' . gettype($value), E_USER_ERROR);
    }

    protected function appendLocales(&$tags, $locales)
    {
        if (empty($locales)) {
            return;
        }

        // handle case with multiple locales
        if (is_array($locales)) {
            // Loop through all locales
            $mainLocale = array_shift($locales);
            $this->appendLocales($tags, $mainLocale);
            foreach ($locales as $locale) {
                $this->AppendTag($tags, 'og:locale:alternate', $locale);
            }
        } else {
            $this->AppendTag($tags, 'og:locale', $locales);
        }
    }

    /**
     * @param array             $tags
     * @param IOGObjectExplicit $object
     */
    protected function appendDefaultMetaTags(&$tags, $object)
    {
        $this->AppendTag($tags, 'og:title', $object->getOGTitle());
        $this->AppendTag($tags, 'og:type', $object->getOGType());
        $this->AppendTag($tags, 'og:url', $object->AbsoluteLink());
        $this->appendMediaMetaTags($tags, 'og:image', $object->getOGImage());

        // Media fields
        $this->appendMediaMetaTags($tags, 'og:audio', $object->getOGAudio());
        $this->appendMediaMetaTags($tags, 'og:video', $object->getOGVideo());

        // Other optional fields
        $this->AppendTag($tags, 'og:description', $object->getOGDescription());
        $this->AppendTag($tags, 'og:determiner ', $object->getOGDeterminer());
        $this->AppendTag($tags, 'og:site_name', $object->getOGSiteName());
        $this->appendLocales($tags, $object->getOGLocales());

        // Entrypoint for extensions to object tags
        $this->extend('updateDefaultMetaTags', $tags, $object);
    }

    /**
     * @param array          $tags
     * @param IOGApplication $config
     */
    protected function appendApplicationMetaTags(&$tags, $config)
    {
        $this->AppendTag($tags, 'fb:admins', $config->getOGAdminID());
        $this->AppendTag($tags, 'fb:app_id', $config->getOGApplicationID());

        // Entrypoint for extensions to application tags
        $this->extend('updateApplicationMetaTags', $tags, $config);
    }

    protected function appendDateTag(&$tags, $name, $date)
    {
        if (empty($date)) {
            return;
        }

        if (!($date instanceof DBDateTime)) {
            $date = DBDatetime::create_field(DBDatetime::class, $date);
        }

        $this->AppendTag($tags, $name, $date->Rfc3339());
    }

    public function BuildTags(&$tags, $object, $config)
    {
        $this->appendDefaultMetaTags($tags, $object);
        $this->appendApplicationMetaTags($tags, $config);
    }
}
