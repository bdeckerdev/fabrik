<?php
/**
 * Base Form view class
 *
 * @package     Joomla
 * @subpackage  Fabrik
 * @copyright   Copyright (C) 2005-2016  Media A-Team, Inc. - All rights reserved.
 * @license     GNU/GPL http://www.gnu.org/copyleft/gpl.html
 */

namespace Joomla\Component\Fabrik\Site\View\Form;

// No direct access
defined('_JEXEC') or die('Restricted access');

use Fabrik\Helpers\Html;
use Fabrik\Helpers\Worker;
use Fabrik\Helpers\StringHelper as FStringHelper;
use Fabrik\Helpers\ArrayHelper as FArrayHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Editor\Editor;
use Joomla\CMS\Filter\InputFilter;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Profiler\Profiler;
use Joomla\CMS\Router\Route;
use Joomla\Component\Fabrik\Administrator\Table\FabTable;
use Joomla\Component\Fabrik\Site\Model\FormModel;
use Joomla\Component\Fabrik\Site\View\AbstractView;
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;

/**
 * Base Form view class
 *
 * @package     Joomla
 * @subpackage  Fabrik
 * @since       4.0
 */
class BaseView extends AbstractView
{
	/**
	 * Is the view rendering inside the Fabrik Joomla content plugin
	 *
	 * @var  bool
	 *
	 * @since 4.0
	 */
	public $isMambot = null;

	/**
	 * Viewing access level
	 *
	 * @var  int
	 *
	 * @since 4.0
	 */
	public $access = null;

	/**
	 * Does the form have any actions?
	 *
	 * @var bool
	 *
	 * @since 4.0
	 */
	public $hasActions = false;

	/**
	 * Form hidden fields
	 *
	 * @var string
	 *
	 * @since 4.0
	 */
	public $hiddenFields = '';

	/**
	 * @var bool
	 * @since 4.0
	 */
	public $showPrint = false;

	/**
	 * @var bool
	 * @since 4.0
	 */
	public $showPDF = false;

	/**
	 * @var bool
	 * @since 4.0
	 */
	public $showEmail = false;

	/**
	 * @var string
	 * @since 4.0
	 */
	public $pluginbottom = '';

	/**
	 * @var string
	 * @since 4.0
	 */
	public $plugintop = '';

	/**
	 * @var bool
	 * @since 4.0
	 */
	public $isMultiPage = false;

	/**
	 * @var string
	 * @since 4.0
	 */
	public $pluginend = '';

	/**
	 * @var string
	 * @since 4.0
	 */
	public $tipLocation = 'above';

	/**
	 * @var string
	 * @since 4.0
	 */
	public $rowid = '';

	/**
	 * Preview the form, used in content type admin page.
	 *
	 * @since 4.0
	 */
	public function preview()
	{
		/** @var FormModel $model */
		$model        = $this->getModel();
		$tmpl         = $model->getTmpl();
		$this->tmpl   = $tmpl;
		$this->form   = $this->prepareFormTable();
		$this->params = new Registry;
		$this->groups = $model->getGroupView($tmpl);
		$this->_repeatGroupButtons($tmpl);
		$this->setTmplFolders($tmpl);
	}

	/**
	 * Set the repeat group button layouts
	 *
	 * @param   string $tmpl Template
	 *
	 * @since 4.0
	 */
	private function _repeatGroupButtons($tmpl)
	{
		/** @var FormModel $formModel */
		$formModel                        = $this->getModel();
		$btnData                          = (object) array('tmpl' => $tmpl);
		$this->removeRepeatGroupButton    = $formModel->getLayout('form.fabrik-repeat-group-delete')->render($btnData);
		$this->addRepeatGroupButton       = $formModel->getLayout('form.fabrik-repeat-group-add')->render($btnData);
		$this->removeRepeatGroupButtonRow = $formModel->getLayout('form.fabrik-repeat-group-row-delete')->render($btnData);
		$this->addRepeatGroupButtonRow    = $formModel->getLayout('form.fabrik-repeat-group-row-add')->render($btnData);
	}

	/**
	 * Main setup routine for displaying the form/detail view
	 *
	 * @param   string $tpl template
	 *
	 * @return  void
	 *
	 * @since 4.0
	 */
	public function display($tpl = null)
	{
		$profiler = Profiler::getInstance('Application');
		$input    = $this->app->input;
		$w        = new Worker();

		/** @var FormModel $model */
		$model = $this->getModel();

		if (!$model)
		{
			// Dodgy URL - can't find the form name see https://github.com/Fabrik/fabrik/issues/1248
			return;
		}

		$model->isMambot = $this->isMambot;

		if ($model->render() === false)
		{
			return false;
		}

		$this->isMultiPage = $model->isMultiPage();
		list($this->plugintop, $this->pluginbottom, $this->pluginend) = $model->getFormPluginHTML();
		$listModel = $model->getlistModel();

		if (!$this->canAccess())
		{
			return false;
		}

		JDEBUG ? $profiler->mark('form view before join group ids got') : null;

		if (!$listModel->noTable())
		{
			$joins = $listModel->getJoins();
			$model->getJoinGroupIds($joins);
		}

		$params = $model->getParams();
		$this->setTitle($w, $params);

		Html::debug($params->get('note'), 'note');
		$params->def('icons', $this->app->get('icons'));
		$params->set('popup', ($input->get('tmpl') == 'component') ? 1 : 0);

		$this->editable = $model->isEditable();
		$form           = $this->prepareFormTable();
		$clearErrors    = false;

		// Module rendered without ajax, we need to assign the session errors back into the model
		if ($model->isMambot)
		{
			$this->package = $this->app->getUserState('com_fabrik.package', 'fabrik');
			$context       = $model->getSessionContext();
			$model->errors = $this->session->get($context . 'errors', array());
			$clearErrors   = true;
		}

		JDEBUG ? $profiler->mark('form view before validation classes loaded') : null;

		$tmpl       = $model->getTmpl();
		$this->tmpl = $tmpl;

		$this->_addButtons();
		JDEBUG ? $profiler->mark('form view before group view got') : null;

		$this->groups = $model->getGroupView($tmpl);
		$this->_repeatGroupButtons($tmpl);

		JDEBUG ? $profiler->mark('form view after group view got') : null;
		$this->data        = $model->tmplData;
		$this->params      = $params;
		$this->tipLocation = $params->get('tiplocation');

		Html::debug($this->groups, 'form:view:groups');

		$this->setTmplFolders($tmpl);
		$this->_addJavascript($listModel->getId());
		JDEBUG ? $profiler->mark('form view: after add js') : null;
		$this->_loadTmplBottom($form);
		JDEBUG ? $profiler->mark('form view: after tmpl bottom loaded') : null;
		$this->form = $form;
		JDEBUG ? $profiler->mark('form view: form assigned as ref') : null;
		$list       = new \stdClass;
		$list->id   = $form->record_in_database ? $model->getListModel()->getTable()->id : 0;
		$this->list = $list;
		JDEBUG ? $profiler->mark('form view: before getRelatedTables()') : null;
		$this->linkedTables = $model->getRelatedTables();
		JDEBUG ? $profiler->mark('form view: after getRelatedTables()') : null;
		$this->setMessage();

		// If rendered as a module (non ajax) and we have inserted the session errors, clear them from the session.
		if ($clearErrors)
		{
			$model->clearErrors();
		}

		JDEBUG ? $profiler->mark('form view before template load') : null;
	}

