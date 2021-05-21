<?php

/*
 * The MIT License
 *
 * Copyright 2019 Austrian Centre for Digital Humanities.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

namespace acdhOeaw\acdhRepoLib;

/**
 * Stores the repository search configuration, e.g. full text search options and pagination options.
 *
 * @author zozlak
 */
class SearchConfig {

    const FTS_BINARY = 'BINARY';

    /**
     * Creates an instance of the SearchConfig class form the POST data.
     * 
     * @return \self
     */
    static public function factory(): self {
        $sc = new SearchConfig();
        foreach ($sc as $k => $v) {
            if (isset($_POST[$k])) {
                $sc->$k = $_POST[$k];
            } elseif (isset($_POST[$k . '[]'])) {
                $sc->$k = $_POST[$k . '[]'];
            }
        }

        return $sc;
    }

    /**
     * Controls amount of metadata included in the search results.
     * 
     * Value should be one of `RepoResourceInterface::META_*` constants.
     * 
     * @var string
     * @see \acdhOeaw\acdhRepoLib\RepoResourceInterface::META_RESOURCE
     */
    public $metadataMode;

    /**
     * RDF predicate used by some of metadataModes.
     * 
     * @var string | null
     */
    public $metadataParentProperty;

    /**
     * Maximum number of returned resources (only resources matched by the search
     * are counted - see `$metadataMode`).
     * 
     * @var int
     */
    public $limit;

    /**
     * Offset of the first returned result.
     * 
     * Remember your search results must be ordered if you want get stable results.
     * 
     * @var int 
     */
    public $offset;

    /**
     * Total number of resources matching the search (despite limit/offset)
     * 
     * Set by RepoInterface::getGraphBy*() and RepoInterface::getResourceBy*() 
     * methods.
     * 
     * @var int
     */
    public $count;
    
    /**
     * List of metadata properties to order results by.
     * 
     * Only literal values are used for ordering.
     * 
     * @var array<string>
     */
    public $orderBy = [];

    /**
     * If specified, only property values with a given language are taken into
     * account for ordering search matches.
     * 
     * @var string
     */
    public $orderByLang;
    
    /**
     * A full text search query used for search results highlighting.
     * 
     * See https://www.postgresql.org/docs/current/textsearch-controls.html#TEXTSEARCH-PARSING-QUERIES
     * and the websearch_to_tsquery() function documentation.
     * 
     * Remember this query is applied only to the search results and is not used to
     * perform an actual search (yes, technically you can search by one term
     * and highlight results using the other).
     * 
     * @var string 
     */
    public $ftsQuery;

    /**
     * Data to be used for full text search results highlighting.
     * 
     * - `null` if both resource metadata and binary content should be used;
     * - an RDF property if a given metadata property should be used
     * - `SearchConfig::FTS_BINARY` if the resource binary content should be used
     * 
     * @var string
     */
    public $ftsProperty;

    /**
     * Full text search highlighting options see - https://www.postgresql.org/docs/current/textsearch-controls.html#TEXTSEARCH-HEADLINE
     * 
     * @var string
     */
    public $ftsStartSel;

    /**
     * Full text search highlighting options see - https://www.postgresql.org/docs/current/textsearch-controls.html#TEXTSEARCH-HEADLINE
     * 
     * @var string
     */
    public $ftsStopSel;

    /**
     * Full text search highlighting options see - https://www.postgresql.org/docs/current/textsearch-controls.html#TEXTSEARCH-HEADLINE
     * 
     * @var int
     */
    public $ftsMaxWords;

    /**
     * Full text search highlighting options see - https://www.postgresql.org/docs/current/textsearch-controls.html#TEXTSEARCH-HEADLINE
     * 
     * @var int
     */
    public $ftsMinWords;

    /**
     * Full text search highlighting options see - https://www.postgresql.org/docs/current/textsearch-controls.html#TEXTSEARCH-HEADLINE
     * 
     * @var int
     */
    public $ftsShortWord;

    /**
     * Full text search highlighting options see - https://www.postgresql.org/docs/current/textsearch-controls.html#TEXTSEARCH-HEADLINE
     * 
     * @var bool
     */
    public $ftsHighlightAll;

    /**
     * Full text search highlighting options see - https://www.postgresql.org/docs/current/textsearch-controls.html#TEXTSEARCH-HEADLINE
     * 
     * @var int
     */
    public $ftsMaxFragments;

    /**
     * Full text search highlighting options see - https://www.postgresql.org/docs/current/textsearch-controls.html#TEXTSEARCH-HEADLINE
     * 
     * @var string
     */
    public $ftsFragmentDelimiter;

    /**
     * An optional class of the for the objects returned as the search results
     *   (to be used by extension libraries).
     * @var string
     */
    public $class;

    public function toArray(): array {
        $a = [];
        foreach ($this as $k => $v) {
            if (!in_array($k, ['class', 'metadataMode', 'metadataParentProperty']) && !empty($v)) {
                $a[$k] = $v;
            }
        }
        return $a;
    }

    /**
     * Returns HTTP request headers setting metadata read mode and metadata parent property
     * according to the search config settings.
     * 
     * @param \acdhOeaw\acdhRepoLib\Repo $repo
     * @return type
     */
    public function getHeaders(Repo $repo) {
        $h = [];
        if (!empty($this->metadataMode)) {
            $h[$repo->getHeaderName('metadataReadMode')] = $this->metadataMode;
        }
        if (!empty($this->metadataParentProperty)) {
            $h[$repo->getHeaderName('metadataParentProperty')] = $this->metadataParentProperty;
        }
        return $h;
    }

    public function toQuery(): string {
        return http_build_query($this->toArray());
    }
}