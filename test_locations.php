<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';

$locations = \App\Models\Location::all();
foreach($locations as $l) {
    echo $l->id . ' - ' . $l->name . "\n";
}
