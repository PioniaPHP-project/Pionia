<?php

namespace Pionia\Http\Request;

use Pionia\Auth\ContextUserObject;
use Pionia\Base\PioniaApplication;
use Pionia\Collections\Arrayable;
use Pionia\Utils\Microable;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\FileBag;

/**
 *
 * This method extends the Symfony request class to add more functionality to the request object.
 *
 * All methods on the request object are still available in this class. But more methods have been added to the request object.
 *
 * @property bool $authenticated Whether the request is authenticated or not
 * @property ContextUserObject|null $auth The currently logged user in context object
 *
 * @author [Jet - ezrajet9@gmail.com](https://www.linkedin.com/in/jetezra/)
 */
class Request extends \Symfony\Component\HttpFoundation\Request
{

    use Microable;

    private PioniaApplication $app;

     private bool $authenticated = false;

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
        if (!empty($userObject->user)) {
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
    private function setArrayContext(array $contextData): static
    {
        $contextUser = $this->auth ?? new ContextUserObject();

        // if the dev has marked the request as authenticated
        if ($contextData['user']) {
            $contextUser->user = $contextData['user'];
            unset($contextData['user']);
            $contextUser->authenticated = $contextData['authenticated'] ?? true;
        }

        if (isset($contextData['authExtra'])){
            $contextUser->authExtra = $contextData['authExtra'];
            unset($contextData['authExtra']);
        }

        if (isset($contextData['permissions'])) {
            $contextUser->permissions = $contextData['permissions'];
            unset($contextData['permissions']);
        }

        $contextUser->authExtra = array_merge($contextUser->authExtra, $contextData);

        $this->setAuthenticationContext($contextUser);

        return $this;
    }

    /**
     * Merges data sent from the client as json and form data as one array where one can access all the request data.
     *
     * This implies that this request is safe for both json and form data scenarios
     * @return Arrayable
     */
    public function getData(): Arrayable
    {
        return arr($this->cookies->all())
            ->merge($this->query->all())
            ->merge($this->files->all())
            ->merge($this->getPayload()->all());
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

    public function setApplication(PioniaApplication $application): void
    {
        $this->app = $application;
    }

    public function getApplication(): PioniaApplication
    {
        return $this->app;
    }
}
