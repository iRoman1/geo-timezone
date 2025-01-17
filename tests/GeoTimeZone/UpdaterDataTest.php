<?php

namespace Tests\GeoTimeZone;
use GeoTimeZone\UpdaterData;

include __DIR__ . "/../../vendor/autoload.php";


class UpdaterDataTest
{
    public function main()
    {
        try {
            $updater = new UpdaterData(__DIR__ . DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."data".DIRECTORY_SEPARATOR);
            $updater->updateData();
        } catch (\ErrorException $error) {
            echo $error->getMessage();
        }
        
    }
}

$test = new UpdaterDataTest();
$test->main();