	/**
	 * Test the form exists and can be accessed
	 *
	 * @return bool
	 *
	 * @since 4.0
	 */
	protected function canAccess()
	{
		$model = $this->getModel();

		if ($model->getForm()->get('id', '') === '')
		{
			throw new \UnexpectedValueException('Form does not exists');
		}
		if (!$model->canPublish())
		{
			if (!$this->app->isAdmin())
			{
				echo Text::_('COM_FABRIK_FORM_NOT_PUBLISHED');

				return false;
			}
		}

		$this->rowid  = $model->getRowId();
		$this->access = $model->checkAccessFromListSettings();

		if ($this->access == 0)
		{
			$this->app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');

			return false;
		}

		return true;
	}

	/**
	 * Prepare the form table for use in the templates
	 *
	 * @return FabTable
	 *
	 * @since 4.0
	 */
	private function prepareFormTable()
	{
		$model        = $this->getModel();
		$form         = $model->getForm();
		$form->label  = Text::_($model->getLabel());
		$form->intro  = Text::_($model->getIntro());
		$form->outro  = Text::_($model->getOutro());
		$form->action = $model->getAction();
		$form->class  = $model->getFormClass();
		$form->formid = $model->isEditable() ? 'form_' . $model->getId() : 'details_' . $model->getId();
		$form->name   = 'form_' . $model->getId();

		if ((string) $this->rowid !== '')
		{
			$form->formid .= '_' . $this->rowid;
		}

		$form->error = $form->error === '' ? Text::_('COM_FABRIK_FAILED_VALIDATION') : Text::_($form->error);

		if (!empty($model->formErrorMsg))
		{
			$form->error .= '<br />' . $model->formErrorMsg;
		}

		$form->origerror = $form->error;
		$form->error     = $model->hasErrors() ? $form->error : '';
		$form->attribs   = ' class="' . $form->class . '" name="' . $form->name . '" id="' .
			$form->formid . '" enctype="' . $model->getFormEncType() . '"';

		return $form;
	}

	/**
	 * Add the template folder paths
	 *
	 * @param $tmpl
	 *
	 * @since 4.0
	 */
	private function setTmplFolders($tmpl)
	{
		// Force front end templates
		$this->_basePath = COM_FABRIK_FRONTEND . '/tmpl';
		$model           = $this->getModel();
		$folder          = $model->isEditable() ? 'form' : 'details';
		$this->addTemplatePath($this->_basePath . '/' . $folder . '/' . $tmpl);
		$root = $this->app->isAdmin() ? JPATH_ADMINISTRATOR : JPATH_SITE;
		$this->addTemplatePath($root . '/templates/' . $this->app->getTemplate() . '/html/com_fabrik/' . $folder . '/' . $tmpl);
	}

	/**
	 * Finally output the HTML, running Joomla content plugins if needed
	 *
	 * @return  void
	 *
	 * @since 4.0
	 */
	public function output()
	{
		$w      = new Worker;
		$text   = $this->loadTemplate();
		$model  = $this->getModel();
		$params = $model->getParams();
		$view   = $model->isEditable() === false ? 'details' : 'form';

		if ($params->get('process-jplugins', 2) == 1
			|| ($params->get('process-jplugins', 2) == 2 && $model->isEditable() === false)
			|| ($params->get('process-jplugins', 2) == 3 && $model->isEditable() === true)
		)
		{
			$cloak = $view === 'details' && $this->app->input->get('format') !== 'pdf';
			Html::runContentPlugins($text, $cloak);
		}

		// Allows you to use {placeholders} in form template Only replacing data accessible to the users acl.

		$text = $w->parseMessageForPlaceHolder($text, $model->accessibleData($view));
		echo $text;
	}

	/**
	 * Set template message when using session multi-pages
	 *
	 * @return  void
	 *
	 * @since 4.0
	 */
	private function setMessage()
	{
		$model   = $this->getModel();
		$message = '';

		if (!$model->isMultiPage())
		{
			$this->message = '';

			return;
		}

		if ($model->sessionModel)
		{
			$this->message = $model->sessionModel->status;

			// See http://fabrikar.com/forums/showpost.php?p=73833&postcount=14
			// if ($model->sessionModel->statusid == _FABRIKFORMSESSION_LOADED_FROM_COOKIE) {

			if ($model->sessionModel->last_page > 0)
			{
				$message .= ' <a href="#" class="clearSession">' . Text::_('COM_FABRIK_CLEAR') . '</a>';
			}
		}

		$this->message = $message;
	}

