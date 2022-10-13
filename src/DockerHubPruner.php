<?php
namespace DockerImagePruner;

use Carbon\Carbon;
use GuzzleHttp\Client as Guzzle ;
use Monolog\Logger;
use Westsworld\TimeAgo;

class DockerHubPruner extends Pruner{

    protected string $username;
    protected string $patORPassword;
    protected string $namespace;
    protected string $repository;
    protected string $token;
    public function __construct(Logger $logger, string $username, string $patORPassword, string $namespace, string $repository)
    {
        parent::__construct($logger);
        $this->setGuzzle(new Guzzle([
            "base_uri" => "https://hub.docker.com/v2/"
        ]));

        $this
            ->setUsername($username)
            ->setPatORPassword($patORPassword)
            ->setNamespace($namespace)
            ->setRepository($repository);
    }

    /**
     * @return string
     */
    public function getUsername(): string
    {
        return $this->username;
    }

    /**
     * @param string $username
     * @return DockerHubPruner
     */
    public function setUsername(string $username): DockerHubPruner
    {
        $this->username = $username;
        return $this;
    }

    /**
     * @return string
     */
    public function getPatORPassword(): string
    {
        return $this->patORPassword;
    }

    /**
     * @param string $patORPassword
     * @return DockerHubPruner
     */
    public function setPatORPassword(string $patORPassword): DockerHubPruner
    {
        $this->patORPassword = $patORPassword;
        return $this;
    }


    /**
     * @return string
     */
    public function getNamespace(): string
    {
        return $this->namespace;
    }

    /**
     * @param string $namespace
     */
    public function setNamespace(string $namespace): self
    {
        $this->namespace = $namespace;
        return $this;
    }

    /**
     * @return string
     */
    public function getRepository(): string
    {
        return $this->repository;
    }

    /**
     * @param string $repository
     */
    public function setRepository(string $repository): void
    {
        $this->repository = $repository;
    }

    /**
     * @return string
     */
    public function getToken(): string
    {
        return $this->token;
    }

    /**
     * @param string $token
     * @return DockerHubPruner
     */
    public function setToken(string $token): DockerHubPruner
    {
        $this->token = $token;
        return $this;
    }

    protected function login(){
        $this->getLogger()->debug("Logging in...");

        $loginResponse = $this->getGuzzle()->post("users/login",[
            'json' => ['username' => $this->getUsername(), 'password' => $this->getPatORPassword()]
        ]);
        if($loginResponse->getStatusCode()!=200){
            $this->getLogger()->emergency("Login failure");
            exit;
        }
        $loginResponse = json_decode($loginResponse->getBody()->getContents());
        $this->setToken($loginResponse->token);
    }

    protected function listImagesPaged(int $page = 1){
        $listResponse = $this->getGuzzle()->get("namespaces/{$this->getNamespace()}/repositories/{$this->getRepository()}/tags?page_size=100&page=$page", [
            'headers' => [ 'Authorization' => "Bearer {$this->getToken()}" ],
        ]);
        $list = json_decode($listResponse->getBody()->getContents());

        return $list;
    }

    /**
     * @return ImageTag[]
     */
    protected function listImages() : array {
        $list = $this->listImagesPaged(1);
        $pages = ($list->count / 100) + 1;

        $this->getLogger()->debug(sprintf("Listing images... There are %d pages to load...",$pages));

        $allResults = [];
        for($i = 1; $i <= $pages; $i++){
            $list = $this->listImagesPaged($i);
            $allResults = array_merge($allResults, $list->results);
        }
        $this->getLogger()->debug(sprintf("Found %d images, %d loaded", $list->count, count($allResults)));

        return $this->toImageList($allResults);
    }

    /**
     * @param array $allResults
     * @return ImageTag[]
     */
    private function toImageList(array $allResults) : array {
        $imageTags = [];
        foreach($allResults as $result){
            //\Kint::dump($result->digest);exit;
            $imageTags[] = (new ImageTag())
                ->setName(sprintf("%s/%s",$this->getNamespace(), $this->getRepository()))
                ->setTag($result->name)
                ->setDigest($result->digest ?? null)
                ->setLastUpdated(Carbon::parse($result->last_updated));
            //\Kint::dump($result, $imageTags[0]);
        }
        return $imageTags;
    }

    /**
     * @param ImageTag[] $imageTags
     * @return ImageTag[]
     */
    protected function combImagesForDeletion(array $imageTags) : array{
        $deleteable = [];
        $fourWeeksAgo = Carbon::now()->subWeeks(4);
        foreach($imageTags as $imageTag){
            $this->getLogger()->debug(sprintf(
                "Image %s is %s",
                $imageTag->getFullName(),
                (new TimeAgo())->inWords($imageTag->getLastUpdated())
            ));
            if($imageTag->getLastUpdated()->lessThan($fourWeeksAgo)){
                $deleteable[] = $imageTag;
            }
        }
        $this->getLogger()->debug(sprintf("Found %d imagetags to delete", count($deleteable)));
        return $deleteable;
    }
    public function run(){
        $this->login();
        $imageTags = $this->listImages();
        $deletableImageTags = $this->combImagesForDeletion($imageTags);

    }
}