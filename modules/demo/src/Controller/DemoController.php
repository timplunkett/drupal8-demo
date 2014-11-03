<?php
/**
 * @file
 * Contains \Drupal\demo\Controller\DemoController.
 */

namespace Drupal\demo\Controller;

class DemoController
{
    /**
     * @var UserRepository
     */
    private $userRepository;

    public function __construct(UserRepository $userRepository) {
        $this->userRepository = $userRepository;
    }

    public function create(ContainerInterface $container) {
        return new static($container->get('demo.drupal_user_repository'));
    }

    /**
     * Generates example page
     */
    public function demo()
    {
        var_dump($this->userRepository);exit;
        return array(
            '#markup' => 'Hello Drupalists! This is a demo page.',
        );
    }
} 
