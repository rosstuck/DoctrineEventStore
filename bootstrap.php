<?php

require_once __DIR__ . '/vendor/autoload.php';

// Create the EntityManager we'll use to communicate with the database
return \Doctrine\ORM\EntityManager::create(
    require 'example/config.php',
    \Doctrine\ORM\Tools\Setup::createAnnotationMetadataConfiguration([__DIR__ . '/example'], true, null, null, false)
);
