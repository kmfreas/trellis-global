#! /usr/bin/env php

<?php

use TrellisHelper\NewSite;
use TrellisHelper\ProvisionServer;
use Symfony\Component\Console\Application;

require 'vendor/autoload.php';

$app = new Application('Trellis Helper', '1.0');

$app->add(new NewSite);
$app->add(new ProvisionServer);

$app->run();
