<?php
    
    use include\common\SplClassLoader;
    
    require (__DIR__ . '/../../../../../SplClassLoader.php');
    
    // Chargement des vendors
    $rendererLoader = new SplClassLoader('processid', __DIR__ . '/../../..');
    $rendererLoader->register();
    