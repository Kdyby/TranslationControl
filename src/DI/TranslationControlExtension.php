<?php
/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\TranslationControl\DI;

use Nette;

/**
 * Translator Control Extension for Nette
 *
 * @author Martin Míka <mail@martinmika.eu>
 */
class TranslationControlExtension extends Nette\DI\CompilerExtension
{
	/**
	 * @var array
	 */
	public $defaults = array(
		'url' => 'kdyby-translation',
		'registerDefaultUrl' => FALSE,
	);

	public function loadConfiguration()
	{
		if ($this->getConfig($this->defaults)['registerDefaultUrl']) {
			$this->setupPresenterMapping();
		}
	}

	public function afterCompile(Nette\PhpGenerator\ClassType $class)
	{
		if ($this->getConfig()['registerDefaultUrl']) {
			$url = $this->getConfig($this->defaults)['url'];
			$initializeMethod = $class->getMethod('initialize');
			$initializeMethod->addBody('Kdyby\TranslationControl\DI\TranslationControlExtension::registerRoute(
                $this->getService("router"), "' . $url . '"
            );');
		}
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