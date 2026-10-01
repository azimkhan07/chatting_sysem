<?php

use App\Providers\AppServiceProvider;
use App\Providers\ChatFeatureCatalogueServiceProvider;

return [
    AppServiceProvider::class,
    // Materialises the ChatFeature enum into the `features` table the admin
    // console's subscription form reads. Registered here rather than in
    // AppServiceProvider because it is a data concern, not a framework one.
    ChatFeatureCatalogueServiceProvider::class,
];
