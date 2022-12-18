<?php

namespace UseTheFork\LaravelElasticsearchModel;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use UseTheFork\LaravelElasticsearchModel\Commands\LaravelElasticsearchModelCommand;
use UseTheFork\LaravelElasticsearchModel\Database\Connection;
use UseTheFork\LaravelElasticsearchModel\Model;

class LaravelElasticsearchModelServiceProvider extends PackageServiceProvider
{
    public function bootingPackage()
    {
        Model::setConnectionResolver($this->app["db"]);
        Model::setEventDispatcher($this->app["events"]);
    }

    public function registeringPackage()
    {
        Connection::resolverFor("elasticsearch", function (
            $pdo,
            $database,
            $prefix,
            $config
        ) {
            return new Connection($pdo, $database, $prefix, $config);
        });
    }

    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name("laravel-elasticsearch-model")
            ->hasConfigFile()
            ->hasViews()
            ->hasCommand(LaravelElasticsearchModelCommand::class);
    }
}
