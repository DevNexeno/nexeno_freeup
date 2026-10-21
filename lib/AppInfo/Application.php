<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nexeno contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\NexenoFreeUp\AppInfo;

use OCA\NexenoFreeUp\Listener\NavigationListener;
use OCA\NexenoFreeUp\Listener\UserSyncListener;
use OCA\NexenoFreeUp\Notification\Notifier;
use OCP\Accounts\UserUpdatedEvent;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Group\Events\UserAddedEvent;
use OCP\Group\Events\UserRemovedEvent;
use OCP\Navigation\Events\LoadAdditionalEntriesEvent;
use OCP\User\Events\PostLoginEvent;
use OCP\User\Events\UserChangedEvent;
use OCP\User\Events\UserCreatedEvent;
use OCP\User\Events\UserDeletedEvent;
use OCP\User\Events\UserFirstTimeLoggedInEvent;
use OCP\User\Events\UserLoggedInEvent;
use OCP\User\Events\UserLoggedInWithCookieEvent;

class Application extends App implements IBootstrap {
	public const APP_ID = 'nexeno_freeup';

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerNotifierService(Notifier::class);

		$context->registerEventListener(LoadAdditionalEntriesEvent::class, NavigationListener::class);

		$context->registerEventListener(UserChangedEvent::class, UserSyncListener::class);
		$context->registerEventListener(UserUpdatedEvent::class, UserSyncListener::class);
		$context->registerEventListener(UserAddedEvent::class, UserSyncListener::class);
		$context->registerEventListener(UserRemovedEvent::class, UserSyncListener::class);
		$context->registerEventListener(UserCreatedEvent::class, UserSyncListener::class);
		$context->registerEventListener(UserDeletedEvent::class, UserSyncListener::class);

		$context->registerEventListener(UserLoggedInEvent::class, UserSyncListener::class);
		$context->registerEventListener(PostLoginEvent::class, UserSyncListener::class);
		$context->registerEventListener(UserFirstTimeLoggedInEvent::class, UserSyncListener::class);
		$context->registerEventListener(UserLoggedInWithCookieEvent::class, UserSyncListener::class);
	}

	public function boot(IBootContext $context): void {
	}
}
