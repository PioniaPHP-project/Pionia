Welcome to Pionia migration to v2.0.0. This is a major release that includes a lot of changes. 
Please read the following guide to understand what has changed and how to migrate your application to the new version.

---

Author: @pionia - 2024

By: Jet Ezra - ezrajet9@gmail.com

---

### Structure

The structure of the new framework is as follows:

``` 
src
|--Pionia/ -- (The core of the framework)
   |--README.md
   |--Auth/ -- (Authentication and Authorization)
   |   |--AuthenticationBackend.php
   |   |--AuthenticationChain.php
   |   |--ContextUserObject.php
   |--Base/ -- (Base Framework classes)
   |   |--PioniaApplication.php
   |--Console/ -- (Commands)
   |   |--Command.php
   |--Contracts/ -- (Interfaces)
   |   |--AuthenticationChainContract.php
   |   |--AuthenticationContract.php
   |   |--CommandContract.php
   |   |--CorsContract.php
   |   |--KernelContract.php
   |   |--MiddlewareContract.php
   |   |--ServiceContract.php
   |--Cors/ -- (Cross-Origin Resource Sharing)
   |   |--PioniaCors.php
   |--Http/ -- (HTTP classes and cycles)
   |   |--Base/
   |   |   |--WebKernel.php
   |   |--Request/
   |   |   |--Request.php
   |   |--Response/
   |   |   |--Response.php
   |   |   |--BaseResponse.php
   |   |--Routing/
   |   |   |--BaseRoutes.php
   |   |   |--PioniaRouter.php
   |   |   |--SupportedHttpMethods.php
   |   |--Services/
   |       |--AuthTrait.php
   |       |--BaseService.php
   |       |--RequestActionTrait.php
   |--Logging/ -- (Logging)
   |   |--PioniaLogger.php
   |--Middlewares/ -- (Middleware classes)
   |   |--Middleware.php
   |   |--MiddlewareChain.php
   |   |--MiddlewareTrait.php
   |--Tests/ -- (Unit tests base classes)
   |   |--PioniaTest.php
   |--Utils/ -- (Utility classes)
   |   |--AppHelpersTrait.php
   |   |--ApplicationLifecycleHooks.php
   |   |--Arrayable.php
   |   |--Containable.php
   |   |--EnvResolver.php
   |   |--Microable.php
   |   |--PathsTrait.php
   |   |--PioniaApplicationType.php
   |--Validation/ -- (Validation classes)
       |--ValidationTrait.php
       |--Validator.php
