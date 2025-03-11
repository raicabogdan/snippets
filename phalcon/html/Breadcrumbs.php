<?php

namespace App\Modules\Portal\Plugins;

/**
 * This file is part of the Phalcon Framework.
 *
 * (c) Phalcon Team <team@phalcon.io>
 *
 * For the full copyright and license information, please view the LICENSE.txt
 * file that was distributed with this source code.
 */

/**
 * Phalcon\Html\Breadcrumbs.
 *
 * Inspired by:
 * @see https://github.com/sergeyklay/breadcrumbs
 *
 * This component offers an easy way to create breadcrumbs for your application.
 * The resulting HTML when calling `render()` will have each breadcrumb enclosed
 * in `<dt>` tags, while the whole string is enclosed in `<dl>` tags.
 */
class Breadcrumbs
{
    /**
     * Crumb separator.
     */
    public string $separator = ' / ';

    /**
     * Crumb element wrapper Tag.
     */
    public string $wrapperTag = 'dl';

    /**
     * Crumb element wrapper Tag attributes.
     */
    public array $attributes = ['class' => 'wrapper'];

    /**
     * The HTML template to use to render the breadcrumbs.
     */
    public array $template = [
        'home' => '<dt><a href="%link%">%label%</a></dt>',
        'link' => '<dt><a href="%link%">%label%</a></dt>',
        'last' => '<dt><a href="%link%">%label%</a></dt>',
    ];

    /**
     * Keeps all the breadcrumbs.
     */
    private array $elements = [];

    /**
     * Allow changing the template of each individual crumbs.
     *
     * ```
     * // Setting a single template for each crumb
     * $breadcrumbs->setTemplate("<dt><a href=\"%link%\">%label%</a></dt>");
     *
     * // Setting individual crumb type template
     * $breadcrumbs->setTemplate(
     *     $homeCrumb,
     *     $linkCrumb,
     *     $lastCrumb,
     * );
     * ```
     */
    public function setTemplate(
        string $homeTemplate,
        ?string $linkTemplate = null,
        ?string $lastTemplate = null,
    ): Breadcrumbs {
        if (is_null($linkTemplate)) {
            $linkTemplate = $homeTemplate;
        }
        if (is_null($lastTemplate)) {
            $lastTemplate = $homeTemplate;
        }

        $template['home'] = $homeTemplate;
        $template['link'] = $linkTemplate;
        $template['last'] = $lastTemplate;

        $this->template = $template;

        return $this;
    }

    /**
     * Adds a new crumb.
     *
     * ```
     * // Adding a crumb with a link
     * $breadcrumbs->add("Home", "/");
     *
     * // Adding a crumb without a link (normally the last one)
     * $breadcrumbs->add("Users");
     * ```
     */
    public function add(string $label, string $link = ''): Breadcrumbs
    {
        $this->elements[$link] = $label;

        return $this;
    }

    /**
     * Clears the crumbs.
     *
     * ```
     * $breadcrumbs->clear()
     * ```
     */
    public function clear(): void
    {
        $this->elements = [];
    }

    /**
     * Crumb separator.
     */
    public function setSeparator(string $separator): Breadcrumbs
    {
        $this->separator = $separator;

        return $this;
    }

    /**
     * Crumb element wrapperTag.
     *
     * ```
     * <breadcrumb_wrapper>
     *     <crumb element />
     *     <crumb element />
     * </breadcrumb_wrapper>
     * ```
     */
    public function setWrapperTag(string $wrapperTag): Breadcrumbs
    {
        $this->wrapperTag = $wrapperTag;

        return $this;
    }

    /**
     * Render wrapper attributes, like css classes, data attributes etc.
     */
    public function getAttributes(): string
    {
        $attributes = '';
        foreach ($this->attributes as $name => $value) {
            $attributes .= $name.'="'.$value.'" ';
        }

        if ('' !== $attributes) {
            // add space before, after was already added
            $attributes = ' '.$attributes;
        }

        return $attributes;
    }

    /**
     * Set crumb wrapper attributes.
     *
     * @return $this
     */
    public function setAttributes(array $attributes): Breadcrumbs
    {
        $this->attributes = $attributes;

        return $this;
    }

    /**
     * Removes crumb by url.
     *
     * ```
     * $breadcrumbs->remove("/admin/user/create");
     *
     * // remove a crumb without an url (last link)
     * $breadcrumbs->remove();
     * ```
     */
    public function remove(string $link): void
    {
        $elements = $this->elements;

        unset($elements[$link]);

        $this->elements = $elements;
    }

    /**
     * Update crumb by url, with a new label and new url in the same position
     * ```
     * $breadcrumbs->update("/user/create", "Create User", "/admin/users/create");
     * ```.
     */
    public function update(string $link, string $newLabel, string $newLink = ''): void
    {
        if (!isset($this->elements[$link])) {
            throw new \OutOfBoundsException(sprintf("No such url '%s' in breadcrumbs list", $link));
        }

        $elements = [];
        foreach ($this->elements as $key => $label) {
            if ($key === $link) {
                $elements[$newLink] = $newLabel;
            } else {
                $elements[$key] = $label;
            }
        }

        $this->elements = $elements;
    }

    /**
     * Renders and outputs breadcrumbs based on previously set template.
     *
     * ```php
     * echo $breadcrumbs->render();
     * ```
     */
    public function render(): string
    {
        if (empty($this->elements)) {
            return '';
        }

        $output = [];
        $elements = $this->elements;
        $template = $this->template;
        $urls = array_keys($elements);

        // Get the Home URL and render it first
        $homeUrl = current($urls);
        $homeLabel = $elements[$homeUrl];
        unset($elements[$homeUrl]);
        $output[] = str_replace(
            ['%label%', '%link%'],
            [$homeLabel, $homeUrl],
            $template['home']
        );

        // Get the last element out and render it
        $lastCrumb = null;
        if (!empty($elements)) {
            $lastUrl = end($urls);
            $lastLabel = $elements[$lastUrl];
            unset($elements[$lastUrl]);
            $lastCrumb = str_replace(
                ['%label%', '%link%'],
                [$lastLabel, $lastUrl],
                $template['last']
            );
        }

        // Render the remaining crumb links
        foreach ($elements as $url => $label) {
            $output[] = str_replace(
                ['%label%', '%link%'],
                [$label, $url],
                $template['link']
            );
        }

        // Add the last Crumb
        if (!is_null($lastCrumb)) {
            $output[] = $lastCrumb;
        }

        return '<'.$this->wrapperTag.$this->getAttributes().'>'
            .implode($this->separator, $output)
            .'</'.$this->wrapperTag.'>';
    }

    /**
     * Returns the internal breadcrumbs array.
     */
    public function toArray(): array
    {
        return $this->elements;
    }

    /**
     * Check if elements exist.
     */
    public function isReady(): bool
    {
        return !empty($this->elements);
    }
}
