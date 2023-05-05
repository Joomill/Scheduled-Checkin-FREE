<?php
/*
 *  package: Scheduled Check-in Items plugin
 *  copyright: Copyright (c) 2023. Jeroen Moolenschot | Joomill
 *  license: GNU General Public License version 2 or later
 *  link: https://www.joomill-extensions.com
 */

// No direct access.
defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

class JFormFieldPRO extends JFormField
{
	protected $type = 'pro';

	protected function getInput()
	{
		$text = Text::_('PLG_TASK_CHECKIN_PRO_ONLY');
		return
			'<code>' . $text . '</code>';
	}


}