<?php

namespace Kdyby\TranslationControl\DI;

use Nette;

class TranslationControlExtension extends Nette\DI\CompilerExtension
{
    /**
     * @var array
     */
    public $defaults = array(
        'url' => 'kdyby-translation'
    );

    public function loadConfiguration()
    {
        $this->setupPresenterMapping();
    }

    public function afterCompile(Nette\PhpGenerator\ClassType $class)
    {
        $url = $this->getConfig($this->defaults)['url'];
        $initializeMethod = $class->getMethod('initialize');
        $initializeMethod->addBody('Kdyby\TranslationControl\DI\TranslationControlExtension::registerRoute(
            $this->getService("router"), "' . $url . '"
        );');
    }

    public function setupPresenterMapping()
    {
        $this->getContainerBuilder()->getDefinition('nette.presenterFactory')->addSetup('setMapping',
            array(
                array('KdybyTranslationControl' => 'Kdyby\TranslationControl\Presenters\*Presenter')
            )
        );
    }

    /**
     * @param Nette\Application\Routers\RouteList $routeList
     * @param string $url
     */
    public static function registerRoute(Nette\Application\Routers\RouteList $routeList, $url)
    {
        $reflection = new \ReflectionClass(get_parent_class($routeList));
        $property = $reflection->getProperty('list');
        $property->setAccessible(TRUE);
        $list = $property->getValue($routeList);
        array_unshift($list, self::getRouteDefinition($url));
        $property->setValue($routeList, $list);;
        $property->setAccessible(FALSE);
    }

    /**
     * @param string $url
     * @return Nette\Application\Routers\Route
     */
    public static function getRouteDefinition($url)
    {
        return new Nette\Application\Routers\Route($url, array(
            'module' => 'KdybyTranslationControl',
            'presenter' => 'Lang',
            'action' => 'default',
            'id' => null
        ));
    }
}