	/**
	 * Set the page title
	 *
	 * @param   object  $w      parent worker
	 * @param   object &$params parameters
	 *
	 * @return  void
	 *
	 * @since 4.0
	 */
	protected function setTitle($w, &$params)
	{
		/** @var FormModel $model */
		$model = $this->getModel();
		$input = $this->app->input;
		$title = '';

		if (!$this->app->isAdmin())
		{
			$menus = $this->app->getMenu();
			$menu  = $menus->getActive();

			// If there is a menu item available AND the form is not rendered in a content plugin or module
			if (is_object($menu) && !$this->isMambot)
			{
				$menuParams = is_a($menu->params, 'Registry') || is_a($menu->params, 'JRegistry') ? $menu->params : new Registry($menu->params);
				$params->set('page_heading', Text::_($menuParams->get('page_heading', '')));
				$params->set('show_page_heading', $menuParams->get('show_page_heading', 0));
				$browserTitle = $model->getPageTitle(Text::_($menuParams->get('page_title')));
				$this->doc->setTitle($w->parseMessageForPlaceHolder($browserTitle, $_REQUEST));
			}
			else
			{
				$params->set('show_page_heading', $input->getInt('show_page_heading', 0));
				$params->set('page_heading', Text::_($input->get('title', $title, 'string')));
				$params->set('show-title', $input->getInt('show-title', $params->get('show-title')));
			}

			if (!$this->isMambot)
			{
				$titleData = array_merge($_REQUEST, $model->data);
				$title     = $w->parseMessageForPlaceHolder(Text::_($params->get('page_heading')), $titleData, false);
				$params->set('page_heading', $title);
			}
		}
		else
		{
			$params->set('page_heading', Text::_($title));
			$params->set('show_page_heading', 0);
		}
	}

	/**
	 * Add buttons to the view e.g. print, pdf
	 *
	 * @return  void
	 *
	 * @since 4.0
	 */
	protected function _addButtons()
	{
		$input = $this->app->input;

		if ($input->get('format') === 'pdf')
		{
			// If we're rendering as PDF, no point showing any buttons
			$this->showEmail = false;
			$this->showPrint = false;
			$this->showPDF   = false;

			return;
		}

		$fbConfig = ComponentHelper::getParams('com_fabrik');

		/** @var FabrikFEModelForm $model */
		$model           = $this->getModel();
		$params          = $model->getParams();
		$this->showEmail = $params->get('email', $fbConfig->get('form_email', 0));
		$this->emailLink = '';
		$this->printLink = '';
		$this->pdfLink   = '';
		$this->pdfURL    = '';
		$this->emailURL  = '';
		$this->printURL  = '';
		$this->showPrint = $params->get('print', $fbConfig->get('form_print', 0));

		if ($this->showPrint)
		{
			$text            = Html::image('print');
			$this->printLink = '<a href="#" class="btn btn-default fabrikPrintIcon" onclick="window.print();return false;">' . $text . '</a>';
		}

		if ($input->get('tmpl') != 'component')
		{
			if ($this->showEmail)
			{
				$this->emailLink = Html::emailIcon($model, $params);
				$this->emailURL  = Html::emailURL($model);
			}
		}
		//Also in popup window create first a printURL ..&tmpl=component&iframe=1&print=1...
		if ($input->get('print', 0) != 1)
		{

			if ($this->showPrint)
			{
				$this->printLink = Html::printIcon($model, $params);
				$this->printURL  = Html::printURL($model);
			}
		}

		// 0 = no, 1 = both, 2 = form only, 3 = details only
		$this->showPDF = $params->get('pdf', $fbConfig->get('form_pdf', false));

		if ($this->showPDF === '2' && !$model->isEditable())
		{
			$this->showPDF = false;
		}

		if ($this->showPDF === '3' && $model->isEditable())
		{
			$this->showPDF = false;
		}

		if ($this->showPDF)
		{
			if (Worker::canPdf(false))
			{
				if ($this->app->isAdmin())
				{
					$this->pdfURL = 'index.php?option=com_' . $this->package . '&task=details.view&format=pdf&formid=' . $model->getId() . '&rowid=' . $model->getRowId();
				}
				else
				{
					$this->pdfURL = 'index.php?option=com_' . $this->package . '&view=details&formid=' . $model->getId() . '&rowid=' . $model->getRowId() . '&format=pdf';
				}

				$this->pdfURL           = Route::_($this->pdfURL);
				$layout                 = Html::getLayout('form.fabrik-pdf-icon');
				$pdfDisplayData         = new \stdClass;
				$pdfDisplayData->pdfURL = $this->pdfURL;
				$pdfDisplayData->tmpl   = $this->tmpl;

				$this->pdfLink = $layout->render($pdfDisplayData);
			}
		}
	}

