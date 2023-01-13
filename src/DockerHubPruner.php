<?php
namespace DockerImagePruner;

use Carbon\Carbon;
use GuzzleHttp\Client as Guzzle ;
use GuzzleHttp\Exception\ClientException;
use Monolog\Logger;
use Westsworld\TimeAgo;

class DockerHubPruner extends Pruner{
    const MAX_IMAGES_PER_CHUNK=25;
    protected string $username;
    protected string $patORPassword;
    protected string $namespace;
    protected string $repository;
    protected string $token;

    protected Carbon $timeInPast;
    public function __construct(
        Logger $logger,
        protected bool $debug,
        protected bool $dryRun,
        string $username,
        string $patORPassword,
        string $namespace,
        string $repository,
    )
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

        $this->timeInPast = Carbon::now()->subMonth();

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

        $loginRequest = ['username' => $this->getUsername(), 'password' => $this->getPatORPassword()];
        #if($this->debug) \Kint::dump($loginRequest);
        $loginResponse = $this->getGuzzle()->post("users/login",[
            'json' => $loginRequest
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

            if(property_exists($result, 'digest')) {
                $digest = $result->digest;
            }elseif(property_exists($result->images[0],'digest')){
                $digest = $result->images['0']->digest;
            }else{
                if($this->debug)
                    \Kint::dump($result);
                $this->getLogger()->critical(sprintf("Missing digest: %s", $result->name));
                exit(1);
                continue;
            }
            $imageTags[] = (new ImageTag())
                ->setName(sprintf("%s/%s",$this->getNamespace(), $this->getRepository()))
                ->setTag($result->name)
                ->setDigest($digest)
                ->setLastUpdated(Carbon::parse($result->last_updated));
        }
        return $imageTags;
    }

    /**
     * @param ImageTag[] $imageTags
     * @return array
     */
    protected function combImagesForDeletion(array $imageTags) : array{
        $deleteable = [];
        foreach($imageTags as $imageTag){
            $canBeDeleted = $imageTag->getLastUpdated()->lessThan($this->timeInPast);
            if($canBeDeleted) {
                $this->getLogger()->info(sprintf(
                    "Image %s is from %s and %s be deleted",
                    $imageTag->getFullName(),
                    (new TimeAgo())->inWords($imageTag->getLastUpdated()),
                    $canBeDeleted ? "will" : "will NOT",
                ));
            }
            if($canBeDeleted){
                $deleteable[$imageTag->getDigest()][] = $imageTag;
            }
        }

        $this->getLogger()->debug(sprintf("Found %d imagetags to delete", count($deleteable)));
        ksort($deleteable);
        return $deleteable;
    }

    /**
     * @return int
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function deleteImageTags(array $imageTags) : int
    {
        $deletedCount = 0;
        foreach($imageTags as $digest => $imageTagGroup) {
            $deleteRequest = [
                'dry_run' => $this->dryRun,
                'active_from' => $this->timeInPast->format("Y-m-d\TH:i:s\Z"),
                'manifests' => array_map(function (ImageTag $imageTag) {
                    return [
                        'repository' => $this->getRepository(),
                        'digest' => $imageTag->getDigest(),
                    ];
                }, $imageTagGroup),
                'ignore_warnings' => [[
                    'repository' => $this->getRepository(),
                    'digest' => $digest,
                    'warning' => 'current_tag',
                    'tags' => array_map(function (ImageTag $imageTag) {
                        return $imageTag->getTag();
                    }, $imageTagGroup),
                ]]
            ];

            try {
                $deleteResponse = $this->getGuzzle()->post(
                    sprintf(
                        "namespaces/%s/delete-images",
                        $this->getNamespace(),
                    ),
                    [
                        'headers' => ['Authorization' => "Bearer {$this->getToken()}"],
                        'json' => $deleteRequest
                    ]
                );

                if($deleteResponse->getStatusCode() == 429){
                    $this->getLogger()->debug("Run out of rate limit.. waiting until we're allowed to make more calls...");
                    time_sleep_until($deleteResponse->getHeader('x-ratelimit-reset')[0]);
                    // Do it again.
                    $deleteResponse = $this->getGuzzle()->post(
                        sprintf(
                            "namespaces/%s/delete-images",
                            $this->getNamespace(),
                        ),
                        [
                            'headers' => ['Authorization' => "Bearer {$this->getToken()}"],
                            'json' => $deleteRequest
                        ]
                    );
                }
                $rateLimitRemaining = $deleteResponse->getHeader('x-ratelimit-remaining')[0];
                $deleteResponse = json_decode($deleteResponse->getBody()->getContents(), true);

                $this->getLogger()->debug(sprintf("> Removed %d tags, (request limit remaining: %d)", $deleteResponse['metrics']['tag_deletes'], $rateLimitRemaining));
                $deletedCount = $deletedCount + $deleteResponse['metrics']['tag_deletes'];

            } catch (ClientException $exception) {
                $exceptionResponse = json_decode($exception->getResponse()->getBody()->getContents(),true);
                if(isset($exceptionResponse['errinfo']['details']['warnings'])) {
                    foreach ($exceptionResponse['errinfo']['details']['warnings'] as $warning) {
                        if($warning['warning'] == 'is_active'){
                            $this->getLogger()->notice(sprintf("> Not removing %s@%s, it is still marked active by docker hub", $warning['repository'], $warning['digest']));
                        }else{
                            if($this->debug) \Kint::dump($deleteRequest, $exceptionResponse);
                            $this->getLogger()->warning(sprintf("> Failed to remove tags: %s",$exceptionResponse['message']));
                        }
                    }
                }
            }
        }
        return $deletedCount;
    }
    public function run(){
        $this->login();
        $imageTags = $this->listImages();
        $deletableImageTags = $this->combImagesForDeletion($imageTags);
        $numberActuallyDeleted = $this->deleteImagetags($deletableImageTags);
        $this->getLogger()->info(sprintf("Found %d images, %d of which are deletable and %d were deleted", count($imageTags), count($deletableImageTags), $numberActuallyDeleted));
    }
}