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

use TYPO3\CMS\Core\Locking\Exception;
use TYPO3\CMS\Core\Locking\LockingStrategyInterface;

/**
 * @author Alexander Miehe <alexander.miehe@tourstream.eu>
 */
class RedisLockStrategy implements LockingStrategyInterface
{
    /**
     * @var \Redis
     */
    private $redis;

    /**
     * @var string
     */
    private $subject;

    /**
     * @var int
     */
    private $ttl;

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
            throw new Exception('no configuration for redis lock strategy found');
        }

        if (!\array_key_exists('host', $config)) {
            throw new Exception('no host for redis lock strategy found');
        }
        $port = 6379;

        if (\array_key_exists('port', $config)) {
            $port = (int) $config['port'];
        }

        if (!\array_key_exists('database', $config)) {
            throw new Exception('no database for redis lock strategy found');
        }

        $this->ttl = 360;
        if (\array_key_exists('ttl', $config)) {
            $this->ttl = (int) $config['ttl'];
        }

        $this->subject = $subject;
        $this->redis   = new \Redis();
        $this->redis->connect($config['host'], $port);

        if (\array_key_exists('auth', $config)) {
            $this->redis->auth($config['auth']);
        }

        $this->redis->select((int) $config['database']);
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
        if ($this->isAcquired()) {
            return true;
        }

        return $this->redis->set($this->subject, uniqid(), $this->ttl);
    }

    /**
     * @inheritdoc
     */
    public function isAcquired()
    {
        return $this->redis->exists($this->subject);
    }

    /**
     * @inheritdoc
     */
    public function destroy()
    {
        $this->release();
    }

    /**
     * @inheritdoc
     */
    public function release()
    {
        if (!$this->isAcquired()) {
            return true;
        }

        return $this->redis->del($this->subject) === 1;
    }

}