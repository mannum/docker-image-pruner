<?php

namespace DockerImagePruner;
use Carbon;

class ImageTag {
    private string $name;
    private string $tag;
    private ?string $digest;
    private Carbon\Carbon $lastUpdated;

    /**
     * @return string
     */
    public function getTag(): string
    {
        return $this->tag;
    }

    /**
     * @param string $tag
     * @return ImageTag
     */
    public function setTag(string $tag): ImageTag
    {
        $this->tag = $tag;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getDigest(): ?string
    {
        return $this->digest;
    }

    /**
     * @param string $digest
     * @return ImageTag
     */
    public function setDigest(string $digest): ImageTag
    {
        $this->digest = $digest;
        return $this;
    }

    /**
     * @return Carbon\Carbon
     */
    public function getLastUpdated(): Carbon\Carbon
    {
        return $this->lastUpdated;
    }

    /**
     * @param Carbon\Carbon $lastUpdated
     * @return ImageTag
     */
    public function setLastUpdated(Carbon\Carbon $lastUpdated): ImageTag
    {
        $this->lastUpdated = $lastUpdated;
        return $this;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @return ImageTag
     */
    public function setName(string $name): ImageTag
    {
        $this->name = $name;
        return $this;
    }

    public function getFullName() : string {
        return $this->getName() . ":" . $this->getTag();
    }

}