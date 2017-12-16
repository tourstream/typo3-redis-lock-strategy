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
use TYPO3\CMS\Core\Locking\LockingStrategyInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

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
     * @expectedException \TYPO3\CMS\Core\Locking\Exception\LockCreateException
     * @expectedExceptionMessage no configuration for redis lock strategy found
     */
    public function shouldThrowExceptionBecauseConfigIsMissing()
    {
        $this->lockFactory->createLocker('test');
    }

    /**
     * @test
     * @expectedException \TYPO3\CMS\Core\Locking\Exception\LockCreateException
     * @expectedExceptionMessage no configuration for redis lock strategy found
     */
    public function shouldThrowExceptionBecauseConfigIsNotAnArray()
    {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['redis_lock'] = 'test';

        $this->lockFactory->createLocker('test');
    }

    /**
     * @test
     * @expectedException \TYPO3\CMS\Core\Locking\Exception\LockCreateException
     * @expectedExceptionMessage no host for redis lock strategy found
     */
    public function shouldThrowExceptionBecauseConfigHasNoHost()
    {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['redis_lock'] = [];

        $this->lockFactory->createLocker('test');
    }

    /**
     * @test
     * @expectedException \TYPO3\CMS\Core\Locking\Exception\LockCreateException
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
    public function shouldConnectAndAcquireAExistingLock()
    {
        $subject = uniqid();

        $locker = $this->getLocker($subject);

        self::assertTrue($locker->acquire());
    }

    /**
     * @test
     */
    public function shouldConnectAndAcquireALock()
    {
        $subject = uniqid();

        $name = sprintf('lock:name:%s', $subject);

        $locker = $this->getLocker($subject);

        $redis = $this->getRedisClient();

        self::assertTrue($locker->acquire());

        self::assertTrue($redis->exists($name));
    }

    /**
     * @test
     */
    public function shouldConnectAndCheckIfLockIsAcquired()
    {
        $subject = uniqid();

        $locker = $this->getLocker($subject);

        $locker->acquire();

        self::assertTrue($locker->isAcquired());
    }

    /**
     * @test
     */
    public function shouldConnectAndDestroyALock()
    {
        $subject = uniqid();

        $name = sprintf('lock:name:%s', $subject);

        $locker = $this->getLocker($subject);

        $redis = $this->getRedisClient();

        $locker->destroy();

        self::assertFalse($redis->exists($name));
    }

    /**
     * @test
     */
    public function shouldAcquireNonBlockingAndReleaseMoreLocks()
    {
        $subject = uniqid();
        $capabilities = LockingStrategyInterface::LOCK_CAPABILITY_EXCLUSIVE | LockingStrategyInterface::LOCK_CAPABILITY_NOBLOCK;

        $locker1 = $this->getLocker($subject);
        $locker2 = $this->getLocker($subject);
        $locker3 = $this->getLocker($subject);

        self::assertTrue($locker1->acquire($capabilities));
        self::expectException('\TYPO3\CMS\Core\Locking\Exception\LockAcquireWouldBlockException') && $locker2->acquire($capabilities);
        self::expectException('\TYPO3\CMS\Core\Locking\Exception\LockAcquireWouldBlockException') && $locker3->acquire($capabilities);

        self::assertTrue($locker1->isAcquired());
        self::assertFalse($locker2->isAcquired());
        self::assertFalse($locker3->isAcquired());

        self::assertTrue($locker1->release());

        self::assertFalse($locker1->isAcquired());
        self::assertFalse($locker2->isAcquired());
        self::assertFalse($locker3->isAcquired());

        self::assertTrue($locker2->acquire($capabilities));

        self::assertFalse($locker1->isAcquired());
        self::assertTrue($locker2->isAcquired());
        self::assertFalse($locker3->isAcquired());

        self::assertTrue($locker3->release());

        self::assertFalse($locker1->isAcquired());
        self::assertTrue($locker2->isAcquired());
        self::assertFalse($locker3->isAcquired());

        self::assertFalse($locker1->acquire($capabilities));

        self::assertFalse($locker1->isAcquired());
        self::assertTrue($locker2->isAcquired());
        self::assertFalse($locker3->isAcquired());

        self::assertTrue($locker2->release());

        self::assertFalse($locker1->isAcquired());
        self::assertFalse($locker2->isAcquired());
        self::assertFalse($locker3->isAcquired());

        self::assertTrue($locker3->acquire($capabilities));

        self::assertFalse($locker1->isAcquired());
        self::assertFalse($locker2->isAcquired());
        self::assertTrue($locker3->isAcquired());
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

    /**
     * @param string $id Locker id
     *
     * @return \TYPO3\CMS\Core\Locking\LockingStrategyInterface
     */
    private function getLocker($id)
    {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['redis_lock'] = [
            'host'     => $this->redisHost,
            'database' => $this->redisDatabase,
        ];

        return $this->lockFactory->createLocker($id);
    }

}
