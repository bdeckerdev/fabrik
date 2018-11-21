<?php
/**
 * Fabrik Component Helper
 *
 * @package     Joomla.Administrator
 * @subpackage  Fabrik
 * @copyright   Copyright (C) 2005-2018  Media A-Team, Inc. - All rights reserved.
 * @license     GNU/GPL http://www.gnu.org/copyleft/gpl.html
 * @since       4.0
 */

namespace Joomla\Component\Fabrik\Administrator\Helper;

// No direct access
defined('_JEXEC') or die('Restricted access');

use Fabrik\Helpers\Worker;
use Joomla\CMS\Access\Access;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Filter\InputFilter;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Object\CMSObject;
use Joomla\String\StringHelper;
use Joomla\CMS\MVC\View\HtmlView;

/**
 * Fabrik Component Helper
 *
 * @package     Joomla
 * @subpackage  Fabrik
 * @since       4.0
 */
class FabrikAdminHelper
{
	/**
	 * Prepare the date for saving
	 * DATES SHOULD BE SAVED AS UTC
	 *
	 * @param   string $strdate publish down date
	 *
	 * @return  string
	 *
	 * @since 4.0
	 */
	public static function prepareSaveDate($strdate)
	{
		/** @var CMSApplication $app */
		$app      = Factory::getApplication();
		$config   = $app->getConfig();
		$tzoffset = $config->get('offset');
		$db       = Worker::getDbo(true);

		// Handle never unpublish date
		if (trim($strdate) == Text::_('Never') || trim($strdate) == '' || trim($strdate) == $db->getNullDate())
		{
			$strdate = $db->getNullDate();
		}
		else
		{
			if (StringHelper::strlen(trim($strdate)) <= 10)
			{
				$strdate .= ' 00:00:00';
			}

			$date    = Factory::getDate($strdate, $tzoffset);
			$strdate = $date->toSql();
		}

		return $strdate;
	}

	/**
	 * Gets a list of the actions that can be performed.
	 *
	 * @param   int $categoryId The category ID.
	 *
	 * @since    4.0
	 *
	 * @return    CMSObject
	 */
	public static function getActions($categoryId = 0)
	{
		$user   = Factory::getUser();
		$result = new CMSObject();

		if (empty($categoryId))
		{
			$assetName = 'com_fabrik';
		}
		else
		{
			$assetName = 'com_fabrik.category.' . (int) $categoryId;
		}

		$actions = array('core.admin', 'core.manage', 'core.create', 'core.edit', 'core.edit.state', 'core.delete');

		foreach ($actions as $action)
		{
			$result->set($action, $user->authorise($action, $assetName));
		}

		return $result;
	}

	/**
	 * Configure the Linkbar.
	 *
	 * @param   string $vName The name of the active view.
	 *
	 * @since    4.0
	 *
	 * @return    void
	 */

	public static function addSubmenu($vName)
	{
		$vizUrl = 'index.php?option=com_fabrik&view=visualizations';

		\JHtmlSidebar::addEntry(Text::_('COM_FABRIK_SUBMENU_HOME'), 'index.php?option=com_fabrik', $vName == 'home');
		\JHtmlSidebar::addEntry(Text::_('COM_FABRIK_SUBMENU_LISTS'), 'index.php?option=com_fabrik&view=lists', $vName == 'lists');
		\JHtmlSidebar::addEntry(Text::_('COM_FABRIK_SUBMENU_FORMS'), 'index.php?option=com_fabrik&view=forms', $vName == 'forms');
		\JHtmlSidebar::addEntry(Text::_('COM_FABRIK_SUBMENU_GROUPS'), 'index.php?option=com_fabrik&view=groups', $vName == 'groups');
		\JHtmlSidebar::addEntry(Text::_('COM_FABRIK_SUBMENU_ELEMENTS'), 'index.php?option=com_fabrik&view=elements', $vName == 'elements');
		\JHtmlSidebar::addEntry(Text::_('COM_FABRIK_SUBMENU_VISUALIZATIONS'), $vizUrl, $vName == 'visualizations');
		//\JHtmlSidebar::addEntry(Text::_('COM_FABRIK_SUBMENU_PACKAGES'), 'index.php?option=com_fabrik&view=packages', $vName == 'packages');
		\JHtmlSidebar::addEntry(Text::_('COM_FABRIK_SUBMENU_CONNECTIONS'), 'index.php?option=com_fabrik&view=connections', $vName == 'connections');
		\JHtmlSidebar::addEntry(Text::_('COM_FABRIK_SUBMENU_CRONS'), 'index.php?option=com_fabrik&view=crons', $vName == 'crons');
	}