	/**
	 * Append the form javascript into the document head
	 *
	 * @param   int $listId table id
	 *
	 * @return  void|boolean
	 *
	 * @since 4.0
	 */
	protected function _addJavascript($listId)
	{
		$pluginManager = Worker::getPluginManager();

		/** @var FormModel $model */
		$model = $this->getModel();
		$model->elementJsJLayouts();
		$aLoadedElementPlugins = array();
		$jsActions             = array();
		$bKey                  = $model->jsKey();
		$mediaFolder           = Html::getMediaFolder();
		$srcs                  = array_merge(
			array(
				'FloatingTips' => $mediaFolder . '/tipsBootStrapMock.js',
				'FbForm'       => $mediaFolder . '/form.js',
				'Fabrik'       => $mediaFolder . '/fabrik.js'
			),
			Html::framework());
		$shim                  = array();

		$liveSiteReq[] = $mediaFolder . '/tipsBootStrapMock.js';

		if (!defined('_JOS_FABRIK_FORMJS_INCLUDED'))
		{
			define('_JOS_FABRIK_FORMJS_INCLUDED', 1);
			Html::slimbox();

			$dep       = new \stdClass;
			$dep->deps = array(
				'fab/element',
				'lib/form_placeholder/Form.Placeholder'
			);

			$shim['fabrik/form'] = $dep;

			$deps                         = new \stdClass;
			$deps->deps                   = array('fab/fabrik', 'fab/element', 'fab/form-submit');
			$framework['fab/elementlist'] = $deps;

			$srcs['Placeholder'] = 'media/com_fabrik/js/lib/form_placeholder/Form.Placeholder.js';
			$srcs['FormSubmit']  = $mediaFolder . '/form-submit.js';
			$srcs['Element']     = $mediaFolder . '/element.js';
		}

		$aWYSIWYGNames = array();

		// $$$ hugh - yet another one where if we =, the $groups array pointer get buggered up and it
		// skips a group
		$groups = $model->getGroupsHiarachy();

		foreach ($groups as $groupModel)
		{
			$elementModels = $groupModel->getPublishedElements();

			foreach ($elementModels as $elementModel)
			{
				$res = $elementModel->useEditor();

				if ($res !== false && $elementModel->canUse() && $model->isEditable())
				{
					$aWYSIWYGNames[] = $res;
				}

				// Load in once the element js class files
				$element = $elementModel->getElement();

				if (!in_array($element->plugin, $aLoadedElementPlugins))
				{
					/* $$$ hugh - certain elements, like file-upload, need to load different JS files
					 * on a per-element basis, so as a test fix, I modified the file-upload's formJavaScriptClass to return false,
					* and test for that here, so as to not add it to aLoadedElementPlugins[].  The existing 'static' tests in
					* formJavascriptClass() should still prevent scripts being added twice.
					*/
					if ($elementModel->formJavascriptClass($srcs, '', $shim) !== false)
					{
						$aLoadedElementPlugins[] = $element->plugin;
					}
				}

				$eventMax = ($groupModel->repeatTotal == 0) ? 1 : $groupModel->repeatTotal;

				for ($c = 0; $c < $eventMax; $c++)
				{
					$jsAct = $elementModel->getFormattedJSActions($bKey, $c);

					if (!empty($jsAct))
					{
						$jsActions[] = $jsAct;
					}
				}
			}
		}

		Html::iniRequireJS($shim);
		$actions = trim(implode("\n", $jsActions));
		Html::windows('a.fabrikWin');
		Html::tips('.hasTip', array(), "$('$bKey')");
		$model->getFormCss();
		$opts = $this->jsOpts();

		$model->jsOpts = $opts;
		$pluginManager->runPlugins('onJSOpts', $model);

		$opts = json_encode($model->jsOpts);

		if (!Html::inAjaxLoadedPage())
		{
			Text::script('COM_FABRIK_VALIDATING');
			Text::script('COM_FABRIK_SUCCESS');
			Text::script('COM_FABRIK_NO_REPEAT_GROUP_DATA');
			Text::script('COM_FABRIK_VALIDATION_ERROR');
			Text::script('COM_FABRIK_CONFIRM_DELETE_1');
		}

		Text::script('COM_FABRIK_FORM_SAVED');

		// $$$ rob don't declare as var $bKey, but rather assign to window, as if loaded via ajax window the function is wrapped
		// inside an anonymous function, and therefore $bKey wont be available as a global var in window
		$script   = array();
		$script[] = "\t\tvar $bKey = new FbForm(" . $model->getId() . ", $opts);";
		$script[] = "\t\tFabrik.addBlock('$bKey', $bKey);";
		// Instantiate js objects for each element
		$vstr      = "\n";
		$groups    = $model->getGroupsHiarachy();
		$script[]  = "\t{$bKey}.addElements(";
		$groupedJs = new \stdClass;

		foreach ($groups as $groupModel)
		{
			$groupId             = $groupModel->getGroup()->id;
			$groupedJs->$groupId = array();

			if (!$groupModel->canView('form'))
			{
				continue;
			}

			$elementJs     = array();
			$elementModels = $groupModel->getPublishedElements();

			// $$$ rob if repeatTotal is 0 we still want to add the js objects as the els are only hidden
			$max = $groupModel->repeatTotal > 0 ? $groupModel->repeatTotal : 1;

			foreach ($elementModels as $elementModel)
			{
				$element = $elementModel->getElement();

				if ($element->published == 0)
				{
					continue;
				}

				// If the view is a form then we should always add the js as long as the element is editable or viewable
				// if the view is details then we should only add the js if the element is viewable.
				if (($elementModel->canUse() && $model->isEditable()) || $elementModel->canView())
				{
					for ($c = 0; $c < $max; $c++)
					{
						$ref = $elementModel->elementJavascript($c);

						if (!empty($ref))
						{
							$elementJs[] = $ref;
						}

						$validations = $elementModel->validator->findAll();

						if (!empty($validations) && $elementModel->isEditable())
						{
							$watchElements = $elementModel->getValidationWatchElements($c);

							foreach ($watchElements as $watchElement)
							{
								$vstr .= "\t$bKey.watchValidation('" . $watchElement['id'] . "', '" . $watchElement['triggerEvent'] . "');\n";
							}
						}
					}
				}
			}

			$groupedJs->$groupId = $elementJs;
		}

		$script[] = json_encode($groupedJs);
		$script[] = "\t);";
		$script[] = $actions;
		$script[] = $vstr;

		// Placeholder
		$script[] = "\tnew Form.Placeholder('.fabrikForm input');";
		$this->_addJavascriptSumbit($script, $listId, $aWYSIWYGNames);

		if (Html::inAjaxLoadedPage())
		{
			$tipOpts  = Html::tipOpts();
			$script[] = "new FloatingTips('#" . $bKey . " .fabrikTip', " . json_encode($tipOpts) . ");";
		}

		$res = $pluginManager->runPlugins('onJSReady', $model);

		if (in_array(false, $res))
		{
			return false;
		}

		$str = implode("\n", $script);
		$model->getCustomJsAction($srcs);

		$pluginManager->runPlugins('onAfterJSLoad', $model);

		// 3.1 call form js plugin code within main require method
		$srcs = array_merge($srcs, $model->formPluginShim);
		$str  .= implode("\n", (array) $model->formPluginJS);
		Html::script($srcs, $str);
	}

