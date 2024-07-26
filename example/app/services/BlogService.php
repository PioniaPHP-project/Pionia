<?php

namespace application\services;

use Pionia\Generics\UniversalGenericService;

class BlogService extends UniversalGenericService
{
    public string $table = 'article';

//    public ?array $relationColumns = ["article.id", "first_name", "last_name", "username", "title", "body", "article.created_at"];
//    public ?array $createColumns = ["title", "body"];
//    public array $actionsRequiringAuth = ['create', 'update', 'delete'];
//    public function preCreate(?array $createItem = null): array|bool|null
//    {
//        $user = $this->auth()->user;
//        if ($createItem) {
//            $createItem['author'] = $user->id;
//        }
//
//        return $createItem;
//    }
//
//    /**
//     * @throws Exception
//     */
//    public function preUpdate(?array $updateItem = null): array|bool|null
//    {
//        $user = $this->auth()->user;
//
//        if ($updateItem["author"] != $user->id) {
//            throw new Exception("Only authors of articles can update them");
//        }
//        return $updateItem;
//    }
//
////    public function getItems(): ?array
////    {
////        return Porm::from($this->table)->getDatabase()
////        ->select(
////            $this->table,
////            ["[>]system_user" => ["author" => "id"]],
////            $this->relationColumns);
////    }
//
//    public function preDelete(object|array|null $itemToDelete = null): array|null|object|bool
//    {
//        $user = $this->auth()->user;
//        if ($itemToDelete->author !== $user->id){
//            throw new Exception("Only authors of articles can delete them");
//        }
//        return $itemToDelete;
//    }
}
