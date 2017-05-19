<?php
/**
 * @copyright Copyright (c) 2016 Lukas Reschke <lukas@statuscode.ch>
 *
 * @author Lukas Reschke <lukas@statuscode.ch>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace Tests\Settings;

use OC\Accounts\AccountManager;
use OC\Settings\Admin\Sharing;
use OC\Settings\Manager;
use OC\Settings\Mapper;
use OC\Settings\Personal\AppPasswords;
use OC\Settings\Section;
use OCP\Encryption\IManager;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Lock\ILockingProvider;
use Test\TestCase;

class ManagerTest extends TestCase {
	/** @var Manager|\PHPUnit_Framework_MockObject_MockObject */
	private $manager;
	/** @var ILogger|\PHPUnit_Framework_MockObject_MockObject */
	private $logger;
	/** @var IDBConnection|\PHPUnit_Framework_MockObject_MockObject */
	private $dbConnection;
	/** @var IL10N|\PHPUnit_Framework_MockObject_MockObject */
	private $l10n;
	/** @var IConfig|\PHPUnit_Framework_MockObject_MockObject */
	private $config;
	/** @var IManager|\PHPUnit_Framework_MockObject_MockObject */
	private $encryptionManager;
	/** @var IUserManager|\PHPUnit_Framework_MockObject_MockObject */
	private $userManager;
	/** @var ILockingProvider|\PHPUnit_Framework_MockObject_MockObject */
	private $lockingProvider;
	/** @var IRequest|\PHPUnit_Framework_MockObject_MockObject */
	private $request;
	/** @var Mapper|\PHPUnit_Framework_MockObject_MockObject */
	private $mapper;
	/** @var IURLGenerator|\PHPUnit_Framework_MockObject_MockObject */
	private $url;
	/** @var AccountManager|\PHPUnit_Framework_MockObject_MockObject */
	private $accountManager;
	/** @var  IGroupManager|\PHPUnit_Framework_MockObject_MockObject */
	private $groupManager;
	/** @var  IFactory|\PHPUnit_Framework_MockObject_MockObject */
	private $l10nFactory;
	/** @var \OC_Defaults|\PHPUnit_Framework_MockObject_MockObject */
	private $defaults;

	public function setUp() {
		parent::setUp();

		$this->logger = $this->createMock(ILogger::class);
		$this->dbConnection = $this->createMock(IDBConnection::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->config = $this->createMock(IConfig::class);
		$this->encryptionManager = $this->createMock(IManager::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->lockingProvider = $this->createMock(ILockingProvider::class);
		$this->request = $this->createMock(IRequest::class);
		$this->mapper = $this->createMock(Mapper::class);
		$this->url = $this->createMock(IURLGenerator::class);
		$this->accountManager = $this->createMock(AccountManager::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->l10nFactory = $this->createMock(IFactory::class);
		$this->defaults = $this->createMock(\OC_Defaults::class);

		$this->manager = new Manager(
			$this->logger,
			$this->dbConnection,
			$this->l10n,
			$this->config,
			$this->encryptionManager,
			$this->userManager,
			$this->lockingProvider,
			$this->request,
			$this->mapper,
			$this->url,
			$this->accountManager,
			$this->groupManager,
			$this->l10nFactory,
			$this->defaults
		);
	}

	public function settingsTypeProvider() {
		return [
			['admin', 'admin_settings'],
			['personal', 'personal_settings'],
		];
	}

	/**
	 * @dataProvider settingsTypeProvider
	 * @param string $type
	 * @param string $table
	 */
	public function testSetupSettingsUpdate($type, $table) {
		$className = 'OCA\Files\Settings\Admin';

		$this->mapper->expects($this->any())
			->method('has')
			->with($table, $className)
			->will($this->returnValue(true));

		$this->mapper->expects($this->once())
			->method('update')
			->with($table,
				'class',
				$className, [
					'section' => 'additional',
					'priority' => 5
				]);
		$this->mapper->expects($this->never())
			->method('add');

		$this->manager->setupSettings([
			$type => $className,
		]);
	}

	/**
	 * @dataProvider settingsTypeProvider
	 * @param string $type
	 * @param string $table
	 */
	public function testSetupSettingsAdd($type, $table) {
		$this->mapper->expects($this->any())
			->method('has')
			->with($table, 'OCA\Files\Settings\Admin')
			->will($this->returnValue(false));

		$this->mapper->expects($this->once())
			->method('add')
			->with($table, [
				'class' => 'OCA\Files\Settings\Admin',
				'section' => 'additional',
				'priority' => 5
			]);

		$this->mapper->expects($this->never())
			->method('update');

		$this->manager->setupSettings([
			$type => 'OCA\Files\Settings\Admin',
		]);
	}

	public function testGetAdminSections() {
		$this->l10n
			->expects($this->any())
			->method('t')
			->will($this->returnArgument(0));

		$this->mapper->expects($this->once())
			->method('getAdminSectionsFromDB')
			->will($this->returnValue([
				['class' => \OCA\WorkflowEngine\Settings\Section::class, 'priority' => 90]
			]));

		$this->url->expects($this->exactly(6))
			->method('imagePath')
			->willReturnMap([
				['settings', 'admin.svg', '1'],
				['core', 'actions/share.svg', '2'],
				['core', 'actions/password.svg', '3'],
				['core', 'actions/settings-dark.svg', '4'],
				['settings', 'help.svg', '5'],
			]);

		$this->assertEquals([
			0 => [new Section('server', 'Basic settings', 0, '1')],
			5 => [new Section('sharing', 'Sharing', 0, '2')],
			10 => [new Section('security', 'Security', 0, '3')],
			45 => [new Section('encryption', 'Encryption', 0, '3')],
			90 => [\OC::$server->query(\OCA\WorkflowEngine\Settings\Section::class)],
			98 => [new Section('additional', 'Additional settings', 0, '4')],
			99 => [new Section('tips-tricks', 'Tips & tricks', 0, '5')],
		], $this->manager->getAdminSections());
	}

	public function testGetPersonalSections() {
		$this->l10n
			->expects($this->any())
			->method('t')
			->will($this->returnArgument(0));

		$this->mapper->expects($this->once())
			->method('getPersonalSectionsFromDB')
			->will($this->returnValue([
				['class' => \OCA\WorkflowEngine\Settings\Section::class, 'priority' => 90]
			]));

		$this->url->expects($this->exactly(5))
			->method('imagePath')
			->willReturnMap([
				['core', 'actions/info.svg', '1'],
				['settings', 'admin.svg', '2'],
				['settings', 'password.svg', '3'],
				['settings', 'change.svg', '4'],
				['core', 'actions/settings-dark.svg', '5'],
			]);

		$this->assertEquals([
			0 => [new Section('personal-info', 'Personal info', 0, '1')],
			5 => [new Section('sessions', 'Sessions', 0, '2')],
			10 => [new Section('app-passwords', 'App passwords', 0, '3')],
			15 => [new Section('sync-clients', 'Sync clients', 0, '4')],
			90 => [\OC::$server->query(\OCA\WorkflowEngine\Settings\Section::class)],
			98 => [new Section('additional', 'Additional settings', 0, '5')],
		], $this->manager->getPersonalSections());
	}

	public function testGetAdminSectionsEmptySection() {
		$this->l10n
			->expects($this->any())
			->method('t')
			->will($this->returnArgument(0));

		$this->mapper->expects($this->once())
			->method('getAdminSectionsFromDB')
			->will($this->returnValue([
			]));

		$this->url->expects($this->exactly(6))
			->method('imagePath')
			->willReturnMap([
				['settings', 'admin.svg', '1'],
				['core', 'actions/share.svg', '2'],
				['core', 'actions/password.svg', '3'],
				['core', 'actions/settings-dark.svg', '4'],
				['settings', 'help.svg', '5'],
			]);

		$this->assertEquals([
			0 => [new Section('server', 'Basic settings', 0, '1')],
			5 => [new Section('sharing', 'Sharing', 0, '2')],
			10 => [new Section('security', 'Security', 0, '3')],
			45 => [new Section('encryption', 'Encryption', 0, '3')],
			98 => [new Section('additional', 'Additional settings', 0, '4')],
			99 => [new Section('tips-tricks', 'Tips & tricks', 0, '5')],
		], $this->manager->getAdminSections());
	}

	public function testGetPersonalSectionsEmptySection() {
		$this->l10n
			->expects($this->any())
			->method('t')
			->will($this->returnArgument(0));

		$this->mapper->expects($this->once())
			->method('getPersonalSectionsFromDB')
			->will($this->returnValue([]));

		$this->url->expects($this->exactly(5))
			->method('imagePath')
			->willReturnMap([
				['core', 'actions/info.svg', '1'],
				['settings', 'admin.svg', '2'],
				['settings', 'password.svg', '3'],
				['settings', 'change.svg', '4'],
				['core', 'actions/settings-dark.svg', '5'],
			]);

		$this->assertEquals([
			0 => [new Section('personal-info', 'Personal info', 0, '1')],
			5 => [new Section('sessions', 'Sessions', 0, '2')],
			10 => [new Section('app-passwords', 'App passwords', 0, '3')],
			15 => [new Section('sync-clients', 'Sync clients', 0, '4')],
			98 => [new Section('additional', 'Additional settings', 0, '5')],
		], $this->manager->getPersonalSections());
	}

	public function testGetAdminSettings() {
		$this->mapper->expects($this->any())
			->method('getAdminSettingsFromDB')
			->will($this->returnValue([]));

		$this->assertEquals([
			0 => [new Sharing($this->config)],
		], $this->manager->getAdminSettings('sharing'));
	}

	public function testGetPersonalSettings() {
		$this->mapper->expects($this->any())
			->method('getPersonalSettingsFromDB')
			->will($this->returnValue([]));

		$this->assertEquals([
			5 => [new AppPasswords()],
		], $this->manager->getPersonalSettings('app-passwords'));
	}
}
