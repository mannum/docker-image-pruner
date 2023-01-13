<?php

namespace DockerImagePruner;
use Bramus\Monolog\Formatter\ColoredLineFormatter;
use Garden\Cli\Args;
use Garden\Cli\Cli as GardenCli;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Spatie\Emoji\Emoji;


class Cli{
    protected Logger $logger;
    protected GardenCli $cli;
    protected Args $args;

    public function __construct(){
        $environment = array_merge($_ENV, $_SERVER);
        ksort($environment);

        $this->cli = new GardenCli();
        $this->cli
            ->opt('dockerhub', 'Enable pruning docker hub images')
            ->opt('github', 'Enable pruning github images')
            ->opt('username', 'username')
            ->opt('pat', 'Personal Access Token')
            ->opt('namespace', 'Name space')
            ->opt('repository', 'Repo')
            ->opt('dry-run', 'Do not actually prune things')
        ;

        $this->args = $this->cli->parse($environment['argv'], true);

        $this->logger = new Logger('syncer');
        //$this->logger->pushHandler(new StreamHandler('/var/log/image-pruner.log', Logger::DEBUG));
        $stdout = new StreamHandler('php://stdout', Logger::DEBUG);
        $stdout->setFormatter(new ColoredLineFormatter(null, "%level_name%: %message% \n"));
        $this->logger->pushHandler($stdout);

    }
    public function run(){
        if ($this->args->hasOpt('github')) {
            $this->logger->info(sprintf(' %s  Running Github Pruner', Emoji::recyclingSymbol()));
        }elseif ($this->args->hasOpt('dockerhub')) {
            $this->logger->info(sprintf(' %s  Running Docker Hub Pruner', Emoji::recyclingSymbol()));
            if(!$this->args->hasOpt('namespace') || !$this->args->hasOpt('repository')){
                $this->logger->critical("You must provide \"namespace\" and \"repository\" flags.");
                exit(1);
            }
            (new DockerHubPruner(
                $this->logger,
                $this->args->getOpt('username'),
                $this->args->getOpt('pat'),
                $this->args->getOpt('namespace'),
                $this->args->getOpt('repository'),
                $this->args->hasOpt('dry-run'),
            ))->run();
        }else{
            $this->logger->critical("You must either provide docker_hub_token or github_token.");
            exit(1);
        }
    }
}
