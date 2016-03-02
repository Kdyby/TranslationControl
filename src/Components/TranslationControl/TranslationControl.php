<?php
/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\TranslationControl\Components\TranslationControl;

use Nette;
use Kdyby;
use Grido\Components\Filters\Filter;
use Grido\DataSources\ArraySource;
use Grido\Grid;
use Kdyby\Translation\Translator;
use Nette\ComponentModel\IContainer;

/**
 * Translator Control for Kdyby Translation
 *
 * @author Martin Míka <mail@martinmika.eu>
 */
class TranslationControl extends Nette\Application\UI\Control
{
	const DEFAULT_DOMAIN = 'messages';

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

	/**
	 * Saves translation to given catalog
	 */
	public function handleSaveTranslation()
	{
		$locale = $this->getParameter('language', $this->translator->getDefaultLocale());
		$code = $this->getParameter('code');
		if (!$code) {
			$this->presenter->sendJson(array('error' => 'Missing "code" parameter'));
		}

		try {
			$this->saveTranslationToCatalogue($locale, $code, $this->presenter->getParameter('string'));
		} catch (Kdyby\TranslationControl\UnsupportedCatalogException $e) {
			$this->presenter->sendJson(array('error' => 'Only Neon format as catalog is supported at this moment.'));
		}

		$this->presenter->sendJson(array('status' => 1));
	}

	/**
	 * Builds listing grid
	 *
	 * @param string|null $name
	 * @return Grid
	 * @throws \Grido\Exception
	 */
	public function createComponentDataGrid($name = NULL)
	{
		$data = $this->generateDataForGrid();
		$dataGrid = new Grid($this, $name);
		$dataGrid->setRowCallback(function ($row, $tr) {
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
				'data-translation-control-change-url' => $this->link('saveTranslation!', array(
					'catalogue' => $values['catalogue'],
					'code' => $values['id'],
					'language' => $this->locale,
				)),
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
		$template->selectedLocale = $this->locale;
		$template->render();
	}

	/**
	 * Iterates over all available locales a tries to find unique language translation identifiers
	 *
	 * @return array
	 */
	private function generateDataForGrid()
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

				if (!preg_match('~(.+?)\.(.+)~', $code, $matches)) {
					$id = $code;
					$catalogue = self::DEFAULT_DOMAIN;
				} else {
					$id = $matches[2];
					$catalogue = $matches[1];
				}

				$result[$code] = array(
					'id' => $id,
					'catalogue' => $catalogue,
					'translation' => '',
				);
			}
		}
	}

	/**
	 * Makes a group of all catalogues in array
	 *
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

	/**
	 * Saves translation to catalog. ONLY NEON IS SUPPORTED AT THIS MOMENT
	 *
	 * @param string $locale
	 * @param string $code
	 * @param string $string
	 */
	private function saveTranslationToCatalogue($locale, $code, $string)
	{
		if (!in_array($locale, $this->translator->getAvailableLocales())) {
			throw new Kdyby\TranslationControl\InvalidArgumentException(sprintf('Catalog "%s" is unknown.', $locale));
		}

		$catalog = $this->translator->getCatalogue($locale);
		$catalogFound = FALSE;
		foreach ($catalog->getResources() as $resource) {
			if (get_class($resource) != 'Symfony\Component\Config\Resource\FileResource') {
				continue;
			}

			/** @var \Symfony\Component\Config\Resource\FileResource $resource */
			$filePath = $resource->getResource();
			if (pathinfo($filePath, PATHINFO_EXTENSION) != 'neon'
				|| !Nette\Utils\Strings::endsWith(strtolower($filePath), sprintf('%s.neon', strtolower($locale)))
			) {
				continue;
			}

			$catalog->add(array($code => $string));
			$this->saveTranslationToNeonFile($filePath, $code, $string);
			$catalogFound = TRUE;
		}

		if (!$catalogFound) {
			throw new Kdyby\TranslationControl\UnsupportedCatalogException('Only neon catalog is supported.');
		}
	}

	/**
	 * @param string $filePath
	 * @param string $code
	 * @param string $string
	 */
	private function saveTranslationToNeonFile($filePath, $code, $string)
	{
		$actualCatalog = Nette\Neon\Neon::decode(file_get_contents($filePath));
		if (!is_array($actualCatalog)) {
			$actualCatalog = array();
		}

		$newCatalogMember = $this->createNestedArrayFromLanguageCode($code, $string);
		$result = array_replace_recursive($actualCatalog, $newCatalogMember);
		file_put_contents($filePath, Nette\Neon\Neon::encode($result, Nette\Neon\Neon::BLOCK));
	}

	/**
	 * We must create an array from code like 'homepage.something' to array('homepage => array('something' => $string))
	 *
	 * @param string $code
	 * @param string $string
	 * @return array
	 */
	private function createNestedArrayFromLanguageCode($code, $string)
	{
		$resultArray = array();
		if (FALSE === ($levels = explode('.', $code))) {
			return array($code => $string);
		}

		$pointer = &$resultArray;
		for ($i = 0; $i < sizeof($levels); $i++) {
			if (!isset($pointer[$levels[$i]])) {
				$pointer[$levels[$i]] = array();
			}

			$pointer = &$pointer[$levels[$i]];
		}

		$pointer = $string;

		return $resultArray;
	}
}