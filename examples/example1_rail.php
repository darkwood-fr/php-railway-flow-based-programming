<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use Amp\Loop;
use Amp\Delayed;
use RFBP\Rail;
use Symfony\Component\Messenger\Envelope as IP;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;

$job1 = static function (object $data): \Generator {
    printf("*. #%d : Calculating number %d\n", $data['id'], $data['number']);

    // simulating calculating some "light" operation from 10 to 90 milliseconds
    $delay = random_int(1, 9) * 10;
    yield new Delayed($delay);
    $result = $data['number'];
    $result += $result;

    printf("*. #%d : Result for number %d is %d and took %d milliseconds\n", $data['id'], $data['number'], $result, $delay);

    $data['number'] = $result;

    return $data;
};


$job2 = static function (object $data): \Generator {
    printf(".* #%d : Calculating number %d\n", $data['id'], $data['number']);

    // simulating calculating some "heavy" operation from 100 to 900 milliseconds
    $delay = random_int(1, 9) * 100;
    yield new Delayed($delay);
    $result = $data['number'];
    $result *= $result;

    printf(".* #%d : Result for number %d is %d and took %d milliseconds\n", $data['id'], $data['number'], $result, $delay);

    $data['number'] = $result;

    return $data;
};

$rails = [
    new Rail($job1, 2),
    new Rail($job2, 4),
];

$ipPool = new SplObjectStorage();
$rails[0]->pipe(static function($ip) use ($ipPool) {
    $ipPool->offsetSet($ip, 1);
});
$rails[1]->pipe(static function($ip) use ($ipPool) {
    $ipPool->offsetUnset($ip);
});

for($i = 1; $i < 5; $i++) {
    $ip = IP::wrap(new ArrayObject(['id' => $i, 'number' => $i]), [new TransportMessageIdStamp(uniqid('ip_', true))]);
    $ipPool->offsetSet($ip, 0);
}

Loop::repeat(1, static function() use ($rails, $ipPool) {
    foreach ($ipPool as $ip) {
        $index = $ipPool[$ip];
        $rails[$index]($ip);
    }

    if($ipPool->count() === 0) {
        Loop::stop();
    }

    //echo "*** tick ***\n";
});

Loop::run();
