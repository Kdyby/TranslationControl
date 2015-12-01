<?php

namespace Kdyby\TranslationControl\Presenters;

use Kdyby\Translation\Translator;
use Kdyby\TranslationControl\Components\TranslationControl;
use Nette;

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