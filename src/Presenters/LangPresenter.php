<?php
/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\TranslationControl\Presenters;

use Kdyby\Translation\Translator;
use Kdyby\TranslationControl\Components\TranslationControl\TranslationControl;
use Nette;

/**
 * Presenter that handles basic page for translating
 *
 * @author Martin Míka <mail@martinmika.eu>
 */
class LangPresenter extends Nette\Application\UI\Presenter
{
    /**
     * @param string|NULL $name
     * @return TranslationControl
     */
    public function createComponentTranslationControl($name = NULL)
    {
        return new TranslationControl($this, $name, $this->context->getByType(Translator::class));
    }
}