### dgfip-si1/application

## Application Hello world

**test.php**
~~~
#!/usr/bin/env php
<?php

use \DgfipSI1\Application\Application;
$classLoader = require __DIR__.'/vendor/autoload.php';

$input = new \Symfony\Component\Console\Input\ArgvInput($argv);
$app = new Application($input);
$app->setApplicationName("test application");
$app->setApplicationVersion("1.0");
$app->setRoboCommands([ \test_app\RoboFile::class]);
$app->finalize();
$statusCode = $app->run(); 

exit($statusCode);
~~~

**RoboFile.php**
~~~
<?php
namespace test_app;

class RoboFile extends \Robo\Tasks
{
    public function helloTest()
    {
        echo "Hello !\n";
    }
}
~~~
**composer.json**
~~~
{
  "name" : "dgfip/test_app",
  "type" : "project",
  "require" : {
    "dgfip-si1/application": ">=1"
  },	
  "minimum-stability" : "alpha",
  "autoload" : {
    "psr-4" : {
      "test_app\\" : "."
    }
  }
}
~~~

