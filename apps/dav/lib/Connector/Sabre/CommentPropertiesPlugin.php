<?php
/**
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @copyright Copyright (c) 2016, ownCloud GmbH.
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\DAV\Connector\Sabre;

use OCP\Comments\ICommentsManager;
use OCP\IUserSession;
use Sabre\DAV\PropFind;
use Sabre\DAV\ServerPlugin;

class CommentPropertiesPlugin extends ServerPlugin {

	const PROPERTY_NAME_HREF   = '{http://owncloud.org/ns}comments-href';
	const PROPERTY_NAME_COUNT  = '{http://owncloud.org/ns}comments-count';
	const PROPERTY_NAME_UNREAD = '{http://owncloud.org/ns}comments-unread';

	/** @var  \Sabre\DAV\Server */
	protected $server;

	/** @var ICommentsManager */
	private $commentsManager;

	/** @var IUserSession */
	private $userSession;

	public function __construct(ICommentsManager $commentsManager, IUserSession $userSession) {
		$this->commentsManager = $commentsManager;
		$this->userSession = $userSession;
	}

	/**
	 * This initializes the plugin.
	 *
	 * This function is called by Sabre\DAV\Server, after
	 * addPlugin is called.
	 *
	 * This method should set up the required event subscriptions.
	 *
	 * @param \Sabre\DAV\Server $server
	 * @return void
	 */
	function initialize(\Sabre\DAV\Server $server) {
		$this->server = $server;
		$this->server->on('propFind', [$this, 'handleGetProperties']);
	}

	/**
	 * Adds tags and favorites properties to the response,
	 * if requested.
	 *
	 * @param PropFind $propFind
	 * @param \Sabre\DAV\INode $node
	 * @return void
	 */
	public function handleGetProperties(
		PropFind $propFind,
		\Sabre\DAV\INode $node
	) {
		if (!($node instanceof File) && !($node instanceof Directory)) {
			return;
		}

		$propFind->handle(self::PROPERTY_NAME_COUNT, function() use ($node) {
			return $this->commentsManager->getNumberOfCommentsForObject('files', strval($node->getId()));
		});

		$propFind->handle(self::PROPERTY_NAME_HREF, function() use ($node) {
			return $this->getCommentsLink($node);
		});

		$propFind->handle(self::PROPERTY_NAME_UNREAD, function() use ($node) {
			return $this->getUnreadCount($node);
		});
	}

	/**
	 * returns a reference to the comments node
	 *
	 * @param Node $node
	 * @return mixed|string
	 */
	public function getCommentsLink(Node $node) {
		$href =  $this->server->getBaseUri();
		$entryPoint = strpos($href, '/remote.php/');
		if($entryPoint === false) {
			// in case we end up somewhere else, unexpectedly.
			return null;
		}
		$commentsPart = 'dav/comments/files/' . rawurldecode($node->getId());
		$href = substr_replace($href, $commentsPart, $entryPoint + strlen('/remote.php/'));
		return $href;
	}

	/**
	 * returns the number of unread comments for the currently logged in user
	 * on the given file or directory node
	 *
	 * @param Node $node
	 * @return Int|null
	 */
	public function getUnreadCount(Node $node) {
		$user = $this->userSession->getUser();
		if(is_null($user)) {
			return null;
		}

		$lastRead = $this->commentsManager->getReadMark('files', strval($node->getId()), $user);

		return $this->commentsManager->getNumberOfCommentsForObject('files', strval($node->getId()), $lastRead);
	}

}
