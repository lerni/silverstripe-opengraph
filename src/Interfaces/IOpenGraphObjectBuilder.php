<?php

namespace TractorCow\OpenGraph\Interfaces;

/**
 * @author Damian Mooyman
 */
interface IOpenGraphObjectBuilder
{
    /**
     * Generates meta tags for the object as MetaComponents array entries
     * @param array $tags The MetaComponents array to add tags to
     * @param mixed $object The entity to extract opengraph data from. {@see IOGObjectExplicit}
     * @param mixed $config The SiteConfig representing the application. {@see IOGApplication}
     */
    public function BuildTags(&$tags, $object, $config);

    /**
     * Appends a meta property tag to a MetaComponents array.
     * Public to allow use by extensions to OpenGraphBuilder
     * @param array $tags The MetaComponents array to add the tag to
     * @param string $name Meta property attribute value (e.g. 'og:title')
     * @param mixed $content Meta content attribute value(s)
     */
    public function AppendTag(&$tags, $name, $content);
}