	/**
	 * Load the JavaScript ini options
	 *
	 * @since  3.1b
	 *
	 * @return \stdClass
	 */
	protected function jsOpts()
	{
		$input = $this->app->input;

		/** @var FormModel $model */
		$model                = $this->getModel();
		$fbConfig             = ComponentHelper::getParams('com_fabrik');
		$form                 = $model->getForm();
		$params               = $model->getParams();
		$listModel            = $model->getlistModel();
		$table                = $listModel->getTable();
		$opts                 = new \stdClass;
		$opts->admin          = $this->app->isAdmin();
		$opts->ajax           = $model->isAjax();
		$opts->ajaxValidation = (bool) $params->get('ajax_validations');
		$opts->lang           = Worker::getMultiLangURLCode();
		$opts->toggleSubmit   = (bool) $params->get('ajax_validations_toggle_submit');
		$opts->showLoader     = (bool) $params->get('show_loader_on_submit', '0');
		$key                  = FStringHelper::safeColNameToArrayKey($table->db_primary_key);
		$opts->primaryKey     = $key;
		$opts->error          = @$form->origerror;
		$opts->pages          = $model->getPages();
		$opts->plugins        = array();
		$opts->multipage_save = (int) $model->saveMultiPage();
		$opts->editable       = $model->isEditable();
		$opts->print          = (bool) $input->getInt('print');
		$startPage            = isset($model->sessionModel->last_page) ? (int) $model->sessionModel->last_page : 0;

		if ($startPage !== 0)
		{
			if ($this->app->input->get('view', 'form') === 'form')
			{
				$this->app->enqueueMessage(Text::_('COM_FABRIK_RESTARTING_MULTIPAGE_FORM'));
			}
		}
		else
		{
			// Form submitted but fails validation - needs to go to the last page
			$startPage = $input->getInt('currentPage', 0);
		}

		$opts->start_page    = $startPage;
		$opts->inlineMessage = (bool) $this->isMambot;

		// $$$rob don't int this as keys may be string
		$opts->rowid = (string) $model->getRowId();

		// 3.0 needed for ajax requests
		$opts->listid = (int) $this->get('ListModel')->getId();

		$errorIcon       = $fbConfig->get('error_icon', 'exclamation-sign');
		$this->errorIcon = Html::image($errorIcon, 'form', $this->tmpl);

		$imgs               = new \stdClass;
		$imgs->alert        = Html::image($errorIcon, 'form', $this->tmpl, '', true);
		$imgs->action_check = Html::image('action_check.png', 'form', $this->tmpl, '', true);

		//$imgs->ajax_loader = Html::image('ajax-loader.gif', 'form', $this->tmpl, '', false);
		$imgs->ajax_loader = Html::icon('icon-spinner icon-spin');
		$opts->images      = $imgs;

		// $$$rob if you are loading a list in a window from a form db join select record option
		// then we want to know the id of the window so we can set its showSpinner() method

		// 3.0 changed to fabrik_window_id (automatically appended by Fabrik.Window xhr request to load window data
		// use getRaw to preserve URL /'s, as window JS will keep them in id
		$opts->fabrik_window_id = $input->getRaw('fabrik_window_id', '');
		$opts->submitOnEnter    = (bool) $params->get('submit_on_enter', false);

		// For editing groups with joined data and an empty joined record (i.e. no joined records)
		$hidden         = array();
		$maxRepeat      = array();
		$minRepeat      = array();
		$showMaxRepeats = array();
		$minMaxErrMsg   = array();
		$numRepeatEls   = array();
		$noDataMsg      = array();

		foreach ($this->groups as $g)
		{
			$hidden[$g->id]         = $g->startHidden;
			$maxRepeat[$g->id]      = $g->maxRepeat;
			$minRepeat[$g->id]      = $g->minRepeat;
			$numRepeatEls[$g->id]   = FStringHelper::safeColNameToArrayKey($g->numRepeatElement);
			$showMaxRepeats[$g->id] = $g->showMaxRepeats;
			$minMaxErrMsg[$g->id]   = Text::_($g->minMaxErrMsg);
			$noDataMsg[$g->id]      = Text::_($g->noDataMsg);
		}

		$opts->hiddenGroup    = $hidden;
		$opts->maxRepeat      = $maxRepeat;
		$opts->minRepeat      = $minRepeat;
		$opts->showMaxRepeats = $showMaxRepeats;
		$opts->minMaxErrMsg   = $minMaxErrMsg;
		$opts->numRepeatEls   = $numRepeatEls;
		$opts->noDataMsg      = $noDataMsg;

		// $$$ hugh adding these so calc element can easily find joined and repeated join groups
		// when it needs to add observe events ... don't ask ... LOL!
		$opts->join_group_ids  = array();
		$opts->group_repeats   = array();
		$opts->group_joins_ids = array();
		$groups                = $model->getGroupsHiarachy();

		foreach ($groups as $groupModel)
		{
			if ($groupModel->getGroup()->is_join)
			{
				$joinParams = $groupModel->getJoinModel()->getJoin()->params;

				if (!(is_a($joinParams, 'Registry') || is_a($joinParams, 'JRegistry')))
				{
					$joinParams = new Registry($joinParams);
				}

				$groupId                                                = $groupModel->getGroup()->id;
				$opts->group_pk_ids[$groupId]                           = FStringHelper::safeColNameToArrayKey($joinParams->get('pk'));
				$opts->join_group_ids[$groupModel->getGroup()->join_id] = (int) $groupModel->getGroup()->id;
				$opts->group_join_ids[$groupId]                         = (int) $groupModel->getGroup()->join_id;
				$opts->group_repeats[$groupId]                          = $groupModel->canRepeat();
				$opts->group_copy_element_values[$groupId]              = $groupModel->canCopyElementValues();
				$opts->group_repeat_intro[$groupId]                     = $groupModel->getParams()->get('repeat_intro', '');
			}
		}

		return $opts;
	}

	/**
	 * Append JS code for form submit
	 *
	 * @param   array &$script        Scripts
	 * @param   int    $listId        List id
	 * @param   array  $aWYSIWYGNames WYSIWYG editor names
	 *
	 * @since   3.1b
	 * @return  void
	 */
	protected function _addJavascriptSumbit(&$script, $listId, $aWYSIWYGNames)
	{
		$script[] = "\tfunction submit_form() {";

		if (!empty($aWYSIWYGNames))
		{
			$editor   = Editor::getInstance($this->config->get('editor'));
			$script[] = $editor->save('label');

			foreach ($aWYSIWYGNames as $parsedName)
			{
				$script[] = $editor->save($parsedName);
			}
		}

		$script[] = "\treturn false;";
		$script[] = "}";
		$script[] = "function submitbutton(button) {";
		$script[] = "\tif (button==\"cancel\") {";
		$script[] = "\t\tdocument.location = '" . Route::_('index.php?option=com_' . $this->package . '&task=viewTable&cid=' . $listId) . "';";
		$script[] = "\t}";
		$script[] = "\tif (button == \"cancelShowForm\") {";
		$script[] = "\t\treturn false;";
		$script[] = "\t}";
		$script[] = "}";
	}

