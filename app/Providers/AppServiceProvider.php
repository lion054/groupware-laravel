<?php

namespace App\Providers;

use GraphAware\Neo4j\Client\ClientBuilder;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('neo4j', function ($app) {
            $host = config('database.connections.neo4j.host');
            $port = config('database.connections.neo4j.port');
            $username = config('database.connections.neo4j.username');
            $password = config('database.connections.neo4j.password');

            return ClientBuilder::create()
                ->addConnection('default', "http://$username:$password@$host:$port")
                ->build();
        });
    }
}
