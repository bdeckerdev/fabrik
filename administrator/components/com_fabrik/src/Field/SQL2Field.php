<?php
/**
 * Create a list from an SQL query
 *
 * @package     Joomla
 * @subpackage  Fabrik
 * @copyright   Copyright (C) 2005-2016  Media A-Team, Inc. - All rights reserved.
 * @license     GNU/GPL http://www.gnu.org/copyleft/gpl.html
 * @since       1.6
 */

namespace Joomla\Component\Fabrik\Administrator\Field;

// No direct access
defined('_JEXEC') or die('Restricted access');

use Fabrik\Helpers\Worker;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\Form\FormHelper;
use Joomla\CMS\HTML\HTMLHelper;

FormHelper::loadFieldClass('list');

/**
 * Renders a SQL element
 *
 * @package  Fabrik
 * @since    3.0
 */
class SQL2Field extends ListField
{
	/**
	 * @var string
	 *
	 * @since 4.0
	 */
	protected $type = 'sql2';

	/**
	 * Element name
	 *
	 * @access    protected
	 * @var        string
	 *
	 * @since     4.0
	 */
	protected $name = 'SQL';

	/**
	 * Method to get the field options.
	 *
	 * @return  array  The field option objects.
	 *
	 * @since 4.0
	 */
	protected function getOptions()
	{
		$db    = Worker::getDbo(true);
		$check = $this->element['checkexists'] ? (bool) $this->element['checkexists'] : false;

		if ($check)
		{
			$q = explode(" ", $this->element['query']);
			$i = array_search('FROM', $q);

			if (!$i)
			{
				$i = array_search('from', $q);
			}

			$i++;
			$tbl = $db->replacePrefix($q[$i]);
			$db->setQuery("SHOW TABLES");
			$rows  = $db->loadColumn();
			$found = in_array($tbl, $rows) ? true : false;

			if (!$found)
			{
				return array(HTMLHelper::_('select.option', $tbl . ' not found', ''));
			}
		}

		$db->setQuery($this->element['query']);
		$key = $this->element['key_field'] ? $this->element['key_field'] : 'value';
		$val = $this->element['value_field'] ? $this->element['value_field'] : $this->name;

		if ($this->element['add_select'])
		{
			$rows = array(HTMLHelper::_('select.option', ''));
			$rows = array_merge($rows, (array) $db->loadObjectList());
		}
		else
		{
			$rows = $db->loadObjectList();
		}

		return $rows;
	}
}