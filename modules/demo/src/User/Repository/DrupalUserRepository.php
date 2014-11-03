<?php

namespace Drupal\demo\User\Repository;

class DrupalUserRepository implements UserRepository
{
    /**
     * @var QueryFactory
     */
    private $storage;

    public function __construct(QueryFactory $storage)
    {
        $this->storage = $storage;
    }

    public function getAll() {
        var_dump($this->storage);exit;
    }
} 