	/**
	 * Create the form bottom hidden fields
	 *
	 * @param   object &$form Object containing form view properties
	 *
	 * @return  void
	 *
	 * @since 4.0
	 */
	protected function _loadTmplBottom(&$form)
	{
		$input  = $this->app->input;
		$itemId = Worker::itemId();

		/** @var FormModel $model */
		$model     = $this->getModel();
		$listModel = $model->getListModel();
		$row       = ArrayHelper::toObject($model->data);
		$canDelete = $listModel->canDelete($row);
		$params    = $model->getParams();
		$task      = 'form.process';
		$refer     = $input->server->get('HTTP_REFERER', '', 'string');

		// $$$rob - if returning from a failed validation then we should use the fabrik_referrer post var
		$refer = str_replace('&', '&amp;', $input->get('fabrik_referrer', $refer, 'string'));

		$thisRowId = is_array($model->getRowId()) ? implode('|', $model->getRowId()) : $model->getRowId();
		$fields    = array();
		$fields[]  = '<input type="hidden" name="listid" value="' . $listModel->getId() . '" />';
		$fields[]  = '<input type="hidden" name="listref" value="' . $listModel->getId() . '" />';
		$fields[]  = '<input type="hidden" name="rowid" value="' . $thisRowId . '" />';
		$fields[]  = '<input type="hidden" name="Itemid" value="' . $itemId . '" />';
		$fields[]  = '<input type="hidden" name="option" value="com_' . $this->package . '" />';
		$fields[]  = '<input type="hidden" name="task" value="' . $task . '" />';
		$fields[]  = '<input type="hidden" name="isMambot" value="' . $this->isMambot . '" />';
		$fields[]  = '<input type="hidden" name="formid" value="' . $model->get('id') . '" />';
		$fields[]  = '<input type="hidden" name="returntoform" value="0" />';
		$fields[]  = '<input type="hidden" name="fabrik_referrer" value="' . $refer . '" />';
		$fields[]  = '<input type="hidden" name="fabrik_ajax" value="' . (int) $model->isAjax() . '" />';
		$fields[]  = '<input type="hidden" name="package" value="' . $this->app->getUserState('com_fabrik.package', 'fabrik') . '" />';
		$fields[]  = '<input type="hidden" name="packageId" value="' . $model->packageId . '" />';

		if ($model->noData)
		{
			$fields[] = '<input type="hidden" name="nodata" value="1"" />';
		}
		else
		{
			$fields[] = '<input type="hidden" name="nodata" value="0" />';
		}

		/*
		if ($input->get('fabrikdebug', '') === '2')
        {
            $fields[]  = '<input type="hidden" name="fabrikdebug" value="2" />';
        }
		*/

		// Allow things like join element with frontend Add to squash redirects
		if ($input->getInt('noredirect', 0) !== 0)
		{
			$fields[] = '<input type="hidden" name="noredirect" value="1" />';
		}

		if ($useKey = Worker::getMenuOrRequestVar('usekey', ''))
		{
			// $$$rob v's been set from -1 to the actual row id - so ignore usekey not sure if we should comment this out
			// see http://fabrikar.com/forums/showthread.php?t=10297&page=5

			$fields[] = '<input type="hidden" name="usekey" value="' . $useKey . '" />';
			$pk_val   = FArrayHelper::getValue($model->data, $listModel->getPrimaryKey(true));

			if (empty($pk_val))
			{
				$fields[] = '<input type="hidden" name="usekey_newrecord" value="1" />';
			}
		}

		/** $$$ hugh - testing a fix for pagination issue when submitting a 'search form'.
		 * If this is a search form, we need to clear 'limitstart', otherwise ... say we
		 * were last on page 4 of the (unfiltered) target table, and the search yields less than 4 pages,
		 * we end up with a blank table 'cos the wrong LIMIT's are applied to the query
		 */
		$saveInSessions = $params->get('save_insession', '');

		if (is_array($saveInSessions))
		{
			foreach ($saveInSessions as $saveInSession)
			{
				if ($saveInSession == '1')
				{
					$fields[] = '<input type="hidden" name="limitstart" value="0" />';
					break;
				}
			}
		}

		$fields[]    = HTMLHelper::_('form.token');
		$resetLabel  = Text::_($params->get('reset_button_label'));
		$resetIcon   = $params->get('reset_icon', '');
		$copyLabel   = Text::_($params->get('copy_button_label'));
		$copyIcon    = $params->get('copy_icon', '');
		$applyLabel  = Text::_($params->get('apply_button_label'));
		$applyIcon   = $params->get('apply_icon', '');
		$deleteLabel = Text::_($params->get('delete_button_label', 'Delete'));
		$deleteIcon  = $params->get('delete_icon', '');
		$goBackLabel = Text::_($params->get('goback_button_label'));
		$goBackIcon  = $params->get('goback_icon', '');
		$btnLayout   = Html::getLayout('fabrik-button');

		if ($resetIcon !== '')
		{
			$resetIcon  = Html::icon($resetIcon);
			$before     = $params->get('reset_icon_location', 'before') == 'before';
			$resetLabel = $before ? $resetIcon . '&nbsp;' . $resetLabel : $resetLabel . '&nbsp;' . $resetIcon;
		}

		$layoutData = (object) array(
			'type'      => 'reset',
			'class'     => $params->get('reset_button_class', 'btn-warning') . ' button clearSession',
			'name'      => 'Reset',
			'label'     => $resetLabel,
			'formModel' => $model
		);

		$form->resetButton = $params->get('reset_button', 0) && $this->editable == '1' ? $btnLayout->render($layoutData) : '';

		if ($copyIcon !== '')
		{
			$copyIcon  = Html::icon($copyIcon);
			$copyLabel = $params->get('copy_icon_location', 'before') == 'before' ? $copyIcon . '&nbsp;' . $copyLabel : $copyLabel . '&nbsp;' . $copyIcon;
		}

		$layoutData       = (object) array(
			'type'      => 'submit',
			'class'     => $params->get('copy_button_class', '') . ' button',
			'name'      => 'Copy',
			'label'     => $copyLabel,
			'formModel' => $model
		);
		$form->copyButton = $params->get('copy_button', 0) && $this->editable && $model->getRowId() != ''
			? $btnLayout->render($layoutData) : '';

		if ($applyIcon !== '')
		{
			$applyIcon  = Html::icon($applyIcon);
			$before     = $params->get('apply_icon_location', 'before') == 'before';
			$applyLabel = $before ? $applyIcon . '&nbsp;' . $applyLabel : $applyLabel . '&nbsp;' . $applyIcon;
		}

		$layoutData = (object) array(
			'type'      => $model->isAjax() ? 'button' : 'submit',
			'class'     => $params->get('apply_button_class', '') . ' button',
			'name'      => 'apply',
			'label'     => $applyLabel,
			'formModel' => $model
		);

		$form->applyButton = $params->get('apply_button', 0) && $this->editable
			? $btnLayout->render($layoutData) : '';

		if ($deleteIcon !== '')
		{
			$deleteIcon  = Html::icon($deleteIcon);
			$before      = $params->get('delete_icon_location', 'before') == 'before';
			$deleteLabel = $before ? $deleteIcon . '&nbsp;' . $deleteLabel : $deleteLabel . '&nbsp;' . $deleteIcon;
		}

		$layoutData = (object) array(
			'type'      => 'submit',
			'class'     => $params->get('delete_button_class', 'btn-danger') . ' button',
			'name'      => 'delete',
			'label'     => $deleteLabel,
			'formModel' => $model
		);

		$form->deleteButton = $params->get('delete_button', 0) && $canDelete && $this->editable && $thisRowId != ''
			? $btnLayout->render($layoutData) : '';

		if ($goBackIcon !== '')
		{
			$goBackIcon  = Html::icon($goBackIcon);
			$before      = $params->get('goback_icon_location', 'before') == 'before';
			$goBackLabel = $before ? $goBackIcon . '&nbsp;' . $goBackLabel : $goBackLabel . '&nbsp;' . $goBackIcon;
		}


		$layoutData = (object) array(
			'type'      => 'button',
			'class'     => 'clearSession',
			'name'      => '',
			'label'     => Text::_('COM_FABRIK_CLEAR_MULTI_PAGE_SESSION'),
			'formModel' => $model
		);

		$multiPageSession                  = $model->sessionModel && $model->sessionModel->last_page > 0;
		$form->clearMultipageSessionButton = $multiPageSession ? $btnLayout->render($layoutData) : '';

		if (!$this->isMambot)
		{
			$layoutData = (object) array(
				'type'       => 'button',
				'class'      => $params->get('goback_button_class', '') . ' button',
				'name'       => 'Goback',
				'label'      => $goBackLabel,
				'attributes' => $model->isAjax() ? '' : Worker::goBackAction(),
				'formModel'  => $model
			);

			$form->gobackButton = $params->get('goback_button', 0) ? $btnLayout->render($layoutData) : '';
		}
		else
		{
			$form->gobackButton = '';
		}

		if ($model->isEditable() && $params->get('submit_button', 1))
		{
			$submitClass = FStringHelper::clean($form->submit_button_label);
			$submitIcon  = $params->get('save_icon', '');
			$submitLabel = Text::_($form->submit_button_label);

			if ($submitIcon !== '')
			{
				$submitIcon  = Html::icon($submitIcon);
				$before      = $params->get('save_icon_location', 'before') == 'before';
				$submitLabel = $before ? $submitIcon . '&nbsp;' . $submitLabel : $submitLabel . '&nbsp;' . $submitIcon;
			}

			$layoutData = (object) array(
				'type'      => $model->isAjax() ? 'button' : 'submit',
				'class'     => $params->get('save_button_class', 'btn-primary') . ' button ' . $submitClass,
				'name'      => 'Submit',
				'label'     => $submitLabel,
				'id'        => 'fabrikSubmit_' . $model->getId(),
				'formModel' => $model
			);

			$form->submitButton = $btnLayout->render($layoutData);
		}
		else
		{
			$form->submitButton = '';
		}

		$layoutData          = (object) array(
			'formModel' => $model,
			'row'       => $row,
			'rowid'     => $thisRowId,
			'itemid'    => $itemId
		);
		$form->customButtons = $model->getLayout('form.fabrik-custom-button')->render($layoutData);

		if ($this->isMultiPage)
		{
			$layoutData       = (object) array(
				'type'      => 'button',
				'class'     => 'fabrikPagePrevious button',
				'name'      => 'fabrikPagePrevious',
				'label'     => Html::icon('icon-previous', Text::_('COM_FABRIK_PREV')),
				'formModel' => $model
			);
			$form->prevButton = $btnLayout->render($layoutData);

			$layoutData = (object) array(
				'type'      => 'button',
				'class'     => 'fabrikPageNext button',
				'name'      => 'fabrikPageNext',
				'label'     => Text::_('COM_FABRIK_NEXT') . '&nbsp;' . Html::icon('icon-next'),
				'formModel' => $model
			);

			$form->nextButton = $btnLayout->render($layoutData);
		}
		else
		{
			$form->nextButton = '';
			$form->prevButton = '';
		}

		// $$$ hugh - hide actions section if we're printing or format=PDF, or if not actions selected
		$noButtons = (
			empty($form->nextButton)
			&& empty($form->prevButton)
			&& empty($form->submitButton)
			&& empty($form->gobackButton)
			&& empty($form->deleteButton)
			&& empty($form->applyButton)
			&& empty($form->copyButton)
			&& empty($form->resetButton)
			&& empty($form->clearMultipageSessionButton)
			&& empty($form->customButtons)
		);

		$this->hasActions = ($input->get('print', '0') == '1' || $input->get('format') === 'pdf' || $noButtons) ? false : true;

		$format     = $model->isAjax() ? 'raw' : 'html';
		$fields[]   = '<input type="hidden" name="format" value="' . $format . '" />';
		$groups     = $model->getGroupsHiarachy();
		$origRowIds = array();

		if ($model->hasErrors())
		{
			$origRowIds = $this->app->input->getRaw('fabrik_group_rowids', array());
		}

		foreach ($groups as $groupModel)
		{
			if ($groupModel->isJoin())
			{
				$groupId = $groupModel->getId();

				if (array_key_exists($groupId, $origRowIds))
				{
					$groupRowIds = htmlentities($origRowIds[$groupId]);
				}
				else
				{
					$groupPk = $groupModel->getJoinModel()->getForeignId();

					// Use raw otherwise we inject the actual <input> into the hidden field's value
					$groupPk     .= '_raw';
					$groupRowIds = (array) FArrayHelper::getValue($this->data, $groupPk, array());
					$groupRowIds = htmlentities(json_encode($groupRowIds));
				}

				// Used to check against in group process(), when deleting removed repeat groups
				$fields[] = '<input type="hidden" name="fabrik_group_rowids[' . $groupId . ']" value="' . $groupRowIds . '" />';
			}

			$group = $groupModel->getGroup();
			$c     = $groupModel->repeatTotal;

			// Used for validations
			$fields[] = '<input type="hidden" name="fabrik_repeat_group[' . $group->id . ']" value="' . $c . '" id="fabrik_repeat_group_'
				. $group->id . '_counter" />';
		}

		// $$$ hugh - testing social_profile_hash stuff
		if ($input->get('fabrik_social_profile_hash', '') != '')
		{
			$fields[] = '<input type="hidden" name="fabrik_social_profile_hash" value="' . $input->get('fabrik_social_profile_hash', '')
				. '" id="fabrik_social_profile_hash" />';
		}

		$this->_cryptQueryString($fields);
		$this->_cryptViewOnlyElements($fields);
		$this->hiddenFields = implode("\n", $fields);
	}

