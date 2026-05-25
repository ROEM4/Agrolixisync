<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::create('/api/historian/daily?location_id=1&days=30', 'GET');
$response = $kernel->handle($request);
echo $response->getContent();
