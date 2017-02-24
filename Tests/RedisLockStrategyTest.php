<?php

namespace Tourstream\RedisLockStrategy\Tests;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017 Alexander Miehe (alexander.miehe@tourstream.eu)
 *  All rights reserved
 *
 *  You may not remove or change the name of the author above. See:
 *  http://www.gnu.org/licenses/gpl-faq.html#IWantCredit
 *
 *  This script is part of the Typo3 project. The Typo3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *  A copy is found in the LICENSE and distributed with these scripts.
 *
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use TYPO3\CMS\Core\Locking\LockFactory;
use TYPO3\CMS\Core\Tests\FunctionalTestCase;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * @author Alexander Miehe <alexander.miehe@tourstream.eu>
 *
 * @covers \Tourstream\RedisLockStrategy\RedisLockStrategy
 */
class RedisLockStrategyTest extends FunctionalTestCase
{
    /**
     * @var LockFactory
     */
    private $lockFactory;
    private $redisHost;
    private $redisDatabase;

    /**
     * @test
     * @expectedException \TYPO3\CMS\Core\Locking\Exception
     * @expectedExceptionMessage no configuration for redis lock strategy found
     */
    public function shouldThrowExceptionBecauseConfigIsMissing()
    {
        $this->lockFactory->createLocker('test');
    }

    /**
     * @test
     * @expectedException \TYPO3\CMS\Core\Locking\Exception
     * @expectedExceptionMessage no configuration for redis lock strategy found
     */
    public function shouldThrowExceptionBecauseConfigIsNotAnArray()
    {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['redis_lock'] = 'test';
        $this->lockFactory->createLocker('test');
    }

    /**
     * @test
     * @expectedException \TYPO3\CMS\Core\Locking\Exception
     * @expectedExceptionMessage no host for redis lock strategy found
     */
    public function shouldThrowExceptionBecauseConfigHasNoHost()
    {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['redis_lock'] = [];

        $this->lockFactory->createLocker('test');
    }

    /**
     * @test
     * @expectedException \TYPO3\CMS\Core\Locking\Exception
     * @expectedExceptionMessage no database for redis lock strategy found
     */
    public function shouldThrowExceptionBecauseConfigHasNoDatabase()
    {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['redis_lock'] = [
            'host' => $this->redisHost,
        ];

        $this->lockFactory->createLocker('test');
    }

    /**
     * @test
     */
    public function shouldConnectAndAcquireALock()
    {
        $id = uniqid();

        $GLOBALS['TYPO3_CONF_VARS']['SYS']['redis_lock'] = [
            'host'     => $this->redisHost,
            'port'     => 6379,
            'database' => $this->redisDatabase,
        ];

        $locker = $this->lockFactory->createLocker($id);

        $redis = $this->getRedisClient();

        $redis->set($id, 'testvalue');

        self::assertTrue($locker->acquire());
    }


    /**
     * @test
     */
    public function shouldConnectAndAcquireAExistingLock()
    {
        $id = uniqid();

        $GLOBALS['TYPO3_CONF_VARS']['SYS']['redis_lock'] = [
            'host'     => $this->redisHost,
            'port'     => 6379,
            'database' => $this->redisDatabase,
        ];

        $locker = $this->lockFactory->createLocker($id);

        self::assertTrue($locker->acquire());

        $redis = $this->getRedisClient();

        self::assertTrue($redis->exists($id));
    }

    /**
     * @test
     */
    public function shouldConnectAndCheckIfLockIsAcquired()
    {
        $id = uniqid();

        $GLOBALS['TYPO3_CONF_VARS']['SYS']['redis_lock'] = [
            'host'     => $this->redisHost,
            'port'     => 6379,
            'database' => $this->redisDatabase,
        ];

        $locker = $this->lockFactory->createLocker($id);

        $redis = $this->getRedisClient();

        $redis->set($id, 'testvalue');

        self::assertTrue($locker->isAcquired());
    }

    /**
     * @test
     */
    public function shouldConnectAndDestroyALock()
    {
        $id = uniqid();

        $GLOBALS['TYPO3_CONF_VARS']['SYS']['redis_lock'] = [
            'host'     => $this->redisHost,
            'port'     => 6379,
            'database' => $this->redisDatabase,
        ];

        $locker = $this->lockFactory->createLocker($id);

        $redis = $this->getRedisClient();

        $redis->set($id, 'testvalue');

        $locker->destroy();

        self::assertFalse($redis->exists($id));
    }

    /**
     * @test
     */
    public function shouldConnectAndDestroyANotExistingLock()
    {
        $id = uniqid();

        $GLOBALS['TYPO3_CONF_VARS']['SYS']['redis_lock'] = [
            'host'     => $this->redisHost,
            'port'     => 6379,
            'database' => $this->redisDatabase,
        ];

        $locker = $this->lockFactory->createLocker($id);

        $redis = $this->getRedisClient();

        $locker->destroy();

        self::assertFalse($redis->exists($id));
    }

    protected function setUp()
    {
        $this->testExtensionsToLoad[] = 'typo3conf/ext/redis_lock_strategy';
        $this->redisHost              = getenv('typo3RedisHost');
        $this->redisDatabase          = getenv('typo3RedisDatabase');

        parent::setUp();

        $this->lockFactory = GeneralUtility::makeInstance(LockFactory::class);
    }

    protected function tearDown()
    {
        parent::tearDown();

        $this->getRedisClient()->flushDB();
    }

    /**
     * @return \Redis
     */
    private function getRedisClient()
    {
        $redis = new \Redis();
        $redis->connect($this->redisHost);
        $redis->select($this->redisDatabase);

        return $redis;
    }

}
