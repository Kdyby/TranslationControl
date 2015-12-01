<?php

namespace Kdyby\TranslationControl\Components;

use Grido\Components\Filters\Filter;
use Grido\DataSources\ArraySource;
use Grido\Grid;
use Kdyby\Translation\Translator;
use Nette;
use Nette\ComponentModel\IContainer;

class TranslationControl extends Nette\Application\UI\Control
{
    /**
     * @var Translator
     */
    protected $translator;

    /**
     * @var string
     */
    private $locale;

    /**
     * @param IContainer $parent
     * @param string|null $name
     * @param Translator $translator
     */
    public function __construct(Nette\ComponentModel\IContainer $parent, $name, Translator $translator)
    {
        $this->translator = $translator;
        $this->locale = $translator->getLocale();

        parent::__construct($parent, $name);
    }

    public function createComponentDataGrid($name = NULL)
    {
        $dataGrid = new Grid($this, $name);
        $dataGrid->setFilterRenderType(Filter::RENDER_INNER);
        $dataGrid->setModel($this->generateDataGridModel());
        $dataGrid->addActionHref('remove', 'Remove', 'remove');
        $dataGrid->setDefaultSort(array('translation' => 'ASC'));

        // Columns
        $dataGrid->addColumnText('id', 'Code')->setSortable()->setFilterText();
        $translationColumn = $dataGrid->addColumnText('translation', 'Translation');
        $translationColumn->setFilterText();
        $translationColumn->setCustomRender(function ($values) {
            $el = Nette\Utils\Html::el('input');
            $el->addAttributes(array(
                'type' => 'text',
                'value' => $values['translation'],
                'class' => 'text',
                'size' => '75'
            ));

            return $el;
        });

        return $dataGrid;
    }


    /**
     * Renders template to client
     */
    public function render()
    {
        $template = $this->template;
        $template->setFile(__DIR__ . '/TranslationControl.latte');
        $template->locales = $this->translator->getAvailableLocales();
        $template->render();
    }

    private function generateDataGridModel()
    {
        $result = array();
        foreach ($this->translator->getAvailableLocales() as $locale) {
            foreach ($this->translator->getCatalogue($locale)->all() as $catalog => $translations) {
                foreach ($translations as $code => $string) {
                    $code = sprintf('%s.%s', $catalog, $code);
                    if ($locale != $this->locale && array_key_exists($code, $result)) {
                        continue;
                    }

                    $result[$code] = array(
                        'id' => $code,
                        'translation' => $locale != $this->locale ? '' : $string,
                    );
                }
            }
        }

        return new ArraySource($result);
    }
}