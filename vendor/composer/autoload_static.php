<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit9b7f5031e39e1ba5d2f55ee7bc3029a6
{
    public static $prefixLengthsPsr4 = array (
        'I' => 
        array (
            'INDIGIT\\' => 8,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'INDIGIT\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src',
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit9b7f5031e39e1ba5d2f55ee7bc3029a6::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit9b7f5031e39e1ba5d2f55ee7bc3029a6::$prefixDirsPsr4;

        }, null, ClassLoader::class);
    }
}