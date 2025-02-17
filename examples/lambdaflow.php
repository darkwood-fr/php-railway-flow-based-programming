<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Flow\Driver\AmpDriver;
use Flow\Driver\FiberDriver;
use Flow\Driver\ParallelDriver;
use Flow\Driver\ReactDriver;
use Flow\Driver\SpatieDriver;
use Flow\Driver\SwooleDriver;
use Flow\Examples\Model\YFlowData;
use Flow\Job\LambdaJob;

$driver = match (random_int(3, 3)) {
    1 => new AmpDriver(),
    2 => new ReactDriver(),
    3 => new FiberDriver(),
    4 => new SwooleDriver(),
    // 5 => new SpatieDriver(),
    // 6 => new ParallelDriver(),
};

$factorialYJob = static function ($factorial) {
    return static function (YFlowData $data) use ($factorial): YFlowData {
        return new YFlowData(
            $data->id,
            $data->number,
            ($data->result <= 1) ? 1 : $data->result * $factorial(new YFlowData($data->id, $data->number, $data->result - 1))->result
        );
    };
};

// $job = new \Flow\Job\LambdaJob('λa.a', fn($a) => $a);
// $job = new \Flow\Job\LambdaJob('λa.λb.(a b)', fn($a) => $a);
// $job = new \Flow\Job\LambdaJob('λa.λb.λc.(a (b c))', fn($a) => $a);
// $job = new \Flow\Job\LambdaJob('λa.λb.λc.λd.(a (b (c d)))', fn($a) => $a);

// $job = new \Flow\Job\LambdaJob('λf.(λx.f (x x)) (λx.f (x x))');
// $job = new \Flow\Job\LambdaJob('λab.a(b)');
// $job = new \Flow\Job\LambdaJob('λabcd.a(b(c(d)))');

// $job = new \Flow\Job\LambdaJob('(λy.(λu.y λx.(u u)) ((λx.λu.x (λy.y (x z))) λu.((x x) λu.y)))', $factorialYJob);
// $job = new \Flow\Job\LambdaJob('(λf.(λx.f (x x)) (λx.f (x x)))', $factorialYJob);

// $job = new \Flow\Job\LambdaJob('(λa.(λb.(a (b b))) (λb.(a (b b))))', $factorialYJob);
$job = new LambdaJob('(λf.((λx.f (λy.(x x) y)) (λx.f (λy.(x x) y))))', $factorialYJob);

$result = $job(new YFlowData(1, 6));
dd($result);