	/**
	 * Store all fabrik querystring vars as encrypted hidden fields
	 * this is used when you have a table with a "Tables with database join elements linking to this table" link to a
	 * form. and when the form's pk element (found in the link) is set to read only OR if you are filtering from an
	 * url?
	 *
	 * @param   array &$fields hidden fields
	 *
	 * @return  void
	 *
	 * @since 4.0
	 */
	protected function _cryptQueryString(&$fields)
	{
		$crypt = Worker::getCrypt();

		/** @var FormModel $formModel */
		$formModel = $this->getModel();
		$filter    = InputFilter::getInstance();
		$get       = $filter->clean($_GET, 'array');

		foreach ($get as $key => $input)
		{
			// 	$$$ rob test if passing in _raw value via qs -used in fabsubs
			if (!$formModel->hasElement($key))
			{
				$key = FStringHelper::rtrimword($key, '_raw');
			}

			if ($formModel->hasElement($key))
			{
				$elementModel = $formModel->getElement($key);

				if (!$elementModel->canUse())
				{
					$input = (is_array($input) && array_key_exists('value', $input)) ? $input['value'] : $input;
					/** $$$ hugh - need to check if $value is an array, 'cos if it isn't, like when presetting
					 * a new form element with &table___element=foo, getValue was chomping it down to just first character
					 * see http://fabrikar.com/forums/showthread.php?p=82726#post82726
					 */

					if (is_array($input))
					{
						$input = FArrayHelper::getValue($input, 'raw', $input);
					}

					// $$$ hugh - the aptly named SimpleCrypt encrypt is going to barf and toss a warning if we try
					// and encrypt a null or empty string
					if (empty($input))
					{
						$input = '';
					}
					else
					{
						$input = $crypt->encrypt($input);
					}

					$fields[] = '<input type="hidden" name="fabrik_vars[querystring][' . $key . ']" value="' . $input . '" />';
				}
			}
		}
	}

