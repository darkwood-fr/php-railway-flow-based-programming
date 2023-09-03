<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Doctrine\DBAL\DriverManager;
use Flow\Driver\AmpDriver;
use Flow\Driver\ReactDriver;
use Flow\Driver\SwooleDriver;
use Flow\Examples\Transport\DoctrineIpTransport;
use Flow\Flow\Flow;
use Flow\Flow\TransportFlow;
use Flow\IpStrategy\MaxIpStrategy;

$randomDriver = random_int(1, 3);

if ($randomDriver === 1) {
    printf("Use AmpDriver\n");

    $driver = new AmpDriver();
} elseif ($randomDriver === 2) {
    printf("Use ReactDriver\n");

    $driver = new ReactDriver();
} else {
    printf("Use SwooleDriver\n");

    $driver = new SwooleDriver();
}

$addOneJob = static function (object $data) use ($driver): void {
    printf("Client %s #%d : Calculating %d + 1\n", $data['client'], $data['id'], $data['number']);

    // simulating calculating some "light" operation from 0.1 to 1 seconds
    $delay = random_int(1, 10) / 10;
    $driver->delay($delay);
    $data['number']++;
};

$multbyTwoJob = static function (object $data) use ($driver): void {
    printf("Client %s #%d : Calculating %d * 2\n", $data['client'], $data['id'], $data['number']);

    // simulating calculating some "heavy" operation from from 1 to 3 seconds
    $delay = random_int(1, 3);
    $driver->delay($delay);

    // simulating 1 chance on 3 to produce an exception from the "heavy" operation
    if (1 === random_int(1, 3)) {
        throw new Error('Failure when processing "Mult by two"');
    }

    $data['number'] *= 2;
};

$minusThreeJob = static function (object $data): void {
    printf("Client %s #%d : Calculating %d - 3\n", $data['client'], $data['id'], $data['number']);

    // simulating calculating some "instant" operation
    $data['number'] -= 3;
};

$errorJob = static function (object $data, Throwable $exception): void {
    printf("Client %s #%d : Exception %s\n", $data['client'], $data['id'], $exception->getMessage());

    $data['number'] = null;
};

$flow = (new Flow($addOneJob, $errorJob, new MaxIpStrategy(1), $driver))
    ->fn(new Flow($multbyTwoJob, $errorJob, new MaxIpStrategy(3), $driver))
    ->fn(new Flow($minusThreeJob, $errorJob, new MaxIpStrategy(2), $driver))
;

$connection = DriverManager::getConnection(['url' => 'mysql://root:root@127.0.0.1:3306/flow?serverVersion=8.0.31']);
$transport = new DoctrineIpTransport($connection);

new TransportFlow(
    $flow,
    $transport,
    $transport,
    $driver
);

$driver->start();
