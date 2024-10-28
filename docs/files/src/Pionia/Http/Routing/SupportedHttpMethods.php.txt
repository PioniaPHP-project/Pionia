<?php

namespace Pionia\Http\Routing;

/**
 * This class holds the supported http methods in the framework.
 *
 * Only GET methods are for checking the server status. The rest are of the request should be POST only
 *
 * @author [Jet - ezrajet9@gmail.com](https://www.linkedin.com/in/jetezra/)
 * */
abstract class SupportedHttpMethods
 {
     const POST = 'POST';
     const GET = 'GET';
 }
