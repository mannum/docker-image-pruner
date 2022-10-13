<?php
namespace DockerImagePruner;

use GuzzleHttp\Client as Guzzle;
use Monolog\Logger;

class Pruner{
    private Guzzle $guzzle;
    private Logger $logger;
    /**
     * @return Guzzle
     */
    public function getGuzzle(): Guzzle
    {
        return $this->guzzle;
    }

    /**
     * @param Guzzle $guzzle
     */
    public function setGuzzle(Guzzle $guzzle): self
    {
        $this->guzzle = $guzzle;
        return $this;
    }

    /**
     * @return Logger
     */
    public function getLogger(): Logger
    {
        return $this->logger;
    }

    /**
     * @param Logger $logger
     * @return Pruner
     */
    public function setLogger(Logger $logger): Pruner
    {
        $this->logger = $logger;
        return $this;
    }

    public function __construct(Logger $logger){
        $this->setLogger($logger);
    }

}