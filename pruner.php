#!/usr/bin/php
<?php
use DockerImagePruner\Cli;

require_once("vendor/autoload.php");

(new Cli())->run();