	/**
	 * Encrypt view only elements
	 *
	 * @param   array &$aHiddenFields Hidden fields
	 *
	 * @return  void
	 *
	 * @since 4.0
	 */
	protected function _cryptViewOnlyElements(&$aHiddenFields)
	{
		/** @var FormModel $model */
		$model  = $this->getModel();
		$crypt  = Worker::getCrypt();
		$fields = array();
		$ro     = $model->getReadOnlyVals();

		foreach ($ro as $key => $pair)
		{
			$repeatGroup = $pair['repeatgroup'];
			$isJoin      = $pair['join'];
			$input       = $pair['data'];

			// $$$ rob not sure this is correct now as I modified the readOnlyVals structure to contain info about if its in a group
			// and it now contains the repeated group data
			$input = (is_array($input) && array_key_exists('value', $input)) ? $input['value'] : $input;

			if ($repeatGroup)
			{
				$ar    = array();
				$input = (array) $input;

				foreach ($input as $i)
				{
					if (is_array($i))
					{
						// Elements with sub options in repeat group
						$i = json_encode($i);
					}

					$ar[] = $i;
				}

				$input = $isJoin ? $ar : json_encode($ar);
			}
			else
			{
				if (is_array($input))
				{
					// Elements with sub options not in repeat group
					$input = json_encode($input);
				}
			}

			if (is_array($input))
			{
				for ($x = 0; $x < count($input); $x++)
				{
					if (trim($input[$x]) !== '')
					{
						$input[$x] = $crypt->encrypt($input[$x]);
					}
				}
			}
			else
			{
				if (trim($input) !== '')
				{
					$input = $crypt->encrypt($input);
				}
			}

			$safeKey = FStringHelper::rtrimword($key, '[]');

			// $$$ rob - no don't do below as it will strip out join names join[x][fullname] => join
			// $key = preg_replace("/\[(.*)\]/", '', $key);
			if (!array_key_exists($safeKey, $fields))
			{
				$fields[$safeKey] = $input;
			}
			else
			{
				$fields[$safeKey]   = (array) $fields[$safeKey];
				$fields[$safeKey][] = $input;
			}
		}

		foreach ($fields as $key => $input)
		{
			if (is_array($input))
			{
				for ($c = 0; $c < count($input); $c++)
				{
					$i        = $input[$c];
					$fields[] = '<input type="hidden" name="fabrik_vars[querystring][' . $key . '][' . $c . ']" value="' . $i . '" />';
				}

				unset($fields[$key]);
			}
			else
			{
				$fields[$key] = '<input type="hidden" name="fabrik_vars[querystring][' . $key . ']" value="' . $input . '" />';
			}
		}

		$aHiddenFields = array_merge($aHiddenFields, array_values($fields));
	}
}