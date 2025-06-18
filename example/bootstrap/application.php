<?php
require __DIR__ . '/../../vendor/autoload.php';

use Pionia\Realm\AppRealm;


$application = AppRealm::create(__DIR__);



return $application;

//
///**
// * ---------------------------------------------------------------
// * APPLICATION & CONTAINER SETUP
// * ---------------------------------------------------------------
// *
// * This file is the entry point for the application. It is responsible for
// * bootstrapping the application and returning the application instance.
// *
// * You can hook into the application lifecycle by adding your code here.
// * Multiple hooks are available for you to use.
// *
// * i) withCacheAdaptor(callable $callback) : This can be used to register a different cache adaptor.
// * By default, the application uses the file cache adaptor. All symfony cache adaptors are supported.
// *
// * ii) setLogger(LoggerInterface $logger) : This can used to set a custom logger for the application.
// * By default, the application uses the Monolog logger.
// *
// * iii) booted(Closure $closure) : This can be used to register a callback to be run after the application is booted.
// * This method can be called multiple times to add more hooks.
// *
// * iv) booting(Closure $callback) : This can be used to register hooks to mutate the application booting cycle.
// * Runs before the application is booted. Can be called multiple times to add more hooks.
// *
// * v) terminating(Closure $callback) : This can be used to register the application's terminating hooks.
// * All logic that needs to run before the application is terminated. Can be called multiple times to add more hooks.
// *
// * vi) terminated(Closure $closure) : This can be used to register the application's terminated hook listeners.
// * All logic that needs to run after the application is terminated. Can be called multiple times to add more hooks.
// *
// * Realm bindings can be added here as well.
// *
// * ---------------------------------------------------------------
// *
// * The application instance is returned at the end of the file.
// *
// */
//
//
///**
// * ---------------------------------------------------------------
// * Setup the application start time if not already defined
// * ---------------------------------------------------------------
// */
//if (!defined(constant_name: 'PIONIA_START')) {
//    define('PIONIA_START', microtime(true));
//}
//
///**
// * ---------------------------------------------------------------
// * Define the base path
// * ---------------------------------------------------------------
// */
//if (!defined('BASEPATH')) {
//    define('BASEPATH', dirname(__DIR__, 1));
//}
//
///**
// * ---------------------------------------------------------------
// * Load the composer autoloader
// * ---------------------------------------------------------------
// */
//include BASEPATH . '/../vendor/autoload.php';
//
///**
// * ---------------------------------------------------------------
// * Create the application instance
// * ---------------------------------------------------------------
// */
//$app = new WebApplication(BASEPATH);
//
//$app::watchErrors();
///**
// * ---------------------------------------------------------------
// * Register the app providers
// * ---------------------------------------------------------------
// * App providers are packages that do not only target to serve Pionia, but also,
// * target to mutate the lifecycle and normal operations of the Application instance.
// *
// * These can be registered in the any .ini file or below using the following method.
// *
// * This method can be called as many times as possible to add more providers.
// */
////$app->addAppProvider(SamplePackageProvider::class);
////    ->addAppProvider(SamplePackageProvider::class);
//
//
///**
// * ---------------------------------------------------------------
// * Register the application's lifecycle hooks
// * ---------------------------------------------------------------
// *
// * This is where you can hook into the application's lifecycle.
// *
// */
//
////$app->booting(function () {
////    // Add your booting hooks here
////});
//
////$app->booted(function ($app) {
////    // Add your booted hooks here
////});
//
////$app->terminating(function ($app) {
////    // Add your terminating hooks here
////});
//
////$app->terminated(function () {
////    // Add your terminated hooks here
////});
//
//$app
//    //->httpsOnly()
//    ->blockedOrigins(['http://localhost:4200']);
//
///**
// * ---------------------------------------------------------------
// * Return the application instance
// * ---------------------------------------------------------------
// * This must stay as the last line of the file.
// */
//return $app;
