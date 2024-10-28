<?php

namespace Pionia\Request;

use Pionia\Core\Helpers\ContextUserObject;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\FileBag;

/**
 *
 * This method extends the Symfony request class to add more functionality to the request object.
 *
 * All methods on the request object are still available in this class. But more methods have been added to the request object.
 *
 * @property bool $authenticated Whether the request is authenticated or not
 * @property mixed $context The entire app context object
 * @property ContextUserObject|null $auth The currently logged user in context object
 *
 * @author [Jet - ezrajet9@gmail.com](https://www.linkedin.com/in/jetezra/)
 */
class Request extends \Symfony\Component\HttpFoundation\Request
{
     private bool $authenticated = false;
     private mixed  $context = null;

     private ContextUserObject | null $auth = null;

    /**
     * The currently logged user in context object
     * @return ContextUserObject|null The currently logged in user object or null if no user is logged in
     */
    public function getAuth(): ?ContextUserObject
    {
        return $this->auth;
    }

    /**
     * This is the entire app context object, it even contains the authentication object itself.
     * @return mixed
     */
    public function getContext(): mixed
    {
        return $this->context;
    }


    /**
     * This method checks if the request is authenticated
     * @return bool Whether the request is authenticated or not
     */
    public function isAuthenticated(): bool
    {
        return $this->authenticated || ( $this->auth && $this->auth->authenticated);
    }


    /**
     * This method sets the authentication context for the request
     * @param ContextUserObject $userObject
     * @return $this
     */
    public function setAuthenticationContext(ContextUserObject $userObject): static
    {
        if ($userObject->user){
            $userObject->authenticated = true;
            $this->authenticated = true;
        }
        $this->auth = $userObject;
        return $this;
    }


    /**
     * This method add data to the context object
     * @param array $contextData The context data to be added to the request
     * @return $this
     */
    private function setAppContext(array $contextData = []): static
    {
        $contextUser = new ContextUserObject();

        // if the dev has marked the request as authenticated
        if ($contextData['user']){
            $contextUser->user = $contextData['user'];
            $contextUser->authenticated = true;
            if ($contextData['authExtra']){
                $contextUser->authExtra = $contextData['authExtra'];
            }
            if ($contextData['permissions']) {
                $contextUser->permissions = $contextData['permissions'];
            }
            $this->setAuthenticationContext($contextUser);
        }

        $this->context = array_merge($this->context, $contextData);
        return $this;
    }

    /**
     * Merges data sent from the client as json and form data as one array where one can access all the request data.
     *
     * This implies that this request is safe for both json and form data scenarios
     * @return array
     */
    public function getData(): array
    {
        return $this->getPayload()->all();
    }

    /**
     * Returns the file from the request if the request was submitted as form data
     * @param $fileName
     * @return FileBag|null
     */
    public function getFileByName($fileName) : ?UploadedFile
    {
        if ($this->getContentTypeFormat() === 'form') {
            return $this->files->get($fileName);
        }
        return null;
    }

}
