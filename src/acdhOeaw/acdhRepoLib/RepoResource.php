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

use EasyRdf\Graph;
use EasyRdf\Resource;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

/**
 * Description of RepoResource
 *
 * @author zozlak
 */
class RepoResource {

    const UPDATE_ADD       = 'add';
    const UPDATE_OVERWRITE = 'overwrite';
    const UPDATE_MERGE     = 'merge';

    /**
     *
     * @var acdhOeaw\acdhRepoLib\Repo
     */
    private $repo;

    /**
     *
     * @var string
     */
    private $url;

    /**
     *
     * @var EasyRdf\Resource
     */
    private $metadata;

    /**
     *
     * @var bool
     */
    private $metaSynced;

    /**
     * Creates an object representing a repository resource.
     * 
     * @param string $url URL of the resource
     * @param \acdhOeaw\acdhRepoLib\RepoInterface $repo repository connection object
     */
    public function __construct(string $url, RepoInterface $repo) {
        $this->url  = $url;
        $this->repo = $repo;
    }

    /**
     * Returns repository resource binary content.
     * 
     * @return Response PSR-7 response containing resource's binary content
     */
    public function getContent(): Response {
        $request = new Request('get', $this->url);
        return $this->repo->sendRequest($request);
    }

    /**
     * Updates repository resource binary content with a given payload.
     * 
     * @param \acdhOeaw\acdhRepoLib\BinaryPayload $content new content
     * @return void
     */
    public function updateContent(BinaryPayload $content): void {
        $request = new Request('put', $this->url);
        $request = $content->attachTo($request);
        $this->repo->sendRequest($request);
        $this->loadMetadata(true);
    }

    /**
     * Checks if the resource has the binary content.
     * 
     * @return bool
     */
    public function hasBinaryContent(): bool {
        $this->loadMetadata();
        return (int) ((string) $this->metadata->getLiteral($this->repo->getSchema()->binarySize)) > 0;
    }

    /**
     * Replaces resource metadata with a given RDF resource graph. A deep copy
     * of the provided metadata is stored meaning future modifications of the
     * $metadata object don't affect the resource metadata.
     * 
     * New metadata are not automatically written back to the repository.
     * Use the `updateMetadata()` method to write them back.
     * 
     * @param EasyRdf\Resource $metadata
     * @see updateMetadata()
     * @see setGraph()
     */
    public function setMetadata(Resource $metadata): void {
        $this->metadata   = $metadata->copy([], '/^$/', $this->getUri());
        $this->metaSynced = false;
    }

    /**
     * Replaces resource metadata with a given RDF resource graph. A reference
     * to the provided metadata is stored meaning future modifications of the
     * $metadata object automatically affect the resource metadata.
     * 
     * New metadata are not automatically written back to the repository.
     * Use the updateMetadata() method to write them back.
     * 
     * @param EasyRdf\Resource $resource
     * @return void
     * @see updateMetadata()
     * @see setMetadata()
     */
    public function setGraph(Resource $resource): void {
        $this->metadata   = $resource;
        $this->metaSynced = false;
    }

    /**
     * Saves the object metadata to the repository.
     * 
     * Local metadata are automatically updated with the metadata resulting from the update.
     * 
     * @param string $mode metadata update mode - one of `RepoResource::UPDATE_MERGE`,
     *   `RepoResource::UPDATE_ADD` and `RepoResource::UPDATE_OVERWRITE`
     * @return void
     */
    public function updateMetadata(string $mode = self::UPDATE_MERGE): void {
        if (!$this->metaSynced) {
            $updateModeHeader = $this->getRepo()->getHeaderName('metadataWriteMode');
            $headers          = [
                'Content-Type'    => 'application/n-triples',
                'Accept'          => 'application/n-triples',
                $updateModeHeader => $mode,
            ];
            $body             = $this->metadata->getGraph()->serialise('application/n-triples');
            $req              = new Request('patch', $this->url . '/metadata', $headers, $body);
            $resp             = $this->repo->sendRequest($req);
            $this->parseMetadata($resp);
            $this->metaSynced = true;
        }
    }

