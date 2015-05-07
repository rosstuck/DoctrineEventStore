<?php
require __DIR__ . '/bootstrap.php';

return Doctrine\ORM\Tools\Console\ConsoleRunner::createHelperSet($entityManager);
