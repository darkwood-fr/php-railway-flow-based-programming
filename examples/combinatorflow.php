<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Flow\AsyncHandler\DeferAsyncHandler;
use Flow\Driver\AmpDriver;
use Flow\Driver\FiberDriver;
use Flow\Driver\ParallelDriver;
use Flow\Driver\ReactDriver;
use Flow\Driver\SpatieDriver;
use Flow\Driver\SwooleDriver;
use Flow\Examples\Model\YFlowData;
use Flow\Flow\CombinatorFlow;
use Flow\Flow\YFlow;
use Flow\FlowFactory;
use Flow\Ip;
use Flow\Job\YJob;
use Flow\JobInterface;

$driver = match (random_int(3, 3)) {
    1 => new AmpDriver(),
    2 => new ReactDriver(),
    3 => new FiberDriver(),
    4 => new SwooleDriver(),
    // 5 => new SpatieDriver(),
    // 6 => new ParallelDriver(),
};

//$job = new \Flow\Job\LambdaJob('λf.(λx.f (x x)) (λx.f (x x))');
//$job = new \Flow\Job\LambdaJob('λa.a');
//$job = new \Flow\Job\LambdaJob('λab.a(b)');
//$job = new \Flow\Job\LambdaJob('λabcd.a(b(c(d)))');

$lambda = new \Flow\Job\LambdaJob('λf.(λx.f (x x)) (λx.f (x x))');

$result = $job(5);
dd($result);