	/**
	 * Applies the content tag filters to arbitrary text as per settings for current user group
	 *
	 * @param   string $text The string to filter
	 *
	 * @return  string  The filtered string
	 *
	 * @since 4.0
	 */
	public static function filterText($text)
	{
		// Filter settings
		$config     = ComponentHelper::getParams('com_config');
		$user       = Factory::getUser();
		$userGroups = Access::getGroupsByUser($user->get('id'));

		$filters = $config->get('filters');

		$blackListTags       = array();
		$blackListAttributes = array();

		$whiteListTags       = array();
		$whiteListAttributes = array();

		$noHtml     = false;
		$whiteList  = false;
		$blackList  = false;
		$unfiltered = false;

		// Cycle through each of the user groups the user is in.
		// Remember they are include in the Public group as well.
		foreach ($userGroups as $groupId)
		{
			// May have added a group by not saved the filters.
			if (!isset($filters->$groupId))
			{
				continue;
			}

			// Each group the user is in could have different filtering properties.
			$filterData = $filters->$groupId;
			$filterType = StringHelper::strtoupper($filterData->filter_type);

			if ($filterType == 'NH')
			{
				// Maximum HTML filtering.
				$noHtml = true;
			}
			elseif ($filterType == 'NONE')
			{
				// No HTML filtering.
				$unfiltered = true;
			}
			else
			{
				// Black or white list.
				// Preprocess the tags and attributes.
				$tags           = explode(',', $filterData->filter_tags);
				$attributes     = explode(',', $filterData->filter_attributes);
				$tempTags       = array();
				$tempAttributes = array();

				foreach ($tags as $tag)
				{
					$tag = trim($tag);

					if ($tag)
					{
						$tempTags[] = $tag;
					}
				}

				foreach ($attributes as $attribute)
				{
					$attribute = trim($attribute);

					if ($attribute)
					{
						$tempAttributes[] = $attribute;
					}
				}

				// Collect the black or white list tags and attributes.
				// Each list is cumulative.
				if ($filterType == 'BL')
				{
					$blackList           = true;
					$blackListTags       = array_merge($blackListTags, $tempTags);
					$blackListAttributes = array_merge($blackListAttributes, $tempAttributes);
				}
				elseif ($filterType == 'WL')
				{
					$whiteList           = true;
					$whiteListTags       = array_merge($whiteListTags, $tempTags);
					$whiteListAttributes = array_merge($whiteListAttributes, $tempAttributes);
				}
			}
		}

		// Remove duplicates before processing (because the black list uses both sets of arrays).
		$blackListTags       = array_unique($blackListTags);
		$blackListAttributes = array_unique($blackListAttributes);
		$whiteListTags       = array_unique($whiteListTags);
		$whiteListAttributes = array_unique($whiteListAttributes);

		// Unfiltered assumes first priority.
		if ($unfiltered)
		{
			// Don't apply filtering.
		}
		else
		{
			// Black lists take second precedence.
			if ($blackList)
			{
				// Remove the white-listed attributes from the black-list.
				$tags   = array_diff($blackListTags, $whiteListTags);
				$attrs  = array_diff($blackListAttributes, $whiteListAttributes);
				$filter = InputFilter::getInstance($tags, $attrs, 1, 1);
			}
			// White lists take third precedence.
			elseif ($whiteList)
			{
				$filter = InputFilter::getInstance($whiteListTags, $whiteListAttributes, 0, 0, 0);
			}
			// No HTML takes last place.
			else
			{
				$filter = InputFilter::getInstance();
			}

			$text = $filter->clean($text, 'html');
		}

		return $text;
	}


	/**
	 * Set the layout based on Joomla version
	 * Allows for loading of new bootstrap admin templates in J3.0+
	 *
	 * @param   HtmlView  $view  current view to setLayout for
	 *
	 * @return  void
	 *
	 * @since 4.0
	 */
	public static function setViewLayout($view)
	{
		// If rendering a list inside a form and viewing in admin - there were layout name conflicts (so renamed bootstrap to admin_bootstrap)
		//$layout = $view->getName() === 'list' ? 'admin_bootstrap' : 'bootstrap';

		$view->setLayout('default');
	}
}
