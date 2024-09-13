<?php

namespace Pionia\Http\Services\Generics\Contracts;

/**
 * Events run before and after the create, update and delete actions.
 * They provide the developer with the ability to modify the data before saving or perform any action before or after saving.
 * These are only available in the services that use the GenericService and are action methods defined in the mixins
 */

use PDOStatement;
use Porm\Database\Builders\Join;

trait EventsContract
{
    /**
     * This method is called before the create action, use it to transform the data before saving.
     *
     * You have access to the object being updated $updateItem
     *
     * Return false, null or raise any exception to stop the update action.
     *
     * @param array|null $updateItem The data being updated - You can modify this data before saving
     * @return array|bool|null The return data is what is saved to the database, if false or null, the update action is aborted
     */
    public function preUpdate(?array $updateItem = null): array | bool|null
    {
        return $updateItem;
    }

    /**
     * This method is called before the create action, use it to transform the data before saving.
     *
     * You have access to the data being created in the `$this->request->getData()` method
     *
     * Return false, null or throw an exception to stop the create action.
     * @param array|null $createItem The data being created - You can modify this data before saving. Return false or null to stop the create action
     */
    public function preCreate(?array $createItem = null): array | bool|null
    {
        return $createItem;
    }

    /**
     * This method is called before the delete action. Use it to perform any action before deleting.
     *
     * You have access to the data being updated in the `$this->request->getData()` method
     *
     * Return false, null or throw an exception to stop the delete action.
     */
    public function preDelete(array | null | object $itemToDelete = null): array|null|object|bool
    {
        return $itemToDelete;
    }

    /**
     * This method is post create action, use it to perform any action after saving.
     *
     * You have access to the data being created in the `$this->request->getData()` method
     *
     * Whatever is returned here is what is returned to the user
     */
    public function postCreate(array | object | null $createdItem = null): object|array|null
    {
        return $createdItem;
    }

    /**
     * This method is post create action, use it to perform any action after saving.
     *
     * You have access to the data being created in the `$this->request->getData()` method
     *
     * Whatever is returned here is what is returned to the user
     */
    public function postUpdate(array | object | null $updatedItem = null): object|array|null
    {
        return $updatedItem;
    }


    /**
     * This method is called before the delete action. Use it to perform any action before deleting.
     *
     * You have access to the data being updated in the `$this->request->getData()` method
     *
     * This has no effect on the delete action. Since it is called after the delete action.
     *
     * Whatever is returned here is what is returned to the user
     */
    public function postDelete(PDOStatement $deleteInstance, array | null | object $deletedItem = null): mixed
    {
        return $deletedItem;
    }


    /**
     * Override this in your service to define the basis to return single item details
     * @return null|object
     */
    public function getItem(): ?object
    {
        return null;
    }

    /**
     * Override this in your service to define the basis to return multiple items from the database
     * @return null|object
     */
    public function getItems(): ?array
    {
        return null;
    }

    /**
     * Override this in your service to define the basis to return multiple items from the database
     * @return null|object
     */
    public function getJoinQuery(): ?Join
    {
        return null;
    }
}

