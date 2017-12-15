<?php

namespace Tourstream\RedisLockStrategy;

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

use TYPO3\CMS\Core\Locking\Exception\LockAcquireException;
use TYPO3\CMS\Core\Locking\Exception\LockCreateException;
use TYPO3\CMS\Core\Locking\Exception\LockAcquireWouldBlockException;
use TYPO3\CMS\Core\Locking\LockingStrategyInterface;

/**
 * @author Alexander Miehe <alexander.miehe@tourstream.eu>
 */
class RedisLockStrategy implements LockingStrategyInterface
{
    /**
     * @var \Redis A key-value data store
     */
    private $redis;

    /**
     * @var string The locking subject, i.e. a string to discriminate the lock
     */
    private $subject;

    /**
     * @var string The key used for the lock itself
     */
    private $name;

    /**
     * @var string The key used for the mutex, i.e. a list
     */
    private $mutex;

    /**
     * @var string The value used for the lock
     */
    private $value;

    /**
     * @var boolean TRUE if lock is acquired
     */
    private $isAcquired = false;

    /**
     * @var int Seconds the lock remains persistent
     */
    private $ttl = 3600;

    /**
     * @var int Seconds to wait for a lock
     */
    private $blTo = 60;

    /**
     * @inheritdoc
     */
    public function __construct($subject)
    {
        $config = null;

        if (\array_key_exists('redis_lock', $GLOBALS['TYPO3_CONF_VARS']['SYS'])) {
            $config = $GLOBALS['TYPO3_CONF_VARS']['SYS']['redis_lock'];
        }

        if (!\is_array($config)) {
            throw new LockCreateException('no configuration for redis lock strategy found');
        }

        if (!\array_key_exists('host', $config)) {
            throw new LockCreateException('no host for redis lock strategy found');
        }
        $port = 6379;

        if (\array_key_exists('port', $config)) {
            $port = (int) $config['port'];
        }

        if (!\array_key_exists('database', $config)) {
            throw new LockCreateException('no database for redis lock strategy found');
        }

        if (\array_key_exists('ttl', $config)) {
            $this->ttl = (int) $config['ttl'];
        }

        $this->redis   = new \Redis();
        $this->redis->connect($config['host'], $port);

        if (\array_key_exists('auth', $config)) {
            $this->redis->auth($config['auth']);
        }

        $this->redis->select((int) $config['database']);

        $this->subject = $subject;
        $this->name = sprintf('lock:name:%s', $subject);
        $this->mutex = sprintf('lock:mutex:%s', $subject);
        $this->value = uniqid();

        $this->init();
    }

    /**
     * @inheritdoc
     */
    public static function getCapabilities()
    {
        return self::LOCK_CAPABILITY_EXCLUSIVE | self::LOCK_CAPABILITY_NOBLOCK;
    }

    /**
     * @inheritdoc
     */
    public static function getPriority()
    {
        return 100;
    }

    /**
     * @inheritdoc
     */
    public function acquire($mode = self::LOCK_CAPABILITY_EXCLUSIVE)
    {
        if ($this->isAcquired) {
            return true;
        }

        if ($mode & self::LOCK_CAPABILITY_EXCLUSIVE) {

            if ($mode & self::LOCK_CAPABILITY_NOBLOCK) {

                // this does not block
                $this->isAcquired = $this->lock();

                if (!$this->isAcquired) {
                    throw new LockAcquireWouldBlockException('could not acquire lock');
                }
            } else {

                // this blocks till the lock gets released
                $this->wait();

                $this->isAcquired = $this->lock();

                if (!$this->isAcquired) {
                    throw new LockAcquireException('could not acquire lock');
                }
            }

        } else {
            throw new LockAcquireException('insufficient capabilities');
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function isAcquired()
    {
        return $this->isAcquired;
    }

    /**
     * @inheritdoc
     */
    public function destroy()
    {
        $this->redis->del($this->name);
        $this->redis->del($this->mutex);
    }

    /**
     * @inheritdoc
     */
    public function release()
    {
        if (!$this->isAcquired) {
            return true;
        }

        // discard return code
        // we want to release the lock even in error case
        // to get a more resilient behaviour
        $this->unlockAndSignal();

        $this->isAcquired = false;

        return !$this->isAcquired;
    }

    /**
     * Initialize the synchronization object, i.e. a simple list with some random element
     *
     * @return boolean TRUE on sucess, FALSE otherwise
     */
    private function init()
    {
        $script = '
            if not redis.call("EXISTS", KEYS[1], KEYS[2]) == 2 then
                return redis.call("RPUSH", KEYS[2], ARGV[1]) and redis.call("EXPIRE", KEYS[2], ARGV[2])
            else
                return 0
            end
        ';
        return (bool) $this->redis->eval($script, [$this->name, $this->mutex, $this->value, $this->ttl], 2);
    }

    /**
     * Try to get the lock
     * N.B. this a is non-blocking operation
     *
     * @return boolean TRUE on success, FALSE otherwise
     */
    private function lock()
    {
        $this->value = uniqid();

        // option NX: set value iff key is not present
        return $this->redis->set($this->name, $this->value, ['NX', 'PX' => $this->ttl]);
    }

    /**
     * Wait for the lock being released
     * N.B. this a is blocking operation
     *
     * @return string The value, FALSE otherwise
     */
    private function wait()
    {
        $result = $this->redis->blPop([$this->mutex], $this->blTo);
        return $result ? $result[1] : false;
    }

    /**
     * Try to unlock the mutex and if succeeds, signal the waiting locks
     *
     * @return boolean TRUE on success, FALSE otherwise
     */
    private function unlockAndSignal()
    {
        $script = '
            if redis.call("GET", KEYS[1]) == ARGV[1] and redis.call("DEL", KEYS[1]) == 1 then
                return redis.call("RPUSH", KEYS[2], ARGV[1]) and redis.call("EXPIRE", KEYS[2], ARGV[2])
            else
                return 0
            end
        ';
        return (bool) $this->redis->eval($script, [$this->name, $this->mutex, $this->value, $this->ttl], 2);
    }

}
