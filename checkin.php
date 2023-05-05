<?php
/*
 *  package: Scheduled Check-in Items plugin
 *  copyright: Copyright (c) 2023. Jeroen Moolenschot | Joomill
 *  license: GNU General Public License version 2 or later
 *  link: https://www.joomill-extensions.com
 */

// Restrict direct access
defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Event\SubscriberInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Access\Access;
use Joomla\CMS\User\UserFactoryInterface;

/**
 * Task plugin with routines to automatically check-in in all items that are checked out longer than you defined.
 *
 * @since  4.1.0
 */
class PlgTaskCheckin extends CMSPlugin implements SubscriberInterface
{
	use TaskPluginTrait;

	/**
	 * @var string[]
	 * @since 4.1.0
	 */
	protected const TASKS_MAP = [
		'plg_task_checkin'             => [
			'langConstPrefix' => 'PLG_TASK_CHECKIN',
			'form'            => 'checkin_parameters',
			'method'          => 'checkin',
		],
	];

	/**
	 * The application object.
	 *
	 * @var  CMSApplication
	 * @since 4.1.0
	 */
	protected $app;

	/**
	 * @var  DatabaseInterface
	 * @since  4.1.0
	 */
	protected $db;

	/**
	 * Autoload the language file.
	 *
	 * @var boolean
	 * @since 4.1.0
	 */
	protected $autoloadLanguage = true;

	/**
	 * @inheritDoc
	 *
	 * @return string[]
	 *
	 * @since 4.1.0
	 */
	public static function getSubscribedEvents(): array
	{
		return [
			'onTaskOptionsList'    => 'advertiseRoutines',
			'onExecuteTask'        => 'standardRoutineHandler',
			'onContentPrepareForm' => 'enhanceTaskItemForm',
		];
	}

	/**
	 * @param   ExecuteTaskEvent  $event  The onExecuteTask event
	 *
	 * @return void
	 *
	 * @since 4.1.0
	 * @throws Exception
	 */
	public function checkin(ExecuteTaskEvent $event): int
	{
        $params    	  = $event->getArgument('params');
        $max_checkout = $params->maxcheckout * 3600;

		$this->startRoutine($event);

		if (Factory::getApplication()->isClient('cli'))
		{
			$this->setGrant();
		}

		if ($event->getArgument('params')->articles ?? false)
		{
			$this->checkinArticles($max_checkout);
		}

		$this->endRoutine($event, Status::OK);
		return Status::OK;
	}

	private function setGrant() : void
	{
		// Get all usergroups with Super User access
		$db = $this->db;
		$query = $db->getQuery(true)
			        ->select([$db->qn('id')])
                    ->from($db->qn('#__usergroups'));
		$groups = $db->setQuery($query)->loadColumn();

		// Get the groups that are Super Users
		$groups = array_filter($groups, function ($gid) {
			return Access::checkGroup($gid, 'core.admin');
		});

		foreach ($groups as $gid)
		{
			$uids = Access::getUsersByGroup($gid);
			$user = Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($uids[0]);
			$this->app->getSession()->set('user', $user);
			break;
		}
	}

	private function checkinArticles($max_checkout) : void
	{
	    $db = $this->db;
    	$query = $db->getQuery(true)
				    ->select($db->qn(array('id', 'checked_out', 'checked_out_time')))
			        ->from($db->qn('#__content'))
			        ->where($db->qn('checked_out') . ' > 0');
	    $items = $db->setQuery($query)->loadObjectList();

        $articles = 0;
	    foreach ($items as $item)
	    {
	        $checked_out = strtotime(Factory::getDate('now')) - strtotime($item->checked_out_time);

            if ($checked_out >= $max_checkout) {
		    	$db = $this->db;
		    	$query = $db->getQuery(true)
        					->update($db->qn('#__content'))
       						->set($db->qn('checked_out') . ' = NULL')
       						->set($db->qn('checked_out_time') . ' = NULL')
       						->where($db->qn('id') . ' = ' .  $item->id);
		      	$db->setQuery($query)->execute();
                $articles++;
	        }
	    }

	    $this->logTask(Text::sprintf('PLG_TASK_CHECKIN_ARTICLES', $articles), 'notice');
	}
}