# Vehicle Configuration Matcher

This repository contains a Laravel module responsible for matching vehicle models
and configurations from external provider APIs to an internal vehicle catalog.

The module works by:

- generating token sets from incoming model/config names
- comparing them with stored catalog tokens
- scoring possible matches
- selecting the best matching model and configuration
- creating missing entities when no match is found

This is NOT a full Laravel application.

Only core domain logic is included for demonstration purposes.
Code is extracted and simplified from a production system.

use this
$this->app->tag([
   NeoVehicleCatalogProvider::class,
   KapitalVehicleCatalogProvider::class,
   InsonVehicleCatalogProvider::class,
], 'vehicle.providers');


$this->app->when(VehicleSyncService::class)
    ->needs('$providers')
    ->giveTagged('vehicle.providers');