    /**
     * Deletes the repository resource.
     * 
     * @param bool $tombstone should tombstones be removed for deleted resources?
     * @param bool $references should references to deleted resources be removed
     *   from other resources?
     * @return void
     */
    public function delete(bool $tombstone = false, bool $references = false): void {
        $req = new Request('delete', $this->getUri());
        $this->repo->sendRequest($req);

        if ($tombstone) {
            $req = new Request('delete', $this->getUri() . '/tombstone');
            $this->repo->sendRequest($req);
        }

        if ($references) {
            $query  = "SELECT id FROM relations WHERE target_id = ?";
            $refRes = $this->repo->getResourcesBySqlQuery($query, [$this->getId()], new SearchConfig());
            foreach ($refRes as $res) {
                /* @var $res \acdhOeaw\acdhRepoLib\RepoResource */
                $res->loadMetadata(false, self::META_RESOURCE);
                $meta = $res->getMetadata();
                foreach ($meta->propertyUris() as $p) {
                    $meta->deleteResource($p, $this->getUri());
                    if (null === $meta->getResource($p)) {
                        $meta->addResource($this->repo->getSchema()->delete, $p);
                    }
                }
                $res->setMetadata($meta);
                $res->updateMetadata();
            }
        }

        $this->metadata = null;
    }

    /**
     * Deletes the repository resource as well as all the resources pointing to
     * it with a given metadata property.
     * 
     * The deletion is performed recursively.
     * 
     * @param string $property property used for the recursive deletion
     * @param bool $tombstone should tombstones be removed for deleted resources?
     * @param bool $references should references to deleted resources be removed
     *   from other resources?
     * @return void
     */
    public function deleteRecursively(string $property, bool $tombstone = false,
                                      bool $references = false): void {
        $query  = "SELECT id FROM relations WHERE property = ? AND target_id = ?";
        $refRes = $this->repo->getResourcesBySqlQuery($query, [$property, $this->getId()], new SearchConfig());
        foreach ($refRes as $res) {
            /* @var $res \acdhOeaw\acdhRepoLib\RepoResource */
            $res->deleteRecursively($property, $tombstone, $references);
        }
        $this->delete($tombstone, $references);
    }

    /**
     * Loads current metadata from the repository.
     * 
     * @param bool $force enforce fetch from the repository 
     *   (when you want to make sure metadata are in line with ones in the repository 
     *   or e.g. reset them back to their current state in the repository)
     * @param string $mode scope of the metadata returned by the repository: 
     *   `RepoResource::META_RESOURCE` - only given resource metadata,
     *   `RepoResource::META_NEIGHBORS` - metadata of a given resource and all the resources pointed by its metadata,
     *   `RepoResource::META_RELATIVES` - metadata of a given resource and all resources recursively pointed to a given metadata property
     *      (see the `$parentProperty` parameter), both directly and in a reverse order (reverse in RDF terms)
     * @param string $parentProperty RDF property name used to find related resources in the `RepoResource::META_RELATIVES` mode
     */
    public function loadMetadata(bool $force = false,
                                 string $mode = self::META_RESOURCE,
                                 ?string $parentProperty = null): void {
        if (!is_object($this->metadata) || $force) {
            $headers = [
                'Accept'                                             => 'application/n-triples',
                $this->repo->getHeaderName('metadataReadMode')       => $mode,
                $this->repo->getHeaderName('metadataParentProperty') => $parentProperty ?? $this->repo->getSchema()->parent,
            ];
            $req     = new Request('get', $this->url . '/metadata', $headers);
            $resp    = $this->repo->sendRequest($req);
            $this->parseMetadata($resp);
        }
    }

    /**
     * Parses metadata fetched from the repository.
     * 
     * @param \GuzzleHttp\Psr7\Response $resp response to the metadata fetch HTTP request.
     * @return void
     */
    private function parseMetadata(Response $resp): void {
        $format           = explode(';', $resp->getHeader('Content-Type')[0] ?? '')[0];
        $graph            = new Graph();
        $graph->parse($resp->getBody(), $format);
        $this->metadata   = $graph->resource($this->url);
        $this->metaSynced = true;
    }

    /**
     * Returns an internal repository resource identifier.
     * 
     * @return int
     */
    protected function getId(): int {
        return (int) substr($this->getUri(), strlen($this->repo->getBaseUrl()));
    }

}
