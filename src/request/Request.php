<?php

namespace jetPhp\request;

use jetPhp\core\helpers\ContextUserObject;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\FileBag;

class Request extends \Symfony\Component\HttpFoundation\Request
{
     private bool $authenticated = false;
     private mixed  $context = null;
     private ContextUserObject | null $auth = null;

    public function getAuth(): ?ContextUserObject
    {
        return $this->auth;
    }

    public function getContext(): mixed
    {
        return $this->context;
    }

    public function isAuthenticated(): bool
    {
        return $this->authenticated || ( $this->auth && $this->auth->authenticated);
    }


    public function setAuthenticationContext(ContextUserObject $userObject)
    {
        if ($userObject->user){
            $userObject->authenticated = true;
            $this->authenticated = true;
        }
        $this->auth = $userObject;
        return $this;
    }


    private function setAppContext($contextData = []): static
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
     * Returns the json data from the request if the request was submitted as json
     * @return array
     */
    public function getJsonData(): array
    {
        if ($this->getContentTypeFormat() === 'json') {
            return $this->toArray();
        }
        return [];
    }

    /**
     * Merges data sent from the client as json and form data as one array where one can access all the request data.
     *
     * This implies that this request is safe for both json and form data scenarios
     * @return array
     */
    public function getData()
    {
        $json = $this->getJsonData()??[];
        $form = $this->getFormData()??[];
        return array_merge($json, $form);
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

    /**
     * Returns the data if the request was submitted as form data
     * @return array
     */
    public function getFormData(): array
    {
        if ($this->getContentTypeFormat() === 'form') {
            return $this->request->all() ?? [];
        }
        return [];
    }

}
