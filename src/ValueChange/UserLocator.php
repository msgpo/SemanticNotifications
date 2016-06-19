<?php

namespace SMW\Notifications\ValueChange;

use SMW\ApplicationFactory;
use SMW\Notifications\IteratorFactory;
use EchoEvent;
use User;

/**
 * @license GNU GPL v2+
 * @since 1.0
 *
 * @author mwjames
 */
class UserLocator {

	/**
	 * Find out for which of the properties that have changed the assigned group
	 * and for each group do locate its members(users).
	 *
	 * @see EchoUserLocator::locateArticleCreator
	 * @since 1.0
	 *
	 * @param EchoEvent $event
	 *
	 * @return User[]|\Iterator
	 */
	public static function doLocateEventSubscribers( EchoEvent $event ) {

		$start = microtime( true );
		$extra = $event->getExtra();

		if ( !isset( $extra['subject'] ) || !isset( $extra['properties'] ) ) {
			return array();
		}

		$store = ApplicationFactory::getInstance()->getStore( '\SMW\SQLStore\SQLStore' );
		$iteratorFactory = new IteratorFactory();

		$notificationGroupsLocator = new NotificationGroupsLocator(
			$store
		);

		$subSemanticDataMatch = isset( $extra['subSemanticDataMatch'] ) ? $extra['subSemanticDataMatch'] : array();
		$type = $event->getType();

		if ( $type === ChangeNotifications::SPECIFICATION_CHANGE ) {
			$groups = $notificationGroupsLocator->getSpecialGroupOnSpecificationChange();
		} else {
			// Find groups assigned to properties on a "lazy" request during the
			// iteration process
			$groups = $iteratorFactory->newMappingIterator(
				$extra['properties'],
				$notificationGroupsLocator->getNotificationsToGroupListByCallback( $subSemanticDataMatch )
			);
		}

		$recursiveGroupMemberIterator = $iteratorFactory->newRecursiveGroupMembersIterator(
			$groups,
			$store
		);

		$agentName = $event->getAgent()->getName();

		$recursiveGroupMemberIterator->notifyAgent(
			$event->getExtraParam( 'notifyAgent', false )
		);

		$recursiveGroupMemberIterator->setAgentName(
			$agentName
		);

		$recursiveGroupMemberIterator->setSubject(
			$extra['subject']
		);

		// Returns a flat array when iterating over the children
		$recursiveIteratorIterator = $iteratorFactory->newRecursiveIteratorIterator(
			$recursiveGroupMemberIterator
		);

		wfDebugLog( 'smw', 'Agent ' . $agentName );

		// Access the user only on request by the iterator
		$mappingIterator = $iteratorFactory->newMappingIterator( $recursiveIteratorIterator, function( $recipient ) {
			wfDebugLog( 'smw', 'User ' . $recipient );
			return User::newFromName( $recipient, false );
		} );

		wfDebugLog( 'smw', __METHOD__ . ' procTime (sec): ' . round( ( microtime( true ) - $start ), 7 ) );

		return $mappingIterator;
	}

}
