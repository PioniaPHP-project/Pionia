<?php

namespace Pionia\Http\Response;

/**
 * This is the internal response. It should not be used anywhere in the project but in the core framework
 *
 * It extends the Symfony response object to add more functionality to the response object.
 *
 * Its job is to make the request confirm to the normal http response object.
 * It sets all requests to return a 200 OK status code. Coz all requests are expected to be successful
 * if they have reached the application.
 *
 * @author [Jet - ezrajet9@gmail.com](https://www.linkedin.com/in/jetezra/)
 */
class Response extends \Symfony\Component\HttpFoundation\Response {}
