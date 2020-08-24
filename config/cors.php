<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Lumen CORS Options
    |--------------------------------------------------------------------------
    */

    /*
     * Origins that are allowed to perform requests, defaults to an empty array. Patterns also accepted, for example *.foo.com
     */
    'allow_origins' => ['*'],

    /*
    * HTTP methods that are allowed, defaults to an empty array
    */
    'allow_methods' => ['*'],

    /*
     * HTTP headers that are allowed, defaults to an empty array
     */
    'allow_headers' => ['*'],

    /*
     * Whether or not the response can be exposed when credentials are present, defaults to false
     */
    'allow_credentials' => false,

    /*
     * HTTP headers that are allowed to be exposed to the web browser, defaults to an empty array
     */
    'expose_headers' => [],

    /*
     * Indicates how long preflight request can be cached, defaults to 0
     */
    'max_age' => 0,
];
