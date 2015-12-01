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
        parent::__construct($parent, $name);
        $this->translator = $translator;
        $selectedLocale = $this->getParameter('language');
        $this->locale = $selectedLocale ?: $translator->getDefaultLocale();
    }

    public function createComponentDataGrid($name = NULL)
    {
        $data = $this->generateDataFromGrid();
        $dataGrid = new Grid($this, $name);
        $dataGrid->setRowCallback(function($row, $tr) {
            /** @var Nette\Utils\Html $tr */
            if (empty($row['translation'])) {
                $tr->attrs['class'][] = 'untranslated';
            }

            return $tr;
        });
        $dataGrid->setFilterRenderType(Filter::RENDER_INNER);
        $dataGrid->setModel(new ArraySource($data));
        $dataGrid->addActionHref('remove', 'Remove', 'remove');
        $dataGrid->setDefaultSort(array('translation' => 'ASC'));
        // Columns
        $catalogueColumn = $dataGrid->addColumnText('catalogue', 'Catalogue');
        $catalogueColumn->setCustomRender(function ($values) {
            return ucfirst($values['catalogue']);
        });
        $catalogueColumn->setFilterSelect($this->getCataloguesInModel($data));
        $catalogueColumn->setSortable();
        $dataGrid->addColumnText('id', 'Code')->setSortable()->setFilterText();
        $translationColumn = $dataGrid->addColumnText('translation', 'Translation');
        $translationColumn->setFilterText();
        $translationColumn->setCustomRender(function ($values) {
            $el = Nette\Utils\Html::el('textarea');
            $el->addAttributes(array(
                'type' => 'text',
                'class' => 'text',
                'rows' => 1,
            ));

            $el->add(str_replace('|', "|\n", $values['translation']));

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

    private function generateDataFromGrid()
    {
        $result = array();
        foreach ($this->translator->getAvailableLocales() as $locale) {
            foreach ($this->translator->getCatalogue($locale)->all() as $catalog => $translations) {
                foreach ($translations as $code => $string) {
                    if ($locale != $this->locale && array_key_exists($code, $result)) {
                        continue;
                    }

                    $result[$code] = array(
                        'id' => $code,
                        'catalogue' => $catalog,
                        'translation' => $locale != $this->locale ? '' : $string,
                    );
                }
            }
        }

        $this->addUntranslatedStringsToGrid($result);

        return $result;
    }

    /**
     * Loads untranslated strings from cache and merge them with given $result
     *
     * @param array $result
     */
    private function addUntranslatedStringsToGrid(&$result)
    {
        $untranslatedCodes = $this->translator->getCache()->load(Translator::UNTRANSLATED_CACHE_KEY, function () {
            return array();
        });

        foreach ($untranslatedCodes as $locale => $codes) {
            foreach ($codes as $code) {
                if (array_key_exists($code, $result)) {
                    continue;
                }

                preg_match('~(.+?)\.(.+)~', $code, $matches);
                $result[$code] = array(
                    'id' => $matches[2],
                    'catalogue' => $matches[1],
                    'translation' => '',
                );
            }
        }
    }

    /**
     * @param array $data
     * @return array
     */
    private function getCataloguesInModel($data)
    {
        $result = array('');
        foreach ($data as $row) {
            if (in_array(ucfirst($row['catalogue']), $result)) {
                continue;
            }

            $result[$row['catalogue']] = ucfirst($row['catalogue']);
        }

        return $result;
    }
}